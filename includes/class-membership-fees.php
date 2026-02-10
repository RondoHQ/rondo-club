<?php
/**
 * Membership Fees Service
 *
 * Handles membership fee settings storage and retrieval using the WordPress Options API.
 *
 * @package Rondo\Fees
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
	const OPTION_KEY = 'rondo_membership_fees';


	/**
	 * Get the option key for a specific season
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return string Option key for season-specific fee storage.
	 */
	public function get_option_key_for_season( string $season ): string {
		return 'rondo_membership_fees_' . $season;
	}

	/**
	 * Get fee settings for a specific season
	 *
	 * Returns a flat array of fee type => amount pairs for backward compatibility
	 * with the REST API settings endpoint. Reads from the category configuration.
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return array<string, int> Array of fee type => amount pairs.
	 */
	public function get_settings_for_season( string $season ): array {
		$categories = $this->get_categories_for_season( $season );

		$settings = [];
		foreach ( $categories as $slug => $category ) {
			$settings[ $slug ] = (int) ( $category['amount'] ?? 0 );
		}

		return $settings;
	}

	/**
	 * Update fee amounts for a specific season
	 *
	 * Updates the amount field within category objects. Only modifies amounts
	 * for categories that exist in the season's configuration.
	 *
	 * @param array<string, mixed> $fees   Array of category slug => amount pairs to update.
	 * @param string               $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return bool True on success, false on failure.
	 */
	public function update_settings_for_season( array $fees, string $season ): bool {
		$categories  = $this->get_categories_for_season( $season );
		$valid_slugs = array_keys( $categories );

		foreach ( $fees as $type => $amount ) {
			// Skip categories not in this season's config
			if ( ! in_array( $type, $valid_slugs, true ) ) {
				continue;
			}

			// Validate: must be numeric and non-negative
			if ( ! is_numeric( $amount ) || $amount < 0 ) {
				continue;
			}

			$categories[ $type ]['amount'] = (int) $amount;
		}

		return $this->save_categories_for_season( $categories, $season );
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
	 * Get a single fee amount by category slug
	 *
	 * Reads the amount from the category configuration for the specified season.
	 *
	 * @param string      $type   The fee category slug (e.g., "senior", "pupil").
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return int The fee amount in euros, or 0 if category not found.
	 */
	public function get_fee( string $type, ?string $season = null ): int {
		$season   = $season ?? $this->get_season_key();
		$category = $this->get_category( $type, $season );

		if ( $category === null || ! isset( $category['amount'] ) ) {
			return 0;
		}

		return (int) $category['amount'];
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
	 * Predict next season's Sportlink age class from current age class
	 *
	 * KNVB age classes follow the pattern "Onder N" where N is based on birth year.
	 * Each season, every youth player moves up one year: "Onder 10" becomes "Onder 11".
	 * The highest youth class "Onder 19" promotes to "Senioren".
	 * Gender suffixes (" Meiden", " Vrouwen") are preserved.
	 *
	 * @param string $current_age_class Sportlink AgeClassDescription (e.g., "Onder 10", "Onder 15 Meiden").
	 * @return string Predicted age class for next season.
	 */
	public function predict_next_season_age_class( string $current_age_class ): string {
		// Extract gender suffix if present
		$suffix = '';
		if ( preg_match( '/(\s+(Meiden|Vrouwen))$/i', $current_age_class, $matches ) ) {
			$suffix = $matches[1];
		}

		// Match "Onder N" pattern
		if ( preg_match( '/^Onder\s+(\d+)/i', $current_age_class, $matches ) ) {
			$age = (int) $matches[1];

			// Onder 19 promotes to Senioren (with appropriate suffix)
			if ( $age >= 19 ) {
				return 'Senioren' . ( $suffix === ' Meiden' ? ' Vrouwen' : $suffix );
			}

			return 'Onder ' . ( $age + 1 ) . $suffix;
		}

		// Non-youth classes (Senioren, etc.) stay the same
		return $current_age_class;
	}

	/**
	 * Find fee category by matching Sportlink age class against season config
	 *
	 * Replaces the former parse_age_group() method. Instead of hardcoded age ranges,
	 * matches the member's Sportlink AgeClassDescription against the age_classes arrays
	 * stored in the season's category configuration.
	 *
	 * Normalizes input by stripping " Meiden" and " Vrouwen" suffixes (Sportlink
	 * appends these for girls' teams).
	 *
	 * If a class appears in multiple categories, the category with the lowest sort_order wins.
	 * A category with null/empty age_classes acts as a catch-all for unmatched classes.
	 *
	 * @param string      $leeftijdsgroep Sportlink AgeClassDescription (e.g., "Onder 10", "Senioren").
	 * @param string|null $season         Optional season key, defaults to current season.
	 * @return string|null Category slug or null if no match and no catch-all exists.
	 */
	public function get_category_by_age_class( string $leeftijdsgroep, ?string $season = null ): ?string {
		$season     = $season ?? $this->get_season_key();
		$categories = $this->get_categories_for_season( $season );

		// Empty config = no categories defined (silent per CONTEXT.md)
		if ( empty( $categories ) ) {
			return null;
		}

		// Normalize: strip " Meiden" and " Vrouwen" suffixes
		$normalized = preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', trim( $leeftijdsgroep ) );

		if ( empty( $normalized ) ) {
			return null;
		}

		// Sort by sort_order so lowest sort_order wins on overlap
		uasort( $categories, function ( $a, $b ) {
			return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
		} );

		$catch_all_slug = null;

		foreach ( $categories as $slug => $category ) {
			// Validate required field (fail loudly per CONTEXT.md)
			if ( ! isset( $category['amount'] ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "Rondo fee category '{$slug}' missing 'amount' for season {$season}" );
				return null;
			}

			$age_classes = $category['age_classes'] ?? null;

			// Null/empty age_classes = catch-all
			if ( $age_classes === null || ( is_array( $age_classes ) && empty( $age_classes ) ) ) {
				if ( $catch_all_slug === null ) {
					$catch_all_slug = $slug;
				}
				continue;
			}

			// Exact string match (case-insensitive)
			foreach ( (array) $age_classes as $age_class ) {
				if ( strcasecmp( $normalized, trim( $age_class ) ) === 0 ) {
					return $slug;
				}
			}
		}

		return $catch_all_slug;
	}

	/**
	 * Get valid category slugs for a season
	 *
	 * Replaces the former VALID_TYPES constant. Returns the category slugs
	 * defined in the season's configuration.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array<string> Array of category slugs.
	 */
	public function get_valid_category_slugs( ?string $season = null ): array {
		$season     = $season ?? $this->get_season_key();
		$categories = $this->get_categories_for_season( $season );

		return array_keys( $categories );
	}

	/**
	 * Get youth category slugs for a season
	 *
	 * Replaces all hardcoded youth_categories arrays. Returns category slugs
	 * where is_youth flag is true in the season's configuration.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array<string> Array of youth category slugs.
	 */
	public function get_youth_category_slugs( ?string $season = null ): array {
		$season     = $season ?? $this->get_season_key();
		$categories = $this->get_categories_for_season( $season );

		return array_keys(
			array_filter(
				$categories,
				function ( $cat ) {
					return ! empty( $cat['is_youth'] );
				}
			)
		);
	}

	/**
	 * Get category sort order map for a season
	 *
	 * Replaces all hardcoded category_order arrays. Returns a map of
	 * category slug to sort_order value from the season's configuration.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array<string, int> Map of category slug => sort_order.
	 */
	public function get_category_sort_order( ?string $season = null ): array {
		$season     = $season ?? $this->get_season_key();
		$categories = $this->get_categories_for_season( $season );

		$order = [];
		foreach ( $categories as $slug => $category ) {
			$order[ $slug ] = $category['sort_order'] ?? 999;
		}

		return $order;
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
	 * Check if a former member should be included in the season's fee list
	 *
	 * A former member qualifies if their lid-sinds date is BEFORE the end of the season
	 * (July 1 of the season's end year). This includes:
	 * - Members who joined before season start and left during it (normal fee, no pro-rata)
	 * - Members who joined mid-season and left during it (pro-rata based on lid-sinds)
	 *
	 * Former members whose lid-sinds is after the season end date are excluded
	 * (they never participated in that season).
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return bool True if former member qualifies for season, false otherwise.
	 */
	public function is_former_member_in_season( int $person_id, ?string $season = null ): bool {
		// Only applies to former members
		$is_former = ( get_field( 'former_member', $person_id ) == true );
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
		$season          = $season ?? $this->get_season_key();
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

	/**
	 * Check if a team is a recreational team
	 *
	 * Recreational teams have "recreant" or "walking football" or "walking voetbal" in their name.
	 *
	 * @deprecated Used only by migration logic. Config-driven matching replaces this.
	 * @param int $team_id The team post ID.
	 * @return bool True if the team is recreational.
	 */
	private function is_recreational_team( int $team_id ): bool {
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
	 * @deprecated Used only by migration logic. Config-driven matching replaces this.
	 * @param int $person_id The person post ID.
	 * @return bool True if the person is a donateur only.
	 */
	private function is_donateur( int $person_id ): bool {
		$werkfuncties = get_field( 'werkfuncties', $person_id ) ?: [];

		if ( empty( $werkfuncties ) ) {
			return false;
		}

		// True only if exactly one function and it's "Donateur"
		return ( count( $werkfuncties ) === 1 && in_array( 'Donateur', $werkfuncties, true ) );
	}

	/**
	 * Find all recreational team IDs from the database
	 *
	 * Used by migration logic to populate matching_teams for 'recreant' category.
	 * Queries all teams and filters using the deprecated is_recreational_team() method.
	 *
	 * @return array<int> Array of team post IDs that match recreational criteria.
	 */
	private function find_recreational_team_ids(): array {
		$query = new \WP_Query(
			[
				'post_type'      => 'team',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'no_found_rows'  => true,
			]
		);

		$recreational_ids = [];
		foreach ( $query->posts as $team_id ) {
			if ( $this->is_recreational_team( $team_id ) ) {
				$recreational_ids[] = $team_id;
			}
		}

		return $recreational_ids;
	}

	/**
	 * Get category by team matching
	 *
	 * Finds the first category (by sort_order) whose matching_teams array contains
	 * any of the provided team IDs. Filters out deleted teams (non-publish status).
	 *
	 * @param array<int>  $team_ids Array of team post IDs to match against.
	 * @param string|null $season   Optional season key, defaults to current season.
	 * @return string|null Category slug or null if no match.
	 */
	private function get_category_by_team_match( array $team_ids, ?string $season = null ): ?string {
		if ( empty( $team_ids ) ) {
			return null;
		}

		$season     = $season ?? $this->get_season_key();
		$categories = $this->get_categories_for_season( $season );

		if ( empty( $categories ) ) {
			return null;
		}

		// Sort by sort_order (lowest first)
		uasort( $categories, function ( $a, $b ) {
			return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
		} );

		foreach ( $categories as $slug => $category ) {
			$matching_teams = $category['matching_teams'] ?? [];

			if ( empty( $matching_teams ) || ! is_array( $matching_teams ) ) {
				continue;
			}

			// Check if any of person's teams are in this category's matching_teams
			foreach ( $team_ids as $team_id ) {
				// Filter out deleted teams
				if ( get_post_status( $team_id ) !== 'publish' ) {
					continue;
				}

				if ( in_array( $team_id, $matching_teams, true ) ) {
					return $slug;
				}
			}
		}

		return null;
	}

	/**
	 * Get category by werkfunctie matching
	 *
	 * Finds the first category (by sort_order) whose matching_werkfuncties array contains
	 * any of the provided werkfuncties. Uses case-insensitive comparison with trimming.
	 *
	 * @param array<string> $werkfuncties Array of werkfunctie strings to match against.
	 * @param string|null   $season       Optional season key, defaults to current season.
	 * @return string|null Category slug or null if no match.
	 */
	private function get_category_by_werkfunctie_match( array $werkfuncties, ?string $season = null ): ?string {
		if ( empty( $werkfuncties ) ) {
			return null;
		}

		$season     = $season ?? $this->get_season_key();
		$categories = $this->get_categories_for_season( $season );

		if ( empty( $categories ) ) {
			return null;
		}

		// Sort by sort_order (lowest first)
		uasort( $categories, function ( $a, $b ) {
			return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
		} );

		foreach ( $categories as $slug => $category ) {
			$matching_werkfuncties = $category['matching_werkfuncties'] ?? [];

			if ( empty( $matching_werkfuncties ) || ! is_array( $matching_werkfuncties ) ) {
				continue;
			}

			// Check if any of person's werkfuncties match (case-insensitive)
			foreach ( $werkfuncties as $person_wf ) {
				$person_wf_trimmed = trim( $person_wf );

				foreach ( $matching_werkfuncties as $category_wf ) {
					if ( strcasecmp( $person_wf_trimmed, trim( $category_wf ) ) === 0 ) {
						return $slug;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Calculate the fee for a person
	 *
	 * Determines the correct fee category and amount based on the person's
	 * age group, team membership, and work functions.
	 *
	 * Priority order: Youth > Team matching > Werkfunctie matching > Non-youth age class fallback
	 * - Youth categories: Matched by age class, return immediately (highest priority)
	 * - Team matching: Config-driven matching via matching_teams arrays
	 * - Werkfunctie matching: Config-driven matching via matching_werkfuncties arrays
	 * - Age class fallback: Non-youth age class match (e.g., senior) as last resort
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key for fee lookup, defaults to current season.
	 * @return array{category: string, base_fee: int, leeftijdsgroep: string|null, person_id: int}|null
	 *         Fee data array or null if person cannot be calculated.
	 */
	public function calculate_fee( int $person_id, ?string $season = null ): ?array {
		// Get leeftijdsgroep from person
		$leeftijdsgroep = get_field( 'leeftijdsgroep', $person_id );
		$age_class_category = null;

		// Parse age group if available
		if ( ! empty( $leeftijdsgroep ) ) {
			$age_class_category = $this->get_category_by_age_class( $leeftijdsgroep, $season );
		}

		// Youth categories: Return immediately (priority over everything)
		$youth_categories = $this->get_youth_category_slugs( $season );
		if ( $age_class_category && in_array( $age_class_category, $youth_categories, true ) ) {
			return [
				'category'       => $age_class_category,
				'base_fee'       => $this->get_fee( $age_class_category, $season ),
				'leeftijdsgroep' => $leeftijdsgroep,
				'person_id'      => $person_id,
			];
		}

		// Check team matching (config-driven)
		$teams = $this->get_current_teams( $person_id );
		if ( ! empty( $teams ) ) {
			$team_matched_category = $this->get_category_by_team_match( $teams, $season );
			if ( $team_matched_category !== null ) {
				return [
					'category'       => $team_matched_category,
					'base_fee'       => $this->get_fee( $team_matched_category, $season ),
					'leeftijdsgroep' => $leeftijdsgroep,
					'person_id'      => $person_id,
				];
			}
		}

		// Check werkfunctie matching (config-driven)
		$werkfuncties = get_field( 'werkfuncties', $person_id ) ?: [];
		if ( ! empty( $werkfuncties ) ) {
			$werkfunctie_matched_category = $this->get_category_by_werkfunctie_match( $werkfuncties, $season );
			if ( $werkfunctie_matched_category !== null ) {
				return [
					'category'       => $werkfunctie_matched_category,
					'base_fee'       => $this->get_fee( $werkfunctie_matched_category, $season ),
					'leeftijdsgroep' => $leeftijdsgroep,
					'person_id'      => $person_id,
				];
			}
		}

		// Fallback: Use non-youth age class match if we had one
		if ( $age_class_category !== null ) {
			return [
				'category'       => $age_class_category,
				'base_fee'       => $this->get_fee( $age_class_category, $season ),
				'leeftijdsgroep' => $leeftijdsgroep,
				'person_id'      => $person_id,
			];
		}

		// No valid category found - exclude
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
	 * Get the previous season key (one year behind current/specified season)
	 *
	 * Takes a season key in "YYYY-YYYY" format and returns the previous season.
	 * Example: "2025-2026" returns "2024-2025"
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return string|null Previous season key in "YYYY-YYYY" format, or null if format is invalid.
	 */
	public function get_previous_season_key( string $season ): ?string {
		// Validate format: "YYYY-YYYY"
		if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
			return null;
		}

		$season_start_year = (int) $matches[1];

		// Previous season is -1 year
		$prev_start_year = $season_start_year - 1;

		return $prev_start_year . '-' . $season_start_year;
	}

	/**
	 * Migrate category data from age_min/age_max format to age_classes format
	 *
	 * Phase 155 stored categories with age_min and age_max integer fields.
	 * Phase 156 replaces these with an age_classes array of Sportlink
	 * AgeClassDescription strings. This method detects the old format and
	 * converts it, removing the age_min and age_max fields.
	 *
	 * Categories that already have age_classes (or have neither format)
	 * are left unchanged.
	 *
	 * @param array $categories Slug-keyed array of category objects.
	 * @return array Migrated categories with age_classes arrays.
	 */
	private function maybe_migrate_age_classes( array $categories ): array {
		$needs_migration = false;

		foreach ( $categories as $slug => $category ) {
			// Detect old format: has age_min or age_max but no age_classes
			if ( ( isset( $category['age_min'] ) || isset( $category['age_max'] ) )
				 && ! isset( $category['age_classes'] ) ) {
				$needs_migration = true;

				// Set age_classes to empty array (catch-all) since we cannot
				// reverse-map age ranges to Sportlink age class strings.
				// Admin must populate the correct age_classes values manually.
				$categories[ $slug ]['age_classes'] = [];

				// Remove old fields
				unset( $categories[ $slug ]['age_min'] );
				unset( $categories[ $slug ]['age_max'] );
			}
		}

		return $categories;
	}

	/**
	 * Migrate category data to include matching_teams and matching_werkfuncties fields
	 *
	 * Phase 161 adds configurable team and werkfunctie matching rules to category objects.
	 * This method auto-populates defaults for existing categories:
	 * - 'recreant' category: matching_teams populated from recreational team IDs in database
	 * - 'donateur' category: matching_werkfuncties set to ['Donateur']
	 * - All other categories: empty arrays for both fields
	 *
	 * Only persists if migration actually changed anything.
	 *
	 * @param array $categories Slug-keyed array of category objects.
	 * @return array Migrated categories with matching rules.
	 */
	private function maybe_migrate_matching_rules( array $categories ): array {
		$needs_migration = false;

		foreach ( $categories as $slug => $category ) {
			// Add matching_teams if missing
			if ( ! isset( $category['matching_teams'] ) ) {
				$needs_migration = true;

				if ( $slug === 'recreant' ) {
					// Populate with current recreational team IDs
					$categories[ $slug ]['matching_teams'] = $this->find_recreational_team_ids();
				} else {
					$categories[ $slug ]['matching_teams'] = [];
				}
			}

			// Add matching_werkfuncties if missing
			if ( ! isset( $category['matching_werkfuncties'] ) ) {
				$needs_migration = true;

				if ( $slug === 'donateur' ) {
					// Populate with default donateur werkfunctie
					$categories[ $slug ]['matching_werkfuncties'] = [ 'Donateur' ];
				} else {
					$categories[ $slug ]['matching_werkfuncties'] = [];
				}
			}
		}

		return $categories;
	}

	/**
	 * Get fee categories for a specific season
	 *
	 * Returns the slug-keyed array of category objects for the specified season.
	 * Each category object contains: label, amount, age_classes, is_youth, sort_order.
	 *
	 * If the season option does not exist, attempts to copy from the previous season.
	 * If no previous season data exists, returns an empty array.
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return array Slug-keyed array of category objects, or empty array if no data.
	 */
	public function get_categories_for_season( string $season ): array {
		$season_key = $this->get_option_key_for_season( $season );
		$stored     = get_option( $season_key, false );

		// If season option exists and is an array, migrate if needed and return
		if ( $stored !== false && is_array( $stored ) ) {
			$migrated = $this->maybe_migrate_age_classes( $stored );
			$migrated = $this->maybe_migrate_matching_rules( $migrated );

			// If migration changed anything, persist the updated format
			if ( $migrated !== $stored ) {
				update_option( $season_key, $migrated );
			}

			return $migrated;
		}

		// Season option doesn't exist - return empty array
		// Manual copy is now handled via REST endpoint (POST /rondo/v1/membership-fees/copy-season)
		return [];
	}

	/**
	 * Save fee categories for a specific season
	 *
	 * Persists the slug-keyed array of category objects to the season-specific option.
	 * Each category object should contain: label, amount, age_classes, is_youth, sort_order.
	 *
	 * @param array  $categories Slug-keyed array of category objects.
	 * @param string $season     Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return bool True on success, false on failure.
	 */
	public function save_categories_for_season( array $categories, string $season ): bool {
		$season_key = $this->get_option_key_for_season( $season );
		return update_option( $season_key, $categories );
	}

	/**
	 * Get a single fee category by slug for a specific season
	 *
	 * @param string      $slug   The category slug (e.g., "senior", "junior").
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array|null Category object or null if not found.
	 */
	public function get_category( string $slug, ?string $season = null ): ?array {
		$season     = $season ?: $this->get_season_key();
		$categories = $this->get_categories_for_season( $season );

		return $categories[ $slug ] ?? null;
	}

	/**
	 * Get family discount configuration for a season
	 *
	 * Returns discount percentages stored in a separate WordPress option.
	 * Implements copy-forward: if no config exists for the requested season,
	 * copies from the previous season. Falls back to default values (25% for
	 * 2nd child, 50% for 3rd+) only if no previous season config exists either.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array Array with 'second_child_percent' and 'third_child_percent' keys.
	 */
	public function get_family_discount_config( ?string $season = null ): array {
		$season   = $season ?: $this->get_season_key();
		$defaults = [
			'second_child_percent' => 25,
			'third_child_percent'  => 50,
		];

		$config = get_option( 'rondo_family_discount_' . $season, false );

		if ( $config !== false && is_array( $config ) ) {
			return [
				'second_child_percent' => $config['second_child_percent'] ?? $defaults['second_child_percent'],
				'third_child_percent'  => $config['third_child_percent'] ?? $defaults['third_child_percent'],
			];
		}

		// Season option doesn't exist - return defaults
		// Manual copy is now handled via REST endpoint (POST /rondo/v1/membership-fees/copy-season)
		return $defaults;
	}

	/**
	 * Save family discount configuration for a season
	 *
	 * @param array  $config Array with 'second_child_percent' and 'third_child_percent' keys.
	 * @param string $season Season key in "YYYY-YYYY" format.
	 * @return bool True on success, false on failure.
	 */
	public function save_family_discount_config( array $config, string $season ): bool {
		return update_option( 'rondo_family_discount_' . $season, $config );
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
		$result = $this->calculate_fee( $person_id, $season );

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
		return 'rondo_fee_cache_' . ( $season ?: $this->get_season_key() );
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

		// Add former member flag for diagnostics
		$is_former               = ( get_field( 'former_member', $person_id ) == true );
		$result['is_former_member'] = $is_former;

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
		$youth_categories = $this->get_youth_category_slugs( $season );

		foreach ( $query->posts as $person_id ) {
			// Skip former members not eligible for this season's fee list
			$is_former = ( get_field( 'former_member', $person_id ) == true );
			if ( $is_former && ! $this->is_former_member_in_season( $person_id, $season ) ) {
				continue; // Skip former members not in this season
			}

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
	 * who pays full fee. Discount percentages are read from season config, with
	 * fallback to default values (25% for 2nd child, 50% for 3rd+).
	 *
	 * @param int         $position 1-indexed position in family (1=most expensive, pays full).
	 * @param string|null $season   Optional season key, defaults to current season.
	 * @return float Discount rate (0.0 to 1.0).
	 */
	public function get_family_discount_rate( int $position, ?string $season = null ): float {
		if ( $position <= 1 ) {
			return 0.0;  // First member always pays full fee
		}

		$config = $this->get_family_discount_config( $season );

		if ( $position === 2 ) {
			return $config['second_child_percent'] / 100.0;
		}

		return $config['third_child_percent'] / 100.0;
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

		// Add former member flag for diagnostics
		$is_former = ( get_field( 'former_member', $person_id ) == true );

		// Add pro-rata fields to result
		return array_merge(
			$fee_data,
			[
				'registration_date'   => $registration_date,
				'prorata_percentage'  => $prorata_percentage,
				'fee_after_discount'  => $fee_after_discount,
				'final_fee'           => $prorata_amount,  // Override final_fee with pro-rata amount
				'is_former_member'    => $is_former,
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
		$youth_categories = $this->get_youth_category_slugs( $season );

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
		$discount_rate   = $this->get_family_discount_rate( $position, $season );
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
		$parsed         = ! empty( $leeftijdsgroep ) ? $this->get_category_by_age_class( $leeftijdsgroep ) : null;
		$teams          = $this->get_current_teams( $person_id );
		$is_donateur    = $this->is_donateur( $person_id );
		$fee_result     = $this->calculate_fee( $person_id );

		// Check former member status
		$is_former               = ( get_field( 'former_member', $person_id ) == true );
		$former_member_in_season = $is_former ? $this->is_former_member_in_season( $person_id ) : false;

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

		// Override reason if former member not in season
		if ( $is_former && ! $former_member_in_season ) {
			$reason = 'former_member_not_in_season';
		}

		return [
			'has_leeftijdsgroep'      => ! empty( $leeftijdsgroep ),
			'leeftijdsgroep_value'    => $leeftijdsgroep ?: null,
			'parsed_category'         => $parsed,
			'has_teams'               => ! empty( $teams ),
			'team_count'              => count( $teams ),
			'is_donateur'             => $is_donateur,
			'is_former_member'        => $is_former,
			'former_member_in_season' => $former_member_in_season,
			'calculable'              => $fee_result !== null,
			'reason'                  => $reason,
		];
	}
}
