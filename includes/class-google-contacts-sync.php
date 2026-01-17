<?php
/**
 * Google Contacts Sync Background Service
 *
 * Handles WP-Cron background synchronization of contacts with Google.
 * Processes users round-robin with configurable sync frequency.
 */

namespace Caelis\Contacts;

use Caelis\Import\GoogleContactsAPI;
use Caelis\Export\GoogleContactsExport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleContactsSync {

	/**
	 * Cron action name
	 */
	const CRON_HOOK = 'prm_google_contacts_sync';

	/**
	 * Custom cron schedule interval name
	 */
	const CRON_SCHEDULE = 'every_15_minutes';

	/**
	 * Transient key for tracking last synced user index
	 */
	const USER_INDEX_TRANSIENT = 'prm_contacts_sync_last_user_index';

	/**
	 * Default sync frequency in minutes
	 */
	const DEFAULT_FREQUENCY = 60;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register custom cron schedule (only if not already registered by calendar sync)
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

		// Register cron callback
		add_action( self::CRON_HOOK, [ $this, 'run_background_sync' ] );

		// Schedule cron on theme activation
		add_action( 'after_switch_theme', [ $this, 'schedule_sync' ] );

		// Unschedule cron on theme deactivation
		add_action( 'switch_theme', [ $this, 'unschedule_sync' ] );
	}

	/**
	 * Add custom cron schedule for 15-minute interval
	 *
	 * Only adds if not already registered (calendar sync may have added it).
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules with our interval added.
	 */
	public function add_cron_schedules( $schedules ) {
		// Only add if not already registered by calendar sync
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = [
				'interval' => 900, // 15 minutes in seconds
				'display'  => __( 'Every 15 Minutes', 'caelis' ),
			];
		}

		return $schedules;
	}

	/**
	 * Schedule the contacts sync cron event
	 */
	public function schedule_sync() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the contacts sync cron event
	 */
	public function unschedule_sync() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Run background sync (cron callback)
	 *
	 * Processes one user per cron run to spread API load.
	 * Uses round-robin through all users with Google Contacts connections.
	 */
	public function run_background_sync() {
		// Get all users with Google Contacts connections
		$users = $this->get_users_with_connections();

		if ( empty( $users ) ) {
			return;
		}

		// Get last processed user index (for round-robin)
		$last_index = (int) get_transient( self::USER_INDEX_TRANSIENT );

		// Calculate next user index
		$next_index = ( $last_index + 1 ) % count( $users );

		// Get the user to sync this run
		$user_id = $users[ $next_index ];

		// Update transient for next run
		set_transient( self::USER_INDEX_TRANSIENT, $next_index, HOUR_IN_SECONDS );

		// Sync this user's contacts
		$this->sync_user( $user_id );
	}

	/**
	 * Get all user IDs that have Google Contacts connections
	 *
	 * @return array User IDs with active connections.
	 */
	public function get_users_with_connections() {
		global $wpdb;

		// Query users who have _prm_google_contacts_connection user meta
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id
				FROM {$wpdb->usermeta}
				WHERE meta_key = %s",
				GoogleContactsConnection::META_KEY
			)
		);

		// Filter to users who actually have credentials (are connected)
		$connected_users = [];

		foreach ( $user_ids as $user_id ) {
			if ( GoogleContactsConnection::is_connected( (int) $user_id ) ) {
				$connected_users[] = (int) $user_id;
			}
		}

		return $connected_users;
	}

	/**
	 * Check if sync is due for a user based on frequency setting
	 *
	 * @param int $user_id User ID.
	 * @return bool True if sync is due.
	 */
	public function is_sync_due( int $user_id ): bool {
		$connection = GoogleContactsConnection::get_connection( $user_id );

		if ( ! $connection ) {
			return false;
		}

		$last_sync = $connection['last_sync'] ?? null;

		// No last sync - always due
		if ( empty( $last_sync ) ) {
			return true;
		}

		// Get sync frequency (default 60 minutes / hourly)
		$frequency_minutes = isset( $connection['sync_frequency'] )
			? absint( $connection['sync_frequency'] )
			: self::DEFAULT_FREQUENCY;

		// Parse last_sync timestamp
		$last_sync_time = strtotime( $last_sync );
		if ( $last_sync_time === false ) {
			return true; // Invalid timestamp, sync now
		}

		// Calculate if enough time has passed
		$seconds_since_sync = time() - $last_sync_time;
		$required_seconds   = $frequency_minutes * 60;

		return $seconds_since_sync >= $required_seconds;
	}

	/**
	 * Sync contacts for a specific user
	 *
	 * Pulls changes from Google (using syncToken) and pushes local changes.
	 *
	 * @param int $user_id User ID to sync.
	 * @return array|null Sync results with pull_stats and push_stats, or null if skipped.
	 */
	public function sync_user( int $user_id ): ?array {
		// Check if sync is due based on frequency setting
		if ( ! $this->is_sync_due( $user_id ) ) {
			return null;
		}

		// Check if user is actually connected
		if ( ! GoogleContactsConnection::is_connected( $user_id ) ) {
			return null;
		}

		// Verify user has readwrite access for bidirectional sync
		$connection = GoogleContactsConnection::get_connection( $user_id );
		if ( ! $connection ) {
			return null;
		}

		$has_write_access = ( $connection['access_mode'] ?? '' ) === 'readwrite';

		$results = [
			'pull_stats' => null,
			'push_stats' => null,
		];

		try {
			// PULL PHASE: Google -> Caelis
			$importer             = new GoogleContactsAPI( $user_id );
			$results['pull_stats'] = $importer->import_delta();

			// PUSH PHASE: Caelis -> Google (only if readwrite access)
			if ( $has_write_access ) {
				$results['push_stats'] = $this->push_changed_contacts( $user_id );
			}

			// Update connection with sync results
			$contact_count = 0;
			if ( $results['pull_stats'] ) {
				$contact_count += $results['pull_stats']['contacts_imported'] ?? 0;
				$contact_count += $results['pull_stats']['contacts_updated'] ?? 0;
			}

			GoogleContactsConnection::update_connection(
				$user_id,
				[
					'last_sync'     => current_time( 'c' ),
					'last_error'    => null,
					'contact_count' => $contact_count,
				]
			);

			// Log success
			$pull_count = $results['pull_stats']
				? ( ( $results['pull_stats']['contacts_imported'] ?? 0 ) + ( $results['pull_stats']['contacts_updated'] ?? 0 ) )
				: 0;
			$push_count = $results['push_stats']['pushed'] ?? 0;
			$unlinked   = $results['pull_stats']['contacts_unlinked'] ?? 0;

			error_log(
				sprintf(
					'PRM_Contacts_Sync: User %d - pulled %d, pushed %d, unlinked %d',
					$user_id,
					$pull_count,
					$push_count,
					$unlinked
				)
			);

			return $results;

		} catch ( \Exception $e ) {
			// Update last_error but continue processing
			GoogleContactsConnection::update_connection(
				$user_id,
				[
					'last_error' => $e->getMessage(),
				]
			);

			error_log(
				sprintf(
					'PRM_Contacts_Sync: Error syncing contacts for user %d: %s',
					$user_id,
					$e->getMessage()
				)
			);

			return null;
		}
	}

	/**
	 * Push contacts modified in Caelis since last export to Google
	 *
	 * @param int $user_id User ID.
	 * @return array Stats with pushed count and errors.
	 */
	private function push_changed_contacts( int $user_id ): array {
		$stats = [
			'pushed'  => 0,
			'failed'  => 0,
			'skipped' => 0,
			'errors'  => [],
		];

		// Query person posts that are linked to Google and may have changed
		$args = [
			'post_type'      => 'person',
			'post_status'    => 'publish',
			'author'         => $user_id,
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => '_google_contact_id',
					'compare' => 'EXISTS',
				],
			],
		];

		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return $stats;
		}

		$exporter = new GoogleContactsExport( $user_id );

		foreach ( $query->posts as $post ) {
			$last_export   = get_post_meta( $post->ID, '_google_last_export', true );
			$post_modified = $post->post_modified;

			// Skip if not modified since last export
			if ( ! empty( $last_export ) && strtotime( $post_modified ) <= strtotime( $last_export ) ) {
				++$stats['skipped'];
				continue;
			}

			try {
				$result = $exporter->export_contact( $post->ID );
				if ( $result ) {
					++$stats['pushed'];
				} else {
					++$stats['failed'];
					$stats['errors'][] = sprintf( 'Export failed for post %d', $post->ID );
				}
			} catch ( \Exception $e ) {
				++$stats['failed'];
				$stats['errors'][] = sprintf( 'Post %d: %s', $post->ID, $e->getMessage() );
			}

			// Small delay between requests to respect Google API rate limits
			// 100ms delay = max 10 requests/second
			usleep( 100000 );
		}

		return $stats;
	}

	/**
	 * Get sync status information
	 *
	 * @return array Status data including next scheduled, user counts, cycle info.
	 */
	public static function get_sync_status() {
		$next_scheduled = wp_next_scheduled( self::CRON_HOOK );

		// Get users with connections
		$instance    = new self();
		$total_users = count( $instance->get_users_with_connections() );

		$current_index = (int) get_transient( self::USER_INDEX_TRANSIENT );

		return [
			'next_scheduled'               => $next_scheduled ? gmdate( 'c', $next_scheduled ) : null,
			'is_scheduled'                 => (bool) $next_scheduled,
			'total_users_with_connections' => $total_users,
			'current_user_index'           => $current_index,
			'estimated_full_cycle_minutes' => $total_users * 15,
			'cron_schedule'                => self::CRON_SCHEDULE,
		];
	}

	/**
	 * Force sync all users immediately (for testing/CLI)
	 *
	 * Ignores rate limiting and syncs all users with connections.
	 *
	 * @return array Summary of sync results per user.
	 */
	public static function force_sync_all(): array {
		$instance = new self();
		$users    = $instance->get_users_with_connections();
		$results  = [];

		foreach ( $users as $user_id ) {
			$user_results = [
				'user_id' => $user_id,
				'status'  => 'pending',
			];

			try {
				// Force sync by temporarily ignoring frequency check
				$sync_results = $instance->force_sync_user( $user_id );

				$user_results['status']     = 'success';
				$user_results['pull_stats'] = $sync_results['pull_stats'] ?? null;
				$user_results['push_stats'] = $sync_results['push_stats'] ?? null;

			} catch ( \Exception $e ) {
				GoogleContactsConnection::update_connection(
					$user_id,
					[
						'last_error' => $e->getMessage(),
					]
				);

				$user_results['status'] = 'error';
				$user_results['error']  = $e->getMessage();
			}

			$results[] = $user_results;
		}

		return $results;
	}

	/**
	 * Force sync a single user, ignoring frequency check
	 *
	 * @param int $user_id User ID to sync.
	 * @return array Sync results with pull_stats and push_stats.
	 * @throws \Exception On sync errors.
	 */
	private function force_sync_user( int $user_id ): array {
		// Check if user is actually connected
		if ( ! GoogleContactsConnection::is_connected( $user_id ) ) {
			throw new \Exception( 'User is not connected to Google Contacts' );
		}

		// Verify user has readwrite access for bidirectional sync
		$connection = GoogleContactsConnection::get_connection( $user_id );
		if ( ! $connection ) {
			throw new \Exception( 'No connection found for user' );
		}

		$has_write_access = ( $connection['access_mode'] ?? '' ) === 'readwrite';

		$results = [
			'pull_stats' => null,
			'push_stats' => null,
		];

		// PULL PHASE: Google -> Caelis
		$importer             = new GoogleContactsAPI( $user_id );
		$results['pull_stats'] = $importer->import_delta();

		// PUSH PHASE: Caelis -> Google (only if readwrite access)
		if ( $has_write_access ) {
			$results['push_stats'] = $this->push_changed_contacts( $user_id );
		}

		// Update connection with sync results
		$contact_count = 0;
		if ( $results['pull_stats'] ) {
			$contact_count += $results['pull_stats']['contacts_imported'] ?? 0;
			$contact_count += $results['pull_stats']['contacts_updated'] ?? 0;
		}

		GoogleContactsConnection::update_connection(
			$user_id,
			[
				'last_sync'     => current_time( 'c' ),
				'last_error'    => null,
				'contact_count' => $contact_count,
			]
		);

		return $results;
	}
}
