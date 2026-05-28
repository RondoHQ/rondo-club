<?php
/**
 * ShiftScheduler
 *
 * Hosts the WordPress hooks for the volunteer-shift lifecycle:
 *
 *   1. `rondo_complete_shifts` hourly cron — flips shifts past `end_datetime + 1h`
 *      to `voltooid`. Triggers the obligation cache to invalidate.
 *
 *   2. Triggers the boete pipeline (#7) whenever a person is marked no-show.
 *
 * The actual no-show write happens through {@see VolunteerObligationCalculator::mark_no_show()}
 * via the REST endpoint — this class wires the boete generator in afterwards.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShiftScheduler {

	const CRON_HOOK = 'rondo_complete_shifts';

	public function __construct() {
		add_action( 'init', [ $this, 'register_cron' ] );
		add_action( self::CRON_HOOK, [ $this, 'run_complete_shifts' ] );

		// Wire the boete generator into no-show events.
		add_action( 'rondo_volunteer_no_show_marked', [ $this, 'on_no_show_marked' ], 10, 3 );
	}

	public function register_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function unregister_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public function run_complete_shifts() {
		$count = VolunteerObligationCalculator::auto_complete_shifts();
		if ( $count > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[Rondo Volunteer] Auto-completed %d shift(s) past their end window.', $count ) );
		}
	}

	/**
	 * Hook: when a coordinator marks a person as no-show, immediately generate
	 * a €30 boete invoice (bestuursbesluit 2026-05-26).
	 */
	public function on_no_show_marked( int $shift_id, int $person_id, int $marked_by_user ): void {
		if ( ! class_exists( '\Rondo\Volunteer\VolunteerFineGenerator' ) ) {
			return;
		}
		try {
			VolunteerFineGenerator::on_no_show( $shift_id, $person_id, $marked_by_user );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
				'[Rondo Volunteer] Failed to generate fine for shift %d / person %d: %s',
				$shift_id,
				$person_id,
				$e->getMessage()
			)
				);
		}
	}
}
