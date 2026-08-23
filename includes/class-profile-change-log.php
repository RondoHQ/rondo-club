<?php
/**
 * Immutable audit log for member self-service profile changes.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProfileChangeLog {

	public const POST_TYPE         = 'rondo_profile_change';
	private const RETENTION_HOOK   = 'rondo_profile_change_cleanup';
	private const RETENTION_MONTHS = 24;

	public function __construct() {
		add_action( 'init', [ $this, 'schedule_cleanup' ] );
		add_action( self::RETENTION_HOOK, [ $this, 'cleanup' ] );
	}

	/** Schedule the daily retention cleanup. */
	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::RETENTION_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::RETENTION_HOOK );
		}
	}

	/** Permanently remove log entries older than 24 months. */
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
	 * Record one user action, potentially affecting several household people.
	 *
	 * @param string $type Change type.
	 * @param array  $changes Field changes.
	 * @param bool   $verified Whether email ownership was verified.
	 * @param int    $actor_id Acting user ID.
	 * @return int|\WP_Error
	 */
	public static function record( string $type, array $changes, bool $verified, int $actor_id ) {
		if ( empty( $changes ) ) {
			return new \WP_Error( 'rondo_empty_profile_change', 'Er zijn geen wijzigingen om vast te leggen.' );
		}

		$person_ids = [];
		$pending    = [];
		foreach ( $changes as $change ) {
			$person_id = (int) ( $change['person_id'] ?? 0 );
			if ( $person_id <= 0 ) {
				continue;
			}
			$person_ids[] = $person_id;
			if ( ! empty( $change['sync'] ) && Fields::try_get_for_post( $person_id, 'knvb_id' ) ) {
				$sync_fields = ! empty( $change['sync_fields'] ) && is_array( $change['sync_fields'] )
					? $change['sync_fields']
					: [ $change['field'] ];
				foreach ( $sync_fields as $sync_field ) {
					$pending[] = self::pending_key( $person_id, (string) $sync_field );
				}
			}
		}

		$pending = array_values( array_unique( $pending ) );
		$post_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_author' => $actor_id,
				'post_title'  => self::type_label( $type ),
			],
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_rondo_profile_change_type', sanitize_key( $type ) );
		update_post_meta( $post_id, '_rondo_profile_change_changes', array_values( $changes ) );
		update_post_meta( $post_id, '_rondo_profile_change_person_ids', array_values( array_unique( $person_ids ) ) );
		update_post_meta( $post_id, '_rondo_profile_change_verified', $verified ? '1' : '0' );
		update_post_meta( $post_id, '_rondo_profile_change_sync_pending', $pending );
		update_post_meta( $post_id, '_rondo_profile_change_sync_errors', [] );
		update_post_meta( $post_id, '_rondo_profile_change_sync_status', empty( $pending ) ? 'local_only' : 'pending' );

		return (int) $post_id;
	}

	/** Apply one rondo-sync callback to every matching pending audit action. */
	public static function update_sync_status( int $person_id, array $fields, string $status, string $error = '' ): int {
		$posts   = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => 100,
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => '_rondo_profile_change_sync_status',
						'value'   => [ 'pending', 'failed' ],
						'compare' => 'IN',
					],
				],
			]
		);
		$updated = 0;
		foreach ( $posts as $post ) {
			$pending = get_post_meta( $post->ID, '_rondo_profile_change_sync_pending', true );
			$pending = is_array( $pending ) ? $pending : [];
			$matches = [];
			foreach ( $fields as $field ) {
				$key = self::pending_key( $person_id, sanitize_key( (string) $field ) );
				if ( in_array( $key, $pending, true ) ) {
					$matches[] = $key;
				}
			}
			if ( empty( $matches ) ) {
				continue;
			}

			if ( $status === 'synced' ) {
				$pending = array_values( array_diff( $pending, $matches ) );
				update_post_meta( $post->ID, '_rondo_profile_change_sync_pending', $pending );
				update_post_meta( $post->ID, '_rondo_profile_change_sync_status', empty( $pending ) ? 'synced' : 'pending' );
			} else {
				$errors   = get_post_meta( $post->ID, '_rondo_profile_change_sync_errors', true );
				$errors   = is_array( $errors ) ? $errors : [];
				$errors[] = [
					'person_id' => $person_id,
					'fields'    => array_values( $fields ),
					'message'   => sanitize_text_field( $error ),
					'at'        => current_time( 'mysql', true ),
				];
				update_post_meta( $post->ID, '_rondo_profile_change_sync_errors', $errors );
				update_post_meta( $post->ID, '_rondo_profile_change_sync_status', 'failed' );
			}
			++$updated;
		}

		return $updated;
	}

	/** Return recent entries for the members-administration UI. */
	public static function recent( int $page = 1, int $per_page = 50 ): array {
		$query = new \WP_Query(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => min( 100, max( 1, $per_page ) ),
				'paged'            => max( 1, $page ),
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			]
		);

		$items = array_map(
			static function ( $post ): array {
				$author = get_userdata( (int) $post->post_author );
				return [
					'id'          => (int) $post->ID,
					'created_at'  => get_post_time( DATE_ATOM, true, $post ),
					'type'        => (string) get_post_meta( $post->ID, '_rondo_profile_change_type', true ),
					'label'       => get_the_title( $post ),
					'actor'       => $author ? $author->display_name : 'Onbekend account',
					'changes'     => (array) get_post_meta( $post->ID, '_rondo_profile_change_changes', true ),
					'verified'    => get_post_meta( $post->ID, '_rondo_profile_change_verified', true ) === '1',
					'sync_status' => (string) get_post_meta( $post->ID, '_rondo_profile_change_sync_status', true ),
					'sync_errors' => (array) get_post_meta( $post->ID, '_rondo_profile_change_sync_errors', true ),
				];
			},
			$query->posts
		);

		return [
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		];
	}

	private static function pending_key( int $person_id, string $field ): string {
		return $person_id . ':' . sanitize_key( $field );
	}

	private static function type_label( string $type ): string {
		return match ( $type ) {
			'email_primary'   => 'Primair e-mailadres gewijzigd',
			'email_secondary' => 'Tweede e-mailadres gewijzigd',
			'email_promoted'  => 'Primair e-mailadres gewisseld',
			'email_removed'   => 'Tweede e-mailadres verwijderd',
			'phones'          => 'Telefoonnummers gewijzigd',
			'address'         => 'Gezinsadres gewijzigd',
			default           => 'Profielgegevens gewijzigd',
		};
	}
}
