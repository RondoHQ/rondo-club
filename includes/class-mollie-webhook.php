<?php
/**
 * Mollie Webhook Handler
 *
 * Receives Mollie webhook POST events and idempotently transitions invoices
 * to `rondo_paid` when payment is confirmed.
 *
 * Supports two lookup paths:
 * - Installment payment links: Reverse-lookup via _mollie_pid_{pl_xxx} meta.
 * - Full payment links: Lookup via _mollie_payment_link_id.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles incoming Mollie webhook notifications.
 *
 * Registers a public REST endpoint at POST /wp-json/rondo/v1/mollie/webhook.
 * Mollie sends a webhook with the payment-link ID; this class re-fetches the
 * payment link from the correct Mollie account before updating the invoice.
 */
class MollieWebhook {

	/**
	 * Constructor
	 *
	 * Registers the REST API route via the rest_api_init action.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes.
	 *
	 * Registers a single public POST route for Mollie webhook notifications.
	 */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/mollie/webhook',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_webhook' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle an incoming Mollie webhook notification.
	 *
	 * Supports payment-link webhooks (`pl_xxx`) for both installment and full-payment flows.
	 *
	 * Always returns HTTP 200 to prevent Mollie retry storms.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return \WP_REST_Response Response with ok:true (always 200).
	 */
	public function handle_webhook( \WP_REST_Request $request ) {
		// 1. Extract payment-link ID.
		$mollie_id = sanitize_text_field( $request->get_param( 'id' ) );

		if ( empty( $mollie_id ) ) {
			error_log( 'Mollie webhook: missing ID' );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		if ( ! str_starts_with( $mollie_id, 'pl_' ) ) {
			error_log( 'Mollie webhook: unsupported legacy ID ' . $mollie_id );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		return $this->handle_payment_link_webhook( $mollie_id );
	}

	/**
	 * Handle a payment link webhook notification (Path 0).
	 *
	 * Re-fetches the payment link from Mollie to verify it is paid, then routes
	 * to the correct handler:
	 * - Path 0a (installments): Reverse-lookup via _mollie_pid_{pl_xxx} meta.
	 *   Routes to handle_installment_paid() for installment tracking and auto-creation.
	 * - Path 0b (full payment / discipline): Lookup via _mollie_payment_link_id meta.
	 *   Transitions invoice directly to rondo_paid.
	 *
	 * @param string $payment_link_id Mollie payment link ID (pl_xxx).
	 * @return \WP_REST_Response Response with ok:true (always 200).
	 */
	private function handle_payment_link_webhook( string $payment_link_id ): \WP_REST_Response {
		$invoice_id = 0;
		$installment_number = 0;

		$installment_posts = get_posts(
			[
				'post_type'      => 'rondo_invoice',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => '_mollie_pid_' . $payment_link_id,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		if ( ! empty( $installment_posts ) ) {
			$invoice_id         = (int) $installment_posts[0];
			$installment_number = (int) get_post_meta( $invoice_id, '_mollie_pid_' . $payment_link_id, true );
		}

		if ( $invoice_id <= 0 ) {
			$posts = get_posts(
				[
					'post_type'      => 'rondo_invoice',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [
						[
							'key'   => '_mollie_payment_link_id',
							'value' => $payment_link_id,
						],
					],
				]
			);

			if ( ! empty( $posts ) ) {
				$invoice_id = (int) $posts[0];
			}
		}

		if ( $invoice_id <= 0 ) {
			error_log( 'Mollie webhook: no invoice found for payment link ' . $payment_link_id );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		$config     = new \Rondo\Config\FinanceConfig();
		$account_id = (string) get_post_meta( $invoice_id, '_payment_account_id', true );
		$api_key    = $config->get_mollie_api_key_for_account( $account_id );
		if ( '' === $api_key ) {
			error_log( 'Mollie webhook: missing API key for invoice ' . $invoice_id . ' and payment link ' . $payment_link_id );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		try {
			$mollie_client = new MollieClient( $api_key );
			$payment_link  = $mollie_client->get()->paymentLinks->get( $payment_link_id );
		} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			error_log( 'Mollie webhook: API exception for payment link ' . $payment_link_id . ': ' . $e->getMessage() );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// Only proceed for confirmed paid payment links.
		if ( ! $payment_link->isPaid() ) {
			return rest_ensure_response( [ 'ok' => true ] );
		}

		if ( $installment_number > 0 ) {
			return $this->handle_installment_paid( $invoice_id, $installment_number, $payment_link_id );
		}

		$invoice = get_post( $invoice_id );
		if ( ! $invoice || 'rondo_paid' === $invoice->post_status ) {
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// Transition invoice to paid.
		wp_update_post(
			[
				'ID'          => $invoice_id,
				'post_status' => 'rondo_paid',
			]
		);

		// Update ACF status field.
		update_field( 'status', 'paid', $invoice_id );

		return rest_ensure_response( [ 'ok' => true ] );
	}

	/**
	 * Handle a confirmed installment payment.
	 *
	 * Marks the specific installment as betaald, checks if all installments
	 * are now paid (gating invoice completion), and creates the next installment
	 * payment automatically if more remain.
	 *
	 * @param int    $invoice_id          Invoice post ID.
	 * @param int    $n                   Installment number that was just paid (1-based).
	 * @param string $payment_id          Mollie payment ID (for logging).
	 * @return \WP_REST_Response Response with ok:true (always 200).
	 */
	private function handle_installment_paid( int $invoice_id, int $n, string $payment_id ): \WP_REST_Response {
		// 1. Idempotency check: already marked betaald — no-op.
		$current_status = get_post_meta( $invoice_id, '_installment_' . $n . '_status', true );
		if ( 'betaald' === $current_status ) {
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// 2. Mark this installment as paid.
		update_post_meta( $invoice_id, '_installment_' . $n . '_status', 'betaald' );

		// 3. All-paid check: read total installment count and loop through all statuses.
		$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		$count = max( 1, $count ); // Floor at 1.

		$all_paid = true;
		for ( $i = 1; $i <= $count; $i++ ) {
			$status = get_post_meta( $invoice_id, '_installment_' . $i . '_status', true );
			if ( 'betaald' !== $status ) {
				$all_paid = false;
				break;
			}
		}

		// 4. If all installments are paid, transition invoice to fully paid.
		if ( $all_paid ) {
			wp_update_post(
				[
					'ID'          => $invoice_id,
					'post_status' => 'rondo_paid',
				]
			);
			// Update ACF status field.
			update_field( 'status', 'paid', $invoice_id );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// 5. More installments remain — create next installment payment automatically.
		$next = $n + 1;
		if ( $next <= $count ) {
			// Idempotency guard: only create if next payment not yet created.
			$next_payment_id = get_post_meta( $invoice_id, '_installment_' . $next . '_mollie_payment_id', true );
			if ( empty( $next_payment_id ) ) {
				try {
					$result = InstallmentPaymentService::create_payment( $invoice_id, $next );
					if ( is_wp_error( $result ) ) {
						error_log( 'Mollie webhook: failed to create installment ' . $next . ' payment for invoice ' . $invoice_id . ': ' . $result->get_error_message() );
					}
				} catch ( \Throwable $e ) {
					// Never propagate — current installment is already marked paid.
					error_log( 'Mollie webhook: exception creating installment ' . $next . ' for invoice ' . $invoice_id . ': ' . $e->getMessage() );
				}
			}
		}

		// 6. Return success.
		return rest_ensure_response( [ 'ok' => true ] );
	}
}
