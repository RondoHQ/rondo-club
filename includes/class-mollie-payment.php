<?php
/**
 * Mollie Payment Service
 *
 * Creates payment links via the Mollie Payments API.
 * Stores the checkout URL and payment ID on the invoice for idempotent reuse.
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
	 * Create a Mollie payment and return the checkout URL.
	 *
	 * Idempotent: if `_mollie_payment_id` and `payment_link` are both stored on the
	 * invoice, the existing URL is returned without a new API call.
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
		$existing_payment_id = get_post_meta( $invoice_id, '_mollie_payment_id', true );
		if ( ! empty( $existing_payment_id ) ) {
			$existing_url = get_field( 'payment_link', $invoice_id );
			if ( ! empty( $existing_url ) ) {
				return $existing_url;
			}
			// Payment ID exists but URL is missing — fall through to create a new payment.
		}

		// 3. Guard: API key configured
		$config  = new FinanceConfig();
		$api_key = $config->get_mollie_api_key();
		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'mollie_not_configured',
				__( 'Mollie API-sleutel niet geconfigureerd.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// 4. Load invoice data
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$total_amount   = get_field( 'total_amount', $invoice_id );

		// 5. Format amount — always use number_format() with 4 args to avoid locale issues.
		$amount_string = number_format( (float) $total_amount, 2, '.', '' );

		// 6. Build payload
		$payload = [
			'amount'      => [
				'currency' => 'EUR',
				'value'    => $amount_string,
			],
			'description' => 'Factuur ' . $invoice_number,
			'redirectUrl' => home_url( '/' ),
		];

		// 7. Conditionally add webhookUrl — omit on localhost and .local environments.
		$site_url = get_site_url();
		if ( false === strpos( $site_url, 'localhost' ) && false === strpos( $site_url, '.local' ) ) {
			$payload['webhookUrl'] = rest_url( 'rondo/v1/mollie/webhook' );
		}

		// 8. Call Mollie SDK
		try {
			$mollie_client = new MollieClient();
			$mollie        = $mollie_client->get();
			$payment       = $mollie->payments->create( $payload );
		} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			error_log( 'Mollie API exception: ' . $e->getMessage() );
			return new \WP_Error(
				'mollie_api_error',
				sprintf( __( 'Mollie betaling aanmaken mislukt: %s', 'rondo' ), $e->getMessage() ),
				[ 'status' => 502 ]
			);
		}

		// 9. Extract checkout URL
		$checkout_url = $payment->getCheckoutUrl();
		if ( empty( $checkout_url ) ) {
			error_log( 'Mollie payment created but checkout URL is empty for invoice ' . $invoice_id );
			return new \WP_Error(
				'mollie_no_checkout_url',
				__( 'Geen checkout URL in Mollie response.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		// 10. Store results
		update_field( 'payment_link', $checkout_url, $invoice_id );
		update_post_meta( $invoice_id, '_mollie_payment_id', $payment->id );

		// 11. Return checkout URL
		return $checkout_url;
	}
}
