<?php
/**
 * Invoice Reminder Scheduler — Daily Cron Sweeper
 *
 * Registers a single daily WP-Cron event that queries eligible invoices in
 * rondo_sent status and triggers reminder emails via InvoiceReminderSender.
 *
 * Eligible invoices:
 * - Membership invoices where the member has NOT yet selected a payment plan
 *   (i.e. _installment_plan meta does NOT exist)
 * - Discipline invoices (including legacy discipline invoices with missing/
 *   empty invoice_type) identified via Mollie payment-link metadata
 *
 * Email timing:
 *   - Reminder 1: 14+ days since sent_date AND no _invoice_reminder_1_sent_at meta
 *   - Reminder 2: 28+ days since sent_date AND no _invoice_reminder_2_sent_at meta (checked first)
 *
 * Idempotency:
 *   - Transient lock prevents concurrent sweeper runs (5-minute TTL)
 *   - Sent timestamps on each invoice prevent duplicate emails
 *   - Timestamp written by InvoiceReminderSender BEFORE wp_mail, so re-runs skip already-sent
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice Reminder Scheduler class
 *
 * Follows the same cron pattern as InstallmentScheduler.
 */
class InvoiceReminderScheduler {

	/**
	 * WP-Cron hook name for the daily sweeper
	 */
	const CRON_HOOK = 'rondo_invoice_reminder_sweeper';

	/**
	 * Transient key used as a mutex lock to prevent concurrent sweeper runs
	 */
	const LOCK_TRANSIENT = 'rondo_invoice_reminder_sweeper_lock';

	/**
	 * Constructor — registers cron hook and lifecycle hooks
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_sweep' ] );
		add_action( 'after_switch_theme', [ $this, 'schedule_sweeper' ] );
		add_action( 'switch_theme', [ $this, 'unschedule_sweeper' ] );
	}

	/**
	 * Schedule the daily sweeper cron event
	 *
	 * Called on after_switch_theme. Schedules event at tomorrow midnight
	 * so the sweeper runs once per day around midnight.
	 */
	public function schedule_sweeper(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the daily sweeper cron event
	 *
	 * Called on switch_theme (theme deactivation).
	 */
	public function unschedule_sweeper(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Run the invoice reminder sweep (cron callback)
	 *
	 * Acquires a transient lock to prevent concurrent execution, then delegates
	 * to process_invoices(). Lock is always released in the finally block.
	 */
	public function run_sweep(): void {
		// Idempotency lock — bail if another sweep is in progress.
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}

		// Set lock with 5-minute TTL.
		set_transient( self::LOCK_TRANSIENT, true, 5 * MINUTE_IN_SECONDS );

		try {
			$this->process_invoices();
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Query all eligible invoices and process each one
	 *
	 * Queries rondo_invoice posts with rondo_sent status where either:
	 * 1. Membership invoice without installment plan selected
	 * 2. Discipline invoice (including legacy invoices with missing/empty type)
	 *    identified via Mollie payment link meta
	 */
	private function process_invoices(): void {
		$invoice_ids = get_posts(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => 'rondo_sent',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'OR',
					[
						'relation' => 'AND',
						[
							'key'     => '_installment_plan',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'   => 'invoice_type',
							'value' => 'membership',
						],
					],
					[
						'relation' => 'AND',
						[
							'key'     => '_mollie_payment_link_id',
							'compare' => 'EXISTS',
						],
						[
							'relation' => 'OR',
							[
								'key'   => 'invoice_type',
								'value' => 'discipline',
							],
							[
								'key'     => 'invoice_type',
								'compare' => 'NOT EXISTS',
							],
							[
								'key'   => 'invoice_type',
								'value' => '',
							],
						],
					],
				],
			]
		);

		if ( empty( $invoice_ids ) ) {
			return;
		}

		foreach ( $invoice_ids as $invoice_id ) {
			$this->process_invoice( (int) $invoice_id );
		}
	}

	/**
	 * Process a single invoice: check sent_date and trigger reminder emails as needed
	 *
	 * Decision tree:
	 *   1. Skip if sent_date is missing (warn and continue)
	 *   2. Reminder 2: >= 28 days since sent_date AND no _invoice_reminder_2_sent_at (check FIRST)
	 *   3. Reminder 1: >= 14 days since sent_date AND no _invoice_reminder_1_sent_at
	 *
	 * Each email action is wrapped in try/catch so one failure doesn't stop the sweep.
	 *
	 * @param int $invoice_id Invoice post ID.
	 */
	private function process_invoice( int $invoice_id ): void {
		// Read sent_date ACF field (stored as Ymd string, e.g. '20260215').
		$sent_date = (string) get_field( 'sent_date', $invoice_id );

		if ( empty( $sent_date ) ) {
			error_log(
				sprintf(
					'[InvoiceReminderScheduler] Invoice %d: missing sent_date — skipping.',
					$invoice_id
				)
			);
			return;
		}

		// Parse sent_date (Ymd format) to timestamp.
		$sent_ts = strtotime( $sent_date );
		if ( $sent_ts === false ) {
			error_log(
				sprintf(
					'[InvoiceReminderScheduler] Invoice %d: could not parse sent_date "%s" — skipping.',
					$invoice_id,
					$sent_date
				)
			);
			return;
		}

		// Calculate days since sent.
		$today_ts   = strtotime( current_time( 'Y-m-d' ) );
		$days_since = ( $today_ts !== false )
			? (int) floor( ( $today_ts - $sent_ts ) / DAY_IN_SECONDS )
			: 0;

		// Check reminder 2 FIRST (>= 28 days also satisfies >= 14 days).
		if ( $days_since >= 28 ) {
			$reminder_2_sent_at = get_post_meta( $invoice_id, '_invoice_reminder_2_sent_at', true );
			if ( empty( $reminder_2_sent_at ) ) {
				try {
					$result = InvoiceReminderSender::send_reminder_2( $invoice_id );
					if ( is_wp_error( $result ) ) {
						error_log(
							sprintf(
								'[InvoiceReminderScheduler] Invoice %d: reminder 2 failed — %s',
								$invoice_id,
								$result->get_error_message()
							)
						);
					} else {
						error_log(
							sprintf(
								'[InvoiceReminderScheduler] Invoice %d: reminder 2 sent (%d days since sent).',
								$invoice_id,
								$days_since
							)
						);
					}
				} catch ( \Throwable $e ) {
					error_log(
						sprintf(
							'[InvoiceReminderScheduler] Invoice %d: exception during reminder 2 — %s',
							$invoice_id,
							$e->getMessage()
						)
					);
				}
			}
			return;
		}

		if ( $days_since >= 14 ) {
			$reminder_1_sent_at = get_post_meta( $invoice_id, '_invoice_reminder_1_sent_at', true );
			if ( empty( $reminder_1_sent_at ) ) {
				try {
					$result = InvoiceReminderSender::send_reminder_1( $invoice_id );
					if ( is_wp_error( $result ) ) {
						error_log(
							sprintf(
								'[InvoiceReminderScheduler] Invoice %d: reminder 1 failed — %s',
								$invoice_id,
								$result->get_error_message()
							)
						);
					} else {
						error_log(
							sprintf(
								'[InvoiceReminderScheduler] Invoice %d: reminder 1 sent (%d days since sent).',
								$invoice_id,
								$days_since
							)
						);
					}
				} catch ( \Throwable $e ) {
					error_log(
						sprintf(
							'[InvoiceReminderScheduler] Invoice %d: exception during reminder 1 — %s',
							$invoice_id,
							$e->getMessage()
						)
					);
				}
			}
		}
	}
}
