<?php
/**
 * Daily, aggregate-only volunteer policy observations.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Fees\SeasonKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VolunteerPolicyHistory {
	const CRON_HOOK     = 'rondo_volunteer_policy_snapshot';
	const OPTION_PREFIX = 'rondo_volunteer_policy_history_';

	public function __construct() {
		add_action( 'init', [ $this, 'register_cron' ] );
		add_action( self::CRON_HOOK, [ $this, 'capture' ], 10, 0 );
	}

	/** Start shortly after deployment, then retain one observation per day. */
	public function register_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/** Remove the daily job when the theme is deactivated. */
	public static function unregister_cron(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/** Read without changing stored history or reconstructing past observations. */
	public static function for_season( string $season ): array {
		$rows = get_option( self::OPTION_PREFIX . $season, [] );
		return is_array( $rows ) ? array_values( $rows ) : [];
	}

	/** Store club-wide counts; an optional clock permits deterministic boundary tests. */
	public function capture( ?\DateTimeImmutable $now = null ): bool {
		// WordPress runs on one host. The OS releases this lock even on process
		// failure; a stale option lock must not stop all future daily captures.
		$handle = fopen( get_temp_dir() . 'rondo-volunteer-policy-' . md5( ABSPATH ) . '.lock', 'c' );
		if ( ! $handle ) {
			return false;
		}
		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			fclose( $handle );
			return false;
		}
		try {
			$now    = $now ?? current_datetime();
			$date   = $now->format( 'Y-m-d' );
			$season = SeasonKey::current( $date );
			$key    = self::OPTION_PREFIX . $season;
			// Another process may have filled this request's options cache before
			// acquiring the lock. Read the persisted series again while locked.
			wp_cache_delete( $key, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$rows = self::for_season( $season );
			if ( in_array( $date, array_column( $rows, 'date' ), true ) ) {
				return true;
			}
			$data   = ( new VolunteerStatistics() )->for_season( $season );
			$rows[] = array_merge(
				$data['policy'],
				[
					'date'         => $date,
					'generated_at' => $now->format( DATE_ATOM ),
				]
			);
			// Each season has at most 366 daily observations; never autoload history.
			return update_option( $key, array_slice( $rows, -366 ), false );
		} finally {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}
}
