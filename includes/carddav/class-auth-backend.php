<?php
/**
 * CardDAV Authentication Backend
 *
 * Uses WordPress Application Passwords for authentication.
 * WordPress 6.8+ uses BLAKE2b hashing ($generic$ prefix) for app passwords.
 *
 * @package Rondo
 */

namespace Rondo\CardDAV;

use Sabre\DAV\Auth\Backend\AbstractBasic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuthBackend extends AbstractBasic {

	/**
	 * Current authenticated user
	 *
	 * @var \WP_User|null
	 */
	protected $current_user = null;

	/**
	 * Validate user credentials against WordPress Application Passwords
	 *
	 * @param string $username Username
	 * @param string $password Application password
	 * @return bool True if valid
	 */
	protected function validateUserPass( $username, $password ) {
		// Get the user by login
		$user = get_user_by( 'login', $username );

		if ( ! $user ) {
			return false;
		}

		// Normalize the password (remove spaces that WordPress adds for display)
		$password = preg_replace( '/\s+/', '', $password );

		// Get the user's application passwords
		$app_passwords = \WP_Application_Passwords::get_user_application_passwords( $user->ID );

		if ( empty( $app_passwords ) ) {
			return false;
		}

		// WordPress 6.8+ uses wp_verify_fast_hash for application passwords
		// It handles both $generic$ (BLAKE2b) and legacy $P$ (phpass) hashes
		// Fall back to wp_check_password for older versions
		$use_fast_hash = function_exists( 'wp_verify_fast_hash' );

		foreach ( $app_passwords as $app_password ) {
			if ( $use_fast_hash ) {
				$is_valid = wp_verify_fast_hash( $password, $app_password['password'] );
			} else {
				$is_valid = wp_check_password( $password, $app_password['password'], $user->ID );
			}

			if ( $is_valid ) {
				return $this->authenticate_user( $user, $app_password );
			}
		}

		return false;
	}

	/**
	 * Complete authentication for a user
	 *
	 * @param \WP_User $user The user object
	 * @param array $app_password The matched application password
	 * @return bool True on success
	 */
	private function authenticate_user( $user, $app_password ) {
		// Store the authenticated user for later use
		$this->current_user = $user;

		// Set WordPress current user
		wp_set_current_user( $user->ID );

		// Record the usage
		\WP_Application_Passwords::record_application_password_usage( $user->ID, $app_password['uuid'] );

		return true;
	}

	/**
	 * Get the current authenticated user
	 *
	 * @return \WP_User|null
	 */
	public function getCurrentUser() {
		return $this->current_user;
	}

	/**
	 * Get the current authenticated user ID
	 *
	 * @return int User ID or 0 if not authenticated
	 */
	public function getCurrentUserId() {
		return $this->current_user ? $this->current_user->ID : 0;
	}
}
