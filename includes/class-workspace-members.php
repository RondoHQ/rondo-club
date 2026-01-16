<?php
/**
 * Workspace Members Management
 *
 * Manages workspace memberships stored in user meta.
 * Enables users to belong to multiple workspaces with different roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Workspace_Members {

	/** Role constants */
	const ROLE_ADMIN  = 'admin';
	const ROLE_MEMBER = 'member';
	const ROLE_VIEWER = 'viewer';

	/** User meta key for storing workspace memberships */
	const META_KEY = '_workspace_memberships';

	/**
	 * Valid roles for workspace membership
	 *
	 * @var array
	 */
	private static $valid_roles = [
		self::ROLE_ADMIN,
		self::ROLE_MEMBER,
		self::ROLE_VIEWER,
	];

	/**
	 * Constructor - register hooks
	 */
	public function __construct() {
		add_action( 'save_post_workspace', [ $this, 'add_owner_as_admin' ], 10, 3 );
	}

	/**
	 * When workspace is created, add author as admin member
	 *
	 * @param int     $post_id The workspace post ID.
	 * @param WP_Post $post    The workspace post object.
	 * @param bool    $update  Whether this is an update or new post.
	 */
	public function add_owner_as_admin( $post_id, $post, $update ) {
		// Only on create (not update)
		if ( $update ) {
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip auto-drafts
		if ( $post->post_status === 'auto-draft' ) {
			return;
		}

		// Add author as admin
		self::add( $post_id, $post->post_author, self::ROLE_ADMIN );
	}

	/**
	 * Add user to workspace
	 *
	 * @param int    $workspace_id The workspace post ID.
	 * @param int    $user_id      The user ID.
	 * @param string $role         The role (admin|member|viewer). Default 'member'.
	 * @return bool True on success, false on failure.
	 */
	public static function add( $workspace_id, $user_id, $role = self::ROLE_MEMBER ) {
		// Validate role
		if ( ! in_array( $role, self::$valid_roles, true ) ) {
			return false;
		}

		// Validate workspace exists and is correct post type
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' ) {
			return false;
		}

		// Validate user exists
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Get current memberships
		$memberships = self::get_user_memberships_raw( $user_id );

		// Check if user is already a member
		foreach ( $memberships as $index => $membership ) {
			if ( (int) $membership['workspace_id'] === (int) $workspace_id ) {
				// Already a member, update role instead
				$memberships[ $index ]['role'] = $role;
				return update_user_meta( $user_id, self::META_KEY, $memberships );
			}
		}

		// Add new membership
		$memberships[] = [
			'workspace_id' => (int) $workspace_id,
			'role'         => $role,
			'joined_at'    => gmdate( 'c' ), // ISO 8601 format
		];

		return (bool) update_user_meta( $user_id, self::META_KEY, $memberships );
	}

	/**
	 * Remove user from workspace
	 *
	 * @param int $workspace_id The workspace post ID.
	 * @param int $user_id      The user ID.
	 * @return bool True on success, false on failure.
	 */
	public static function remove( $workspace_id, $user_id ) {
		// Don't allow removing the workspace owner
		$workspace = get_post( $workspace_id );
		if ( $workspace && (int) $workspace->post_author === (int) $user_id ) {
			return false; // Owner cannot be removed
		}

		// Get current memberships
		$memberships = self::get_user_memberships_raw( $user_id );

		// Find and remove the membership
		$found = false;
		foreach ( $memberships as $index => $membership ) {
			if ( (int) $membership['workspace_id'] === (int) $workspace_id ) {
				unset( $memberships[ $index ] );
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return false; // User wasn't a member
		}

		// Re-index array
		$memberships = array_values( $memberships );

		return (bool) update_user_meta( $user_id, self::META_KEY, $memberships );
	}

	/**
	 * Update user's role in workspace
	 *
	 * @param int    $workspace_id The workspace post ID.
	 * @param int    $user_id      The user ID.
	 * @param string $new_role     The new role (admin|member|viewer).
	 * @return bool True on success, false on failure.
	 */
	public static function update_role( $workspace_id, $user_id, $new_role ) {
		// Validate role
		if ( ! in_array( $new_role, self::$valid_roles, true ) ) {
			return false;
		}

		// Get current memberships
		$memberships = self::get_user_memberships_raw( $user_id );

		// Find and update the membership
		foreach ( $memberships as $index => $membership ) {
			if ( (int) $membership['workspace_id'] === (int) $workspace_id ) {
				$memberships[ $index ]['role'] = $new_role;
				return (bool) update_user_meta( $user_id, self::META_KEY, $memberships );
			}
		}

		return false; // User wasn't a member
	}

	/**
	 * Get all members of a workspace
	 *
	 * @param int $workspace_id The workspace post ID.
	 * @return array Array of member data [{user_id, role, joined_at}, ...].
	 */
	public static function get_members( $workspace_id ) {
		$members = [];

		// Query all users who have this workspace in their memberships
		// Note: This is a somewhat expensive query but necessary without a separate table
		$users = get_users(
			[
				'meta_key'     => self::META_KEY,
				'meta_compare' => 'EXISTS',
			]
		);

		foreach ( $users as $user ) {
			$memberships = self::get_user_memberships_raw( $user->ID );

			foreach ( $memberships as $membership ) {
				if ( (int) $membership['workspace_id'] === (int) $workspace_id ) {
					$members[] = [
						'user_id'   => $user->ID,
						'role'      => $membership['role'],
						'joined_at' => $membership['joined_at'],
					];
					break;
				}
			}
		}

		return $members;
	}

	/**
	 * Get all workspaces a user belongs to
	 *
	 * @param int $user_id The user ID.
	 * @return array Array of workspace data [{workspace_id, role, joined_at}, ...].
	 */
	public static function get_user_workspaces( $user_id ) {
		$memberships = self::get_user_memberships_raw( $user_id );
		$workspaces  = [];

		foreach ( $memberships as $membership ) {
			// Verify workspace still exists
			$workspace = get_post( $membership['workspace_id'] );
			if ( $workspace && $workspace->post_type === 'workspace' && $workspace->post_status === 'publish' ) {
				$workspaces[] = [
					'workspace_id' => $membership['workspace_id'],
					'role'         => $membership['role'],
					'joined_at'    => $membership['joined_at'],
				];
			}
		}

		return $workspaces;
	}

	/**
	 * Get user's role in a workspace
	 *
	 * @param int $workspace_id The workspace post ID.
	 * @param int $user_id      The user ID.
	 * @return string|false Role string or false if not a member.
	 */
	public static function get_user_role( $workspace_id, $user_id ) {
		$memberships = self::get_user_memberships_raw( $user_id );

		foreach ( $memberships as $membership ) {
			if ( (int) $membership['workspace_id'] === (int) $workspace_id ) {
				return $membership['role'];
			}
		}

		return false;
	}

	/**
	 * Check if user is member of workspace (any role)
	 *
	 * @param int $workspace_id The workspace post ID.
	 * @param int $user_id      The user ID.
	 * @return bool True if member, false otherwise.
	 */
	public static function is_member( $workspace_id, $user_id ) {
		return self::get_user_role( $workspace_id, $user_id ) !== false;
	}

	/**
	 * Get workspace IDs for a user (for query optimization)
	 *
	 * @param int $user_id The user ID.
	 * @return array Array of workspace IDs [workspace_id, ...].
	 */
	public static function get_user_workspace_ids( $user_id ) {
		$memberships = self::get_user_memberships_raw( $user_id );
		$ids         = [];

		foreach ( $memberships as $membership ) {
			$ids[] = (int) $membership['workspace_id'];
		}

		return $ids;
	}

	/**
	 * Check if user has admin role in workspace
	 *
	 * @param int $workspace_id The workspace post ID.
	 * @param int $user_id      The user ID.
	 * @return bool True if admin, false otherwise.
	 */
	public static function is_admin( $workspace_id, $user_id ) {
		return self::get_user_role( $workspace_id, $user_id ) === self::ROLE_ADMIN;
	}

	/**
	 * Check if user can edit in workspace (admin or member)
	 *
	 * @param int $workspace_id The workspace post ID.
	 * @param int $user_id      The user ID.
	 * @return bool True if can edit, false otherwise.
	 */
	public static function can_edit( $workspace_id, $user_id ) {
		$role = self::get_user_role( $workspace_id, $user_id );
		return in_array( $role, [ self::ROLE_ADMIN, self::ROLE_MEMBER ], true );
	}

	/**
	 * Get raw memberships from user meta
	 *
	 * @param int $user_id The user ID.
	 * @return array Array of membership data or empty array.
	 */
	private static function get_user_memberships_raw( $user_id ) {
		$memberships = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $memberships ) ) {
			return [];
		}

		return $memberships;
	}
}
