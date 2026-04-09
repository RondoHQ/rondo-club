<?php
/**
 * Fee Cache Invalidation
 *
 * Automatically invalidates cached membership fees when relevant fields change.
 * Hooks into ACF update_value filters and REST API updates.
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

use Rondo\Core\RoleFinder;
use Rondo\Notifications\EmailTemplate;

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
	 * Fee cache storage service (Phase 218).
	 *
	 * Replaces the MembershipFees reference this class held from its
	 * inception. Before Phase 218, every cache clear went through
	 * `$this->fee_cache->clear_fee_cache()`; now they go through
	 * `$this->fee_cache->clear_fee_cache()` on an explicit typed
	 * dependency.
	 *
	 * @var FeeCache
	 */
	private FeeCache $fee_cache;

	/**
	 * FamilyGroupingService instance.
	 *
	 * Explicit dependency established in Phase 215 (STRU-04). All five
	 * family-grouping call sites go through this property instead of
	 * reaching through a god-class reference.
	 *
	 * @var FamilyGroupingService
	 */
	private FamilyGroupingService $family_grouping;

	/**
	 * Constructor - registers all invalidation hooks
	 */
	public function __construct() {
		$this->fee_cache       = FeeServices::fee_cache();
		$this->family_grouping = FeeServices::family_grouping();

		// Age group changes affect fee category
		add_filter( 'acf/update_value/name=leeftijdsgroep', [ $this, 'invalidate_person_cache' ], 10, 3 );

		// Address changes affect family grouping (invalidate entire family)
		add_filter( 'acf/update_value/name=addresses', [ $this, 'invalidate_family_cache' ], 10, 3 );

		// Team changes affect senior vs recreant categorization
		add_filter( 'acf/update_value/name=work_history', [ $this, 'invalidate_person_cache' ], 10, 3 );

		// lid-sinds changes affect pro-rata calculation (PRO-04)
		add_filter( 'acf/update_value/name=lid-sinds', [ $this, 'invalidate_person_cache' ], 10, 3 );

		// former_member changes affect fee eligibility
		add_filter( 'acf/update_value/name=former_member', [ $this, 'invalidate_person_cache' ], 10, 3 );

		// REST API updates
		add_action( 'rest_after_insert_person', [ $this, 'invalidate_person_cache_rest' ], 10, 2 );

		// Log timeline activity when contributie exclusion is toggled.
		add_action( 'added_post_meta', [ $this, 'log_contributie_exclusion_toggle' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'log_contributie_exclusion_toggle' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'log_contributie_exclusion_toggle' ], 10, 4 );

		// Settings changes affect all people - trigger bulk recalculation
		add_action( 'update_option_rondo_membership_fees', [ $this, 'schedule_bulk_recalculation' ], 10, 2 );

		// Cron hook for background recalculation
		add_action( 'rondo_recalculate_all_fees', [ $this, 'recalculate_all_fees_background' ] );
	}

	/**
	 * Resolve a post ID from an int or ACF object, returning null if not a person post.
	 *
	 * @param mixed $post_id Raw post ID (int or ACF object with ->ID).
	 * @return int|null Resolved integer post ID, or null if not a person post.
	 */
	private function resolve_person_post_id( $post_id ): ?int {
		if ( is_object( $post_id ) && isset( $post_id->ID ) ) {
			$post_id = $post_id->ID;
		}

		if ( get_post_type( $post_id ) !== 'person' ) {
			return null;
		}

		return (int) $post_id;
	}

	/**
	 * Invalidate fee cache for a person when relevant field changes
	 *
	 * @param mixed $value   The new field value.
	 * @param mixed $post_id The post ID.
	 * @param array $field   The ACF field array.
	 * @return mixed The unmodified value (filter passthrough).
	 */
	public function invalidate_person_cache( $value, $post_id, $field ) {
		$post_id = $this->resolve_person_post_id( $post_id );

		if ( $post_id === null ) {
			return $value;
		}

		$this->fee_cache->clear_fee_cache( $post_id );

		return $value;
	}

	/**
	 * Invalidate fee cache for entire family when address changes
	 *
	 * Address changes affect family grouping, which impacts discounts for all
	 * family members at the same address. Must invalidate all siblings.
	 *
	 * @param mixed $value   The new field value.
	 * @param mixed $post_id The post ID.
	 * @param array $field   The ACF field array.
	 * @return mixed The unmodified value (filter passthrough).
	 */
	public function invalidate_family_cache( $value, $post_id, $field ) {
		$post_id = $this->resolve_person_post_id( $post_id );

		if ( $post_id === null ) {
			return $value;
		}

		// Clear this person's cache first
		$this->fee_cache->clear_fee_cache( $post_id );

		// Get family key BEFORE the address update (using current saved value)
		$old_family_key = $this->family_grouping->get_family_key( $post_id );

		// Clear family discount meta for this person (will be recalculated on next cache miss)
		delete_post_meta( $post_id, '_family_discount_rate' );
		delete_post_meta( $post_id, '_family_discount_position' );

		// If person was in a family, invalidate all members and recalculate positions in one pass
		if ( $old_family_key !== null ) {
			$this->invalidate_and_recalculate_family( $old_family_key, $post_id );
		}

		return $value;
	}

	/**
	 * Invalidate all family members' caches and recalculate positions for a given family key
	 *
	 * Combines invalidation and position recalculation into a single pass over family groups
	 * to avoid calling build_family_groups() twice per address change.
	 *
	 * @param string $family_key The family key (postal code + house number).
	 * @param int    $exclude_id Person ID to exclude from invalidation (already invalidated).
	 */
	private function invalidate_and_recalculate_family( string $family_key, int $exclude_id ): void {
		$families = $this->family_grouping->build_family_groups()['families'];

		if ( empty( $families[ $family_key ] ) ) {
			return;
		}

		foreach ( $families[ $family_key ] as $member_id ) {
			if ( (int) $member_id !== $exclude_id ) {
				$this->fee_cache->clear_fee_cache( (int) $member_id );
				delete_post_meta( $member_id, '_family_discount_rate' );
				delete_post_meta( $member_id, '_family_discount_position' );
			}
		}

		$first_member = (int) $families[ $family_key ][0];
		$this->family_grouping->recalculate_family_positions_for_person( $first_member );
	}

	/**
	 * Invalidate fee cache when person is updated via REST API
	 *
	 * @param \WP_Post         $post    The post object.
	 * @param \WP_REST_Request $request The request object.
	 */
	public function invalidate_person_cache_rest( $post, $request ) {
		$this->fee_cache->clear_fee_cache( $post->ID );
	}

	/**
	 * Log timeline activity when _exclude_from_contributie changes.
	 *
	 * @param int    $meta_id    Meta row ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function log_contributie_exclusion_toggle( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		if ( $meta_key !== '_exclude_from_contributie' ) {
			return;
		}

		if ( get_post_type( $post_id ) !== 'person' ) {
			return;
		}

		$is_excluded = (bool) get_post_meta( $post_id, '_exclude_from_contributie', true );
		$actor_id    = get_current_user_id();
		$actor_name  = __( 'Systeem', 'rondo' );

		if ( $actor_id > 0 ) {
			$user = get_userdata( $actor_id );
			if ( $user && ! empty( $user->display_name ) ) {
				$actor_name = $user->display_name;
			}
		}

		if ( $is_excluded ) {
			// translators: %s is the name of the user who excluded the member from fees.
			$message = sprintf( __( 'Contributie uitgesloten door %s.', 'rondo' ), $actor_name );
		} else {
			// translators: %s is the name of the user who re-included the member for fees.
			$message = sprintf( __( 'Contributie opnieuw opgenomen door %s.', 'rondo' ), $actor_name );
		}

		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => (int) $post_id,
				'comment_content'  => $message,
				'comment_type'     => \Rondo\Collaboration\CommentTypes::TYPE_ACTIVITY,
				'user_id'          => $actor_id,
				'comment_approved' => 1,
			]
		);

		if ( ! $comment_id ) {
			return;
		}

		update_comment_meta( $comment_id, 'activity_type', 'contributie_exclusion_toggle' );
		update_comment_meta( $comment_id, 'activity_date', current_time( 'Y-m-d' ) );
		update_comment_meta( $comment_id, 'activity_time', current_time( 'H:i' ) );

		$this->send_exclusion_notification_email( (int) $post_id, $is_excluded, $actor_name );
	}

	/**
	 * Send email notification to Secretaris and Penningmeester about an exclusion toggle.
	 *
	 * Falls back to administrator emails when no Secretaris/Penningmeester users are found.
	 * Failure is logged but never blocks the toggle action.
	 *
	 * @param int    $post_id    Person post ID.
	 * @param bool   $is_excluded Whether the person is now excluded.
	 * @param string $actor_name Display name of the user who triggered the toggle.
	 */
	private function send_exclusion_notification_email( int $post_id, bool $is_excluded, string $actor_name ): void {
		try {
			// Collect recipient user IDs from Secretaris and Penningmeester roles.
			$user_ids = array_unique(
				array_merge(
					RoleFinder::get_user_ids_by_role( 'Secretaris' ),
					RoleFinder::get_user_ids_by_role( 'Penningmeester' )
				)
			);

			// Resolve email addresses, skipping users without a valid email.
			$emails = [];
			foreach ( $user_ids as $uid ) {
				$user = get_userdata( $uid );
				if ( $user && is_email( $user->user_email ) ) {
					$emails[] = $user->user_email;
				}
			}
			$emails = array_unique( $emails );

			if ( empty( $emails ) ) {
				return;
			}

			$person_name = get_the_title( $post_id );
			$timestamp   = current_time( 'd-m-Y H:i' );

			$subject = $is_excluded
				? sprintf( '%s uitgesloten van contributiebetaling', $person_name )
				: sprintf( '%s opgenomen in contributiebetaling', $person_name );

			$heading = $subject;

			$body_html = $is_excluded
				? sprintf(
					'<p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;"><strong>%s</strong> is door <strong>%s</strong> uitgesloten van de contributiebetaling op %s.</p>',
					esc_html( $person_name ),
					esc_html( $actor_name ),
					esc_html( $timestamp )
				)
				: sprintf(
					'<p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;"><strong>%s</strong> is door <strong>%s</strong> opnieuw opgenomen in de contributiebetaling op %s.</p>',
					esc_html( $person_name ),
					esc_html( $actor_name ),
					esc_html( $timestamp )
				);

			$message = EmailTemplate::render(
				[
					'eyebrow'   => 'Contributie',
					'heading'   => $heading,
					'body_html' => $body_html,
					'cta_url'   => home_url( '/people/' . $post_id ),
					'cta_label' => 'Bekijk lid',
				]
			);

			$site_name = get_bloginfo( 'name' );
			$host      = wp_parse_url( home_url(), PHP_URL_HOST );
			$parts     = explode( '.', $host );
			$domain    = count( $parts ) >= 2
				? implode( '.', array_slice( $parts, -2 ) )
				: $host;

			$headers = [
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $site_name . ' <notifications@' . $domain . '>',
			];

			wp_mail( $emails, $subject, $message, $headers );
		} catch ( \Throwable $e ) {
			error_log(
				sprintf(
					'[Rondo Contributie] Failed to send exclusion notification for person %d: %s',
					$post_id,
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Invalidate all fee caches (called when settings change)
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return int Number of caches cleared.
	 */
	public function invalidate_all_caches( ?string $season = null ): int {
		$season = $season ?: SeasonKey::current();
		return $this->fee_cache->clear_all_fee_caches( $season );
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
		$season = SeasonKey::current();

		// Clear all caches and family discount meta immediately
		$cleared = $this->fee_cache->clear_all_fee_caches( $season );
		$this->family_grouping->clear_all_family_discount_meta();

		// Schedule background recalculation (10 seconds from now)
		if ( ! wp_next_scheduled( 'rondo_recalculate_all_fees', [ $season ] ) ) {
			wp_schedule_single_event( time() + 10, 'rondo_recalculate_all_fees', [ $season ] );
		}

		// Log for debugging
		error_log(
			sprintf(
				'[Rondo Fee Cache] Settings changed: cleared %d caches for season %s, recalculation scheduled',
				$cleared,
				$season
			)
		);
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
		// Recalculate all family positions first (single pass over all persons)
		$positions_updated = $this->family_grouping->recalculate_all_family_positions( $season );

		error_log(
			sprintf(
				'[Rondo Fee Cache] Family positions recalculated: %d persons updated for season %s',
				$positions_updated,
				$season
			)
		);

		// Now recalculate individual fees (which read stored meta instead of rebuilding groups)
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
			// Clear existing cache first to force fresh calculation
			$this->fee_cache->clear_fee_cache( (int) $person_id, $season );

			// Now get_fee_for_person_cached will calculate fresh and save
			$result = $this->fee_cache->get_fee_for_person_cached( (int) $person_id, $season );

			if ( $result !== null ) {
				++$calculated;
			} else {
				++$skipped;
			}
		}

		error_log(
			sprintf(
				'[Rondo Fee Cache] Bulk recalculation complete: %d calculated, %d skipped for season %s',
				$calculated,
				$skipped,
				$season
			)
		);
	}
}
