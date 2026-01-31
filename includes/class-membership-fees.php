<?php
/**
 * Membership Fees Service
 *
 * Handles membership fee settings storage and retrieval using the WordPress Options API.
 *
 * @package Stadion\Fees
 */

namespace Stadion\Fees;

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
	 * Get all fee settings
	 *
	 * @return array<string, int> Array of fee type => amount pairs
	 */
	public function get_all_settings(): array {
		$stored = get_option( self::OPTION_KEY, [] );

		// Merge with defaults to ensure all keys exist
		$settings = array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : [] );

		// Ensure all values are integers
		return array_map( 'intval', $settings );
	}

	/**
	 * Get a single fee amount by type
	 *
	 * @param string $type The fee type (mini, pupil, junior, senior, recreant, donateur).
	 * @return int The fee amount in euros
	 */
	public function get_fee( string $type ): int {
		$settings = $this->get_all_settings();

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
		// Get current settings
		$current = $this->get_all_settings();

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

		return update_option( self::OPTION_KEY, $current );
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
	 * Recreational teams have "recreant" or "walking football" in their name.
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

		return ( stripos( $title, 'recreant' ) !== false || stripos( $title, 'walking football' ) !== false );
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
	 * @param int $person_id The person post ID.
	 * @return array{category: string, base_fee: int, leeftijdsgroep: string|null, person_id: int}|null
	 *         Fee data array or null if person cannot be calculated.
	 */
	public function calculate_fee( int $person_id ): ?array {
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
				'base_fee'       => $this->get_fee( $category ),
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
						'base_fee'       => $this->get_fee( 'donateur' ),
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
				'base_fee'       => $this->get_fee( $fee_category ),
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
				'base_fee'       => $this->get_fee( 'donateur' ),
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
