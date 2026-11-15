<?php
/** Resumable member-photo migration; copies are verified before public files are removed. */
namespace Rondo\Media;

final class MemberMediaMigration {
	public static function candidates(): array {
		$access = new MemberMedia( false );
		$access->references();
		$ids = get_posts(
			[
				'post_type'        => 'attachment',
				'post_status'      => [ 'inherit', 'publish', 'private', 'trash' ],
				'post_mime_type'   => 'image',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
			);
		return array_values( array_filter( $ids, fn( $id ) => ! get_post_meta( $id, '_rondo_private_iva_path', true ) && ( $access->people( (int) $id ) || get_post_meta( $id, MemberMedia::META, true ) ) ) );
	}

	/** Produce a bounded, exact file manifest from WordPress metadata and old derivatives. */
	public static function inventory( int $id ) {
		$marker = get_post_meta( $id, MemberMedia::META, true );
		if ( is_array( $marker ) ) {
			return $marker;
		}
		$file    = get_attached_file( $id );
		$uploads = wp_upload_dir();
		$root    = realpath( $uploads['basedir'] );
		$real    = is_string( $file ) ? realpath( $file ) : false;
		if ( ! $root || ! $real || is_link( $file ) || ! str_starts_with( $real, $root . '/' ) || ! wp_get_image_mime( $real ) ) {
			return new \WP_Error( 'member_media_source', 'Afbeelding ontbreekt of staat buiten de uploadmap.' );
		}
		$metadata = wp_get_attachment_metadata( $id ) ?: [];
		$names    = [ basename( $real ) ];
		if ( ! empty( $metadata['original_image'] ) ) {
			$names[] = $metadata['original_image'];
		}
		foreach ( array_merge( (array) ( $metadata['sizes'] ?? [] ), (array) get_post_meta( $id, '_wp_attachment_backup_sizes', true ) ) as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$names[] = $size['file'];
			}
		}
		// Include stale generated sizes left by older theme image-size settings.
		foreach ( array_unique( [ basename( $real ), $metadata['original_image'] ?? basename( $real ) ] ) as $original ) {
			$stem = pathinfo( $original, PATHINFO_FILENAME );
			foreach ( glob( dirname( $real ) . '/' . $stem . '-*' ) ?: [] as $candidate ) {
				if ( preg_match( '/^' . preg_quote( $stem, '/' ) . '-(?:\d+x\d+|scaled|rotated)(?:-\d+x\d+)?\.(?:jpe?g|png|gif|webp)$/i', basename( $candidate ) ) ) {
					$names[] = basename( $candidate );
				}
			}
		}
		$files = [];
		foreach ( array_unique( $names ) as $name ) {
			if ( ! is_string( $name ) || basename( $name ) !== $name ) {
				return new \WP_Error( 'member_media_path', 'Ongeldig afbeeldingspad in metadata.' );
			}
			$path = dirname( $real ) . '/' . $name;
			if ( ! file_exists( $path ) ) {
				continue;
			}
			if ( is_link( $path ) || ! is_file( $path ) || realpath( $path ) !== $path ) {
				return new \WP_Error( 'member_media_path', 'Afbeeldingspad is geen regulier bestand.' );
			}
			$files[] = [
				'source' => $path,
				'name'   => $name,
				'sha256' => hash_file( 'sha256', $path ),
			];
		}
		$paths  = array_column( $files, 'source' );
		$paths  = array_merge( $paths, array_map( '_wp_relative_upload_path', $paths ) );
		$shared = get_posts(
			[
				'post_type'        => 'attachment',
				'post_status'      => [ 'inherit', 'publish', 'private', 'trash' ],
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'post__not_in'     => [ $id ],
				'meta_query'       => [
					[
						'key'     => '_wp_attached_file',
						'value'   => $paths,
						'compare' => 'IN',
					],
				],
			]
			);
		if ( $shared ) {
			return new \WP_Error( 'member_media_shared_file', 'Een afgeleid bestand wordt door een ander mediarecord gebruikt.' );
		}
		return [
			'version'                => 1,
			'directory'              => '',
			'main'                   => basename( $real ),
			'files'                  => $files,
			'original_metadata'      => $metadata,
			'original_attached_file' => get_post_meta( $id, '_wp_attached_file', true ),
		];
	}

