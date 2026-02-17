<?php
/**
 * Mollie Webhook Handler
 *
 * Receives Mollie webhook POST events and idempotently transitions invoices
 * to `rondo_paid` when payment is confirmed.
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
	 * then transitions the matching invoice to `rondo_paid` when payment is confirmed.
	 * Always returns HTTP 200 to prevent Mollie retry storms.
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

		// 5. Find invoice by _mollie_payment_id meta.
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

		// 6. Guard: no matching invoice.
		if ( ! $query->have_posts() ) {
			error_log( 'Mollie webhook: no invoice found for payment ' . $payment_id );
			return rest_ensure_response( [ 'ok' => true ] );
		}

		$invoice = $query->posts[0];

		// 7. Idempotency check (WHKT-04): already paid — no-op.
		if ( 'rondo_paid' === $invoice->post_status ) {
			return rest_ensure_response( [ 'ok' => true ] );
		}

		// 8. Transition invoice to paid.
		wp_update_post(
			[
				'ID'          => $invoice->ID,
				'post_status' => 'rondo_paid',
			]
		);

		// Update ACF status field (field is named 'status' per acf-json and RestInvoices pattern).
		update_field( 'status', 'paid', $invoice->ID );

		// 9. Return success.
		return rest_ensure_response( [ 'ok' => true ] );
	}
}
