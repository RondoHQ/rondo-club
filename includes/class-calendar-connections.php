<?php
/**
 * Calendar Connections Helper Class
 *
 * Manages calendar connections stored in user meta. Each connection represents
 * a linked calendar (Google Calendar, CalDAV) with encrypted credentials.
 */

namespace Rondo\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Connections {

	/**
	 * User meta key for storing calendar connections
	 */
	const META_KEY = '_rondo_calendar_connections';

	/**
	 * Get all calendar connections for a user
	 *
	 * @param int $user_id WordPress user ID
	 * @return array Array of connection objects
	 */
	public static function get_user_connections( int $user_id ): array {
		$connections = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $connections ) ) {
			return [];
		}

		return $connections;
	}

	/**
	 * Find the array index of a connection by ID
	 *
	 * @param array  $connections   Array of connections
	 * @param string $connection_id Connection ID to find
	 * @return int|null Array index if found, null otherwise
	 */
	private static function find_connection_index( array $connections, string $connection_id ): ?int {
		foreach ( $connections as $index => $connection ) {
			if ( isset( $connection['id'] ) && $connection['id'] === $connection_id ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Get a single connection by ID
	 *
	 * @param int    $user_id       WordPress user ID
	 * @param string $connection_id Connection ID (e.g., 'conn_abc123')
	 * @return array|null Connection data or null if not found
	 */
	public static function get_connection( int $user_id, string $connection_id ): ?array {
		$connections = self::get_user_connections( $user_id );
		$index       = self::find_connection_index( $connections, $connection_id );

		return $index !== null ? $connections[ $index ] : null;
	}

	/**
	 * Add a new calendar connection
	 *
	 * @param int   $user_id    WordPress user ID
	 * @param array $connection Connection data (provider, name, calendar_id, credentials, etc.)
	 * @return string The generated connection ID
	 */
	public static function add_connection( int $user_id, array $connection ): string {
		$connections = self::get_user_connections( $user_id );

		// Generate unique ID if not provided
		if ( empty( $connection['id'] ) ) {
			$connection['id'] = 'conn_' . uniqid();
		}

		// Set defaults for required fields
		$connection = wp_parse_args(
			$connection,
			[
				'provider'       => '',
				'name'           => '',
				'calendar_id'    => '',
				'credentials'    => '',
				'sync_enabled'   => true,
				'auto_log'       => true,
				'sync_from_days' => 90,
				'sync_to_days'   => 30,
				'sync_frequency' => 15,
				'last_sync'      => null,
				'last_error'     => null,
				'created_at'     => current_time( 'c' ),
			]
		);

		$connections[] = $connection;

		update_user_meta( $user_id, self::META_KEY, $connections );

		return $connection['id'];
	}

	/**
	 * Update an existing connection
	 *
	 * @param int    $user_id       WordPress user ID
	 * @param string $connection_id Connection ID to update
	 * @param array  $updates       Fields to update
	 * @return bool True if updated, false if connection not found
	 */
	public static function update_connection( int $user_id, string $connection_id, array $updates ): bool {
		$connections = self::get_user_connections( $user_id );
		$index       = self::find_connection_index( $connections, $connection_id );

		if ( $index === null ) {
			return false;
		}

		// Merge updates into existing connection, preserving id
		$updates['id']         = $connection_id;
		$connections[ $index ] = array_merge( $connections[ $index ], $updates );

		update_user_meta( $user_id, self::META_KEY, $connections );

		return true;
	}

	/**
	 * Update only the credentials for a connection
	 *
	 * Used for token refresh - encrypts credentials and updates only that field.
	 *
	 * @param int    $user_id       WordPress user ID
	 * @param string $connection_id Connection ID to update
	 * @param array  $credentials   New credentials to encrypt and store
	 * @return bool True if updated, false if connection not found
	 */
	public static function update_credentials( int $user_id, string $connection_id, array $credentials ): bool {
		$encrypted = \Rondo\Data\CredentialEncryption::encrypt( $credentials );

		return self::update_connection(
			$user_id,
			$connection_id,
			[
				'credentials' => $encrypted,
				'last_error'  => null, // Clear any previous error on successful refresh
			]
		);
	}

	/**
	 * Delete a connection and its associated calendar events
	 *
	 * @param int    $user_id       WordPress user ID
	 * @param string $connection_id Connection ID to delete
	 * @return bool True if deleted, false if connection not found
	 */
	public static function delete_connection( int $user_id, string $connection_id ): bool {
		$connections = self::get_user_connections( $user_id );
		$index       = self::find_connection_index( $connections, $connection_id );

		if ( $index === null ) {
			return false;
		}

		unset( $connections[ $index ] );

		// Re-index array to avoid gaps
		$connections = array_values( $connections );

		update_user_meta( $user_id, self::META_KEY, $connections );

		// Delete all calendar_event posts associated with this connection
		$events = get_posts(
			[
				'post_type'      => 'calendar_event',
				'author'         => $user_id,
				'meta_key'       => '_connection_id',
				'meta_value'     => $connection_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		foreach ( $events as $event_id ) {
			wp_delete_post( $event_id, true );
		}

		return true;
	}
}
