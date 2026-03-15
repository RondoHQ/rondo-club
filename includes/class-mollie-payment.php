<?php
/**
 * Mollie Payment Service
 *
 * Creates payment links via the Mollie Payment Links API.
 * Stores the checkout URL and payment link ID on the invoice for idempotent reuse.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mollie payment service class
 *
 * Pure service class — no constructor hooks, no REST routes.
 * Called directly by RestInvoices::send_invoice() for Mollie payment provider routing.
 */
class MolliePayment {

	/**
	 * Create a Mollie payment link and return the checkout URL.
	 *
	 * Uses the Mollie Payment Links API (POST /v2/payment-links) which creates a
	 * persistent link that remains valid until paid or archived — unlike regular
	 * Mollie payments (POST /v2/payments) which expire in ~15 minutes.
	 *
	 * Idempotent: if `_mollie_payment_link_id` and `payment_link` are both stored on
	 * the invoice, the existing URL is returned without a new API call.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return string|\WP_Error Checkout URL on success, WP_Error on failure.
	 */
	public function create_payment_link( int $invoice_id ) {
		// 1. Validate invoice
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'invalid_invoice',
				__( 'Ongeldige factuur.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// 2. Idempotency check
		$existing_link_id = get_post_meta( $invoice_id, '_mollie_payment_link_id', true );
		if ( ! empty( $existing_link_id ) ) {
			$existing_url = get_field( 'payment_link', $invoice_id );
			if ( ! empty( $existing_url ) ) {
				return $existing_url;
			}
			// Payment link ID exists but URL is missing — fall through to create a new link.
		}

		// 3. Guard: account API key configured
		$config     = new FinanceConfig();
		$account_id = (string) get_post_meta( $invoice_id, '_payment_account_id', true );
		$api_key    = $config->get_mollie_api_key_for_account( $account_id );
		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'mollie_not_configured',
				__( 'Voor deze factuur is geen Mollie API-sleutel geconfigureerd.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// 4. Load invoice data
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$total_amount   = get_field( 'total_amount', $invoice_id );

		// 5. Format amount — always use number_format() with 4 args to avoid locale issues.
		$amount_string = number_format( (float) $total_amount, 2, '.', '' );

		// 6. Determine redirect URL — configured URL or fallback to homepage.
		$redirect_url = $config->get_mollie_redirect_url();
		if ( empty( $redirect_url ) ) {
			$redirect_url = home_url( '/' );
		}

		// 7. Build payload
		$payload = [
			'amount'      => [
				'currency' => 'EUR',
				'value'    => $amount_string,
			],
			'description' => 'Factuur ' . $invoice_number,
			'redirectUrl' => $redirect_url,
		];

		// 8. Conditionally add webhookUrl — omit on localhost and .local environments.
		$site_url = get_site_url();
		if ( strpos( $site_url, 'localhost' ) === false && strpos( $site_url, '.local' ) === false ) {
			$payload['webhookUrl'] = rest_url( 'rondo/v1/mollie/webhook' );
		}

		// 9. Call Mollie Payment Links API — creates a persistent link (no expiry by default).
		try {
			$mollie_client = new MollieClient( $api_key );
			$mollie        = $mollie_client->get();
			$payment_link  = $mollie->paymentLinks->create( $payload );
		} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			error_log( 'Mollie API exception: ' . $e->getMessage() );
			return new \WP_Error(
				'mollie_api_error',
				// translators: %s is the Mollie API error message.
			sprintf( __( 'Mollie betaallink aanmaken mislukt: %s', 'rondo' ), $e->getMessage() ),
				[ 'status' => 502 ]
			);
		}

		// 10. Extract checkout URL
		$checkout_url = $payment_link->getCheckoutUrl();
		if ( empty( $checkout_url ) ) {
			error_log( 'Mollie payment link created but checkout URL is empty for invoice ' . $invoice_id );
			return new \WP_Error(
				'mollie_no_checkout_url',
				__( 'Geen checkout URL in Mollie response.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		// 11. Store results
		update_field( 'payment_link', $checkout_url, $invoice_id );
		update_post_meta( $invoice_id, '_mollie_payment_link_id', $payment_link->id );

		// 12. Return checkout URL
		return $checkout_url;
	}
}
