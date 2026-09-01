<?php
/**
 * Daily tournament payment reminder sweeper.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use DateTimeImmutable;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentPaymentReminderScheduler {
	public const CRON_HOOK       = 'rondo_tournament_payment_reminder_sweeper';
	private const LOCK_TRANSIENT = 'rondo_tournament_payment_reminder_lock';

	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_sweep' ] );
		add_action( 'init', [ $this, 'schedule_sweeper' ] );
		add_action( 'after_switch_theme', [ $this, 'schedule_sweeper' ] );
		add_action( 'switch_theme', [ $this, 'unschedule_sweeper' ] );
	}

	public function schedule_sweeper(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow 08:00' ), 'daily', self::CRON_HOOK );
		}
	}

	public function unschedule_sweeper(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public function run_sweep(): void {
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}
		set_transient( self::LOCK_TRANSIENT, true, 5 * MINUTE_IN_SECONDS );
		try {
			$entry_ids = get_posts(
				[
					'post_type'        => TournamentService::ENTRY_POST_TYPE,
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'meta_key'         => 'registration_status',
					'meta_value'       => 'submitted',
				]
			);
			foreach ( $entry_ids as $entry_id ) {
				$this->process_entry( (int) $entry_id );
			}
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/** Process due reminder moments for one entry. Public for focused tests. */
	public function process_entry( int $entry_id ): void {
		$fields     = Fields::all_for_post( $entry_id );
		$invoice_id = (int) ( $fields['invoice_id'] ?? 0 );
		if ( (string) ( $fields['registration_status'] ?? '' ) !== 'submitted' || $invoice_id <= 0 || get_post_status( $invoice_id ) === 'rondo_paid' || (string) Fields::get_for_post( $invoice_id, 'payment_link' ) === '' ) {
			return;
		}
		$tournament_id  = (int) ( $fields['tournament_id'] ?? 0 );
		$tournament     = Fields::all_for_post( $tournament_id );
		$deadline_value = (string) ( $tournament['payment_deadline'] ?? $tournament['internal_deadline'] ?? '' );
		if ( $deadline_value === '' ) {
			return;
		}
		try {
			$today    = current_datetime()->setTime( 0, 0 );
			$deadline = ( new DateTimeImmutable( $deadline_value, wp_timezone() ) )->setTime( 0, 0 );
		} catch ( \Exception $error ) {
			return;
		}
		if ( $today > $deadline ) {
			return;
		}
		$rows = $tournament['payment_reminder_days'] ?? [];
		$days = empty( $rows ) ? [ 7, 2 ] : array_map( static fn( $row ): int => absint( is_array( $row ) ? ( $row['days_before'] ?? 0 ) : $row ), $rows );
		$days = array_values( array_unique( $days ) );
		sort( $days, SORT_NUMERIC );
		foreach ( $days as $days_before ) {
			if ( $today >= $deadline->modify( '-' . $days_before . ' days' ) ) {
				TournamentPaymentEmail::send_automatic_reminder( $entry_id, $days_before );
				break;
			}
		}
	}
}
