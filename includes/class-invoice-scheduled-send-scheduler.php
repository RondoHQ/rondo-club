<?php
/**
 * Invoice Scheduled-Send Scheduler — Daily Cron Sweeper
 *
 * Registers a daily WP-Cron event that finds draft invoices with a
 * `_scheduled_send_date` at or before today and runs the normal send flow for
 * each, attributing the send to the user who scheduled it.
 *
 * The invoice stays a plain `rondo_draft` while queued — the schedule is just a
 * post meta value — so nothing about the existing draft/send flow changes. A
 * manual "Verstuur nu" send clears the schedule; the daily sweep sends whatever
 * is still a draft once its date arrives.
 *
 * Idempotency:
 *   - Transient lock prevents concurrent sweeper runs (5-minute TTL)
 *   - Invoices::send_invoice() clears the schedule meta on success, so a sent
 *     invoice is never picked up again
 *   - A failed send keeps the meta and is retried on the next daily run
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice Scheduled-Send Scheduler class
 *
 * Follows the same cron pattern as InvoiceReminderScheduler.
 */
class InvoiceScheduledSendScheduler {

	/**
	 * WP-Cron hook name for the daily sweeper
	 */
	const CRON_HOOK = 'rondo_invoice_scheduled_send_sweeper';

	/**
	 * Transient key used as a mutex lock to prevent concurrent sweeper runs
	 */
	const LOCK_TRANSIENT = 'rondo_invoice_scheduled_send_lock';

	/**
	 * Constructor — registers cron and lifecycle hooks
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_sweep' ] );
		add_action( 'after_switch_theme', [ $this, 'schedule_sweeper' ] );
		add_action( 'switch_theme', [ $this, 'unschedule_sweeper' ] );

		// Self-heal on existing installs where theme re-activation hasn't run
		// since this cron hook was added.
		add_action( 'init', [ $this, 'schedule_sweeper' ] );
	}

	/**
	 * Schedule the daily sweeper cron event if not already scheduled.
	 */
	public function schedule_sweeper(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow 6:00' ), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the daily sweeper cron event (theme deactivation).
	 */
	public function unschedule_sweeper(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Run the scheduled-send sweep (cron callback).
	 */
	public function run_sweep(): void {
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}

		set_transient( self::LOCK_TRANSIENT, true, 5 * MINUTE_IN_SECONDS );

		try {
			$this->process_invoices();
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Query all due scheduled draft invoices and send each one.
	 */
	private function process_invoices(): void {
		$today = current_time( 'Ymd' );

		$invoice_ids = get_posts(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => 'rondo_draft',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => '_scheduled_send_date',
						'value'   => $today,
						'compare' => '<=',
						'type'    => 'CHAR',
					],
				],
			]
		);

		if ( empty( $invoice_ids ) ) {
			return;
		}

		foreach ( $invoice_ids as $invoice_id ) {
			$this->send_scheduled_invoice( (int) $invoice_id );
		}
	}

	/**
	 * Send a single scheduled invoice via the normal send flow.
	 *
	 * @param int $invoice_id Invoice post ID.
	 */
	private function send_scheduled_invoice( int $invoice_id ): void {
		$scheduled = (string) get_post_meta( $invoice_id, '_scheduled_send_date', true );

		// Guard against empty/future values that could slip through the query.
		if ( $scheduled === '' || $scheduled > current_time( 'Ymd' ) ) {
			return;
		}

		$post = get_post( $invoice_id );
		if ( ! $post || $post->post_type !== 'rondo_invoice' || $post->post_status !== 'rondo_draft' ) {
			// No longer a sendable draft — drop the stale schedule.
			delete_post_meta( $invoice_id, '_scheduled_send_date' );
			delete_post_meta( $invoice_id, '_scheduled_send_by_user_id' );
			return;
		}

		$scheduler_uid = (int) get_post_meta( $invoice_id, '_scheduled_send_by_user_id', true );
		$previous_uid  = get_current_user_id();
		if ( $scheduler_uid > 0 ) {
			wp_set_current_user( $scheduler_uid );
		}

		try {
			$request = new \WP_REST_Request( 'POST', "/rondo/v1/invoices/{$invoice_id}/send" );
			$request->set_param( 'id', $invoice_id );

			$controller = new \Rondo\REST\Invoices();
			$result     = $controller->send_invoice( $request );

			if ( is_wp_error( $result ) ) {
				error_log(
					sprintf(
						'[InvoiceScheduledSendScheduler] Invoice %d: scheduled send failed — %s',
						$invoice_id,
						$result->get_error_message()
					)
				);
			} else {
				error_log(
					sprintf(
						'[InvoiceScheduledSendScheduler] Invoice %d: scheduled send completed (scheduled for %s).',
						$invoice_id,
						$scheduled
					)
				);
			}
		} catch ( \Throwable $e ) {
			error_log(
				sprintf(
					'[InvoiceScheduledSendScheduler] Invoice %d: exception during scheduled send — %s',
					$invoice_id,
					$e->getMessage()
				)
			);
		} finally {
			wp_set_current_user( $previous_uid );
		}
	}
}
