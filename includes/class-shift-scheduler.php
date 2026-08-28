<?php
/**
 * ShiftScheduler
 *
 * Hosts the WordPress hooks for the volunteer-shift lifecycle:
 *
 *   1. `rondo_complete_shifts` hourly cron — flips shifts past `end_datetime + 1h`
 *      to `voltooid`. Triggers the obligation cache to invalidate.
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
}