	public static function migrate( int $id ) {
		if ( ! wp_mkdir_p( MemberMedia::directory() ) ) {
			return new \WP_Error( 'member_media_directory', 'Private opslag kon niet worden aangemaakt.' );
		}
		chmod( MemberMedia::directory(), 0700 );
		$lock = fopen( MemberMedia::directory() . '/.migration-' . $id . '.lock', 'c' );
		if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( $lock ) {
				fclose( $lock );
			}
			return new \WP_Error( 'member_media_locked', 'Deze afbeelding wordt al verwerkt.' );
		}
		try {
			$manifest = self::inventory( $id );
			if ( is_wp_error( $manifest ) ) {
				return $manifest;
			}
			if ( empty( $manifest['directory'] ) ) {
				$directory = MemberMedia::directory() . '/' . bin2hex( random_bytes( 16 ) );
				if ( ! wp_mkdir_p( $directory ) ) {
					return new \WP_Error( 'member_media_directory', 'Private opslag kon niet worden aangemaakt.' );
				}
				chmod( $directory, 0700 );
				foreach ( $manifest['files'] as $file ) {
					$target = $directory . '/' . $file['name'];
					if ( ! copy( $file['source'], $target ) || ! hash_equals( $file['sha256'], hash_file( 'sha256', $target ) ) ) {
						return new \WP_Error( 'member_media_copy', 'Private kopie kon niet worden geverifieerd; bron behouden.' );
					}
					chmod( $target, 0600 );
				}
				$manifest['directory'] = $directory;
				update_post_meta( $id, MemberMedia::META, $manifest );
			}
			foreach ( $manifest['files'] as $file ) {
				$target = $manifest['directory'] . '/' . $file['name'];
				if ( ! is_file( $target ) || is_link( $target ) || ! hash_equals( $file['sha256'], hash_file( 'sha256', $target ) ) ) {
					return new \WP_Error( 'member_media_copy', 'Private kopie kon niet worden geverifieerd; bron behouden.' );
				}
			}
			$main = $manifest['directory'] . '/' . $manifest['main'];
			$root = realpath( MemberMedia::directory() );
			if ( ! $root || ! str_starts_with( (string) realpath( $main ), $root . '/' ) ) {
				return new \WP_Error( 'member_media_target', 'Ongeldige private opslag.' );
			}
			if ( get_attached_file( $id ) !== $main ) {
				update_attached_file( $id, $main );
				$metadata         = $manifest['original_metadata'];
				$metadata['file'] = $main;
				wp_update_attachment_metadata( $id, $metadata );
			}
			wp_update_post(
				[
					'ID'   => $id,
					'guid' => MemberMedia::delivery_url( $id ),
				]
				);
			if ( get_attached_file( $id ) !== $main || ! MemberMedia::file( $id ) ) {
				return new \WP_Error( 'member_media_reference', 'Private bestandsverwijzing is niet opgeslagen; bron behouden.' );
			}
			$public_root = realpath( wp_upload_dir()['basedir'] );
			foreach ( $manifest['files'] as $file ) {
				if ( ! file_exists( $file['source'] ) ) {
					continue;
				}
				$target = $manifest['directory'] . '/' . $file['name'];
				if ( ! $public_root || is_link( $file['source'] ) || ! str_starts_with( (string) realpath( $file['source'] ), $public_root . '/' ) || ! is_file( $target ) || ! hash_equals( $file['sha256'], hash_file( 'sha256', $target ) ) || ! hash_equals( $file['sha256'], hash_file( 'sha256', $file['source'] ) ) ) {
					return new \WP_Error( 'member_media_changed', 'Bestand is gewijzigd; publieke kopie niet verwijderd.' );
				}
				if ( ! unlink( $file['source'] ) ) {
					return new \WP_Error( 'member_media_cleanup', 'Publieke kopie kon niet worden verwijderd.' );
				}
			}
			return true;
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/** Verify every copied original and derivative, independently of the write. */
	public static function verify( int $id ) {
		$manifest = get_post_meta( $id, MemberMedia::META, true );
		if ( ! is_array( $manifest ) || ! MemberMedia::file( $id ) ) {
			return new \WP_Error( 'member_media_missing', 'Private afbeelding ontbreekt.' );
		}
		foreach ( $manifest['files'] as $file ) {
			$target = $manifest['directory'] . '/' . $file['name'];
			if ( ! is_file( $target ) || is_link( $target ) || ! hash_equals( $file['sha256'], hash_file( 'sha256', $target ) ) ) {
				return new \WP_Error( 'member_media_checksum', 'Private kopie wijkt af.' );
			}
			if ( file_exists( $file['source'] ) || is_link( $file['source'] ) ) {
				return new \WP_Error( 'member_media_public', 'Publieke kopie bestaat nog.' );
			}
		}
		foreach ( ( wp_get_attachment_metadata( $id )['sizes'] ?? [] ) as $size => $details ) {
			if ( ! MemberMedia::file( $id, $size ) ) {
				return new \WP_Error( 'member_media_derivative', 'Private thumbnail ontbreekt.' );
			}
		}
		return true;
	}

	/** New member uploads never pass through a publicly served directory. */
	public static function upload( array $file, int $person_id ) {
		$directory = MemberMedia::directory() . '/' . bin2hex( random_bytes( 16 ) );
		if ( ! wp_mkdir_p( $directory ) ) {
			return new \WP_Error( 'member_media_directory', 'Private opslag kon niet worden aangemaakt.' );
		}
		chmod( $directory, 0700 );
		$filter = static fn( $uploads ) => array_merge(
			$uploads,
			[
				'path'    => $directory,
				'basedir' => $directory,
				'subdir'  => '',
				'url'     => home_url( '/' ),
				'baseurl' => home_url( '/' ),
				'error'   => false,
			]
			);
		add_filter( 'upload_dir', $filter );
		try {
			$id = media_handle_sideload( $file, $person_id, 'Ledenfoto' );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$path     = get_attached_file( $id );
			$metadata = wp_get_attachment_metadata( $id ) ?: [];
		} finally {
			remove_filter( 'upload_dir', $filter );
		}
		$metadata['file'] = $path;
		update_attached_file( $id, $path );
		wp_update_attachment_metadata( $id, $metadata );
		update_post_meta(
			$id,
			MemberMedia::META,
			[
				'version'           => 1,
				'directory'         => $directory,
				'main'              => basename( $path ),
				'files'             => [],
				'original_metadata' => $metadata,
			]
			);
		wp_update_post(
			[
				'ID'   => $id,
				'guid' => MemberMedia::delivery_url( $id ),
			]
			);
		return $id;
	}

	/** Inventory and verify print only IDs and counts, never names or image URLs. */
	public static function cli( array $args, array $assoc ): void {
		$operation = $args[0] ?? 'inventory';
		if ( ! in_array( $operation, [ 'inventory', 'migrate', 'verify' ], true ) || ( $operation === 'migrate' && ! isset( $assoc['all'] ) && ! isset( $assoc['ids'] ) ) ) {
			\WP_CLI::error( 'Gebruik inventory, verify, of migrate --all/--ids=1,2.' );
		}
		$ids    = isset( $assoc['ids'] ) ? array_map( 'absint', explode( ',', $assoc['ids'] ) ) : self::candidates();
		$errors = [];
		$public = 0;
		foreach ( $ids as $id ) {
			$result = $operation === 'migrate' ? self::migrate( $id ) : self::inventory( $id );
			if ( is_wp_error( $result ) ) {
				$errors[ $id ] = $result->get_error_code();
				continue;
			}
			$manifest = self::inventory( $id );
			foreach ( $manifest['files'] ?? [] as $file ) {
				$public += is_file( $file['source'] ) ? 1 : 0;
			}
			if ( $operation === 'verify' ) {
				$verified = self::verify( $id );
				if ( is_wp_error( $verified ) ) {
					$errors[ $id ] = $verified->get_error_code();
				}
			}
		}
		\WP_CLI::line(
			wp_json_encode(
			[
				'operation'              => $operation,
				'attachments'            => count( $ids ),
				'public_files_remaining' => $public,
				'errors'                 => $errors,
			]
			)
			);
		if ( $errors || ( $operation === 'verify' && $public ) ) {
			\WP_CLI::error( 'Migratie of verificatie niet voltooid.' );
		}
	}
}
