<?php
/**
 * User Roles for Rondo
 *
 * Registers custom user roles for Rondo users with minimal permissions.
 * Base roles are hardcoded; custom roles are admin-created and stored in a wp_option.
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserRoles {

	const ROLE_NAME                     = 'rondo_user';
	const ROLE_DISPLAY_NAME             = 'Rondo User';
	const FAIRPLAY_CAPABILITY           = 'fairplay';
	const VOG_CAPABILITY                = 'vog';
	const FINANCIEEL_CAPABILITY         = 'financieel';
	const TOEGANG_CAPABILITY            = 'toegangscontrole';
	const CLOTHING_CAPABILITY           = 'manage_clothing';
	const LEDENADMINISTRATIE_CAPABILITY = 'ledenadministratie';
	const VRIJWILLIGERS_CAPABILITY      = 'vrijwilligers';
	const IVA_APPROVE_CAPABILITY        = 'rondo_iva_approve';

	/**
	 * WordPress option key for admin-created custom roles.
	 * Stored as [ slug => label ] associative array.
	 */
	const CUSTOM_ROLES_OPTION = 'rondo_custom_roles';

	/**
	 * Built-in Rondo roles: slug => [ display_name, extra capabilities ]
	 * Each role gets the base rondo_user capabilities plus the listed extras.
	 * Custom roles are stored separately in the rondo_custom_roles wp_option.
	 */
	const BASE_ROLES = [
		'rondo_user'               => [ 'Rondo User', [] ],
		'rondo_fairplay'           => [ 'Rondo FairPlay', [ 'fairplay' ] ],
		'rondo_vog'                => [ 'Rondo VOG', [ 'vog' ] ],
		'rondo_financieel'         => [ 'Rondo Financieel', [ 'financieel' ] ],
		'rondo_toegangscontrole'   => [ 'Rondo Toegangscontrole', [ 'toegangscontrole' ] ],
		'rondo_clothing_manager'   => [ 'Rondo Kledingbeheer', [ 'manage_clothing' ] ],
		'rondo_ledenadministratie' => [ 'Rondo Ledenadministratie', [ 'ledenadministratie' ] ],
		'rondo_vrijwilligers'      => [ 'Rondo Vrijwilligers', [ 'vrijwilligers' ] ],
		'rondo_iva_approver'       => [ 'Rondo IVA Goedkeurder (Bestuurslid Kantine)', [ 'rondo_iva_approve', 'vrijwilligers' ] ],
		'rondo_pool_schoonmaak'    => [ 'Rondo Schoonmaakpoule', [] ],
		'rondo_pool_activiteiten'  => [ 'Rondo Activiteitenpoule', [] ],
		'rondo_pool_werkploeg'     => [ 'Rondo Werkploeg terreinonderhoud', [] ],
		'rondo_bestuur'            => [ 'Rondo Bestuur', [ 'fairplay', 'vog', 'financieel', 'toegangscontrole', 'manage_clothing', 'ledenadministratie', 'vrijwilligers', 'rondo_iva_approve' ] ],
	];

	public function __construct() {
		// Register role on theme activation
		add_action( 'after_switch_theme', [ $this, 'register_role' ] );

		// Remove role on theme deactivation
		add_action( 'switch_theme', [ $this, 'remove_role' ] );

		// Ensure role exists on init (in case theme was already active)
		add_action( 'init', [ $this, 'ensure_role_exists' ], 20 );

		// Delete user's posts when user is deleted
		add_action( 'delete_user', [ $this, 'delete_user_posts' ], 10, 1 );
	}

	/**
	 * Get all Rondo roles: base + custom.
	 *
	 * Returns the same shape as BASE_ROLES: slug => [ display_name, extra_capabilities ].
	 * Custom roles always have empty extra capabilities (managed via capability matrix).
	 *
	 * @return array<string, array{0: string, 1: string[]}> All roles.
	 */
	public static function get_all_roles(): array {
		$roles = self::BASE_ROLES;

		foreach ( self::get_custom_roles() as $slug => $label ) {
			$roles[ $slug ] = [ $label, [] ];
		}

		return $roles;
	}

	/**
	 * Get admin-created custom roles from wp_option.
	 *
	 * @return array<string, string> Slug => label pairs. Empty array if none.
	 */
	public static function get_custom_roles(): array {
		$custom = get_option( self::CUSTOM_ROLES_OPTION, [] );
		return is_array( $custom ) ? $custom : [];
	}

	/**
	 * Create a new custom role.
	 *
	 * Generates a slug from the label with rondo_ prefix, registers the WP role
	 * with base capabilities, and stores it in the custom roles option.
	 *
	 * @param string $label Human-readable role name (e.g. "Coördinator Pupillen").
	 * @return string|\WP_Error The role slug on success, or WP_Error on failure.
	 */
	public static function add_custom_role( string $label ): string|\WP_Error {
		$label = trim( $label );

		if ( empty( $label ) ) {
			return new \WP_Error(
				'empty_label',
				'Role label cannot be empty.',
				[ 'status' => 400 ]
			);
		}

		// Generate slug: rondo_ prefix + sanitized label.
		$slug = 'rondo_' . sanitize_title( $label );

		// Ensure no conflict with existing WP roles (base + custom + core).
		if ( get_role( $slug ) ) {
			return new \WP_Error(
				'role_exists',
				sprintf( 'A role with slug "%s" already exists.', $slug ),
				[ 'status' => 409 ]
			);
		}

		// Base capabilities — same as rondo_user.
		$capabilities = [
			'read'                   => true,
			'edit_posts'             => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'edit_published_posts'   => true,
			'delete_published_posts' => true,
			'upload_files'           => true,
		];

		$result = add_role( $slug, $label, $capabilities );

		if ( ! $result ) {
			return new \WP_Error(
				'role_creation_failed',
				'WordPress add_role() failed.',
				[ 'status' => 500 ]
			);
		}

		// Persist in custom roles option.
		$custom          = self::get_custom_roles();
		$custom[ $slug ] = $label;
		update_option( self::CUSTOM_ROLES_OPTION, $custom );

		return $slug;
	}

	/**
	 * Delete a custom role.
	 *
	 * Removes the WP role, strips it from all users who have it, and removes
	 * it from the custom roles option. Base roles cannot be deleted.
	 *
	 * @param string $slug Role slug to remove.
	 * @return true|\WP_Error True on success, or WP_Error on failure.
	 */
	public static function remove_custom_role( string $slug ): true|\WP_Error {
		// Prevent deleting base roles.
		if ( isset( self::BASE_ROLES[ $slug ] ) || $slug === 'administrator' ) {
			return new \WP_Error(
				'base_role_protected',
				sprintf( 'Cannot delete built-in role "%s".', $slug ),
				[ 'status' => 403 ]
			);
		}

		$custom = self::get_custom_roles();

		if ( ! isset( $custom[ $slug ] ) ) {
			return new \WP_Error(
				'role_not_found',
				sprintf( 'Custom role "%s" not found.', $slug ),
				[ 'status' => 404 ]
			);
		}

		// Remove the role from all users who have it.
		$users = get_users( [ 'role' => $slug ] );
		foreach ( $users as $user ) {
			$user->remove_role( $slug );
		}

		// Remove the WP role definition.
		remove_role( $slug );

		// Remove from stored custom roles.
		unset( $custom[ $slug ] );
		update_option( self::CUSTOM_ROLES_OPTION, $custom );

		return true;
	}

	/**
	 * Ensure all roles exist (for themes already active)
	 */
	public function ensure_role_exists() {
		foreach ( self::get_all_roles() as $slug => $_ ) {
			if ( ! get_role( $slug ) ) {
				$this->register_role();
				return;
			}
		}
	}

	/**
	 * Register all Rondo roles (base + custom)
	 */
	public function register_role() {
		$base_capabilities = $this->get_role_capabilities();

		foreach ( self::get_all_roles() as $slug => [ $display_name, $extra_caps ] ) {
			$capabilities = $base_capabilities;
			foreach ( $extra_caps as $cap ) {
				$capabilities[ $cap ] = true;
			}
			add_role( $slug, $display_name, $capabilities );
		}

		// Add app-specific capabilities to administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( self::FAIRPLAY_CAPABILITY );
			$admin_role->add_cap( self::VOG_CAPABILITY );
			$admin_role->add_cap( self::FINANCIEEL_CAPABILITY );
			$admin_role->add_cap( self::TOEGANG_CAPABILITY );
			$admin_role->add_cap( self::CLOTHING_CAPABILITY );
			$admin_role->add_cap( self::LEDENADMINISTRATIE_CAPABILITY );
			$admin_role->add_cap( self::VRIJWILLIGERS_CAPABILITY );
			$admin_role->add_cap( self::IVA_APPROVE_CAPABILITY );
		}
	}

	/**
	 * Remove all Rondo roles (base + custom) on theme deactivation
	 */
	public function remove_role() {
		// Remove app-specific capabilities from administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( self::FAIRPLAY_CAPABILITY );
			$admin_role->remove_cap( self::VOG_CAPABILITY );
			$admin_role->remove_cap( self::FINANCIEEL_CAPABILITY );
			$admin_role->remove_cap( self::TOEGANG_CAPABILITY );
			$admin_role->remove_cap( self::CLOTHING_CAPABILITY );
			$admin_role->remove_cap( self::LEDENADMINISTRATIE_CAPABILITY );
			$admin_role->remove_cap( self::VRIJWILLIGERS_CAPABILITY );
			$admin_role->remove_cap( self::IVA_APPROVE_CAPABILITY );
		}

		foreach ( self::get_all_roles() as $slug => $_ ) {
			// Reassign users to subscriber before removing role
			$users = get_users( [ 'role' => $slug ] );
			foreach ( $users as $user ) {
				$user->set_role( 'subscriber' );
			}
			remove_role( $slug );
		}
	}

	/**
	 * Get capabilities for Rondo User role
	 *
	 * Minimal permissions needed to:
	 * - Create, edit, and delete their own people and teams
	 * - Upload files (for photos and logos)
	 * - Read content (required for WordPress)
	 */
	private function get_role_capabilities() {
		return [
			// Basic WordPress capabilities
			'read'                   => true,

			// Post capabilities (used by person, team, and other post types)
			'edit_posts'             => true,                    // Can create and edit their own posts
			'publish_posts'          => true,                 // Can publish their own posts
			'delete_posts'           => true,                  // Can delete their own posts
			'edit_published_posts'   => true,          // Can edit their own published posts
			'delete_published_posts' => true,        // Can delete their own published posts

			// Media capabilities
			'upload_files'           => true,                  // Can upload files (photos, logos)

			// No other capabilities - users can't:
			// - Edit other users' posts
			// - Manage other users
			// - Access WordPress admin settings
			// - Install plugins or themes
			// - Edit themes or plugins
		];
	}


	/**
	 * Get all Rondo role slugs (base + custom)
	 *
	 * @return string[] Array of role slugs.
	 */
	public static function get_role_slugs() {
		return array_keys( self::get_all_roles() );
	}

	/**
	 * Check if a user has any Rondo role (base or custom)
	 *
	 * @param \WP_User $user User to check.
	 * @return bool True if user has any Rondo role.
	 */
	public static function has_rondo_role( $user ) {
		return ! empty( array_intersect( self::get_role_slugs(), $user->roles ) );
	}

	/**
	 * Check if a user ID is valid
	 *
	 * Kept for backward compatibility with existing code.
	 * Simply returns true if user ID exists.
	 *
	 * @param int $user_id User ID to check.
	 * @return bool True if user ID is valid.
	 */
	public static function is_user_approved( $user_id ) {
		return (bool) $user_id;
	}


	/**
	 * Delete all posts belonging to a user when user is deleted
	 * This is called by WordPress before the user is actually deleted
	 */
	public function delete_user_posts( $user_id ) {
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
				wp_delete_post( $post->ID, true ); // Force delete (bypass trash)
			}
		}
	}
}
