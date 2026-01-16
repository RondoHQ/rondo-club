<?php
/**
 * Visibility Helper Class
 *
 * Provides static helper methods for managing post visibility and sharing.
 *
 * @package Caelis
 * @since 1.44.0
 */

namespace Caelis\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visibility {

	/**
	 * Visibility constants
	 */
	const VISIBILITY_PRIVATE   = 'private';
	const VISIBILITY_WORKSPACE = 'workspace';
	const VISIBILITY_SHARED    = 'shared';

	/**
	 * Meta key for shared_with data
	 */
	const SHARED_WITH_META_KEY = '_shared_with';

	/**
	 * Valid visibility values
	 */
	private static $valid_visibilities = [
		self::VISIBILITY_PRIVATE,
		self::VISIBILITY_WORKSPACE,
		self::VISIBILITY_SHARED,
	];

	/**
	 * Get visibility for a post
	 *
	 * @param int $post_id Post ID.
	 * @return string Visibility value ('private', 'workspace', or 'shared'). Returns 'private' if not set.
	 */
	public static function get_visibility( $post_id ) {
		$visibility = get_field( '_visibility', $post_id );

		// Return 'private' as default if not set or invalid
		if ( empty( $visibility ) || ! in_array( $visibility, self::$valid_visibilities, true ) ) {
			return self::VISIBILITY_PRIVATE;
		}

		return $visibility;
	}

	/**
	 * Set visibility for a post
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $visibility Visibility value ('private', 'workspace', or 'shared').
	 * @return bool True on success, false on failure.
	 */
	public static function set_visibility( $post_id, $visibility ) {
		// Validate visibility value
		if ( ! in_array( $visibility, self::$valid_visibilities, true ) ) {
			return false;
		}

		return update_field( '_visibility', $visibility, $post_id );
	}

	/**
	 * Get shared_with array for a post
	 *
	 * Returns the list of users a post is shared with.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of share objects with user_id, permission, shared_by, shared_at.
	 */
	public static function get_shares( $post_id ) {
		$shares = get_post_meta( $post_id, self::SHARED_WITH_META_KEY, true );

		// Return empty array if not set or invalid
		if ( empty( $shares ) || ! is_array( $shares ) ) {
			return [];
		}

		return $shares;
	}

	/**
	 * Add a user share to a post
	 *
	 * Adds a new share entry. If the user already has a share, it will be updated.
	 *
	 * @param int    $post_id    Post ID.
	 * @param int    $user_id    User ID to share with.
	 * @param string $permission Permission level ('view' or 'edit'). Default 'view'.
	 * @param int    $shared_by  User ID who shared. Default current user.
	 * @return bool True on success, false on failure.
	 */
	public static function add_share( $post_id, $user_id, $permission = 'view', $shared_by = null ) {
		// Validate inputs
		if ( empty( $post_id ) || empty( $user_id ) ) {
			return false;
		}

		// Validate permission
		if ( ! in_array( $permission, [ 'view', 'edit' ], true ) ) {
			$permission = 'view';
		}

		// Default shared_by to current user
		if ( null === $shared_by ) {
			$shared_by = get_current_user_id();
		}

		// Get existing shares
		$shares = self::get_shares( $post_id );

		// Check if user already has a share
		$found = false;
		foreach ( $shares as &$share ) {
			if ( isset( $share['user_id'] ) && (int) $share['user_id'] === (int) $user_id ) {
				// Update existing share
				$share['permission'] = $permission;
				$share['shared_by']  = (int) $shared_by;
				$share['shared_at']  = gmdate( 'c' ); // ISO 8601 format
				$found               = true;
				break;
			}
		}
		unset( $share );

		// Add new share if not found
		if ( ! $found ) {
			$shares[] = [
				'user_id'    => (int) $user_id,
				'permission' => $permission,
				'shared_by'  => (int) $shared_by,
				'shared_at'  => gmdate( 'c' ),
			];
		}

		// Save shares
		return update_post_meta( $post_id, self::SHARED_WITH_META_KEY, $shares );
	}

	/**
	 * Remove a user share from a post
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID to remove share for.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_share( $post_id, $user_id ) {
		// Validate inputs
		if ( empty( $post_id ) || empty( $user_id ) ) {
			return false;
		}

		// Get existing shares
		$shares = self::get_shares( $post_id );

		// Filter out the user's share
		$filtered = array_filter(
			$shares,
			function ( $share ) use ( $user_id ) {
				return isset( $share['user_id'] ) && (int) $share['user_id'] !== (int) $user_id;
			}
		);

		// Re-index array
		$filtered = array_values( $filtered );

		// Save shares (or delete meta if empty)
		if ( empty( $filtered ) ) {
			return delete_post_meta( $post_id, self::SHARED_WITH_META_KEY );
		}

		return update_post_meta( $post_id, self::SHARED_WITH_META_KEY, $filtered );
	}

	/**
	 * Check if a user has share access to a post
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID to check.
	 * @return bool True if user has a share, false otherwise.
	 */
	public static function user_has_share( $post_id, $user_id ) {
		return self::get_share_permission( $post_id, $user_id ) !== false;
	}

	/**
	 * Get share permission for a user
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID to check.
	 * @return string|false Permission level ('view' or 'edit'), or false if no share exists.
	 */
	public static function get_share_permission( $post_id, $user_id ) {
		// Validate inputs
		if ( empty( $post_id ) || empty( $user_id ) ) {
			return false;
		}

		$shares = self::get_shares( $post_id );

		foreach ( $shares as $share ) {
			if ( isset( $share['user_id'] ) && (int) $share['user_id'] === (int) $user_id ) {
				return isset( $share['permission'] ) ? $share['permission'] : 'view';
			}
		}

		return false;
	}

	/**
	 * Get all valid visibility values
	 *
	 * @return array Array of valid visibility values.
	 */
	public static function get_valid_visibilities() {
		return self::$valid_visibilities;
	}

	/**
	 * Check if a visibility value is valid
	 *
	 * @param string $visibility Visibility value to check.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_visibility( $visibility ) {
		return in_array( $visibility, self::$valid_visibilities, true );
	}
}
