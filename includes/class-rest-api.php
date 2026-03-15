<?php
/**
 * Extended REST API Endpoints
 */

namespace Rondo\REST;

use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api extends Base {

	private const KADERLIJST_SNAPSHOT_OPTION = 'rondo_kaderlijst_snapshot';
	private const KADERLIJST_UPDATED_OPTION  = 'rondo_kaderlijst_snapshot_updated_at';

	/**
	 * Get persisted kaderlijst snapshot from WordPress options.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_kaderlijst_snapshot( $request ) {
		$snapshot   = get_option( self::KADERLIJST_SNAPSHOT_OPTION, null );
		$updated_at = get_option( self::KADERLIJST_UPDATED_OPTION, null );

		if ( ! is_array( $snapshot ) || ! isset( $snapshot['teams'], $snapshot['rows'] ) ) {
			$snapshot = null;
		}

		return rest_ensure_response(
			[
				'snapshot'   => $snapshot,
				'updated_at' => is_string( $updated_at ) ? $updated_at : null,
			]
		);
	}

	/**
	 * Persist kaderlijst snapshot in WordPress options.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_kaderlijst_snapshot( $request ) {
		$snapshot = $request->get_param( 'snapshot' );
		if ( ! is_array( $snapshot ) || ! isset( $snapshot['teams'], $snapshot['rows'] ) || ! is_array( $snapshot['teams'] ) || ! is_array( $snapshot['rows'] ) ) {
			return new \WP_Error(
				'invalid_snapshot',
				__( 'Invalid kaderlijst snapshot payload.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		update_option( self::KADERLIJST_SNAPSHOT_OPTION, $snapshot, false );
		$updated_at = gmdate( 'c' );
		update_option( self::KADERLIJST_UPDATED_OPTION, $updated_at, false );

		return rest_ensure_response(
			[
				'snapshot'   => $snapshot,
				'updated_at' => $updated_at,
			]
		);
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_acf_fields' ] );
		// NOTE: save_post_person hook for volunteer start date cache invalidation
		// moved to Rondo\REST\Reminders constructor

		// Dashboard cache invalidation hooks.
		$post_types = [ 'person', 'team', 'commissie', 'rondo_todo', 'rondo_feedback' ];
		foreach ( $post_types as $post_type ) {
			add_action( 'save_post_' . $post_type, [ $this, 'invalidate_dashboard_cache' ] );
		}
		add_action( 'wp_insert_comment', [ $this, 'maybe_invalidate_dashboard_on_comment' ], 10, 2 );
		add_action( 'edit_comment', [ $this, 'maybe_invalidate_dashboard_on_comment' ], 10, 2 );
	}

	/**
	 * Invalidate all dashboard transient caches.
	 */
	public function invalidate_dashboard_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rondo_dashboard_%' OR option_name LIKE '_transient_timeout_rondo_dashboard_%'" );
	}

	/**
	 * Conditionally invalidate dashboard cache when a rondo_activity comment is saved.
	 *
	 * @param int               $comment_id The comment ID.
	 * @param \WP_Comment|array $comment    The comment object or data array.
	 */
	public function maybe_invalidate_dashboard_on_comment( $comment_id, $comment ) {
		$comment_type = '';
		if ( $comment instanceof \WP_Comment ) {
			$comment_type = $comment->comment_type;
		} elseif ( is_array( $comment ) && isset( $comment['comment_type'] ) ) {
			$comment_type = $comment['comment_type'];
		} else {
			$comment_obj = get_comment( $comment_id );
			if ( $comment_obj ) {
				$comment_type = $comment_obj->comment_type;
			}
		}

		if ( 'rondo_activity' === $comment_type ) {
			$this->invalidate_dashboard_cache();
		}
	}

	/**
	 * Register custom REST routes
	 */
	public function register_routes() {
		// NOTE: Reminders, anniversaries, user settings, users, VOG, and fees routes
		// have been extracted to dedicated controllers. See:
		// - class-rest-reminders.php (Rondo\REST\Reminders)
		// - class-rest-user-settings.php (Rondo\REST\UserSettings)
		// - class-rest-users.php (Rondo\REST\Users)
		// - class-rest-vog.php (Rondo\REST\Vog)
		// - class-rest-fees.php (Rondo\REST\Fees)
		// Search across all content
		register_rest_route(
			'rondo/v1',
			'/search',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'global_search' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'q' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( $param ) >= 2;
						},
					],
				],
			]
		);

		// Find person by email (for sync deduplication)
		// Uses check_authenticated instead of check_user_approved for sync scripts
		register_rest_route(
			'rondo/v1',
			'/people/find-by-email',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'find_person_by_email' ],
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => [
					'email' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					],
				],
			]
		);

		// Dashboard summary
		register_rest_route(
			'rondo/v1',
			'/dashboard',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dashboard_summary' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// Kaderlijst snapshot (database-backed)
		register_rest_route(
			'rondo/v1',
			'/kaderlijst/snapshot',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_kaderlijst_snapshot' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_kaderlijst_snapshot' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
					'args'                => [
						'snapshot' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param ) && isset( $param['teams'], $param['rows'] ) && is_array( $param['teams'] ) && is_array( $param['rows'] );
							},
						],
					],
				],
			]
		);

		// Version check (public endpoint for cache invalidation)
		register_rest_route(
			'rondo/v1',
			'/version',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_version' ],
				'permission_callback' => '__return_true',
			]
		);

		// Get teams where a person or company is an investor
		register_rest_route(
			'rondo/v1',
			'/investments/(?P<investor_id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_investments' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'investor_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Restore default relationship type configurations
		register_rest_route(
			'rondo/v1',
			'/relationship-types/restore-defaults',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'restore_relationship_type_defaults' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// Get entity (team or commissie) by ID - unified lookup
		register_rest_route(
			'rondo/v1',
			'/entity/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_entity_by_id' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					],
				],
			]
		);

		// Club configuration (admin write, all-users read)
		register_rest_route(
			'rondo/v1',
			'/config',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_club_config' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_club_config' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'club_name'                 => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'freescout_url'             => [
							'required'          => false,
							'sanitize_callback' => 'esc_url_raw',
						],
						'freescout_api_key'         => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_api_token'      => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_team_api_token' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_project_id'     => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_route_id'       => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_from_email'     => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => function ( $param ) {
								return $param === null || $param === '' || is_email( $param );
							},
						],
						'lettermint_from_name'      => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_webhook_secret' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_verification_email_subject' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_verification_email_body' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'lettermint_verification_from_email' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => function ( $param ) {
								return $param === null || $param === '' || is_email( $param );
							},
						],
						'lettermint_verification_from_name' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Capability sync (admin only — called by rondo-sync per member)
		register_rest_route(
			'rondo/v1',
			'/capability-sync',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_user_capabilities' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'knvb_id'  => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					],
					'functies' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					],
				],
			]
		);

		// Capability sync all (admin only — on-demand from Settings)
		register_rest_route(
			'rondo/v1',
			'/capability-sync/all',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_all_capabilities' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Capability sync for a single person (admin only — on-demand from AccountCard)
		register_rest_route(
			'rondo/v1',
			'/people/(?P<id>[\d]+)/capability-sync',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_person_capabilities' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Register ACF fields to REST API
	 */
	public function register_acf_fields() {
		// Expose ACF fields in REST API for taxonomy terms
		add_filter( 'rest_prepare_relationship_type', [ $this, 'add_acf_to_relationship_type' ], 10, 3 );

		// Allow updating ACF fields via REST API
		add_action( 'rest_insert_relationship_type', [ $this, 'update_relationship_type_acf' ], 10, 3 );

		// NOTE: VOG response filters (rest_prepare_person, rest_prepare_discipline_case)
		// moved to Rondo\REST\Vog::register_response_filters()
	}

	/**
	 * Add ACF fields to relationship_type REST response
	 */
	public function add_acf_to_relationship_type( $response, $term, $request ) {
		$acf_data = get_fields( 'relationship_type_' . $term->term_id );
		if ( $acf_data ) {
			$response->data['acf'] = $acf_data;
		}
		return $response;
	}

	/**
	 * Update ACF fields when relationship_type is updated via REST API
	 */
	public function update_relationship_type_acf( $term, $request, $creating ) {
		$acf_data = $request->get_param( 'acf' );
		if ( is_array( $acf_data ) ) {
			foreach ( $acf_data as $field_name => $value ) {
				update_field( $field_name, $value, 'relationship_type_' . $term->term_id );
			}
		}
	}

	/**
	 * Restore default relationship type configurations
	 */
	public function restore_relationship_type_defaults( $request ) {
		// Get the taxonomies class instance
		$taxonomies = new \Rondo\Core\Taxonomies();

		// Call the setup method
		if ( method_exists( $taxonomies, 'setup_default_relationship_configurations' ) ) {
			$taxonomies->setup_default_relationship_configurations();

			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Default relationship type configurations have been restored.', 'rondo' ),
				],
				200
			);
		}

		return new \WP_Error(
			'restore_failed',
			__( 'Failed to restore defaults.', 'rondo' ),
			[ 'status' => 500 ]
		);
	}

	/**
	 * Find a person by email address (for sync deduplication)
	 *
	 * Searches all people for a matching email in fixed fields.
	 * Returns the person ID if found, null otherwise.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with person ID or null.
	 */
	public function find_person_by_email( $request ) {
		$email = strtolower( trim( sanitize_email( $request->get_param( 'email' ) ) ) );

		if ( empty( $email ) ) {
			return new \WP_REST_Response( [ 'id' => null ], 200 );
		}

		// Search by email_1 first, then email_2.
		foreach ( [ 'email_1', 'email_2' ] as $field ) {
			$matches = get_posts(
				[
					'post_type'        => 'person',
					'posts_per_page'   => 1,
					'post_status'      => 'publish',
					'suppress_filters' => true,
					'meta_query'       => [
						[
							'key'     => $field,
							'value'   => $email,
							'compare' => '=',
						],
					],
				]
			);

			if ( ! empty( $matches ) ) {
				return new \WP_REST_Response( [ 'id' => $matches[0]->ID ], 200 );
			}
		}

		return new \WP_REST_Response( [ 'id' => null ], 200 );
	}

	/**
	 * Global search across people, teams, and dates
	 */
	public function global_search( $request ) {
		$query = sanitize_text_field( $request->get_param( 'q' ) );

		$results = [
			'people'   => [],
			'teams'    => [],
			'invoices' => [],
		];

		// Search people with scoring to prioritize first name matches
		$people_results = [];

		// Query 1: First name matches (highest priority)
		$first_name_matches = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'first_name',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $first_name_matches as $person ) {
			$first_name  = strtolower( get_field( 'first_name', $person->ID ) ?: '' );
			$query_lower = strtolower( $query );

			// Score: exact = 100, starts with = 80, contains = 60
			if ( $first_name === $query_lower ) {
				$score = 100;
			} elseif ( strpos( $first_name, $query_lower ) === 0 ) {
				$score = 80;
			} else {
				$score = 60;
			}

			$people_results[ $person->ID ] = [
				'person' => $person,
				'score'  => $score,
			];
		}

		// Query 2: Infix matches (score: 50)
		$infix_matches = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'infix',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $infix_matches as $person ) {
			if ( ! isset( $people_results[ $person->ID ] ) ) {
				$people_results[ $person->ID ] = [
					'person' => $person,
					'score'  => 50,
				];
			}
		}

		// Query 3: Last name matches (lower priority)
		$last_name_matches = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'last_name',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $last_name_matches as $person ) {
			if ( ! isset( $people_results[ $person->ID ] ) ) {
				$people_results[ $person->ID ] = [
					'person' => $person,
					'score'  => 40,
				];
			}
		}

		// Query 4: General WordPress search (catches title, content)
		$general_matches = get_posts(
			[
				'post_type'      => 'person',
				's'              => $query,
				'posts_per_page' => 20,
				'post_status'    => 'publish',
			]
		);

		foreach ( $general_matches as $person ) {
			if ( ! isset( $people_results[ $person->ID ] ) ) {
				$people_results[ $person->ID ] = [
					'person' => $person,
					'score'  => 20,
				];
			}
		}

			// Query 5: Custom field matches (score: 30)
			$custom_field_names = $this->get_searchable_custom_fields( 'person' );
		if ( ! empty( $custom_field_names ) ) {
			$custom_meta_query = $this->build_custom_field_meta_query( $custom_field_names, $query );

			$custom_field_matches = get_posts(
				[
					'post_type'      => 'person',
					'posts_per_page' => 20,
					'post_status'    => 'publish',
					'meta_query'     => $custom_meta_query,
				]
			);

			foreach ( $custom_field_matches as $person ) {
				if ( ! isset( $people_results[ $person->ID ] ) ) {
					$people_results[ $person->ID ] = [
						'person' => $person,
						'score'  => 30,
					];
				}
			}
		}

			// Query 6: KNVB ID matches (score: 70)
			$knvb_matches = get_posts(
				[
					'post_type'      => 'person',
					'posts_per_page' => 20,
					'post_status'    => 'publish',
					'meta_query'     => [
						'relation' => 'OR',
						[
							'key'     => 'knvb-id',
							'value'   => $query,
							'compare' => 'LIKE',
						],
						[
							'key'     => 'custom_knvb-id',
							'value'   => $query,
							'compare' => 'LIKE',
						],
					],
				]
			);

		foreach ( $knvb_matches as $person ) {
			if ( ! isset( $people_results[ $person->ID ] ) ) {
				$people_results[ $person->ID ] = [
					'person' => $person,
					'score'  => 70,
				];
			}
		}

			// Query 7: Contact email matches in email_1/email_2 fields (score: 75)
		if ( strpos( $query, '@' ) !== false || is_email( $query ) ) {
			$email_matches = $this->find_people_by_contact_email_fragment( $query, 20 );

			foreach ( $email_matches as $person ) {
				if ( ! isset( $people_results[ $person->ID ] ) ) {
					$people_results[ $person->ID ] = [
						'person' => $person,
						'score'  => 75,
					];
				}
			}
		}

		// Apply former member penalty to prioritize current members
		foreach ( $people_results as $person_id => &$item ) {
			if ( get_field( 'former_member', $person_id ) ) {
				$item['score'] -= 50;
			}
		}
		unset( $item ); // Break reference

		// Sort by score descending, take top 10
		uasort(
			$people_results,
			function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);

		$people_results = array_slice( $people_results, 0, 10, true );

		foreach ( $people_results as $item ) {
			$results['people'][] = $this->format_person_summary( $item['person'] );
		}

		// Search teams with scoring (similar to people)
		$team_results = [];

		// Query 1: Name field matches (highest priority, score: 60)
		$name_matches = get_posts(
			[
				'post_type'      => 'team',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'name',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $name_matches as $team ) {
			$team_results[ $team->ID ] = [
				'team'  => $team,
				'score' => 60,
			];
		}

		// Query 2: General WordPress search (score: 20)
		$general_company_matches = get_posts(
			[
				'post_type'      => 'team',
				's'              => $query,
				'posts_per_page' => 20,
				'post_status'    => 'publish',
			]
		);

		foreach ( $general_company_matches as $team ) {
			if ( ! isset( $team_results[ $team->ID ] ) ) {
				$team_results[ $team->ID ] = [
					'team'  => $team,
					'score' => 20,
				];
			}
		}

		// Query 3: Custom field matches (score: 30)
		$team_custom_fields = $this->get_searchable_custom_fields( 'team' );
		if ( ! empty( $team_custom_fields ) ) {
			$team_meta_query = $this->build_custom_field_meta_query( $team_custom_fields, $query );

			$team_custom_matches = get_posts(
				[
					'post_type'      => 'team',
					'posts_per_page' => 20,
					'post_status'    => 'publish',
					'meta_query'     => $team_meta_query,
				]
			);

			foreach ( $team_custom_matches as $team ) {
				if ( ! isset( $team_results[ $team->ID ] ) ) {
					$team_results[ $team->ID ] = [
						'team'  => $team,
						'score' => 30,
					];
				}
			}
		}

		// Sort by score descending, take top 10
		uasort(
			$team_results,
			function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);

		$team_results = array_slice( $team_results, 0, 10, true );

		foreach ( $team_results as $item ) {
			$results['teams'][] = $this->format_company_summary( $item['team'] );
		}

		// Search invoices (only for users with financieel capability)
		if ( current_user_can( 'financieel' ) ) {
			$invoice_posts = get_posts(
				[
					'post_type'      => 'rondo_invoice',
					'posts_per_page' => 10,
					'post_status'    => [ 'publish', 'rondo_sent', 'rondo_paid', 'rondo_overdue', 'draft' ],
					'meta_query'     => [
						[
							'key'     => 'invoice_number',
							'value'   => $query,
							'compare' => 'LIKE',
						],
					],
				]
			);

			$invoice_results = [];
			foreach ( $invoice_posts as $invoice ) {
				$invoice_number = get_field( 'invoice_number', $invoice->ID );
				$person_id      = get_field( 'person', $invoice->ID );
				$person_name    = null;

				if ( ! empty( $person_id ) ) {
					$first_name  = get_field( 'first_name', $person_id ) ?: '';
					$infix       = get_field( 'infix', $person_id ) ?: '';
					$last_name   = get_field( 'last_name', $person_id ) ?: '';
					$name_parts  = array_filter( [ $first_name, $infix, $last_name ] );
					$person_name = implode( ' ', $name_parts ) ?: null;
				}

				$invoice_results[] = [
					'id'             => $invoice->ID,
					'invoice_number' => $invoice_number,
					'person_name'    => $person_name,
					'total_amount'   => (float) get_field( 'total_amount', $invoice->ID ),
					'status'         => get_field( 'status', $invoice->ID ),
				];
			}

			// Sort by invoice_number descending (most recent first)
			usort(
				$invoice_results,
				function ( $a, $b ) {
					return strcmp( $b['invoice_number'] ?? '', $a['invoice_number'] ?? '' );
				}
			);

			$results['invoices'] = $invoice_results;
		}

		return rest_ensure_response( $results );
	}

	/**
	 * Get current theme version and build time
	 * Used for cache invalidation on PWA/mobile apps
	 */
	public function get_version( $request ) {
		// Get build time from manifest file modification time
		$build_time    = null;
		$manifest_path = \RONDO_THEME_DIR . '/dist/.vite/manifest.json';
		if ( file_exists( $manifest_path ) ) {
			$build_time = gmdate( 'c', filemtime( $manifest_path ) );
		} else {
			// Fallback to current time for dev mode.
			$build_time = gmdate( 'c' );
		}

		return rest_ensure_response(
			[
				'version'   => \RONDO_THEME_VERSION,
				'buildTime' => $build_time,
			]
		);
	}

	/**
	 * Get dashboard summary
	 */
	public function get_dashboard_summary( $request ) {
		$user_id = get_current_user_id();

		// Check transient cache.
		$cache_key = 'rondo_dashboard_' . $user_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		// Get post counts (all approved users see all data)
		// Access control is already applied via WP_Query filters
		// Exclude former members from people count
		$people_query     = new \WP_Query(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
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
			]
		);
		$total_people     = $people_query->found_posts;
		$total_teams      = wp_count_posts( 'team' )->publish;
		$total_commissies = wp_count_posts( 'commissie' )->publish;

		// Recent people (exclude former members)
		$recent_people = get_posts(
			[
				'post_type'              => 'person',
				'posts_per_page'         => 5,
				'post_status'            => 'publish',
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'update_post_meta_cache' => true,
				'no_found_rows'          => true,
				'meta_query'             => [
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
			]
		);

		// Upcoming reminders
		$reminders_handler      = new \RONDO_Reminders();
		$upcoming_reminders     = $reminders_handler->get_upcoming_reminders( 14 );
		$reminders_rest         = new Reminders();
		$upcoming_anniversaries = $reminders_rest->get_upcoming_anniversaries_data( 365, 20 );

		// Get open todos count
		$open_todos_count = $this->count_open_todos();

		// Get awaiting todos count
		$awaiting_todos_count = $this->count_awaiting_todos();

		// Get total volunteers count
		$total_volunteers = $this->count_volunteers();

		// Get open feedback count
		$open_feedback_count = $this->count_open_feedback();

		// Recently contacted (people with most recent activities)
		$recently_contacted = $this->get_recently_contacted_people( 5 );

		$response_data = [
			'stats'                  => [
				'total_people'         => $total_people,
				'total_teams'          => $total_teams,
				'total_commissies'     => $total_commissies,
				'open_todos_count'     => $open_todos_count,
				'awaiting_todos_count' => $awaiting_todos_count,
				'total_volunteers'     => $total_volunteers,
				'open_feedback_count'  => $open_feedback_count,
			],
			'recent_people'          => array_map( [ $this, 'format_person_summary' ], $recent_people ),
			'upcoming_reminders'     => $this->limit_items_with_all_today( $upcoming_reminders, 5 ),
			'upcoming_anniversaries' => $this->limit_items_with_all_today( $upcoming_anniversaries, 5 ),
			'recently_contacted'     => $recently_contacted,
		];

		set_transient( $cache_key, $response_data, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $response_data );
	}

	/**
	 * Limit upcoming items to $limit while always including all items for today.
	 *
	 * Input arrays are expected to be sorted by date ascending and include
	 * a `days_until` field.
	 *
	 * @param array $items Sorted items with `days_until`.
	 * @param int   $limit Default maximum items to return.
	 * @return array
	 */
	private function limit_items_with_all_today( array $items, int $limit ): array {
		$today_count = 0;
		foreach ( $items as $item ) {
			if ( (int) ( $item['days_until'] ?? -1 ) === 0 ) {
				++$today_count;
			} else {
				break; // Reminders are sorted by date, so no more today entries after this.
			}
		}

		return array_slice( $items, 0, max( $limit, $today_count ) );
	}

	/**
	 * Count open todos visible to the current user.
	 *
	 * Visibility rule:
	 * - user is post author (creator), OR
	 * - user is the assigned user in `assigned_user_id` post meta.
	 */
	private function count_open_todos() {
		global $wpdb;
		$current_user_id = get_current_user_id();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm
				   ON pm.post_id = p.ID
				  AND pm.meta_key = %s
				 WHERE p.post_type = %s
				   AND p.post_status = %s
				   AND (p.post_author = %d OR CAST(pm.meta_value AS UNSIGNED) = %d)",
				'assigned_user_id',
				'rondo_todo',
				'rondo_open',
				$current_user_id,
				$current_user_id
			)
		);
	}

	/**
	 * Get entity (team or commissie) by ID
	 *
	 * Unified lookup that determines the post type and returns the appropriate data.
	 * Used by frontend to avoid 404 errors when entity type is unknown.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_entity_by_id( $request ) {
		$id = (int) $request->get_param( 'id' );

		$post = get_post( $id );

		if ( ! $post ) {
			return new \WP_Error(
				'not_found',
				'Entity not found',
				[ 'status' => 404 ]
			);
		}

		// Check if it's a team or commissie
		if ( ! in_array( $post->post_type, [ 'team', 'commissie' ], true ) ) {
			return new \WP_Error(
				'invalid_type',
				'Entity is not a team or commissie',
				[ 'status' => 400 ]
			);
		}

		// Build response similar to WP REST API
		$response = [
			'id'           => $post->ID,
			'title'        => [ 'rendered' => get_the_title( $post ) ],
			'slug'         => $post->post_name,
			'status'       => $post->post_status,
			'type'         => $post->post_type,
			'_entity_type' => $post->post_type,
		];

		// Add featured image if available
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$thumbnail_url         = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
			$full_url              = wp_get_attachment_image_url( $thumbnail_id, 'full' );
			$response['_embedded'] = [
				'wp:featuredmedia' => [
					[
						'source_url'    => $full_url,
						'media_details' => [
							'sizes' => [
								'thumbnail' => [
									'source_url' => $thumbnail_url,
								],
							],
						],
					],
				],
			];
		}

		// Add ACF fields if available
		if ( function_exists( 'get_fields' ) ) {
			$acf_fields = get_fields( $post->ID );
			if ( $acf_fields ) {
				$response['acf'] = $acf_fields;
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Count awaiting todos visible to the current user.
	 *
	 * Visibility rule:
	 * - user is post author (creator), OR
	 * - user is the assigned user in `assigned_user_id` post meta.
	 */
	private function count_awaiting_todos() {
		global $wpdb;
		$current_user_id = get_current_user_id();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm
				   ON pm.post_id = p.ID
				  AND pm.meta_key = %s
				 WHERE p.post_type = %s
				   AND p.post_status = %s
				   AND (p.post_author = %d OR CAST(pm.meta_value AS UNSIGNED) = %d)",
				'assigned_user_id',
				'rondo_todo',
				'rondo_awaiting',
				$current_user_id,
				$current_user_id
			)
		);
	}

	/**
	 * Count current volunteers.
	 *
	 * Counts published person posts with huidig-vrijwilliger meta set to true,
	 * excluding former members.
	 */
	private function count_volunteers() {
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => 'huidig-vrijwilliger',
						'value' => '1',
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
		return $query->found_posts;
	}

	/**
	 * Count open feedback items.
	 *
	 * Counts published feedback posts with status NOT IN ('resolved', 'declined').
	 * Also includes posts without a status field (which default to 'new').
	 */
	private function count_open_feedback() {
		$query = new \WP_Query(
			[
				'post_type'      => 'rondo_feedback',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => 'status',
						'value'   => [ 'resolved', 'declined' ],
						'compare' => 'NOT IN',
					],
					[
						'key'     => 'status',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);
		return $query->found_posts;
	}

	/**
	 * Get people with most recent activities
	 *
	 * @param int $limit Number of people to return
	 * @return array Array of person summaries with last activity info
	 */
	private function get_recently_contacted_people( $limit = 5 ) {
		global $wpdb;

		// Check if user has access (all approved users see all data)
		if ( ! is_user_logged_in() ) {
			return [];
		}

		// Query to get people with their most recent activity date
		// No post__in filter needed - approved users see all people
		// Exclude former members from recently contacted
		$query = $wpdb->prepare(
			"SELECT c.comment_post_ID as person_id, MAX(cm.meta_value) as last_activity_date
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'activity_date'
             INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
             LEFT JOIN {$wpdb->postmeta} fm ON p.ID = fm.post_id AND fm.meta_key = 'former_member'
             WHERE c.comment_type = 'rondo_activity'
             AND c.comment_approved = '1'
             AND p.post_type = 'person'
             AND p.post_status = 'publish'
             AND (fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0')
             GROUP BY c.comment_post_ID
             ORDER BY last_activity_date DESC
             LIMIT %d",
			$limit
		);

		$results = $wpdb->get_results( $query );

		if ( empty( $results ) ) {
			return [];
		}

		$person_ids = array_map(
			function ( $row ) {
				return (int) $row->person_id;
			},
			$results
		);

		// Single query with meta cache warmup.
		$people = get_posts(
			[
				'post__in'               => $person_ids,
				'post_type'              => 'person',
				'post_status'            => 'publish',
				'posts_per_page'         => count( $person_ids ),
				'orderby'                => 'post__in',
				'update_post_meta_cache' => true,
				'no_found_rows'          => true,
			]
		);

		$people_by_id = [];
		foreach ( $people as $person ) {
			$people_by_id[ $person->ID ] = $person;
		}

		$recently_contacted = [];
		foreach ( $results as $row ) {
			if ( isset( $people_by_id[ (int) $row->person_id ] ) ) {
				$summary                       = $this->format_person_summary( $people_by_id[ (int) $row->person_id ] );
				$summary['last_activity_date'] = $row->last_activity_date;
				$recently_contacted[]          = $summary;
			}
		}

		return $recently_contacted;
	}
	/**
	 * Get teams and commissies where a person or company is listed as an investor
	 */
	public function get_investments( $request ) {
		$investor_id = (int) $request->get_param( 'investor_id' );

		// Query both teams and commissies where this ID appears in the investors field
		// Access control applied automatically via WP_Query filters (all approved users see all data)
		$entities = get_posts(
			[
				'post_type'      => [ 'team', 'commissie' ],
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'investors',
						'value'   => sprintf( '"%d"', $investor_id ),
						'compare' => 'LIKE',
					],
				],
			]
		);

		// Also check with serialized format (ACF stores as serialized array)
		$entities_serialized = get_posts(
			[
				'post_type'      => [ 'team', 'commissie' ],
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'investors',
						'value'   => serialize( strval( $investor_id ) ),
						'compare' => 'LIKE',
					],
				],
			]
		);

		// Merge and dedupe
		$all_entities    = array_merge( $entities, $entities_serialized );
		$seen_ids        = [];
		$unique_entities = [];
		foreach ( $all_entities as $entity ) {
			if ( ! in_array( $entity->ID, $seen_ids, true ) ) {
				$seen_ids[]        = $entity->ID;
				$unique_entities[] = $entity;
			}
		}

		// Format response
		$investments = [];
		foreach ( $unique_entities as $entity ) {
			$thumbnail_id  = get_post_thumbnail_id( $entity->ID );
			$thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

			$investments[] = [
				'id'        => $entity->ID,
				'type'      => $entity->post_type,
				'name'      => $this->sanitize_text( $entity->post_title ),
				'website'   => $this->sanitize_url( get_field( 'website', $entity->ID ) ),
				'thumbnail' => $this->sanitize_url( $thumbnail_url ),
			];
		}

		// Sort alphabetically by name
		usort(
			$investments,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return rest_ensure_response( $investments );
	}


	/**
	 * Get club configuration settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with club configuration settings.
	 */
	public function get_club_config( $request ) {
		return rest_ensure_response( \Rondo\Config\ClubConfig::get_all_settings() );
	}

	/**
	 * Update club configuration settings
	 *
	 * Supports partial updates - only provided fields will be updated.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with updated club configuration settings.
	 */
	public function update_club_config( $request ) {
		// Update club_name if provided
		$club_name = $request->get_param( 'club_name' );
		if ( $club_name !== null ) {
			\Rondo\Config\ClubConfig::update_club_name( $club_name );
		}

		// Update freescout_url if provided
		$freescout_url = $request->get_param( 'freescout_url' );
		if ( $freescout_url !== null ) {
			\Rondo\Config\ClubConfig::update_freescout_url( $freescout_url );
		}

		$freescout_api_key = $request->get_param( 'freescout_api_key' );
		if ( $freescout_api_key !== null ) {
			\Rondo\Config\ClubConfig::update_freescout_api_key( $freescout_api_key );
		}

		$lettermint_api_token = $request->get_param( 'lettermint_api_token' );
		if ( $lettermint_api_token !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_api_token( $lettermint_api_token );
		}

		$lettermint_team_api_token = $request->get_param( 'lettermint_team_api_token' );
		if ( $lettermint_team_api_token !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_team_api_token( $lettermint_team_api_token );
		}

		$lettermint_project_id = $request->get_param( 'lettermint_project_id' );
		if ( $lettermint_project_id !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_project_id( $lettermint_project_id );
		}

		$lettermint_route_id = $request->get_param( 'lettermint_route_id' );
		if ( $lettermint_route_id !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_route_id( $lettermint_route_id );
		}

		$lettermint_from_email = $request->get_param( 'lettermint_from_email' );
		if ( $lettermint_from_email !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_from_email( $lettermint_from_email );
		}

		$lettermint_from_name = $request->get_param( 'lettermint_from_name' );
		if ( $lettermint_from_name !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_from_name( $lettermint_from_name );
		}

		$lettermint_webhook_secret = $request->get_param( 'lettermint_webhook_secret' );
		if ( $lettermint_webhook_secret !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_webhook_secret( $lettermint_webhook_secret );
		}

		$lettermint_verification_email_subject = $request->get_param( 'lettermint_verification_email_subject' );
		if ( $lettermint_verification_email_subject !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_verification_email_subject( $lettermint_verification_email_subject );
		}

		$lettermint_verification_email_body = $request->get_param( 'lettermint_verification_email_body' );
		if ( $lettermint_verification_email_body !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_verification_email_body( $lettermint_verification_email_body );
		}

		$lettermint_verification_from_email = $request->get_param( 'lettermint_verification_from_email' );
		if ( $lettermint_verification_from_email !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_verification_from_email( $lettermint_verification_from_email );
		}

		$lettermint_verification_from_name = $request->get_param( 'lettermint_verification_from_name' );
		if ( $lettermint_verification_from_name !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_verification_from_name( $lettermint_verification_from_name );
		}

		return rest_ensure_response( \Rondo\Config\ClubConfig::get_all_settings() );
	}

	/**
	 * Proxy a single-member sync request to the Rondo Sync server.
	 *
	 * After a successful Sportlink sync, also triggers a capability sync for
	 * the linked WordPress user so role mappings are applied immediately.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response from sync server or error.
	 */
	public function sync_individual_from_sportlink( $request ) {
		if ( ! defined( 'RONDO_SYNC_URL' ) || ! defined( 'RONDO_SYNC_API_KEY' ) ) {
			return new \WP_Error(
				'sync_not_configured',
				'Sportlink sync is not configured. Add RONDO_SYNC_URL and RONDO_SYNC_API_KEY to wp-config.php.',
				[ 'status' => 500 ]
			);
		}

		$knvb_id  = $request->get_param( 'knvb_id' );
		$response = wp_remote_post(
			rtrim( RONDO_SYNC_URL, '/' ) . '/api/sync/individual',
			[
				'headers' => [
					'Content-Type'   => 'application/json',
					'X-Sync-API-Key' => RONDO_SYNC_API_KEY,
				],
				'body'    => wp_json_encode( [ 'knvb_id' => $knvb_id ] ),
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$is_timeout    = stripos( $error_message, 'timed out' ) !== false
				|| stripos( $error_message, 'cURL error 28' ) !== false
				|| stripos( $error_message, 'operation timeout' ) !== false;

			return new \WP_Error(
				'sync_request_failed',
				$is_timeout
					? 'Sportlink sync duurde te lang en is afgebroken. Probeer het opnieuw.'
					: $error_message,
				[ 'status' => $is_timeout ? 504 : 502 ]
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			$default_message = wp_remote_retrieve_response_message( $response );
			if ( empty( $default_message ) ) {
				$default_message = 'Sync server returned an error.';
			}

			return new \WP_Error(
				'sync_error',
				$body['error'] ?? $default_message,
				[ 'status' => $status_code ]
			);
		}

		// After a successful Sportlink sync, trigger capability sync for the linked WP user.
		// The Sportlink sync may have updated work_history; we re-apply role mappings now.
		// Look up the person by KNVB ID via ACF field meta, then sync via person ID.
		$person_id = $this->find_person_id_by_knvb_id( $knvb_id );
		if ( $person_id ) {
			$deduped_count = $this->dedupe_person_work_history_entries( $person_id );
			if ( $deduped_count > 0 ) {
				$body['work_history_deduped'] = $deduped_count;
			}

			$cap_sync        = new \Rondo\Users\CapabilitySync();
			$cap_sync_result = $cap_sync->sync_user_by_person_id( $person_id );
			if ( is_array( $cap_sync_result ) ) {
				$body['capability_sync'] = $cap_sync_result;
			}
		}

		return rest_ensure_response( $body );
	}

	/**
	 * Find a person post ID by their KNVB ID stored in ACF meta.
	 *
	 * @param string $knvb_id The KNVB member ID.
	 * @return int|null Person post ID, or null if not found.
	 */
	private function find_person_id_by_knvb_id( string $knvb_id ): ?int {
		// ACF stores the field key as meta for the 'knvb-id' field.
		// The raw post meta key is 'knvb-id'.
		$posts = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => 'knvb-id',
						'value' => $knvb_id,
					],
				],
			]
		);

		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	/**
	 * Normalize one work history row into a stable dedupe key.
	 *
	 * @param array $row Raw ACF work_history row.
	 * @return string Stable serialized key.
	 */
	private function get_work_history_dedupe_key( array $row ): string {
		$team = $row['team'] ?? '';
		if ( is_array( $team ) ) {
			$team = $team['ID'] ?? $team['id'] ?? $team['post_id'] ?? '';
		}

		$team = is_numeric( $team ) ? (string) (int) $team : trim( (string) $team );

		$normalized = [
			'team'        => $team,
			'entity_type' => strtolower( trim( (string) ( $row['entity_type'] ?? '' ) ) ),
			'job_title'   => trim( (string) ( $row['job_title'] ?? '' ) ),
			'description' => trim( (string) ( $row['description'] ?? '' ) ),
			'start_date'  => trim( (string) ( $row['start_date'] ?? '' ) ),
			'end_date'    => trim( (string) ( $row['end_date'] ?? '' ) ),
			'is_current'  => ! empty( $row['is_current'] ) ? '1' : '0',
		];

		return wp_json_encode( $normalized );
	}

	/**
	 * Remove exact duplicate work_history rows for a person.
	 *
	 * Duplicates are determined by normalized team/context + role + dates + current flag.
	 *
	 * @param int $person_id Person post ID.
	 * @return int Number of removed duplicate rows.
	 */
	private function dedupe_person_work_history_entries( int $person_id ): int {
		$work_history = get_field( 'work_history', $person_id );
		if ( ! is_array( $work_history ) || empty( $work_history ) ) {
			return 0;
		}

		$seen          = [];
		$deduped       = [];
		$removed_count = 0;

		foreach ( $work_history as $row ) {
			if ( ! is_array( $row ) ) {
				$deduped[] = $row;
				continue;
			}

			$key = $this->get_work_history_dedupe_key( $row );
			if ( isset( $seen[ $key ] ) ) {
				++$removed_count;
				continue;
			}

			$seen[ $key ] = true;
			$deduped[]    = $row;
		}

		if ( $removed_count > 0 ) {
			update_field( 'work_history', $deduped, $person_id );
		}

		return $removed_count;
	}

	/**
	 * Sync capabilities for a single user by KNVB ID (admin only).
	 *
	 * Called by rondo-sync per member during capability sync pipeline step.
	 * Returns { status: 'no_user' } with HTTP 200 if no WP user has the given KNVB ID.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Sync result or WP_Error.
	 */
	public function sync_user_capabilities( $request ) {
		$knvb_id  = sanitize_text_field( $request->get_param( 'knvb_id' ) );
		$functies = array_map( 'sanitize_text_field', (array) $request->get_param( 'functies' ) );

		$sync   = new \Rondo\Users\CapabilitySync();
		$result = $sync->sync_user_by_knvb_id( $knvb_id, $functies );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Sync capabilities for all provisioned users (admin only).
	 *
	 * Body-less endpoint: the server derives functies from each user's linked
	 * person's work_history ACF field (is_current entries). Used by the
	 * on-demand "Sync now" button in the Settings Functies tab.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Aggregated sync result.
	 */
	public function sync_all_capabilities( $request ) {
		$sync   = new \Rondo\Users\CapabilitySync();
		$result = $sync->sync_all();

		return rest_ensure_response( $result );
	}

	/**
	 * Sync capabilities for the WordPress user linked to a single person (admin only).
	 *
	 * Derives functies from the person's current work_history ACF field and
	 * applies the FunctieCapabilityMap. Used by the per-person "Sync rollen"
	 * button in AccountCard. Returns { status: 'no_user' } if the person has
	 * no linked WordPress account.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Sync result or WP_Error.
	 */
	public function sync_person_capabilities( $request ) {
		$person_id = (int) $request->get_param( 'id' );

		$sync   = new \Rondo\Users\CapabilitySync();
		$result = $sync->sync_user_by_person_id( $person_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get searchable custom field names for a post type.
	 *
	 * @param string $post_type Post type to get fields for.
	 * @return array Array of field names.
	 */
	private function get_searchable_custom_fields( string $post_type ): array {
		$manager = new \Rondo\CustomFields\Manager();
		$fields  = $manager->get_fields( $post_type, false ); // Active only.

		$searchable_types = [
			'text',
			'textarea',
			'email',
			'url',
			'number',
			'select',
			'checkbox',
		];

		$field_names = [];
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'], $searchable_types, true ) ) {
				$field_names[] = $field['name'];
			}
		}

		return $field_names;
	}

	/**
	 * Build meta_query array for custom field search.
	 *
	 * @param array  $field_names Array of field names to search.
	 * @param string $query       Search query string.
	 * @return array Meta query array for get_posts().
	 */
	private function build_custom_field_meta_query( array $field_names, string $query ): array {
		if ( empty( $field_names ) ) {
			return [];
		}

		$meta_query = [ 'relation' => 'OR' ];

		foreach ( $field_names as $field_name ) {
			$meta_query[] = [
				'key'     => $field_name,
				'value'   => $query,
				'compare' => 'LIKE',
			];
		}

		return $meta_query;
	}
}
