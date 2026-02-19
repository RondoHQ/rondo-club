<?php
/**
 * Installment Scheduler — Daily Cron Sweeper
 *
 * Registers a single daily WP-Cron event that queries all sent invoices with
 * active payment plans, evaluates each installment's due date and overdue status,
 * and triggers the appropriate email action via InstallmentEmailSender.
 *
 * Email timing:
 *   - Initial send: installment status = 'pending' AND today >= due_date
 *   - Reminder 1:  installment status = 'sent'    AND 14+ days overdue (no reminder 1 yet)
 *   - Reminder 2:  installment status = 'sent'    AND 21+ days overdue (no reminder 2 yet, checked first)
 *
 * Idempotency:
 *   - Transient lock prevents concurrent sweeper runs (5-minute TTL)
 *   - Sent timestamps on each installment prevent duplicate emails
 *   - Initial status written by InstallmentEmailSender before wp_mail, so re-runs skip paid/sent
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installment Scheduler class
 *
 * Follows the same cron pattern as Rondo\Calendar\Sync.
 */
class InstallmentScheduler {

	/**
	 * WP-Cron hook name for the daily sweeper
	 */
	const CRON_HOOK = 'rondo_installment_sweeper';

	/**
	 * Transient key used as a mutex lock to prevent concurrent sweeper runs
	 */
	const LOCK_TRANSIENT = 'rondo_installment_sweeper_lock';

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
	 * Run the installment sweep (cron callback)
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
	 * Query all invoices with active payment plans and process each one
	 *
	 * Queries rondo_invoice posts with rondo_sent status where the installment plan
	 * is not 'full' (i.e. quarterly_3 or monthly_8).
	 */
	private function process_invoices(): void {
		$invoice_ids = get_posts( [
			'post_type'        => 'rondo_invoice',
			'post_status'      => 'rondo_sent',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [
				[
					'key'     => '_installment_plan',
					'value'   => 'full',
					'compare' => '!=',
				],
			],
		] );

		if ( empty( $invoice_ids ) ) {
			return;
		}

		foreach ( $invoice_ids as $invoice_id ) {
			$this->process_invoice( (int) $invoice_id );
		}
	}

	/**
	 * Process a single invoice: check each installment and trigger emails as needed
	 *
	 * Decision tree per installment:
	 *   1. Skip if due_date is missing (warn and continue)
	 *   2. Skip if status is 'betaald' (already paid)
	 *   3. Initial send:  status='pending' AND today >= due_date
	 *   4. Reminder 2:   status='sent' AND overdue >= 21 days AND no reminder_2_sent_at (check BEFORE reminder 1)
	 *   5. Reminder 1:   status='sent' AND overdue >= 14 days AND no reminder_1_sent_at
	 *
	 * Each email action is wrapped in try/catch so one failure doesn't stop the sweep.
	 *
	 * @param int $invoice_id Invoice post ID.
	 */
	private function process_invoice( int $invoice_id ): void {
		$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		if ( $count < 1 ) {
			return;
		}

		$today = current_time( 'Y-m-d' );

		for ( $n = 1; $n <= $count; $n++ ) {
			$due_date = (string) get_post_meta( $invoice_id, '_installment_' . $n . '_due_date', true );
			$status   = (string) get_post_meta( $invoice_id, '_installment_' . $n . '_status', true );

			// Skip installments with missing due date.
			if ( empty( $due_date ) ) {
				error_log( sprintf(
					'[InstallmentScheduler] Invoice %d installment %d: missing due date — skipping.',
					$invoice_id,
					$n
				) );
				continue;
			}

			// Skip installments that have been paid.
			if ( $status === 'betaald' ) {
				continue;
			}

			// Calculate days overdue (negative = not yet due).
			$today_ts    = strtotime( $today );
			$due_ts      = strtotime( $due_date );
			$days_overdue = ( $today_ts !== false && $due_ts !== false )
				? (int) floor( ( $today_ts - $due_ts ) / DAY_IN_SECONDS )
				: 0;

			// Initial send: pending installment is now due.
			if ( $status === 'pending' && $today >= $due_date ) {
				try {
					$result = InstallmentEmailSender::send_installment_email( $invoice_id, $n );
					if ( is_wp_error( $result ) ) {
						error_log( sprintf(
							'[InstallmentScheduler] Invoice %d installment %d: initial email failed — %s',
							$invoice_id,
							$n,
							$result->get_error_message()
						) );
					} else {
						error_log( sprintf(
							'[InstallmentScheduler] Invoice %d installment %d: initial email sent.',
							$invoice_id,
							$n
						) );
					}
				} catch ( \Throwable $e ) {
					error_log( sprintf(
						'[InstallmentScheduler] Invoice %d installment %d: exception during initial send — %s',
						$invoice_id,
						$n,
						$e->getMessage()
					) );
				}
				continue;
			}

			// Overdue reminders (only for 'sent' status).
			if ( $status === 'sent' ) {
				// Check reminder 2 FIRST (>= 21 days also satisfies >= 14 days).
				if ( $days_overdue >= 21 ) {
					$reminder_2_sent_at = get_post_meta( $invoice_id, '_installment_' . $n . '_reminder_2_sent_at', true );
					if ( empty( $reminder_2_sent_at ) ) {
						try {
							$result = InstallmentEmailSender::send_reminder_2( $invoice_id, $n );
							if ( is_wp_error( $result ) ) {
								error_log( sprintf(
									'[InstallmentScheduler] Invoice %d installment %d: reminder 2 failed — %s',
									$invoice_id,
									$n,
									$result->get_error_message()
								) );
							} else {
								error_log( sprintf(
									'[InstallmentScheduler] Invoice %d installment %d: reminder 2 sent (%d days overdue).',
									$invoice_id,
									$n,
									$days_overdue
								) );
							}
						} catch ( \Throwable $e ) {
							error_log( sprintf(
								'[InstallmentScheduler] Invoice %d installment %d: exception during reminder 2 — %s',
								$invoice_id,
								$n,
								$e->getMessage()
							) );
						}
					}
					continue;
				}

				if ( $days_overdue >= 14 ) {
					$reminder_1_sent_at = get_post_meta( $invoice_id, '_installment_' . $n . '_reminder_1_sent_at', true );
					if ( empty( $reminder_1_sent_at ) ) {
						try {
							$result = InstallmentEmailSender::send_reminder_1( $invoice_id, $n );
							if ( is_wp_error( $result ) ) {
								error_log( sprintf(
									'[InstallmentScheduler] Invoice %d installment %d: reminder 1 failed — %s',
									$invoice_id,
									$n,
									$result->get_error_message()
								) );
							} else {
								error_log( sprintf(
									'[InstallmentScheduler] Invoice %d installment %d: reminder 1 sent (%d days overdue).',
									$invoice_id,
									$n,
									$days_overdue
								) );
							}
						} catch ( \Throwable $e ) {
							error_log( sprintf(
								'[InstallmentScheduler] Invoice %d installment %d: exception during reminder 1 — %s',
								$invoice_id,
								$n,
								$e->getMessage()
							) );
						}
					}
				}
			}
		}
	}
}
