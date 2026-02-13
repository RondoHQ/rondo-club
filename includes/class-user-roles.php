<?php
/**
 * User Roles for Rondo
 *
 * Registers custom user role for Rondo users with minimal permissions
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserRoles {

	const ROLE_NAME              = 'rondo_user';
	const ROLE_DISPLAY_NAME      = 'Rondo User';
	const FAIRPLAY_CAPABILITY    = 'fairplay';
	const VOG_CAPABILITY         = 'vog';
	const FINANCIEEL_CAPABILITY  = 'financieel';

	/**
	 * All Rondo roles: slug => [ display_name, extra capabilities ]
	 * Each role gets the base rondo_user capabilities plus the listed extras.
	 */
	const ROLES = [
		'rondo_user'     => [ 'Rondo User', [] ],
		'rondo_fairplay' => [ 'Rondo FairPlay', [ 'fairplay' ] ],
		'rondo_vog'      => [ 'Rondo VOG', [ 'vog' ] ],
		'rondo_bestuur'  => [ 'Rondo Bestuur', [ 'fairplay', 'vog', 'financieel' ] ],
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
	 * Ensure all roles exist (for themes already active)
	 */
	public function ensure_role_exists() {
		foreach ( self::ROLES as $slug => $_ ) {
			if ( ! get_role( $slug ) ) {
				$this->register_role();
				return;
			}
		}
	}

	/**
	 * Register all Rondo roles
	 */
	public function register_role() {
		$base_capabilities = $this->get_role_capabilities();

		foreach ( self::ROLES as $slug => [ $display_name, $extra_caps ] ) {
			$capabilities = $base_capabilities;
			foreach ( $extra_caps as $cap ) {
				$capabilities[ $cap ] = true;
			}
			add_role( $slug, $display_name, $capabilities );
		}

		// Add fairplay, VOG, and financieel capabilities to administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( self::FAIRPLAY_CAPABILITY );
			$admin_role->add_cap( self::VOG_CAPABILITY );
			$admin_role->add_cap( self::FINANCIEEL_CAPABILITY );
		}
	}

	/**
	 * Remove all Rondo roles
	 */
	public function remove_role() {
		// Remove fairplay, VOG, and financieel capabilities from administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( self::FAIRPLAY_CAPABILITY );
			$admin_role->remove_cap( self::VOG_CAPABILITY );
			$admin_role->remove_cap( self::FINANCIEEL_CAPABILITY );
		}

		foreach ( self::ROLES as $slug => $_ ) {
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
	 * Get all Rondo role slugs
	 *
	 * @return string[] Array of role slugs.
	 */
	public static function get_role_slugs() {
		return array_keys( self::ROLES );
	}

	/**
	 * Check if a user has any Rondo role
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
