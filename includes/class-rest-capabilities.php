<?php
/**
 * REST API Endpoints for Capability Matrix, Roles, and Access Management
 *
 * Handles volunteer role classification, werkfuncties, functie-to-capability
 * mapping, commissie-to-capability mapping, the role×capability matrix,
 * age-group access restrictions, and custom role management.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Capabilities extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register capability and role management REST routes.
	 */
	public function register_routes() {
		// Volunteer role classification - available roles (admin only).
		register_rest_route(
			'rondo/v1',
			'/volunteer-roles/available',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_available_volunteer_roles' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Volunteer role classification settings (read: all users, write: admin).
		register_rest_route(
			'rondo/v1',
			'/volunteer-roles/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_volunteer_role_settings' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_volunteer_role_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'player_roles'   => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
							'sanitize_callback' => function ( $param ) {
								return array_values( array_unique( array_map( 'sanitize_text_field', $param ) ) );
							},
						],
						'excluded_roles' => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
							'sanitize_callback' => function ( $param ) {
								return array_values( array_unique( array_map( 'sanitize_text_field', $param ) ) );
							},
						],
						'staff_roles'    => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
							'sanitize_callback' => function ( $param ) {
								return array_values( array_unique( array_map( 'sanitize_text_field', $param ) ) );
							},
						],
					],
				],
			]
		);

		// Werkfuncties - available werkfuncties from database (admin only).
		register_rest_route(
			'rondo/v1',
			'/werkfuncties/available',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_available_werkfuncties' ],
				'permission_callback' => [ $this, 'check_admin_or_financieel_permission' ],
			]
		);

		// Functie-to-capability mapping (admin only for both read and write).
		register_rest_route(
			'rondo/v1',
			'/functie-capability-map',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_functie_capability_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_functie_capability_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'map' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Commissie-to-capability mapping (admin only for both read and write).
		register_rest_route(
			'rondo/v1',
			'/commissie-capability-map',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_commissie_capability_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_commissie_capability_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'map' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Capability matrix (admin only — manage role×capability assignments).
		register_rest_route(
			'rondo/v1',
			'/settings/capability-matrix',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_capability_matrix' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_capability_matrix' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'roles' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Age-group access (admin only — per-role leeftijdsgroep restrictions).
		register_rest_route(
			'rondo/v1',
			'/settings/age-group-access',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_age_group_access' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_age_group_access' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'roles' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Custom role management (admin only — create/delete custom roles).
		register_rest_route(
			'rondo/v1',
			'/settings/roles',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_custom_role' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'label' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( trim( $param ) );
						},
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/settings/roles/(?P<slug>[a-z0-9_]+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_custom_role' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
	}

	/**
	 * Get all distinct job_title values from work_history across all person posts.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response List of distinct role names.
	 */
	public function get_available_volunteer_roles( $request ) {
		global $wpdb;

		$results = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key LIKE 'work_history_%_job_title'
			 AND meta_value != ''
			 ORDER BY meta_value ASC"
		);

		return rest_ensure_response( $results ?: [] );
	}

	/**
	 * Get current volunteer role classification settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Settings with current and default role arrays.
	 */
	public function get_volunteer_role_settings( $request ) {
		return rest_ensure_response(
			[
				'player_roles'           => \Rondo\Core\VolunteerStatus::get_player_roles(),
				'excluded_roles'         => \Rondo\Core\VolunteerStatus::get_excluded_roles(),
				'staff_roles'            => \Rondo\Core\VolunteerStatus::get_staff_roles(),
				'default_player_roles'   => \Rondo\Core\VolunteerStatus::get_default_player_roles(),
				'default_excluded_roles' => \Rondo\Core\VolunteerStatus::get_default_excluded_roles(),
				'default_staff_roles'    => \Rondo\Core\VolunteerStatus::get_default_staff_roles(),
			]
		);
	}

	/**
	 * Update volunteer role classification settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Updated settings with recalculation count.
	 */
	public function update_volunteer_role_settings( $request ) {
		$player_roles   = $request->get_param( 'player_roles' );
		$excluded_roles = $request->get_param( 'excluded_roles' );
		$staff_roles    = $request->get_param( 'staff_roles' );

		if ( $player_roles !== null ) {
			update_option( \Rondo\Core\VolunteerStatus::OPTION_PLAYER_ROLES, $player_roles );
		}

		if ( $excluded_roles !== null ) {
			update_option( \Rondo\Core\VolunteerStatus::OPTION_EXCLUDED_ROLES, $excluded_roles );
		}

		if ( $staff_roles !== null ) {
			update_option( \Rondo\Core\VolunteerStatus::OPTION_STAFF_ROLES, $staff_roles );
		}

		// Trigger volunteer status recalculation for all people.
		$people_recalculated = $this->trigger_vog_recalculation();

		return rest_ensure_response(
			[
				'player_roles'        => \Rondo\Core\VolunteerStatus::get_player_roles(),
				'excluded_roles'      => \Rondo\Core\VolunteerStatus::get_excluded_roles(),
				'staff_roles'         => \Rondo\Core\VolunteerStatus::get_staff_roles(),
				'people_recalculated' => $people_recalculated,
			]
		);
	}

	/**
	 * Get all distinct werkfunctie values from the database.
	 *
	 * Derives job titles from current people work_history rows and returns
	 * unique werkfunctie values sorted alphabetically.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response List of distinct werkfunctie names.
	 */
	public function get_available_werkfuncties( $request ) {
		$people = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => 'work_history',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$all_werkfuncties = [];
		foreach ( $people as $person_id ) {
			$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' ) ?: [];
			foreach ( $work_history as $position ) {
				if ( ! empty( $position['job_title'] ) && is_string( $position['job_title'] ) ) {
					$job_title = trim( $position['job_title'] );
					if ( $job_title !== '' ) {
						$all_werkfuncties[ $job_title ] = true;
					}
				}
			}
		}

		$unique = array_keys( $all_werkfuncties );
		sort( $unique );

		return rest_ensure_response( $unique );
	}

	/**
	 * Get all Rondo roles as [ slug, label ] pairs.
	 *
	 * @return array[] Array of [ 'slug' => string, 'label' => string ].
	 */
	private function get_rondo_roles_list(): array {
		$roles = [];
		foreach ( \Rondo\Core\UserRoles::get_all_roles() as $slug => $data ) {
			$roles[] = [
				'slug'  => $slug,
				'label' => $data[0],
			];
		}
		return $roles;
	}

	/**
	 * Get the current Functie-to-Role capability mapping.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The current map plus all available Rondo roles.
	 */
	public function get_functie_capability_map( $request ) {
		return rest_ensure_response(
			[
				'map'   => \Rondo\Config\FunctieCapabilityMap::get_map(),
				'roles' => $this->get_rondo_roles_list(),
			]
		);
	}

	/**
	 * Update the Functie-to-Role capability mapping.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The updated map plus all available Rondo roles.
	 */
	public function update_functie_capability_map( $request ) {
		$raw_map = $request->get_param( 'map' );

		// Sanitize: ensure all keys are strings and all values are arrays of booleans.
		$sanitized = [];
		if ( is_array( $raw_map ) ) {
			foreach ( $raw_map as $functie => $role_flags ) {
				$key = sanitize_text_field( $functie );
				if ( ! is_array( $role_flags ) ) {
					continue;
				}
				$sanitized[ $key ] = [];
				foreach ( $role_flags as $role_slug => $enabled ) {
					$sanitized[ $key ][ sanitize_text_field( $role_slug ) ] = (bool) $enabled;
				}
			}
		}

		\Rondo\Config\FunctieCapabilityMap::update_map( $sanitized );

		return rest_ensure_response(
			[
				'map'   => \Rondo\Config\FunctieCapabilityMap::get_map(),
				'roles' => $this->get_rondo_roles_list(),
			]
		);
	}

	/**
	 * Get the current Commissie-to-Role capability mapping.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The current map, all commissies, and available roles.
	 */
	public function get_commissie_capability_map( $request ) {
		$commissies = get_posts(
			[
				'post_type'      => 'commissie',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		$commissie_list = [];
		foreach ( $commissies as $c ) {
			$commissie_list[] = [
				'id'   => $c->ID,
				'name' => $c->post_title,
			];
		}

		return rest_ensure_response(
			[
				'map'        => \Rondo\Config\CommissieCapabilityMap::get_map(),
				'commissies' => $commissie_list,
				'roles'      => $this->get_rondo_roles_list(),
			]
		);
	}

	/**
	 * Update the Commissie-to-Role capability mapping.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The updated map plus all available Rondo roles.
	 */
	public function update_commissie_capability_map( $request ) {
		$raw_map = $request->get_param( 'map' );

		$sanitized = [];
		if ( is_array( $raw_map ) ) {
			foreach ( $raw_map as $commissie_id => $role_flags ) {
				$key = sanitize_text_field( $commissie_id );
				if ( ! is_array( $role_flags ) ) {
					continue;
				}
				$sanitized[ $key ] = [];
				foreach ( $role_flags as $role_slug => $enabled ) {
					$sanitized[ $key ][ sanitize_text_field( $role_slug ) ] = (bool) $enabled;
				}
			}
		}

		\Rondo\Config\CommissieCapabilityMap::update_map( $sanitized );

		// Re-fetch full response (reuse GET handler logic).
		return $this->get_commissie_capability_map( $request );
	}

	/**
	 * Get the role × capability matrix for all Rondo roles + administrator.
	 *
	 * @return \WP_REST_Response Matrix of roles and their custom capabilities.
	 */
	public function get_capability_matrix() {
		$capability_labels = [
			'fairplay'           => 'FairPlay',
			'vog'                => 'VOG',
			'financieel'         => 'Financieel (bewerken)',
			'financieel_read'    => 'Financieel (lezen)',
			'toegangscontrole'   => 'Toegangscontrole',
			'manage_clothing'    => 'Kledingbeheer',
			'ledenadministratie' => 'Ledenadministratie',
			'sponsorbeheer'      => 'Sponsorbeheer',
			'narrowcasting'      => 'Club TV-content',
			'vrijwilligers'      => 'Vrijwilligersbeheer',
			'rondo_iva_approve'  => 'IVA goedkeuren',
		];

		$wp_roles     = wp_roles();
		$all_roles    = \Rondo\Core\UserRoles::get_all_roles();
		$custom_slugs = array_keys( \Rondo\Core\UserRoles::get_custom_roles() );
		$role_slugs   = array_keys( $all_roles );
		$role_slugs[] = 'administrator';

		$roles = [];
		foreach ( $role_slugs as $slug ) {
			$role_obj = $wp_roles->get_role( $slug );
			if ( ! $role_obj ) {
				continue;
			}

			if ( $slug === 'administrator' ) {
				$label = 'Administrator';
			} else {
				$label = $all_roles[ $slug ][0] ?? $slug;
			}

			$caps = [];
			foreach ( array_keys( $capability_labels ) as $cap ) {
				$caps[ $cap ] = ! empty( $role_obj->capabilities[ $cap ] );
			}

			$roles[ $slug ] = [
				'label'        => $label,
				'capabilities' => $caps,
				'is_custom'    => in_array( $slug, $custom_slugs, true ),
			];
		}

		return new \WP_REST_Response(
			[
				'roles'                   => $roles,
				'capability_labels'       => $capability_labels,
				// Granting one of these makes a role's age-group restriction
				// meaningless, so the UI clears it. Served from the PHP constant
				// rather than duplicated in the frontend, where the copy silently
				// went stale every time the list changed.
				'management_capabilities' => \Rondo\Core\AccessControl::get_management_capabilities(),
			]
		);
	}

	/**
	 * Update the role × capability matrix.
	 *
	 * Accepts { roles: { slug: { capabilities: { cap: bool } } } }.
	 * Diffs each role's desired capabilities against current state and applies changes.
	 * Administrator's manage_options capability is never removable.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Updated matrix or error.
	 */
	public function update_capability_matrix( $request ) {
		$submitted_roles = $request->get_param( 'roles' );

		if ( ! is_array( $submitted_roles ) ) {
			return new \WP_Error(
				'invalid_data',
				'The roles parameter must be an object of role slugs.',
				[ 'status' => 400 ]
			);
		}

		$allowed_caps  = [ 'fairplay', 'vog', 'financieel', 'financieel_read', 'toegangscontrole', 'manage_clothing', 'ledenadministratie', 'sponsorbeheer', 'narrowcasting', 'vrijwilligers', 'rondo_iva_approve' ];
		$valid_slugs   = array_keys( \Rondo\Core\UserRoles::get_all_roles() );
		$valid_slugs[] = 'administrator';

		foreach ( $submitted_roles as $slug => $role_data ) {
			if ( ! in_array( $slug, $valid_slugs, true ) ) {
				continue;
			}

			$role_obj = get_role( $slug );
			if ( ! $role_obj ) {
				continue;
			}

			$desired_caps = $role_data['capabilities'] ?? [];
			if ( ! is_array( $desired_caps ) ) {
				continue;
			}

			foreach ( $desired_caps as $cap => $enabled ) {
				if ( ! in_array( $cap, $allowed_caps, true ) ) {
					continue;
				}

				// Never remove manage_options from administrator.
				if ( $slug === 'administrator' && $cap === 'manage_options' && ! $enabled ) {
					continue;
				}

				$has_cap = ! empty( $role_obj->capabilities[ $cap ] );

				if ( $enabled && ! $has_cap ) {
					$role_obj->add_cap( $cap );
				} elseif ( ! $enabled && $has_cap ) {
					$role_obj->remove_cap( $cap );
				}
			}

			\Rondo\Core\UserRoles::sync_role_capabilities( $slug );
		}

		// Return fresh matrix state.
		return $this->get_capability_matrix();
	}

	/**
	 * Get age-group access configuration.
	 *
	 * Returns the per-role age-group restrictions and the list of available
	 * leeftijdsgroep values currently in use across person records.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_age_group_access() {
		global $wpdb;

		// Current per-role config (default: empty = no restrictions).
		$raw = get_option( 'rondo_age_group_access', [] );
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		// Query distinct leeftijdsgroep values in use.
		$rows = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = 'leeftijdsgroep'
			   AND pm.meta_value != ''
			   AND p.post_type = 'person'
			   AND p.post_status = 'publish'
			 ORDER BY pm.meta_value ASC"
		);

		// Sort age groups naturally (Onder 6, Onder 7, …, Onder 19, Senioren).
		usort(
			$rows,
			function ( $a, $b ) {
				$num_a = preg_match( '/(\d+)/', $a, $m ) ? (int) $m[1] : 999;
				$num_b = preg_match( '/(\d+)/', $b, $m ) ? (int) $m[1] : 999;
				return $num_a - $num_b;
			}
		);

		return rest_ensure_response(
			[
				'roles'                => (object) $raw,
				'available_age_groups' => array_values( $rows ),
			]
		);
	}

	/**
	 * Update age-group access configuration.
	 *
	 * Accepts per-role arrays of permitted leeftijdsgroep values. Empty arrays
	 * are removed (empty = no restriction for that role).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_age_group_access( $request ) {
		$submitted_roles = $request->get_param( 'roles' );

		if ( ! is_array( $submitted_roles ) ) {
			return new \WP_Error(
				'invalid_data',
				'The roles parameter must be an object of role slugs.',
				[ 'status' => 400 ]
			);
		}

		$valid_slugs   = array_keys( \Rondo\Core\UserRoles::get_all_roles() );
		$valid_slugs[] = 'administrator';

		$config = [];

		foreach ( $submitted_roles as $slug => $age_groups ) {
			if ( ! in_array( $slug, $valid_slugs, true ) ) {
				return new \WP_Error(
					'invalid_role_slug',
					sprintf( 'Invalid role slug: %s', $slug ),
					[ 'status' => 400 ]
				);
			}

			if ( ! is_array( $age_groups ) ) {
				continue;
			}

			// Sanitize values.
			$sanitized = array_values( array_filter( array_map( 'sanitize_text_field', $age_groups ) ) );

			// Only store non-empty arrays (empty = no restriction).
			if ( ! empty( $sanitized ) ) {
				$config[ $slug ] = $sanitized;
			}
		}

		update_option( 'rondo_age_group_access', $config );

		// Return fresh state.
		return $this->get_age_group_access();
	}

	/**
	 * Create a custom role.
	 *
	 * @param \WP_REST_Request $request The request object with 'label' param.
	 * @return \WP_REST_Response|\WP_Error Created role data or error.
	 */
	public function create_custom_role( $request ) {
		$label  = sanitize_text_field( $request->get_param( 'label' ) );
		$result = \Rondo\Core\UserRoles::add_custom_role( $label );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			[
				'slug'  => $result,
				'label' => $label,
			],
			201
		);
	}

	/**
	 * Delete a custom role.
	 *
	 * @param \WP_REST_Request $request The request object with 'slug' URL param.
	 * @return \WP_REST_Response|\WP_Error Success response or error.
	 */
	public function delete_custom_role( $request ) {
		$slug   = $request->get_param( 'slug' );
		$result = \Rondo\Core\UserRoles::remove_custom_role( $slug );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			[
				'deleted' => true,
				'slug'    => $slug,
			]
		);
	}

	/**
	 * Trigger volunteer status recalculation for all people.
	 *
	 * @return int Number of people recalculated.
	 */
	private function trigger_vog_recalculation(): int {
		$volunteer_status = new \Rondo\Core\VolunteerStatus();

		$people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			]
		);

		foreach ( $people as $person_id ) {
			$volunteer_status->calculate_and_update_status( $person_id );
		}

		return count( $people );
	}
}
