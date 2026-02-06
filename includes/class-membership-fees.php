<?php
/**
 * Membership Fees Service
 *
 * Handles membership fee settings storage and retrieval using the WordPress Options API.
 *
 * @package Stadion\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Membership Fees service class
 */
class MembershipFees {

	/**
	 * Option key for storing all membership fee settings
	 */
	const OPTION_KEY = 'stadion_membership_fees';

	/**
	 * Default fee amounts (in euros)
	 *
	 * @var array<string, int>
	 */
	const DEFAULTS = [
		'mini'     => 130,
		'pupil'    => 180,
		'junior'   => 230,
		'senior'   => 255,
		'recreant' => 65,
		'donateur' => 55,
	];

	/**
	 * Valid fee type keys
	 *
	 * @var array<string>
	 */
	const VALID_TYPES = [ 'mini', 'pupil', 'junior', 'senior', 'recreant', 'donateur' ];

	/**
	 * Get the option key for a specific season
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return string Option key for season-specific fee storage.
	 */
	public function get_option_key_for_season( string $season ): string {
		return 'stadion_membership_fees_' . $season;
	}

	/**
	 * Get fee settings for a specific season
	 *
	 * Handles automatic migration from old global option to current season on first read.
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return array<string, int> Array of fee type => amount pairs.
	 */
	public function get_settings_for_season( string $season ): array {
		$season_key = $this->get_option_key_for_season( $season );
		$stored     = get_option( $season_key, false );

		// If season option exists, use it
		if ( $stored !== false && is_array( $stored ) ) {
			$settings = array_merge( self::DEFAULTS, $stored );
			return array_map( 'intval', $settings );
		}

		// Season option doesn't exist - check for migration
		$current_season = $this->get_season_key();
		if ( $season === $current_season ) {
			// Check if old global option exists (migration needed)
			$old_stored = get_option( self::OPTION_KEY, false );

			if ( $old_stored !== false && is_array( $old_stored ) ) {
				// Migrate: copy old global option to season-specific option
				update_option( $season_key, $old_stored );
				// Delete old global option (one-time migration)
				delete_option( self::OPTION_KEY );
				// Return the migrated values
				$settings = array_merge( self::DEFAULTS, $old_stored );
				return array_map( 'intval', $settings );
			}
		}

		// No data for this season, return defaults
		return self::DEFAULTS;
	}

	/**
	 * Update fee settings for a specific season
	 *
	 * @param array<string, mixed> $fees   Array of fee type => amount pairs to update.
	 * @param string               $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return bool True on success, false on failure.
	 */
	public function update_settings_for_season( array $fees, string $season ): bool {
		// Get current settings for this season
		$current = $this->get_settings_for_season( $season );

		// Validate and merge new values
		foreach ( $fees as $type => $amount ) {
			// Skip invalid types
			if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
				continue;
			}

			// Validate: must be numeric and non-negative
			if ( ! is_numeric( $amount ) || $amount < 0 ) {
				continue;
			}

			$current[ $type ] = (int) $amount;
		}

