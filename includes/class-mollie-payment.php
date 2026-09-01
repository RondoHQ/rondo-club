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
			$existing_url = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'payment_link' );
			if ( ! empty( $existing_url ) ) {
				return $existing_url;
			}
			// Payment link ID exists but URL is missing — fall through to create a new link.
		}

		// 3. Guard: account API key configured
		$account_id = (string) get_post_meta( $invoice_id, '_payment_account_id', true );
		$api_key    = FinanceServices::mollie()->get_mollie_api_key_for_account( $account_id );
		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'mollie_not_configured',
				__( 'Voor deze factuur is geen Mollie API-sleutel geconfigureerd.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// 4. Load invoice data
		$invoice_number = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_number' );
		$total_amount   = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'total_amount' );

		// 5. Format amount — always use number_format() with 4 args to avoid locale issues.
		$amount_string = number_format( (float) $total_amount, 2, '.', '' );

		// 6. Determine redirect URL — configured URL or fallback to homepage.
		$redirect_url = (string) get_post_meta( $invoice_id, '_mollie_redirect_url', true );
		if ( empty( $redirect_url ) ) {
			$redirect_url = FinanceServices::mollie()->get_mollie_redirect_url();
		}
		if ( empty( $redirect_url ) ) {
			$redirect_url = home_url( '/' );
		}
		$description = (string) get_post_meta( $invoice_id, '_mollie_description', true );
		if ( $description === '' ) {
			$description = 'Factuur ' . $invoice_number;
		}

		// 7. Build payload
		$payload = [
			'amount'      => [
				'currency' => 'EUR',
				'value'    => $amount_string,
			],
			'description' => $description,
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
			$payment_link  = MollieClient::with_retry(
				fn() => $mollie->paymentLinks->create( $payload )
			);
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
		\Rondo\Fields\Fields::update_for_post( $invoice_id, 'payment_link', $checkout_url );
		update_post_meta( $invoice_id, '_mollie_payment_link_id', $payment_link->id );

		// 12. Return checkout URL
		return $checkout_url;
	}

	/**
	 * Archive all Mollie payment links for an invoice so they become unpayable.
	 *
	 * Covers both the full-invoice payment link (`_mollie_payment_link_id`) and any
	 * per-installment payment links (`_installment_{N}_mollie_payment_id`). Used when
	 * an invoice is cancelled (vervallen) so members can no longer pay via old links.
	 *
	 * Non-blocking: archiving failures are ignored — a link may already be archived,
	 * paid, or the API key may be missing. The `_mollie_pid_*` reverse-lookup keys are
	 * kept so a payment that slips through before archiving still routes via the webhook.
	 *
	 * @param int $invoice_id Invoice post ID.
	 */
	public function archive_payment_links( int $invoice_id ): void {
		$link_ids = [];

		$full_link_id = (string) get_post_meta( $invoice_id, '_mollie_payment_link_id', true );
		if ( $full_link_id !== '' ) {
			$link_ids[] = $full_link_id;
		}

		$installment_count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		$check_up_to       = max( $installment_count, 8 );
		for ( $n = 1; $n <= $check_up_to; $n++ ) {
			$installment_link_id = (string) get_post_meta( $invoice_id, '_installment_' . $n . '_mollie_payment_id', true );
			if ( $installment_link_id !== '' ) {
				$link_ids[] = $installment_link_id;
			}
		}

		if ( empty( $link_ids ) ) {
			return;
		}

		$mollie_cfg = FinanceServices::mollie();
		$account_id = (string) get_post_meta( $invoice_id, '_payment_account_id', true );
		if ( $account_id === '' ) {
			$invoice_type = (string) get_post_meta( $invoice_id, 'invoice_type', true );
			$account_id   = $mollie_cfg->get_default_mollie_account_id( $invoice_type ?: 'manual' );
		}

		$api_key = $mollie_cfg->get_mollie_api_key_for_account( $account_id );
		if ( $api_key === '' ) {
			return;
		}

		try {
			$mollie = ( new MollieClient( $api_key ) )->get();
		} catch ( \Throwable $e ) {
			return;
		}

		foreach ( $link_ids as $link_id ) {
			try {
				$mollie->paymentLinks->delete( $link_id );
			} catch ( \Throwable $e ) {
				// Non-blocking — link may already be archived or paid.
			}
		}
	}
}
