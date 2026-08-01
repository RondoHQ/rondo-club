<?php
/**
 * Installment Payment Service
 *
 * Shared service for creating Mollie payment links for invoice installments.
 * Uses the Payment Links API (not regular Payments API) so that links emailed
 * to members remain valid until paid — regular payments expire in ~15 minutes.
 *
 * Used by both PublicPaymentPage (initial payment) and MollieWebhook
 * (subsequent installment creation after each payment is confirmed).
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared service for Mollie installment payment link creation.
 *
 * Extracts the payment link creation logic that was previously private to
 * PublicPaymentPage so it can be reused by the webhook handler when
 * automatically creating the next installment payment link after one is confirmed.
 */
class InstallmentPaymentService {

	/**
	 * Create a Mollie payment link for a specific installment of an invoice.
	 *
	 * Uses the Mollie Payment Links API (POST /v2/payment-links) which creates a
	 * persistent link that remains valid until paid — unlike regular Mollie payments
	 * (POST /v2/payments) which expire in ~15 minutes. This is critical because
	 * installment links are emailed to members who may open them hours or days later.
	 *
	 * Reads installment meta from the invoice post, builds the Mollie payload,
	 * creates the payment link via the Mollie API, and stores the results
	 * (payment link ID, checkout URL, reverse-lookup meta) back on the invoice.
	 *
	 * For the `full` plan (no installment meta written by write_installment_meta),
	 * the amount falls back to the native field total_amount field.
	 *
	 * @param int $invoice_id         Invoice post ID.
	 * @param int $installment_number Which installment to create (1-based).
	 * @return string|\WP_Error Mollie checkout URL on success, WP_Error on failure.
	 */
	public static function create_payment( int $invoice_id, int $installment_number ): string|\WP_Error {
		// Guard: Mollie API key must be configured.
		$mollie     = FinanceServices::mollie();
		$account_id = (string) get_post_meta( $invoice_id, '_payment_account_id', true );
		if ( $account_id === '' ) {
			$account_id = $mollie->get_default_mollie_account_id( 'membership' );
		}
		$api_key = $mollie->get_mollie_api_key_for_account( $account_id );
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'mollie_not_configured', 'Voor deze contributiefactuur is geen Mollie API-sleutel geconfigureerd.' );
		}

		// Read plan meta (raw WP meta, not native field).
		$plan  = get_post_meta( $invoice_id, '_installment_plan', true );
		$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		$count = max( 1, $count ); // Floor at 1.
		$token = get_post_meta( $invoice_id, '_payment_token', true );

		// Calculate amount.
		// For multi-installment plans, use stored per-installment amounts.
		// For the full plan, use native field total_amount (no admin fee for full plan).
		$installment_amount = get_post_meta( $invoice_id, '_installment_' . $installment_number . '_amount', true );
		if ( $installment_amount !== '' && $installment_amount !== false ) {
			$admin_fee = (float) get_post_meta( $invoice_id, '_installment_' . $installment_number . '_admin_fee', true );
			$amount    = (float) $installment_amount + $admin_fee;
		} else {
			// Full plan — no admin fee, use total amount directly.
			$amount = (float) \Rondo\Fields\Fields::get_for_post( $invoice_id, 'total_amount' );
		}

		// Build description.
		$invoice_number = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_number' );
		if ( $plan === 'full' ) {
			$description = 'Factuur ' . $invoice_number;
		} else {
			$description = 'Termijn ' . $installment_number . '/' . $count . ' - Factuur ' . $invoice_number;
		}

		// Build Mollie payment link payload.
		$payload = [
			'amount'      => [
				'currency' => 'EUR',
				'value'    => number_format( $amount, 2, '.', '' ),
			],
			'description' => $description,
			'redirectUrl' => home_url( '/betaling/' . $token . '?betaald=1' ),
		];

		// Conditionally add webhookUrl — omit on localhost/.local environments.
		$site_url = get_site_url();
		if ( strpos( $site_url, 'localhost' ) === false && strpos( $site_url, '.local' ) === false ) {
			$payload['webhookUrl'] = rest_url( 'rondo/v1/mollie/webhook' );
		}

		// Create payment link via Mollie Payment Links API (persistent, no expiry).
		try {
			$mollie_client = new MollieClient( $api_key );
			$mollie        = $mollie_client->get();
			$payment_link  = MollieClient::with_retry(
				fn() => $mollie->paymentLinks->create( $payload )
			);
		} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			error_log( 'Mollie API exception (installment ' . $installment_number . '): ' . $e->getMessage() );
			return new \WP_Error( 'mollie_api_error', $e->getMessage() );
		}

		// Store payment link results on invoice (raw WP meta).
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_mollie_payment_id', $payment_link->id );
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_payment_link', $payment_link->getCheckoutUrl() );

		// Reverse-lookup meta for O(1) webhook matching.
		update_post_meta( $invoice_id, '_mollie_pid_' . $payment_link->id, $installment_number );

		return $payment_link->getCheckoutUrl();
	}
}
