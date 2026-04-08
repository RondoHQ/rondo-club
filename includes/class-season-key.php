<?php
/**
 * Season Key helper
 *
 * Stateless arithmetic over Rondo's "YYYY-YYYY" season key format.
 * The sports season starts July 1 and runs through June 30 of the following year.
 *
 * Extracted from MembershipFees so that classes which only need "what season is it?"
 * (wallet passes, public pages) don't have to depend on the full fee service.
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Season key helper.
 */
class SeasonKey {

	/**
	 * Get the current season key, or the season containing a given date.
	 *
	 * The season starts July 1. Dates from July-December return "YYYY-(YYYY+1)",
	 * dates from January-June return "(YYYY-1)-YYYY".
	 *
	 * @param string|null $date Optional date string parseable by strtotime(). Defaults to now.
	 * @return string Season key in "YYYY-YYYY" format.
	 */
	public static function current( ?string $date = null ): string {
		$timestamp = $date ? strtotime( $date ) : time();
		$month     = (int) date( 'n', $timestamp );
		$year      = (int) date( 'Y', $timestamp );

		// Season starts July 1: if month >= 7, season is current year to next year
		$season_start_year = $month >= 7 ? $year : $year - 1;

		return $season_start_year . '-' . ( $season_start_year + 1 );
	}

	/**
	 * Get the next season key (one year ahead of current/specified season).
	 *
	 * Example: "2025-2026" returns "2026-2027".
	 *
	 * @param string|null $current_season Optional season key, defaults to current season.
	 * @return string Next season key in "YYYY-YYYY" format.
	 */
	public static function next( ?string $current_season = null ): string {
		if ( $current_season === null ) {
			$current_season = self::current();
		}

		// Extract start year from "YYYY-YYYY" format
		$season_start_year = (int) substr( $current_season, 0, 4 );

		// Next season is +1 year
		$next_start_year = $season_start_year + 1;

		return $next_start_year . '-' . ( $next_start_year + 1 );
	}

	/**
	 * Get the previous season key (one year behind a given season).
	 *
	 * Example: "2025-2026" returns "2024-2025".
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return string|null Previous season key in "YYYY-YYYY" format, or null if format is invalid.
	 */
	public static function previous( string $season ): ?string {
		// Validate format: "YYYY-YYYY"
		if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
			return null;
		}

		$season_start_year = (int) $matches[1];

		// Previous season is -1 year
		$prev_start_year = $season_start_year - 1;

		return $prev_start_year . '-' . $season_start_year;
	}
}
