<?php
/**
 * Fee Cache
 *
 * Read-through cache for calculated membership fees. Owns two distinct
 * per-person post-meta stores:
 *
 * 1. **Fee cache** (`rondo_fee_cache_{season}`) — short-lived performance
 *    cache for the cached-read fast path. Invalidated by
 *    {@see FeeCacheInvalidator} on canonical field updates.
 * 2. **Fee snapshot** (`fee_snapshot_{season}`) — season-lock storage used
 *    by the admin "lock fees for a season" flow. Retained for future use
 *    even though the current codebase doesn't populate it automatically
 *    (only `save_fee_snapshot`, `clear_fee_snapshot` and
 *    `clear_all_snapshots_for_season` are exposed).
 *
 * Extracted from {@see MembershipFees} in Phase 218 of the v33.0 Fee
 * Service Decomposition milestone, as part of retiring the god class.
 *
 * Depends on a deferred fee calculator callable so the cache can resolve
 * a cache miss by calling `FeeCalculator::calculate_full_fee()` without
 * forcing a typed circular dependency (FeeCalculator itself doesn't
 * depend on FeeCache, so the cycle is shallow — but using a closure
 * keeps the construction pattern consistent with FamilyGroupingService).
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fee cache storage layer.
 */
class FeeCache {
	/**
	 * Increment when cached eligibility semantics change.
	 */
	private const CACHE_VERSION = 2;

	/**
	 * Deferred full-fee calculator callable.
	 *
	 * Signature: (int $person_id, ?string $registration_date, ?string $season): ?array
	 *
	 * Invoked only on cache miss inside {@see self::get_fee_for_person_cached}.
	 *
	 * @var callable
	 */
	private $full_fee_calculator;

	/**
	 * Constructor.
	 *
	 * @param callable $full_fee_calculator Deferred `FeeCalculator::calculate_full_fee()` callable.
	 */
	public function __construct( callable $full_fee_calculator ) {
		$this->full_fee_calculator = $full_fee_calculator;
	}

	// -------------------------------------------------------------------
	// Fee snapshot (season-lock store)
	// -------------------------------------------------------------------

	/**
	 * Get the post meta key for storing fee snapshots.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return string Meta key for fee snapshot storage.
	 */
	public function get_snapshot_meta_key( ?string $season = null ): string {
		return 'fee_snapshot_' . ( $season ?: SeasonKey::current() );
	}

	/**
	 * Save a fee snapshot for a person.
	 *
	 * Stores fee calculation result in post meta with a timestamp. This
	 * locks the fee for the season, preventing recalculation unless
	 * explicitly requested.
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
	 * Get the fee snapshot for a person.
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
	 * Clear the fee snapshot for a person.
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
	 * Clear all fee snapshots for a season.
	 *
	 * Removes all stored fee calculations for the specified season across
	 * all people. Enables the admin "recalculate all" flow.
	 *
	 * @param string $season The season key (e.g., "2025-2026").
	 * @return int Number of snapshots deleted.
	 */
	public function clear_all_snapshots_for_season( string $season ): int {
		$meta_key = $this->get_snapshot_meta_key( $season );
		$deleted  = 0;

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

	// -------------------------------------------------------------------
	// Performance cache (short-lived)
	// -------------------------------------------------------------------

	/**
	 * Get the post meta key for storing fee cache entries.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return string Meta key for fee cache storage.
	 */
	public function get_fee_cache_meta_key( ?string $season = null ): string {
		return 'rondo_fee_cache_' . ( $season ?: SeasonKey::current() );
	}

	/**
	 * Save calculated fee to cache for fast retrieval.
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
		$fee_data['cache_version'] = self::CACHE_VERSION;

		return (bool) update_post_meta( $person_id, $meta_key, $fee_data );
	}

	/**
	 * Get fee for a person with caching for performance.
	 *
	 * Checks cache first, calculates if cache miss (via the deferred
	 * FeeCalculator callable), saves to cache. Uses the lid-sinds field
	 * for pro-rata calculation (PRO-04).
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

		if ( ! empty( $cached ) && is_array( $cached ) && ! $this->is_cache_stale( $person_id, $cached ) ) {
			$cached['from_cache'] = true;
			return $cached;
		}

		if ( ! empty( $cached ) ) {
			delete_post_meta( $person_id, $meta_key );
		}

		// Cache miss - calculate fresh using lid-sinds (PRO-04 fix)
		$lid_sinds = \Rondo\Fields\Fields::get_for_post( $person_id, 'lid_sinds' );
		$result    = ( $this->full_fee_calculator )( $person_id, $lid_sinds ?: null, $season );

		if ( $result === null ) {
			return null;
		}

		// Add former member flag for diagnostics
		$is_former                  = (bool) \Rondo\Fields\Fields::get_for_post( $person_id, 'former_member' );
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
	 * Check whether a dated work-history transition has outlived the cache.
	 *
	 * A position stops being current on its end date. Field-update invalidation
	 * handles newly saved endings; this read-time check handles future end dates
	 * that become effective through the passage of midnight.
	 *
	 * @param int   $person_id Person post ID.
	 * @param array $cached    Cached fee payload.
	 * @return bool True when the cached calculation must be replaced.
	 */
	private function is_cache_stale( int $person_id, array $cached ): bool {
		if ( (int) ( $cached['cache_version'] ?? 0 ) !== self::CACHE_VERSION ) {
			return true;
		}

		$calculated_at = trim( (string) ( $cached['calculated_at'] ?? '' ) );
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $calculated_at, $matches ) !== 1 ) {
			return true;
		}

		$calculated_date = $matches[1];
		$today           = current_datetime()->format( 'Y-m-d' );
		if ( $calculated_date >= $today ) {
			return false;
		}

		$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' );
		if ( ! is_array( $work_history ) ) {
			return false;
		}

		foreach ( $work_history as $position ) {
			if ( ! is_array( $position ) ) {
				continue;
			}

			$end_date = str_replace( '-', '', trim( (string) ( $position['end_date'] ?? '' ) ) );
			if ( preg_match( '/^\d{8}$/', $end_date ) !== 1 ) {
				continue;
			}

			$end_date = substr( $end_date, 0, 4 ) . '-' . substr( $end_date, 4, 2 ) . '-' . substr( $end_date, 6, 2 );
			if ( $end_date > $calculated_date && $end_date <= $today ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clear the fee cache for a person.
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
	 * Clear all fee caches for a season.
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
}
