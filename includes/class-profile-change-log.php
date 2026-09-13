<?php
/**
 * Immutable audit log for member self-service profile changes.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Fields\Fields;
use Rondo\Data\InverseRelationships;

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
	 * @param string $source Origin of the change: rondo or sportlink.
	 * @return int|\WP_Error
	 */
	public static function record( string $type, array $changes, bool $verified, int $actor_id, string $source = 'rondo' ) {
		if ( empty( $changes ) ) {
			return new \WP_Error( 'rondo_empty_profile_change', 'Er zijn geen wijzigingen om vast te leggen.' );
		}

		$changes    = self::prepare_parent_sync( $changes, $type );
		$person_ids = [];
		$pending    = [];
		foreach ( $changes as $change ) {
			$person_id = (int) ( $change['person_id'] ?? 0 );
			if ( $person_id <= 0 ) {
				continue;
			}
			$person_ids[] = $person_id;
			foreach ( $change['parent_sync']['child_ids'] ?? [] as $child_id ) {
				$pending[] = self::pending_key( $person_id, 'parent_' . $child_id . '_' . $change['field'] );
			}
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
		update_post_meta( $post_id, '_rondo_profile_change_source', $source === 'sportlink' ? 'sportlink' : 'rondo' );
		update_post_meta( $post_id, '_rondo_profile_change_changes', array_values( $changes ) );
		update_post_meta( $post_id, '_rondo_profile_change_person_ids', array_values( array_unique( $person_ids ) ) );
		update_post_meta( $post_id, '_rondo_profile_change_verified', $verified ? '1' : '0' );
		update_post_meta( $post_id, '_rondo_profile_change_sync_pending', $pending );
		update_post_meta( $post_id, '_rondo_profile_change_sync_errors', [] );
		update_post_meta( $post_id, '_rondo_profile_change_sync_status', empty( $pending ) ? 'local_only' : 'pending' );

		return (int) $post_id;
	}

	/** Log only a successfully saved photo, never a protected/skipped import. */
	public static function record_photo( int $person_id, int $before, int $after, string $source ) {
		return self::record(
			'photo',
			[
				[
					'person_id'         => $person_id,
					'person_name'       => get_the_title( $person_id ),
					'field'             => 'photo',
					'label'             => 'Profielfoto',
					'old'               => $before ? 'Vorige foto' : 'Geen foto',
					'new'               => 'Nieuwe foto opgeslagen',
					'old_attachment_id' => $before,
					'new_attachment_id' => $after,
					'sync'              => false, // PhotoSync owns its own revision-bound queue.
				],
			],
			false,
			get_current_user_id(),
			$source
		);
	}

	/** Show photo delivery state without sending photos through the field-sync queue. */
	private static function photo_status( array $changes, string $source ): string {
		if ( $source === 'sportlink' ) {
			return 'imported';
		}
		$change = $changes[0] ?? [];
		$person = (int) ( $change['person_id'] ?? 0 );
		if ( (int) get_post_thumbnail_id( $person ) !== (int) ( $change['new_attachment_id'] ?? 0 ) ) {
			return 'superseded';
		}
		$status = \Rondo\People\PhotoSync::status( $person );
		return match ( $status['state'] ?? '' ) {
			'synced' => 'synced',
			'review' => 'action_required',
			'pending', 'waiting_window', 'sending' => 'pending',
			default => 'local_only',
		};
	}

	/** Snapshot the separate Sportlink parent-slot targets of an audited action. */
	public static function prepare_parent_sync( array $changes, string $type = '' ): array {
		$phone_fields = [ 'mobile_1', 'telephone_1', 'mobile_2', 'telephone_2' ];
		$phone_done   = [];
		foreach ( $changes as &$change ) {
			unset( $change['parent_sync'] );
			$person_id = (int) ( $change['person_id'] ?? 0 );
			$field     = (string) ( $change['field'] ?? '' );
			$is_phone  = in_array( $field, $phone_fields, true );
			if ( $type === 'email_promoted' && $field === 'email_2' ) {
				continue;
			}
			if ( ! $is_phone && ! in_array( $field, [ 'email_1', 'email_2' ], true ) ) {
				continue;
			}
			$old = (string) ( $change['old'] ?? '' );
			$new = (string) ( $change['new'] ?? '' );
			if ( $is_phone ) {
				if ( isset( $phone_done[ $person_id ] ) ) {
					continue;
				}
				$phone_done[ $person_id ] = true;
				$current                  = [];
				foreach ( $phone_fields as $phone_field ) {
					$current[ $phone_field ] = (string) Fields::try_get_for_post( $person_id, $phone_field );
				}
				$before = $current;
				foreach ( $changes as $other ) {
					if ( (int) $other['person_id'] === $person_id && in_array( $other['field'], $phone_fields, true ) ) {
						$before[ $other['field'] ] = (string) $other['old'];
					}
				}
				$old = (string) ( array_values( array_filter( $before ) )[0] ?? '' );
				$new = (string) ( array_values( array_filter( $current ) )[0] ?? '' );
			}
			if ( $old === $new || ( ! $is_phone && $old === '' ) ) {
				continue;
			}
			$child_ids = [];
			foreach ( Fields::try_get_for_post( $person_id, 'relationships' ) ?: [] as $relationship ) {
				$child_id = (int) ( $relationship['related_person'] ?? 0 );
				if ( (int) ( $relationship['relationship_type'] ?? 0 ) !== InverseRelationships::TYPE_CHILD || get_post_type( $child_id ) !== 'person' || get_post_status( $child_id ) !== 'publish' || ! Fields::try_get_for_post( $child_id, 'knvb_id' ) || Fields::try_get_for_post( $child_id, 'former_member' ) ) {
					continue;
				}
				foreach ( Fields::try_get_for_post( $child_id, 'relationships' ) ?: [] as $inverse ) {
					if ( (int) ( $inverse['related_person'] ?? 0 ) === $person_id && (int) ( $inverse['relationship_type'] ?? 0 ) === InverseRelationships::TYPE_PARENT ) {
						$child_ids[] = $child_id;
						break;
					}
				}
			}
			if ( $child_ids ) {
				$change['parent_sync'] = [
					'child_ids' => array_values( array_unique( $child_ids ) ),
					'kind'      => $is_phone ? 'phone' : 'email',
					'old'       => $old,
					'new'       => $new,
				];
			}
		}
		unset( $change );
		return $changes;
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
						'value'   => [ 'pending', 'failed', 'action_required' ],
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
				update_post_meta(
					$post->ID,
					'_rondo_profile_change_sync_status',
					$status === 'action_required' ? 'action_required' : 'failed'
				);
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
				$author  = get_userdata( (int) $post->post_author );
				$type    = (string) get_post_meta( $post->ID, '_rondo_profile_change_type', true );
				$source  = get_post_meta( $post->ID, '_rondo_profile_change_source', true ) ?: 'rondo';
				$changes = (array) get_post_meta( $post->ID, '_rondo_profile_change_changes', true );
				return [
					'id'           => (int) $post->ID,
					'created_at'   => get_post_time( DATE_ATOM, true, $post ),
					'type'         => $type,
					'source'       => $source,
					'source_label' => $source === 'sportlink' ? 'Sportlink/voetbal.nl' : 'Rondo',
					'label'        => get_the_title( $post ),
					'actor'        => $author ? $author->display_name : 'Onbekend account',
					'changes'      => $changes,
					'verified'     => get_post_meta( $post->ID, '_rondo_profile_change_verified', true ) === '1',
					'sync_status'  => $type === 'photo' ? self::photo_status( $changes, $source ) : (string) get_post_meta( $post->ID, '_rondo_profile_change_sync_status', true ),
					'sync_errors'  => (array) get_post_meta( $post->ID, '_rondo_profile_change_sync_errors', true ),
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
			'photo'           => 'Profielfoto gewijzigd',
			default           => 'Profielgegevens gewijzigd',
		};
	}
}
