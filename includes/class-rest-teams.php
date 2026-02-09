<?php
/**
 * Teams REST API Endpoints
 *
 * Handles REST API endpoints related to teams domain.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Teams extends Base {

	/**
	 * Constructor
	 *
	 * Register routes for team endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register custom REST routes for teams domain
	 */
	public function register_routes() {
		// People by company
		register_rest_route(
			'rondo/v1',
			'/teams/(?P<team_id>\d+)/people',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_people_by_company' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'team_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Set team logo (featured image) - by media ID
		register_rest_route(
			'rondo/v1',
			'/teams/(?P<team_id>\d+)/logo',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_company_logo' ],
				'permission_callback' => [ $this, 'check_company_edit_permission' ],
				'args'                => [
					'team_id' => [
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

		// Upload team logo with proper filename
		register_rest_route(
			'rondo/v1',
			'/teams/(?P<team_id>\d+)/logo/upload',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_company_logo' ],
				'permission_callback' => [ $this, 'check_company_edit_permission' ],
				'args'                => [
					'team_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Sharing endpoints
		register_rest_route(
			'rondo/v1',
			'/teams/(?P<id>\d+)/shares',
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
			'rondo/v1',
			'/teams/(?P<id>\d+)/shares/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_share' ],
				'permission_callback' => [ $this, 'check_post_owner' ],
			]
		);

		// Bulk update teams
		register_rest_route(
			'rondo/v1',
			'/teams/bulk-update',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_update_teams' ],
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
	 * Get people who work/worked at a team
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing current and former employees.
	 */
	public function get_people_by_company( $request ) {
		$team_id = (int) $request->get_param( 'team_id' );
		$user_id    = get_current_user_id();

		// Check if user can access this team
		$access_control = new \RONDO_Access_Control();
		if ( ! current_user_can( 'manage_options' ) && ! $access_control->user_can_access_post( $team_id, $user_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this team.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		// Query people who have work_history data
		// We filter at database level by checking for work_history count > 0
		// This reduces the dataset before PHP filtering
		// Also exclude former members from team rosters
		$people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => 'work_history',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					],
					[
						'relation' => 'OR',
						[
							'key'     => 'former_member',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => 'former_member',
							'value'   => '1',
							'compare' => '!=',
						],
					],
				],
				'fields'         => 'ids', // Only get IDs first for efficiency
			]
		);

		// Convert IDs back to post objects for processing
		$people = array_map( 'get_post', $people );

		$current = [];
		$former  = [];

		// Loop through all people and check their work history
		foreach ( $people as $person ) {
			$work_history = get_field( 'work_history', $person->ID ) ?: [];

			if ( empty( $work_history ) ) {
				continue;
			}

			// Find the relevant work history entry for this team
			foreach ( $work_history as $job ) {
				// Ensure type consistency for comparison
				$job_team_id = isset( $job['team'] ) ? (int) $job['team'] : 0;

				if ( $job_team_id === $team_id ) {
					$person_data               = $this->format_person_summary( $person );
					$person_data['job_title']  = $job['job_title'] ?? '';
					$person_data['start_date'] = $job['start_date'] ?? '';
					$person_data['end_date']   = $job['end_date'] ?? '';

					// Determine if person is current or former
					$is_current = false;

					// If is_current flag is set, check if end_date has passed
					if ( ! empty( $job['is_current'] ) ) {
						// If there's an end_date, check if it's in the future
						if ( ! empty( $job['end_date'] ) ) {
							$end_date = strtotime( $job['end_date'] );
							$today    = strtotime( 'today' );
							// Only current if end_date is today or in the future
							$is_current = ( $end_date >= $today );
						} else {
							// No end_date, so still current
							$is_current = true;
						}
					}
					// If no is_current flag but no end_date, they're current
					elseif ( empty( $job['end_date'] ) ) {
						$is_current = true;
					}
					// If end_date is in the future (and is_current not set), they're still current
					elseif ( ! empty( $job['end_date'] ) ) {
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
					break; // Found the matching job, move to next person
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
	 * Set team logo (featured image) by media ID
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with logo info or error.
	 */
	public function set_company_logo( $request ) {
		$team_id = (int) $request->get_param( 'team_id' );
		$media_id   = (int) $request->get_param( 'media_id' );

		// Verify team exists
		$team = get_post( $team_id );
		if ( ! $team || $team->post_type !== 'team' ) {
			return new \WP_Error( 'company_not_found', __( 'Team not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		// Verify media exists
		$media = get_post( $media_id );
		if ( ! $media || $media->post_type !== 'attachment' ) {
			return new \WP_Error( 'media_not_found', __( 'Media not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		// Set as featured image
		$result = set_post_thumbnail( $team_id, $media_id );

		if ( ! $result ) {
			return new \WP_Error( 'set_thumbnail_failed', __( 'Failed to set team logo.', 'rondo' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'success'       => true,
				'media_id'      => $media_id,
				'thumbnail_url' => get_the_post_thumbnail_url( $team_id, 'thumbnail' ),
				'full_url'      => get_the_post_thumbnail_url( $team_id, 'full' ),
			]
		);
	}

	/**
	 * Upload team logo with proper filename based on company name
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with attachment info or error.
	 */
	public function upload_company_logo( $request ) {
		$team_id = (int) $request->get_param( 'team_id' );

		// Verify team exists
		$team = get_post( $team_id );
		if ( ! $team || $team->post_type !== 'team' ) {
			return new \WP_Error( 'company_not_found', __( 'Team not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		// Check for uploaded file
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'rondo' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		// Validate file type
		$allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ];
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid file type. Please upload an image.', 'rondo' ), [ 'status' => 400 ] );
		}

		// Get company name for filename
		$team_name = $team->post_title;
		$name_slug    = sanitize_title( strtolower( trim( $team_name ) ) );

		// Get file extension
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $extension === 'jpeg' ) {
			$extension = 'jpg';
		}

		// Generate filename
		$filename = ! empty( $name_slug ) ? $name_slug . '-logo.' . $extension : 'company-' . $team_id . '.' . $extension;

		// Load required files
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Prepare file array with new filename
		$file_array = [
			'name'     => $filename,
			'type'     => $file['type'],
			'tmp_name' => $file['tmp_name'],
			'error'    => $file['error'],
			'size'     => $file['size'],
		];

		// Handle the upload
		$attachment_id = media_handle_sideload( $file_array, $team_id, sprintf( '%s Logo', $team_name ) );

		if ( is_wp_error( $attachment_id ) ) {
			return new \WP_Error( 'upload_failed', $attachment_id->get_error_message(), [ 'status' => 500 ] );
		}

		// Set as featured image
		set_post_thumbnail( $team_id, $attachment_id );

		return rest_ensure_response(
			[
				'success'       => true,
				'attachment_id' => $attachment_id,
				'filename'      => $filename,
				'thumbnail_url' => get_the_post_thumbnail_url( $team_id, 'thumbnail' ),
				'full_url'      => get_the_post_thumbnail_url( $team_id, 'full' ),
			]
		);
	}

	/**
	 * Check if current user owns this post (can share it)
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

		if ( ! $post || $post->post_type !== 'team' ) {
			return false;
		}

		// Must be post author or admin
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

		// Validate user exists
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'invalid_user', __( 'User not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		// Can't share with yourself
		if ( $user_id === get_current_user_id() ) {
			return new \WP_Error( 'invalid_share', __( 'Cannot share with yourself.', 'rondo' ), [ 'status' => 400 ] );
		}

		// Get current shares
		$shares = get_field( '_shared_with', $post_id ) ?: [];

		// Check if already shared
		foreach ( $shares as $key => $share ) {
			if ( (int) $share['user_id'] === $user_id ) {
				// Update permission
				$shares[ $key ]['permission'] = $permission;
				update_field( '_shared_with', $shares, $post_id );
				return rest_ensure_response(
					[
						'success' => true,
						'message' => __( 'Share updated.', 'rondo' ),
					]
				);
			}
		}

		// Add new share
		$shares[] = [
			'user_id'    => $user_id,
			'permission' => $permission,
		];
		update_field( '_shared_with', $shares, $post_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Shared successfully.', 'rondo' ),
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
		$shares = array_values( $shares ); // Re-index

		update_field( '_shared_with', $shares, $post_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Share removed.', 'rondo' ),
			]
		);
	}

	/**
	 * Check if current user can bulk update the specified teams
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool|WP_Error True if permitted, WP_Error otherwise.
	 */
	public function check_bulk_update_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to perform this action.', 'rondo' ),
				[ 'status' => 401 ]
			);
		}

		$ids             = $request->get_param( 'ids' );
		$current_user_id = get_current_user_id();
		$is_admin        = current_user_can( 'administrator' );

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || $post->post_type !== 'team' ) {
				return new \WP_Error(
					'rest_invalid_id',
					sprintf( __( 'Team with ID %d not found.', 'rondo' ), $post_id ),
					[ 'status' => 404 ]
				);
			}

			// Must be post author or admin
			if ( (int) $post->post_author !== $current_user_id && ! $is_admin ) {
				return new \WP_Error(
					'rest_forbidden',
					sprintf( __( 'You do not have permission to update company with ID %d.', 'rondo' ), $post_id ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Bulk update multiple teams
	 *
	 * Updates labels for multiple teams at once.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response with updated/failed arrays.
	 */
	public function bulk_update_teams( $request ) {
		$ids     = $request->get_param( 'ids' );
		$updates = $request->get_param( 'updates' );

		$updated = [];
		$failed  = [];

		foreach ( $ids as $post_id ) {
			try {
				// Add labels if provided (append, don't replace)
				if ( ! empty( $updates['labels_add'] ) ) {
					$term_ids = array_map( 'intval', $updates['labels_add'] );
					wp_set_object_terms( $post_id, $term_ids, 'team_label', true );
				}

				// Remove labels if provided
				if ( ! empty( $updates['labels_remove'] ) ) {
					$term_ids = array_map( 'intval', $updates['labels_remove'] );
					wp_remove_object_terms( $post_id, $term_ids, 'team_label' );
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
