<?php
/**
 * People REST API Endpoints
 *
 * Handles REST API endpoints related to people domain.
 */

namespace Rondo\REST;

use Rondo\CustomFields\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class People extends Base {

	/**
	 * Constructor
	 *
	 * Register routes and filters for people endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Expand relationship data in person REST responses
		add_filter( 'rest_prepare_person', [ $this, 'expand_person_relationships' ], 10, 3 );

		// Add computed fields (is_deceased) to person REST responses
		add_filter( 'rest_prepare_person', [ $this, 'add_person_computed_fields' ], 20, 3 );
	}

	/**
	 * Register custom REST routes for people domain
	 */
	public function register_routes() {
		// Dates by person
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/dates',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dates_by_person' ],
				'permission_callback' => [ $this, 'check_person_access' ],
				'args'                => [
					'person_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Sideload Gravatar image
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/gravatar',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sideload_gravatar' ],
				'permission_callback' => [ $this, 'check_person_access' ],
				'args'                => [
					'person_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
					'email'     => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					],
				],
			]
		);

		// Upload person photo with proper filename
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/photo',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_person_photo' ],
				'permission_callback' => [ $this, 'check_person_edit_permission' ],
				'args'                => [
					'person_id' => [
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
			'/people/(?P<id>\d+)/shares',
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
			'/people/(?P<id>\d+)/shares/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_share' ],
				'permission_callback' => [ $this, 'check_post_owner' ],
			]
		);

		// Bulk update endpoint
		register_rest_route(
			'rondo/v1',
			'/people/bulk-update',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_update_people' ],
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
							$has_update = array_key_exists( 'organization_id', $param );
							if ( ! $has_update ) {
								return false;
							}
							// Validate organization_id if provided (can be int or null)
							if ( array_key_exists( 'organization_id', $param ) ) {
								$org_id = $param['organization_id'];
								if ( $org_id !== null ) {
									if ( ! is_numeric( $org_id ) ) {
										return false;
									}
									// Validate organization exists as published team post
									$org = get_post( (int) $org_id );
									if ( ! $org || $org->post_type !== 'team' || $org->post_status !== 'publish' ) {
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

		// Filtered people with server-side pagination, filtering, and sorting
		register_rest_route(
			'rondo/v1',
			'/people/filtered',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_filtered_people' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'page'                 => [
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					],
					'per_page'             => [
						'default'           => 100,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0 && (int) $param <= 100;
						},
						'sanitize_callback' => 'absint',
					],
					'ownership'            => [
						'default'           => 'all',
						'validate_callback' => function ( $param ) {
							return in_array( $param, [ 'mine', 'shared', 'all' ], true );
						},
					],
					'modified_days'        => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || $param === '' || ( is_numeric( $param ) && (int) $param > 0 );
						},
						'sanitize_callback' => function ( $param ) {
							return $param === null || $param === '' ? null : absint( $param );
						},
					],
					'orderby'              => [
						'default'           => 'first_name',
						'validate_callback' => [ $this, 'validate_orderby_param' ],
					],
					'order'                => [
						'default'           => 'asc',
						'validate_callback' => function ( $param ) {
							return in_array( strtolower( $param ), [ 'asc', 'desc' ], true );
						},
						'sanitize_callback' => function ( $param ) {
							return strtolower( $param );
						},
					],
					'birth_year_from'      => [
						'description'       => 'Filter by birth year (minimum year, inclusive)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1900 && $value <= 2100;
						},
					],
					'birth_year_to'        => [
						'description'       => 'Filter by birth year (maximum year, inclusive)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1900 && $value <= 2100;
						},
					],
					// Custom field filters
					'huidig_vrijwilliger'  => [
						'description'       => 'Filter by current volunteer status (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'financiele_blokkade'  => [
						'description'       => 'Filter by financial block status (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'type_lid'             => [
						'description'       => 'Filter by member type',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'foto_missing'         => [
						'description'       => 'Filter for people without photo date (1=missing, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'vog_missing'          => [
						'description'       => 'Filter for people without VOG date (1=missing, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'vog_older_than_years' => [
						'description'       => 'Filter for VOG older than N years',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1 && $value <= 10;
						},
					],
					'vog_email_status'     => [
						'description'       => 'Filter by VOG email status (sent, not_sent, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'sent', 'not_sent' ], true );
						},
					],
					'vog_type'             => [
						'description'       => 'Filter by VOG type (nieuw=no VOG, vernieuwing=expired VOG)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'nieuw', 'vernieuwing' ], true );
						},
					],
					'leeftijdsgroep'       => [
						'description'       => 'Filter by age group',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'vog_expiring_within_days' => [
						'description'       => 'Filter for VOG expiring within N days (valid but expiring soon)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1 && $value <= 365;
						},
					],
					'vog_justis_status'    => [
						'description'       => 'Filter by VOG Justis status (submitted, not_submitted, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'submitted', 'not_submitted' ], true );
						},
					],
					'vog_reminder_status'  => [
						'description'       => 'Filter by VOG reminder status (sent, not_sent, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'sent', 'not_sent' ], true );
						},
					],
					'include_former'       => [
						'description'       => 'Include former members in results (1=include, empty=exclude)',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'lid_tot_future'       => [
						'description'       => 'Filter for people with lid-tot date in the future (1=future only, empty=all)',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
				],
			]
		);

		// Filter options endpoint
		register_rest_route(
			'rondo/v1',
			'/people/filter-options',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_filter_options' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
	}

	/**
	 * Sideload Gravatar image for a person
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with attachment info or error.
	 */
	public function sideload_gravatar( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$email     = sanitize_email( $request->get_param( 'email' ) );

		if ( empty( $email ) ) {
			return new \WP_Error( 'missing_email', __( 'Email address is required.', 'rondo' ), [ 'status' => 400 ] );
		}

		// Generate Gravatar URL
		$email_hash   = md5( strtolower( trim( $email ) ) );
		$gravatar_url = sprintf( 'https://www.gravatar.com/avatar/%s?s=400&d=404', $email_hash );

		// Check if Gravatar exists (404 means no gravatar)
		$response = wp_remote_head( $gravatar_url );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 404 ) {
			return rest_ensure_response(
				[
					'success' => false,
					'message' => 'No Gravatar found for this email address',
				]
			);
		}

		// Sideload the image
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the file
		$tmp = download_url( $gravatar_url );

		if ( is_wp_error( $tmp ) ) {
			return new \WP_Error( 'download_failed', __( 'Failed to download Gravatar image.', 'rondo' ), [ 'status' => 500 ] );
		}

		// Get person's name for filename
		$first_name = get_field( 'first_name', $person_id ) ?: '';
		$last_name  = get_field( 'last_name', $person_id ) ?: '';
		$name_slug  = sanitize_title( strtolower( trim( $first_name . ' ' . $last_name ) ) );
		$filename   = ! empty( $name_slug ) ? $name_slug . '.jpg' : 'gravatar-' . $person_id . '.jpg';

		// Get file info
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp,
		];

		// Sideload the file
		$attachment_id = media_handle_sideload( $file_array, $person_id, sprintf( __( '%s Gravatar', 'rondo' ), $first_name . ' ' . $last_name ) );

		// Clean up temp file if sideload failed
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return new \WP_Error( 'sideload_failed', __( 'Failed to sideload Gravatar image.', 'rondo' ), [ 'status' => 500 ] );
		}

		// Set as featured image
		set_post_thumbnail( $person_id, $attachment_id );

		return rest_ensure_response(
			[
				'success'       => true,
				'attachment_id' => $attachment_id,
				'thumbnail_url' => get_the_post_thumbnail_url( $person_id, 'thumbnail' ),
			]
		);
	}

	/**
	 * Upload person photo with proper filename based on person's name
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with attachment info or error.
	 */
	public function upload_person_photo( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );

		// Verify person exists
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'person_not_found', __( 'Person not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		// Check for uploaded file
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'rondo' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		// Validate file type
		$allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' ];
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid file type. Please upload an image.', 'rondo' ), [ 'status' => 400 ] );
		}

		// Get person's name for filename
		$first_name = get_field( 'first_name', $person_id ) ?: '';
		$last_name  = get_field( 'last_name', $person_id ) ?: '';
		$name_slug  = sanitize_title( strtolower( trim( $first_name . ' ' . $last_name ) ) );

		// Get file extension
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $extension === 'jpeg' ) {
			$extension = 'jpg';
		}

		// Generate filename
		$filename = ! empty( $name_slug ) ? $name_slug . '.' . $extension : 'person-' . $person_id . '.' . $extension;

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
		$attachment_id = media_handle_sideload( $file_array, $person_id, sprintf( '%s %s', $first_name, $last_name ) );

		if ( is_wp_error( $attachment_id ) ) {
			return new \WP_Error( 'upload_failed', $attachment_id->get_error_message(), [ 'status' => 500 ] );
		}

		// Set as featured image
		set_post_thumbnail( $person_id, $attachment_id );

		return rest_ensure_response(
			[
				'success'       => true,
				'attachment_id' => $attachment_id,
				'filename'      => $filename,
				'thumbnail_url' => get_the_post_thumbnail_url( $person_id, 'thumbnail' ),
				'full_url'      => get_the_post_thumbnail_url( $person_id, 'full' ),
			]
		);
	}

	/**
	 * Expand relationship data with person names and relationship type names
	 *
	 * @param WP_REST_Response $response The REST response object.
	 * @param WP_Post $post The post object.
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Modified response with expanded relationships.
	 */
	public function expand_person_relationships( $response, $post, $request ) {
		// Return early if response is an error (e.g., unauthorized access)
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! isset( $data['acf']['relationships'] ) || ! is_array( $data['acf']['relationships'] ) ) {
			return $response;
		}

		$expanded_relationships = [];

		foreach ( $data['acf']['relationships'] as $rel ) {
			// Get person ID - could be an object, array, or just an ID
			$person_id = null;
			if ( is_object( $rel['related_person'] ) ) {
				$person_id = $rel['related_person']->ID;
			} elseif ( is_array( $rel['related_person'] ) ) {
				$person_id = $rel['related_person']['ID'] ?? null;
			} else {
				$person_id = $rel['related_person'];
			}

			// Get relationship type - could be term object, array, or ID
			$type_id   = null;
			$type_name = '';
			$type_slug = '';

			if ( is_object( $rel['relationship_type'] ) ) {
				$type_id   = $rel['relationship_type']->term_id;
				$type_name = $rel['relationship_type']->name;
				$type_slug = $rel['relationship_type']->slug;
			} elseif ( is_array( $rel['relationship_type'] ) ) {
				$type_id   = $rel['relationship_type']['term_id'] ?? null;
				$type_name = $rel['relationship_type']['name'] ?? '';
				$type_slug = $rel['relationship_type']['slug'] ?? '';
			} else {
				$type_id = $rel['relationship_type'];
				if ( $type_id ) {
					$term = get_term( $type_id, 'relationship_type' );
					if ( $term && ! is_wp_error( $term ) ) {
						$type_name = $term->name;
						$type_slug = $term->slug;
					}
				}
			}

			// Get person name
			$person_name      = '';
			$person_thumbnail = '';
			if ( $person_id ) {
				$person_name      = get_the_title( $person_id );
				$person_thumbnail = get_the_post_thumbnail_url( $person_id, 'thumbnail' );
			}

			$expanded_relationships[] = [
				'related_person'     => $person_id,
				'person_name'        => $person_name,
				'person_thumbnail'   => $person_thumbnail ?: '',
				'relationship_type'  => $type_id,
				'relationship_name'  => $type_name,
				'relationship_slug'  => $type_slug,
				'relationship_label' => $rel['relationship_label'] ?? '',
			];
		}

		$data['acf']['relationships'] = $expanded_relationships;
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Add computed fields to person REST response
	 * This includes is_deceased and birth_year
	 *
	 * @param WP_REST_Response $response The REST response object.
	 * @param WP_Post $post The post object.
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Modified response with computed fields.
	 */
	public function add_person_computed_fields( $response, $post, $request ) {
		// Return early if response is an error (e.g., unauthorized access)
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();

		// Deceased status field (reserved for future use)
		$data['is_deceased'] = false;

		// Get birth year from birthdate field on person
		$data['birth_year'] = null;
		$birthdate          = get_field( 'birthdate', $post->ID );
		if ( $birthdate ) {
			$year = (int) gmdate( 'Y', strtotime( $birthdate ) );
			if ( $year > 0 ) {
				$data['birth_year'] = $year;
			}
		}

		$response->set_data( $data );

		return $response;
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

		if ( ! $post || $post->post_type !== 'person' ) {
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
	 * Check if current user can bulk update the specified people
	 *
	 * Verifies that the current user owns all posts in the request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool|WP_Error True if user owns all posts, WP_Error otherwise.
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

			if ( ! $post || $post->post_type !== 'person' ) {
				return new \WP_Error(
					'rest_invalid_id',
					sprintf( __( 'Person with ID %d not found.', 'rondo' ), $post_id ),
					[ 'status' => 404 ]
				);
			}

			// Must be post author or admin
			if ( (int) $post->post_author !== $current_user_id && ! $is_admin ) {
				return new \WP_Error(
					'rest_forbidden',
					sprintf( __( 'You do not have permission to update person with ID %d.', 'rondo' ), $post_id ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Bulk update multiple people
	 *
	 * Updates organization for multiple people at once.
	 *
	 * Supported updates:
	 * - organization_id: Team post ID to set as current employer (null to clear)
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response with success/failure details.
	 */
	public function bulk_update_people( $request ) {
		$ids     = $request->get_param( 'ids' );
		$updates = $request->get_param( 'updates' );

		$updated = [];
		$failed  = [];

		foreach ( $ids as $post_id ) {
			try {
				// Update organization assignment if provided
				if ( array_key_exists( 'organization_id', $updates ) ) {
					$org_id = $updates['organization_id'];

					// Get current work_history
					$work_history = get_field( 'work_history', $post_id ) ?: [];

					if ( $org_id === null ) {
						// Clear current organization: set is_current=false on all entries
						foreach ( $work_history as &$job ) {
							$job['is_current'] = false;
						}
						unset( $job ); // Unset reference to avoid issues
					} else {
						// Check if team already exists in work history
						$found = false;
						foreach ( $work_history as &$job ) {
							$job['is_current'] = ( $job['team'] == $org_id );
							if ( $job['team'] == $org_id ) {
								$found = true;
							}
						}
						unset( $job ); // Unset reference to avoid issues

						// If team not in history, add new entry
						if ( ! $found ) {
							$work_history[] = [
								'team'       => $org_id,
								'job_title'  => '',
								'start_date' => '',
								'end_date'   => '',
								'is_current' => true,
							];
						}
					}

					update_field( 'work_history', $work_history, $post_id );
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

	/**
	 * Validate orderby parameter - accepts built-in fields or custom_ prefixed fields.
	 *
	 * @param string $param The orderby value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_orderby_param( $param ) {
		// Check built-in fields first.
		$built_in_fields = [
			'first_name',
			'last_name',
			'modified',
			// Sportlink fields (ACF fields, not from Manager)
			'custom_knvb-id',
			'custom_type-lid',
			'custom_leeftijdsgroep',
			'custom_lid-sinds',
			'custom_datum-foto',
			'custom_datum-vog',
			'custom_isparent',
			'custom_huidig-vrijwilliger',
			'custom_financiele-blokkade',
			'custom_freescout-id',
		];
		if ( in_array( $param, $built_in_fields, true ) ) {
			return true;
		}

		// Check for custom field (must start with 'custom_').
		if ( strpos( $param, 'custom_' ) !== 0 ) {
			return false;
		}

		// Extract field name (remove 'custom_' prefix).
		$field_name = substr( $param, 7 );

		// Get all active custom fields for person entity.
		$manager = new Manager();
		$fields  = $manager->get_fields( 'person', false );

		// Find field by name and validate it's sortable.
		foreach ( $fields as $field ) {
			if ( $field['name'] === $field_name ) {
				// Only allow sortable field types.
				$sortable_types = [ 'text', 'textarea', 'number', 'date', 'select', 'email', 'url', 'true_false' ];
				return in_array( $field['type'], $sortable_types, true );
			}
		}

		// Field not found or inactive.
		return false;
	}

	/**
	 * Get filtered and paginated people
	 *
	 * Returns people with server-side filtering, sorting, and pagination.
	 * Uses optimized $wpdb queries with JOINs to fetch data in minimal queries.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response with people array and pagination info.
	 */
	public function get_filtered_people( $request ) {
		global $wpdb;

		// Extract validated parameters
		$page            = (int) $request->get_param( 'page' );
		$per_page        = (int) $request->get_param( 'per_page' );
		$ownership       = $request->get_param( 'ownership' );
		$modified_days   = $request->get_param( 'modified_days' );
		$birth_year_from = $request->get_param( 'birth_year_from' );
		$birth_year_to   = $request->get_param( 'birth_year_to' );
		$orderby         = $request->get_param( 'orderby' );
		$order           = strtoupper( $request->get_param( 'order' ) );

		// Custom field filter parameters
		$huidig_vrijwilliger  = $request->get_param( 'huidig_vrijwilliger' );
		$financiele_blokkade  = $request->get_param( 'financiele_blokkade' );
		$type_lid             = $request->get_param( 'type_lid' );
		$foto_missing         = $request->get_param( 'foto_missing' );
		$vog_missing          = $request->get_param( 'vog_missing' );
		$vog_older_than_years    = $request->get_param( 'vog_older_than_years' );
		$vog_expiring_within_days = $request->get_param( 'vog_expiring_within_days' );
		$vog_email_status        = $request->get_param( 'vog_email_status' );
		$vog_type             = $request->get_param( 'vog_type' );
		$leeftijdsgroep       = $request->get_param( 'leeftijdsgroep' );
		$vog_justis_status    = $request->get_param( 'vog_justis_status' );
		$vog_reminder_status  = $request->get_param( 'vog_reminder_status' );
		$include_former       = $request->get_param( 'include_former' );
		$lid_tot_future       = $request->get_param( 'lid_tot_future' );

		// Double-check access control (permission_callback should have caught this,
		// but custom $wpdb queries bypass pre_get_posts hooks, so we verify explicitly)
		$access_control = new \Rondo\Core\AccessControl();
		if ( ! $access_control->is_user_approved() ) {
			return rest_ensure_response(
				[
					'people'      => [],
					'total'       => 0,
					'page'        => $page,
					'total_pages' => 0,
				]
			);
		}

		$offset = ( $page - 1 ) * $per_page;

		// Check if VOG-only user (should only see volunteers)
		$volunteers_only = $access_control->should_filter_volunteers_only();

		// Build query components
		$select_fields  = 'p.ID, p.post_modified, p.post_author';
		$join_clauses   = [];
		$where_clauses  = [
			"p.post_type = 'person'",
			"p.post_status = 'publish'",
		];
		$prepare_values = [];

		// Former member handling
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} fm ON p.ID = fm.post_id AND fm.meta_key = 'former_member'";
		if ( $include_former !== '1' ) {
			// Default: exclude former members
			$where_clauses[] = "(fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0')";
		}
		$select_fields .= ', fm.meta_value AS is_former_member';

		// Always JOIN meta for first_name, infix, and last_name (needed for display and sorting)
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} fn ON p.ID = fn.post_id AND fn.meta_key = 'first_name'";
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} ix ON p.ID = ix.post_id AND ix.meta_key = 'infix'";
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} ln ON p.ID = ln.post_id AND ln.meta_key = 'last_name'";
		$select_fields .= ', fn.meta_value AS first_name, ix.meta_value AS infix, ln.meta_value AS last_name';

		// VOG-only users can only see volunteers
		if ( $volunteers_only ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} vog_hv ON p.ID = vog_hv.post_id AND vog_hv.meta_key = 'huidig-vrijwilliger'";
			$where_clauses[] = "(vog_hv.meta_value = '1')";
		}

		// Ownership filter
		if ( $ownership === 'mine' ) {
			$where_clauses[]  = 'p.post_author = %d';
			$prepare_values[] = get_current_user_id();
		} elseif ( $ownership === 'shared' ) {
			$where_clauses[]  = 'p.post_author != %d';
			$prepare_values[] = get_current_user_id();
		}

		// Modified date filter
		if ( $modified_days !== null ) {
			$date_threshold   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$modified_days} days" ) );
			$where_clauses[]  = 'p.post_modified >= %s';
			$prepare_values[] = $date_threshold;
		}

		// Birth year filter (uses denormalized _birthdate meta from Phase 112)
		if ( $birth_year_from !== null || $birth_year_to !== null ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} bd ON p.ID = bd.post_id AND bd.meta_key = '_birthdate'";

			if ( $birth_year_from !== null && $birth_year_to !== null ) {
				// Range filter
				$where_clauses[]  = 'YEAR(bd.meta_value) BETWEEN %d AND %d';
				$prepare_values[] = $birth_year_from;
				$prepare_values[] = $birth_year_to;
			} elseif ( $birth_year_from !== null ) {
				// Minimum year only (treat as exact match for single year)
				$where_clauses[]  = 'YEAR(bd.meta_value) = %d';
				$prepare_values[] = $birth_year_from;
			} else {
				// Maximum year only (treat as exact match for single year)
				$where_clauses[]  = 'YEAR(bd.meta_value) = %d';
				$prepare_values[] = $birth_year_to;
			}
		}

		// Custom field filters
		// Note: These use hardcoded field names for the specific Sportlink integration fields

		// Huidig vrijwilliger (current volunteer) - boolean filter
		if ( $huidig_vrijwilliger !== null && $huidig_vrijwilliger !== '' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} hv ON p.ID = hv.post_id AND hv.meta_key = 'huidig-vrijwilliger'";
			$where_clauses[] = $huidig_vrijwilliger === '1'
				? "(hv.meta_value = '1')"
				: "(hv.meta_value IS NULL OR hv.meta_value = '' OR hv.meta_value = '0')";
		}

		// Financiele blokkade (financial block) - boolean filter
		if ( $financiele_blokkade !== null && $financiele_blokkade !== '' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} fb ON p.ID = fb.post_id AND fb.meta_key = 'financiele-blokkade'";
			$where_clauses[] = $financiele_blokkade === '1'
				? "(fb.meta_value = '1' OR fb.meta_value = 'Ja')"
				: "(fb.meta_value IS NULL OR fb.meta_value = '' OR fb.meta_value = '0' OR fb.meta_value = 'Nee')";
		}

		// Type lid (member type) - select filter
		if ( ! empty( $type_lid ) ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} tl ON p.ID = tl.post_id AND tl.meta_key = 'type-lid'";
			$where_clauses[]  = 'tl.meta_value = %s';
			$prepare_values[] = $type_lid;
		}

		// Leeftijdsgroep (age group) - select filter
		if ( ! empty( $leeftijdsgroep ) ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} lg ON p.ID = lg.post_id AND lg.meta_key = 'leeftijdsgroep'";
			$where_clauses[]  = 'lg.meta_value = %s';
			$prepare_values[] = $leeftijdsgroep;
		}

		// Datum foto (photo date) - missing filter
		if ( $foto_missing === '1' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} df ON p.ID = df.post_id AND df.meta_key = 'datum-foto'";
			$where_clauses[] = "(df.meta_value IS NULL OR df.meta_value = '')";
		}

		// Datum VOG filtering based on vog_type
		if ( $vog_type === 'nieuw' ) {
			// Only show people WITHOUT a VOG date
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$where_clauses[] = "(dv.meta_value IS NULL OR dv.meta_value = '')";
		} elseif ( $vog_type === 'vernieuwing' && $vog_older_than_years !== null ) {
			// Only show people WITH an expired VOG date
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
			$where_clauses[]  = "(dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value <= %s)";
			$prepare_values[] = $cutoff_date;
		} elseif ( $vog_missing === '1' && $vog_older_than_years !== null ) {
			// Default: OR both conditions (show all needing VOG)
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
			$where_clauses[]  = "((dv.meta_value IS NULL OR dv.meta_value = '') OR (dv.meta_value <= %s))";
			$prepare_values[] = $cutoff_date;
		} elseif ( $vog_missing === '1' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$where_clauses[] = "(dv.meta_value IS NULL OR dv.meta_value = '')";
		} elseif ( $vog_older_than_years !== null ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
			$where_clauses[]  = "(dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value <= %s)";
			$prepare_values[] = $cutoff_date;
		} elseif ( $vog_expiring_within_days !== null ) {
			// Find people whose VOG is still valid but will expire within N days.
			// VOG validity = 3 years. Expiry = datum-vog + 3 years.
			// We want: today < expiry <= today + N days
			// Which means: today - 3 years < datum-vog <= today + N days - 3 years
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$expired_date     = gmdate( 'Y-m-d', strtotime( '-3 years' ) );
			$expiring_date    = gmdate( 'Y-m-d', strtotime( "+{$vog_expiring_within_days} days -3 years" ) );
			$where_clauses[]  = "(dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value > %s AND dv.meta_value <= %s)";
			$prepare_values[] = $expired_date;
			$prepare_values[] = $expiring_date;
		}

		// VOG email status filter (sent/not_sent based on vog_email_sent_date meta field)
		if ( $vog_email_status !== null && $vog_email_status !== '' ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} ves ON p.ID = ves.post_id AND ves.meta_key = 'vog_email_sent_date'";

			if ( $vog_email_status === 'sent' ) {
				$where_clauses[] = "(ves.meta_value IS NOT NULL AND ves.meta_value != '')";
			} elseif ( $vog_email_status === 'not_sent' ) {
				$where_clauses[] = "(ves.meta_value IS NULL OR ves.meta_value = '')";
			}
		}

		// VOG Justis status filter (submitted/not_submitted based on vog_justis_submitted_date meta field)
		if ( $vog_justis_status !== null && $vog_justis_status !== '' ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} vjs ON p.ID = vjs.post_id AND vjs.meta_key = 'vog_justis_submitted_date'";

			if ( $vog_justis_status === 'submitted' ) {
				$where_clauses[] = "(vjs.meta_value IS NOT NULL AND vjs.meta_value != '')";
			} elseif ( $vog_justis_status === 'not_submitted' ) {
				$where_clauses[] = "(vjs.meta_value IS NULL OR vjs.meta_value = '')";
			}
		}

		// VOG Reminder status filter (sent/not_sent based on vog_reminder_sent_date meta field)
		if ( $vog_reminder_status !== null && $vog_reminder_status !== '' ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} vrs ON p.ID = vrs.post_id AND vrs.meta_key = 'vog_reminder_sent_date'";

			if ( $vog_reminder_status === 'sent' ) {
				$where_clauses[] = "(vrs.meta_value IS NOT NULL AND vrs.meta_value != '')";
			} elseif ( $vog_reminder_status === 'not_sent' ) {
				$where_clauses[] = "(vrs.meta_value IS NULL OR vrs.meta_value = '')";
			}
		}

		// Lid-tot (membership end date) future filter
		if ( $lid_tot_future === '1' ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} lt ON p.ID = lt.post_id AND lt.meta_key = 'lid-tot'";
			$today            = gmdate( 'Y-m-d' );
			$where_clauses[]  = "(lt.meta_value IS NOT NULL AND lt.meta_value != '' AND lt.meta_value >= %s)";
			$prepare_values[] = $today;
		}

		// Build ORDER BY clause (columns are whitelisted in args validation)
		// ORDER and orderby are safe - validated against whitelist
		switch ( $orderby ) {
			case 'first_name':
				$order_clause = "ORDER BY fn.meta_value $order, ln.meta_value $order";
				break;
			case 'last_name':
				$order_clause = "ORDER BY ln.meta_value $order, fn.meta_value $order";
				break;
			case 'modified':
				$order_clause = "ORDER BY p.post_modified $order";
				break;
			case 'custom_datum-vog':
				// ACF date field - not a custom field from Manager, so handle explicitly
				$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = 'datum-vog'";
				$order_clause   = "ORDER BY COALESCE(cf.meta_value, '') $order, fn.meta_value ASC";
				break;
			case 'custom_lid-sinds':
			case 'custom_datum-foto':
				// ACF date fields (not from Manager)
				$field_name     = substr( $orderby, 7 ); // Remove 'custom_' prefix
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				$order_clause   = "ORDER BY STR_TO_DATE(cf.meta_value, '%%Y-%%m-%%d') $order, fn.meta_value ASC";
				break;
			case 'custom_isparent':
			case 'custom_huidig-vrijwilliger':
			case 'custom_financiele-blokkade':
				// Boolean ACF fields
				$field_name     = substr( $orderby, 7 );
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				$order_clause   = "ORDER BY CAST(COALESCE(cf.meta_value, '0') AS UNSIGNED) $order, fn.meta_value ASC";
				break;
			case 'custom_freescout-id':
				// Numeric ACF field
				$field_name     = substr( $orderby, 7 );
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				$order_clause   = "ORDER BY CAST(cf.meta_value AS DECIMAL(10,2)) $order, fn.meta_value ASC";
				break;
			case 'custom_knvb-id':
			case 'custom_type-lid':
				// Text ACF fields
				$field_name     = substr( $orderby, 7 );
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				$order_clause   = "ORDER BY COALESCE(cf.meta_value, '') $order, fn.meta_value ASC";
				break;
			case 'custom_leeftijdsgroep':
				// ACF field with custom age group sorting logic
				$field_name     = substr( $orderby, 7 );
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				// Custom sort for leeftijdsgroep: Onder 6 < Onder 7 < ... < Onder 19 < Senioren
				$order_clause   = "ORDER BY
					CASE
						WHEN cf.meta_value LIKE 'Onder %' THEN CAST(SUBSTRING(cf.meta_value, 7) AS UNSIGNED)
						WHEN cf.meta_value LIKE 'Senioren%' THEN 99
						ELSE 100
					END $order,
					CASE
						WHEN cf.meta_value LIKE '%Meiden%' OR cf.meta_value LIKE '%Vrouwen%' THEN 1
						ELSE 0
					END $order,
					fn.meta_value ASC";
				break;
			default:
				// Check if this is a custom field (starts with 'custom_')
				if ( strpos( $orderby, 'custom_' ) === 0 ) {
					$field_name = substr( $orderby, 7 );

					// Get the field definition to determine type-appropriate sorting
					$manager    = new Manager();
					$fields     = $manager->get_fields( 'person', false );
					$field_type = null;

					foreach ( $fields as $field ) {
						if ( $field['name'] === $field_name ) {
							$field_type = $field['type'];
							break;
						}
					}

					// Add LEFT JOIN for the custom field meta
					$join_clauses[] = $wpdb->prepare(
						"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
						$field_name
					);

					// Build type-appropriate ORDER BY clause
					// Always include first_name as secondary sort for consistent ordering
					if ( $field_name === 'leeftijdsgroep' ) {
						// Custom sort for leeftijdsgroep: Onder 6 < Onder 7 < ... < Onder 19 < Senioren
						// Extract numeric part from "Onder X" values, treat "Senioren" as 99
						$order_clause = "ORDER BY
							CASE
								WHEN cf.meta_value LIKE 'Onder %' THEN CAST(SUBSTRING(cf.meta_value, 7) AS UNSIGNED)
								WHEN cf.meta_value LIKE 'Senioren%' THEN 99
								ELSE 100
							END $order,
							CASE
								WHEN cf.meta_value LIKE '%Meiden%' OR cf.meta_value LIKE '%Vrouwen%' THEN 1
								ELSE 0
							END $order,
							fn.meta_value ASC";
					} elseif ( $field_type === 'number' ) {
						// Numeric sort with NULLS LAST
						$order_clause = "ORDER BY CAST(cf.meta_value AS DECIMAL(10,2)) $order, fn.meta_value ASC";
					} elseif ( $field_type === 'date' ) {
						// Date sort (ACF stores dates as Y-m-d format) with NULLS LAST
						// Double %% to escape for wpdb->prepare()
						$order_clause = "ORDER BY STR_TO_DATE(cf.meta_value, '%%Y-%%m-%%d') $order, fn.meta_value ASC";
					} elseif ( $field_type === 'true_false' ) {
						// Boolean sort (ACF stores as 1 or 0/empty) - cast to integer
						$order_clause = "ORDER BY CAST(COALESCE(cf.meta_value, '0') AS UNSIGNED) $order, fn.meta_value ASC";
					} else {
						// Text-based sort (text, textarea, select, email, url) with NULLS LAST
						$order_clause = "ORDER BY COALESCE(cf.meta_value, '') $order, fn.meta_value ASC";
					}
				} else {
					// Fallback to first_name
					$order_clause = "ORDER BY fn.meta_value $order";
				}
		}

		// Combine clauses
		$join_sql  = implode( ' ', $join_clauses );
		$where_sql = implode( ' AND ', $where_clauses );

		// Main query with DISTINCT (needed when filtering by taxonomy to avoid duplicates)
		$main_sql = "SELECT DISTINCT $select_fields
					 FROM {$wpdb->posts} p
					 $join_sql
					 WHERE $where_sql
					 $order_clause";

		// Add pagination
		$prepare_values[] = $per_page;
		$prepare_values[] = $offset;
		$paginated_sql    = $main_sql . ' LIMIT %d OFFSET %d';

		// Prepare and execute main query
		$prepared_sql = $wpdb->prepare( $paginated_sql, $prepare_values );
		$results      = $wpdb->get_results( $prepared_sql );

		// Count query (same joins/where, no order/limit)
		// Need to rebuild prepare_values without the pagination values
		$count_prepare_values = array_slice( $prepare_values, 0, -2 );
		$count_sql            = "SELECT COUNT(DISTINCT p.ID)
								 FROM {$wpdb->posts} p
								 $join_sql
								 WHERE $where_sql";

		if ( ! empty( $count_prepare_values ) ) {
			$prepared_count_sql = $wpdb->prepare( $count_sql, $count_prepare_values );
		} else {
			$prepared_count_sql = $count_sql;
		}
		$total = (int) $wpdb->get_var( $prepared_count_sql );

		// Format results
		$people = [];
		foreach ( $results as $row ) {
			$person = [
				'id'            => (int) $row->ID,
				'first_name'    => $this->sanitize_text( $row->first_name ?: '' ),
				'infix'         => $this->sanitize_text( $row->infix ?: '' ),
				'last_name'     => $this->sanitize_text( $row->last_name ?: '' ),
				'modified'      => $row->post_modified,
				'former_member' => ( $row->is_former_member === '1' ),
				// These are fetched post-query to avoid complex JOINs
				'thumbnail'     => $this->sanitize_url( get_the_post_thumbnail_url( $row->ID, 'thumbnail' ) ),
			];

			// Add ACF fields for custom field columns
			if ( function_exists( 'get_fields' ) ) {
				$acf_fields = get_fields( $row->ID );
				if ( $acf_fields ) {
					$person['acf'] = $acf_fields;
				}
			}

			// Add VOG-related post meta fields to acf array for frontend consistency
			$vog_email_sent = get_post_meta( $row->ID, 'vog_email_sent_date', true );
			$vog_justis     = get_post_meta( $row->ID, 'vog_justis_submitted_date', true );
			$vog_reminder   = get_post_meta( $row->ID, 'vog_reminder_sent_date', true );
			if ( $vog_email_sent ) {
				$person['acf']['vog_email_sent_date'] = $vog_email_sent;
			}
			if ( $vog_justis ) {
				$person['acf']['vog_justis_submitted_date'] = $vog_justis;
			}
			if ( $vog_reminder ) {
				$person['acf']['vog_reminder_sent_date'] = $vog_reminder;
			}

			$people[] = $person;
		}

		return rest_ensure_response(
			[
				'people'      => $people,
				'total'       => $total,
				'page'        => $page,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);
	}

	/**
	 * Get dynamic filter configuration
	 *
	 * Maps filter keys to their meta_key and sort method.
	 * This makes adding future dynamic filters trivial.
	 *
	 * @return array Filter configuration.
	 */
	private function get_dynamic_filter_config() {
		return [
			'age_groups'   => [
				'meta_key'    => 'leeftijdsgroep',
				'sort_method' => 'sort_age_groups',
			],
			'member_types' => [
				'meta_key'    => 'type-lid',
				'sort_method' => 'sort_member_types',
			],
		];
	}

	/**
	 * Get filter options with counts
	 *
	 * Returns dynamic filter options for the People list.
	 * Each option includes the value and count of matching people.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response with filter options and counts.
	 */
	public function get_filter_options( $request ) {
		global $wpdb;

		// Double-check access control (permission_callback should have caught this)
		$access_control = new \Rondo\Core\AccessControl();
		if ( ! $access_control->is_user_approved() ) {
			return rest_ensure_response(
				[
					'total'        => 0,
					'age_groups'   => [],
					'member_types' => [],
				]
			);
		}

		// Get total count of published person posts
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->posts}
			 WHERE post_type = 'person'
			   AND post_status = 'publish'"
		);

		$result = [ 'total' => $total ];

		// Get filter configuration
		$filters = $this->get_dynamic_filter_config();

		// Query each filter
		foreach ( $filters as $filter_key => $config ) {
			$meta_key    = $config['meta_key'];
			$sort_method = $config['sort_method'];

			// Query DISTINCT meta_values with COUNT using GROUP BY
			$sql = $wpdb->prepare(
				"SELECT pm.meta_value AS value, COUNT(DISTINCT p.ID) AS count
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = 'person'
				   AND p.post_status = 'publish'
				   AND pm.meta_key = %s
				   AND pm.meta_value != ''
				 GROUP BY pm.meta_value
				 HAVING count > 0",
				$meta_key
			);

			$rows = $wpdb->get_results( $sql );

			// Convert to array with value and count
			$options = [];
			foreach ( $rows as $row ) {
				$options[] = [
					'value' => $row->value,
					'count' => (int) $row->count,
				];
			}

			// Apply sort method
			if ( method_exists( $this, $sort_method ) ) {
				$options = $this->$sort_method( $options );
			}

			$result[ $filter_key ] = $options;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Sort age groups intelligently
	 *
	 * Sorts age groups youngest to oldest with smart numeric extraction.
	 * - "Onder 6" before "Onder 7", etc.
	 * - Within same number, base value before gender variant (e.g., "Onder 9" before "Onder 9 Meiden")
	 * - Non-numeric values (e.g., "Senioren", "Senioren Vrouwen") sort to end
	 * - Among non-numeric, base before gender variant
	 *
	 * @param array $options Array of options with value and count.
	 * @return array Sorted array.
	 */
	private function sort_age_groups( $options ) {
		usort(
			$options,
			function ( $a, $b ) {
				$a_value = $a['value'];
				$b_value = $b['value'];

				// Extract numeric part from "Onder X" pattern
				$a_has_number = preg_match( '/(\d+)/', $a_value, $a_matches );
				$b_has_number = preg_match( '/(\d+)/', $b_value, $b_matches );

				$a_number = $a_has_number ? (int) $a_matches[1] : 9999;
				$b_number = $b_has_number ? (int) $b_matches[1] : 9999;

				// Primary sort: by number (youngest to oldest)
				if ( $a_number !== $b_number ) {
					return $a_number - $b_number;
				}

				// Secondary sort: gender variants after base groups
				$a_has_gender = strpos( $a_value, 'Meiden' ) !== false || strpos( $a_value, 'Vrouwen' ) !== false;
				$b_has_gender = strpos( $b_value, 'Meiden' ) !== false || strpos( $b_value, 'Vrouwen' ) !== false;

				if ( $a_has_gender !== $b_has_gender ) {
					return $a_has_gender ? 1 : -1;
				}

				// Tertiary sort: alphabetical
				return strcmp( $a_value, $b_value );
			}
		);

		return $options;
	}

	/**
	 * Sort member types in priority order
	 *
	 * Sorts member types in meaningful priority order.
	 * Values not in priority array sort to end (allows new types from sync to appear automatically).
	 *
	 * @param array $options Array of options with value and count.
	 * @return array Sorted array.
	 */
	private function sort_member_types( $options ) {
		$priority = [
			'Junior'             => 1,
			'Senior'             => 2,
			'Donateur'           => 3,
			'Lid van Verdienste' => 4,
		];

		usort(
			$options,
			function ( $a, $b ) use ( $priority ) {
				$a_priority = $priority[ $a['value'] ] ?? 99;
				$b_priority = $priority[ $b['value'] ] ?? 99;

				if ( $a_priority !== $b_priority ) {
					return $a_priority - $b_priority;
				}

				// Same priority: alphabetical
				return strcmp( $a['value'], $b['value'] );
			}
		);

		return $options;
	}
}
