<?php
/**
 * Commissies REST API Endpoints
 *
 * Handles REST API endpoints related to commissies domain.
 */

namespace Rondo\REST;

use InvalidArgumentException;
use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Fields\RestFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Commissies extends Base {

	/** Rondo-local fields that the board may maintain independently of Sportlink. */
	private const LOCAL_INFO_FIELDS = [
		'lange_omschrijving',
		'taakomschrijving',
		'uren_aantal',
		'uren_periode',
		'dagen_flexibel',
		'max_leden',
		'max_wachtlijst',
	];

	/**
	 * Post type for sharing permission checks.
	 */
	protected $sharing_post_type = 'commissie';

	/**
	 * Constructor
	 *
	 * Register routes for commissie endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_prepare_commissie', [ $this, 'add_member_count_to_response' ], 10, 3 );
	}

	/**
	 * Register custom REST routes for commissies domain
	 */
	public function register_routes() {
		// Member counts load independently so they do not block the commissie list.
		register_rest_route(
			'rondo/v1',
			'/commissies/member-counts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_member_counts' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// Rondo-local commissie information. Core identity remains read-only.
		register_rest_route(
			'rondo/v1',
			'/commissies/(?P<commissie_id>\d+)/info',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_commissie_info' ],
				'permission_callback' => [ $this, 'check_commissie_info_edit_permission' ],
				'args'                => [
					'commissie_id' => [
						'validate_callback' => static fn( $param ) => is_numeric( $param ),
					],
				],
			]
		);

		// People by commissie
		register_rest_route(
			'rondo/v1',
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
			'rondo/v1',
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
					'media_id'     => [
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
			'rondo/v1',
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
			'rondo/v1',
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
			'rondo/v1',
			'/commissies/(?P<id>\d+)/shares/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_share' ],
				'permission_callback' => [ $this, 'check_post_owner' ],
			]
		);
	}

	/**
	 * Check whether the current user may edit local commissie information.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_commissie_info_edit_permission( $request ): bool {
		$commissie = get_post( (int) $request->get_param( 'commissie_id' ) );

		return $commissie
			&& $commissie->post_type === 'commissie'
			&& UserRoles::can_manage_commissie_info();
	}

	/**
	 * Update only the Rondo-local information fields of a commissie.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_commissie_info( $request ) {
		$commissie_id = (int) $request->get_param( 'commissie_id' );
		$fields       = $request->get_param( 'fields' );

		if ( ! is_array( $fields ) ) {
			return new \WP_Error(
				'rondo_invalid_commissie_info',
				__( 'The fields payload must be an object.', 'rondo' ),
				[
					'status' => 400,
					'field'  => 'fields',
				]
			);
		}

		foreach ( array_keys( $fields ) as $field_name ) {
			if ( ! in_array( $field_name, self::LOCAL_INFO_FIELDS, true ) ) {
				return new \WP_Error(
					'rondo_invalid_commissie_info',
					__( 'Only Rondo-local commissie information can be changed here.', 'rondo' ),
					[
						'status' => 400,
						'field'  => 'fields.' . $field_name,
					]
				);
			}
		}

		try {
			$normalized = Formatter::for_storage( 'commissie', $fields );
		} catch ( InvalidArgumentException $error ) {
			return new \WP_Error(
				'rondo_invalid_commissie_info',
				__( 'Invalid commissie information.', 'rondo' ),
				[
					'status' => 400,
					'detail' => $error->getMessage(),
				]
			);
		}

		$result = Fields::update_many_for_post( $commissie_id, $normalized );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			[
				'id'     => $commissie_id,
				'fields' => RestFields::for_post( 'commissie', $commissie_id ),
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
		$access_control = new \Rondo\Core\AccessControl();
		if ( ! current_user_can( 'manage_options' ) && ! $access_control->user_can_access_post( $commissie_id, $user_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this commissie.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		// Let WordPress select only people whose numbered work-history rows point
		// at this commissie. Post meta is primed for the small result set, so the
		// field formatter below does not create an N+1 query over every person.
		$people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'meta_query'     => [
					[
						'key'         => '^work_history_[0-9]+_team$',
						'compare_key' => 'REGEXP',
						'value'       => (string) $commissie_id,
						'compare'     => '=',
					],
				],
			]
		);

		$current = [];
		$former  = [];
		$seen    = [];

		// Loop through all people and check their work history
		foreach ( $people as $person ) {
			if ( isset( $seen[ $person->ID ] ) ) {
				continue;
			}
			$seen[ $person->ID ] = true;

			$work_history = \Rondo\Fields\Fields::get_for_post( $person->ID, 'work_history' ) ?: [];

			if ( empty( $work_history ) ) {
				continue;
			}

			// Find the relevant work history entry for this commissie
			foreach ( $work_history as $job ) {
				// Check if this job references a commissie (using the 'team' field which now supports both)
				$job_commissie_id = isset( $job['team'] ) ? (int) $job['team'] : 0;

				if ( $job_commissie_id === $commissie_id ) {
					$person_data               = $this->format_person_summary( $person );
					$person_data['email']      = $this->get_primary_email( $person->ID );
					$person_data['job_title']  = $job['job_title'] ?? '';
					$person_data['start_date'] = $job['start_date'] ?? '';
					$person_data['end_date']   = $job['end_date'] ?? '';

					// Determine if person is current or former
					$is_current = false;

					if ( ! empty( $job['is_current'] ) ) {
						if ( ! empty( $job['end_date'] ) ) {
							$end_date   = strtotime( $job['end_date'] );
							$today      = strtotime( 'today' );
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
	 * Get current member counts for all published commissies.
	 *
	 * The overview requests this independently from the core post collection, so
	 * names and local fields can render without waiting for the aggregate query.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_member_counts() {
		$commissie_ids = get_posts(
			[
				'post_type'      => 'commissie',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		$all_counts    = Teams::get_all_member_counts();
		$counts        = [];

		foreach ( $commissie_ids as $commissie_id ) {
			$counts[ (string) $commissie_id ] = $all_counts[ $commissie_id ]['total'] ?? 0;
		}

		return rest_ensure_response( $counts );
	}

	/**
	 * Get the first valid email address for a commissie member.
	 *
	 * @param int $person_id Person post ID.
	 * @return string Valid email address, or an empty string.
	 */
	private function get_primary_email( int $person_id ): string {
		foreach ( [ 'email_1', 'email_2' ] as $field_name ) {
			$email = sanitize_email( (string) \Rondo\Fields\Fields::get_for_post( $person_id, $field_name ) );
			if ( is_email( $email ) ) {
				return $email;
			}
		}

		return '';
	}

	/**
	 * Add member_count field to commissie REST API responses.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response Modified response with member_count.
	 */
	public function add_member_count_to_response( $response, $post, $request ) {
		if ( ! $this->request_includes_field( $request, 'member_count' ) ) {
			return $response;
		}

		$counts = Teams::get_all_member_counts();
		$data   = $response->get_data();
		$entry  = $counts[ $post->ID ] ?? [ 'total' => 0 ];

		$data['member_count'] = $entry['total'];

		$response->set_data( $data );

		return $response;
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
		return (int) $commissie->post_author === get_current_user_id() || current_user_can( 'manage_options' );
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
			return new \WP_Error( 'commissie_not_found', __( 'Commissie not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		$media = get_post( $media_id );
		if ( ! $media || $media->post_type !== 'attachment' ) {
			return new \WP_Error( 'media_not_found', __( 'Media not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		$result = set_post_thumbnail( $commissie_id, $media_id );

		if ( ! $result ) {
			return new \WP_Error( 'set_thumbnail_failed', __( 'Failed to set commissie logo.', 'rondo' ), [ 'status' => 500 ] );
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
			return new \WP_Error( 'commissie_not_found', __( 'Commissie not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'rondo' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		$allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ];
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid file type. Please upload an image.', 'rondo' ), [ 'status' => 400 ] );
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
}
