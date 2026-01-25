<?php
/**
 * CardDAV Authentication Backend
 *
 * Uses WordPress Application Passwords for authentication.
 * WordPress 6.8+ uses BLAKE2b hashing ($generic$ prefix) for app passwords.
 *
 * @package Stadion
 */

namespace Stadion\CardDAV;

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
			error_log( 'CardDAV Auth Failed: User not found - ' . $username );
			return false;
		}

		// Normalize the password (remove spaces that WordPress adds for display)
		$password = preg_replace( '/\s+/', '', $password );

		// Get the user's application passwords
		$app_passwords = \WP_Application_Passwords::get_user_application_passwords( $user->ID );

		if ( empty( $app_passwords ) ) {
			error_log( 'CardDAV Auth Failed for user: ' . $username . ' - No application passwords found' );
			return false;
		}

		// Log password details for debugging
		$password_length  = strlen( $password );
		$password_preview = substr( $password, 0, 4 ) . '...' . substr( $password, -4 );
		error_log( "CardDAV Auth: Checking user {$username}, password length: {$password_length}, preview: {$password_preview}, found " . count( $app_passwords ) . ' app password(s)' );

		// Check each application password using wp_verify_fast_hash (WordPress 6.8+)
		foreach ( $app_passwords as $app_password ) {
			$hash_prefix = substr( $app_password['password'], 0, 10 );

			// WordPress 6.8+ uses wp_verify_fast_hash for application passwords
			// It handles both $generic$ (BLAKE2b) and legacy $P$ (phpass) hashes
			if ( function_exists( 'wp_verify_fast_hash' ) ) {
				$result = wp_verify_fast_hash( $password, $app_password['password'] );
				if ( $result ) {
					return $this->authenticate_user( $user, $app_password );
				}
			} else {
				// Fallback for WordPress < 6.8
				if ( wp_check_password( $password, $app_password['password'], $user->ID ) ) {
					return $this->authenticate_user( $user, $app_password );
				}
			}
		}

		error_log( 'CardDAV Auth Failed for user: ' . $username . ' - Invalid application password (tried ' . count( $app_passwords ) . ' passwords)' );
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

		error_log( 'CardDAV Auth: Success for user ' . $user->user_login . ' with app password "' . $app_password['name'] . '"' );
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
