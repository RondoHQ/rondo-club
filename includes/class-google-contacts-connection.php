<?php
/**
 * Google Contacts Connection Storage Class
 *
 * Manages Google Contacts connection state stored in user meta.
 * This is separate from calendar connections (which are per-calendar)
 * because Contacts is a user-level connection (one per user account).
 *
 * Connection data structure:
 * - enabled: bool - Whether sync is enabled
 * - access_mode: string - 'readonly' or 'readwrite'
 * - credentials: string - Encrypted OAuth tokens (via CredentialEncryption)
 * - email: string - Connected Google account email
 * - connected_at: string - ISO 8601 timestamp
 * - last_sync: string|null - ISO 8601 timestamp of last sync
 * - last_error: string|null - Last error message
 * - contact_count: int - Number of contacts synced
 * - sync_token: string|null - Google sync token for incremental sync
 * - sync_frequency: int - Sync frequency in minutes (15, 60, 360, 1440)
 */

namespace Caelis\Contacts;

use Caelis\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleContactsConnection {

	/**
	 * User meta key for storing Google Contacts connection data
	 */
	const META_KEY = '_prm_google_contacts_connection';

	/**
	 * User meta key for pending import flag
	 */
	const PENDING_IMPORT_KEY = '_prm_google_contacts_pending_import';

	/**
	 * Get the Google Contacts connection for a user
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
	 * Save the Google Contacts connection for a user
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
	 * Delete the Google Contacts connection for a user
	 *
	 * Also removes the pending import flag.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function delete_connection( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
		delete_user_meta( $user_id, self::PENDING_IMPORT_KEY );
	}

	/**
	 * Check if a user has an active Google Contacts connection
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
	 * Set the pending import flag for a user
	 *
	 * Used to trigger initial import after OAuth connection.
	 * Phase 80 will check this flag and perform the import.
	 *
	 * @param int  $user_id WordPress user ID.
	 * @param bool $pending Whether import is pending.
	 */
	public static function set_pending_import( int $user_id, bool $pending ): void {
		update_user_meta( $user_id, self::PENDING_IMPORT_KEY, $pending );
	}

	/**
	 * Check if a user has a pending import
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True if import is pending.
	 */
	public static function has_pending_import( int $user_id ): bool {
		return (bool) get_user_meta( $user_id, self::PENDING_IMPORT_KEY, true );
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

	/**
	 * Get the default sync frequency in minutes
	 *
	 * @return int Default frequency (60 = hourly)
	 */
	public static function get_default_frequency(): int {
		return 60;
	}

	/**
	 * Get available sync frequency options
	 *
	 * @return array Associative array of minutes => label
	 */
	public static function get_frequency_options(): array {
		return [
			15   => __( 'Every 15 minutes', 'caelis' ),
			60   => __( 'Every hour', 'caelis' ),
			360  => __( 'Every 6 hours', 'caelis' ),
			1440 => __( 'Daily', 'caelis' ),
		];
	}

	/**
	 * Get sync frequency for a user
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Frequency in minutes.
	 */
	public static function get_sync_frequency( int $user_id ): int {
		$connection = self::get_connection( $user_id );
		if ( ! $connection || ! isset( $connection['sync_frequency'] ) ) {
			return self::get_default_frequency();
		}
		return (int) $connection['sync_frequency'];
	}

	/**
	 * Add sync history entry (keeps last 10)
	 *
	 * Records sync operation results for display in Settings UI.
	 *
	 * @param int   $user_id User ID.
	 * @param array $entry   History entry with keys: timestamp, pulled, pushed, errors, duration_ms.
	 */
	public static function add_sync_history_entry( int $user_id, array $entry ): void {
		$connection = self::get_connection( $user_id );
		if ( ! $connection ) {
			return;
		}

		$history = $connection['sync_history'] ?? [];

		// Prepend new entry
		array_unshift( $history, $entry );

		// Keep only last 10
		$history = array_slice( $history, 0, 10 );

		self::update_connection( $user_id, [ 'sync_history' => $history ] );
	}
}
