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
	 * Lazily instantiated category resolver.
	 *
	 * Extracted from this class in Phase 214 of the v33.0 Fee Service
	 * Decomposition milestone. Accessed via {@see self::category_resolver()}
	 * to avoid circular construction (the resolver calls back into
	 * $this->get_categories_for_season through its provider).
	 *
	 * @var FeeCategoryResolver|null
	 */
	private ?FeeCategoryResolver $category_resolver = null;

	/**
	 * Lazily instantiated family grouping service.
	 *
	 * Extracted from this class in Phase 215 of the v33.0 Fee Service
	 * Decomposition milestone. Accessed via {@see self::family_grouping()}
	 * to avoid circular construction — FamilyGroupingService holds a
	 * closure back at $this->fee_calculator() for base-fee calculation,
	 * and Phase 217 will remove the remaining settings helpers.
	 *
	 * @var FamilyGroupingService|null
	 */
	private ?FamilyGroupingService $family_grouping = null;

	/**
	 * Lazily instantiated fee calculator.
	 *
	 * Extracted from this class in Phase 216 of the v33.0 Fee Service
	 * Decomposition milestone. Accessed via {@see self::fee_calculator()}.
	 * FeeCalculator takes category_resolver, family_grouping and
	 * MembershipFees as explicit collaborators. The family_grouping
	 * constructor closure points back at $this->fee_calculator() so the
	 * two services can call each other without either holding a typed
	 * reference to the other.
	 *
	 * @var FeeCalculator|null
	 */
	private ?FeeCalculator $fee_calculator = null;

	/**
	 * Get the lazy-initialized category resolver.
	 *
	 * The resolver reads season categories via a callable provider pointing
	 * back at $this->get_categories_for_season(). External callers (e.g. the
	 * REST fees controller) use this accessor to reach the resolver without
	 * needing to know how it is wired.
	 *
	 * In Phase 217 the provider will swap to MembershipFeeSettings and this
	 * accessor becomes a thin pass-through (or is replaced outright by
	 * direct DI of FeeCategoryResolver at the call sites).
	 *
	 * @return FeeCategoryResolver
	 */
	public function category_resolver(): FeeCategoryResolver {
		if ( $this->category_resolver === null ) {
			$this->category_resolver = new FeeCategoryResolver(
				fn( string $season ): array => $this->get_categories_for_season( $season )
			);
		}

		return $this->category_resolver;
	}

	/**
	 * Get the lazy-initialized family grouping service.
	 *
	 * External callers (REST fees controller, FeeCacheInvalidator) obtain
	 * their FamilyGroupingService reference through this accessor so they
	 * do not have to know about the wiring. Phase 217 will replace the
	 * remaining MembershipFees helpers with MembershipFeeSettings.
	 *
	 * The fee_calculator closure is deferred: the actual call to
	 * $this->fee_calculator() only fires when FamilyGroupingService
	 * invokes the closure at runtime, so there is no circular
	 * construction.
	 *
	 * @return FamilyGroupingService
	 */
	public function family_grouping(): FamilyGroupingService {
		if ( $this->family_grouping === null ) {
			$this->family_grouping = new FamilyGroupingService(
				$this,
				fn( int $person_id, ?string $season ) => $this->fee_calculator()->calculate_fee( $person_id, $season )
			);
		}

		return $this->family_grouping;
	}

	/**
	 * Get the lazy-initialized fee calculator.
	 *
	 * FeeCalculator is wired with FeeCategoryResolver (Phase 214),
	 * FamilyGroupingService (Phase 215) and a MembershipFees reference
	 * (this class, for helpers still living here). External callers use
	 * this accessor to reach the calculator without having to construct
	 * it themselves.
	 *
	 * Note: family_grouping() must be reachable before fee_calculator()
	 * to satisfy the typed constructor signature, but because the
	 * FamilyGroupingService uses a deferred closure for calculate_fee,
	 * there is no recursion at construction time.
	 *
	 * @return FeeCalculator
	 */
	public function fee_calculator(): FeeCalculator {
		if ( $this->fee_calculator === null ) {
			$this->fee_calculator = new FeeCalculator(
				$this->category_resolver(),
				$this->family_grouping(),
				$this
			);
		}

		return $this->fee_calculator;
	}


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
		return $this->get_settings_for_season( SeasonKey::current() );
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
		$season   = $season ?? SeasonKey::current();
		$category = $this->category_resolver()->get_category( $type, $season );

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
		return $this->update_settings_for_season( $fees, SeasonKey::current() );
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
		$season     = $season ?? SeasonKey::current();
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
		$season     = $season ?? SeasonKey::current();
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
		$season     = $season ?? SeasonKey::current();
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
	 * For fee calculation purposes, only includes teams where the person has a
	 * player role (Teamspeler, positional roles, recreational player). Staff roles
	 * like Trainer, Teammanager, etc. are excluded to prevent non-players from
	 * being assigned team-based fee categories.
	 *
	 * @param int $person_id The person post ID.
	 * @return array<int> Array of unique team IDs.
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
	 * Uses current work_history job titles as the source of truth for fee matching.
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
	 * "Donateur" is treated as a donateur-only signal: when combined with any
	 * other function, it is removed from matching so active roles can determine
	 * the fee category.
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

	/**
	 * Get the billing method for a season.
	 *
	 * Determines whether membership fees for the given season are billed
	 * through the external Nikki system or through Rondo's own invoicing.
	 *
	 * @param string|null $season Season key (e.g., "2025-2026"). Defaults to current season.
	 * @return string 'nikki' or 'rondo'. Defaults to 'nikki' if not set.
	 */
	public function get_billing_method( ?string $season = null ): string {
		$season = $season ?? SeasonKey::current();
		return get_option( 'rondo_billing_method_' . $season, 'nikki' );
	}

	/**
	 * Set the billing method for a season.
	 *
	 * @param string      $method 'nikki' or 'rondo'.
	 * @param string|null $season Season key. Defaults to current season.
	 * @return bool True on success, false on invalid method.
	 */
	public function set_billing_method( string $method, ?string $season = null ): bool {
		$season = $season ?? SeasonKey::current();
		if ( ! in_array( $method, [ 'nikki', 'rondo' ], true ) ) {
			return false;
		}
		return update_option( 'rondo_billing_method_' . $season, $method );
	}

	/**
	 * Get whether installment plan 3 (quarterly_3) is enabled for a season.
	 *
	 * @param string|null $season Optional season key. Defaults to current season.
	 * @return bool True if plan is enabled (default: true).
	 */
	public function get_installment_plan_3_enabled( ?string $season = null ): bool {
		$season = $season ?? SeasonKey::current();
		return (bool) get_option( 'rondo_installment_plan_3_enabled_' . $season, true );
	}

	/**
	 * Set whether installment plan 3 (quarterly_3) is enabled for a season.
	 *
	 * @param bool        $enabled Whether to enable the plan.
	 * @param string|null $season  Optional season key. Defaults to current season.
	 * @return bool True on success, false on failure.
	 */
	public function set_installment_plan_3_enabled( bool $enabled, ?string $season = null ): bool {
		$season = $season ?? SeasonKey::current();
		return update_option( 'rondo_installment_plan_3_enabled_' . $season, $enabled );
	}

	/**
	 * Get whether installment plan 8 (monthly_8) is enabled for a season.
	 *
	 * @param string|null $season Optional season key. Defaults to current season.
	 * @return bool True if plan is enabled (default: true).
	 */
	public function get_installment_plan_8_enabled( ?string $season = null ): bool {
		$season = $season ?? SeasonKey::current();
		return (bool) get_option( 'rondo_installment_plan_8_enabled_' . $season, true );
	}

	/**
	 * Set whether installment plan 8 (monthly_8) is enabled for a season.
	 *
	 * @param bool        $enabled Whether to enable the plan.
	 * @param string|null $season  Optional season key. Defaults to current season.
	 * @return bool True on success, false on failure.
	 */
	public function set_installment_plan_8_enabled( bool $enabled, ?string $season = null ): bool {
		$season = $season ?? SeasonKey::current();
		return update_option( 'rondo_installment_plan_8_enabled_' . $season, $enabled );
	}

	/**
	 * Get per-installment administration fee for a season.
	 *
	 * @param string|null $season Optional season key. Defaults to current season.
	 * @return float Administration fee per installment (default: legacy global value, else 0.00).
	 */
	public function get_installment_admin_fee( ?string $season = null ): float {
		$season         = $season ?? SeasonKey::current();
		$legacy_default = (float) get_option( 'rondo_finance_installment_admin_fee', 0 );
		return (float) get_option( 'rondo_installment_admin_fee_' . $season, $legacy_default );
	}

	/**
	 * Set per-installment administration fee for a season.
	 *
	 * @param float       $fee    Administration fee per installment.
	 * @param string|null $season Optional season key. Defaults to current season.
	 * @return bool True on success, false on failure.
	 */
	public function set_installment_admin_fee( float $fee, ?string $season = null ): bool {
		$season = $season ?? SeasonKey::current();
		return update_option( 'rondo_installment_admin_fee_' . $season, max( 0.0, $fee ) );
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
					$categories[ $slug ]['matching_teams'] = $this->category_resolver()->find_recreational_team_ids();
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
		$season   = $season ?: SeasonKey::current();
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
	 * Get entry discount (instapkorting) configuration for a season
	 *
	 * Returns configurable periods that define how much pro-rata discount applies
	 * based on when a member joins. Stored in a separate WordPress option per season.
	 * Falls back to default quarterly periods (matching previous hardcoded behavior)
	 * if no config exists for the requested season.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array Array with 'periods' key containing array of period configs.
	 */
	public function get_entry_discount_config( ?string $season = null ): array {
		$season   = $season ?: SeasonKey::current();
		$defaults = [
			'periods' => [
				[
					'start_month'      => 7,
					'end_month'        => 9,
					'discount_percent' => 0,
				],
				[
					'start_month'      => 10,
					'end_month'        => 12,
					'discount_percent' => 25,
				],
				[
					'start_month'      => 1,
					'end_month'        => 3,
					'discount_percent' => 50,
				],
				[
					'start_month'      => 4,
					'end_month'        => 6,
					'discount_percent' => 75,
				],
			],
		];

		$config = get_option( 'rondo_entry_discount_' . $season, false );

		if ( $config !== false && is_array( $config ) && isset( $config['periods'] ) ) {
			return $config;
		}

		// Season option doesn't exist - return defaults (matches previous hardcoded quarterly behavior)
		return $defaults;
	}

	/**
	 * Save entry discount (instapkorting) configuration for a season
	 *
	 * @param array  $config Array with 'periods' key containing period configs.
	 * @param string $season Season key in "YYYY-YYYY" format.
	 * @return bool True on success, false on failure.
	 */
	public function save_entry_discount_config( array $config, string $season ): bool {
		return update_option( 'rondo_entry_discount_' . $season, $config );
	}

	/**
	 * Get the post meta key for storing fee snapshots
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return string Meta key for fee snapshot storage.
	 */
	public function get_snapshot_meta_key( ?string $season = null ): string {
		return 'fee_snapshot_' . ( $season ?: SeasonKey::current() );
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
		$season            = $options['season'] ?? SeasonKey::current();
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
		$result = $this->fee_calculator()->calculate_fee( $person_id, $season );

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
					++$deleted;
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
		return 'rondo_fee_cache_' . ( $season ?: SeasonKey::current() );
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
		$fee_data['season']        = $season ?: SeasonKey::current();

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
		$season   = $season ?: SeasonKey::current();
		$meta_key = $this->get_fee_cache_meta_key( $season );

		// Try cache first
		$cached = get_post_meta( $person_id, $meta_key, true );

		if ( ! empty( $cached ) && is_array( $cached ) ) {
			$cached['from_cache'] = true;
			return $cached;
		}

		// Cache miss - calculate fresh using lid-sinds (PRO-04 fix)
		$lid_sinds = get_field( 'lid-sinds', $person_id );
		$result    = $this->fee_calculator()->calculate_full_fee( $person_id, $lid_sinds, $season );

		if ( $result === null ) {
			return null;
		}

		// Add former member flag for diagnostics
		$is_former                  = (bool) get_field( 'former_member', $person_id );
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
					++$cleared;
				}
			}
		}

		return $cleared;
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
		$category_resolver = $this->category_resolver();

		$leeftijdsgroep = get_field( 'leeftijdsgroep', $person_id );
		$parsed         = ! empty( $leeftijdsgroep ) ? $category_resolver->get_category_by_age_class( $leeftijdsgroep ) : null;
		$teams          = $this->get_current_teams( $person_id );
		$is_donateur    = $category_resolver->is_donateur( $this->get_effective_werkfuncties( $person_id ) );
		$fee_result     = $this->fee_calculator()->calculate_fee( $person_id );

		// Check former member status
		$is_former               = (bool) get_field( 'former_member', $person_id );
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
