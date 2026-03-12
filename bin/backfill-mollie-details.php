#!/usr/bin/env php
<?php
/**
 * One-time backfill script: populate Mollie payment details for already-paid invoices.
 *
 * Run on production via WP-CLI:
 *   wp eval-file wp-content/themes/rondo-club/bin/backfill-mollie-details.php
 *
 * Or with --dry-run to preview:
 *   wp eval-file wp-content/themes/rondo-club/bin/backfill-mollie-details.php -- --dry-run
 *
 * What it does:
 * - Finds all rondo_paid invoices with a _mollie_payment_link_id
 * - Skips invoices that already have _mollie_payment_method (already backfilled)
 * - Fetches the PaymentLink from Mollie, iterates its payments
 * - Stores the same meta keys as the webhook extraction (T01)
 * - For installment invoices, also backfills per-installment details
 *
 * Safe to run multiple times — skips already-processed invoices.
 *
 * @package Rondo\Finance
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "This script must be run via WP-CLI: wp eval-file <path>\n";
	exit( 1 );
}

// Dry-run mode: set DRY_RUN=1 environment variable to preview changes.
// Usage: DRY_RUN=1 wp eval-file wp-content/themes/rondo-club/bin/backfill-mollie-details.php
$dry_run = (bool) getenv( 'DRY_RUN' );

if ( $dry_run ) {
	WP_CLI::log( '🔍 DRY RUN — no changes will be made.' );
}

// 1. Get all paid invoices with a Mollie payment link ID.
$invoices = get_posts( [
	'post_type'      => 'rondo_invoice',
	'post_status'    => 'rondo_paid',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'meta_query'     => [
		[
			'key'     => '_mollie_payment_link_id',
			'compare' => 'EXISTS',
		],
		[
			'key'     => '_mollie_payment_link_id',
			'value'   => '',
			'compare' => '!=',
		],
	],
] );

WP_CLI::log( sprintf( 'Found %d paid invoices with Mollie payment link IDs.', count( $invoices ) ) );

$config = new \Rondo\Config\FinanceConfig();

// Build a cache of API keys per account ID.
$api_key_cache = [];

// Get the first usable account as fallback for invoices without _payment_account_id.
$usable_accounts = $config->get_usable_mollie_accounts();
$fallback_account_id = ! empty( $usable_accounts ) ? $usable_accounts[0]['id'] : '';

$stats = [
	'skipped_already_done' => 0,
	'skipped_no_api_key'   => 0,
	'skipped_api_error'    => 0,
	'skipped_not_paid'     => 0,
	'skipped_no_payment'   => 0,
	'backfilled'           => 0,
	'installments_done'    => 0,
];

foreach ( $invoices as $invoice_id ) {
	$title = get_the_title( $invoice_id );

	// Skip if already has payment method (already backfilled or webhook-populated).
	$existing_method = get_post_meta( $invoice_id, '_mollie_payment_method', true );
	if ( ! empty( $existing_method ) ) {
		$stats['skipped_already_done']++;
		WP_CLI::log( sprintf( '  ⏭  %s (#%d) — already has payment method: %s', $title, $invoice_id, $existing_method ) );
		continue;
	}

	$payment_link_id = get_post_meta( $invoice_id, '_mollie_payment_link_id', true );
	$account_id      = (string) get_post_meta( $invoice_id, '_payment_account_id', true );

	// Build ordered list of API keys to try: invoice's account first, then all others.
	$keys_to_try = [];
	if ( ! empty( $account_id ) && '' !== $config->get_mollie_api_key_for_account( $account_id ) ) {
		$keys_to_try[ $account_id ] = $config->get_mollie_api_key_for_account( $account_id );
	}
	foreach ( $usable_accounts as $acct ) {
		if ( ! isset( $keys_to_try[ $acct['id'] ] ) ) {
			$keys_to_try[ $acct['id'] ] = $config->get_mollie_api_key_for_account( $acct['id'] );
		}
	}

	if ( empty( $keys_to_try ) ) {
		$stats['skipped_no_api_key']++;
		WP_CLI::warning( sprintf( '  ❌ %s (#%d) — no usable API keys', $title, $invoice_id ) );
		continue;
	}

	// Fetch payment link from Mollie — try each account until one works.
	$payment_link  = null;
	$mollie_client = null;
	$last_error    = '';
	foreach ( $keys_to_try as $try_account_id => $try_key ) {
		try {
			$mollie_client = new \Rondo\Finance\MollieClient( $try_key );
			$payment_link  = $mollie_client->get()->paymentLinks->get( $payment_link_id );
			break; // Success — stop trying.
		} catch ( \Throwable $e ) {
			$last_error = $e->getMessage();
			$payment_link = null;
			usleep( 100000 ); // 100ms between retries.
		}
	}

	if ( null === $payment_link ) {
		$stats['skipped_api_error']++;
		WP_CLI::warning( sprintf( '  ❌ %s (#%d) — all accounts failed. Last error: %s', $title, $invoice_id, $last_error ) );
		continue;
	}

	if ( ! $payment_link->isPaid() ) {
		$stats['skipped_not_paid']++;
		WP_CLI::log( sprintf( '  ⏭  %s (#%d) — payment link not marked as paid', $title, $invoice_id ) );
		continue;
	}

	// Find the paid payment object.
	try {
		$payments     = $payment_link->payments();
		$paid_payment = null;
		foreach ( $payments as $payment ) {
			if ( $payment->isPaid() ) {
				$paid_payment = $payment;
			}
		}
	} catch ( \Throwable $e ) {
		$stats['skipped_api_error']++;
		WP_CLI::warning( sprintf( '  ❌ %s (#%d) — error fetching payments: %s', $title, $invoice_id, $e->getMessage() ) );
		continue;
	}

	if ( null === $paid_payment ) {
		$stats['skipped_no_payment']++;
		WP_CLI::log( sprintf( '  ⏭  %s (#%d) — no paid payment found on link', $title, $invoice_id ) );
		continue;
	}

	$method        = $paid_payment->method ?? '(unknown)';
	$paid_at       = $paid_payment->paidAt ?? '(unknown)';
	$dashboard_url = $paid_payment->_links->dashboard->href ?? null;
	$consumer_name = $paid_payment->details->consumerName ?? null;
	$consumer_acct = $paid_payment->details->consumerAccount ?? null;

	if ( $dry_run ) {
		WP_CLI::log( sprintf(
			'  🔎 %s (#%d) — would store: method=%s, paid_at=%s, consumer=%s',
			$title, $invoice_id, $method, $paid_at, $consumer_name ?? '(none)'
		) );
	} else {
		// Store invoice-level details.
		update_post_meta( $invoice_id, '_mollie_payment_method', $paid_payment->method ?? '' );
		update_post_meta( $invoice_id, '_mollie_paid_at', $paid_payment->paidAt ?? '' );

		if ( null !== $dashboard_url ) {
			update_post_meta( $invoice_id, '_mollie_dashboard_url', $dashboard_url );
		}
		if ( null !== $consumer_name ) {
			update_post_meta( $invoice_id, '_mollie_consumer_name', $consumer_name );
		}
		if ( null !== $consumer_acct ) {
			update_post_meta( $invoice_id, '_mollie_consumer_account', $consumer_acct );
		}
		if ( null !== $paid_payment->details ) {
			update_post_meta( $invoice_id, '_mollie_payment_details', wp_json_encode( $paid_payment->details ) );
		}

		WP_CLI::success( sprintf(
			'  ✅ %s (#%d) — method=%s, paid_at=%s, consumer=%s',
			$title, $invoice_id, $method, $paid_at, $consumer_name ?? '(none)'
		) );
	}
	$stats['backfilled']++;

	// Also backfill per-installment details if installments exist.
	$installment_count = (int) get_post_meta( $invoice_id, '_installment_count', true );
	if ( $installment_count > 0 ) {
		for ( $n = 1; $n <= $installment_count; $n++ ) {
			$inst_pl_id = get_post_meta( $invoice_id, '_installment_' . $n . '_mollie_payment_id', true );
			if ( empty( $inst_pl_id ) ) {
				continue;
			}

			// Skip if already has per-installment method.
			$inst_method = get_post_meta( $invoice_id, '_installment_' . $n . '_mollie_method', true );
			if ( ! empty( $inst_method ) ) {
				continue;
			}

			// For installments, the payment link ID might be the same or different.
			// If it's a pl_ ID, fetch it. If tr_ (legacy), we need to use payments API.
			try {
				if ( str_starts_with( $inst_pl_id, 'pl_' ) ) {
					$inst_link = $mollie_client->get()->paymentLinks->get( $inst_pl_id );
					$inst_payments = $inst_link->payments();
					$inst_paid = null;
					foreach ( $inst_payments as $p ) {
						if ( $p->isPaid() ) {
							$inst_paid = $p;
						}
					}
				} elseif ( str_starts_with( $inst_pl_id, 'tr_' ) ) {
					// Legacy: direct payment ID.
					$inst_paid = $mollie_client->get()->payments->get( $inst_pl_id );
					if ( ! $inst_paid->isPaid() ) {
						$inst_paid = null;
					}
				} else {
					continue;
				}

				if ( null === $inst_paid ) {
					continue;
				}

				if ( ! $dry_run ) {
					update_post_meta( $invoice_id, '_installment_' . $n . '_mollie_method', $inst_paid->method ?? '' );
					update_post_meta( $invoice_id, '_installment_' . $n . '_mollie_paid_at', $inst_paid->paidAt ?? '' );

					$inst_dashboard = $inst_paid->_links->dashboard->href ?? null;
					if ( null !== $inst_dashboard ) {
						update_post_meta( $invoice_id, '_installment_' . $n . '_mollie_dashboard_url', $inst_dashboard );
					}
				}

				$stats['installments_done']++;
				$action = $dry_run ? 'would backfill' : 'backfilled';
				WP_CLI::log( sprintf(
					'    📋 Installment %d: %s method=%s',
					$n, $action, $inst_paid->method ?? '(unknown)'
				) );
			} catch ( \Throwable $e ) {
				WP_CLI::warning( sprintf(
					'    ❌ Installment %d: error — %s',
					$n, $e->getMessage()
				) );
			}
		}
	}

	// Be gentle to Mollie API — small delay between invoices.
	usleep( 200000 ); // 200ms
}

WP_CLI::log( '' );
WP_CLI::log( '📊 Summary:' );
WP_CLI::log( sprintf( '  Backfilled:         %d', $stats['backfilled'] ) );
WP_CLI::log( sprintf( '  Installments done:  %d', $stats['installments_done'] ) );
WP_CLI::log( sprintf( '  Skipped (done):     %d', $stats['skipped_already_done'] ) );
WP_CLI::log( sprintf( '  Skipped (no key):   %d', $stats['skipped_no_api_key'] ) );
WP_CLI::log( sprintf( '  Skipped (API err):  %d', $stats['skipped_api_error'] ) );
WP_CLI::log( sprintf( '  Skipped (not paid): %d', $stats['skipped_not_paid'] ) );
WP_CLI::log( sprintf( '  Skipped (no pmt):   %d', $stats['skipped_no_payment'] ) );

if ( $dry_run ) {
	WP_CLI::log( '' );
	WP_CLI::log( '🔍 This was a dry run. Run without --dry-run to apply changes.' );
}
