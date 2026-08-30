<?php
/**
 * Immutable activity log for sponsor self-service actions.
 *
 * @package Rondo\Sponsors
 */

namespace Rondo\Sponsors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Record sponsor logins and self-service changes for sponsor managers. */
final class ActivityLog {

	public const POST_TYPE         = 'rondo_sponsor_log';
	private const RETENTION_HOOK   = 'rondo_sponsor_log_cleanup';
	private const RETENTION_MONTHS = 24;
	private const EVENT_TYPES      = [ 'login', 'logo_changed', 'club_tv_preference_changed' ];

	public function __construct() {
		add_action( 'wp_login', [ $this, 'record_login' ], 10, 2 );
		add_action( 'init', [ $this, 'schedule_cleanup' ] );
		add_action( self::RETENTION_HOOK, [ $this, 'cleanup' ] );
	}

	/** Record a successful login against every active sponsor represented by the account. */
	public function record_login( string $user_login, \WP_User $user ): void {
		$person_id = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
		if ( $person_id <= 0 ) {
			return;
		}

		$sponsor_ids = [];
		foreach ( Relations::for_person( $person_id, true ) as $relationship ) {
			$sponsor_id = (int) ( $relationship['sponsor_id'] ?? 0 );
			if ( $sponsor_id > 0 ) {
				$sponsor_ids[] = $sponsor_id;
			}
		}

		foreach ( array_unique( $sponsor_ids ) as $sponsor_id ) {
			self::record( $sponsor_id, 'login', $user->ID, $person_id );
		}
	}

	/** Schedule daily retention cleanup. */
	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::RETENTION_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::RETENTION_HOOK );
		}
	}

	/** Permanently remove sponsor activity older than 24 months. */
	public function cleanup(): void {
		$ids = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded daily retention batch.
				'fields'           => 'ids',
				'suppress_filters' => true,
				'date_query'       => [
					[
						'before'    => gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::RETENTION_MONTHS . ' months' ) ),
						'inclusive' => false,
					],
				],
			]
		);

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * Record one successful sponsor activity.
	 *
	 * @param int    $sponsor_id Sponsor company post ID.
	 * @param string $event_type Stable event type.
	 * @param int    $user_id    Acting WordPress user ID.
	 * @param int    $person_id  Linked person ID, if known.
	 * @param array  $details    Small event-specific detail map.
	 * @return int|\WP_Error
	 */
	public static function record( int $sponsor_id, string $event_type, int $user_id = 0, int $person_id = 0, array $details = [] ) {
		if ( get_post_type( $sponsor_id ) !== 'rondo_sponsor' ) {
			return new \WP_Error( 'rondo_sponsor_activity_invalid_sponsor', 'De sponsoractiviteit hoort niet bij een geldige sponsor.' );
		}

		$event_type = sanitize_key( $event_type );
		if ( ! in_array( $event_type, self::EVENT_TYPES, true ) ) {
			return new \WP_Error( 'rondo_sponsor_activity_invalid_type', 'Het type sponsoractiviteit is ongeldig.' );
		}

		$user       = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;
		$person_id  = $person_id > 0 ? $person_id : (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		$actor_name = $person_id > 0 && get_post_type( $person_id ) === 'person'
			? get_the_title( $person_id )
			: ( $user ? $user->display_name : '' );
		$actor_name = sanitize_text_field( (string) $actor_name ) ?: 'Onbekende gebruiker';

		$post_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_author' => $user_id,
				'post_title'  => sprintf( '%s: %s', self::event_label( $event_type, $details ), $actor_name ),
			],
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_rondo_sponsor_activity_sponsor_id', $sponsor_id );
		update_post_meta( $post_id, '_rondo_sponsor_activity_type', $event_type );
		update_post_meta( $post_id, '_rondo_sponsor_activity_user_id', $user_id );
		update_post_meta( $post_id, '_rondo_sponsor_activity_person_id', $person_id );
		update_post_meta( $post_id, '_rondo_sponsor_activity_actor_name', $actor_name );
		update_post_meta( $post_id, '_rondo_sponsor_activity_details', self::sanitize_details( $details ) );

		return (int) $post_id;
	}

	/** Return recent activities for one sponsor profile. */
	public static function recent( int $sponsor_id, int $limit = 50 ): array {
		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => min( 100, max( 1, $limit ) ),
				'orderby'          => [
					'date' => 'DESC',
					'ID'   => 'DESC',
				],
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => '_rondo_sponsor_activity_sponsor_id',
						'value'   => $sponsor_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);

		return array_map(
			static function ( \WP_Post $post ): array {
				$type    = (string) get_post_meta( $post->ID, '_rondo_sponsor_activity_type', true );
				$details = get_post_meta( $post->ID, '_rondo_sponsor_activity_details', true );
				$details = is_array( $details ) ? $details : [];

				return [
					'id'         => (int) $post->ID,
					'type'       => $type,
					'label'      => self::event_label( $type, $details ),
					'created_at' => get_post_time( DATE_ATOM, true, $post ),
					'actor_name' => (string) get_post_meta( $post->ID, '_rondo_sponsor_activity_actor_name', true ),
					'person_id'  => (int) get_post_meta( $post->ID, '_rondo_sponsor_activity_person_id', true ),
					'details'    => $details,
				];
			},
			$posts
		);
	}

	/** Keep stored event details small and predictable. */
	private static function sanitize_details( array $details ): array {
		return array_key_exists( 'opt_out', $details )
			? [ 'opt_out' => (bool) $details['opt_out'] ]
			: [];
	}

	/** Build the operator-facing event label. */
	private static function event_label( string $event_type, array $details = [] ): string {
		switch ( $event_type ) {
			case 'login':
				return 'Ingelogd';
			case 'logo_changed':
				return 'Logo aangepast';
			case 'club_tv_preference_changed':
				return ! empty( $details['opt_out'] ) ? 'Club TV: niet tonen' : 'Club TV: tonen';
			default:
				return 'Sponsoractiviteit';
		}
	}
}
