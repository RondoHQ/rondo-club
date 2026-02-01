<?php
/**
 * Fee Cache Invalidation
 *
 * Automatically invalidates cached membership fees when relevant fields change.
 * Hooks into ACF update_value filters and REST API updates.
 *
 * @package Stadion\Fees
 */

namespace Stadion\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FeeCacheInvalidator
 *
 * Manages automatic invalidation of fee caches when dependencies change:
 * - leeftijdsgroep: Affects fee category (mini/pupil/junior/senior)
 * - addresses: Affects family grouping and discount
 * - work_history: Affects team membership and recreant detection
 * - lid-sinds: Affects pro-rata calculation
 */
class FeeCacheInvalidator {

	/**
	 * MembershipFees instance
	 *
	 * @var MembershipFees
	 */
	private $fees;

	/**
	 * Constructor - registers all invalidation hooks
	 */
	public function __construct() {
		$this->fees = new MembershipFees();

		// Age group changes affect fee category
		add_filter( 'acf/update_value/name=leeftijdsgroep', [ $this, 'invalidate_person_cache' ], 10, 3 );

		// Address changes affect family grouping (invalidate entire family)
		add_filter( 'acf/update_value/name=addresses', [ $this, 'invalidate_family_cache' ], 10, 3 );

		// Team changes affect senior vs recreant categorization
		add_filter( 'acf/update_value/name=work_history', [ $this, 'invalidate_person_cache' ], 10, 3 );

		// lid-sinds changes affect pro-rata calculation (PRO-04)
		add_filter( 'acf/update_value/name=lid-sinds', [ $this, 'invalidate_person_cache' ], 10, 3 );

		// REST API updates
		add_action( 'rest_after_insert_person', [ $this, 'invalidate_person_cache_rest' ], 10, 2 );

		// Settings changes affect all people - trigger bulk recalculation
		add_action( 'update_option_stadion_membership_fees', [ $this, 'schedule_bulk_recalculation' ], 10, 2 );

		// Cron hook for background recalculation
		add_action( 'stadion_recalculate_all_fees', [ $this, 'recalculate_all_fees_background' ] );
	}

	/**
	 * Invalidate fee cache for a person when relevant field changes
	 *
	 * @param mixed $value   The new field value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The ACF field array.
	 * @return mixed The unmodified value (filter passthrough).
	 */
	public function invalidate_person_cache( $value, $post_id, $field ) {
		// Handle object post IDs (can be passed by ACF)
		if ( is_object( $post_id ) && isset( $post_id->ID ) ) {
			$post_id = $post_id->ID;
		}

		// Validate post type
		if ( get_post_type( $post_id ) !== 'person' ) {
			return $value;
		}

		// Clear fee cache for current season
		$this->fees->clear_fee_cache( (int) $post_id );

		return $value;
	}

	/**
	 * Invalidate fee cache for entire family when address changes
	 *
	 * Address changes affect family grouping, which impacts discounts for all
	 * family members at the same address. Must invalidate all siblings.
	 *
	 * @param mixed $value   The new field value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The ACF field array.
	 * @return mixed The unmodified value (filter passthrough).
	 */
	public function invalidate_family_cache( $value, $post_id, $field ) {
		// Handle object post IDs
		if ( is_object( $post_id ) && isset( $post_id->ID ) ) {
			$post_id = $post_id->ID;
		}

		// Validate post type
		if ( get_post_type( $post_id ) !== 'person' ) {
			return $value;
		}

		$post_id = (int) $post_id;

		// Clear this person's cache first
		$this->fees->clear_fee_cache( $post_id );

		// Get family key BEFORE the address update (using current saved value)
		$old_family_key = $this->fees->get_family_key( $post_id );

		// If person was in a family, invalidate all family members
		if ( $old_family_key !== null ) {
			$this->invalidate_family_by_key( $old_family_key, $post_id );
		}

		return $value;
	}

	/**
	 * Invalidate all family members' caches by family key
	 *
	 * @param string $family_key The family key (postal code + house number).
	 * @param int    $exclude_id Person ID to exclude (already invalidated).
	 */
	private function invalidate_family_by_key( string $family_key, int $exclude_id ): void {
		$groups   = $this->fees->build_family_groups();
		$families = $groups['families'];

		if ( ! isset( $families[ $family_key ] ) ) {
			return;
		}

		foreach ( $families[ $family_key ] as $member_id ) {
			if ( (int) $member_id !== $exclude_id ) {
				$this->fees->clear_fee_cache( (int) $member_id );
			}
		}
	}

	/**
	 * Invalidate fee cache when person is updated via REST API
	 *
	 * @param \WP_Post         $post    The post object.
	 * @param \WP_REST_Request $request The request object.
	 */
	public function invalidate_person_cache_rest( $post, $request ) {
		$this->fees->clear_fee_cache( $post->ID );
	}

	/**
	 * Invalidate all fee caches (called when settings change)
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return int Number of caches cleared.
	 */
	public function invalidate_all_caches( ?string $season = null ): int {
		$season = $season ?: $this->fees->get_season_key();
		return $this->fees->clear_all_fee_caches( $season );
	}

	/**
	 * Schedule bulk recalculation when fee settings change
	 *
	 * Uses wp_schedule_single_event to avoid timeouts with large member counts.
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $new_value The new option value.
	 */
	public function schedule_bulk_recalculation( $old_value, $new_value ) {
		$season = $this->fees->get_season_key();

		// Clear all caches immediately
		$cleared = $this->fees->clear_all_fee_caches( $season );

		// Schedule background recalculation (10 seconds from now)
		if ( ! wp_next_scheduled( 'stadion_recalculate_all_fees', [ $season ] ) ) {
			wp_schedule_single_event( time() + 10, 'stadion_recalculate_all_fees', [ $season ] );
		}

		// Log for debugging
		error_log( sprintf(
			'[Stadion Fee Cache] Settings changed: cleared %d caches for season %s, recalculation scheduled',
			$cleared,
			$season
		) );
	}

	/**
	 * Background job to recalculate all fees
	 *
	 * Called by WordPress cron after settings change.
	 * Calculates and caches fees for all people to pre-warm cache.
	 *
	 * @param string $season The season key.
	 */
	public function recalculate_all_fees_background( $season ) {
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		$calculated = 0;
		$skipped    = 0;

		foreach ( $query->posts as $person_id ) {
			// Use get_fee_for_person_cached which calculates and saves
			$result = $this->fees->get_fee_for_person_cached( (int) $person_id, $season );

			if ( $result !== null ) {
				$calculated++;
			} else {
				$skipped++;
			}
		}

		error_log( sprintf(
			'[Stadion Fee Cache] Bulk recalculation complete: %d calculated, %d skipped for season %s',
			$calculated,
			$skipped,
			$season
		) );
	}
}
