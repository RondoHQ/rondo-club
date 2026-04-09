<?php
/**
 * Person Fee Context
 *
 * Person-data helpers that the fee system needs: current team membership
 * derived from the work_history ACF repeater, effective werkfuncties list
 * with donateur normalisation, and the former-member-in-season eligibility
 * check used by fee snapshots and invoicing.
 *
 * Extracted from {@see MembershipFees} in Phase 218 of the v33.0 Fee
 * Service Decomposition milestone, as part of retiring the god class.
 * These helpers are narrow fee-context accessors — they stay together
 * because FeeCalculator, FamilyGroupingService and the snapshot tool all
 * need them side by side.
 *
 * Zero constructor dependencies: all methods read ACF fields directly.
 * Any service can instantiate `new PersonFeeContext()` freely.
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Person fee context helpers.
 */
class PersonFeeContext {

	/**
	 * Job title values (lowercased) that count as "player" roles for fee
	 * category matching. Staff roles (Trainer, Teammanager, etc.) are
	 * deliberately excluded so they don't get assigned team-based fees.
	 */
	private const PLAYER_JOB_TITLES = [
		'teamspeler',
		'verdediger',
		'middenvelder',
		'aanvaller',
		'keeper',
		'zondag recranten',
		'zaterdag recreanten',
	];

	/**
	 * Check whether a work_history row represents a current position.
	 *
	 * @param array<string,mixed> $job   A single work_history row.
	 * @param int                 $today Timestamp for today's date.
	 * @return bool True when the row is considered current.
	 */
	private function is_current_work_history_entry( array $job, int $today ): bool {
		if ( ! empty( $job['is_current'] ) ) {
			if ( ! empty( $job['end_date'] ) ) {
				$end_date = strtotime( (string) $job['end_date'] );
				return $end_date !== false && $end_date >= $today;
			}
			return true;
		}

		if ( empty( $job['end_date'] ) ) {
			return true;
		}

		$end_date = strtotime( (string) $job['end_date'] );
		return $end_date !== false && $end_date >= $today;
	}

	/**
	 * Get effective werkfuncties for a person.
	 *
	 * Uses current work_history job titles as the source of truth for
	 * fee matching.
	 *
	 * @param int $person_id The person post ID.
	 * @return array<string> List of unique werkfuncties.
	 */
	public function get_effective_werkfuncties( int $person_id ): array {
		$work_history = get_field( 'work_history', $person_id ) ?: [];
		if ( empty( $work_history ) ) {
			return [];
		}

		$today   = strtotime( 'today' );
		$derived = [];
		foreach ( $work_history as $job ) {
			if ( ! is_array( $job ) ) {
				continue;
			}

			if ( ! $this->is_current_work_history_entry( $job, $today ) ) {
				continue;
			}

			$job_title = trim( (string) ( $job['job_title'] ?? '' ) );
			if ( $job_title === '' ) {
				continue;
			}

			$derived[] = $job_title;
		}

		return array_values( array_unique( $derived ) );
	}

	/**
	 * Normalize werkfuncties for fee matching.
	 *
	 * "Donateur" is treated as a donateur-only signal: when combined with
	 * any other function, it is removed from matching so active roles can
	 * determine the fee category.
	 *
	 * @param array<string> $werkfuncties Raw werkfuncties list.
	 * @return array<string> Normalized list used for category matching.
	 */
	public function normalize_werkfuncties_for_fee_match( array $werkfuncties ): array {
		$normalized = [];
		foreach ( $werkfuncties as $functie ) {
			$functie = trim( (string) $functie );
			if ( $functie !== '' ) {
				$normalized[] = $functie;
			}
		}

		$normalized = array_values( array_unique( $normalized ) );

		if ( count( $normalized ) > 1 ) {
			$normalized = array_values(
				array_filter(
					$normalized,
					function ( string $functie ): bool {
						return strcasecmp( $functie, 'Donateur' ) !== 0;
					}
				)
			);
		}

		return $normalized;
	}

	/**
	 * Get current team IDs for a person.
	 *
	 * Retrieves team IDs from the work_history ACF repeater where the
	 * person is currently active (is_current flag or end_date in future /
	 * not set).
	 *
	 * For fee calculation purposes, only includes teams where the person
	 * has a player role (Teamspeler, positional roles, recreational
	 * player). Staff roles like Trainer, Teammanager, etc. are excluded
	 * to prevent non-players from being assigned team-based fees.
	 *
	 * @param int $person_id The person post ID.
	 * @return array<int> Array of unique team IDs.
	 */
	public function get_current_teams( int $person_id ): array {
		$work_history = get_field( 'work_history', $person_id ) ?: [];
		$team_ids     = [];

		if ( empty( $work_history ) ) {
			return [];
		}

		$today = strtotime( 'today' );

		foreach ( $work_history as $job ) {
			// Skip if no team reference
			if ( ! isset( $job['team'] ) || empty( $job['team'] ) ) {
				continue;
			}

			// Only include player roles — skip staff (trainers, managers, etc.)
			$job_title = strtolower( trim( $job['job_title'] ?? '' ) );
			if ( ! in_array( $job_title, self::PLAYER_JOB_TITLES, true ) ) {
				continue;
			}

			$team_id  = (int) $job['team'];
			$job_post = get_post( $team_id );

			// Verify the post is actually a team
			if ( ! $job_post || $job_post->post_type !== 'team' ) {
				continue;
			}

			$is_current = $this->is_current_work_history_entry( $job, $today );

			if ( $is_current && ! in_array( $team_id, $team_ids, true ) ) {
				$team_ids[] = $team_id;
			}
		}

		return $team_ids;
	}

	/**
	 * Check if a former member should be included in the season's fee list.
	 *
	 * A former member qualifies if their lid-sinds date is BEFORE the end
	 * of the season (July 1 of the season's end year). This includes:
	 * - Members who joined before season start and left during it (normal
	 *   fee, no pro-rata)
	 * - Members who joined mid-season and left during it (pro-rata based
	 *   on lid-sinds)
	 *
	 * Former members whose lid-sinds is after the season end date are
	 * excluded (they never participated in that season).
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return bool True if former member qualifies for season, false otherwise.
	 */
	public function is_former_member_in_season( int $person_id, ?string $season = null ): bool {
		// Only applies to former members
		$is_former = (bool) get_field( 'former_member', $person_id );
		if ( ! $is_former ) {
			return false;
		}

		// Get lid-sinds date
		$lid_sinds = get_field( 'lid-sinds', $person_id );
		if ( empty( $lid_sinds ) ) {
			// Cannot determine eligibility without membership date
			return false;
		}

		// Calculate season end date (July 1 of season's end year)
		$season          = $season ?? SeasonKey::current();
		$season_end_year = (int) substr( $season, 5, 4 );
		$season_end_date = strtotime( $season_end_year . '-07-01' );

		// Parse lid-sinds timestamp
		$lid_sinds_ts = strtotime( $lid_sinds );
		if ( $lid_sinds_ts === false ) {
			return false;
		}

		// Qualifies if joined before season end
		return ( $lid_sinds_ts < $season_end_date );
	}
}
