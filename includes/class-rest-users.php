<?php
/**
 * User Management REST API Controller
 *
 * Handles admin user management: listing users, deleting users, provisioning,
 * provisioning settings, and user search for sharing.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Users extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes for user management.
	 */
	public function register_routes() {
		// User list (admin only)
		register_rest_route(
			'rondo/v1',
			'/users',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_users' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Delete user (admin only)
		register_rest_route(
			'rondo/v1',
			'/users/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_user' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'user_id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Provisionable users (admin only)
		register_rest_route(
			'rondo/v1',
			'/users/provisionable',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_provisionable_users' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// User search (for sharing)
		register_rest_route(
			'rondo/v1',
			'/users/search',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'search_users' ],
				'permission_callback' => 'is_user_logged_in',
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

		// User provisioning (admin only)
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/provision',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'provision_user' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'person_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Provisioning email template settings (admin only)
		register_rest_route(
			'rondo/v1',
			'/provisioning/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_provisioning_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_provisioning_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		// Onboarding email template settings, per type (admin only).
		// Stored as separate options so the existing account-provisioning template is untouched.
		register_rest_route(
			'rondo/v1',
			'/onboarding/email-settings/(?P<type>lid|vrijwilliger)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_onboarding_email_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_onboarding_email_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);
	}

	/**
	 * Get list of users (admin only).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_users( $request ) {
		$users = get_users(
			[
				'meta_key'     => 'rondo_linked_person_id',
				'meta_compare' => '!=',
				'meta_value'   => '',
				'number'       => -1,
			]
		);

		$user_list = [];
		foreach ( $users as $user ) {
			$linked_person_id   = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
			$linked_person_name = null;
			if ( $linked_person_id ) {
				$person = get_post( $linked_person_id );
				if ( $person && $person->post_type === 'person' ) {
					$first              = get_field( 'first_name', $linked_person_id ) ?: '';
					$infix              = get_field( 'infix', $linked_person_id ) ?: '';
					$last               = get_field( 'last_name', $linked_person_id ) ?: '';
					$linked_person_name = implode( ' ', array_filter( [ $first, $infix, $last ] ) );
				}
			}

			$user_list[] = [
				'id'                 => $user->ID,
				'name'               => $user->display_name,
				'email'              => $user->user_email,
				'registered'         => $user->user_registered,
				'last_active'        => get_user_meta( $user->ID, 'rondo_last_active', true ) ?: null,
				'linked_person_id'   => $linked_person_id ?: null,
				'linked_person_name' => $linked_person_name,
			];
		}

		return rest_ensure_response( $user_list );
	}

	/**
	 * Get people eligible for provisioning.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_provisionable_users( $request ) {
		$search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

		if ( strlen( $search ) < 2 ) {
			return rest_ensure_response( [] );
		}

		$meta_key = \Rondo\Users\UserProvisioning::META_USER_ID;

		$people = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				's'              => $search,
				// No knvb-id requirement: the parents who carry the ouderplicht are not
				// Sportlink members and have none. An email address is the only thing a
				// provisionable person actually needs.
				'meta_query'     => [
					[
						'key'     => $meta_key,
						'compare' => 'NOT EXISTS',
					],
				],
				'fields'         => 'ids',
			]
		);

		$result = [];
		foreach ( $people as $person_id ) {
			if ( get_field( 'former_member', $person_id ) === true ) {
				continue;
			}

			$email = get_field( 'email_1', $person_id );
			if ( ! is_email( $email ) ) {
				$email = get_field( 'email_2', $person_id );
			}
			if ( ! is_email( $email ) ) {
				continue;
			}

			$first     = get_field( 'first_name', $person_id ) ?: '';
			$last      = get_field( 'last_name', $person_id ) ?: '';
			$thumbnail = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null;

			$result[] = [
				'id'        => $person_id,
				'name'      => trim( $first . ' ' . $last ),
				'email'     => $email,
				'thumbnail' => $thumbnail,
			];
		}

		usort( $result, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

		return rest_ensure_response( $result );
	}

	/**
	 * Provision a WordPress user account for a person (admin only).
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function provision_user( $request ) {
		$person_id   = (int) $request->get_param( 'person_id' );
		$provisioner = new \Rondo\Users\UserProvisioning();
		$result      = $provisioner->provision( $person_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get provisioning email template settings (admin only).
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function get_provisioning_settings( $request ) {
		$provisioner = new \Rondo\Users\UserProvisioning();
		return rest_ensure_response( $provisioner->get_settings() );
	}

	/**
	 * Update provisioning email template settings (admin only).
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function update_provisioning_settings( $request ) {
		$provisioner = new \Rondo\Users\UserProvisioning();

		$settings = array_filter(
			[
				'subject'    => $request->get_param( 'subject' ),
				'body'       => $request->get_param( 'body' ),
				'from_email' => $request->get_param( 'from_email' ),
				'from_name'  => $request->get_param( 'from_name' ),
			],
			function ( $v ) {
				return $v !== null;
			}
		);

		return rest_ensure_response( $provisioner->update_settings( $settings ) );
	}

	/**
	 * Get onboarding email template settings for a given type.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function get_onboarding_email_settings( $request ) {
		$type   = $request->get_param( 'type' );
		$sender = new \Rondo\Notifications\OnboardingEmailSender();
		return rest_ensure_response( $sender->get_settings( $type ) );
	}

	/**
	 * Update onboarding email template settings for a given type.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function update_onboarding_email_settings( $request ) {
		$type   = $request->get_param( 'type' );
		$sender = new \Rondo\Notifications\OnboardingEmailSender();

		$settings = array_filter(
			[
				'subject' => $request->get_param( 'subject' ),
				'body'    => $request->get_param( 'body' ),
			],
			function ( $v ) {
				return $v !== null;
			}
		);

		return rest_ensure_response( $sender->update_settings( $type, $settings ) );
	}

	/**
	 * Delete a user and all their related data (admin only).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_user( $request ) {
		$user_id = (int) $request->get_param( 'user_id' );

		if ( $user_id === get_current_user_id() ) {
			return new \WP_Error(
				'cannot_delete_self',
				__( 'You cannot delete your own account.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error(
				'user_not_found',
				__( 'User not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$this->delete_user_posts( $user_id );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$result = wp_delete_user( $user_id );

		if ( ! $result ) {
			return new \WP_Error(
				'delete_failed',
				__( 'Failed to delete user.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'User and all related data deleted.', 'rondo' ),
			]
		);
	}

	/**
	 * Search users for sharing functionality.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function search_users( $request ) {
		$query = sanitize_text_field( $request->get_param( 'q' ) );

		if ( strlen( $query ) < 2 ) {
			return rest_ensure_response( [] );
		}

		$users = get_users(
			[
				'search'         => '*' . $query . '*',
				'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
				'number'         => 10,
				'exclude'        => [ get_current_user_id() ],
			]
		);

		$result = [];
		foreach ( $users as $user ) {
			$result[] = [
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'avatar_url'   => get_avatar_url( $user->ID, [ 'size' => 48 ] ),
			];
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Delete all posts belonging to a user.
	 *
	 * @param int $user_id The user ID.
	 */
	private function delete_user_posts( $user_id ) {
		$post_types = [ 'person', 'team' ];

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				[
					'post_type'      => $post_type,
					'author'         => $user_id,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				]
			);

			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true );
			}
		}
	}
}
