<?php
/**
 * Google Sheets Connection Storage Class
 *
 * Manages Google Sheets connection state stored in user meta.
 * This is separate from contacts/calendar connections.
 * Simpler than contacts - just needs credentials and connection state.
 *
 * Connection data structure:
 * - credentials: string - Encrypted OAuth tokens (via CredentialEncryption)
 * - email: string - Connected Google account email
 * - connected_at: string - ISO 8601 timestamp
 * - last_error: string|null - Last error message
 */

namespace Rondo\Sheets;

use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleSheetsConnection {

	/**
	 * User meta key for storing Google Sheets connection data
	 */
	const META_KEY = '_rondo_google_sheets_connection';

	/**
	 * Get the Google Sheets connection for a user
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|null Connection data array or null if not connected.
	 */
	public static function get_connection( int $user_id ): ?array {
		$connection = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $connection ) ) {
			return null;
		}

		return $connection;
	}

	/**
	 * Save the Google Sheets connection for a user
	 *
	 * Encrypts credentials if provided as an array (not already encrypted).
	 *
	 * @param int   $user_id    WordPress user ID.
	 * @param array $connection Connection data to save.
	 */
	public static function save_connection( int $user_id, array $connection ): void {
		// Encrypt credentials if provided as array (not already encrypted string)
		if ( ! empty( $connection['credentials'] ) && is_array( $connection['credentials'] ) ) {
			$connection['credentials'] = CredentialEncryption::encrypt( $connection['credentials'] );
		}

		update_user_meta( $user_id, self::META_KEY, $connection );
	}

	/**
	 * Delete the Google Sheets connection for a user
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function delete_connection( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * Check if a user has an active Google Sheets connection
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True if connected with credentials.
	 */
	public static function is_connected( int $user_id ): bool {
		$connection = self::get_connection( $user_id );
		return ! empty( $connection['credentials'] );
	}

	/**
	 * Get decrypted credentials for a user's connection
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|null Decrypted credentials or null on failure.
	 */
	public static function get_decrypted_credentials( int $user_id ): ?array {
		$connection = self::get_connection( $user_id );

		if ( empty( $connection['credentials'] ) ) {
			return null;
		}

		return CredentialEncryption::decrypt( $connection['credentials'] );
	}

	/**
	 * Update specific fields of an existing connection
	 *
	 * Merges updates into existing connection data. Handles credential
	 * encryption if credentials are being updated.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $updates Fields to update.
	 * @return bool True if updated, false if no existing connection.
	 */
	public static function update_connection( int $user_id, array $updates ): bool {
		$connection = self::get_connection( $user_id );

		if ( ! $connection ) {
			return false;
		}

		// Handle credential encryption if credentials being updated
		if ( ! empty( $updates['credentials'] ) && is_array( $updates['credentials'] ) ) {
			$updates['credentials'] = CredentialEncryption::encrypt( $updates['credentials'] );
		}

		// Merge updates into existing connection
		$connection = array_merge( $connection, $updates );

		update_user_meta( $user_id, self::META_KEY, $connection );

		return true;
	}

	/**
	 * Update credentials with automatic encryption
	 *
	 * Convenience method for token refresh - encrypts and updates credentials,
	 * clears any previous error.
	 *
	 * @param int   $user_id     WordPress user ID.
	 * @param array $credentials New credentials to encrypt and store.
	 * @return bool True if updated, false if no existing connection.
	 */
	public static function update_credentials( int $user_id, array $credentials ): bool {
		return self::update_connection(
			$user_id,
			[
				'credentials' => $credentials,
				'last_error'  => null,
			]
		);
	}
}
