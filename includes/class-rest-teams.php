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
	 * Get current member counts for all teams and commissies in a single query.
	 *
	 * Uses ACF repeater meta key patterns (work_history_X_team) and joins
	 * with corresponding end_date and job_title entries to determine current
	 * membership and player/staff classification.
	 * Results are cached in a static variable for the duration of the request.
	 *
	 * @return array<int, array{total: int, players: int, staff: int}> Map of entity_id => counts.
	 */
	public static function get_all_member_counts() {
		static $counts = null;

		if ( $counts !== null ) {
			return $counts;
		}

		global $wpdb;

		$today = current_time( 'Y-m-d' );
		$like  = $wpdb->esc_like( 'work_history_' ) . '%' . $wpdb->esc_like( '_team' );

		// Build IN clause for player positions.
		$position_placeholders = implode( ', ', array_fill( 0, count( self::PLAYER_POSITIONS ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m_team.meta_value AS entity_id,
					COUNT(DISTINCT p.ID) AS total_count,
					COUNT(DISTINCT CASE WHEN m_title.meta_value IN ({$position_placeholders}) THEN p.ID END) AS player_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m_team ON m_team.post_id = p.ID
				LEFT JOIN {$wpdb->postmeta} m_end ON m_end.post_id = p.ID
					AND m_end.meta_key = CONCAT(
						'work_history_',
						REPLACE(REPLACE(m_team.meta_key, 'work_history_', ''), '_team', ''),
						'_end_date'
					)
				LEFT JOIN {$wpdb->postmeta} m_title ON m_title.post_id = p.ID
					AND m_title.meta_key = CONCAT(
						'work_history_',
						REPLACE(REPLACE(m_team.meta_key, 'work_history_', ''), '_team', ''),
						'_job_title'
					)
				LEFT JOIN {$wpdb->postmeta} m_former ON m_former.post_id = p.ID
					AND m_former.meta_key = 'former_member'
				WHERE p.post_type = 'person'
					AND p.post_status = 'publish'
					AND m_team.meta_key LIKE %s
					AND (m_former.meta_value IS NULL OR m_former.meta_value = '0' OR m_former.meta_value = '')
					AND (m_end.meta_value IS NULL OR m_end.meta_value = '' OR m_end.meta_value >= %s)
				GROUP BY m_team.meta_value",
				...array_merge( self::PLAYER_POSITIONS, [ $like, $today ] )
			)
		);

		$counts = [];
		foreach ( $results as $row ) {
			$total   = (int) $row->total_count;
			$players = (int) $row->player_count;

			$counts[ (int) $row->entity_id ] = [
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
		$counts = self::get_all_member_counts();
		$data   = $response->get_data();
		$entry  = $counts[ $post->ID ] ?? [ 'total' => 0, 'players' => 0, 'staff' => 0 ];

		$data['player_count'] = $entry['players'];
		$data['staff_count']  = $entry['staff'];

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

}
