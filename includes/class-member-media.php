<?php
/** Private storage and permission-checked delivery for member media. */
namespace Rondo\Media;

use Rondo\Core\AccessControl;

final class MemberMedia {
	public const META          = '_rondo_private_member_media';
	private ?array $references = null;

	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'guard_upload' ] );
		add_filter( 'rest_pre_insert_person', [ $this, 'guard_person_media' ], 6, 2 );
		add_filter( 'rest_attachment_query', [ $this, 'filter_query' ] );
		add_filter( 'ajax_query_attachments_args', [ $this, 'filter_query' ] );
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'prepare_for_js' ], 99, 3 );
		add_filter( 'rest_prepare_attachment', [ $this, 'prepare' ], 99, 3 );
		add_filter( 'map_meta_cap', [ $this, 'capabilities' ], 20, 4 );
		add_filter( 'rest_pre_dispatch', [ $this, 'guard' ], 8, 3 );
		add_filter( 'wp_get_attachment_url', [ $this, 'url' ], 99, 2 );
		add_filter( 'image_downsize', [ $this, 'image' ], 99, 3 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'srcset' ], 99, 5 );
		add_action( 'template_redirect', [ $this, 'serve' ], -10 );
		add_action( 'pre_get_posts', [ $this, 'filter_media_queries' ] );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'rondo member-media', [ MemberMediaMigration::class, 'cli' ] );
		}
		foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
			add_action( $hook, [ $this, 'clear_references' ], 10, 3 );
		}
	}

	/** Ordinary media-library uploads must not create public member portraits. */
	public function guard_upload( array $file ): array {
		// Core verifies the upload nonce; this filter only adds a denial.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$parent = absint( $_REQUEST['post_id'] ?? $_REQUEST['post'] ?? 0 );
		if ( $parent && get_post_type( $parent ) === 'person' ) {
			$file['error'] = 'Gebruik de profielfoto-upload voor ledenfoto’s.';
		}
		return $file;
	}

	/** Existing public media cannot be linked into a private person's gallery. */
	public function guard_person_media( $post, $request ) {
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$fields = \Rondo\Fields\RestFields::request_payload( $request, 'person' );
		$ids    = (array) ( $fields['photo_gallery'] ?? [] );
		if ( $request->has_param( 'featured_media' ) ) {
			$ids[] = $request['featured_media'];
		}
		foreach ( array_filter( array_map( 'intval', $ids ) ) as $id ) {
			if ( ! get_post_meta( $id, self::META, true ) || ! $this->can_view( $id ) || $this->people( $id ) !== [ (int) ( $post->ID ?? 0 ) ] ) {
				return new \WP_Error( 'rondo_private_media_required', 'Gebruik de profielfoto-upload voor ledenfoto’s.', [ 'status' => 403 ] );
			}
		}
		return $post;
	}

	public function clear_references( $meta_id, $post_id, $key ): void {
		if ( in_array( $key, [ '_thumbnail_id', 'photo_gallery' ], true ) ) {
			$this->references = null;
		}
	}

	/** Include legacy images whose parent was lost or changed. */
	public function references(): array {
		if ( $this->references !== null ) {
			return $this->references;
		}
		$this->references = [];
		$people           = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => [ 'publish', 'draft', 'private', 'trash' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'OR',
					[
						'key'     => '_thumbnail_id',
						'compare' => 'EXISTS',
					],
					[
						'key'     => 'photo_gallery',
						'compare' => 'EXISTS',
					],
				],
			]
			);
		update_meta_cache( 'post', $people );
		foreach ( $people as $person ) {
			$ids = array_merge( [ (int) get_post_thumbnail_id( $person ) ], (array) get_post_meta( $person, 'photo_gallery', true ) );
			foreach ( array_filter( array_map( 'intval', $ids ) ) as $id ) {
				$this->references[ $id ][] = (int) $person;
			}
		}
		return $this->references;
	}

	public function people( int $id ): array {
		if ( $this->references !== null ) {
			$people = $this->references[ $id ] ?? [];
		} else {
			// A single image request must not load every member's metadata.
			$candidates = get_posts(
				[
					'post_type'        => 'person',
					'post_status'      => [ 'publish', 'draft', 'private', 'trash' ],
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'suppress_filters' => true,
					'meta_query'       => [
						'relation' => 'OR',
						[
							'key'   => '_thumbnail_id',
							'value' => $id,
						],
						[
							'key'     => 'photo_gallery',
							'value'   => '"' . $id . '"',
							'compare' => 'LIKE',
						],
						[
							'key'     => 'photo_gallery',
							'value'   => 'i:' . $id . ';',
							'compare' => 'LIKE',
						],
					],
				]
				);
			$people     = [];
			foreach ( $candidates as $person ) {
				$ids = array_merge( [ (int) get_post_thumbnail_id( $person ) ], array_map( 'intval', (array) get_post_meta( $person, 'photo_gallery', true ) ) );
				if ( in_array( $id, $ids, true ) ) {
					$people[] = (int) $person; }
			}
		}

		$post = get_post( $id );
		if ( $post && $post->post_parent > 0 && get_post_type( $post->post_parent ) === 'person' ) {
			$people[] = (int) $post->post_parent;
		}
		return array_values( array_unique( $people ) );
	}

	public function can_view( int $id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		$post    = get_post( $id );
		if ( ! $post || $post->post_type !== 'attachment' || $post->post_status === 'trash' ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		if ( get_post_meta( $id, '_rondo_private_iva_path', true ) ) {
			// Certificates are available only through the dedicated IVA download route.
			return false;
		}
		$people = $this->people( $id );
		if ( $people ) {
			foreach ( $people as $person ) {
				if ( get_post_status( $person ) !== 'publish' || ! AccessControl::can_view_person( $person, $user_id ) ) {
					return false;
				}
			}
			return true;
		}
		// An unlinked private photo never becomes public again.
		if ( get_post_meta( $id, self::META, true ) ) {
			return false;
		}
		if ( $post->post_parent > 0 && in_array( get_post_type( $post->post_parent ), [ 'team', 'commissie', 'rondo_sponsor' ], true ) && get_post_status( $post->post_parent ) === 'publish' ) {
			return true;
		}
		return $user_id > 0 && (int) $post->post_author === $user_id;
	}

	public function filter_media_queries( $query ): void {
		$types = (array) $query->get( 'post_type' );
		if ( $query->get( 'suppress_filters' ) || current_user_can( 'manage_options' ) || ( ! in_array( 'attachment', $types, true ) && ! in_array( 'any', $types, true ) ) ) {
			return;
		}
		$args = $this->filter_query( [] );
		if ( $types === [ 'attachment' ] ) {
			$included = (array) $query->get( 'post__in' );
			$query->set( 'post__in', $included ? ( array_values( array_intersect( $included, $args['post__in'] ) ) ?: [ 0 ] ) : $args['post__in'] );
			return;
		}
		$hidden   = get_posts(
			[
				'post_type'        => 'attachment',
				'post_status'      => [ 'inherit', 'publish', 'private' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'post__not_in'     => $args['post__in'],
			]
			);
		$included = (array) $query->get( 'post__in' );
		if ( $included ) {
			$query->set( 'post__in', array_values( array_diff( $included, $hidden ) ) ?: [ 0 ] );
		} else {
			$query->set( 'post__not_in', array_values( array_unique( array_merge( (array) $query->get( 'post__not_in' ), $hidden ) ) ) );
		}
	}

	public function filter_query( array $args ): array {
		if ( current_user_can( 'manage_options' ) ) {
			return $args;
		}
		$this->references();
		$all              = get_posts(
			[
				'post_type'        => 'attachment',
				'post_status'      => [ 'inherit', 'publish', 'private' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
			);
		$allowed          = array_values( array_filter( $all, fn( $id ) => $this->can_view( (int) $id ) ) );
		$args['post__in'] = empty( $args['post__in'] ) ? ( $allowed ?: [ 0 ] ) : ( array_values( array_intersect( $args['post__in'], $allowed ) ) ?: [ 0 ] );
		return $args;
	}

	public function capabilities( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, [ 'read_post', 'edit_post', 'delete_post' ], true ) || empty( $args[0] ) || get_post_type( $args[0] ) !== 'attachment' || user_can( $user_id, 'manage_options' ) ) {
			return $caps;
		}
		if ( ! $this->can_view( (int) $args[0], (int) $user_id ) ) {
			return [ 'do_not_allow' ];
		}
		if ( $cap !== 'read_post' ) {
			foreach ( $this->people( (int) $args[0] ) as $person ) {
				if ( ! AccessControl::can_edit_person( $person, $user_id ) || \Rondo\Fields\Fields::get_for_post( $person, 'former_member' ) ) {
					return [ 'do_not_allow' ];
				}
			}
		}
		return $caps;
	}

	public function guard( $result, $server, $request ) {
		if ( ! preg_match( '#^/wp/v2/media(?:/(\d+))?(?:/|$)#i', $request->get_route(), $matches ) ) {
			return $result;
		}
		if ( ! empty( $matches[1] ) && ! $this->can_view( (int) $matches[1] ) ) {
			return new \WP_Error( 'rest_forbidden', 'Geen toegang tot deze media.', [ 'status' => rest_authorization_required_code() ] );
		}
		if ( ! in_array( $request->get_method(), [ 'GET', 'HEAD', 'OPTIONS' ], true ) && $request->has_param( 'post' ) && ( get_post_type( (int) $request['post'] ) === 'person' || ( ! empty( $matches[1] ) && $this->people( (int) $matches[1] ) ) ) ) {
			return new \WP_Error( 'rondo_private_media_required', 'Gebruik de profielfoto-upload voor ledenfoto’s.', [ 'status' => 403 ] );
		}
		return $result;
	}

	/** The media modal and AJAX single-item endpoint share the same boundary. */
	public function prepare_for_js( $response, $attachment, $metadata ) {
		if ( ! $this->can_view( (int) $attachment->ID ) ) {
			return false;
		}
		if ( get_post_meta( $attachment->ID, self::META, true ) ) {
			$response['url'] = self::delivery_url( (int) $attachment->ID );
			foreach ( $response['sizes'] ?? [] as $size => $details ) {
				$response['sizes'][ $size ]['url'] = self::delivery_url( (int) $attachment->ID, $size );
			}
		}
		return $response;
	}

	public function prepare( $response, $post, $request ) {
		if ( ! $this->can_view( (int) $post->ID ) ) {
			return new \WP_Error( 'rest_forbidden', 'Geen toegang tot deze media.', [ 'status' => rest_authorization_required_code() ] );
		}
		if ( is_wp_error( $response ) || ! get_post_meta( $post->ID, self::META, true ) ) {
			return $response;
		}
		$data = $response->get_data();
		unset( $data['media_details']['file'], $data['media_details']['original_image'] );
		foreach ( $data['media_details']['sizes'] ?? [] as $size => $details ) {
			unset( $data['media_details']['sizes'][ $size ]['file'] );
			$data['media_details']['sizes'][ $size ]['source_url'] = self::delivery_url( $post->ID, $size );
		}
		$response->set_data( $data );
		return $response;
	}

	public static function delivery_url( int $id, string $size = 'full' ): string {
		return add_query_arg(
			[
				'rondo_member_media' => $id,
				'size'               => $size,
			],
			home_url( '/' )
			);
	}

	public function url( $url, $id ) {
		return get_post_meta( $id, self::META, true ) ? self::delivery_url( (int) $id ) : $url;
	}

	public function image( $value, $id, $size ) {
		if ( ! get_post_meta( $id, self::META, true ) ) {
			return $value;
		}
		$metadata   = wp_get_attachment_metadata( $id ) ?: [];
		$name       = is_string( $size ) && isset( $metadata['sizes'][ $size ] ) ? $size : 'full';
		$dimensions = $name === 'full' ? $metadata : $metadata['sizes'][ $name ];
		return [ self::delivery_url( (int) $id, $name ), (int) ( $dimensions['width'] ?? 0 ), (int) ( $dimensions['height'] ?? 0 ), $name !== 'full' ];
	}

	public function srcset( $sources, $size, $url, $metadata, $id ) {
		return get_post_meta( $id, self::META, true ) ? false : $sources;
	}

	public static function directory(): string {
		return trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'rondo-private/member-media';
	}

	/** Resolve a known derivative within private storage, never a caller path. */
	public static function file( int $id, string $size = 'full' ) {
		$marker = get_post_meta( $id, self::META, true );
		if ( ! is_array( $marker ) ) {
			return false;
		}
		$path     = get_attached_file( $id );
		$metadata = wp_get_attachment_metadata( $id ) ?: [];
		if ( $size !== 'full' ) {
			$name = $metadata['sizes'][ $size ]['file'] ?? '';
			if ( ! is_string( $name ) || $name === '' || basename( $name ) !== $name ) {
				return false;
			}
			$path = dirname( $path ) . '/' . $name;
		}
		$root      = realpath( self::directory() );
		$directory = isset( $marker['directory'] ) ? realpath( $marker['directory'] ) : false;
		$real      = is_string( $path ) ? realpath( $path ) : false;
		return $root && $directory && $real && str_starts_with( $directory, $root . '/' ) && dirname( $real ) === $directory && ! is_link( $path ) && is_file( $real ) && is_readable( $real ) ? $real : false;
	}

	/** Ordinary same-origin image GETs use the WordPress login cookie. */
	public function serve(): void {
		// Read-only image requests use the login cookie and row permission, not a REST nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['rondo_member_media'] ) ) {
			if ( is_attachment() && ! $this->can_view( get_queried_object_id() ) ) {
				nocache_headers();
				status_header( 403 );
				exit;
			}
			return;
		}
		$id   = absint( $_GET['rondo_member_media'] );
		$size = isset( $_GET['size'] ) && is_string( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : 'full';
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Vary: Cookie' );
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', [ 'GET', 'HEAD' ], true ) || ! $this->can_view( $id ) ) {
			status_header( 403 );
			exit;
		}
		$file = self::file( $id, $size );
		$mime = $file ? wp_get_image_mime( $file ) : false;
		if ( ! $file || ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ], true ) ) {
			status_header( 404 );
			exit;
		}
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $file ) );
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'HEAD' ) {
			readfile( $file );
		}
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
