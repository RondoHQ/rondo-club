<?php
/** Private VOG submissions and their lifecycle. */
namespace Rondo\VOG;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VogSubmissions {
	const TYPE   = 'rondo_vog_submission';
	const META   = '_rondo_vog_submission';
	const RULES  = 'rondo_vog_approval_rules';
	const ACTIVE = [ 'checking', 'technical', 'review', 'needs_original', 'waiting_paper' ];

	public function __construct() {
		add_action( 'init', [ self::class, 'register' ] );
		add_action( 'rondo_vog_retry', [ self::class, 'process' ] );
		add_action( 'rondo_vog_cleanup', [ self::class, 'cleanup' ] );
		add_action( 'before_delete_post', [ self::class, 'delete_person' ] );
		if ( did_action( 'init' ) ) {
			self::register();
		}
	}

	public static function register(): void {
		register_post_type(
			self::TYPE,
			[
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'rewrite'      => false,
				'supports'     => [],
				'can_export'   => false,
				'map_meta_cap' => false,
				'capabilities' => array_fill_keys( [ 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts', 'read' ], 'do_not_allow' ),
			]
			);
		if ( ! wp_next_scheduled( 'rondo_vog_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'rondo_vog_cleanup' );
		}
	}

	public static function directory(): string {
		$directory = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'rondo-private/vog';
		if ( ! wp_mkdir_p( $directory ) ) {
			throw new \RuntimeException( 'Private VOG-opslag is niet beschikbaar.' );
		}
		chmod( $directory, 0700 );
		return $directory;
	}

	/** A kernel lock cannot expire while its owner still writes. */
	public static function locked( int $person_id, callable $callback ) {
		$lock = fopen( self::directory() . '/lock-' . $person_id, 'c' );
		if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( $lock ) {
				fclose( $lock );
			}
			return new \WP_Error( 'vog_busy', 'Deze VOG wordt al verwerkt. Probeer het zo opnieuw.', [ 'status' => 409 ] );
		}
		try {
			return $callback();
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	public static function eligible( int $person_id, int $user_id ): bool {
		return $user_id > 0 && get_user_by( 'id', $user_id ) && get_post_type( $person_id ) === 'person'
			&& get_post_status( $person_id ) === 'publish'
			&& AccessControl::can_view_person( $person_id, $user_id )
			&& (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) === $person_id
			&& ! Fields::get_for_post( $person_id, 'former_member' )
			&& ! get_option( 'rondo_is_demo_site', false );
	}

	public static function get( int $id ): array {
		if ( get_post_type( $id ) !== self::TYPE ) {
			return [];
		}
		$data = get_post_meta( $id, self::META, true );
		return is_array( $data ) ? $data : [];
	}

	public static function latest_id( int $person_id ): int {
		$ids = get_posts(
			[
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_parent' => $person_id,
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
				'fields'      => 'ids',
			]
			);
		return (int) ( $ids[0] ?? 0 );
	}

	public static function save( int $id, array $data ): void {
		$data['version'] = ( $data['version'] ?? 0 ) + 1;
		update_post_meta( $id, self::META, $data );
		if ( get_post_meta( $id, self::META, true ) !== $data ) {
			throw new \RuntimeException( 'De VOG kon niet worden opgeslagen.' );
		}
		update_post_meta( $id, '_rondo_vog_active', in_array( $data['status'], self::ACTIVE, true ) ? '1' : '0' );
		update_post_meta( $id, '_rondo_vog_expires', $data['expires'] );
	}

	public static function path( array $file ): string {
		$name = $file['name'] ?? '';
		return preg_match( '/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $name ) ? self::directory() . '/' . $name : '';
	}

	/** Caller owns the person lock and has already validated all file contents. */
	public static function create( int $person_id, int $user_id, string $source, array $files, array $parsed ): int {
		$id = wp_insert_post(
			[
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_parent' => $person_id,
				'post_author' => $user_id,
				'post_title'  => 'VOG inzending',
			],
			true
			);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( 'De VOG kon niet worden opgeslagen.' );
		}
		self::save(
			$id,
			[
				'person_id' => $person_id,
				'user_id'   => $user_id,
				'source'    => $source,
				'status'    => $source === 'digital' ? 'checking' : ( $source === 'paper' ? 'waiting_paper' : 'needs_original' ),
				'files'     => $files,
				'parsed'    => $parsed,
				'code'      => null,
				'attempts'  => 0,
				'version'   => 0,
				'expires'   => time() + 30 * DAY_IN_SECONDS,
				'reason'    => [],
				'note'      => '',
				'method'    => '',
			]
			);
		$older = get_posts(
			[
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_parent' => $person_id,
				'numberposts' => -1,
				'fields'      => 'ids',
				'exclude'     => [ $id ],
				'meta_key'    => '_rondo_vog_active',
				'meta_value'  => '1',
			]
			);
		foreach ( $older as $old_id ) {
			self::finish( $old_id, self::get( $old_id ), 'replaced' );
		}
		return $id;
	}

	/** Delete all source bytes and extracted identities, preserve a minimal receipt. */
	public static function finish( int $id, array $data, string $status ): void {
		foreach ( $data['files'] ?? [] as $file ) {
			$path = self::path( $file );
			if ( $path && is_file( $path ) && ! unlink( $path ) ) {
				throw new \RuntimeException( 'Het tijdelijke VOG-bestand kon niet worden verwijderd.' );
			}
		}
		$data['hashes']      = array_column( $data['files'] ?? [], 'sha256' );
		$data['files']       = [];
		$data['parsed']      = [];
		$data['reason']      = [];
		$data['status']      = $status;
		$data['finished_at'] = gmdate( 'c' );
		wp_clear_scheduled_hook( 'rondo_vog_retry', [ $id ] );
		self::save( $id, $data );
	}

	public static function process( int $id ): void {
		$data = self::get( $id );
		if ( ! $data ) {
			return;
		}
		self::locked(
			$data['person_id'],
			static function () use ( $id ) {
				$data = self::get( $id );
				if ( ! in_array( $data['status'], [ 'checking', 'technical' ], true ) || $data['source'] !== 'digital' || $data['attempts'] >= 5 ) {
					return;
				}
				if ( $data['expires'] <= time() || ! self::eligible( $data['person_id'], $data['user_id'] ) || self::latest_id( $data['person_id'] ) !== $id ) {
					self::finish( $id, $data, 'expired' );
					return;
				}
				$file = $data['files'][0];
				$path = self::path( $file );
				if ( ! is_readable( $path ) || hash_file( 'sha256', $path ) !== $file['sha256'] ) {
					$data['status'] = 'needs_original';
					self::save( $id, $data );
					return;
				}
				wp_clear_scheduled_hook( 'rondo_vog_retry', [ $id ] );
				++$data['attempts'];
				// Persist the attempt and a recovery event before external work.
				self::save( $id, $data );
				$data = self::get( $id );
				if ( $data['attempts'] < 3 ) {
					wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'rondo_vog_retry', [ $id ] );
				}
				$data['code']       = VogDocument::validate( $path );
				$data['checked_at'] = gmdate( 'c' );
				$data['status']     = 'technical';
				wp_clear_scheduled_hook( 'rondo_vog_retry', [ $id ] );
				if ( $data['code'] === 0 ) {
					$read                 = VogDocument::read( $path );
					$data['parsed']       = VogDocument::parse( $read['text'] ?? '' );
					$rules                = get_option( self::RULES, [] );
					$data['reason']       = VogDocument::reasons( $data['parsed'], $data['person_id'], $rules );
					$data['rule_version'] = hash( 'sha256', wp_json_encode( $rules ) );
					$data['status']       = 'review';
					self::save( $id, $data );
					$data = self::get( $id );
					if ( ! $data['reason'] && self::eligible( $data['person_id'], $data['user_id'] ) ) {
						$result = self::approve( $id, $data, $data['parsed']['date'], 'gaav_auto', 0 );
						if ( ! is_wp_error( $result ) ) {
							return;
						}
						$data['reason'][] = $result->get_error_message();
					}
				} elseif ( in_array( $data['code'], [ 1, 2, 6 ], true ) ) {
					$data['status'] = 'needs_original';
				} elseif ( $data['attempts'] < 3 ) {
					wp_schedule_single_event( time() + ( $data['attempts'] === 1 ? 5 : 30 ) * MINUTE_IN_SECONDS, 'rondo_vog_retry', [ $id ] );
				}
				self::save( $id, $data );
			}
			);
	}

	/** Caller holds the person lock; no date can go backwards. */
	public static function approve( int $id, array $data, string $date, string $method, int $reviewer ) {
		if ( ! VogDocument::valid_date( $date ) ) {
			return new \WP_Error( 'vog_date', 'Kies een geldige afgiftedatum binnen de clubtermijn.', [ 'status' => 400 ] );
		}
		if ( $method !== 'paper_original' ) {
			$file = $data['files'][0] ?? [];
			$path = self::path( $file );
			if ( $data['code'] !== 0 || ! is_readable( $path ) || hash_file( 'sha256', $path ) !== ( $file['sha256'] ?? '' ) ) {
				return new \WP_Error( 'vog_original_changed', 'Het originele bestand kan niet meer worden bevestigd. Lever het opnieuw in.', [ 'status' => 409 ] );
			}
		}
		$old = (string) Fields::get_for_post( $data['person_id'], 'datum_vog' );
		if ( preg_replace( '/\D/', '', $old ) > str_replace( '-', '', $date ) ) {
			return new \WP_Error( 'vog_older', 'Er staat al een nieuwere VOG geregistreerd.', [ 'status' => 409 ] );
		}
		$result = Fields::update_many_for_post( $data['person_id'], [ 'datum_vog' => $date ] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// Reverse sync polls modified_after, so a field-only write must touch the post.
		$updated = wp_update_post( [ 'ID' => $data['person_id'] ], true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		$data['approved_date'] = $date;
		$data['method']        = $method;
		$data['reviewer']      = $reviewer;
		$data['reviewed_at']   = gmdate( 'c' );
		self::finish( $id, $data, 'approved' );
		return true;
	}

	public static function payload( int $id, bool $reviewer = false ): ?array {
		$data = self::get( $id );
		if ( ! $data ) {
			return null;
		}
		$expired = $data['expires'] <= time() && in_array( $data['status'], self::ACTIVE, true );
		$out     = [
			'id'            => $id,
			'status'        => $expired ? 'expired' : $data['status'],
			'version'       => $data['version'],
			'source'        => $data['source'],
			'method'        => $data['method'],
			'note'          => $data['note'],
			'expires_at'    => gmdate( 'c', $data['expires'] ),
			'approved_date' => $data['approved_date'] ?? '',
			'attempts'      => $data['attempts'],
		];
		if ( $reviewer ) {
			$out += [
				'person_id' => $data['person_id'],
				'name'      => get_the_title( $data['person_id'] ),
				'code'      => $data['code'],
				'parsed'    => $expired ? [] : $data['parsed'],
				'reasons'   => $data['reason'],
				'files'     => $expired ? [] : array_map( static fn( $f ) => [ 'type' => $f['type'] ], $data['files'] ),
			];
		}
		return $out;
	}

	public static function cleanup(): void {
		$ids = get_posts(
			[
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'numberposts' => 100,
				'fields'      => 'ids',
				'meta_query'  => [
					[
						'key'   => '_rondo_vog_active',
						'value' => '1',
					],
					[
						'key'     => '_rondo_vog_expires',
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					],
				],
			]
			);
		foreach ( $ids as $id ) {
			$data = self::get( $id );
			self::locked(
				$data['person_id'],
				static function () use ( $id ) {
					$data = self::get( $id );
					if ( $data && $data['expires'] <= time() && in_array( $data['status'], self::ACTIVE, true ) ) {
						self::finish( $id, $data, 'expired' );
					}
				}
				);
		}
		// A terminated upload may leave files before the private post exists.
		foreach ( glob( self::directory() . '/*.*' ) ?: [] as $path ) {
			if ( preg_match( '/^[a-f0-9]{32}\.(pdf|jpg|png)$/', basename( $path ) ) && filemtime( $path ) < time() - 31 * DAY_IN_SECONDS ) {
				unlink( $path );
			}
		}
	}

	public static function delete_person( int $id ): void {
		if ( get_post_type( $id ) === self::TYPE ) {
			$data = self::get( $id );
			if ( $data ) {
				self::finish( $id, $data, 'expired' );
			}
			return;
		}
		if ( get_post_type( $id ) !== 'person' ) {
			return;
		}
		foreach ( get_posts(
			[
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_parent' => $id,
				'numberposts' => -1,
				'fields'      => 'ids',
			]
			) as $submission_id ) {
			wp_delete_post( $submission_id, true );
		}
	}
}
