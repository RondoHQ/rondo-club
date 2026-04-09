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
	 * FeeCalculator takes category_resolver, family_grouping, settings
	 * and MembershipFees as explicit collaborators.
	 *
	 * @var FeeCalculator|null
	 */
	private ?FeeCalculator $fee_calculator = null;

	/**
	 * Lazily instantiated membership fee settings repository.
	 *
	 * Extracted from this class in Phase 217 of the v33.0 Fee Service
	 * Decomposition milestone. Owns all Options API storage for fees:
	 * categories per season, billing method, installment plans,
	 * family/entry discount config, and legacy data migrations.
	 * Accessed via {@see self::settings()}.
	 *
	 * @var MembershipFeeSettings|null
	 */
	private ?MembershipFeeSettings $settings = null;

	/**
	 * Get the lazy-initialized category resolver.
	 *
	 * The resolver reads season categories via a callable provider pointing
	 * at {@see MembershipFeeSettings::get_categories_for_season()} — the
	 * provider was rewired from MembershipFees to MembershipFeeSettings in
	 * Phase 217 so the resolver no longer reaches into the god class for
	 * settings data.
	 *
	 * @return FeeCategoryResolver
	 */
	public function category_resolver(): FeeCategoryResolver {
		if ( $this->category_resolver === null ) {
			$settings                = $this->settings();
			$this->category_resolver = new FeeCategoryResolver(
				fn( string $season ): array => $settings->get_categories_for_season( $season )
			);
		}

		return $this->category_resolver;
	}

	/**
	 * Get the lazy-initialized family grouping service.
	 *
	 * External callers (REST fees controller, FeeCacheInvalidator) obtain
	 * their FamilyGroupingService reference through this accessor so they
	 * do not have to know about the wiring.
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
				$this->settings(),
				fn( int $person_id, ?string $season ) => $this->fee_calculator()->calculate_fee( $person_id, $season )
			);
		}

		return $this->family_grouping;
	}

	/**
	 * Get the lazy-initialized fee calculator.
	 *
	 * FeeCalculator is wired with FeeCategoryResolver (Phase 214),
	 * FamilyGroupingService (Phase 215), MembershipFeeSettings (Phase 217)
	 * and a MembershipFees reference for the person-data helpers that
	 * still live here (get_current_teams, get_effective_werkfuncties,
	 * normalize_werkfuncties_for_fee_match).
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
				$this->settings(),
				$this
			);
		}

		return $this->fee_calculator;
	}

	/**
	 * Get the lazy-initialized membership fee settings repository.
	 *
	 * Returns the single shared MembershipFeeSettings instance for this
	 * MembershipFees facade. External callers should go through this
	 * accessor instead of constructing a new instance so config reads
	 * benefit from the process-level WP options cache already populated
	 * during this request.
	 *
	 * MembershipFeeSettings itself has zero constructor dependencies, so
	 * callers are also free to instantiate `new MembershipFeeSettings()`
	 * directly if they want to bypass the facade.
	 *
	 * @return MembershipFeeSettings
	 */
	public function settings(): MembershipFeeSettings {
		if ( $this->settings === null ) {
			$this->settings = new MembershipFeeSettings();
		}

		return $this->settings;
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
