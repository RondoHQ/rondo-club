<?php
/**
 * Calendar Sync Background Service
 *
 * Handles WP-Cron background synchronization of calendar events
 * with rate limiting and auto-logging of past meetings as activities.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Calendar_Sync {

	/**
	 * Cron action name
	 */
	const CRON_HOOK = 'prm_calendar_sync';

	/**
	 * Custom cron schedule interval name
	 */
	const CRON_SCHEDULE = 'every_15_minutes';

	/**
	 * Transient key for tracking last synced user index
	 */
	const USER_INDEX_TRANSIENT = 'prm_calendar_sync_last_user_index';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register custom cron schedule
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Register cron callback
		add_action( self::CRON_HOOK, array( $this, 'run_background_sync' ) );

		// Schedule cron on theme activation
		add_action( 'after_switch_theme', array( $this, 'schedule_sync' ) );

		// Unschedule cron on theme deactivation
		add_action( 'switch_theme', array( $this, 'unschedule_sync' ) );
	}

	/**
	 * Add custom cron schedule for 15-minute interval
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules with our interval added.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => 900, // 15 minutes in seconds
			'display'  => __( 'Every 15 Minutes', 'personal-crm' ),
		);

		return $schedules;
	}

	/**
	 * Schedule the calendar sync cron event
	 */
	public function schedule_sync() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the calendar sync cron event
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
	 * Uses round-robin through all users with calendar connections.
	 */
	public function run_background_sync() {
		// Get all users with calendar connections
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

		// Sync this user's connections
		$this->sync_user_connections( $user_id );

		// Auto-log past meetings for all users (rate limited to 10 events per run)
		$this->auto_log_past_meetings();
	}

	/**
	 * Get all user IDs that have calendar connections with sync enabled
	 *
	 * @return array User IDs
	 */
	private function get_users_with_connections() {
		global $wpdb;

		// Query users who have _prm_calendar_connections user meta
		$user_ids = $wpdb->get_col(
			"SELECT DISTINCT user_id
             FROM {$wpdb->usermeta}
             WHERE meta_key = '_prm_calendar_connections'"
		);

		// Filter to users who have at least one sync-enabled connection
		$filtered_users = array();

		foreach ( $user_ids as $user_id ) {
			$connections = PRM_Calendar_Connections::get_user_connections( (int) $user_id );

			foreach ( $connections as $connection ) {
				if ( ! empty( $connection['sync_enabled'] ) ) {
					$filtered_users[] = (int) $user_id;
					break; // Only need one enabled connection
				}
			}
		}

		return $filtered_users;
	}

	/**
	 * Sync calendar connections for a specific user
	 *
	 * @param int $user_id User ID to sync.
	 */
	private function sync_user_connections( $user_id ) {
		$connections = PRM_Calendar_Connections::get_user_connections( $user_id );

		foreach ( $connections as $connection ) {
			// Skip disabled connections
			if ( empty( $connection['sync_enabled'] ) ) {
				continue;
			}

			$connection_id = $connection['id'] ?? '';
			$provider      = $connection['provider'] ?? '';

			if ( empty( $connection_id ) || empty( $provider ) ) {
				continue;
			}

			try {
				// Add user_id to connection for token refresh (Google provider)
				$connection['user_id'] = $user_id;

				// Route to appropriate provider
				if ( $provider === 'caldav' ) {
					$result = PRM_CalDAV_Provider::sync( $user_id, $connection );
				} elseif ( $provider === 'google' ) {
					$result = PRM_Google_Calendar_Provider::sync( $user_id, $connection );
				} else {
					continue; // Unknown provider
				}

				// Update last_sync timestamp and clear error
				PRM_Calendar_Connections::update_connection(
					$user_id,
					$connection_id,
					array(
						'last_sync'  => current_time( 'c' ),
						'last_error' => null,
					)
				);

				error_log(
					sprintf(
						'PRM_Calendar_Sync: Synced connection %s for user %d - %d events (%d created, %d updated)',
						$connection_id,
						$user_id,
						$result['total'] ?? 0,
						$result['created'] ?? 0,
						$result['updated'] ?? 0
					)
				);

			} catch ( Exception $e ) {
				// Update last_error but don't stop other connections
				PRM_Calendar_Connections::update_connection(
					$user_id,
					$connection_id,
					array(
						'last_error' => $e->getMessage(),
					)
				);

				error_log(
					sprintf(
						'PRM_Calendar_Sync: Error syncing connection %s for user %d: %s',
						$connection_id,
						$user_id,
						$e->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Auto-log past meetings as activities
	 *
	 * Creates activity records for past calendar events that:
	 * - Have ended (past events)
	 * - Have matched people
	 * - Have not been logged yet
	 * - Belong to connections with auto_log enabled
	 *
	 * Rate limited to 10 events per run.
	 */
	public function auto_log_past_meetings() {
		$current_time = current_time( 'mysql' );

		// Query for past events that haven't been logged
		$events = get_posts(
			array(
				'post_type'      => 'calendar_event',
				'posts_per_page' => 10, // Rate limit
				'post_status'    => 'publish',
				'orderby'        => 'meta_value',
				'meta_key'       => '_end_time',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					// Past events only
					array(
						'key'     => '_end_time',
						'value'   => $current_time,
						'compare' => '<',
						'type'    => 'DATETIME',
					),
					// Not already logged
					array(
						'relation' => 'OR',
						array(
							'key'     => '_logged_as_activity',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_logged_as_activity',
							'value'   => '',
							'compare' => '=',
						),
						array(
							'key'     => '_logged_as_activity',
							'value'   => '0',
							'compare' => '=',
						),
					),
					// Has matched people
					array(
						'key'     => '_matched_people',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		foreach ( $events as $event ) {
			$this->process_event_for_auto_log( $event );
		}
	}

	/**
	 * Process a single event for auto-logging
	 *
	 * @param WP_Post $event Calendar event post.
	 */
	private function process_event_for_auto_log( $event ) {
		$event_id      = $event->ID;
		$user_id       = (int) $event->post_author;
		$connection_id = get_post_meta( $event_id, '_connection_id', true );

		// Check if connection has auto_log enabled
		if ( ! $this->is_auto_log_enabled( $user_id, $connection_id ) ) {
			return;
		}

		// Get matched people
		$matched_people_json = get_post_meta( $event_id, '_matched_people', true );
		$matched_people      = $matched_people_json ? json_decode( $matched_people_json, true ) : array();

		if ( empty( $matched_people ) || ! is_array( $matched_people ) ) {
			return;
		}

		// Filter to high-confidence matches (>= 50%)
		$valid_matches = array_filter(
			$matched_people,
			function ( $match ) {
				return isset( $match['confidence'] ) && $match['confidence'] >= 50;
			}
		);

		if ( empty( $valid_matches ) ) {
			return;
		}

		// Create activities
		$activities_created = self::create_activity_from_event( $event_id, $user_id, $valid_matches );

		if ( $activities_created > 0 ) {
			// Mark as logged
			update_post_meta( $event_id, '_logged_as_activity', true );

			error_log(
				sprintf(
					'PRM_Calendar_Sync: Auto-logged event %d as %d activities',
					$event_id,
					$activities_created
				)
			);
		}
	}

	/**
	 * Check if auto_log is enabled for a connection
	 *
	 * @param int    $user_id       User ID.
	 * @param string $connection_id Connection ID.
	 * @return bool True if auto_log is enabled.
	 */
	private function is_auto_log_enabled( $user_id, $connection_id ) {
		if ( empty( $connection_id ) ) {
			return false;
		}

		$connection = PRM_Calendar_Connections::get_connection( $user_id, $connection_id );

		if ( ! $connection ) {
			return false;
		}

		return ! empty( $connection['auto_log'] );
	}

	/**
	 * Create activities from a calendar event for matched people
	 *
	 * This is a static method that can be called from both the background sync
	 * and the REST API endpoint (log_event_as_activity).
	 *
	 * @param int   $event_id       Calendar event post ID.
	 * @param int   $user_id        User ID.
	 * @param array $matched_people Array of matches with person_id.
	 * @return int Number of activities created.
	 */
	public static function create_activity_from_event( $event_id, $user_id, $matched_people ) {
		$event = get_post( $event_id );
		if ( ! $event || $event->post_type !== 'calendar_event' ) {
			return 0;
		}

		// Get event metadata
		$title       = $event->post_title;
		$start_time  = get_post_meta( $event_id, '_start_time', true );
		$location    = get_post_meta( $event_id, '_location', true );
		$meeting_url = get_post_meta( $event_id, '_meeting_url', true );

		// Build activity content
		$content = esc_html( $title );
		if ( $location ) {
			$content .= ' at ' . esc_html( $location );
		}
		if ( $meeting_url ) {
			$content .= ' (' . esc_url( $meeting_url ) . ')';
		}

		// Parse date and time from start_time
		$activity_date = '';
		$activity_time = '';
		if ( $start_time ) {
			$datetime = date_create( $start_time );
			if ( $datetime ) {
				$activity_date = $datetime->format( 'Y-m-d' );
				$activity_time = $datetime->format( 'H:i' );
			}
		}

		// Extract person IDs
		$person_ids = array();
		foreach ( $matched_people as $match ) {
			if ( isset( $match['person_id'] ) ) {
				$person_ids[] = (int) $match['person_id'];
			}
		}

		if ( empty( $person_ids ) ) {
			return 0;
		}

		$activities_created = 0;

		// Create activity for each matched person
		foreach ( $person_ids as $person_id ) {
			// Get other participant IDs (everyone except this person)
			$participants = array_filter(
				$person_ids,
				function ( $pid ) use ( $person_id ) {
					return $pid !== $person_id;
				}
			);

			// Create activity comment
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'  => $person_id,
					'comment_content'  => $content,
					'comment_type'     => 'prm_activity',
					'user_id'          => $user_id,
					'comment_approved' => 1,
				)
			);

			if ( $comment_id ) {
				// Save activity meta
				update_comment_meta( $comment_id, 'activity_type', 'meeting' );
				if ( $activity_date ) {
					update_comment_meta( $comment_id, 'activity_date', $activity_date );
				}
				if ( $activity_time ) {
					update_comment_meta( $comment_id, 'activity_time', $activity_time );
				}
				if ( ! empty( $participants ) ) {
					update_comment_meta( $comment_id, 'participants', array_values( $participants ) );
				}

				++$activities_created;
			}
		}

		return $activities_created;
	}

	/**
	 * Get sync status information
	 *
	 * @return array Status data including next scheduled, user counts, cycle time.
	 */
	public static function get_sync_status() {
		$next_scheduled = wp_next_scheduled( self::CRON_HOOK );

		// Get users with connections
		global $wpdb;
		$user_ids = $wpdb->get_col(
			"SELECT DISTINCT user_id
             FROM {$wpdb->usermeta}
             WHERE meta_key = '_prm_calendar_connections'"
		);

		$total_users = 0;
		foreach ( $user_ids as $user_id ) {
			$connections = PRM_Calendar_Connections::get_user_connections( (int) $user_id );
			foreach ( $connections as $connection ) {
				if ( ! empty( $connection['sync_enabled'] ) ) {
					++$total_users;
					break;
				}
			}
		}

		$current_index = (int) get_transient( self::USER_INDEX_TRANSIENT );

		return array(
			'next_scheduled'               => $next_scheduled ? date( 'c', $next_scheduled ) : null,
			'is_scheduled'                 => (bool) $next_scheduled,
			'total_users_with_connections' => $total_users,
			'current_user_index'           => $current_index,
			'estimated_full_cycle_minutes' => $total_users * 15,
			'cron_schedule'                => self::CRON_SCHEDULE,
		);
	}

	/**
	 * Force sync all users immediately (for testing/CLI)
	 *
	 * Ignores rate limiting and syncs all users with enabled connections.
	 *
	 * @return array Summary of sync results per user.
	 */
	public static function force_sync_all() {
		$instance = new self();
		$users    = $instance->get_users_with_connections();
		$results  = array();

		foreach ( $users as $user_id ) {
			$user_results = array(
				'user_id'     => $user_id,
				'connections' => array(),
			);

			$connections = PRM_Calendar_Connections::get_user_connections( $user_id );

			foreach ( $connections as $connection ) {
				if ( empty( $connection['sync_enabled'] ) ) {
					continue;
				}

				$connection_id = $connection['id'] ?? '';
				$provider      = $connection['provider'] ?? '';

				try {
					$connection['user_id'] = $user_id;

					if ( $provider === 'caldav' ) {
						$result = PRM_CalDAV_Provider::sync( $user_id, $connection );
					} elseif ( $provider === 'google' ) {
						$result = PRM_Google_Calendar_Provider::sync( $user_id, $connection );
					} else {
						continue;
					}

					PRM_Calendar_Connections::update_connection(
						$user_id,
						$connection_id,
						array(
							'last_sync'  => current_time( 'c' ),
							'last_error' => null,
						)
					);

					$user_results['connections'][] = array(
						'id'      => $connection_id,
						'status'  => 'success',
						'created' => $result['created'] ?? 0,
						'updated' => $result['updated'] ?? 0,
						'total'   => $result['total'] ?? 0,
					);

				} catch ( Exception $e ) {
					PRM_Calendar_Connections::update_connection(
						$user_id,
						$connection_id,
						array(
							'last_error' => $e->getMessage(),
						)
					);

					$user_results['connections'][] = array(
						'id'     => $connection_id,
						'status' => 'error',
						'error'  => $e->getMessage(),
					);
				}
			}

			$results[] = $user_results;
		}

		// Also run auto-log after full sync
		$instance->auto_log_past_meetings();

		return $results;
	}
}
