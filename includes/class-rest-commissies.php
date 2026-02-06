<?php
/**
 * Commissies REST API Endpoints
 *
 * Handles REST API endpoints related to commissies domain.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Commissies extends Base {

	/**
	 * Constructor
	 *
	 * Register routes for commissie endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register custom REST routes for commissies domain
	 */
	public function register_routes() {
		// People by commissie
		register_rest_route(
			'stadion/v1',
			'/commissies/(?P<commissie_id>\d+)/people',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_people_by_commissie' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'commissie_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Set commissie logo (featured image) - by media ID
		register_rest_route(
			'stadion/v1',
			'/commissies/(?P<commissie_id>\d+)/logo',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_commissie_logo' ],
				'permission_callback' => [ $this, 'check_commissie_edit_permission' ],
				'args'                => [
					'commissie_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
					'media_id'   => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Upload commissie logo with proper filename
		register_rest_route(
			'stadion/v1',
			'/commissies/(?P<commissie_id>\d+)/logo/upload',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_commissie_logo' ],
				'permission_callback' => [ $this, 'check_commissie_edit_permission' ],
				'args'                => [
					'commissie_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Sharing endpoints
		register_rest_route(
			'stadion/v1',
			'/commissies/(?P<id>\d+)/shares',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_shares' ],
					'permission_callback' => [ $this, 'check_post_owner' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_share' ],
					'permission_callback' => [ $this, 'check_post_owner' ],
				],
			]
		);

		register_rest_route(
			'stadion/v1',
			'/commissies/(?P<id>\d+)/shares/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_share' ],
				'permission_callback' => [ $this, 'check_post_owner' ],
			]
		);

		// Bulk update commissies
		register_rest_route(
			'stadion/v1',
			'/commissies/bulk-update',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_update_commissies' ],
				'permission_callback' => [ $this, 'check_bulk_update_permission' ],
				'args'                => [
					'ids'     => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							if ( ! is_array( $param ) || empty( $param ) ) {
								return false;
							}
							foreach ( $param as $id ) {
								if ( ! is_numeric( $id ) ) {
									return false;
								}
							}
							return true;
						},
						'sanitize_callback' => function ( $param ) {
							return array_map( 'intval', $param );
						},
					],
					'updates' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							if ( ! is_array( $param ) || empty( $param ) ) {
								return false;
							}
							// Must have at least one supported update type
							$has_update = isset( $param['labels_add'] )
								|| isset( $param['labels_remove'] );
							if ( ! $has_update ) {
								return false;
							}
							// Validate labels_add if provided
							if ( isset( $param['labels_add'] ) ) {
								if ( ! is_array( $param['labels_add'] ) ) {
									return false;
								}
								foreach ( $param['labels_add'] as $term_id ) {
									if ( ! is_numeric( $term_id ) ) {
										return false;
									}
								}
							}
							// Validate labels_remove if provided
							if ( isset( $param['labels_remove'] ) ) {
								if ( ! is_array( $param['labels_remove'] ) ) {
									return false;
								}
								foreach ( $param['labels_remove'] as $term_id ) {
									if ( ! is_numeric( $term_id ) ) {
										return false;
									}
								}
							}
							return true;
						},
					],
				],
			]
		);
	}

	/**
	 * Get people who are members of a commissie
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing current and former members.
	 */
	public function get_people_by_commissie( $request ) {
		$commissie_id = (int) $request->get_param( 'commissie_id' );
		$user_id      = get_current_user_id();

		// Check if user can access this commissie
		$access_control = new \STADION_Access_Control();
		if ( ! current_user_can( 'manage_options' ) && ! $access_control->user_can_access_post( $commissie_id, $user_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this commissie.', 'stadion' ),
				[ 'status' => 403 ]
			);
		}

		// Get all people and filter by work history containing this commissie
		$people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			]
		);

		$current = [];
		$former  = [];

		// Loop through all people and check their work history
		foreach ( $people as $person ) {
			$work_history = get_field( 'work_history', $person->ID ) ?: [];

			if ( empty( $work_history ) ) {
				continue;
			}

			// Find the relevant work history entry for this commissie
			foreach ( $work_history as $job ) {
				// Check if this job references a commissie (using the 'team' field which now supports both)
				$job_commissie_id = isset( $job['team'] ) ? (int) $job['team'] : 0;

				// Verify the post is actually a commissie
				$job_post = get_post( $job_commissie_id );
				if ( ! $job_post || $job_post->post_type !== 'commissie' ) {
					continue;
				}

				if ( $job_commissie_id === $commissie_id ) {
					$person_data               = $this->format_person_summary( $person );
					$person_data['job_title']  = $job['job_title'] ?? '';
					$person_data['start_date'] = $job['start_date'] ?? '';
					$person_data['end_date']   = $job['end_date'] ?? '';

					// Determine if person is current or former
					$is_current = false;

					if ( ! empty( $job['is_current'] ) ) {
						if ( ! empty( $job['end_date'] ) ) {
							$end_date = strtotime( $job['end_date'] );
							$today    = strtotime( 'today' );
							$is_current = ( $end_date >= $today );
						} else {
							$is_current = true;
						}
					} elseif ( empty( $job['end_date'] ) ) {
						$is_current = true;
					} elseif ( ! empty( $job['end_date'] ) ) {
						$end_date = strtotime( $job['end_date'] );
						$today    = strtotime( 'today' );
						if ( $end_date >= $today ) {
							$is_current = true;
						}
					}

					if ( $is_current ) {
						$current[] = $person_data;
					} else {
						$former[] = $person_data;
					}
					break;
				}
			}
		}

		return rest_ensure_response(
			[
				'current' => $current,
				'former'  => $former,
			]
		);
	}

	/**
	 * Check if user can edit a commissie
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can edit, false otherwise.
	 */
	public function check_commissie_edit_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$commissie_id = $request->get_param( 'commissie_id' );
		$commissie    = get_post( $commissie_id );

		if ( ! $commissie || $commissie->post_type !== 'commissie' ) {
			return false;
		}

		// Check if user can edit this commissie
		return (int) $commissie->post_author === get_current_user_id() || current_user_can( 'administrator' );
	}

	/**
	 * Set commissie logo (featured image) by media ID
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with logo info or error.
	 */
	public function set_commissie_logo( $request ) {
		$commissie_id = (int) $request->get_param( 'commissie_id' );
		$media_id     = (int) $request->get_param( 'media_id' );

		$commissie = get_post( $commissie_id );
		if ( ! $commissie || $commissie->post_type !== 'commissie' ) {
			return new \WP_Error( 'commissie_not_found', __( 'Commissie not found.', 'stadion' ), [ 'status' => 404 ] );
		}

		$media = get_post( $media_id );
		if ( ! $media || $media->post_type !== 'attachment' ) {
			return new \WP_Error( 'media_not_found', __( 'Media not found.', 'stadion' ), [ 'status' => 404 ] );
		}

		$result = set_post_thumbnail( $commissie_id, $media_id );

		if ( ! $result ) {
			return new \WP_Error( 'set_thumbnail_failed', __( 'Failed to set commissie logo.', 'stadion' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'success'       => true,
				'media_id'      => $media_id,
				'thumbnail_url' => get_the_post_thumbnail_url( $commissie_id, 'thumbnail' ),
				'full_url'      => get_the_post_thumbnail_url( $commissie_id, 'full' ),
			]
		);
	}

	/**
	 * Upload commissie logo with proper filename
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with attachment info or error.
	 */
	public function upload_commissie_logo( $request ) {
		$commissie_id = (int) $request->get_param( 'commissie_id' );

		$commissie = get_post( $commissie_id );
		if ( ! $commissie || $commissie->post_type !== 'commissie' ) {
			return new \WP_Error( 'commissie_not_found', __( 'Commissie not found.', 'stadion' ), [ 'status' => 404 ] );
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'stadion' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		$allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ];
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid file type. Please upload an image.', 'stadion' ), [ 'status' => 400 ] );
		}

		$commissie_name = $commissie->post_title;
		$name_slug      = sanitize_title( strtolower( trim( $commissie_name ) ) );

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $extension === 'jpeg' ) {
			$extension = 'jpg';
		}

		$filename = ! empty( $name_slug ) ? $name_slug . '-logo.' . $extension : 'commissie-' . $commissie_id . '.' . $extension;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file_array = [
			'name'     => $filename,
			'type'     => $file['type'],
			'tmp_name' => $file['tmp_name'],
			'error'    => $file['error'],
			'size'     => $file['size'],
		];

		$attachment_id = media_handle_sideload( $file_array, $commissie_id, sprintf( '%s Logo', $commissie_name ) );

		if ( is_wp_error( $attachment_id ) ) {
			return new \WP_Error( 'upload_failed', $attachment_id->get_error_message(), [ 'status' => 500 ] );
		}

		set_post_thumbnail( $commissie_id, $attachment_id );

		return rest_ensure_response(
			[
				'success'       => true,
				'attachment_id' => $attachment_id,
				'filename'      => $filename,
				'thumbnail_url' => get_the_post_thumbnail_url( $commissie_id, 'thumbnail' ),
				'full_url'      => get_the_post_thumbnail_url( $commissie_id, 'full' ),
			]
		);
	}

	/**
	 * Check if current user owns this post
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user owns the post or is admin.
	 */
	public function check_post_owner( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$post_id = $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== 'commissie' ) {
			return false;
		}

		return (int) $post->post_author === get_current_user_id() || current_user_can( 'administrator' );
	}

	/**
	 * Get list of users this post is shared with
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing share list.
	 */
	public function get_shares( $request ) {
		$post_id = $request->get_param( 'id' );
		$shares  = get_field( '_shared_with', $post_id ) ?: [];

		$result = [];
		foreach ( $shares as $share ) {
			$user = get_user_by( 'ID', $share['user_id'] );
			if ( $user ) {
				$result[] = [
					'user_id'      => (int) $share['user_id'],
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
					'avatar_url'   => get_avatar_url( $user->ID, [ 'size' => 48 ] ),
					'permission'   => $share['permission'],
				];
			}
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Share post with a user
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with success or error.
	 */
	public function add_share( $request ) {
		$post_id    = $request->get_param( 'id' );
		$user_id    = (int) $request->get_param( 'user_id' );
		$permission = $request->get_param( 'permission' ) ?: 'view';

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'invalid_user', __( 'User not found.', 'stadion' ), [ 'status' => 404 ] );
		}

		if ( $user_id === get_current_user_id() ) {
			return new \WP_Error( 'invalid_share', __( 'Cannot share with yourself.', 'stadion' ), [ 'status' => 400 ] );
		}

		$shares = get_field( '_shared_with', $post_id ) ?: [];

		foreach ( $shares as $key => $share ) {
			if ( (int) $share['user_id'] === $user_id ) {
				$shares[ $key ]['permission'] = $permission;
				update_field( '_shared_with', $shares, $post_id );
				return rest_ensure_response(
					[
						'success' => true,
						'message' => __( 'Share updated.', 'stadion' ),
					]
				);
			}
		}

		$shares[] = [
			'user_id'    => $user_id,
			'permission' => $permission,
		];
		update_field( '_shared_with', $shares, $post_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Shared successfully.', 'stadion' ),
			]
		);
	}

	/**
	 * Remove share from a user
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response with success status.
	 */
	public function remove_share( $request ) {
		$post_id = $request->get_param( 'id' );
		$user_id = (int) $request->get_param( 'user_id' );

		$shares = get_field( '_shared_with', $post_id ) ?: [];
		$shares = array_filter(
			$shares,
			function ( $share ) use ( $user_id ) {
				return (int) $share['user_id'] !== $user_id;
			}
		);
		$shares = array_values( $shares );

		update_field( '_shared_with', $shares, $post_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Share removed.', 'stadion' ),
			]
		);
	}

	/**
	 * Check if current user can bulk update the specified commissies
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool|WP_Error True if permitted, WP_Error otherwise.
	 */
	public function check_bulk_update_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to perform this action.', 'stadion' ),
				[ 'status' => 401 ]
			);
		}

		$ids             = $request->get_param( 'ids' );
		$current_user_id = get_current_user_id();
		$is_admin        = current_user_can( 'administrator' );

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || $post->post_type !== 'commissie' ) {
				return new \WP_Error(
					'rest_invalid_id',
					sprintf( __( 'Commissie with ID %d not found.', 'stadion' ), $post_id ),
					[ 'status' => 404 ]
				);
			}

			if ( (int) $post->post_author !== $current_user_id && ! $is_admin ) {
				return new \WP_Error(
					'rest_forbidden',
					sprintf( __( 'You do not have permission to update commissie with ID %d.', 'stadion' ), $post_id ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Bulk update multiple commissies
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response with updated/failed arrays.
	 */
	public function bulk_update_commissies( $request ) {
		$ids     = $request->get_param( 'ids' );
		$updates = $request->get_param( 'updates' );

		$updated = [];
		$failed  = [];

		foreach ( $ids as $post_id ) {
			try {
				if ( ! empty( $updates['labels_add'] ) ) {
					$term_ids = array_map( 'intval', $updates['labels_add'] );
					wp_set_object_terms( $post_id, $term_ids, 'commissie_label', true );
				}

				if ( ! empty( $updates['labels_remove'] ) ) {
					$term_ids = array_map( 'intval', $updates['labels_remove'] );
					wp_remove_object_terms( $post_id, $term_ids, 'commissie_label' );
				}

				$updated[] = $post_id;
			} catch ( Exception $e ) {
				$failed[] = [
					'id'    => $post_id,
					'error' => $e->getMessage(),
				];
			}
		}

		return rest_ensure_response(
			[
				'success' => empty( $failed ),
				'updated' => $updated,
				'failed'  => $failed,
			]
		);
	}
}
