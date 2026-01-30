<?php
/**
 * People REST API Endpoints
 *
 * Handles REST API endpoints related to people domain.
 */

namespace Stadion\REST;

use Stadion\CustomFields\Manager;

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
			'stadion/v1',
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
			'stadion/v1',
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
			'stadion/v1',
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
			'stadion/v1',
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
			'stadion/v1',
			'/people/(?P<id>\d+)/shares/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_share' ],
				'permission_callback' => [ $this, 'check_post_owner' ],
			]
		);

		// Bulk update endpoint
		register_rest_route(
			'stadion/v1',
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
							$has_update = array_key_exists( 'organization_id', $param )
								|| isset( $param['labels_add'] )
								|| isset( $param['labels_remove'] );
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

		// Filtered people with server-side pagination, filtering, and sorting
		register_rest_route(
			'stadion/v1',
			'/people/filtered',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_filtered_people' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'page'          => [
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					],
					'per_page'      => [
						'default'           => 100,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0 && (int) $param <= 100;
						},
						'sanitize_callback' => 'absint',
					],
					'labels'        => [
						'default'           => [],
						'validate_callback' => function ( $param ) {
							if ( ! is_array( $param ) ) {
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
							return array_map( 'absint', $param );
						},
					],
					'ownership'     => [
						'default'           => 'all',
						'validate_callback' => function ( $param ) {
							return in_array( $param, [ 'mine', 'shared', 'all' ], true );
						},
					],
					'modified_days' => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || $param === '' || ( is_numeric( $param ) && (int) $param > 0 );
						},
						'sanitize_callback' => function ( $param ) {
							return $param === null || $param === '' ? null : absint( $param );
						},
					],
					'orderby'       => [
						'default'           => 'first_name',
						'validate_callback' => [ $this, 'validate_orderby_param' ],
					],
					'order'         => [
						'default'           => 'asc',
						'validate_callback' => function ( $param ) {
							return in_array( strtolower( $param ), [ 'asc', 'desc' ], true );
						},
						'sanitize_callback' => function ( $param ) {
							return strtolower( $param );
						},
					],
					'birth_year_from' => [
						'description'       => 'Filter by birth year (minimum year, inclusive)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1900 && $value <= 2100;
						},
					],
					'birth_year_to' => [
						'description'       => 'Filter by birth year (maximum year, inclusive)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1900 && $value <= 2100;
						},
					],
					// Custom field filters
					'huidig_vrijwilliger' => [
						'description'       => 'Filter by current volunteer status (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'financiele_blokkade' => [
						'description'       => 'Filter by financial block status (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'type_lid' => [
						'description'       => 'Filter by member type',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'foto_missing' => [
						'description'       => 'Filter for people without photo date (1=missing, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'vog_missing' => [
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
					'vog_email_status' => [
						'description'       => 'Filter by VOG email status (sent, not_sent, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'sent', 'not_sent' ], true );
						},
					],
				],
			]
		);
	}

	/**
	 * Get dates related to a person
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing formatted dates.
	 */
	public function get_dates_by_person( $request ) {
		$person_id = $request->get_param( 'person_id' );

		// Query dates where this person is in the related_people field
		// ACF stores post_object arrays as serialized PHP arrays
		// Try both formats: serialized array and quoted JSON-style (for backward compatibility)
		// suppress_filters bypasses access control - safe because check_person_access already verified
		// the user can access this person
		$dates = get_posts(
			[
				'post_type'        => 'important_date',
				'posts_per_page'   => -1,
				'post_status'      => 'publish',
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'OR',
					[
						'key'     => 'related_people',
						'value'   => serialize( strval( $person_id ) ),
						'compare' => 'LIKE',
					],
					[
						'key'     => 'related_people',
						'value'   => '"' . $person_id . '"',
						'compare' => 'LIKE',
					],
				],
			]
		);

		$formatted = array_map( [ $this, 'format_date' ], $dates );

		return rest_ensure_response( $formatted );
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
			return new \WP_Error( 'missing_email', __( 'Email address is required.', 'stadion' ), [ 'status' => 400 ] );
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
			return new \WP_Error( 'download_failed', __( 'Failed to download Gravatar image.', 'stadion' ), [ 'status' => 500 ] );
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
		$attachment_id = media_handle_sideload( $file_array, $person_id, sprintf( __( '%s Gravatar', 'stadion' ), $first_name . ' ' . $last_name ) );

		// Clean up temp file if sideload failed
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return new \WP_Error( 'sideload_failed', __( 'Failed to sideload Gravatar image.', 'stadion' ), [ 'status' => 500 ] );
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
			return new \WP_Error( 'person_not_found', __( 'Person not found.', 'stadion' ), [ 'status' => 404 ] );
		}

		// Check for uploaded file
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'stadion' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		// Validate file type
		$allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' ];
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid file type. Please upload an image.', 'stadion' ), [ 'status' => 400 ] );
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

		// Get all dates for this person to compute deceased status and birth year
		// ACF stores post_object arrays as serialized PHP arrays
		// Try both formats: serialized array and quoted JSON-style (for backward compatibility)
		// suppress_filters bypasses access control - safe because this filter only runs for
		// persons the user already has access to (via rest_prepare_person filter)
		$person_dates = get_posts(
			[
				'post_type'        => 'important_date',
				'posts_per_page'   => -1,
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'OR',
					[
						'key'     => 'related_people',
						'value'   => serialize( strval( $post->ID ) ),
						'compare' => 'LIKE',
					],
					[
						'key'     => 'related_people',
						'value'   => '"' . $post->ID . '"',
						'compare' => 'LIKE',
					],
				],
			]
		);

		$data['is_deceased'] = false;
		$data['birth_year']  = null;

		foreach ( $person_dates as $date_post ) {
			$date_types = wp_get_post_terms( $date_post->ID, 'date_type', [ 'fields' => 'slugs' ] );

			// Check for deceased status
			if ( in_array( 'died', $date_types ) ) {
				$data['is_deceased'] = true;
			}

			// Check for birthday and extract year (only if year is known)
			if ( in_array( 'birthday', $date_types ) ) {
				$year_unknown = get_field( 'year_unknown', $date_post->ID );
				if ( ! $year_unknown ) {
					$date_value = get_field( 'date_value', $date_post->ID );
					if ( $date_value ) {
						$year = (int) gmdate( 'Y', strtotime( $date_value ) );
						if ( $year > 0 ) {
							$data['birth_year'] = $year;
						}
					}
				}
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
			return new \WP_Error( 'invalid_user', __( 'User not found.', 'stadion' ), [ 'status' => 404 ] );
		}

		// Can't share with yourself
		if ( $user_id === get_current_user_id() ) {
			return new \WP_Error( 'invalid_share', __( 'Cannot share with yourself.', 'stadion' ), [ 'status' => 400 ] );
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
						'message' => __( 'Share updated.', 'stadion' ),
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
		$shares = array_values( $shares ); // Re-index

		update_field( '_shared_with', $shares, $post_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Share removed.', 'stadion' ),
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
				__( 'You must be logged in to perform this action.', 'stadion' ),
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
					sprintf( __( 'Person with ID %d not found.', 'stadion' ), $post_id ),
					[ 'status' => 404 ]
				);
			}

			// Must be post author or admin
			if ( (int) $post->post_author !== $current_user_id && ! $is_admin ) {
				return new \WP_Error(
					'rest_forbidden',
					sprintf( __( 'You do not have permission to update person with ID %d.', 'stadion' ), $post_id ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Bulk update multiple people
	 *
	 * Updates organization and/or labels for multiple people at once.
	 *
	 * Supported updates:
	 * - organization_id: Team post ID to set as current employer (null to clear)
	 * - labels_add: Array of person_label term IDs to add
	 * - labels_remove: Array of person_label term IDs to remove
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

				// Add labels if provided (append, don't replace)
				if ( ! empty( $updates['labels_add'] ) ) {
					$term_ids = array_map( 'intval', $updates['labels_add'] );
					wp_set_object_terms( $post_id, $term_ids, 'person_label', true );
				}

				// Remove labels if provided
				if ( ! empty( $updates['labels_remove'] ) ) {
					$term_ids = array_map( 'intval', $updates['labels_remove'] );
					wp_remove_object_terms( $post_id, $term_ids, 'person_label' );
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
		$built_in_fields = [ 'first_name', 'last_name', 'modified' ];
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
		$labels          = $request->get_param( 'labels' ) ?: [];
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
		$vog_older_than_years = $request->get_param( 'vog_older_than_years' );
		$vog_email_status     = $request->get_param( 'vog_email_status' );

		// Double-check access control (permission_callback should have caught this,
		// but custom $wpdb queries bypass pre_get_posts hooks, so we verify explicitly)
		$access_control = new \Stadion\Core\AccessControl();
		if ( ! $access_control->is_user_approved() ) {
			return rest_ensure_response( [
				'people'      => [],
				'total'       => 0,
				'page'        => $page,
				'total_pages' => 0,
			] );
		}

		$offset = ( $page - 1 ) * $per_page;

		// Build query components
		$select_fields  = "p.ID, p.post_modified, p.post_author";
		$join_clauses   = [];
		$where_clauses  = [
			"p.post_type = 'person'",
			"p.post_status = 'publish'",
		];
		$prepare_values = [];

		// Always JOIN meta for first_name and last_name (needed for display and sorting)
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} fn ON p.ID = fn.post_id AND fn.meta_key = 'first_name'";
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} ln ON p.ID = ln.post_id AND ln.meta_key = 'last_name'";
		$select_fields .= ", fn.meta_value AS first_name, ln.meta_value AS last_name";

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

		// Label filter (taxonomy terms with OR logic)
		if ( ! empty( $labels ) ) {
			$join_clauses[] = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
			$join_clauses[] = "INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'person_label'";

			$placeholders     = implode( ',', array_fill( 0, count( $labels ), '%d' ) );
			$where_clauses[]  = "tt.term_id IN ($placeholders)";
			$prepare_values   = array_merge( $prepare_values, $labels );
		}

		// Birth year filter (uses denormalized _birthdate meta from Phase 112)
		if ( $birth_year_from !== null || $birth_year_to !== null ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} bd ON p.ID = bd.post_id AND bd.meta_key = '_birthdate'";

			if ( $birth_year_from !== null && $birth_year_to !== null ) {
				// Range filter
				$where_clauses[]  = "YEAR(bd.meta_value) BETWEEN %d AND %d";
				$prepare_values[] = $birth_year_from;
				$prepare_values[] = $birth_year_to;
			} elseif ( $birth_year_from !== null ) {
				// Minimum year only (treat as exact match for single year)
				$where_clauses[]  = "YEAR(bd.meta_value) = %d";
				$prepare_values[] = $birth_year_from;
			} else {
				// Maximum year only (treat as exact match for single year)
				$where_clauses[]  = "YEAR(bd.meta_value) = %d";
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
			$where_clauses[]  = "tl.meta_value = %s";
			$prepare_values[] = $type_lid;
		}

		// Datum foto (photo date) - missing filter
		if ( $foto_missing === '1' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} df ON p.ID = df.post_id AND df.meta_key = 'datum-foto'";
			$where_clauses[] = "(df.meta_value IS NULL OR df.meta_value = '')";
		}

		// Datum VOG - missing or older than N years filter
		// When both are set, OR them together (show both new volunteers AND renewals)
		if ( $vog_missing === '1' && $vog_older_than_years !== null ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
			$where_clauses[]  = "((dv.meta_value IS NULL OR dv.meta_value = '') OR (dv.meta_value < %s))";
			$prepare_values[] = $cutoff_date;
		} elseif ( $vog_missing === '1' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$where_clauses[] = "(dv.meta_value IS NULL OR dv.meta_value = '')";
		} elseif ( $vog_older_than_years !== null ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
			$where_clauses[]  = "(dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value < %s)";
			$prepare_values[] = $cutoff_date;
		}

		// VOG email status filter (sent/not_sent based on stadion_email comments)
		if ( ! empty( $vog_email_status ) ) {
			// Subquery to find people with email comments
			$email_subquery = "SELECT DISTINCT comment_post_ID FROM {$wpdb->comments} WHERE comment_type = 'stadion_email'";

			if ( $vog_email_status === 'sent' ) {
				$where_clauses[] = "p.ID IN ($email_subquery)";
			} elseif ( $vog_email_status === 'not_sent' ) {
				$where_clauses[] = "p.ID NOT IN ($email_subquery)";
			}
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
			default:
				// Check if this is a custom field (starts with 'custom_')
				if ( strpos( $orderby, 'custom_' ) === 0 ) {
					$field_name = substr( $orderby, 7 );

					// Get the field definition to determine type-appropriate sorting
					$manager = new Manager();
					$fields  = $manager->get_fields( 'person', false );
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
					if ( $field_type === 'number' ) {
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
				'id'         => (int) $row->ID,
				'first_name' => $this->sanitize_text( $row->first_name ?: '' ),
				'last_name'  => $this->sanitize_text( $row->last_name ?: '' ),
				'modified'   => $row->post_modified,
				// These are fetched post-query to avoid complex JOINs
				'thumbnail'  => $this->sanitize_url( get_the_post_thumbnail_url( $row->ID, 'thumbnail' ) ),
				'labels'     => wp_get_post_terms( $row->ID, 'person_label', [ 'fields' => 'names' ] ),
			];

			// Add ACF fields for custom field columns
			if ( function_exists( 'get_fields' ) ) {
				$acf_fields = get_fields( $row->ID );
				if ( $acf_fields ) {
					$person['acf'] = $acf_fields;
				}
			}

			$people[] = $person;
		}

		return rest_ensure_response( [
			'people'      => $people,
			'total'       => $total,
			'page'        => $page,
			'total_pages' => (int) ceil( $total / $per_page ),
		] );
	}
}
