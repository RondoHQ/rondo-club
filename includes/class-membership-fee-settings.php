<?php
/**
 * Membership Fee Settings
 *
 * Owns all WordPress Options API storage for the Rondo Club membership fee
 * system: category definitions per season, billing method per season,
 * installment plan toggles, installment admin fee, family-discount config,
 * entry-discount (pro-rata periods) config, plus the legacy data migrations
 * that keep older fee option payloads shaped correctly.
 *
 * Extracted from {@see MembershipFees} in Phase 217 of the v33.0 Fee Service
 * Decomposition milestone. This class has zero constructor dependencies —
 * it is pure storage, so any other service can instantiate it freely.
 *
 * WordPress option-key contract (do not change without a migration):
 * - `rondo_membership_fees_{season}`     — slug-keyed categories array
 * - `rondo_family_discount_{season}`     — family discount percentages
 * - `rondo_entry_discount_{season}`      — pro-rata period config
 * - `rondo_billing_method_{season}`      — 'nikki' | 'rondo'
 * - `rondo_installment_plan_3_enabled_{season}` — bool
 * - `rondo_installment_plan_8_enabled_{season}` — bool
 * - `rondo_installment_admin_fee_{season}`      — float (with legacy fallback)
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Membership fee settings repository.
 */
class MembershipFeeSettings {

	// -------------------------------------------------------------------
	// Category storage (per season)
	// -------------------------------------------------------------------

	/**
	 * Get the option key for a specific season.
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return string Option key for season-specific fee storage.
	 */
	public function get_option_key_for_season( string $season ): string {
		return 'rondo_membership_fees_' . $season;
	}

	/**
	 * Get fee categories for a specific season.
	 *
	 * Returns the slug-keyed array of category objects for the specified season.
	 * Each category object contains: label, amount, age_classes, is_youth, sort_order.
	 *
	 * Runs idempotent migrations on read if the stored format is older
	 * (missing `age_classes`, or missing `matching_teams`/`matching_werkfuncties`).
	 * Persists the migrated payload only if something actually changed.
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
	 * Save fee categories for a specific season.
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
	 * Get fee settings for a specific season.
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
	 * Update fee amounts for a specific season.
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
	 * Get all fee settings (current season).
	 *
	 * @return array<string, int> Array of fee type => amount pairs
	 */
	public function get_all_settings(): array {
		// Use current season settings for backward compatibility
		return $this->get_settings_for_season( SeasonKey::current() );
	}

	/**
	 * Update fee settings (current season).
	 *
	 * @param array<string, mixed> $fees Array of fee type => amount pairs to update.
	 * @return bool True on success, false on failure
	 */
	public function update_settings( array $fees ): bool {
		// Use current season for backward compatibility
		return $this->update_settings_for_season( $fees, SeasonKey::current() );
	}

	/**
	 * Get a single fee amount by category slug.
	 *
	 * @param string      $type   The fee category slug (e.g., "senior", "pupil").
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return int The fee amount in euros, or 0 if category not found.
	 */
	public function get_fee( string $type, ?string $season = null ): int {
		$season     = $season ?? SeasonKey::current();
		$categories = $this->get_categories_for_season( $season );

		if ( ! isset( $categories[ $type ] ) || ! isset( $categories[ $type ]['amount'] ) ) {
			return 0;
		}

		return (int) $categories[ $type ]['amount'];
	}

	/**
	 * Get valid category slugs for a season.
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
	 * Get youth category slugs for a season.
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
	 * Get category sort order map for a season.
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

	// -------------------------------------------------------------------
	// Billing method & installment settings (per season)
	// -------------------------------------------------------------------

	/**
	 * Get the billing method for a season.
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

	// -------------------------------------------------------------------
	// Family & entry discount config (per season)
	// -------------------------------------------------------------------

	/**
	 * Get family discount configuration for a season.
	 *
	 * Returns discount percentages stored in a separate WordPress option.
	 * Falls back to default values (25% for 2nd child, 50% for 3rd+) if no
	 * config exists for the requested season.
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
	 * Save family discount configuration for a season.
	 *
	 * @param array  $config Array with 'second_child_percent' and 'third_child_percent' keys.
	 * @param string $season Season key in "YYYY-YYYY" format.
	 * @return bool True on success, false on failure.
	 */
	public function save_family_discount_config( array $config, string $season ): bool {
		return update_option( 'rondo_family_discount_' . $season, $config );
	}