		// Save to season-specific option
		$season_key = $this->get_option_key_for_season( $season );
		return update_option( $season_key, $current );
	}

	/**
	 * Get all fee settings
	 *
	 * @return array<string, int> Array of fee type => amount pairs
	 */
	public function get_all_settings(): array {
		// Use current season settings for backward compatibility
		return $this->get_settings_for_season( $this->get_season_key() );
	}

	/**
	 * Get a single fee amount by type
	 *
	 * @param string      $type   The fee type (mini, pupil, junior, senior, recreant, donateur).
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return int The fee amount in euros
	 */
	public function get_fee( string $type, ?string $season = null ): int {
		$settings = $season !== null
			? $this->get_settings_for_season( $season )
			: $this->get_all_settings();

		if ( ! isset( $settings[ $type ] ) ) {
			// Return 0 for unknown types
			return 0;
		}

		return $settings[ $type ];
	}

	/**
	 * Update fee settings
	 *
	 * @param array<string, mixed> $fees Array of fee type => amount pairs to update.
	 * @return bool True on success, false on failure
	 */
	public function update_settings( array $fees ): bool {
		// Use current season for backward compatibility
		return $this->update_settings_for_season( $fees, $this->get_season_key() );
	}

	/**
	 * Parse leeftijdsgroep (age group) to fee category
	 *
	 * Normalizes the age group string and maps it to the appropriate fee category.
	 * Strips " Meiden" and " Vrouwen" suffixes before matching.
	 *
	 * @param string $leeftijdsgroep The age group string (e.g., "Onder 14", "Senioren", "Onder 8 Meiden").
	 * @return string|null The fee category (mini, pupil, junior, senior) or null if unrecognized.
	 */
	public function parse_age_group( string $leeftijdsgroep ): ?string {
		// Normalize: trim and strip " Meiden" / " Vrouwen" suffixes
		$normalized = preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', trim( $leeftijdsgroep ) );

		// Handle empty string after normalization
		if ( empty( $normalized ) ) {
			return null;
		}

		// Handle "Senioren" (case-insensitive)
		if ( strcasecmp( $normalized, 'Senioren' ) === 0 ) {
			return 'senior';
		}

		// Handle "JO23" format (treated as senior)
		if ( preg_match( '/^JO\s*23$/i', $normalized ) ) {
			return 'senior';
		}

		// Extract number from "Onder X" format
		if ( preg_match( '/^Onder\s+(\d+)$/i', $normalized, $matches ) ) {
			$age = (int) $matches[1];

			// Map age ranges to fee categories
			if ( $age >= 6 && $age <= 7 ) {
				return 'mini';
			}
			if ( $age >= 8 && $age <= 11 ) {
				return 'pupil';
			}
			if ( $age >= 12 && $age <= 19 ) {
				return 'junior';
			}
		}

		// Handle "JO" format (e.g., JO14, JO8)
		if ( preg_match( '/^JO\s*(\d+)$/i', $normalized, $matches ) ) {
			$age = (int) $matches[1];

			if ( $age >= 6 && $age <= 7 ) {
				return 'mini';
			}
			if ( $age >= 8 && $age <= 11 ) {
				return 'pupil';
			}
			if ( $age >= 12 && $age <= 19 ) {
				return 'junior';
			}
		}

		// Unrecognized format
		return null;
	}

	/**
	 * Get current team IDs for a person
	 *
	 * Retrieves team IDs from the work_history ACF repeater field where the person
	 * is currently active (is_current flag or end_date in future/not set).
	 *
	 * For fee calculation purposes, excludes teams where the job_title is "Donateur"
	 * since donateurs are non-playing members and shouldn't affect team-based fees.
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

			// Skip donateur roles - they are non-playing members
			if ( ! empty( $job['job_title'] ) && strcasecmp( trim( $job['job_title'] ), 'Donateur' ) === 0 ) {
				continue;
			}

			$team_id  = (int) $job['team'];
			$job_post = get_post( $team_id );

			// Verify the post is actually a team
			if ( ! $job_post || $job_post->post_type !== 'team' ) {
				continue;
			}

			// Determine if person is currently on this team
			$is_current = false;

			if ( ! empty( $job['is_current'] ) ) {
				// is_current flag is set
				if ( ! empty( $job['end_date'] ) ) {
					$end_date   = strtotime( $job['end_date'] );
					$is_current = ( $end_date >= $today );
				} else {
					$is_current = true;
				}
			} elseif ( empty( $job['end_date'] ) ) {
				// No end date means still current
				$is_current = true;
			} else {
				// Check if end date is in future
				$end_date   = strtotime( $job['end_date'] );
				$is_current = ( $end_date >= $today );
			}

			if ( $is_current && ! in_array( $team_id, $team_ids, true ) ) {
				$team_ids[] = $team_id;
			}
		}

		return $team_ids;
	}

	/**
	 * Check if a team is a recreational team
	 *
	 * Recreational teams have "recreant" or "walking football" or "walking voetbal" in their name.
	 *
	 * @param int $team_id The team post ID.
	 * @return bool True if the team is recreational.
	 */
	public function is_recreational_team( int $team_id ): bool {
		$team = get_post( $team_id );

		if ( ! $team || $team->post_type !== 'team' ) {
			return false;
		}

		$title = strtolower( $team->post_title );

		return ( stripos( $title, 'recreant' ) !== false || stripos( $title, 'walking football' ) !== false || stripos( $title, 'walking voetbal' ) !== false );
	}

	/**
	 * Check if a person is a donateur (donor) only
	 *
	 * Returns true only if the person has exactly one werkfunctie and it is "Donateur".
	 *
	 * @param int $person_id The person post ID.
	 * @return bool True if the person is a donateur only.
	 */
	public function is_donateur( int $person_id ): bool {
		$werkfuncties = get_field( 'werkfuncties', $person_id ) ?: [];

		if ( empty( $werkfuncties ) ) {
			return false;
		}

		// True only if exactly one function and it's "Donateur"
		return ( count( $werkfuncties ) === 1 && in_array( 'Donateur', $werkfuncties, true ) );
	}

	/**
	 * Calculate the fee for a person
	 *
	 * Determines the correct fee category and amount based on the person's
	 * age group, team membership, and work functions.
	 *
	 * Priority order: Youth > Senior/Recreant > Donateur
	 * - Youth (mini/pupil/junior): Always get age-based fee
	 * - Senior: Regular senior fee, unless ALL teams are recreational
	 * - Recreant: Senior with only recreational teams
	 * - Donateur: Only if no valid age group and no teams
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key for fee lookup, defaults to current season.
	 * @return array{category: string, base_fee: int, leeftijdsgroep: string|null, person_id: int}|null
	 *         Fee data array or null if person cannot be calculated.
	 */
	public function calculate_fee( int $person_id, ?string $season = null ): ?array {
		// Get leeftijdsgroep from person
		$leeftijdsgroep = get_field( 'leeftijdsgroep', $person_id );
		$category       = null;

		// Parse age group if available
		if ( ! empty( $leeftijdsgroep ) ) {
			$category = $this->parse_age_group( $leeftijdsgroep );
		}

		// Youth categories: Return immediately (priority over everything)
		if ( in_array( $category, [ 'mini', 'pupil', 'junior' ], true ) ) {
			return [
				'category'       => $category,
				'base_fee'       => $this->get_fee( $category, $season ),
				'leeftijdsgroep' => $leeftijdsgroep,
				'person_id'      => $person_id,
			];
		}

		// Senior category: Check for recreational teams
		if ( $category === 'senior' ) {
			$teams = $this->get_current_teams( $person_id );

			// Senior with no teams: check donateur status
			if ( empty( $teams ) ) {
				// If senior with no teams but is donateur, they're still a senior member
				// If no teams and not a playing member, exclude
				if ( $this->is_donateur( $person_id ) ) {
					// Has senior leeftijdsgroep but only donateur function, no teams
					// Treat as donateur
					return [
						'category'       => 'donateur',
						'base_fee'       => $this->get_fee( 'donateur', $season ),
						'leeftijdsgroep' => $leeftijdsgroep,
						'person_id'      => $person_id,
					];
				}

				// Senior with no teams and not donateur - exclude
				return null;
			}

			// Check if ALL teams are recreational
			$all_recreational = true;
			foreach ( $teams as $team_id ) {
				if ( ! $this->is_recreational_team( $team_id ) ) {
					$all_recreational = false;
					break;
				}
			}

			// If ALL teams are recreational, use recreant fee
			// Otherwise, use senior fee (higher fee wins)
			$fee_category = $all_recreational ? 'recreant' : 'senior';

			return [
				'category'       => $fee_category,
				'base_fee'       => $this->get_fee( $fee_category, $season ),
				'leeftijdsgroep' => $leeftijdsgroep,
				'person_id'      => $person_id,
			];
		}

		// No valid leeftijdsgroep or parse failed
		// Check if person has teams (data issue - exclude)
		$teams = $this->get_current_teams( $person_id );

		if ( ! empty( $teams ) ) {
			// Has teams but no valid age group - data issue, exclude
			return null;
		}

		// No teams, check if donateur
		if ( $this->is_donateur( $person_id ) ) {
			return [
				'category'       => 'donateur',
				'base_fee'       => $this->get_fee( 'donateur', $season ),
				'leeftijdsgroep' => $leeftijdsgroep,
				'person_id'      => $person_id,
			];
		}

		// No valid category, no teams, not donateur - exclude
		return null;
	}

	/**
	 * Get the season key for a given date
	 *
	 * Season starts July 1 (month 7). Returns format "YYYY-YYYY" (e.g., "2025-2026").
	 * If date is July or later, season is current year to next year.
	 * If date is before July, season is previous year to current year.
	 *
	 * @param string|null $date Optional date string (parseable by strtotime), defaults to current date.
	 * @return string Season key in "YYYY-YYYY" format.
	 */
	public function get_season_key( ?string $date = null ): string {
		$timestamp = $date ? strtotime( $date ) : time();
		$month     = (int) date( 'n', $timestamp );
		$year      = (int) date( 'Y', $timestamp );

		// Season starts July 1: if month >= 7, season is current year to next year
		$season_start_year = $month >= 7 ? $year : $year - 1;

		return $season_start_year . '-' . ( $season_start_year + 1 );
	}

	/**
	 * Get the next season key (one year ahead of current/specified season)
	 *
	 * Takes a season key in "YYYY-YYYY" format and returns the next season.
	 * Example: "2025-2026" returns "2026-2027"
	 *
	 * @param string|null $current_season Optional season key, defaults to current season.
	 * @return string Next season key in "YYYY-YYYY" format.
	 */
	public function get_next_season_key( ?string $current_season = null ): string {
		if ( $current_season === null ) {
			$current_season = $this->get_season_key();
		}

		// Extract start year from "YYYY-YYYY" format
		$season_start_year = (int) substr( $current_season, 0, 4 );

		// Next season is +1 year
		$next_start_year = $season_start_year + 1;

		return $next_start_year . '-' . ( $next_start_year + 1 );
	}

	/**
	 * Get the post meta key for storing fee snapshots
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return string Meta key for fee snapshot storage.
	 */
	public function get_snapshot_meta_key( ?string $season = null ): string {
		return 'fee_snapshot_' . ( $season ?: $this->get_season_key() );
	}

	/**
	 * Save a fee snapshot for a person
	 *
	 * Stores fee calculation result in post meta with a timestamp.
	 * This locks the fee for the season, preventing recalculation unless explicitly requested.
	 *
	 * @param int         $person_id The person post ID.
	 * @param array       $fee_data  Fee calculation result (category, base_fee, etc.).
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return bool True on success, false on failure.
	 */
	public function save_fee_snapshot( int $person_id, array $fee_data, ?string $season = null ): bool {
		$meta_key = $this->get_snapshot_meta_key( $season );

		// Add calculated_at timestamp
		$fee_data['calculated_at'] = current_time( 'Y-m-d H:i:s' );

		return (bool) update_post_meta( $person_id, $meta_key, $fee_data );
	}

	/**
	 * Get the fee snapshot for a person
	 *
	 * Retrieves the stored fee calculation for the specified season.
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return array|null Fee snapshot data or null if not found.
	 */
	public function get_fee_snapshot( int $person_id, ?string $season = null ): ?array {
		$meta_key = $this->get_snapshot_meta_key( $season );
		$snapshot = get_post_meta( $person_id, $meta_key, true );

		if ( empty( $snapshot ) || ! is_array( $snapshot ) ) {
			return null;
		}

		return $snapshot;
	}

	/**
	 * Clear the fee snapshot for a person
	 *
	 * Removes the stored fee calculation, allowing fresh recalculation.
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return bool True on success, false on failure.
	 */
	public function clear_fee_snapshot( int $person_id, ?string $season = null ): bool {
		$meta_key = $this->get_snapshot_meta_key( $season );

		return delete_post_meta( $person_id, $meta_key );
	}

	/**
	 * Get the fee for a person with caching support
	 *
	 * This is the primary public API for fee retrieval. It checks for cached snapshots
	 * first, and calculates fresh if needed. Results can be automatically saved to
	 * the snapshot cache for future retrieval.
	 *
	 * @param int   $person_id The person post ID.
	 * @param array $options   {
	 *     Optional. Configuration options.
	 *
	 *     @type bool        $use_cache         Whether to check for cached snapshot. Default true.
	 *     @type bool        $save_snapshot     Whether to save result to cache. Default true.
	 *     @type string|null $season            Season key to use. Default current season.
	 *     @type bool        $force_recalculate Whether to ignore cache and recalculate. Default false.
	 * }
	 * @return array|null Fee data with season and cache info, or null if not calculable.
	 */
	public function get_fee_for_person( int $person_id, array $options = [] ): ?array {
		// Parse options with defaults
		$use_cache         = $options['use_cache'] ?? true;
		$save_snapshot     = $options['save_snapshot'] ?? true;
		$season            = $options['season'] ?? $this->get_season_key();
		$force_recalculate = $options['force_recalculate'] ?? false;

		// Check cache first (unless force recalculate)
		if ( $use_cache && ! $force_recalculate ) {
			$cached = $this->get_fee_snapshot( $person_id, $season );

			if ( $cached !== null ) {
				// Return cached result with cache flag
				$cached['from_cache'] = true;
				$cached['season']     = $season;

				return $cached;
			}
		}

		// Calculate fresh
		$result = $this->calculate_fee( $person_id );

		if ( $result === null ) {
			return null;
		}

		// Add metadata
		$result['season']     = $season;
		$result['from_cache'] = false;

		// Save to snapshot if requested
		if ( $save_snapshot ) {
			$this->save_fee_snapshot( $person_id, $result, $season );
			// Add calculated_at timestamp to return value (save_fee_snapshot adds it to stored data)
			$result['calculated_at'] = current_time( 'Y-m-d H:i:s' );
		}

		return $result;
	}

	/**
	 * Clear all fee snapshots for a season
	 *
	 * Removes all stored fee calculations for the specified season across all people.
	 * This enables the admin "recalculate all" functionality.
	 *
	 * @param string $season The season key (e.g., "2025-2026").
	 * @return int Number of snapshots deleted.
	 */
	public function clear_all_snapshots_for_season( string $season ): int {
		$meta_key = $this->get_snapshot_meta_key( $season );
		$deleted  = 0;

		// Query all person posts
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $person_id ) {
				if ( delete_post_meta( $person_id, $meta_key ) ) {
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Get the post meta key for storing fee cache
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return string Meta key for fee cache storage.
	 */
	public function get_fee_cache_meta_key( ?string $season = null ): string {
		return 'stadion_fee_cache_' . ( $season ?: $this->get_season_key() );
	}

	/**
	 * Save calculated fee to cache for fast retrieval
	 *
	 * Stores the complete fee calculation result in post meta.
	 * This is separate from the snapshot system which is used for season locking.
	 *
	 * @param int         $person_id The person post ID.
	 * @param array       $fee_data  Complete fee calculation result.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return bool True on success, false on failure.
	 */
	public function save_fee_cache( int $person_id, array $fee_data, ?string $season = null ): bool {
		$meta_key = $this->get_fee_cache_meta_key( $season );

		// Add metadata
		$fee_data['calculated_at'] = current_time( 'Y-m-d H:i:s' );
		$fee_data['season']        = $season ?: $this->get_season_key();

		return (bool) update_post_meta( $person_id, $meta_key, $fee_data );
	}

	/**
	 * Get fee for a person with caching for performance
	 *
	 * Checks cache first, calculates if cache miss, saves to cache.
	 * Uses the lid-sinds field for pro-rata calculation (PRO-04).
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return array|null Fee data with cache info, or null if not calculable.
	 */
	public function get_fee_for_person_cached( int $person_id, ?string $season = null ): ?array {
		$season   = $season ?: $this->get_season_key();
		$meta_key = $this->get_fee_cache_meta_key( $season );

		// Try cache first
		$cached = get_post_meta( $person_id, $meta_key, true );

		if ( ! empty( $cached ) && is_array( $cached ) ) {
			$cached['from_cache'] = true;
			return $cached;
		}

		// Cache miss - calculate fresh using lid-sinds (PRO-04 fix)
		$lid_sinds = get_field( 'lid-sinds', $person_id );
		$result    = $this->calculate_full_fee( $person_id, $lid_sinds, $season );

		if ( $result === null ) {
			return null;
		}

		// Save to cache
		$this->save_fee_cache( $person_id, $result, $season );

		// Add cache flag
		$result['from_cache']    = false;
		$result['calculated_at'] = current_time( 'Y-m-d H:i:s' );
		$result['season']        = $season;

		return $result;
	}

	/**
	 * Clear the fee cache for a person
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return bool True on success, false on failure.
	 */
	public function clear_fee_cache( int $person_id, ?string $season = null ): bool {
		$meta_key = $this->get_fee_cache_meta_key( $season );
		return delete_post_meta( $person_id, $meta_key );
	}

	/**
	 * Clear all fee caches for a season
	 *
	 * @param string $season The season key (e.g., "2025-2026").
	 * @return int Number of caches cleared.
	 */
	public function clear_all_fee_caches( string $season ): int {
		$meta_key = $this->get_fee_cache_meta_key( $season );
		$cleared  = 0;

		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $person_id ) {
				if ( delete_post_meta( $person_id, $meta_key ) ) {
					$cleared++;
				}
			}
		}

		return $cleared;
	}

	/**
	 * Normalize a Dutch postal code
	 *
	 * Removes whitespace and converts to uppercase. Dutch postal codes
	 * have format "1234AB" (4 digits + 2 letters).
	 *
	 * @param string $postal_code The postal code to normalize.
	 * @return string Normalized postal code (e.g., "1234 ab" -> "1234AB").
	 */
	public function normalize_postal_code( string $postal_code ): string {
		// Trim, remove all whitespace, and convert to uppercase
		$trimmed    = trim( $postal_code );
		$no_spaces  = preg_replace( '/\s+/', '', $trimmed );

		return strtoupper( $no_spaces );
	}

	/**
	 * Extract house number from a street address
	 *
	 * Parses the house number (with optional addition) from a street address.
	 * Supports formats like "Kerkstraat 12", "Kerkstraat 12A", "Straat 7-bis".
	 *
	 * @param string $street The street address to parse.
	 * @return string|null House number with addition (e.g., "12A") or null if not found.
	 */
	public function extract_house_number( string $street ): ?string {
		$trimmed = trim( $street );

		if ( empty( $trimmed ) ) {
			return null;
		}

		// Match number at end of street, optionally followed by addition
		// Examples: "Straat 12", "Straat 12A", "Straat 12-A", "Straat 12/A"
		if ( preg_match( '/(\d+)\s*[-\/]?\s*([a-zA-Z0-9]*)$/', $trimmed, $matches ) ) {
			$number   = $matches[1];
			$addition = strtoupper( trim( $matches[2] ) );

			if ( ! empty( $addition ) ) {
				return $number . $addition;
			}

			return $number;
		}

		return null;
	}

	/**
	 * Get the family grouping key for a person
	 *
	 * Generates a unique key based on the person's address for grouping
	 * family members. Uses postal code + house number (ignores street name).
	 * House number additions ARE significant (12A and 12B are different families).
	 *
	 * @param int $person_id The person post ID.
	 * @return string|null Family key (e.g., "1234AB-12A") or null if address incomplete.
	 */
	public function get_family_key( int $person_id ): ?string {
		// Get addresses from person
		$addresses = get_field( 'addresses', $person_id ) ?: [];

		if ( empty( $addresses ) ) {
			return null;
		}

		// Use first address as primary
		$primary     = $addresses[0];
		$postal_code = $primary['postal_code'] ?? '';
		$street      = $primary['street'] ?? '';

		// Require both postal code and street
		if ( empty( $postal_code ) || empty( $street ) ) {
			return null;
		}

		// Normalize postal code
		$normalized_postal = $this->normalize_postal_code( $postal_code );

		// Extract house number from street
		$house_number = $this->extract_house_number( $street );

		if ( $house_number === null ) {
			return null;
		}

		// Validate postal code format (4 digits + 2 letters)
		if ( ! preg_match( '/^\d{4}[A-Z]{2}$/', $normalized_postal ) ) {
			return null;
		}

		// Return family key: POSTALCODE-HOUSENUMBER
		return $normalized_postal . '-' . $house_number;
	}

	/**
	 * Build family groups from youth members
	 *
	 * Groups youth members (mini, pupil, junior) by family key (address).
	 * Only includes members with valid addresses and calculable fees.
	 * Members within each family are sorted by base_fee descending.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array{
	 *     families: array<string, array<int>>,
	 *     person_data: array<int, array{person_id: int, family_key: string, base_fee: int, category: string}>
	 * } Family groups and person data.
	 */
	public function build_family_groups( ?string $season = null ): array {
		// Resolve season for consistent usage
		$season = $season ?: $this->get_season_key();

		// Query all person posts (suppress_filters to bypass access control in CLI/cron contexts)
		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		$families    = [];
		$person_data = [];

		// Youth categories eligible for family discount
		$youth_categories = [ 'mini', 'pupil', 'junior' ];

		foreach ( $query->posts as $person_id ) {
			// Calculate fee for this person using season-specific rates
			$fee_data = $this->calculate_fee( $person_id, $season );

			// Skip if not calculable
			if ( $fee_data === null ) {
				continue;
			}

			// Skip if not a youth category (FAM-05: only youth eligible)
			if ( ! in_array( $fee_data['category'], $youth_categories, true ) ) {
				continue;
			}

			// Get family key from address
			$family_key = $this->get_family_key( $person_id );

			// Skip if no valid address
			if ( $family_key === null ) {
				continue;
			}

			// Store person data
			$person_data[ $person_id ] = [
				'person_id'  => $person_id,
				'family_key' => $family_key,
				'base_fee'   => $fee_data['base_fee'],
				'category'   => $fee_data['category'],
			];

			// Add to family group
			if ( ! isset( $families[ $family_key ] ) ) {
				$families[ $family_key ] = [];
			}
			$families[ $family_key ][] = $person_id;
		}

		// Sort members within each family by base_fee descending (highest fee = position 1)
		foreach ( $families as $key => $members ) {
			usort(
				$members,
				function ( $a, $b ) use ( $person_data ) {
					$fee_a = $person_data[ $a ]['base_fee'];
					$fee_b = $person_data[ $b ]['base_fee'];

					// Sort by fee descending (highest first)
					if ( $fee_a !== $fee_b ) {
						return $fee_b - $fee_a;
					}

					// Tie-breaker: lower person_id first
					return $a - $b;
				}
			);

			$families[ $key ] = $members;
		}

		return [
			'families'    => $families,
			'person_data' => $person_data,
		];
	}

	/**
	 * Get discount rate based on family position
	 *
	 * Position is 1-indexed where position 1 is the most expensive youth member
	 * who pays full fee. Position 2 gets 25% off, position 3+ gets 50% off.
	 *
	 * @param int $position 1-indexed position in family (1=most expensive, pays full).
	 * @return float Discount rate (0.0, 0.25, or 0.50).
	 */
	public function get_family_discount_rate( int $position ): float {
		if ( $position <= 1 ) {
			return 0.0;  // First member pays full fee
		}
		if ( $position === 2 ) {
			return 0.25; // Second member gets 25% off
		}
		return 0.50;     // Third+ get 50% off
	}

	/**
	 * Get pro-rata percentage based on registration date relative to season.
	 *
	 * Members who joined BEFORE the current season starts (before July 1 of season start year)
	 * pay 100% - they were already members when the season began.
	 *
	 * Members who join DURING the current season get pro-rata based on quarter:
	 * - Q1 (July-September): 100% - full season
	 * - Q2 (October-December): 75% - 3/4 season
	 * - Q3 (January-March): 50% - 1/2 season
	 * - Q4 (April-June): 25% - 1/4 season
	 *
	 * @param string|null $registration_date Date in Y-m-d format (lid-sinds field), or null for 100%.
	 * @param string|null $season            Optional season key (e.g., "2025-2026"), defaults to current season.
	 * @return float Pro-rata percentage (0.25 to 1.0).
	 */
	public function get_prorata_percentage( ?string $registration_date, ?string $season = null ): float {
		// Null date = full fee (100%)
		if ( $registration_date === null || trim( $registration_date ) === '' ) {
			return 1.0;
		}

		$timestamp = strtotime( $registration_date );
		if ( $timestamp === false ) {
			return 1.0; // Invalid date = full fee
		}

		// Determine the season start date
		$season           = $season ?: $this->get_season_key();
		$season_start_year = (int) substr( $season, 0, 4 );
		$season_start_date = strtotime( $season_start_year . '-07-01' );

		// If member joined BEFORE the season started, they pay 100%
		if ( $timestamp < $season_start_date ) {
			return 1.0;
		}

		// Member joined during the current season - apply quarterly pro-rata
		$month = (int) date( 'n', $timestamp );

		// Q1: July-September = 100%
		if ( $month >= 7 && $month <= 9 ) {
			return 1.0;
		}
		// Q2: October-December = 75%
		if ( $month >= 10 && $month <= 12 ) {
			return 0.75;
		}
		// Q3: January-March = 50%
		if ( $month >= 1 && $month <= 3 ) {
			return 0.50;
		}
		// Q4: April-June = 25%
		return 0.25;
	}

	/**
	 * Calculate complete fee with family discount and pro-rata
	 *
	 * Calculates: base_fee -> apply family discount -> apply pro-rata -> final_fee
	 *
	 * The registration_date should come from ACF field 'lid-sinds' (membership join date).
	 *
	 * @param int         $person_id         The person post ID.
	 * @param string|null $registration_date Sportlink registration date (Y-m-d format).
	 * @param string|null $season            Optional season key, defaults to current season.
	 * @return array|null Complete fee data or null if not calculable.
	 */
	public function calculate_full_fee( int $person_id, ?string $registration_date = null, ?string $season = null ): ?array {
		// Get fee with family discount
		$fee_data = $this->calculate_fee_with_family_discount( $person_id, $season );

		if ( $fee_data === null ) {
			return null;
		}

		// Get pro-rata percentage (pass season to compare against season start date)
		$season             = $season ?: $this->get_season_key();
		$prorata_percentage = $this->get_prorata_percentage( $registration_date, $season );

		// Calculate pro-rata amount (applied to fee after family discount)
		$fee_after_discount = $fee_data['final_fee'];
		$prorata_amount     = round( $fee_after_discount * $prorata_percentage, 2 );

		// Add pro-rata fields to result
		return array_merge(
			$fee_data,
			[
				'registration_date'   => $registration_date,
				'prorata_percentage'  => $prorata_percentage,
				'fee_after_discount'  => $fee_after_discount,
				'final_fee'           => $prorata_amount,  // Override final_fee with pro-rata amount
			]
		);
	}

	/**
	 * Calculate fee with family discount for a person
	 *
	 * Calculates the base fee and applies family discount based on position
	 * within the family group. Most expensive youth member pays full fee,
	 * second gets 25% off, third+ get 50% off.
	 *
	 * Non-youth members (senior, recreant, donateur) are not eligible for
	 * family discount per FAM-05 requirement.
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return array|null Fee data with family discount info, or null if not calculable.
	 */
	public function calculate_fee_with_family_discount( int $person_id, ?string $season = null ): ?array {
		// Resolve season for consistent usage
		$season = $season ?: $this->get_season_key();

		// Get base fee using calculate_fee with season
		$fee_data = $this->calculate_fee( $person_id, $season );

		if ( $fee_data === null ) {
			return null;
		}

		// Youth categories eligible for family discount
		$youth_categories = [ 'mini', 'pupil', 'junior' ];

		// Non-youth: no family discount eligible
		if ( ! in_array( $fee_data['category'], $youth_categories, true ) ) {
			return array_merge(
				$fee_data,
				[
					'family_discount_rate'   => 0.0,
					'family_discount_amount' => 0,
					'final_fee'              => $fee_data['base_fee'],
					'family_position'        => null,
					'family_key'             => null,
					'family_size'            => null,
				]
			);
		}

		// Get family key for this person
		$family_key = $this->get_family_key( $person_id );

		// No valid address: no discount possible
		if ( $family_key === null ) {
			return array_merge(
				$fee_data,
				[
					'family_discount_rate'   => 0.0,
					'family_discount_amount' => 0,
					'final_fee'              => $fee_data['base_fee'],
					'family_position'        => null,
					'family_key'             => null,
					'family_size'            => 1,
				]
			);
		}

		// Build family groups
		$groups         = $this->build_family_groups( $season );
		$families       = $groups['families'];
		$person_data    = $groups['person_data'];
		$family_members = $families[ $family_key ] ?? [];

		// Family has only 1 member: no discount
		if ( count( $family_members ) <= 1 ) {
			return array_merge(
				$fee_data,
				[
					'family_discount_rate'   => 0.0,
					'family_discount_amount' => 0,
					'final_fee'              => $fee_data['base_fee'],
					'family_position'        => 1,
					'family_key'             => $family_key,
					'family_size'            => count( $family_members ),
					'family_members'         => [],
				]
			);
		}

		// Build sorted list by base_fee descending (most expensive first)
		$sorted = [];
		foreach ( $family_members as $member_id ) {
			$sorted[] = [
				'person_id' => $member_id,
				'base_fee'  => $person_data[ $member_id ]['base_fee'],
			];
		}

		usort(
			$sorted,
			function ( $a, $b ) {
				// Sort descending by base_fee
				$cmp = $b['base_fee'] <=> $a['base_fee'];
				if ( $cmp !== 0 ) {
					return $cmp;
				}
				// Tie-breaker: lower person_id first (older record = full fee)
				return $a['person_id'] <=> $b['person_id'];
			}
		);

		// Find position of current person (1-indexed)
		$position = 1;
		foreach ( $sorted as $index => $member ) {
			if ( $member['person_id'] === $person_id ) {
				$position = $index + 1;
				break;
			}
		}

		// Calculate discount
		$discount_rate   = $this->get_family_discount_rate( $position );
		$discount_amount = round( $fee_data['base_fee'] * $discount_rate, 2 );
		$final_fee       = $fee_data['base_fee'] - $discount_amount;

		// Build family members array with names (excluding current person)
		$siblings = [];
		foreach ( $sorted as $member ) {
			if ( $member['person_id'] !== $person_id ) {
				$first_name = get_field( 'first_name', $member['person_id'] ) ?: '';
				$last_name  = get_field( 'last_name', $member['person_id'] ) ?: '';
				$name       = trim( $first_name . ' ' . $last_name );
				if ( empty( $name ) ) {
					$name = get_the_title( $member['person_id'] );
				}
				$siblings[] = [
					'id'   => $member['person_id'],
					'name' => $name,
				];
			}
		}

		return array_merge(
			$fee_data,
			[
				'family_discount_rate'   => $discount_rate,
				'family_discount_amount' => $discount_amount,
				'final_fee'              => $final_fee,
				'family_position'        => $position,
				'family_key'             => $family_key,
				'family_size'            => count( $family_members ),
				'family_members'         => $siblings,
			]
		);
	}

	/**
	 * Get calculation status for a person
	 *
	 * Returns diagnostic information about why a person might be excluded from
	 * fee calculation. Useful for admin UI and debugging.
	 *
	 * @param int $person_id The person post ID.
	 * @return array{
	 *     has_leeftijdsgroep: bool,
	 *     leeftijdsgroep_value: string|null,
	 *     parsed_category: string|null,
	 *     has_teams: bool,
	 *     team_count: int,
	 *     is_donateur: bool,
	 *     calculable: bool,
	 *     reason: string
	 * } Diagnostic information array.
	 */
	public function get_calculation_status( int $person_id ): array {
		$leeftijdsgroep = get_field( 'leeftijdsgroep', $person_id );
		$parsed         = ! empty( $leeftijdsgroep ) ? $this->parse_age_group( $leeftijdsgroep ) : null;
		$teams          = $this->get_current_teams( $person_id );
		$is_donateur    = $this->is_donateur( $person_id );
		$fee_result     = $this->calculate_fee( $person_id );

		// Determine reason if not calculable
		$reason = 'calculable';

		if ( $fee_result === null ) {
			if ( ! empty( $leeftijdsgroep ) && $parsed === null ) {
				$reason = 'unknown_age_group';
			} elseif ( ! empty( $teams ) && empty( $leeftijdsgroep ) ) {
				$reason = 'has_team_but_no_age_group';
			} elseif ( ! empty( $teams ) && $parsed === null ) {
				$reason = 'has_team_but_no_age_group';
			} else {
				$reason = 'no_age_group_no_team_not_donateur';
			}
		}

		return [
			'has_leeftijdsgroep'  => ! empty( $leeftijdsgroep ),
			'leeftijdsgroep_value' => $leeftijdsgroep ?: null,
			'parsed_category'     => $parsed,
			'has_teams'           => ! empty( $teams ),
			'team_count'          => count( $teams ),
			'is_donateur'         => $is_donateur,
			'calculable'          => $fee_result !== null,
			'reason'              => $reason,
		];
	}
}
