<?php
/**
 * Installment Payment Service
 *
 * Shared service for creating Mollie payments for invoice installments.
 * Used by both PublicPaymentPage (initial payment) and MollieWebhook
 * (subsequent installment creation after each payment is confirmed).
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared service for Mollie installment payment creation.
 *
 * Extracts the payment creation logic that was previously private to
 * PublicPaymentPage so it can be reused by the webhook handler when
 * automatically creating the next installment payment after one is confirmed.
 */
class InstallmentPaymentService {

	/**
	 * Create a Mollie payment for a specific installment of an invoice.
	 *
	 * Reads installment meta from the invoice post, builds the Mollie payment
	 * payload, creates the payment via the Mollie API, and stores the results
	 * (payment ID, checkout URL, reverse-lookup meta) back on the invoice.
	 *
	 * For the `full` plan (no installment meta written by write_installment_meta),
	 * the amount falls back to the ACF total_amount field.
	 *
	 * @param int $invoice_id         Invoice post ID.
	 * @param int $installment_number Which installment to create (1-based).
	 * @return string|\WP_Error Mollie checkout URL on success, WP_Error on failure.
	 */
	public static function create_payment( int $invoice_id, int $installment_number ): string|\WP_Error {
		// Guard: Mollie API key must be configured.
		$config  = new FinanceConfig();
		$api_key = $config->get_mollie_api_key();
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'mollie_not_configured', 'Mollie API-sleutel niet geconfigureerd.' );
		}

		// Read plan meta (raw WP meta, not ACF).
		$plan  = get_post_meta( $invoice_id, '_installment_plan', true );
		$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		$count = max( 1, $count ); // Floor at 1.
		$token = get_post_meta( $invoice_id, '_payment_token', true );

		// Calculate amount.
		// For multi-installment plans, use stored per-installment amounts.
		// For the full plan, use ACF total_amount (no admin fee for full plan).
		$installment_amount = get_post_meta( $invoice_id, '_installment_' . $installment_number . '_amount', true );
		if ( '' !== $installment_amount && false !== $installment_amount ) {
			$admin_fee = (float) get_post_meta( $invoice_id, '_installment_' . $installment_number . '_admin_fee', true );
			$amount    = (float) $installment_amount + $admin_fee;
		} else {
			// Full plan — no admin fee, use total amount directly.
			$amount = (float) get_field( 'total_amount', $invoice_id );
		}

		// Build description.
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		if ( 'full' === $plan ) {
			$description = 'Factuur ' . $invoice_number;
		} else {
			$description = 'Termijn ' . $installment_number . '/' . $count . ' - Factuur ' . $invoice_number;
		}

		// Build Mollie payment payload.
		$payload = [
			'amount'      => [
				'currency' => 'EUR',
				'value'    => number_format( $amount, 2, '.', '' ),
			],
			'description' => $description,
			'redirectUrl' => home_url( '/betaling/' . $token . '?betaald=1' ),
			'metadata'    => [
				'invoice_id'         => $invoice_id,
				'installment_number' => $installment_number,
			],
		];

		// Conditionally add webhookUrl — omit on localhost/.local environments.
		$site_url = get_site_url();
		if ( false === strpos( $site_url, 'localhost' ) && false === strpos( $site_url, '.local' ) ) {
			$payload['webhookUrl'] = rest_url( 'rondo/v1/mollie/webhook' );
		}

		// Create payment via Mollie SDK.
		try {
			$mollie_client = new MollieClient();
			$mollie        = $mollie_client->get();
			$payment       = $mollie->payments->create( $payload );
		} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			error_log( 'Mollie API exception (installment ' . $installment_number . '): ' . $e->getMessage() );
			return new \WP_Error( 'mollie_api_error', $e->getMessage() );
		}

		// Store payment results on invoice (raw WP meta).
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_mollie_payment_id', $payment->id );
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_payment_link', $payment->getCheckoutUrl() );

		// Reverse-lookup meta for O(1) webhook matching.
		update_post_meta( $invoice_id, '_mollie_pid_' . $payment->id, $installment_number );

		return $payment->getCheckoutUrl();
	}
}
