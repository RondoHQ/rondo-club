<?php
/**
 * Workspaces REST API Endpoints
 *
 * Handles REST API endpoints for workspace management and member operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_REST_Workspaces extends PRM_REST_Base {

	/**
	 * Constructor
	 *
	 * Register routes for workspace endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register custom REST routes for workspace domain
	 */
	public function register_routes() {
		// GET /prm/v1/workspaces - List user's workspaces
		register_rest_route(
			'prm/v1',
			'/workspaces',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_workspaces' ),
				'permission_callback' => array( $this, 'check_user_approved' ),
			)
		);

		// POST /prm/v1/workspaces - Create workspace
		register_rest_route(
			'prm/v1',
			'/workspaces',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_workspace' ),
				'permission_callback' => array( $this, 'check_user_approved' ),
				'args'                => array(
					'title'       => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// GET /prm/v1/workspaces/(?P<id>\d+) - Get workspace details
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_workspace' ),
				'permission_callback' => array( $this, 'check_workspace_access' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// PUT /prm/v1/workspaces/(?P<id>\d+) - Update workspace
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_workspace' ),
				'permission_callback' => array( $this, 'check_workspace_admin' ),
				'args'                => array(
					'id'          => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'title'       => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// DELETE /prm/v1/workspaces/(?P<id>\d+) - Delete workspace
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_workspace' ),
				'permission_callback' => array( $this, 'check_workspace_owner' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// POST /prm/v1/workspaces/(?P<id>\d+)/members - Add member
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)/members',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_member' ),
				'permission_callback' => array( $this, 'check_workspace_admin' ),
				'args'                => array(
					'id'      => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'user_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'role'    => array(
						'required'          => false,
						'default'           => 'member',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'admin', 'member', 'viewer' ), true );
						},
					),
				),
			)
		);

		// DELETE /prm/v1/workspaces/(?P<id>\d+)/members/(?P<user_id>\d+) - Remove member
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)/members/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'remove_member' ),
				'permission_callback' => array( $this, 'check_workspace_admin' ),
				'args'                => array(
					'id'      => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'user_id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// PUT /prm/v1/workspaces/(?P<id>\d+)/members/(?P<user_id>\d+) - Update member role
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)/members/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_member_role' ),
				'permission_callback' => array( $this, 'check_workspace_admin' ),
				'args'                => array(
					'id'      => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'user_id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'role'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'admin', 'member', 'viewer' ), true );
						},
					),
				),
			)
		);

		// POST /prm/v1/workspaces/(?P<id>\d+)/invites - Create & send invite
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)/invites',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_invite' ),
				'permission_callback' => array( $this, 'check_workspace_admin' ),
				'args'                => array(
					'id'    => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'email' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					),
					'role'  => array(
						'required'          => false,
						'default'           => 'member',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'admin', 'member', 'viewer' ), true );
						},
					),
				),
			)
		);

		// GET /prm/v1/workspaces/(?P<id>\d+)/invites - List pending invites
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)/invites',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_invites' ),
				'permission_callback' => array( $this, 'check_workspace_admin' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// DELETE /prm/v1/workspaces/(?P<id>\d+)/invites/(?P<invite_id>\d+) - Revoke invite
		register_rest_route(
			'prm/v1',
			'/workspaces/(?P<id>\d+)/invites/(?P<invite_id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'revoke_invite' ),
				'permission_callback' => array( $this, 'check_workspace_admin' ),
				'args'                => array(
					'id'        => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'invite_id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// GET /prm/v1/invites/(?P<token>[a-zA-Z0-9]+) - Validate invite (PUBLIC)
		register_rest_route(
			'prm/v1',
			'/invites/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'validate_invite' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'token' => array(
						'validate_callback' => function ( $param ) {
							return preg_match( '/^[a-zA-Z0-9]+$/', $param );
						},
					),
				),
			)
		);

		// POST /prm/v1/invites/(?P<token>[a-zA-Z0-9]+)/accept - Accept invite
		register_rest_route(
			'prm/v1',
			'/invites/(?P<token>[a-zA-Z0-9]+)/accept',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'accept_invite' ),
				'permission_callback' => array( $this, 'check_user_approved' ),
				'args'                => array(
					'token' => array(
						'validate_callback' => function ( $param ) {
							return preg_match( '/^[a-zA-Z0-9]+$/', $param );
						},
					),
				),
			)
		);

		// GET /prm/v1/workspaces/members/search - Search members across workspaces
		register_rest_route(
			'prm/v1',
			'/workspaces/members/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_workspace_members' ),
				'permission_callback' => array( $this, 'check_user_approved' ),
				'args'                => array(
					'workspace_ids' => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Comma-separated workspace IDs',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'query'         => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Search query',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return strlen( $param ) >= 1;
						},
					),
				),
			)
		);
	}

	/**
	 * Check if user has access to workspace (is a member)
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can access workspace, false otherwise.
	 */
	public function check_workspace_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! $this->check_user_approved() ) {
			return false;
		}

		$workspace_id = (int) $request->get_param( 'id' );
		$user_id      = get_current_user_id();

		// Verify workspace exists
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return false;
		}

		// Admins have full access
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return PRM_Workspace_Members::is_member( $workspace_id, $user_id );
	}

	/**
	 * Check if user is admin of workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user is workspace admin, false otherwise.
	 */
	public function check_workspace_admin( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! $this->check_user_approved() ) {
			return false;
		}

		$workspace_id = (int) $request->get_param( 'id' );
		$user_id      = get_current_user_id();

		// Verify workspace exists
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return false;
		}

		// WordPress admins have full access
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return PRM_Workspace_Members::is_admin( $workspace_id, $user_id );
	}

	/**
	 * Check if user is owner of workspace (for delete operations)
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user is workspace owner, false otherwise.
	 */
	public function check_workspace_owner( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! $this->check_user_approved() ) {
			return false;
		}

		$workspace_id = (int) $request->get_param( 'id' );
		$user_id      = get_current_user_id();

		// Verify workspace exists
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return false;
		}

		// WordPress admins can delete any workspace
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Only the workspace owner can delete
		return (int) $workspace->post_author === $user_id;
	}

	/**
	 * Get list of user's workspaces
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing array of workspaces.
	 */
	public function get_workspaces( $request ) {
		$user_id         = get_current_user_id();
		$user_workspaces = PRM_Workspace_Members::get_user_workspaces( $user_id );

		$workspaces = array();
		foreach ( $user_workspaces as $membership ) {
			$workspace = get_post( $membership['workspace_id'] );
			if ( ! $workspace || $workspace->post_status !== 'publish' ) {
				continue;
			}

			$members = PRM_Workspace_Members::get_members( $membership['workspace_id'] );

			$workspaces[] = array(
				'id'           => $workspace->ID,
				'title'        => $this->sanitize_text( $workspace->post_title ),
				'description'  => $this->sanitize_text( $workspace->post_content ),
				'role'         => $membership['role'],
				'member_count' => count( $members ),
				'joined_at'    => $membership['joined_at'],
				'is_owner'     => (int) $workspace->post_author === $user_id,
			);
		}

		return rest_ensure_response( $workspaces );
	}

	/**
	 * Get workspace details including members
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing workspace data or error.
	 */
	public function get_workspace( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );
		$workspace    = get_post( $workspace_id );
		$user_id      = get_current_user_id();

		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return new WP_Error( 'workspace_not_found', __( 'Workspace not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Get members with user info
		$members_raw = PRM_Workspace_Members::get_members( $workspace_id );
		$members     = array();

		foreach ( $members_raw as $member ) {
			$user = get_user_by( 'ID', $member['user_id'] );
			if ( ! $user ) {
				continue;
			}

			$members[] = array(
				'user_id'      => $member['user_id'],
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'avatar_url'   => get_avatar_url( $member['user_id'], array( 'size' => 96 ) ),
				'role'         => $member['role'],
				'joined_at'    => $member['joined_at'],
				'is_owner'     => (int) $workspace->post_author === $member['user_id'],
			);
		}

		$current_role = PRM_Workspace_Members::get_user_role( $workspace_id, $user_id );

		$response = array(
			'id'           => $workspace->ID,
			'title'        => $this->sanitize_text( $workspace->post_title ),
			'description'  => $this->sanitize_text( $workspace->post_content ),
			'owner_id'     => (int) $workspace->post_author,
			'member_count' => count( $members ),
			'members'      => $members,
			'current_user' => array(
				'role'     => $current_role,
				'is_owner' => (int) $workspace->post_author === $user_id,
			),
			'created_at'   => $workspace->post_date_gmt,
			'modified_at'  => $workspace->post_modified_gmt,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Create a new workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing new workspace data or error.
	 */
	public function create_workspace( $request ) {
		$user_id     = get_current_user_id();
		$title       = $request->get_param( 'title' );
		$description = $request->get_param( 'description' ) ?: '';

		$post_data = array(
			'post_type'    => 'workspace',
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => 'publish',
			'post_author'  => $user_id,
		);

		$workspace_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $workspace_id ) ) {
			return new WP_Error( 'create_failed', $workspace_id->get_error_message(), array( 'status' => 500 ) );
		}

		// The save_post_workspace hook in PRM_Workspace_Members auto-adds author as admin

		$workspace = get_post( $workspace_id );

		return rest_ensure_response(
			array(
				'id'           => $workspace_id,
				'title'        => $this->sanitize_text( $workspace->post_title ),
				'description'  => $this->sanitize_text( $workspace->post_content ),
				'role'         => 'admin',
				'member_count' => 1,
				'is_owner'     => true,
				'created_at'   => $workspace->post_date_gmt,
			)
		);
	}

	/**
	 * Update workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing updated workspace data or error.
	 */
	public function update_workspace( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );
		$workspace    = get_post( $workspace_id );

		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return new WP_Error( 'workspace_not_found', __( 'Workspace not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		$post_data = array(
			'ID' => $workspace_id,
		);

		$title       = $request->get_param( 'title' );
		$description = $request->get_param( 'description' );

		if ( $title !== null ) {
			$post_data['post_title'] = $title;
		}

		if ( $description !== null ) {
			$post_data['post_content'] = $description;
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'update_failed', $result->get_error_message(), array( 'status' => 500 ) );
		}

		$workspace    = get_post( $workspace_id );
		$user_id      = get_current_user_id();
		$members      = PRM_Workspace_Members::get_members( $workspace_id );
		$current_role = PRM_Workspace_Members::get_user_role( $workspace_id, $user_id );

		return rest_ensure_response(
			array(
				'id'           => $workspace_id,
				'title'        => $this->sanitize_text( $workspace->post_title ),
				'description'  => $this->sanitize_text( $workspace->post_content ),
				'role'         => $current_role,
				'member_count' => count( $members ),
				'is_owner'     => (int) $workspace->post_author === $user_id,
				'modified_at'  => $workspace->post_modified_gmt,
			)
		);
	}

	/**
	 * Delete workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with success status or error.
	 */
	public function delete_workspace( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );
		$workspace    = get_post( $workspace_id );

		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return new WP_Error( 'workspace_not_found', __( 'Workspace not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		$result = wp_trash_post( $workspace_id );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete workspace.', 'caelis' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $workspace_id,
				'message' => __( 'Workspace moved to trash.', 'caelis' ),
			)
		);
	}

	/**
	 * Add member to workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing member data or error.
	 */
	public function add_member( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );
		$user_id      = (int) $request->get_param( 'user_id' );
		$role         = $request->get_param( 'role' ) ?: 'member';

		// Validate workspace exists
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return new WP_Error( 'workspace_not_found', __( 'Workspace not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Validate user exists
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'User not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Check if user is already a member
		if ( PRM_Workspace_Members::is_member( $workspace_id, $user_id ) ) {
			// Update their role instead
			$result = PRM_Workspace_Members::update_role( $workspace_id, $user_id, $role );
			if ( ! $result ) {
				return new WP_Error( 'update_failed', __( 'Failed to update member role.', 'caelis' ), array( 'status' => 500 ) );
			}
		} else {
			// Add new member
			$result = PRM_Workspace_Members::add( $workspace_id, $user_id, $role );
			if ( ! $result ) {
				return new WP_Error( 'add_failed', __( 'Failed to add member.', 'caelis' ), array( 'status' => 500 ) );
			}
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'user_id'      => $user_id,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'avatar_url'   => get_avatar_url( $user_id, array( 'size' => 96 ) ),
				'role'         => $role,
				'is_owner'     => (int) $workspace->post_author === $user_id,
			)
		);
	}

	/**
	 * Remove member from workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with success status or error.
	 */
	public function remove_member( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );
		$user_id      = (int) $request->get_param( 'user_id' );

		// Validate workspace exists
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return new WP_Error( 'workspace_not_found', __( 'Workspace not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Check if user is the owner (cannot be removed)
		if ( (int) $workspace->post_author === $user_id ) {
			return new WP_Error( 'cannot_remove_owner', __( 'Cannot remove the workspace owner.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Check if user is a member
		if ( ! PRM_Workspace_Members::is_member( $workspace_id, $user_id ) ) {
			return new WP_Error( 'not_a_member', __( 'User is not a member of this workspace.', 'caelis' ), array( 'status' => 400 ) );
		}

		$result = PRM_Workspace_Members::remove( $workspace_id, $user_id );

		if ( ! $result ) {
			return new WP_Error( 'remove_failed', __( 'Failed to remove member.', 'caelis' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'user_id' => $user_id,
				'message' => __( 'Member removed from workspace.', 'caelis' ),
			)
		);
	}

	/**
	 * Update member role in workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing updated member data or error.
	 */
	public function update_member_role( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );
		$user_id      = (int) $request->get_param( 'user_id' );
		$role         = $request->get_param( 'role' );

		// Validate workspace exists
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return new WP_Error( 'workspace_not_found', __( 'Workspace not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Validate user exists
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'User not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Check if user is a member
		if ( ! PRM_Workspace_Members::is_member( $workspace_id, $user_id ) ) {
			return new WP_Error( 'not_a_member', __( 'User is not a member of this workspace.', 'caelis' ), array( 'status' => 400 ) );
		}

		$result = PRM_Workspace_Members::update_role( $workspace_id, $user_id, $role );

		if ( ! $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update member role.', 'caelis' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'user_id'      => $user_id,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'avatar_url'   => get_avatar_url( $user_id, array( 'size' => 96 ) ),
				'role'         => $role,
				'is_owner'     => (int) $workspace->post_author === $user_id,
			)
		);
	}

	/**
	 * Create and send workspace invite
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing invite data or error.
	 */
	public function create_invite( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );
		$email        = $request->get_param( 'email' );
		$role         = $request->get_param( 'role' ) ?: 'member';

		// Validate workspace exists
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return new WP_Error( 'workspace_not_found', __( 'Workspace not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Check if user with this email is already a workspace member
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user && PRM_Workspace_Members::is_member( $workspace_id, $existing_user->ID ) ) {
			return new WP_Error( 'already_member', __( 'This user is already a member of this workspace.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Check if pending invite already exists for this email+workspace
		$existing_invites = get_posts(
			array(
				'post_type'      => 'workspace_invite',
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_invite_workspace_id',
						'value' => $workspace_id,
					),
					array(
						'key'   => '_invite_email',
						'value' => $email,
					),
					array(
						'key'   => '_invite_status',
						'value' => 'pending',
					),
				),
				'posts_per_page' => 1,
			)
		);

		if ( ! empty( $existing_invites ) ) {
			return new WP_Error( 'invite_exists', __( 'A pending invite already exists for this email address.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Generate secure token (alphanumeric only)
		$token = wp_generate_password( 32, false );

		// Calculate expiration (7 days from now)
		$expires_at = gmdate( 'c', strtotime( '+7 days' ) );

		// Create invite post
		$invite_data = array(
			'post_type'   => 'workspace_invite',
			'post_title'  => $email,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		);

		$invite_id = wp_insert_post( $invite_data, true );

		if ( is_wp_error( $invite_id ) ) {
			return new WP_Error( 'create_failed', $invite_id->get_error_message(), array( 'status' => 500 ) );
		}

		// Set ACF fields
		update_field( '_invite_workspace_id', $workspace_id, $invite_id );
		update_field( '_invite_email', $email, $invite_id );
		update_field( '_invite_role', $role, $invite_id );
		update_field( '_invite_token', $token, $invite_id );
		update_field( '_invite_status', 'pending', $invite_id );
		update_field( '_invite_expires_at', $expires_at, $invite_id );

		// Send invitation email
		$email_sent = $this->send_invite_email( $invite_id );

		return rest_ensure_response(
			array(
				'id'         => $invite_id,
				'email'      => $email,
				'role'       => $role,
				'status'     => 'pending',
				'expires_at' => $expires_at,
				'email_sent' => $email_sent,
			)
		);
	}

	/**
	 * Get list of pending invites for a workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing array of invites.
	 */
	public function get_invites( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );

		$invites = get_posts(
			array(
				'post_type'      => 'workspace_invite',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_invite_workspace_id',
						'value' => $workspace_id,
					),
					array(
						'key'   => '_invite_status',
						'value' => 'pending',
					),
				),
			)
		);

		$result = array();
		foreach ( $invites as $invite ) {
			$inviter  = get_user_by( 'ID', $invite->post_author );
			$result[] = array(
				'id'         => $invite->ID,
				'email'      => get_field( '_invite_email', $invite->ID ),
				'role'       => get_field( '_invite_role', $invite->ID ),
				'status'     => get_field( '_invite_status', $invite->ID ),
				'expires_at' => get_field( '_invite_expires_at', $invite->ID ),
				'invited_by' => $inviter ? $inviter->display_name : __( 'Unknown', 'caelis' ),
				'invited_at' => $invite->post_date_gmt,
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Revoke a pending invite
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with success status or error.
	 */
	public function revoke_invite( $request ) {
		$workspace_id = (int) $request->get_param( 'id' );
		$invite_id    = (int) $request->get_param( 'invite_id' );

		$invite = get_post( $invite_id );
		if ( ! $invite || $invite->post_type !== 'workspace_invite' ) {
			return new WP_Error( 'invite_not_found', __( 'Invite not found.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Verify invite belongs to this workspace
		$invite_workspace_id = (int) get_field( '_invite_workspace_id', $invite_id );
		if ( $invite_workspace_id !== $workspace_id ) {
			return new WP_Error( 'invite_not_found', __( 'Invite not found for this workspace.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Check status
		$status = get_field( '_invite_status', $invite_id );
		if ( $status !== 'pending' ) {
			return new WP_Error( 'invalid_status', __( 'Only pending invites can be revoked.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Revoke the invite
		update_field( '_invite_status', 'revoked', $invite_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $invite_id,
				'message' => __( 'Invite has been revoked.', 'caelis' ),
			)
		);
	}

	/**
	 * Validate invite token (public endpoint)
	 *
	 * Returns workspace info if token is valid and not expired.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing invite info or error.
	 */
	public function validate_invite( $request ) {
		$token = $request->get_param( 'token' );

		$invite = $this->get_invite_by_token( $token );
		if ( ! $invite ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or expired invitation.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Check status
		$status = get_field( '_invite_status', $invite->ID );
		if ( $status !== 'pending' ) {
			return new WP_Error( 'invalid_status', __( 'This invitation is no longer valid.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Check expiration
		$expires_at = get_field( '_invite_expires_at', $invite->ID );
		if ( strtotime( $expires_at ) < time() ) {
			// Mark as expired
			update_field( '_invite_status', 'expired', $invite->ID );
			return new WP_Error( 'expired', __( 'This invitation has expired.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Get workspace info
		$workspace_id = (int) get_field( '_invite_workspace_id', $invite->ID );
		$workspace    = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_status !== 'publish' ) {
			return new WP_Error( 'workspace_not_found', __( 'The workspace no longer exists.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Get inviter info
		$inviter = get_user_by( 'ID', $invite->post_author );

		return rest_ensure_response(
			array(
				'email'          => get_field( '_invite_email', $invite->ID ),
				'role'           => get_field( '_invite_role', $invite->ID ),
				'workspace_id'   => $workspace_id,
				'workspace_name' => $this->sanitize_text( $workspace->post_title ),
				'invited_by'     => $inviter ? $inviter->display_name : __( 'Unknown', 'caelis' ),
				'expires_at'     => $expires_at,
			)
		);
	}

	/**
	 * Accept invite and join workspace
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing workspace data or error.
	 */
	public function accept_invite( $request ) {
		$token   = $request->get_param( 'token' );
		$user_id = get_current_user_id();
		$user    = get_user_by( 'ID', $user_id );

		$invite = $this->get_invite_by_token( $token );
		if ( ! $invite ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or expired invitation.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Check status
		$status = get_field( '_invite_status', $invite->ID );
		if ( $status !== 'pending' ) {
			return new WP_Error( 'invalid_status', __( 'This invitation is no longer valid.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Check expiration
		$expires_at = get_field( '_invite_expires_at', $invite->ID );
		if ( strtotime( $expires_at ) < time() ) {
			update_field( '_invite_status', 'expired', $invite->ID );
			return new WP_Error( 'expired', __( 'This invitation has expired.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Verify email matches or user is admin
		$invite_email = get_field( '_invite_email', $invite->ID );
		if ( $user->user_email !== $invite_email && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'email_mismatch', __( 'Your email address does not match this invitation.', 'caelis' ), array( 'status' => 403 ) );
		}

		// Get workspace
		$workspace_id = (int) get_field( '_invite_workspace_id', $invite->ID );
		$workspace    = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_status !== 'publish' ) {
			return new WP_Error( 'workspace_not_found', __( 'The workspace no longer exists.', 'caelis' ), array( 'status' => 404 ) );
		}

		// Check if user is already a member
		if ( PRM_Workspace_Members::is_member( $workspace_id, $user_id ) ) {
			// Mark invite as accepted anyway
			update_field( '_invite_status', 'accepted', $invite->ID );
			update_field( '_invite_accepted_by', $user_id, $invite->ID );

			return new WP_Error( 'already_member', __( 'You are already a member of this workspace.', 'caelis' ), array( 'status' => 400 ) );
		}

		// Add user to workspace
		$role   = get_field( '_invite_role', $invite->ID );
		$result = PRM_Workspace_Members::add( $workspace_id, $user_id, $role );

		if ( ! $result ) {
			return new WP_Error( 'join_failed', __( 'Failed to join workspace.', 'caelis' ), array( 'status' => 500 ) );
		}

		// Mark invite as accepted
		update_field( '_invite_status', 'accepted', $invite->ID );
		update_field( '_invite_accepted_by', $user_id, $invite->ID );

		return rest_ensure_response(
			array(
				'success'        => true,
				'workspace_id'   => $workspace_id,
				'workspace_name' => $this->sanitize_text( $workspace->post_title ),
				'role'           => $role,
				'message'        => __( 'You have joined the workspace.', 'caelis' ),
			)
		);
	}

	/**
	 * Search for members across one or more workspaces
	 *
	 * Used for @mention autocomplete functionality.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing array of matching members.
	 */
	public function search_workspace_members( $request ) {
		$workspace_ids   = array_map( 'intval', explode( ',', $request->get_param( 'workspace_ids' ) ) );
		$query           = $request->get_param( 'query' );
		$current_user_id = get_current_user_id();

		// Filter to only workspaces the current user has access to
		$accessible_workspace_ids = array();
		foreach ( $workspace_ids as $workspace_id ) {
			if ( PRM_Workspace_Members::is_member( $workspace_id, $current_user_id ) || current_user_can( 'manage_options' ) ) {
				$accessible_workspace_ids[] = $workspace_id;
			}
		}

		if ( empty( $accessible_workspace_ids ) ) {
			return rest_ensure_response( array() );
		}

		// Collect all member user IDs from accessible workspaces
		$member_ids = array();
		foreach ( $accessible_workspace_ids as $workspace_id ) {
			$members = PRM_Workspace_Members::get_members( $workspace_id );
			foreach ( $members as $member ) {
				$member_ids[ $member['user_id'] ] = true;
			}
		}

		if ( empty( $member_ids ) ) {
			return rest_ensure_response( array() );
		}

		// Search users by display name or email
		$users = get_users(
			array(
				'include'        => array_keys( $member_ids ),
				'search'         => '*' . $query . '*',
				'search_columns' => array( 'display_name', 'user_email' ),
				'number'         => 10,
			)
		);

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'    => $user->ID,
				'name'  => $user->display_name,
				'email' => $user->user_email,
			);
		}

		return rest_ensure_response( $results );
	}

	/**
	 * Find invite by token
	 *
	 * @param string $token The invite token.
	 * @return WP_Post|null The invite post or null if not found.
	 */
	private function get_invite_by_token( $token ) {
		$invites = get_posts(
			array(
				'post_type'      => 'workspace_invite',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_invite_token',
						'value' => $token,
					),
				),
			)
		);

		return ! empty( $invites ) ? $invites[0] : null;
	}

	/**
	 * Send invitation email
	 *
	 * @param int $invite_id The invite post ID.
	 * @return bool True if email was sent successfully.
	 */
	private function send_invite_email( $invite_id ) {
		$invite = get_post( $invite_id );
		if ( ! $invite ) {
			return false;
		}

		$email        = get_field( '_invite_email', $invite_id );
		$token        = get_field( '_invite_token', $invite_id );
		$role         = get_field( '_invite_role', $invite_id );
		$workspace_id = (int) get_field( '_invite_workspace_id', $invite_id );

		$workspace = get_post( $workspace_id );
		if ( ! $workspace ) {
			return false;
		}

		$inviter      = get_user_by( 'ID', $invite->post_author );
		$inviter_name = $inviter ? $inviter->display_name : __( 'Someone', 'caelis' );

		$workspace_name = $this->sanitize_text( $workspace->post_title );
		$accept_url     = site_url( '/workspace-invite/' . $token );

		// Build email subject
		$subject = sprintf(
			/* translators: %s: workspace name */
			__( "You've been invited to %s on Caelis", 'caelis' ),
			$workspace_name
		);

		// Build HTML email body
		$body = $this->build_invite_email_html( $inviter_name, $workspace_name, $role, $accept_url );

		// Set content type to HTML
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * Build HTML email body for invitation
	 *
	 * @param string $inviter_name The name of the person who sent the invite.
	 * @param string $workspace_name The workspace name.
	 * @param string $role The role being offered.
	 * @param string $accept_url The URL to accept the invitation.
	 * @return string HTML email body.
	 */
	private function build_invite_email_html( $inviter_name, $workspace_name, $role, $accept_url ) {
		$role_labels = array(
			'admin'  => __( 'Admin', 'caelis' ),
			'member' => __( 'Member', 'caelis' ),
			'viewer' => __( 'Viewer', 'caelis' ),
		);
		$role_label  = $role_labels[ $role ] ?? $role;

		$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">' . __( 'Workspace Invitation', 'caelis' ) . '</h1>
    </div>
    <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px; margin-top: 0;">' . sprintf(
			/* translators: 1: inviter name, 2: workspace name */
			__( '%1$s has invited you to join <strong>%2$s</strong>.', 'caelis' ),
			esc_html( $inviter_name ),
			esc_html( $workspace_name )
		) . '</p>
        <p style="font-size: 14px; color: #666;">' . sprintf(
			/* translators: %s: role name */
			__( 'You will be added as a %s.', 'caelis' ),
			'<strong>' . esc_html( $role_label ) . '</strong>'
		) . '</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . esc_url( $accept_url ) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; padding: 14px 30px; border-radius: 6px; font-weight: 600; font-size: 16px;">' . __( 'Accept Invitation', 'caelis' ) . '</a>
        </div>
        <p style="font-size: 13px; color: #888; margin-bottom: 0;">' . __( 'This invitation expires in 7 days.', 'caelis' ) . '</p>
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
        <p style="font-size: 12px; color: #888; margin: 0;">' . sprintf(
			/* translators: %s: accept URL */
			__( 'If the button above does not work, copy and paste this link into your browser: %s', 'caelis' ),
			'<br><a href="' . esc_url( $accept_url ) . '" style="color: #667eea; word-break: break-all;">' . esc_url( $accept_url ) . '</a>'
		) . '</p>
    </div>
</body>
</html>';

		return $html;
	}
}
