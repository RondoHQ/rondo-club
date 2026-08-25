<?php
/**
 * Private audit log for failed public account activations.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ActivationLog {

	public const POST_TYPE         = 'rondo_activation_log';
	private const RETENTION_HOOK   = 'rondo_activation_log_cleanup';
	private const RETENTION_MONTHS = 12;

	public function __construct() {
		add_action( 'init', [ $this, 'schedule_cleanup' ] );
		add_action( self::RETENTION_HOOK, [ $this, 'cleanup' ] );
	}

	/** Schedule daily retention cleanup. */
	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::RETENTION_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::RETENTION_HOOK );
		}
	}

	/** Permanently remove activation errors older than twelve months. */
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
	 * Record one failed activation attempt.
	 *
	 * @param string $code       Stable error code.
	 * @param string $message    Operator-facing error message.
	 * @param int[]  $person_ids People proven to belong to the activation context.
	 * @param string $email      Address proven by the token or person lookup.
	 * @param string $source     Activation entry point.
	 * @return int|\WP_Error
	 */
	public static function record_failure( string $code, string $message, array $person_ids = [], string $email = '', string $source = 'public_activation' ) {
		$person_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $person_ids ),
					static fn( int $person_id ): bool => $person_id > 0 && get_post_type( $person_id ) === 'person'
				)
			)
		);
		$email      = sanitize_email( $email );
		$code       = sanitize_key( $code ) ?: 'activation_failed';
		$message    = sanitize_text_field( $message ) ?: 'Accountactivatie is mislukt.';
		$source     = sanitize_key( $source ) ?: 'public_activation';
		$names      = array_values( array_filter( array_map( 'get_the_title', $person_ids ) ) );
		$title      = ! empty( $names ) ? implode( ', ', $names ) : ( $email ?: 'Onbekende activatie' );

		$post_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => sprintf( 'Activatie mislukt: %s', $title ),
			],
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_rondo_activation_error_code', $code );
		update_post_meta( $post_id, '_rondo_activation_error_message', $message );
		update_post_meta( $post_id, '_rondo_activation_person_ids', $person_ids );
		update_post_meta( $post_id, '_rondo_activation_person_names', $names );
		update_post_meta( $post_id, '_rondo_activation_email', $email );
		update_post_meta( $post_id, '_rondo_activation_source', $source );

		return (int) $post_id;
	}

	/** Return recent activation errors for the admin user-management screen. */
	public static function recent( int $limit = 50 ): array {
		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => min( 100, max( 1, $limit ) ),
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			]
		);

		return array_map(
			static function ( $post ): array {
				$person_ids   = (array) get_post_meta( $post->ID, '_rondo_activation_person_ids', true );
				$stored_names = (array) get_post_meta( $post->ID, '_rondo_activation_person_names', true );
				$people       = [];
				foreach ( array_values( $person_ids ) as $index => $person_id ) {
					$person_id = (int) $person_id;
					if ( $person_id <= 0 ) {
						continue;
					}
					$people[] = [
						'id'   => $person_id,
						'name' => get_the_title( $person_id ) ?: (string) ( $stored_names[ $index ] ?? 'Onbekende persoon' ),
					];
				}

				return [
					'id'         => (int) $post->ID,
					'created_at' => get_post_time( DATE_ATOM, true, $post ),
					'code'       => (string) get_post_meta( $post->ID, '_rondo_activation_error_code', true ),
					'message'    => (string) get_post_meta( $post->ID, '_rondo_activation_error_message', true ),
					'email'      => (string) get_post_meta( $post->ID, '_rondo_activation_email', true ),
					'source'     => (string) get_post_meta( $post->ID, '_rondo_activation_source', true ),
					'people'     => $people,
				];
			},
			$posts
		);
	}
}
