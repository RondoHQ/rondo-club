<?php
/**
 * User Roles for Stadion
 *
 * Registers custom user role for Stadion users with minimal permissions
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserRoles {

	const ROLE_NAME              = 'stadion_user';
	const ROLE_DISPLAY_NAME      = 'Stadion User';
	const APPROVAL_META_KEY      = 'stadion_user_approved';
	const FAIRPLAY_CAPABILITY    = 'fairplay';
	const VOG_CAPABILITY         = 'vog';
	const FINANCIEEL_CAPABILITY  = 'financieel';

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
	 * Ensure the role exists (for themes already active)
	 */
	public function ensure_role_exists() {
		if ( ! get_role( self::ROLE_NAME ) ) {
			$this->register_role();
		}
	}

	/**
	 * Register the Stadion User role
	 */
	public function register_role() {
		// Get the role capabilities
		$capabilities = $this->get_role_capabilities();

		// Add the role
		add_role(
			self::ROLE_NAME,
			self::ROLE_DISPLAY_NAME,
			$capabilities
		);

		// Add fairplay, VOG, and financieel capabilities to administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( self::FAIRPLAY_CAPABILITY );
			$admin_role->add_cap( self::VOG_CAPABILITY );
			$admin_role->add_cap( self::FINANCIEEL_CAPABILITY );
		}
	}

	/**
	 * Remove the Stadion User role
	 */
	public function remove_role() {
		// Remove fairplay, VOG, and financieel capabilities from administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( self::FAIRPLAY_CAPABILITY );
			$admin_role->remove_cap( self::VOG_CAPABILITY );
			$admin_role->remove_cap( self::FINANCIEEL_CAPABILITY );
		}

		// Get all users with this role
		$users = get_users( [ 'role' => self::ROLE_NAME ] );

		// Reassign to subscriber role before removing
		foreach ( $users as $user ) {
			$user->set_role( 'subscriber' );
		}

		// Remove the role
		remove_role( self::ROLE_NAME );
	}

	/**
	 * Get capabilities for Stadion User role
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
	 * Check if a user is approved
	 *
	 * Note: User approval workflow has been removed (registration is disabled).
	 * All logged-in users are now considered approved.
	 *
	 * @param int $user_id User ID to check.
	 * @return bool Always returns true for valid user IDs.
	 */
	public static function is_user_approved( $user_id ) {
		// All logged-in users are approved (approval workflow removed)
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
