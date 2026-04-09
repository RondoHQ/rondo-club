<?php
/**
 * Fee Category Resolver
 *
 * Resolves a person's fee category using the season configuration. Owns the
 * eight methods that used to live in MembershipFees and dealt exclusively
 * with "given X, which category applies?" — age-class parsing, team matching,
 * werkfunctie matching, donateur/recreational detection, category lookup,
 * and next-season age-class prediction.
 *
 * Extracted from {@see MembershipFees} in Phase 214 of the v33.0 Fee Service
 * Decomposition milestone. The resolver is stateless with respect to person
 * data — callers pass in werkfuncties / team ids / age classes — and takes
 * the season settings via a `callable $categories_provider` so we don't hard
 * couple to the MembershipFees god class. Phase 217 will swap the provider
 * for `MembershipFeeSettings::get_categories_for_season()`.
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fee category resolver service.
 */
class FeeCategoryResolver {

	/**
	 * Provider that returns the slug-keyed category array for a given season.
	 *
	 * Signature: (string $season) : array
	 *
	 * In Phase 214 this is wired to MembershipFees::get_categories_for_season().
	 * In Phase 217 it will be swapped for MembershipFeeSettings equivalent.
	 *
	 * @var callable
	 */
	private $categories_provider;

	/**
	 * Constructor.
	 *
	 * @param callable $categories_provider Callable returning categories for a season key.
	 */
	public function __construct( callable $categories_provider ) {
		$this->categories_provider = $categories_provider;
	}

	/**
	 * Fetch the slug-keyed category array for a season via the provider.
	 *
	 * @param string $season Season key in "YYYY-YYYY" format.
	 * @return array Slug-keyed category configuration (may be empty).
	 */
	private function categories_for_season( string $season ): array {
		$categories = ( $this->categories_provider )( $season );

		return is_array( $categories ) ? $categories : [];
	}

	/**
	 * Predict next season's Sportlink age class from current age class.
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
	 * Find fee category by matching Sportlink age class against season config.
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
		$season     = $season ?? SeasonKey::current();
		$categories = $this->categories_for_season( $season );

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
		uasort(
			$categories,
			function ( $a, $b ) {
				return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
			}
		);

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
	 * Get a single fee category by slug for a specific season.
	 *
	 * @param string      $slug   The category slug (e.g., "senior", "junior").
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array|null Category object or null if not found.
	 */
	public function get_category( string $slug, ?string $season = null ): ?array {
		$season     = $season ?: SeasonKey::current();
		$categories = $this->categories_for_season( $season );

		return $categories[ $slug ] ?? null;
	}

	/**
	 * Get category by team matching.
	 *
	 * Finds the first category (by sort_order) whose matching_teams array contains
	 * any of the provided team IDs. Filters out deleted teams (non-publish status).
	 *
	 * @param array<int>  $team_ids Array of team post IDs to match against.
	 * @param string|null $season   Optional season key, defaults to current season.
	 * @return string|null Category slug or null if no match.
	 */
	public function get_category_by_team_match( array $team_ids, ?string $season = null ): ?string {
		if ( empty( $team_ids ) ) {
			return null;
		}

		$season     = $season ?? SeasonKey::current();
		$categories = $this->categories_for_season( $season );

		if ( empty( $categories ) ) {
			return null;
		}

		// Sort by sort_order (lowest first)
		uasort(
			$categories,
			function ( $a, $b ) {
				return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
			}
		);

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
	 * Get category by werkfunctie matching.
	 *
	 * Finds the first category (by sort_order) whose matching_werkfuncties array contains
	 * any of the provided werkfuncties. Uses case-insensitive comparison with trimming.
	 *
	 * @param array<string> $werkfuncties Array of werkfunctie strings to match against.
	 * @param string|null   $season       Optional season key, defaults to current season.
	 * @return string|null Category slug or null if no match.
	 */
	public function get_category_by_werkfunctie_match( array $werkfuncties, ?string $season = null ): ?string {
		if ( empty( $werkfuncties ) ) {
			return null;
		}

		$season     = $season ?? SeasonKey::current();
		$categories = $this->categories_for_season( $season );

		if ( empty( $categories ) ) {
			return null;
		}

		// Sort by sort_order (lowest first)
		uasort(
			$categories,
			function ( $a, $b ) {
				return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
			}
		);

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
	 * Check if a team is a recreational team.
	 *
	 * Recreational teams have "recreant" or "walking football" or "walking voetbal" in their name.
	 *
	 * @deprecated Used only by migration logic. Config-driven matching replaces this.
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
	 * Check if a person is a donateur (donor) only.
	 *
	 * Returns true only if the person has exactly one werkfunctie and it is "Donateur".
	 *
	 * Signature change in Phase 214: previously took `int $person_id` and reached
	 * into `MembershipFees::get_effective_werkfuncties()`. Now takes the werkfuncties
	 * array directly so the resolver stays stateless with respect to person data.
	 * Callers compute the effective werkfuncties first.
	 *
	 * @deprecated Used only by migration logic. Config-driven matching replaces this.
	 * @param array<string> $werkfuncties Effective werkfuncties for the person.
	 * @return bool True if the person is a donateur only.
	 */
	public function is_donateur( array $werkfuncties ): bool {
		if ( empty( $werkfuncties ) ) {
			return false;
		}

		// True only if exactly one function and it's "Donateur"
		return ( count( $werkfuncties ) === 1 && in_array( 'Donateur', $werkfuncties, true ) );
	}

	/**
	 * Find all recreational team IDs from the database.
	 *
	 * Used by migration logic to populate matching_teams for 'recreant' category.
	 * Queries all teams and filters using the deprecated is_recreational_team() method.
	 *
	 * @return array<int> Array of team post IDs that match recreational criteria.
	 */
	public function find_recreational_team_ids(): array {
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
}
