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
}
