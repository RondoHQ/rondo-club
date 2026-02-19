<?php
/**
 * Mollie Webhook Handler
 *
 * Receives Mollie webhook POST events and idempotently transitions invoices
 * to `rondo_paid` when payment is confirmed.
 *
 * Supports two lookup paths:
 * - Path 1 (new): Installment reverse-lookup via _mollie_pid_{payment_id} meta.
 * - Path 2 (legacy): Full-payment lookup via _mollie_payment_id meta.
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
 * Mollie sends a webhook with the payment ID; this class re-fetches the
 * payment from the Mollie API to verify its status before updating the invoice.
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
	 * Re-fetches the payment from the Mollie API (never trusts POST body alone),
	 * then routes to the installment handler (Path 1) or the legacy full-payment
	 * handler (Path 2). Always returns HTTP 200 to prevent Mollie retry storms.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return \WP_REST_Response Response with ok:true (always 200).
	 */
	public function handle_webhook( \WP_REST_Request $request ) {
		// 1. Extract payment ID.
		$payment_id = sanitize_text_field( $request->get_param( 'id' ) );

		// 2. Guard: missing payment ID.
		if ( empty( $payment_id ) ) {
			error_log( 'Mollie webhook: missing payment ID' );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// 3. Re-fetch payment from Mollie API (WHKT-02 — never trust POST body alone).
		try {
			$mollie_client = new MollieClient();
			$payment       = $mollie_client->get()->payments->get( $payment_id );
		} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			error_log( 'Mollie webhook: API exception for payment ' . $payment_id . ': ' . $e->getMessage() );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// 4. Only proceed for confirmed paid payments.
		// isPaid() checks paidAt which is more reliable than comparing status string.
		if ( ! $payment->isPaid() ) {
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// 5. Path 1 — Installment reverse-lookup (new).
		// Invoices created with the installment plan system store a reverse-lookup
		// meta key (_mollie_pid_{payment_id}) pointing to the installment number.
		$installment_posts = get_posts( [
			'post_type'      => 'rondo_invoice',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_mollie_pid_' . $payment_id,
					'compare' => 'EXISTS',
				],
			],
		] );

		if ( ! empty( $installment_posts ) ) {
			$invoice_id          = (int) $installment_posts[0];
			$installment_number  = (int) get_post_meta( $invoice_id, '_mollie_pid_' . $payment_id, true );
			return $this->handle_installment_paid( $invoice_id, $installment_number, $payment_id );
		}

		// 6. Path 2 — Legacy full-payment lookup (existing behavior, unchanged).
		// Invoices created before Phase 193 store the payment ID in _mollie_payment_id.
		// post_status => 'any' is required because invoice statuses (rondo_sent, rondo_overdue)
		// are custom and not included in the default query.
		$query = new \WP_Query(
			[
				'post_type'      => 'rondo_invoice',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => [
					[
						'key'   => '_mollie_payment_id',
						'value' => $payment_id,
					],
				],
			]
		);

		// 7. Guard: no matching invoice.
		if ( ! $query->have_posts() ) {
			error_log( 'Mollie webhook: no invoice found for payment ' . $payment_id );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		$invoice = $query->posts[0];

		// 8. Idempotency check (WHKT-04): already paid — no-op.
		if ( 'rondo_paid' === $invoice->post_status ) {
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// 9. Transition invoice to paid.
		wp_update_post(
			[
				'ID'          => $invoice->ID,
				'post_status' => 'rondo_paid',
			]
		);

		// Update ACF status field (field is named 'status' per acf-json and RestInvoices pattern).
		update_field( 'status', 'paid', $invoice->ID );

		// 10. Return success.
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
