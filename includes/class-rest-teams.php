<?php
/**
 * Teams REST API Endpoints
 *
 * Handles REST API endpoints related to teams domain.
 */

namespace Rondo\REST;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Teams extends Base {

	/**
	 * Post type for sharing permission checks.
	 */
	protected $sharing_post_type = 'team';

	/**
	 * Constructor
	 *
	 * Register routes for team endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_prepare_team', [ $this, 'add_member_count_to_response' ], 10, 3 );
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
					'team_id'  => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
					'media_id' => [
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
	}

	/**
	 * Get people who work/worked at a team
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing current and former employees.
	 */
	public function get_people_by_company( $request ) {
		$team_id = (int) $request->get_param( 'team_id' );
		$user_id = get_current_user_id();

		// Check if user can access this team
		$access_control = new \Rondo\Core\AccessControl();
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
			$work_history = \Rondo\Fields\Fields::get_for_post( $person->ID, 'work_history' ) ?: [];
			$former_match = null;

			if ( empty( $work_history ) ) {
				continue;
			}

			// Prefer a current entry when a person has multiple periods at this team.
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
						$current[]    = $person_data;
						$former_match = null;
						break;
					}

					if ( $former_match === null ) {
						$former_match = $person_data;
					}
				}
			}

			if ( $former_match !== null ) {
				$former[] = $former_match;
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
		$team_id  = (int) $request->get_param( 'team_id' );
		$media_id = (int) $request->get_param( 'media_id' );

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
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid file type. Please upload an image.', 'rondo' ), [ 'status' => 400 ] );
		}

		// Get company name for filename
		$team_name = $team->post_title;
		$name_slug = sanitize_title( strtolower( trim( $team_name ) ) );

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
	 * Sportlink player position job titles.
	 * These come from the Players API endpoint (UnionTeamPlayers/ClubTeamPlayers).
	 * Everything else is considered staff (from NonPlayers endpoint).
	 */
	private const PLAYER_POSITIONS = [
		'Teamspeler',
		'Keeper',
		'Verdediger',
		'Middenvelder',
		'Aanvaller',
	];

	/**
	 * Get current member counts for all teams and commissies.
	 *
	 * WordPress selects only active people with work history and primes their post
	 * meta in one pass. Counting the native repeater rows in PHP avoids the costly
	 * self-joins that previously made every team and commissie response wait on a
	 * multi-second aggregate query.
	 * Results are cached in a static variable for the duration of the request.
	 *
	 * @return array<int, array{total: int, players: int, staff: int}> Map of entity_id => counts.
	 */
	public static function get_all_member_counts() {
		static $counts = null;

		if ( $counts !== null ) {
			return $counts;
		}

		$people = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
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
			]
		);

		$today             = current_time( 'Ymd' );
		$members_by_entity = [];

		foreach ( $people as $person ) {
			$work_history = Fields::get_for_post( $person->ID, 'work_history' ) ?: [];

			foreach ( $work_history as $job ) {
				$entity_id = isset( $job['team'] ) ? (int) $job['team'] : 0;
				$end_date  = preg_replace( '/\D/', '', (string) ( $job['end_date'] ?? '' ) );

				if ( $entity_id <= 0 || ( $end_date !== '' && $end_date < $today ) ) {
					continue;
				}

				$is_player = in_array( $job['job_title'] ?? '', self::PLAYER_POSITIONS, true );
				if ( ! isset( $members_by_entity[ $entity_id ][ $person->ID ] ) ) {
					$members_by_entity[ $entity_id ][ $person->ID ] = $is_player;
				} elseif ( $is_player ) {
					$members_by_entity[ $entity_id ][ $person->ID ] = true;
				}
			}
		}

		$counts = [];
		foreach ( $members_by_entity as $entity_id => $members ) {
			$total   = count( $members );
			$players = count( array_filter( $members ) );

			$counts[ $entity_id ] = [
				'total'   => $total,
				'players' => $players,
				'staff'   => $total - $players,
			];
		}

		return $counts;
	}

	/**
	 * Add player_count and staff_count fields to team REST API responses.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response Modified response with player/staff counts.
	 */
	public function add_member_count_to_response( $response, $post, $request ) {
		if ( ! $this->request_includes_field( $request, 'player_count' )
			&& ! $this->request_includes_field( $request, 'staff_count' ) ) {
			return $response;
		}

		$counts = self::get_all_member_counts();
		$data   = $response->get_data();
		$entry  = $counts[ $post->ID ] ?? [
			'total'   => 0,
			'players' => 0,
			'staff'   => 0,
		];

		$data['player_count'] = $entry['players'];
		$data['staff_count']  = $entry['staff'];

		$response->set_data( $data );

		return $response;
	}
}
