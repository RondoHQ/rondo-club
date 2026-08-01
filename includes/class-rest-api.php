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

	/**
	 * Option containing the current dashboard cache generation.
	 */
	private const DASHBOARD_CACHE_GENERATION_OPTION = 'rondo_dashboard_cache_generation';

	/**
	 * Canonical fields the Kaderlijst renders. The endpoint returns nothing else — a
	 * scoped viewer never sees a kaderlid's financial flags or private meta.
	 */
	private const KADERLIJST_FIELDS = [
		'first_name',
		'infix',
		'last_name',
		'work_history',
		'email_1',
		'email_2',
		'mobile_1',
		'mobile_2',
		'telephone_1',
		'telephone_2',
	];

	/**
	 * Memoised player-role lookup (role name => true) for the current request.
	 *
	 * @var array<string, true>|null
	 */
	private $player_role_lookup = null;

	/**
	 * Return the kaderleden the current user may see, with only the fields the
	 * Kaderlijst renders.
	 *
	 * "Kader" is anyone with a current `work_history` job linked to a team. The
	 * result is scoped server-side — this is the surface that replaced the old
	 * `suppress_age_group` full-club fetch:
	 *
	 *   - Management (unrestricted) sees every kaderlid.
	 *   - A coordinator sees kaderleden attached to a team whose current roster
	 *     includes one of their permitted `leeftijdsgroep` values — i.e. the
	 *     kader of the age groups they coordinate, not kader who merely share
	 *     their own (adult) age group.
	 *   - A scoped member sees only kaderleden in their own household.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_kaderlijst_people( $request ) {
		$permitted = \Rondo\Core\AccessControl::get_permitted_age_groups();

		// Scoped member: only their own household, and only if they are kader.
		if ( is_array( $permitted ) && empty( $permitted ) ) {
			$visible = \Rondo\Core\AccessControl::get_visible_person_ids();

			return rest_ensure_response(
				[ 'people' => empty( $visible ) ? [] : $this->build_kaderlijst_people( $visible ) ]
			);
		}

		$candidate_ids = $this->kaderlijst_candidate_ids();

		// Coordinator: keep only kaderleden attached to a team they coordinate.
		if ( is_array( $permitted ) && ! empty( $permitted ) ) {
			$scoped_teams  = $this->teams_for_age_groups( $permitted );
			$candidate_ids = $this->filter_candidates_by_teams( $candidate_ids, $scoped_teams );
		}

		return rest_ensure_response(
			[ 'people' => $this->build_kaderlijst_people( $candidate_ids ) ]
		);
	}

	/**
	 * Person IDs of the kader: at least one current `work_history` job whose
	 * job_title is not a player role. Excludes former members.
	 *
	 * A job is "current" when its end date is empty or today-or-later — the same
	 * rule the client applies (`isCurrentJob`), which ignores the `is_current`
	 * flag. Player rows are dropped here for the same reason the client hides them:
	 * they are not kader. A player who is also a coach still qualifies on the coach
	 * row. The team is deliberately optional — the old list showed teamless
	 * coordinator functies too, and they are re-derived from the role text client-side.
	 *
	 * native field stores repeater rows as flat meta (`work_history_{N}_job_title`,
	 * `work_history_{N}_end_date`) and date_pickers as `Ymd` (a few legacy rows are
	 * `Y-m-d`), so the end date is normalised with REPLACE before comparison.
	 *
	 * @return int[]
	 */
	private function kaderlijst_candidate_ids(): array {
		global $wpdb;

		$player_roles = \Rondo\Core\VolunteerStatus::get_player_roles();
		$today        = current_time( 'Ymd' );

		if ( empty( $player_roles ) ) {
			$player_roles = [ '' ];
		}

		$role_placeholders = implode( ', ', array_fill( 0, count( $player_roles ), '%s' ) );

		$sql = $wpdb->prepare(
			"SELECT DISTINCT p.ID
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} wh_jt
			   ON wh_jt.post_id = p.ID
			   AND wh_jt.meta_key REGEXP '^work_history_[0-9]+_job_title$'
			   AND wh_jt.meta_value <> ''
			   AND wh_jt.meta_value NOT IN ($role_placeholders)
			 LEFT JOIN {$wpdb->postmeta} wh_ed
			   ON wh_ed.post_id = p.ID
			   AND wh_ed.meta_key = REPLACE( wh_jt.meta_key, '_job_title', '_end_date' )
			 LEFT JOIN {$wpdb->postmeta} fm
			   ON fm.post_id = p.ID AND fm.meta_key = 'former_member'
			 WHERE p.post_type = 'person'
			   AND p.post_status = 'publish'
			   AND ( fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0' )
			   AND ( wh_ed.meta_value IS NULL OR wh_ed.meta_value = '' OR REPLACE( wh_ed.meta_value, '-', '' ) >= %s )",
			...array_merge( $player_roles, [ $today ] )
		);

		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
	}

	/**
	 * Team IDs whose current roster includes a player in one of the given
	 * `leeftijdsgroep` values. This is how a coordinator's age-group scope
	 * (`Onder 12`, `Onder 9 Meiden`, `Senioren Vrouwen`, …) resolves to the set
	 * of teams they coordinate, without parsing team names.
	 *
	 * @param string[] $age_groups Permitted leeftijdsgroep values.
	 * @return int[] Team post IDs.
	 */
	private function teams_for_age_groups( array $age_groups ): array {
		global $wpdb;

		$player_roles = \Rondo\Core\VolunteerStatus::get_player_roles();

		if ( empty( $age_groups ) || empty( $player_roles ) ) {
			return [];
		}

		$ag_placeholders   = implode( ', ', array_fill( 0, count( $age_groups ), '%s' ) );
		$role_placeholders = implode( ', ', array_fill( 0, count( $player_roles ), '%s' ) );

		$sql = $wpdb->prepare(
			"SELECT DISTINCT wh_tm.meta_value AS team_id
			 FROM {$wpdb->postmeta} lg
			 JOIN {$wpdb->postmeta} wh_jt
			   ON wh_jt.post_id = lg.post_id
			   AND wh_jt.meta_key REGEXP '^work_history_[0-9]+_job_title$'
			   AND wh_jt.meta_value IN ($role_placeholders)
			 JOIN {$wpdb->postmeta} wh_ic
			   ON wh_ic.post_id = lg.post_id
			   AND wh_ic.meta_key = REPLACE( wh_jt.meta_key, '_job_title', '_is_current' )
			   AND wh_ic.meta_value = '1'
			 JOIN {$wpdb->postmeta} wh_tm
			   ON wh_tm.post_id = lg.post_id
			   AND wh_tm.meta_key = REPLACE( wh_jt.meta_key, '_job_title', '_team' )
			 WHERE lg.meta_key = 'leeftijdsgroep'
			   AND lg.meta_value IN ($ag_placeholders)",
			...array_merge( $player_roles, $age_groups )
		);

		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
	}

	/**
	 * Keep only candidates who have a current, team-linked job on one of the
	 * given teams.
	 *
	 * @param int[] $candidate_ids Person IDs to filter.
	 * @param int[] $team_ids      Allowed team IDs.
	 * @return int[]
	 */
	private function filter_candidates_by_teams( array $candidate_ids, array $team_ids ): array {
		if ( empty( $candidate_ids ) || empty( $team_ids ) ) {
			return [];
		}

		$allowed = array_flip( array_map( 'intval', $team_ids ) );
		$kept    = [];

		foreach ( $candidate_ids as $person_id ) {
			foreach ( $this->current_team_ids_for_person( $person_id ) as $team_id ) {
				if ( isset( $allowed[ $team_id ] ) ) {
					$kept[] = $person_id;
					break;
				}
			}
		}

		return $kept;
	}

	/**
	 * Team IDs a person currently coaches: the teams of their current, non-player
	 * work_history jobs. This is the set a coordinator's team scope is matched
	 * against, so a player's own team never widens what a coordinator can see.
	 *
	 * @param int $person_id Person post ID.
	 * @return int[]
	 */
	private function current_team_ids_for_person( int $person_id ): array {
		$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' );

		if ( empty( $work_history ) || ! is_array( $work_history ) ) {
			return [];
		}

		$player_roles = $this->player_role_lookup();
		$team_ids     = [];

		foreach ( $work_history as $job ) {
			$team_id = (int) ( $job['team'] ?? 0 );
			$role    = trim( (string) ( $job['job_title'] ?? '' ) );

			if ( $team_id && $role !== '' && ! isset( $player_roles[ $role ] ) && $this->is_current_job( $job ) ) {
				$team_ids[] = $team_id;
			}
		}

		return $team_ids;
	}

	/**
	 * Player role names as a lookup map (role => true), memoised per request.
	 *
	 * @return array<string, true>
	 */
	private function player_role_lookup(): array {
		if ( $this->player_role_lookup === null ) {
			$this->player_role_lookup = array_fill_keys( \Rondo\Core\VolunteerStatus::get_player_roles(), true );
		}

		return $this->player_role_lookup;
	}

	/**
	 * Whether a work_history row is current. Mirrors the client's `isCurrentJob`:
	 * current when the end date is empty, or a parseable date that is today or
	 * later. The `is_current` flag is deliberately ignored — the client ignores it
	 * too (both of its branches reduce to the same end-date test).
	 *
	 * @param array $job Work history row.
	 * @return bool
	 */
	private function is_current_job( array $job ): bool {
		$end_date = trim( (string) ( $job['end_date'] ?? '' ) );

		if ( $end_date === '' ) {
			return true;
		}

		$digits = preg_replace( '/\D/', '', $end_date );

		if ( strlen( $digits ) !== 8 ) {
			return false;
		}

		$end = \DateTimeImmutable::createFromFormat( 'Ymd', $digits );

		return $end && $end >= new \DateTimeImmutable( 'today' );
	}

	/**
	 * Build the trimmed person payload for the given IDs, in the wp/v2 shape the
	 * Kaderlijst already consumes (`{ id, fields }`).
	 *
	 * @param int[] $person_ids Person post IDs.
	 * @return array<int, array{id:int, fields:array}>
	 */
	private function build_kaderlijst_people( array $person_ids ): array {
		$people = [];

		foreach ( array_unique( array_map( 'intval', $person_ids ) ) as $person_id ) {
			if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
				continue;
			}

			if ( \Rondo\Fields\Fields::get_for_post( $person_id, 'former_member' ) ) {
				continue;
			}

			$canonical_names = [];
			foreach ( self::KADERLIJST_FIELDS as $legacy_name ) {
				$canonical_names[] = \Rondo\Fields\Registry::resolve( 'person', $legacy_name )['canonical_name'];
			}

			$people[] = [
				'id'     => $person_id,
				'fields' => array_intersect_key(
					\Rondo\Fields\RestFields::for_post( 'person', $person_id ),
					array_flip( $canonical_names )
				),
			];
		}

		return $people;
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		// NOTE: save_post_person hook for volunteer start date cache invalidation
		// moved to Rondo\REST\Reminders constructor

		// Dashboard cache invalidation hooks.
		$post_types = [ 'person', 'team', 'commissie', 'rondo_todo', 'rondo_feedback', 'discipline_case' ];
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
		update_option( self::DASHBOARD_CACHE_GENERATION_OPTION, wp_generate_uuid4(), false );
		delete_transient( 'rondo_anniversaries_365' );
	}

	/**
	 * Build a persistent-object-cache-safe dashboard transient key.
	 *
	 * Advancing the generation makes every existing per-user cache unreachable.
	 * The old transients expire naturally after their normal 15-minute lifetime.
	 *
	 * @param int $user_id Current user ID.
	 * @return string
	 */
	private function get_dashboard_cache_key( int $user_id ): string {
		$generation = (string) get_option( self::DASHBOARD_CACHE_GENERATION_OPTION, '1' );

		return 'rondo_dashboard_' . $generation . '_' . $user_id;
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

		if ( $comment_type === 'rondo_activity' ) {
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
		// Restricted to membership administration and the admin-authenticated sync user.
		register_rest_route(
			'rondo/v1',
			'/people/find-by-email',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'find_person_by_email' ],
				'permission_callback' => [ $this, 'check_ledenadministratie_permission' ],
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

		// Kaderlijst — scoped kader people, visibility enforced server-side.
		register_rest_route(
			'rondo/v1',
			'/kaderlijst/people',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_kaderlijst_people' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
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
						'club_name'                   => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'volunteer_signup_info'       => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'volunteer_second_half_opens' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'iva_approval_email_subject'  => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'iva_approval_email_body'     => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'freescout_url'               => [
							'required'          => false,
							'sanitize_callback' => 'esc_url_raw',
						],
						'freescout_api_key'           => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_api_token'        => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_team_api_token'   => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_project_id'       => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_route_id'         => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_from_email'       => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => function ( $param ) {
								return $param === null || $param === '' || is_email( $param );
							},
						],
						'lettermint_from_name'        => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_webhook_secret'   => [
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

		// Sportlink individual sync (admin and toegangscontrole users)
		register_rest_route(
			'rondo/v1',
			'/sportlink/sync-individual',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_individual_from_sportlink' ],
				'permission_callback' => [ $this, 'check_admin_or_toegangscontrole_permission' ],
				'args'                => [
					'knvb_id' => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					],
				],
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
	 * Searches published and trashed people for a matching email in fixed fields.
	 * The legacy top-level ID remains the first published match (or first trashed
	 * match) while `matches` lets the sync exclude children and restore a parent.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with person ID or null.
	 */
	public function find_person_by_email( $request ) {
		$email = strtolower( trim( sanitize_email( $request->get_param( 'email' ) ) ) );

		if ( empty( $email ) ) {
			return new \WP_REST_Response( [ 'id' => null ], 200 );
		}

		$found = [];

		// Prefer published matches for backward compatibility, but expose trashed
		// matches so rondo-sync can restore an existing parent instead of duplicating it.
		foreach ( [ 'publish', 'trash' ] as $status ) {
			foreach ( [ 'email_1', 'email_2' ] as $field ) {
				$matches = get_posts(
					[
						'post_type'        => 'person',
						'posts_per_page'   => -1,
						'post_status'      => $status,
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

				foreach ( $matches as $match ) {
					$found[ $match->ID ] = [
						'id'     => (int) $match->ID,
						'status' => (string) $match->post_status,
					];
				}
			}
		}

		$matches = array_values( $found );

		return new \WP_REST_Response(
			[
				'id'      => $matches[0]['id'] ?? null,
				'status'  => $matches[0]['status'] ?? null,
				'matches' => $matches,
			],
			200
		);
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
				'post_type'        => 'person',
				'suppress_filters' => false,
				'posts_per_page'   => 20,
				'post_status'      => 'publish',
				'meta_query'       => [
					[
						'key'     => 'first_name',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $first_name_matches as $person ) {
			$first_name  = strtolower( \Rondo\Fields\Fields::get_for_post( $person->ID, 'first_name' ) ?: '' );
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
				'post_type'        => 'person',
				'suppress_filters' => false,
				'posts_per_page'   => 20,
				'post_status'      => 'publish',
				'meta_query'       => [
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
				'post_type'        => 'person',
				'suppress_filters' => false,
				'posts_per_page'   => 20,
				'post_status'      => 'publish',
				'meta_query'       => [
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
				'post_type'        => 'person',
				'suppress_filters' => false,
				's'                => $query,
				'posts_per_page'   => 20,
				'post_status'      => 'publish',
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
					'post_type'        => 'person',
					'suppress_filters' => false,
					'posts_per_page'   => 20,
					'post_status'      => 'publish',
					'meta_query'       => $custom_meta_query,
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
					'post_type'        => 'person',
					'suppress_filters' => false,
					'posts_per_page'   => 20,
					'post_status'      => 'publish',
					'meta_query'       => [
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
			if ( \Rondo\Fields\Fields::get_for_post( $person_id, 'former_member' ) ) {
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

		// Search invoices (only for users who may view finance data)
		if ( \Rondo\Core\UserRoles::can_view_finances() ) {
			$invoice_posts = get_posts(
				[
					'post_type'      => 'rondo_invoice',
					'posts_per_page' => 10,
					'post_status'    => [ 'publish', 'rondo_sent', 'rondo_paid', 'rondo_overdue', 'rondo_cancelled', 'draft' ],
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
				$invoice_number = \Rondo\Fields\Fields::get_for_post( $invoice->ID, 'invoice_number' );
				$person_id      = \Rondo\Fields\Fields::get_for_post( $invoice->ID, 'person' );
				$person_name    = null;

				if ( ! empty( $person_id ) ) {
					$first_name  = \Rondo\Fields\Fields::try_get_for_post( $person_id, 'first_name' ) ?: '';
					$infix       = \Rondo\Fields\Fields::try_get_for_post( $person_id, 'infix' ) ?: '';
					$last_name   = \Rondo\Fields\Fields::try_get_for_post( $person_id, 'last_name' ) ?: '';
					$name_parts  = array_filter( [ $first_name, $infix, $last_name ] );
					$person_name = implode( ' ', $name_parts ) ?: null;
				}

				$invoice_results[] = [
					'id'             => $invoice->ID,
					'invoice_number' => $invoice_number,
					'person_name'    => $person_name,
					'total_amount'   => (float) \Rondo\Fields\Fields::get_for_post( $invoice->ID, 'total_amount' ),
					'status'         => \Rondo\Fields\Fields::get_for_post( $invoice->ID, 'status' ),
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
		$cache_key = $this->get_dashboard_cache_key( $user_id );
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return rest_ensure_response( $cached );
		}

		// Consolidated counts: people, volunteers, and open feedback in a single query.
		$counts              = $this->get_dashboard_counts();
		$total_people        = (int) $counts->total_people;
		$total_volunteers    = (int) $counts->total_volunteers;
		$open_feedback_count = (int) $counts->open_feedback_count;
		$total_teams         = wp_count_posts( 'team' )->publish;
		$total_commissies    = wp_count_posts( 'commissie' )->publish;

		// Recent people (exclude former members)
		$recent_people = get_posts(
			[
				'post_type'              => 'person',
				'suppress_filters'       => false,
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

		// Upcoming reminders (birthday query now filtered in SQL)
		$reminders_handler  = new \RONDO_Reminders();
		$upcoming_reminders = $reminders_handler->get_upcoming_reminders( 14 );

		// Anniversaries are visibility-scoped, so their cache must be per user.
		$anniversary_cache_key  = 'rondo_anniversaries_365_' . get_current_user_id();
		$upcoming_anniversaries = get_transient( $anniversary_cache_key );
		if ( $upcoming_anniversaries === false ) {
			$reminders_rest         = new Reminders();
			$upcoming_anniversaries = $reminders_rest->get_upcoming_anniversaries_data( 365, 20 );
			set_transient( $anniversary_cache_key, $upcoming_anniversaries, DAY_IN_SECONDS );
		}

		// Get open/awaiting todos count (user-specific, can't consolidate)
		$open_todos_count     = $this->count_open_todos();
		$awaiting_todos_count = $this->count_awaiting_todos();

		// Recently contacted (people with most recent activities)
		$recently_contacted = $this->get_recently_contacted_people( 5 );

		// VOG counts (conditional on capability)
		$vog_counts = null;
		if ( current_user_can( 'vog' ) ) {
			$vog_counts = $this->get_vog_counts();
		}

		// Discipline case count (conditional on capability)
		$discipline_case_count = null;
		if ( current_user_can( 'fairplay' ) ) {
			$discipline_case_count = $this->get_discipline_case_count();
		}

		// Open todos for dashboard display (limited to 5)
		$open_todos = $this->get_dashboard_todos( 5 );

		// User settings (avoids separate API calls)
		$user_settings      = new \Rondo\REST\UserSettings();
		$dashboard_settings = $user_settings->get_dashboard_settings_data( $user_id );
		$current_user_data  = $user_settings->get_current_user_data( $user_id );

		$response_data = [
			'stats'                  => [
				'total_people'          => $total_people,
				'total_teams'           => $total_teams,
				'total_commissies'      => $total_commissies,
				'open_todos_count'      => $open_todos_count,
				'awaiting_todos_count'  => $awaiting_todos_count,
				'total_volunteers'      => $total_volunteers,
				'open_feedback_count'   => $open_feedback_count,
				'vog_counts'            => $vog_counts,
				'discipline_case_count' => $discipline_case_count,
			],
			'recent_people'          => array_map( [ $this, 'format_person_summary' ], $recent_people ),
			'upcoming_reminders'     => $this->limit_items_with_all_today( $upcoming_reminders, 5 ),
			'upcoming_anniversaries' => $this->limit_items_with_all_today( $upcoming_anniversaries, 5 ),
			'recently_contacted'     => $recently_contacted,
			'open_todos'             => $open_todos,
			'dashboard_settings'     => $dashboard_settings,
			'current_user'           => $current_user_data,
		];

		set_transient( $cache_key, $response_data, 15 * MINUTE_IN_SECONDS );

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

		$response['fields'] = \Rondo\Fields\RestFields::for_post( $post->post_type, $post->ID );

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
	/**
	 * Get people, volunteer, and open feedback counts in a single query.
	 *
	 * Replaces three separate WP_Query calls with one raw SQL query.
	 *
	 * @return object Object with total_people, total_volunteers, open_feedback_count.
	 */
	private function get_dashboard_counts() {
		global $wpdb;

		// Raw SQL bypasses every query filter, so the person scope has to be
		// applied by hand — otherwise a member who may see nobody is still told
		// how many people the club has.
		$visible_person_ids = \Rondo\Core\AccessControl::visible_person_ids_or_null();
		$person_scope_sql   = '';
		if ( $visible_person_ids !== null ) {
			$ids              = implode( ',', array_map( 'absint', $visible_person_ids ?: [ 0 ] ) );
			$person_scope_sql = " AND ( p.post_type <> 'person' OR p.ID IN ($ids) )";
		}

		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are absint-cast above.
			"SELECT
				SUM(CASE WHEN p.post_type = 'person'
					AND (fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0')
					THEN 1 ELSE 0 END) AS total_people,
				SUM(CASE WHEN p.post_type = 'person'
					AND (fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0')
					AND hv.meta_value = '1'
					THEN 1 ELSE 0 END) AS total_volunteers,
				SUM(CASE WHEN p.post_type = 'rondo_feedback'
					AND (fs.meta_value IS NULL OR fs.meta_value NOT IN ('resolved','declined'))
					THEN 1 ELSE 0 END) AS open_feedback_count
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} fm ON p.ID = fm.post_id AND fm.meta_key = 'former_member'
			LEFT JOIN {$wpdb->postmeta} hv ON p.ID = hv.post_id AND hv.meta_key = 'huidig-vrijwilliger'
			LEFT JOIN {$wpdb->postmeta} fs ON p.ID = fs.post_id AND fs.meta_key = 'status'
			WHERE p.post_status = 'publish'
			AND p.post_type IN ('person', 'rondo_feedback')" . $person_scope_sql
		);
	}

	/**
	 * Get VOG counts for the dashboard in a single query.
	 *
	 * Returns three counts:
	 * - not_submitted_to_justis: volunteers needing VOG who have NOT been submitted
	 * - submitted_to_justis: volunteers needing VOG who HAVE been submitted
	 * - expiring_soon: volunteers whose VOG expires within 30 days
	 *
	 * @return array Associative array with the three counts.
	 */
	private function get_vog_counts() {
		global $wpdb;

		$cutoff_date   = gmdate( 'Y-m-d', strtotime( '-3 years' ) );
		$expired_date  = gmdate( 'Y-m-d', strtotime( '-3 years' ) );
		$expiring_date = gmdate( 'Y-m-d', strtotime( '+30 days -3 years' ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN needs_vog = 1 AND (vjs.meta_value IS NULL OR vjs.meta_value = '') THEN 1 ELSE 0 END) AS not_submitted_to_justis,
					SUM(CASE WHEN needs_vog = 1 AND (vjs.meta_value IS NOT NULL AND vjs.meta_value != '') THEN 1 ELSE 0 END) AS submitted_to_justis,
					SUM(CASE WHEN expiring_soon = 1 THEN 1 ELSE 0 END) AS expiring_soon
				FROM (
					SELECT p.ID,
						CASE WHEN (dv.meta_value IS NULL OR dv.meta_value = '' OR dv.meta_value <= %s) THEN 1 ELSE 0 END AS needs_vog,
						CASE WHEN (dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value > %s AND dv.meta_value <= %s) THEN 1 ELSE 0 END AS expiring_soon
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} hv ON p.ID = hv.post_id AND hv.meta_key = 'huidig-vrijwilliger' AND hv.meta_value = '1'
					LEFT JOIN {$wpdb->postmeta} fm ON p.ID = fm.post_id AND fm.meta_key = 'former_member'
					LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'
					WHERE p.post_type = 'person'
					AND p.post_status = 'publish'
					AND (fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0')
				) AS volunteers
				LEFT JOIN {$wpdb->postmeta} vjs ON volunteers.ID = vjs.post_id AND vjs.meta_key = 'vog_justis_submitted_date'",
				$cutoff_date,
				$expired_date,
				$expiring_date
			)
		);

		return [
			'not_submitted_to_justis' => (int) ( $row->not_submitted_to_justis ?? 0 ),
			'submitted_to_justis'     => (int) ( $row->submitted_to_justis ?? 0 ),
			'expiring_soon'           => (int) ( $row->expiring_soon ?? 0 ),
		];
	}

	/**
	 * Get discipline case count for the current season.
	 *
	 * @return int Number of discipline cases in the current season.
	 */
	private function get_discipline_case_count() {
		$taxonomies     = new \RONDO_Taxonomies();
		$current_season = $taxonomies->get_current_season();

		$args = [
			'post_type'      => 'discipline_case',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		];

		if ( $current_season ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'seizoen',
					'terms'    => $current_season->term_id,
				],
			];
		}

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Get open todos for dashboard display.
	 *
	 * Returns formatted todos visible to the current user, sorted by due date.
	 *
	 * @param int $limit Maximum number of todos to return.
	 * @return array Formatted todo items.
	 */
	private function get_dashboard_todos( $limit = 5 ) {
		$current_user_id = get_current_user_id();

		$todos = get_posts(
			[
				'post_type'        => 'rondo_todo',
				'posts_per_page'   => $limit * 2, // Fetch extra to account for access filtering
				'post_status'      => 'rondo_open',
				'suppress_filters' => false,
				'orderby'          => 'date',
				'order'            => 'DESC',
			]
		);

		$formatted = [];
		foreach ( $todos as $todo ) {
			// Access check: user must be author or assigned user
			$assigned_user = (int) get_post_meta( $todo->ID, 'assigned_user_id', true );
			if ( (int) $todo->post_author !== $current_user_id && $assigned_user !== $current_user_id ) {
				continue;
			}

			$person_ids = \Rondo\Fields\Fields::get_for_post( $todo->ID, 'related_persons' ) ?: [];
			if ( ! is_array( $person_ids ) ) {
				$person_ids = $person_ids ? [ $person_ids ] : [];
			}

			$persons = [];
			foreach ( $person_ids as $pid ) {
				$persons[] = [
					'id'        => (int) $pid,
					'name'      => html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ),
					'thumbnail' => get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '',
				];
			}

			$status_map = [
				'rondo_open'      => 'open',
				'rondo_awaiting'  => 'awaiting',
				'rondo_completed' => 'completed',
				'publish'         => 'open',
			];
			$todo_dates = \Rondo\Fields\Formatter::for_wire(
				'rondo_todo',
				[
					'due_date'       => \Rondo\Fields\Fields::get_for_post( $todo->ID, 'due_date' ),
					'awaiting_since' => \Rondo\Fields\Fields::get_for_post( $todo->ID, 'awaiting_since' ),
				]
			);

			$formatted[] = [
				'id'               => $todo->ID,
				'type'             => 'todo',
				'content'          => html_entity_decode( $todo->post_title, ENT_QUOTES, 'UTF-8' ),
				'person_id'        => $persons[0]['id'] ?? null,
				'person_name'      => $persons[0]['name'] ?? '',
				'person_thumbnail' => $persons[0]['thumbnail'] ?? '',
				'persons'          => $persons,
				'author_id'        => (int) $todo->post_author,
				'assigned_user_id' => $assigned_user > 0 ? $assigned_user : null,
				'created'          => $todo->post_date,
				'status'           => $status_map[ $todo->post_status ] ?? 'open',
				'due_date'         => $todo_dates['due_date'],
				'awaiting_since'   => $todo_dates['awaiting_since'],
			];

			if ( count( $formatted ) >= $limit ) {
				break;
			}
		}

		// Sort by due date (earliest first), nulls last
		usort(
			$formatted,
			function ( $a, $b ) {
				if ( $a['due_date'] && $b['due_date'] ) {
					return strtotime( $a['due_date'] ) - strtotime( $b['due_date'] );
				}
				if ( $a['due_date'] && ! $b['due_date'] ) {
					return -1;
				}
				if ( ! $a['due_date'] && $b['due_date'] ) {
					return 1;
				}
				return strtotime( $b['created'] ) - strtotime( $a['created'] );
			}
		);

		return $formatted;
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
				'suppress_filters'       => false,
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

		$volunteer_signup_info = $request->get_param( 'volunteer_signup_info' );
		if ( $volunteer_signup_info !== null ) {
			\Rondo\Config\ClubConfig::update_volunteer_signup_info( $volunteer_signup_info );
		}

		$second_half_opens = $request->get_param( 'volunteer_second_half_opens' );
		if ( $second_half_opens !== null
			&& ! \Rondo\Config\ClubConfig::update_volunteer_second_half_opens( $second_half_opens ) ) {
			return new \WP_Error(
				'rondo_invalid_second_half_opens',
				__( 'Gebruik een geldige datum in de vorm MM-DD, bijvoorbeeld 11-01.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$iva_approval_email_subject = $request->get_param( 'iva_approval_email_subject' );
		if ( $iva_approval_email_subject !== null ) {
			\Rondo\Config\ClubConfig::update_iva_approval_email_subject( $iva_approval_email_subject );
		}

		$iva_approval_email_body = $request->get_param( 'iva_approval_email_body' );
		if ( $iva_approval_email_body !== null ) {
			\Rondo\Config\ClubConfig::update_iva_approval_email_body( $iva_approval_email_body );
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
				'timeout' => 180,
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
		// Look up the person by KNVB ID via canonical field meta, then sync via person ID.
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
	 * Find people where email_1 or email_2 matches a query fragment.
	 *
	 * @param string $query Email search fragment.
	 * @param int    $limit Max number of results.
	 * @return array<int, \WP_Post>
	 */
	private function find_people_by_contact_email_fragment( string $query, int $limit = 20 ): array {
		$query_lower = strtolower( trim( $query ) );
		if ( $query_lower === '' ) {
			return [];
		}

		return get_posts(
			[
				'post_type'        => 'person',
				'suppress_filters' => false,
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				'meta_query'       => [
					'relation' => 'OR',
					[
						'key'     => 'email_1',
						'value'   => $query_lower,
						'compare' => 'LIKE',
					],
					[
						'key'     => 'email_2',
						'value'   => $query_lower,
						'compare' => 'LIKE',
					],
				],
			]
		);
	}

	/**
	 * Find a person post ID by their KNVB ID stored in native field meta.
	 *
	 * @param string $knvb_id The KNVB member ID.
	 * @return int|null Person post ID, or null if not found.
	 */
	private function find_person_id_by_knvb_id( string $knvb_id ): ?int {
		// native field stores the field key as meta for the 'knvb-id' field.
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
	 * @param array $row Raw native field work_history row.
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
		$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' );
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
			\Rondo\Fields\Fields::update_for_post( $person_id, 'work_history', $deduped );
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
	 * person's work_history canonical field (is_current entries). Used by the
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
	 * Derives functies from the person's current work_history canonical field and
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
