<?php
/**
 * Companies REST API Endpoints
 *
 * Handles REST API endpoints related to companies domain.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_REST_Companies extends PRM_REST_Base {

	/**
	 * Constructor
	 *
	 * Register routes for company endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register custom REST routes for companies domain
	 */
	public function register_routes() {
		// People by company
		register_rest_route(
			'prm/v1',
			'/companies/(?P<company_id>\d+)/people',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_people_by_company' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'company_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Set company logo (featured image) - by media ID
		register_rest_route(
			'prm/v1',
			'/companies/(?P<company_id>\d+)/logo',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_company_logo' ],
				'permission_callback' => [ $this, 'check_company_edit_permission' ],
				'args'                => [
					'company_id' => [
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

		// Upload company logo with proper filename
		register_rest_route(
			'prm/v1',
			'/companies/(?P<company_id>\d+)/logo/upload',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_company_logo' ],
				'permission_callback' => [ $this, 'check_company_edit_permission' ],
				'args'                => [
					'company_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Sharing endpoints
		register_rest_route(
			'prm/v1',
			'/companies/(?P<id>\d+)/shares',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_shares' ],
					'permission_callback' => [ $this, 'check_post_owner' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_share' ],
					'permission_callback' => [ $this, 'check_post_owner' ],
				],
			]
		);

		register_rest_route(
			'prm/v1',
			'/companies/(?P<id>\d+)/shares/(?P<user_id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_share' ],
				'permission_callback' => [ $this, 'check_post_owner' ],
			]
		);

		// Bulk update companies
		register_rest_route(
			'prm/v1',
			'/companies/bulk-update',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_update_companies' ],
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
							$has_update = isset( $param['visibility'] )
								|| isset( $param['assigned_workspaces'] )
								|| isset( $param['labels_add'] )
								|| isset( $param['labels_remove'] );
							if ( ! $has_update ) {
								return false;
							}
							// Validate visibility if provided
							if ( isset( $param['visibility'] ) ) {
								$valid_visibilities = [ 'private', 'workspace', 'shared' ];
								if ( ! in_array( $param['visibility'], $valid_visibilities, true ) ) {
									return false;
								}
							}
							// Validate assigned_workspaces if provided
							if ( isset( $param['assigned_workspaces'] ) ) {
								if ( ! is_array( $param['assigned_workspaces'] ) ) {
									return false;
								}
								foreach ( $param['assigned_workspaces'] as $ws_id ) {
									if ( ! is_numeric( $ws_id ) ) {
										return false;
									}
								}
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
	 * Get people who work/worked at a company
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing current and former employees.
	 */
	public function get_people_by_company( $request ) {
		$company_id = (int) $request->get_param( 'company_id' );
		$user_id    = get_current_user_id();

		// Check if user can access this company
		$access_control = new PRM_Access_Control();
		if ( ! current_user_can( 'manage_options' ) && ! $access_control->user_can_access_post( $company_id, $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this company.', 'caelis' ),
				[ 'status' => 403 ]
			);
		}

		// Get all people (if you can see the company, you can see who works there)
		// Don't rely on meta_query with ACF repeater fields - filter in PHP instead
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

			// Find the relevant work history entry for this company
			foreach ( $work_history as $job ) {
				// Ensure type consistency for comparison
				$job_company_id = isset( $job['company'] ) ? (int) $job['company'] : 0;

				if ( $job_company_id === $company_id ) {
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
	 * Set company logo (featured image) by media ID
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with logo info or error.
	 */
	public function set_company_logo( $request ) {
		$company_id = (int) $request->get_param( 'company_id' );
		$media_id   = (int) $request->get_param( 'media_id' );

		// Verify company exists
		$company = get_post( $company_id );
		if ( ! $company || $company->post_type !== 'company' ) {
			return new WP_Error( 'company_not_found', __( 'Company not found.', 'caelis' ), [ 'status' => 404 ] );
		}

		// Verify media exists
		$media = get_post( $media_id );
		if ( ! $media || $media->post_type !== 'attachment' ) {
			return new WP_Error( 'media_not_found', __( 'Media not found.', 'caelis' ), [ 'status' => 404 ] );
		}

		// Set as featured image
		$result = set_post_thumbnail( $company_id, $media_id );

		if ( ! $result ) {
			return new WP_Error( 'set_thumbnail_failed', __( 'Failed to set company logo.', 'caelis' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'success'       => true,
				'media_id'      => $media_id,
				'thumbnail_url' => get_the_post_thumbnail_url( $company_id, 'thumbnail' ),
				'full_url'      => get_the_post_thumbnail_url( $company_id, 'full' ),
			]
		);
	}

	/**
	 * Upload company logo with proper filename based on company name
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with attachment info or error.
	 */
	public function upload_company_logo( $request ) {
		$company_id = (int) $request->get_param( 'company_id' );

		// Verify company exists
		$company = get_post( $company_id );
		if ( ! $company || $company->post_type !== 'company' ) {
			return new WP_Error( 'company_not_found', __( 'Company not found.', 'caelis' ), [ 'status' => 404 ] );
		}

		// Check for uploaded file
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded.', 'caelis' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		// Validate file type
		$allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ];
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid file type. Please upload an image.', 'caelis' ), [ 'status' => 400 ] );
		}

		// Get company name for filename
		$company_name = $company->post_title;
		$name_slug    = sanitize_title( strtolower( trim( $company_name ) ) );

		// Get file extension
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $extension === 'jpeg' ) {
			$extension = 'jpg';
		}

		// Generate filename
		$filename = ! empty( $name_slug ) ? $name_slug . '-logo.' . $extension : 'company-' . $company_id . '.' . $extension;

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
		$attachment_id = media_handle_sideload( $file_array, $company_id, sprintf( '%s Logo', $company_name ) );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'upload_failed', $attachment_id->get_error_message(), [ 'status' => 500 ] );
		}

		// Set as featured image
		set_post_thumbnail( $company_id, $attachment_id );

		return rest_ensure_response(
			[
				'success'       => true,
				'attachment_id' => $attachment_id,
				'filename'      => $filename,
				'thumbnail_url' => get_the_post_thumbnail_url( $company_id, 'thumbnail' ),
				'full_url'      => get_the_post_thumbnail_url( $company_id, 'full' ),
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

		if ( ! $post || $post->post_type !== 'company' ) {
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
			return new WP_Error( 'invalid_user', __( 'User not found.', 'caelis' ), [ 'status' => 404 ] );
		}

		// Can't share with yourself
		if ( $user_id === get_current_user_id() ) {
			return new WP_Error( 'invalid_share', __( 'Cannot share with yourself.', 'caelis' ), [ 'status' => 400 ] );
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
						'message' => __( 'Share updated.', 'caelis' ),
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
				'message' => __( 'Shared successfully.', 'caelis' ),
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
				'message' => __( 'Share removed.', 'caelis' ),
			]
		);
	}

	/**
	 * Check if current user can bulk update the specified companies
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool|WP_Error True if permitted, WP_Error otherwise.
	 */
	public function check_bulk_update_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to perform this action.', 'caelis' ),
				[ 'status' => 401 ]
			);
		}

		$ids             = $request->get_param( 'ids' );
		$current_user_id = get_current_user_id();
		$is_admin        = current_user_can( 'administrator' );

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || $post->post_type !== 'company' ) {
				return new WP_Error(
					'rest_invalid_id',
					sprintf( __( 'Company with ID %d not found.', 'caelis' ), $post_id ),
					[ 'status' => 404 ]
				);
			}

			// Must be post author or admin
			if ( (int) $post->post_author !== $current_user_id && ! $is_admin ) {
				return new WP_Error(
					'rest_forbidden',
					sprintf( __( 'You do not have permission to update company with ID %d.', 'caelis' ), $post_id ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Bulk update multiple companies
	 *
	 * Updates visibility, workspace assignments, and/or labels
	 * for multiple companies at once.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response with updated/failed arrays.
	 */
	public function bulk_update_companies( $request ) {
		$ids     = $request->get_param( 'ids' );
		$updates = $request->get_param( 'updates' );

		$updated = [];
		$failed  = [];

		foreach ( $ids as $post_id ) {
			try {
				// Update visibility if provided
				if ( isset( $updates['visibility'] ) ) {
					$result = PRM_Visibility::set_visibility( $post_id, $updates['visibility'] );
					if ( ! $result ) {
						$failed[] = [
							'id'    => $post_id,
							'error' => __( 'Failed to update visibility.', 'caelis' ),
						];
						continue;
					}
				}

				// Update workspace assignments if provided
				if ( isset( $updates['assigned_workspaces'] ) ) {
					$workspace_ids = array_map( 'intval', $updates['assigned_workspaces'] );

					// Convert workspace post IDs to term IDs
					$term_ids = [];
					foreach ( $workspace_ids as $workspace_id ) {
						$term_slug = 'workspace-' . $workspace_id;
						$term      = get_term_by( 'slug', $term_slug, 'workspace_access' );

						if ( $term && ! is_wp_error( $term ) ) {
							$term_ids[] = $term->term_id;
						}
					}

					// Set the terms on the post
					wp_set_object_terms( $post_id, $term_ids, 'workspace_access' );

					// Update the ACF field with term IDs
					update_field( '_assigned_workspaces', $term_ids, $post_id );
				}

				// Add labels if provided (append, don't replace)
				if ( ! empty( $updates['labels_add'] ) ) {
					$term_ids = array_map( 'intval', $updates['labels_add'] );
					wp_set_object_terms( $post_id, $term_ids, 'company_label', true );
				}

				// Remove labels if provided
				if ( ! empty( $updates['labels_remove'] ) ) {
					$term_ids = array_map( 'intval', $updates['labels_remove'] );
					wp_remove_object_terms( $post_id, $term_ids, 'company_label' );
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
