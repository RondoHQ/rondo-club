<?php
/**
 * Durable, revision-bound photo jobs for Sportlink.
 */

namespace Rondo\People;

use Rondo\Fields\Fields;

class PhotoSync {
	public const META       = '_rondo_photo_sync';
	public const STATE_META = '_rondo_photo_sync_state';

	private static function save_job( int $person_id, array $job ): void {
		update_post_meta( $person_id, self::META, $job );
		update_post_meta( $person_id, self::STATE_META, $job['state'] );
	}

	/** Paginate the native pending index without exposing tokens or image bytes. */
	public static function pending( int $page = 1, int $per_page = 50 ): array {
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => min( 100, max( 1, $per_page ) ),
				'paged'          => max( 1, $page ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => self::STATE_META,
						'value' => 'pending',
					],
				],
			]
		);
		$jobs  = [];
		foreach ( $query->posts as $id ) {
			$job = self::job( (int) $id );
			if ( ! is_wp_error( $job ) && $job['state'] === 'pending' ) {
				$jobs[] = $job;
			}
		}
		return [
			'jobs'      => $jobs,
			'next_page' => $page < $query->max_num_pages ? $page + 1 : null,
			'window'    => self::window(),
		];
	}

	/** Date boundaries always follow the club's Dutch calendar. */
	public static function window( ?\DateTimeImmutable $now = null ): array {
		$now   = ( $now ?? new \DateTimeImmutable( 'now' ) )->setTimezone( new \DateTimeZone( 'Europe/Amsterdam' ) );
		$month = (int) $now->format( 'n' );
		$year  = (int) $now->format( 'Y' ) + ( $month > 10 ? 1 : 0 );
		return [
			'open'       => $month >= 7 && $month <= 10,
			'next_start' => $year . '-07-01',
		];
	}

	public static function eligible( int $person_id ): bool {
		return get_post_type( $person_id ) === 'person'
			&& get_post_status( $person_id ) === 'publish'
			&& (bool) Fields::get_for_post( $person_id, 'knvb_id' )
			&& ! Fields::get_for_post( $person_id, 'former_member' )
			&& Fields::get_for_post( $person_id, 'person_type' ) !== 'contact';
	}

	/** Serialize photo writes. A crashed request fails closed until reviewed. */
	public static function locked( int $person_id, callable $callback ) {
		$key = 'rondo_photo_lock_' . $person_id;
		if ( ! add_option( $key, time(), '', false ) ) {
			return self::error( 'busy', 'De foto wordt al verwerkt. Probeer het later opnieuw.' );
		}
		try {
			return $callback();
		} finally {
			delete_option( $key );
		}
	}

	/** Called under the upload lock only for an explicit manual upload. */
	public static function queue( int $person_id, int $attachment_id ): void {
		$job = [
			'revision'      => wp_generate_uuid4(),
			'attachment_id' => $attachment_id,
			'knvb_id'       => (string) Fields::get_for_post( $person_id, 'knvb_id' ),
			'state'         => self::eligible( $person_id ) ? 'pending' : 'local_only',
			'created_at'    => gmdate( 'c' ),
		];
		self::save_job( $person_id, $job );
	}

	public static function status( int $person_id ): ?array {
		$job = get_post_meta( $person_id, self::META, true );
		if ( ! is_array( $job ) ) {
			return null;
		}
		$window = self::window();
		$state  = $job['state'];
		if ( $state === 'pending' && ! self::eligible( $person_id ) ) {
			$state = 'local_only';
		} elseif ( $state === 'pending' && ! $window['open'] ) {
			$state = 'waiting_window';
		}
		$messages = [
			'pending'        => 'Foto opgeslagen in Rondo; wordt bij de volgende synchronisatie naar Sportlink verstuurd.',
			'waiting_window' => 'Foto opgeslagen in Rondo; versturen naar Sportlink kan vanaf 1 juli ' . substr( $window['next_start'], 0, 4 ) . '.',
			'sending'        => 'Verzending naar Sportlink gestart; bevestiging wordt gecontroleerd.',
			'review'         => 'Foto opgeslagen in Rondo; controle van de verzending naar Sportlink is nodig.',
			'synced'         => 'Foto bevestigd in Sportlink.',
			'local_only'     => 'Foto opgeslagen in Rondo; dit profiel wordt niet naar Sportlink gesynchroniseerd.',
		];
		return [
			'state'       => $state,
			'message'     => $messages[ $state ],
			'window_open' => $window['open'],
		];
	}

	/** Rondo remains authoritative for manually uploaded photos, including confirmed crops. */
	public static function protects_photo( int $person_id ): bool {
		return is_array( get_post_meta( $person_id, self::META, true ) );
	}

	public static function error( string $code, string $message ): \WP_Error {
		return new \WP_Error( 'rondo_photo_' . $code, $message, [ 'status' => 409 ] );
	}

	/** Read a specific job, optionally preparing a bounded JPEG for the worker. */
	public static function job( int $person_id, bool $include_file = false ) {
		$job = get_post_meta( $person_id, self::META, true );
		if ( ! is_array( $job ) || ! self::eligible( $person_id )
			|| $job['knvb_id'] !== (string) Fields::get_for_post( $person_id, 'knvb_id' )
			|| (int) get_post_thumbnail_id( $person_id ) !== $job['attachment_id'] ) {
			return self::error( 'stale', 'Geen actuele foto-opdracht voor dit Sportlink-profiel.' );
		}
		unset( $job['claim_token'] );
		$job['person_id'] = $person_id;
		$job['window']    = self::window();
		if ( ! $include_file ) {
			return $job;
		}
		if ( $job['state'] !== 'pending' || ! $job['window']['open'] ) {
			return self::error( 'not_ready', 'De foto staat niet klaar voor verzending binnen de toegestane periode.' );
		}
		$editor = wp_get_image_editor( get_attached_file( $job['attachment_id'] ) );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$size = $editor->get_size();
		if ( $size['width'] > 1200 || $size['height'] > 1200 ) {
			$resized = $editor->resize( 1200, 1200, false );
			if ( is_wp_error( $resized ) ) {
				return $resized;
			}
		}
		$temp = wp_tempnam( 'sportlink-photo.jpg' );
		if ( ! $temp ) {
			return self::error( 'export', 'De foto kon niet worden voorbereid.' );
		}
		$saved = null;
		try {
			$saved = $editor->save( $temp, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$bytes = file_get_contents( $saved['path'] );
			if ( ! $bytes || strlen( $bytes ) > 5 * MB_IN_BYTES ) {
				return self::error( 'export', 'De voorbereide foto is leeg of groter dan 5 MB.' );
			}
			$job['file'] = [
				'mime_type' => 'image/jpeg',
				'sha256'    => hash( 'sha256', $bytes ),
				'base64'    => base64_encode( $bytes ),
			];
			return $job;
		} finally {
			wp_delete_file( $temp );
			if ( is_array( $saved ) && $saved['path'] !== $temp ) {
				wp_delete_file( $saved['path'] );
			}
		}
	}

	/** Claim once; uncertain uploads require review instead of automatic retries. */
	public static function transition( int $person_id, array $input ) {
		foreach ( [ 'action', 'revision', 'knvb_id', 'claim_token', 'sportlink_photo_date', 'verified_sha256' ] as $key ) {
			if ( isset( $input[ $key ] ) && ( ! is_string( $input[ $key ] ) || strlen( $input[ $key ] ) > 100 ) ) {
				return self::error( 'input', 'Ongeldige foto-opdracht.' );
			}
		}
		return self::locked(
			$person_id,
			static function () use ( $person_id, $input ) {
				$current = self::job( $person_id );
				if ( is_wp_error( $current ) ) {
					return $current;
				}
				$job = get_post_meta( $person_id, self::META, true );
				if ( ( $input['revision'] ?? '' ) !== $job['revision'] || ( $input['knvb_id'] ?? '' ) !== $job['knvb_id'] ) {
					return self::error( 'stale', 'De foto of het gekoppelde profiel is gewijzigd.' );
				}
				$action = $input['action'] ?? '';
				if ( $action === 'claim' ) {
					if ( ! self::window()['open'] || $job['state'] !== 'pending' ) {
						return self::error( 'not_ready', 'Verzenden kan alleen van 1 juli tot en met 31 oktober, voor een nog niet verzonden foto.' );
					}
					$job['state']       = 'sending';
					$job['claim_token'] = wp_generate_uuid4();
				} elseif ( in_array( $action, [ 'complete', 'review' ], true ) ) {
					if ( $job['state'] !== 'sending' || empty( $input['claim_token'] ) || ! hash_equals( $job['claim_token'], $input['claim_token'] ) ) {
						return self::error( 'claim', 'Deze bevestiging hoort niet bij de actieve verzending.' );
					}
					if ( $action === 'complete' && ( empty( $input['sportlink_photo_date'] ) || empty( $input['verified_sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $input['verified_sha256'] ) ) ) {
						return self::error( 'verification', 'De bevestiging van de opgeslagen Sportlink-foto ontbreekt.' );
					}
					$job['state'] = $action === 'complete' ? 'synced' : 'review';
					if ( $action === 'complete' ) {
						$job['sportlink_photo_date'] = sanitize_text_field( $input['sportlink_photo_date'] );
						$job['verified_sha256']      = $input['verified_sha256'];
					}
				} else {
					return self::error( 'action', 'Onbekende foto-actie.' );
				}
				$job['updated_at'] = gmdate( 'c' );
				self::save_job( $person_id, $job );
				return $job;
			}
		);
	}
}