	/**
	 * Get discount rate based on family position.
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
	 * Get entry discount (instapkorting) configuration for a season.
	 *
	 * Returns configurable periods that define how much pro-rata discount applies
	 * based on when a member joins. Falls back to default quarterly periods if no
	 * config exists for the requested season.
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
	 * Save entry discount (instapkorting) configuration for a season.
	 *
	 * @param array  $config Array with 'periods' key containing period configs.
	 * @param string $season Season key in "YYYY-YYYY" format.
	 * @return bool True on success, false on failure.
	 */
	public function save_entry_discount_config( array $config, string $season ): bool {
		return update_option( 'rondo_entry_discount_' . $season, $config );
	}

	// -------------------------------------------------------------------
	// Legacy payload migrations (run idempotently on read)
	// -------------------------------------------------------------------

	/**
	 * Migrate category data from age_min/age_max format to age_classes format.
	 *
	 * Phase 155 stored categories with age_min and age_max integer fields.
	 * Phase 156 replaces these with an age_classes array of Sportlink
	 * AgeClassDescription strings. Detects the old format and converts it,
	 * removing the age_min and age_max fields.
	 *
	 * @param array $categories Slug-keyed array of category objects.
	 * @return array Migrated categories with age_classes arrays.
	 */
	private function maybe_migrate_age_classes( array $categories ): array {
		foreach ( $categories as $slug => $category ) {
			// Detect old format: has age_min or age_max but no age_classes
			if ( ( isset( $category['age_min'] ) || isset( $category['age_max'] ) )
				&& ! isset( $category['age_classes'] ) ) {

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
	 * Migrate category data to include matching_teams and matching_werkfuncties fields.
	 *
	 * Phase 161 adds configurable team and werkfunctie matching rules to category
	 * objects. Auto-populates defaults for legacy payloads:
	 * - 'recreant' category: matching_teams populated from recreational team IDs in database
	 * - 'donateur' category: matching_werkfuncties set to ['Donateur']
	 * - All other categories: empty arrays for both fields
	 *
	 * The team-name lookup used to be delegated to FeeCategoryResolver, but we
	 * inline a minimal WP_Query here so MembershipFeeSettings has zero service
	 * dependencies. This keeps the repository layer free of the resolver <->
	 * settings circular coupling Phase 217 otherwise runs into. The migration
	 * runs at most once per season (until matching_teams/werkfuncties are
	 * populated), so duplicating 15 lines is acceptable.
	 *
	 * @param array $categories Slug-keyed array of category objects.
	 * @return array Migrated categories with matching rules.
	 */
	private function maybe_migrate_matching_rules( array $categories ): array {
		foreach ( $categories as $slug => $category ) {
			// Add matching_teams if missing
			if ( ! isset( $category['matching_teams'] ) ) {
				if ( $slug === 'recreant' ) {
					// Populate with current recreational team IDs (inline query).
					$categories[ $slug ]['matching_teams'] = $this->find_recreational_team_ids_inline();
				} else {
					$categories[ $slug ]['matching_teams'] = [];
				}
			}

			// Add matching_werkfuncties if missing
			if ( ! isset( $category['matching_werkfuncties'] ) ) {
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
	 * Find recreational team IDs via inline WP_Query.
	 *
	 * Migration-only helper: used by {@see self::maybe_migrate_matching_rules}
	 * to auto-populate the recreant matching_teams field on legacy payloads.
	 * Intentionally does not depend on FeeCategoryResolver to avoid coupling
	 * the settings repository to the resolver.
	 *
	 * @return array<int> Array of team post IDs matching recreational criteria.
	 */
	private function find_recreational_team_ids_inline(): array {
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
			$team = get_post( $team_id );
			if ( ! $team || $team->post_type !== 'team' ) {
				continue;
			}

			$title = strtolower( $team->post_title );
			if ( stripos( $title, 'recreant' ) !== false
				|| stripos( $title, 'walking football' ) !== false
				|| stripos( $title, 'walking voetbal' ) !== false ) {
				$recreational_ids[] = (int) $team_id;
			}
		}

		return $recreational_ids;
	}
}
