<?php
/**
 * Workspaces REST API Endpoints
 *
 * Handles REST API endpoints for workspace management and member operations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_REST_Workspaces extends PRM_REST_Base {

    /**
     * Constructor
     *
     * Register routes for workspace endpoints.
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register custom REST routes for workspace domain
     */
    public function register_routes() {
        // GET /prm/v1/workspaces - List user's workspaces
        register_rest_route('prm/v1', '/workspaces', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_workspaces'],
            'permission_callback' => [$this, 'check_user_approved'],
        ]);

        // POST /prm/v1/workspaces - Create workspace
        register_rest_route('prm/v1', '/workspaces', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_workspace'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'title' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'description' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        // GET /prm/v1/workspaces/(?P<id>\d+) - Get workspace details
        register_rest_route('prm/v1', '/workspaces/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_workspace'],
            'permission_callback' => [$this, 'check_workspace_access'],
            'args'                => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // PUT /prm/v1/workspaces/(?P<id>\d+) - Update workspace
        register_rest_route('prm/v1', '/workspaces/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_workspace'],
            'permission_callback' => [$this, 'check_workspace_admin'],
            'args'                => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'title' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'description' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        // DELETE /prm/v1/workspaces/(?P<id>\d+) - Delete workspace
        register_rest_route('prm/v1', '/workspaces/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_workspace'],
            'permission_callback' => [$this, 'check_workspace_owner'],
            'args'                => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // POST /prm/v1/workspaces/(?P<id>\d+)/members - Add member
        register_rest_route('prm/v1', '/workspaces/(?P<id>\d+)/members', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'add_member'],
            'permission_callback' => [$this, 'check_workspace_admin'],
            'args'                => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'user_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'role' => [
                    'required'          => false,
                    'default'           => 'member',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return in_array($param, ['admin', 'member', 'viewer'], true);
                    },
                ],
            ],
        ]);

        // DELETE /prm/v1/workspaces/(?P<id>\d+)/members/(?P<user_id>\d+) - Remove member
        register_rest_route('prm/v1', '/workspaces/(?P<id>\d+)/members/(?P<user_id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'remove_member'],
            'permission_callback' => [$this, 'check_workspace_admin'],
            'args'                => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'user_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // PUT /prm/v1/workspaces/(?P<id>\d+)/members/(?P<user_id>\d+) - Update member role
        register_rest_route('prm/v1', '/workspaces/(?P<id>\d+)/members/(?P<user_id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_member_role'],
            'permission_callback' => [$this, 'check_workspace_admin'],
            'args'                => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'user_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'role' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return in_array($param, ['admin', 'member', 'viewer'], true);
                    },
                ],
            ],
        ]);
    }

    /**
     * Check if user has access to workspace (is a member)
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if user can access workspace, false otherwise.
     */
    public function check_workspace_access($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        if (!$this->check_user_approved()) {
            return false;
        }

        $workspace_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();

        // Verify workspace exists
        $workspace = get_post($workspace_id);
        if (!$workspace || $workspace->post_type !== 'workspace') {
            return false;
        }

        // Admins have full access
        if (current_user_can('manage_options')) {
            return true;
        }

        return PRM_Workspace_Members::is_member($workspace_id, $user_id);
    }

    /**
     * Check if user is admin of workspace
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if user is workspace admin, false otherwise.
     */
    public function check_workspace_admin($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        if (!$this->check_user_approved()) {
            return false;
        }

        $workspace_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();

        // Verify workspace exists
        $workspace = get_post($workspace_id);
        if (!$workspace || $workspace->post_type !== 'workspace') {
            return false;
        }

        // WordPress admins have full access
        if (current_user_can('manage_options')) {
            return true;
        }

        return PRM_Workspace_Members::is_admin($workspace_id, $user_id);
    }

    /**
     * Check if user is owner of workspace (for delete operations)
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if user is workspace owner, false otherwise.
     */
    public function check_workspace_owner($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        if (!$this->check_user_approved()) {
            return false;
        }

        $workspace_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();

        // Verify workspace exists
        $workspace = get_post($workspace_id);
        if (!$workspace || $workspace->post_type !== 'workspace') {
            return false;
        }

        // WordPress admins can delete any workspace
        if (current_user_can('manage_options')) {
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
    public function get_workspaces($request) {
        $user_id = get_current_user_id();
        $user_workspaces = PRM_Workspace_Members::get_user_workspaces($user_id);

        $workspaces = [];
        foreach ($user_workspaces as $membership) {
            $workspace = get_post($membership['workspace_id']);
            if (!$workspace || $workspace->post_status !== 'publish') {
                continue;
            }

            $members = PRM_Workspace_Members::get_members($membership['workspace_id']);

            $workspaces[] = [
                'id'           => $workspace->ID,
                'title'        => $this->sanitize_text($workspace->post_title),
                'description'  => $this->sanitize_text($workspace->post_content),
                'role'         => $membership['role'],
                'member_count' => count($members),
                'joined_at'    => $membership['joined_at'],
                'is_owner'     => (int) $workspace->post_author === $user_id,
            ];
        }

        return rest_ensure_response($workspaces);
    }

    /**
     * Get workspace details including members
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing workspace data or error.
     */
    public function get_workspace($request) {
        $workspace_id = (int) $request->get_param('id');
        $workspace = get_post($workspace_id);
        $user_id = get_current_user_id();

        if (!$workspace || $workspace->post_type !== 'workspace') {
            return new WP_Error('workspace_not_found', __('Workspace not found.', 'personal-crm'), ['status' => 404]);
        }

        // Get members with user info
        $members_raw = PRM_Workspace_Members::get_members($workspace_id);
        $members = [];

        foreach ($members_raw as $member) {
            $user = get_user_by('ID', $member['user_id']);
            if (!$user) {
                continue;
            }

            $members[] = [
                'user_id'     => $member['user_id'],
                'display_name' => $user->display_name,
                'email'       => $user->user_email,
                'avatar_url'  => get_avatar_url($member['user_id'], ['size' => 96]),
                'role'        => $member['role'],
                'joined_at'   => $member['joined_at'],
                'is_owner'    => (int) $workspace->post_author === $member['user_id'],
            ];
        }

        $current_role = PRM_Workspace_Members::get_user_role($workspace_id, $user_id);

        $response = [
            'id'           => $workspace->ID,
            'title'        => $this->sanitize_text($workspace->post_title),
            'description'  => $this->sanitize_text($workspace->post_content),
            'owner_id'     => (int) $workspace->post_author,
            'member_count' => count($members),
            'members'      => $members,
            'current_user' => [
                'role'     => $current_role,
                'is_owner' => (int) $workspace->post_author === $user_id,
            ],
            'created_at'   => $workspace->post_date_gmt,
            'modified_at'  => $workspace->post_modified_gmt,
        ];

        return rest_ensure_response($response);
    }

    /**
     * Create a new workspace
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing new workspace data or error.
     */
    public function create_workspace($request) {
        $user_id = get_current_user_id();
        $title = $request->get_param('title');
        $description = $request->get_param('description') ?: '';

        $post_data = [
            'post_type'    => 'workspace',
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'publish',
            'post_author'  => $user_id,
        ];

        $workspace_id = wp_insert_post($post_data, true);

        if (is_wp_error($workspace_id)) {
            return new WP_Error('create_failed', $workspace_id->get_error_message(), ['status' => 500]);
        }

        // The save_post_workspace hook in PRM_Workspace_Members auto-adds author as admin

        $workspace = get_post($workspace_id);

        return rest_ensure_response([
            'id'           => $workspace_id,
            'title'        => $this->sanitize_text($workspace->post_title),
            'description'  => $this->sanitize_text($workspace->post_content),
            'role'         => 'admin',
            'member_count' => 1,
            'is_owner'     => true,
            'created_at'   => $workspace->post_date_gmt,
        ]);
    }

    /**
     * Update workspace
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing updated workspace data or error.
     */
    public function update_workspace($request) {
        $workspace_id = (int) $request->get_param('id');
        $workspace = get_post($workspace_id);

        if (!$workspace || $workspace->post_type !== 'workspace') {
            return new WP_Error('workspace_not_found', __('Workspace not found.', 'personal-crm'), ['status' => 404]);
        }

        $post_data = [
            'ID' => $workspace_id,
        ];

        $title = $request->get_param('title');
        $description = $request->get_param('description');

        if ($title !== null) {
            $post_data['post_title'] = $title;
        }

        if ($description !== null) {
            $post_data['post_content'] = $description;
        }

        $result = wp_update_post($post_data, true);

        if (is_wp_error($result)) {
            return new WP_Error('update_failed', $result->get_error_message(), ['status' => 500]);
        }

        $workspace = get_post($workspace_id);
        $user_id = get_current_user_id();
        $members = PRM_Workspace_Members::get_members($workspace_id);
        $current_role = PRM_Workspace_Members::get_user_role($workspace_id, $user_id);

        return rest_ensure_response([
            'id'           => $workspace_id,
            'title'        => $this->sanitize_text($workspace->post_title),
            'description'  => $this->sanitize_text($workspace->post_content),
            'role'         => $current_role,
            'member_count' => count($members),
            'is_owner'     => (int) $workspace->post_author === $user_id,
            'modified_at'  => $workspace->post_modified_gmt,
        ]);
    }

    /**
     * Delete workspace
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with success status or error.
     */
    public function delete_workspace($request) {
        $workspace_id = (int) $request->get_param('id');
        $workspace = get_post($workspace_id);

        if (!$workspace || $workspace->post_type !== 'workspace') {
            return new WP_Error('workspace_not_found', __('Workspace not found.', 'personal-crm'), ['status' => 404]);
        }

        $result = wp_trash_post($workspace_id);

        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete workspace.', 'personal-crm'), ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'id'      => $workspace_id,
            'message' => __('Workspace moved to trash.', 'personal-crm'),
        ]);
    }

    /**
     * Add member to workspace
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing member data or error.
     */
    public function add_member($request) {
        $workspace_id = (int) $request->get_param('id');
        $user_id = (int) $request->get_param('user_id');
        $role = $request->get_param('role') ?: 'member';

        // Validate workspace exists
        $workspace = get_post($workspace_id);
        if (!$workspace || $workspace->post_type !== 'workspace') {
            return new WP_Error('workspace_not_found', __('Workspace not found.', 'personal-crm'), ['status' => 404]);
        }

        // Validate user exists
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new WP_Error('user_not_found', __('User not found.', 'personal-crm'), ['status' => 404]);
        }

        // Check if user is already a member
        if (PRM_Workspace_Members::is_member($workspace_id, $user_id)) {
            // Update their role instead
            $result = PRM_Workspace_Members::update_role($workspace_id, $user_id, $role);
            if (!$result) {
                return new WP_Error('update_failed', __('Failed to update member role.', 'personal-crm'), ['status' => 500]);
            }
        } else {
            // Add new member
            $result = PRM_Workspace_Members::add($workspace_id, $user_id, $role);
            if (!$result) {
                return new WP_Error('add_failed', __('Failed to add member.', 'personal-crm'), ['status' => 500]);
            }
        }

        return rest_ensure_response([
            'success'      => true,
            'user_id'      => $user_id,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'avatar_url'   => get_avatar_url($user_id, ['size' => 96]),
            'role'         => $role,
            'is_owner'     => (int) $workspace->post_author === $user_id,
        ]);
    }

    /**
     * Remove member from workspace
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with success status or error.
     */
    public function remove_member($request) {
        $workspace_id = (int) $request->get_param('id');
        $user_id = (int) $request->get_param('user_id');

        // Validate workspace exists
        $workspace = get_post($workspace_id);
        if (!$workspace || $workspace->post_type !== 'workspace') {
            return new WP_Error('workspace_not_found', __('Workspace not found.', 'personal-crm'), ['status' => 404]);
        }

        // Check if user is the owner (cannot be removed)
        if ((int) $workspace->post_author === $user_id) {
            return new WP_Error('cannot_remove_owner', __('Cannot remove the workspace owner.', 'personal-crm'), ['status' => 400]);
        }

        // Check if user is a member
        if (!PRM_Workspace_Members::is_member($workspace_id, $user_id)) {
            return new WP_Error('not_a_member', __('User is not a member of this workspace.', 'personal-crm'), ['status' => 400]);
        }

        $result = PRM_Workspace_Members::remove($workspace_id, $user_id);

        if (!$result) {
            return new WP_Error('remove_failed', __('Failed to remove member.', 'personal-crm'), ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'user_id' => $user_id,
            'message' => __('Member removed from workspace.', 'personal-crm'),
        ]);
    }

    /**
     * Update member role in workspace
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing updated member data or error.
     */
    public function update_member_role($request) {
        $workspace_id = (int) $request->get_param('id');
        $user_id = (int) $request->get_param('user_id');
        $role = $request->get_param('role');

        // Validate workspace exists
        $workspace = get_post($workspace_id);
        if (!$workspace || $workspace->post_type !== 'workspace') {
            return new WP_Error('workspace_not_found', __('Workspace not found.', 'personal-crm'), ['status' => 404]);
        }

        // Validate user exists
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new WP_Error('user_not_found', __('User not found.', 'personal-crm'), ['status' => 404]);
        }

        // Check if user is a member
        if (!PRM_Workspace_Members::is_member($workspace_id, $user_id)) {
            return new WP_Error('not_a_member', __('User is not a member of this workspace.', 'personal-crm'), ['status' => 400]);
        }

        $result = PRM_Workspace_Members::update_role($workspace_id, $user_id, $role);

        if (!$result) {
            return new WP_Error('update_failed', __('Failed to update member role.', 'personal-crm'), ['status' => 500]);
        }

        return rest_ensure_response([
            'success'      => true,
            'user_id'      => $user_id,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'avatar_url'   => get_avatar_url($user_id, ['size' => 96]),
            'role'         => $role,
            'is_owner'     => (int) $workspace->post_author === $user_id,
        ]);
    }
}
