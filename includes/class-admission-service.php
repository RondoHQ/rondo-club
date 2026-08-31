<?php
/**
 * Anonymous admission registration and access-event snapshots.
 *
 * @package Rondo\Access
 */

namespace Rondo\Access;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores accepted admissions. Regular member admissions remain anonymous;
 * guest admissions retain their host and guest label for 30 days.
 */
class AdmissionService {

	private const FINGERPRINT_SECRET_OPTION = 'rondo_access_fingerprint_secret';
	private const CLEANUP_HOOK              = 'rondo_access_cleanup_fingerprints';
	private const FINGERPRINT_RETENTION     = 30 * DAY_IN_SECONDS;
	private const LOCK_META_KEY             = '_rondo_access_lock_key';
	private const FINGERPRINT_ACTIVE_META   = '_rondo_access_fingerprint_active';

	public const PASS_TYPES = [
		'bondslid'       => 'Bondslid',
		'verenigingslid' => 'Verenigingslid',
		'businessclub'   => 'Businessclub',
		'awc_sponsor'    => 'AWC-sponsor',
		'guest'          => 'Gast',
	];

	/** Register daily privacy cleanup. */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}

		add_action( 'init', [ $this, 'schedule_cleanup' ] );
		add_action( self::CLEANUP_HOOK, [ $this, 'cleanup_fingerprints' ] );
	}

	/** Schedule deletion of event-scoped duplicate-detection fingerprints. */
	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Create or update the private event snapshot for a Sportlink match.
	 *
	 * @param array<string,mixed> $match Normalized Sportlink match.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function select_match( array $match ) {
		$source_id = sanitize_text_field( (string) ( $match['id'] ?? '' ) );
		if ( $source_id === '' || ( $match['club_side'] ?? '' ) !== 'home' ) {
			return new \WP_Error( 'rondo_access_match_invalid', __( 'Deze wedstrijd kan niet voor toegangsregistratie worden gebruikt.', 'rondo' ), [ 'status' => 422 ] );
		}
		if ( ! empty( $match['cancelled'] ) ) {
			return new \WP_Error( 'rondo_access_match_cancelled', __( 'Voor een afgelaste wedstrijd kan geen toegangsregistratie worden gestart.', 'rondo' ), [ 'status' => 422 ] );
		}

		$event_id      = $this->find_event_by_source_id( $source_id );
		$mapping_key   = 'rondo_access_event_' . substr( hash( 'sha256', $source_id ), 0, 40 );
		$mapping_value = get_option( $mapping_key, false );
		$mapped_id     = is_numeric( $mapping_value ) ? (int) $mapping_value : 0;
		if ( $event_id <= 0 && $mapped_id > 0 && get_post_type( $mapped_id ) === 'rondo_access_event' ) {
			$event_id = $mapped_id;
		}
		if ( $event_id <= 0 && $mapping_value !== false ) {
			$is_pending       = is_string( $mapping_value ) && str_starts_with( $mapping_value, 'pending:' );
			$pending_started  = $is_pending ? (int) substr( $mapping_value, strlen( 'pending:' ) ) : 0;
			$is_stale_pending = $pending_started > 0 && $pending_started < time() - 60;
			if ( $is_stale_pending || ! $is_pending ) {
				delete_option( $mapping_key );
			}
		}

		$created = false;
		if ( $event_id <= 0 ) {
			if ( ! add_option( $mapping_key, 'pending:' . time(), '', false ) ) {
				$event_id = $this->find_event_by_source_id( $source_id );
				if ( $event_id <= 0 ) {
					return new \WP_Error( 'rondo_access_match_busy', __( 'De wedstrijd wordt al geopend. Probeer het nogmaals.', 'rondo' ), [ 'status' => 409 ] );
				}
			} else {
				$event_id = wp_insert_post(
					[
						'post_type'   => 'rondo_access_event',
						'post_status' => 'publish',
						'post_title'  => $this->match_title( $match ),
						'post_author' => 0,
					],
					true
				);
				if ( is_wp_error( $event_id ) ) {
					delete_option( $mapping_key );
					return $event_id;
				}
				$created = true;
			}
		}

		wp_update_post(
			[
				'ID'         => $event_id,
				'post_title' => $this->match_title( $match ),
			]
		);

		$updated = Fields::update_many_for_post(
			$event_id,
			[
				'source_id'        => $source_id,
				'starts_at'        => (string) ( $match['starts_at'] ?? '' ),
				'home_team'        => sanitize_text_field( (string) ( $match['home_team'] ?? '' ) ),
				'away_team'        => sanitize_text_field( (string) ( $match['away_team'] ?? '' ) ),
				'pitch'            => sanitize_text_field( (string) ( $match['pitch'] ?? '' ) ),
				'location'         => sanitize_text_field( (string) ( $match['location'] ?? '' ) ),
				'sportlink_status' => sanitize_text_field( (string) ( $match['status'] ?? '' ) ),
				'cancelled'        => ! empty( $match['cancelled'] ),
			]
		);

		if ( is_wp_error( $updated ) ) {
			if ( $created ) {
				wp_delete_post( $event_id, true );
				delete_option( $mapping_key );
			}
			return $updated;
		}

		update_option( $mapping_key, $event_id, false );

		return $this->format_event( $event_id );
	}

	/**
	 * Record one accepted person once per event.
	 *
	 * The fingerprint is an event-scoped HMAC. The attendee post ID is never
	 * persisted in either the admission post or its metadata.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function record_admission( int $event_id, int $person_id, string $pass_type ) {
		if ( get_post_type( $event_id ) !== 'rondo_access_event' ) {
			return new \WP_Error( 'rondo_access_event_not_found', __( 'Toegangsevenement niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( ! isset( self::PASS_TYPES[ $pass_type ] ) ) {
			return new \WP_Error( 'rondo_access_pass_type_invalid', __( 'Onbekend pastype.', 'rondo' ), [ 'status' => 422 ] );
		}

		$fingerprint = hash_hmac( 'sha256', 'person|' . $event_id . '|' . $person_id, $this->fingerprint_secret() );
		return $this->record_with_fingerprint( $event_id, $pass_type, $fingerprint );
	}

	/** Record one stable guest slot once per event. */
	public function record_guest_admission( int $event_id, int $guest_pass_id, int $host_person_id, int $slot, string $guest_name ) {
		if ( get_post_type( $event_id ) !== 'rondo_access_event' ) {
			return new \WP_Error( 'rondo_access_event_not_found', __( 'Toegangsevenement niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( get_post_type( $guest_pass_id ) !== 'rondo_guest_pass' || $host_person_id <= 0 || $slot < 1 || $slot > 2 ) {
			return new \WP_Error( 'rondo_guest_admission_invalid', __( 'Ongeldige gastpasregistratie.', 'rondo' ), [ 'status' => 422 ] );
		}

		$fingerprint = hash_hmac( 'sha256', 'guest|' . $event_id . '|' . $host_person_id . '|' . $slot, $this->fingerprint_secret() );
		return $this->record_with_fingerprint(
			$event_id,
			'guest',
			$fingerprint,
			[
				'guest_pass_id'  => $guest_pass_id,
				'host_person_id' => $host_person_id,
				'guest_slot'     => $slot,
				'guest_name'     => sanitize_text_field( $guest_name ),
			]
		);
	}

	/** Persist an admission behind one atomic event-scoped lock. */
	private function record_with_fingerprint( int $event_id, string $pass_type, string $fingerprint, array $extra_fields = [] ) {
		$lock_key   = 'rondo_admission_once_' . $event_id . '_' . substr( $fingerprint, 0, 40 );
		$lock_value = get_option( $lock_key, false );

		if ( $lock_value !== false ) {
			$existing = $this->duplicate_result( $lock_value, $pass_type );
			if ( $existing !== null ) {
				return $existing;
			}

			delete_option( $lock_key );
		}

		if ( ! add_option( $lock_key, 'pending:' . time(), '', false ) ) {
			return $this->duplicate_result( get_option( $lock_key, false ), $pass_type ) ?? [
				'counted'    => false,
				'duplicate'  => true,
				'pass_type'  => $pass_type,
				'scanned_at' => null,
			];
		}

		$scanned_at   = wp_date( DATE_RFC3339 );
		$admission_id = wp_insert_post(
			[
				'post_type'   => 'rondo_admission',
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Toegang %d %s', $event_id, $scanned_at ),
				'post_author' => 0,
			],
			true
		);

		if ( is_wp_error( $admission_id ) ) {
			delete_option( $lock_key );
			return $admission_id;
		}

		$updated = Fields::update_many_for_post(
			$admission_id,
			array_merge(
				[
					'event_id'   => $event_id,
					'pass_type'  => $pass_type,
					'scanned_at' => $scanned_at,
				],
				$extra_fields
			)
		);
		if ( is_wp_error( $updated ) ) {
			wp_delete_post( $admission_id, true );
			delete_option( $lock_key );
			return $updated;
		}

		update_post_meta( $admission_id, self::LOCK_META_KEY, $lock_key );
		update_post_meta( $admission_id, self::FINGERPRINT_ACTIVE_META, 1 );
		update_option( $lock_key, $admission_id, false );

		return [
			'id'         => $admission_id,
			'counted'    => true,
			'duplicate'  => false,
			'pass_type'  => $pass_type,
			'scanned_at' => $scanned_at,
		];
	}

	/** Return permanent aggregate counts without attendee data. */
	public function get_stats( int $event_id ): array {
		$counts = array_fill_keys( array_keys( self::PASS_TYPES ), 0 );
		$ids    = get_posts(
			[
				'post_type'      => 'rondo_admission',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					[
						'key'     => 'event_id',
						'value'   => $event_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);

		foreach ( $ids as $admission_id ) {
			$pass_type = (string) Fields::get_for_post( (int) $admission_id, 'pass_type' );
			if ( isset( $counts[ $pass_type ] ) ) {
				++$counts[ $pass_type ];
			}
		}

		$breakdown = [];
		foreach ( self::PASS_TYPES as $type => $label ) {
			$breakdown[] = [
				'type'  => $type,
				'label' => $label,
				'count' => $counts[ $type ],
			];
		}

		return [
			'event_id'  => $event_id,
			'total'     => array_sum( $counts ),
			'counts'    => $counts,
			'breakdown' => $breakdown,
		];
	}

	/** Format one event for scanner clients. */
	public function format_event( int $event_id ): array {
		$post = get_post( $event_id );
		if ( ! $post || $post->post_type !== 'rondo_access_event' ) {
			return [];
		}

		return [
			'id'               => $event_id,
			'source_id'        => (string) Fields::get_for_post( $event_id, 'source_id' ),
			'title'            => get_the_title( $event_id ),
			'starts_at'        => Fields::get_for_post( $event_id, 'starts_at' ),
			'home_team'        => (string) Fields::get_for_post( $event_id, 'home_team' ),
			'away_team'        => (string) Fields::get_for_post( $event_id, 'away_team' ),
			'pitch'            => (string) Fields::get_for_post( $event_id, 'pitch' ),
			'location'         => (string) Fields::get_for_post( $event_id, 'location' ),
			'sportlink_status' => (string) Fields::get_for_post( $event_id, 'sportlink_status' ),
			'cancelled'        => (bool) Fields::get_for_post( $event_id, 'cancelled' ),
		];
	}

	/** Remove only duplicate-detection keys after 30 days; aggregate posts remain. */
	public function cleanup_fingerprints(): void {
		$admission_ids = get_posts(
			[
				'post_type'      => 'rondo_admission',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => [
					[
						'before'    => gmdate( 'Y-m-d H:i:s', time() - self::FINGERPRINT_RETENTION ),
						'inclusive' => true,
						'column'    => 'post_date_gmt',
					],
				],
				'meta_query'     => [
					[
						'key'   => self::FINGERPRINT_ACTIVE_META,
						'value' => 1,
					],
				],
			]
		);

		foreach ( $admission_ids as $admission_id ) {
			$lock_key = (string) get_post_meta( (int) $admission_id, self::LOCK_META_KEY, true );
			if ( $lock_key !== '' ) {
				delete_option( $lock_key );
			}
			delete_post_meta( (int) $admission_id, self::LOCK_META_KEY );
			delete_post_meta( (int) $admission_id, self::FINGERPRINT_ACTIVE_META );

			if ( Fields::get_for_post( (int) $admission_id, 'pass_type' ) === 'guest' ) {
				Fields::delete_for_post( (int) $admission_id, 'guest_pass_id' );
				Fields::delete_for_post( (int) $admission_id, 'host_person_id' );
				Fields::delete_for_post( (int) $admission_id, 'guest_slot' );
				Fields::delete_for_post( (int) $admission_id, 'guest_name' );
			}
		}
	}

	private function find_event_by_source_id( string $source_id ): int {
		$ids = get_posts(
			[
				'post_type'      => 'rondo_access_event',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => 'source_id',
				'meta_value'     => $source_id,
			]
		);

		return $ids ? (int) $ids[0] : 0;
	}

	private function duplicate_result( $lock_value, string $fallback_pass_type ): ?array {
		if ( is_numeric( $lock_value ) ) {
			$admission_id = (int) $lock_value;
			if ( get_post_type( $admission_id ) === 'rondo_admission' ) {
				return [
					'id'         => $admission_id,
					'counted'    => false,
					'duplicate'  => true,
					'pass_type'  => (string) Fields::get_for_post( $admission_id, 'pass_type' ),
					'scanned_at' => Fields::get_for_post( $admission_id, 'scanned_at' ),
				];
			}
		}

		if ( is_string( $lock_value ) && str_starts_with( $lock_value, 'pending:' ) ) {
			$created_at = (int) substr( $lock_value, strlen( 'pending:' ) );
			if ( $created_at > 0 && $created_at >= time() - 60 ) {
				return [
					'counted'    => false,
					'duplicate'  => true,
					'pass_type'  => $fallback_pass_type,
					'scanned_at' => null,
				];
			}
		}

		return null;
	}

	private function fingerprint_secret(): string {
		$secret = (string) get_option( self::FINGERPRINT_SECRET_OPTION, '' );
		if ( strlen( $secret ) >= 32 ) {
			return $secret;
		}

		$new_secret = wp_generate_password( 64, true, true );
		if ( add_option( self::FINGERPRINT_SECRET_OPTION, $new_secret, '', false ) ) {
			return $new_secret;
		}

		return (string) get_option( self::FINGERPRINT_SECRET_OPTION, $new_secret );
	}

	/** @param array<string,mixed> $match Match data. */
	private function match_title( array $match ): string {
		return sanitize_text_field( trim( (string) ( $match['home_team'] ?? '' ) . ' – ' . (string) ( $match['away_team'] ?? '' ) ) );
	}
}
