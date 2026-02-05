<?php
/**
 * Reminders System
 *
 * Handles date-based reminders and notifications
 */

namespace Stadion\Collaboration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reminders {

	/**
	 * Available notification channels
	 */
	private $channels = [];

	public function __construct() {
		// Register per-user cron hook (new system)
		add_action( 'stadion_user_reminder', [ $this, 'process_user_reminders' ] );

		// Register legacy cron hook (deprecated, kept for backward compatibility)
		add_action( 'stadion_daily_reminder_check', [ $this, 'process_daily_reminders' ] );

		// Add custom cron schedule if needed
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

		// Initialize notification channels
		$this->channels = [
			new \STADION_Email_Channel(),
		];
	}

	/**
	 * Schedule reminder cron job for a specific user
	 *
	 * @param int $user_id User ID
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function schedule_user_reminder( $user_id ) {
		// Get user's preferred notification time
		$preferred_time = get_user_meta( $user_id, 'stadion_notification_time', true );
		if ( empty( $preferred_time ) ) {
			$preferred_time = '09:00';
		}

		// Parse time
		list($hour, $minute) = explode( ':', $preferred_time );
		$hour                = (int) $hour;
		$minute              = (int) $minute;

		// Calculate next occurrence in WordPress timezone
		$now      = new \DateTime( 'now', wp_timezone() );
		$next_run = clone $now;
		$next_run->setTime( $hour, $minute, 0 );

		// If time has passed today, schedule for tomorrow
		if ( $next_run <= $now ) {
			$next_run->modify( '+1 day' );
		}

		// Unschedule existing cron for this user (if any)
		$this->unschedule_user_reminder( $user_id );

		// Schedule the cron job (recurring daily)
		$scheduled = wp_schedule_event(
			$next_run->getTimestamp(),
			'daily',
			'stadion_user_reminder',
			[ $user_id ]
		);

		if ( $scheduled === false ) {
			return new \WP_Error(
				'cron_schedule_failed',
				sprintf( __( 'Failed to schedule reminder cron for user %d.', 'stadion' ), $user_id )
			);
		}

		return true;
	}

	/**
	 * Unschedule reminder cron job for a specific user
	 *
	 * @param int $user_id User ID
	 * @return bool True on success
	 */
	public function unschedule_user_reminder( $user_id ) {
		$timestamp = wp_next_scheduled( 'stadion_user_reminder', [ $user_id ] );

		if ( $timestamp !== false ) {
			wp_unschedule_event( $timestamp, 'stadion_user_reminder', [ $user_id ] );
		}

		return true;
	}

	/**
	 * Schedule reminder cron jobs for all users who should receive reminders
	 *
	 * @return int Number of users scheduled
	 */
	public function schedule_all_user_reminders() {
		$users_to_notify = $this->get_all_users_to_notify();
		$scheduled_count = 0;

		foreach ( $users_to_notify as $user_id ) {
			$result = $this->schedule_user_reminder( $user_id );
			if ( $result === true ) {
				++$scheduled_count;
			}
		}

		return $scheduled_count;
	}

	/**
	 * Process reminders for a specific user (called by per-user cron)
	 *
	 * @param int $user_id User ID
	 */
	public function process_user_reminders( $user_id ) {
		// Verify user exists
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Get weekly digest for this user
		$digest_data = $this->get_weekly_digest( $user_id );

		// Check if there are any dates or todos to notify about
		$has_dates = ! empty( $digest_data['today'] ) ||
					! empty( $digest_data['tomorrow'] ) ||
					! empty( $digest_data['rest_of_week'] );

		$has_todos = ! empty( $digest_data['todos']['today'] ) ||
					! empty( $digest_data['todos']['tomorrow'] ) ||
					! empty( $digest_data['todos']['rest_of_week'] );

		// Get queued mention notifications
		$mentions = \STADION_Mention_Notifications::get_queued_mentions( $user_id );

		// Get recent workspace activity (notes on shared contacts in user's workspaces)
		$workspace_activity = $this->get_workspace_activity( $user_id );

		// Add collaborative data to digest
		$digest_data['mentions']           = $mentions;
		$digest_data['workspace_activity'] = $workspace_activity;

		// Check if there's any content at all
		$has_collab = ! empty( $mentions ) || ! empty( $workspace_activity );

		if ( ! $has_dates && ! $has_todos && ! $has_collab ) {
			return;
		}

		// Send via all enabled channels
		foreach ( $this->channels as $channel ) {
			if ( $channel->is_enabled_for_user( $user_id ) ) {
				$channel->send( $user_id, $digest_data );
			}
		}

		// Update expired work history (only once per day, not per user)
		// Use a transient to ensure this only runs once per day
		$transient_key = 'stadion_work_history_updated_' . gmdate( 'Y-m-d' );
		if ( false === get_transient( $transient_key ) ) {
			$this->update_expired_work_history();
			set_transient( $transient_key, true, DAY_IN_SECONDS );
		}
	}

	/**
	 * Get recent activity on contacts in user's workspaces (last 24 hours)
	 *
	 * @param int $user_id User ID
	 * @return array Recent notes on workspace contacts by other users
	 */
	private function get_workspace_activity( $user_id ) {
		// Get user's workspace memberships
		$memberships = get_user_meta( $user_id, '_workspace_memberships', true );
		if ( ! is_array( $memberships ) || empty( $memberships ) ) {
			return [];
		}

		$workspace_ids = array_keys( $memberships );

		// Get workspace terms
		$term_slugs = array_map(
			function ( $id ) {
				return 'workspace-' . $id;
			},
			$workspace_ids
		);
		$terms      = get_terms(
			[
				'taxonomy'   => 'workspace_access',
				'slug'       => $term_slugs,
				'hide_empty' => false,
			]
		);
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}
		$term_ids = wp_list_pluck( $terms, 'term_id' );

		// Get contacts in these workspaces
		$contacts = get_posts(
			[
				'post_type'      => [ 'person', 'team' ],
				'posts_per_page' => -1,
				'tax_query'      => [
					[
						'taxonomy' => 'workspace_access',
						'field'    => 'term_id',
						'terms'    => $term_ids,
					],
				],
				'fields'         => 'ids',
			]
		);
		if ( empty( $contacts ) ) {
			return [];
		}

		// Get shared notes from last 24 hours by OTHER users
		$since    = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
		$comments = get_comments(
			[
				'post__in'       => $contacts,
				'type'           => 'stadion_note',
				'author__not_in' => [ $user_id ], // Not user's own notes
				'date_query'     => [
					'after' => $since,
				],
				'meta_query'     => [
					[
						'key'   => '_note_visibility',
						'value' => 'shared',
					],
				],
				'number'         => 10, // Limit to recent 10
			]
		);

		$activity = [];
		foreach ( $comments as $comment ) {
			$author = get_userdata( $comment->user_id );
			$post   = get_post( $comment->comment_post_ID );
			if ( ! $author || ! $post ) {
				continue;
			}

			$activity[] = [
				'author'     => $author->display_name,
				'post_title' => $post->post_title,
				'post_type'  => $post->post_type,
				'post_url'   => home_url( ( $post->post_type === 'person' ? '/people/' : '/teams/' ) . $post->ID ),
				'preview'    => wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 20 ),
				'date'       => $comment->comment_date,
			];
		}

		return $activity;
	}

	/**
	 * Add custom cron schedules
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['stadion_twice_daily'] = [
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily', 'stadion' ),
		];

		return $schedules;
	}

	/**
	 * Get upcoming reminders
	 */
	public function get_upcoming_reminders( $days_ahead = 30 ) {
		$dates = get_posts(
			[
				'post_type'      => 'important_date',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			]
		);

		$upcoming = [];
		$today    = new \DateTime( 'today', wp_timezone() );
		$end_date = ( clone $today )->modify( "+{$days_ahead} days" );

		foreach ( $dates as $date_post ) {
			$date_value   = get_field( 'date_value', $date_post->ID );
			$is_recurring = get_field( 'is_recurring', $date_post->ID );

			if ( ! $date_value ) {
				continue;
			}

			$next_occurrence = $this->calculate_next_occurrence( $date_value, $is_recurring );

			if ( ! $next_occurrence ) {
				continue;
			}

			// Check if the next occurrence falls within our window
			if ( $next_occurrence > $end_date ) {
				continue;
			}

			// Only include if next occurrence is today or in the future
			if ( $next_occurrence < $today ) {
				continue;
			}

			$related_people = get_field( 'related_people', $date_post->ID ) ?: [];
			$people_data    = [];

			foreach ( $related_people as $person ) {
				$person_id     = is_object( $person ) ? $person->ID : $person;
				$people_data[] = [
					'id'        => $person_id,
					'name'      => html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' ),
					'thumbnail' => get_the_post_thumbnail_url( $person_id, 'thumbnail' ),
				];
			}

			$upcoming[] = [
				'id'              => $date_post->ID,
				'title'           => html_entity_decode( $date_post->post_title, ENT_QUOTES, 'UTF-8' ),
				'date_value'      => $date_value,
				'next_occurrence' => $next_occurrence->format( 'Y-m-d' ),
				'days_until'      => (int) $today->diff( $next_occurrence )->days,
				'is_recurring'    => (bool) $is_recurring,
				'year_unknown'    => (bool) get_field( 'year_unknown', $date_post->ID ),
				'date_type'       => wp_get_post_terms( $date_post->ID, 'date_type', [ 'fields' => 'names' ] ),
				'related_people'  => $people_data,
			];
		}

		// Sort by next occurrence
		usort(
			$upcoming,
			function ( $a, $b ) {
				return strcmp( $a['next_occurrence'], $b['next_occurrence'] );
			}
		);

		return $upcoming;
	}

	/**
	 * Calculate the next occurrence of a date
	 */
	public function calculate_next_occurrence( $date_string, $is_recurring ) {
		try {
			$date = \DateTime::createFromFormat( 'Y-m-d', $date_string, wp_timezone() );

			if ( ! $date ) {
				// Try alternative formats
				$date = \DateTime::createFromFormat( 'Ymd', $date_string, wp_timezone() );
			}

			if ( ! $date ) {
				return null;
			}

			$today = new \DateTime( 'today', wp_timezone() );

			if ( ! $is_recurring ) {
				// One-time date: only return if today or in the future
				return $date >= $today ? $date : null;
			}

			// Recurring: find next occurrence (same month/day, this year or next)
			$this_year = ( clone $date )->setDate(
				(int) $today->format( 'Y' ),
				(int) $date->format( 'm' ),
				(int) $date->format( 'd' )
			);

			if ( $this_year >= $today ) {
				return $this_year;
			}

			// Already passed this year, return next year
			return $this_year->modify( '+1 year' );

		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Process daily reminders (cron job)
	 *
	 * @deprecated Use process_user_reminders() with per-user cron jobs instead.
	 *             This method is kept for backward compatibility and will reschedule
	 *             all user cron jobs when called.
	 */
	public function process_daily_reminders() {
		// Legacy method - reschedule all user cron jobs for per-user timing
		$this->schedule_all_user_reminders();

		// Also run work history update
		$this->update_expired_work_history();
	}

	/**
	 * Get weekly digest for a user (today, tomorrow, rest of week)
	 *
	 * @param int $user_id User ID
	 * @return array Digest data with today, tomorrow, rest_of_week, and todos keys
	 */
	public function get_weekly_digest( $user_id ) {
		$access_control = new \STADION_Access_Control();
		$today          = new \DateTime( 'today', wp_timezone() );
		$tomorrow       = ( clone $today )->modify( '+1 day' );
		$end_of_week    = ( clone $today )->modify( '+7 days' );

		// Use direct database query to bypass access control filters
		// This is needed for cron jobs that run without a logged-in user
		global $wpdb;

		$date_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = %s 
             AND post_status = 'publish'",
				'important_date'
			)
		);

		// Get todos for the user
		$todos = $this->get_user_todos_for_digest( $user_id, $today, $end_of_week );

		if ( empty( $date_ids ) ) {
			return [
				'today'        => [],
				'tomorrow'     => [],
				'rest_of_week' => [],
				'todos'        => $todos,
			];
		}

		// Get full post objects
		$dates = array_map( 'get_post', $date_ids );

		$digest = [
			'today'        => [],
			'tomorrow'     => [],
			'rest_of_week' => [],
			'todos'        => $todos,
		];

		foreach ( $dates as $date_post ) {
			if ( ! $date_post ) {
				continue;
			}
			$date_value   = get_field( 'date_value', $date_post->ID );
			$is_recurring = get_field( 'is_recurring', $date_post->ID );

			if ( ! $date_value ) {
				continue;
			}

			$next_occurrence = $this->calculate_next_occurrence( $date_value, $is_recurring );

			if ( ! $next_occurrence ) {
				continue;
			}

			// Check if date is within the week
			if ( $next_occurrence > $end_of_week ) {
				continue;
			}

			// Get related people and check access
			$related_people  = get_field( 'related_people', $date_post->ID ) ?: [];
			$people_data     = [];
			$user_has_access = false;

			// Skip dates with no related people
			if ( empty( $related_people ) ) {
				continue;
			}

			// Ensure it's an array
			if ( ! is_array( $related_people ) ) {
				$related_people = [ $related_people ];
			}

			foreach ( $related_people as $person ) {
				// Extract person ID - handle different ACF return formats
				$person_id = null;
				if ( is_object( $person ) ) {
					$person_id = $person->ID;
				} elseif ( is_array( $person ) ) {
					$person_id = isset( $person['ID'] ) ? $person['ID'] : ( isset( $person['id'] ) ? $person['id'] : $person );
				} else {
					$person_id = $person;
				}

				if ( ! $person_id ) {
					continue;
				}

				// Only include if user can access this person
				if ( $access_control->user_can_access_post( $person_id, $user_id ) ) {
					$user_has_access = true;
					$people_data[]   = [
						'id'        => $person_id,
						'name'      => html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' ),
						'thumbnail' => get_the_post_thumbnail_url( $person_id, 'thumbnail' ),
					];
				}
			}

			// Skip if user doesn't have access to any related people
			if ( ! $user_has_access || empty( $people_data ) ) {
				continue;
			}

			$date_item = [
				'id'              => $date_post->ID,
				'title'           => html_entity_decode( $date_post->post_title, ENT_QUOTES, 'UTF-8' ),
				'date_value'      => $date_value,
				'next_occurrence' => $next_occurrence->format( 'Y-m-d' ),
				'days_until'      => (int) $today->diff( $next_occurrence )->days,
				'is_recurring'    => (bool) $is_recurring,
				'year_unknown'    => (bool) get_field( 'year_unknown', $date_post->ID ),
				'date_type'       => wp_get_post_terms( $date_post->ID, 'date_type', [ 'fields' => 'names' ] ),
				'related_people'  => $people_data,
			];

			// Categorize by when it occurs
			$occurrence_date = $next_occurrence->format( 'Y-m-d' );
			$today_date      = $today->format( 'Y-m-d' );
			$tomorrow_date   = $tomorrow->format( 'Y-m-d' );

			if ( $occurrence_date === $today_date ) {
				$digest['today'][] = $date_item;
			} elseif ( $occurrence_date === $tomorrow_date ) {
				$digest['tomorrow'][] = $date_item;
			} elseif ( $next_occurrence <= $end_of_week ) {
				$digest['rest_of_week'][] = $date_item;
			}
		}

		// Sort each section by next occurrence (only for date sections)
		foreach ( [ 'today', 'tomorrow', 'rest_of_week' ] as $key ) {
			if ( ! empty( $digest[ $key ] ) ) {
				usort(
					$digest[ $key ],
					function ( $a, $b ) {
						return strcmp( $a['next_occurrence'], $b['next_occurrence'] );
					}
				);
			}
		}

		return $digest;
	}

	/**
	 * Get user todos for digest (today, tomorrow, rest of week)
	 *
	 * @param int $user_id User ID
	 * @param DateTime $today Today's date
	 * @param DateTime $end_of_week End of week date
	 * @return array Todos grouped by today, tomorrow, rest_of_week
	 */
	private function get_user_todos_for_digest( $user_id, $today, $end_of_week ) {
		$access_control = new \STADION_Access_Control();
		$tomorrow       = ( clone $today )->modify( '+1 day' );

		$todos = [
			'today'        => [],
			'tomorrow'     => [],
			'rest_of_week' => [],
		];

		// Get person IDs that the user can access
		global $wpdb;

		$person_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'person' 
             AND post_status = 'publish'
             AND post_author = %d",
				$user_id
			)
		);

		if ( empty( $person_ids ) ) {
			return $todos;
		}

		// Get incomplete todos with due dates in the week
		$todo_comments = get_comments(
			[
				'post__in'   => $person_ids,
				'type'       => 'stadion_todo',
				'status'     => 'approve',
				'meta_query' => [
					'relation' => 'AND',
					[
						'key'     => 'is_completed',
						'value'   => '0',
						'compare' => '=',
					],
					[
						'key'     => 'due_date',
						'value'   => '',
						'compare' => '!=',
					],
				],
			]
		);

		foreach ( $todo_comments as $comment ) {
			$due_date_str = get_comment_meta( $comment->comment_ID, 'due_date', true );

			if ( empty( $due_date_str ) ) {
				continue;
			}

			try {
				$due_date = \DateTime::createFromFormat( 'Y-m-d', $due_date_str, wp_timezone() );

				if ( ! $due_date ) {
					continue;
				}

				// Skip if due date is after end of week
				if ( $due_date > $end_of_week ) {
					continue;
				}

				// Get person info
				$person_post = get_post( $comment->comment_post_ID );
				if ( ! $person_post ) {
					continue;
				}

				$todo_item = [
					'id'               => $comment->comment_ID,
					'content'          => $comment->comment_content,
					'due_date'         => $due_date_str,
					'person_id'        => $comment->comment_post_ID,
					'person_name'      => html_entity_decode( get_the_title( $comment->comment_post_ID ), ENT_QUOTES, 'UTF-8' ),
					'person_thumbnail' => get_the_post_thumbnail_url( $comment->comment_post_ID, 'thumbnail' ),
					'is_overdue'       => $due_date < $today,
				];

				// Categorize by when it's due
				$due_date_formatted = $due_date->format( 'Y-m-d' );
				$today_formatted    = $today->format( 'Y-m-d' );
				$tomorrow_formatted = $tomorrow->format( 'Y-m-d' );

				if ( $due_date_formatted <= $today_formatted ) {
					// Include overdue todos in today
					$todos['today'][] = $todo_item;
				} elseif ( $due_date_formatted === $tomorrow_formatted ) {
					$todos['tomorrow'][] = $todo_item;
				} else {
					$todos['rest_of_week'][] = $todo_item;
				}
			} catch ( Exception $e ) {
				continue;
			}
		}

		// Sort each section by due date
		foreach ( $todos as $key => $items ) {
			usort(
				$todos[ $key ],
				function ( $a, $b ) {
					return strcmp( $a['due_date'], $b['due_date'] );
				}
			);
		}

		return $todos;
	}

	/**
	 * Get all users who should receive reminders
	 * (users who have created people with important dates)
	 *
	 * @return array User IDs
	 */
	public function get_all_users_to_notify() {
		// Use direct database query to bypass access control filters
		// Cron jobs need to see all dates regardless of user
		global $wpdb;

		$date_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = %s 
             AND post_status = 'publish'",
				'important_date'
			)
		);

		if ( empty( $date_ids ) ) {
			return [];
		}

		// Get full post objects
		$dates = array_map( 'get_post', $date_ids );

		$user_ids = [];

		foreach ( $dates as $date_post ) {
			// Get related people using ACF (handles repeater fields correctly)
			$related_people = get_field( 'related_people', $date_post->ID );

			if ( empty( $related_people ) ) {
				continue;
			}

			// Ensure it's an array
			if ( ! is_array( $related_people ) ) {
				$related_people = [ $related_people ];
			}

			// Get user IDs from people post authors
			foreach ( $related_people as $person ) {
				$person_id = is_object( $person ) ? $person->ID : ( is_array( $person ) ? $person['ID'] : $person );

				if ( ! $person_id ) {
					continue;
				}

				$person_post = get_post( $person_id );
				if ( $person_post ) {
					$user_ids[] = (int) $person_post->post_author;
				}
			}
		}

		return array_unique( $user_ids );
	}

	/**
	 * Update work history entries where is_current is true but end_date has passed
	 */
	private function update_expired_work_history() {
		$people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			]
		);

		$today         = new \DateTime( 'today', wp_timezone() );
		$updated_count = 0;

		foreach ( $people as $person ) {
			$work_history = get_field( 'work_history', $person->ID ) ?: [];

			if ( empty( $work_history ) ) {
				continue;
			}

			$needs_update = false;

			foreach ( $work_history as $index => $job ) {
				// Check if job is marked as current but has an end_date that has passed
				if ( ! empty( $job['is_current'] ) && ! empty( $job['end_date'] ) ) {
					$end_date = \DateTime::createFromFormat( 'Y-m-d', $job['end_date'], wp_timezone() );

					if ( $end_date && $end_date < $today ) {
						// End date has passed, mark as not current
						$work_history[ $index ]['is_current'] = false;
						$needs_update                         = true;
					}
				}
			}

			if ( $needs_update ) {
				update_field( 'work_history', $work_history, $person->ID );
				++$updated_count;
			}
		}

		// Log if any updates were made (optional, for debugging)
		if ( $updated_count > 0 ) {
			error_log( sprintf( 'PRM: Updated %d person(s) with expired work history entries', $updated_count ) );
		}
	}


	/**
	 * Get reminders for a specific user
	 */
	public function get_user_reminders( $user_id, $days_ahead = 30 ) {
		$all_reminders = $this->get_upcoming_reminders( $days_ahead );

		// Filter to only include reminders for people this user can access
		$access_control = new \STADION_Access_Control();
		$user_reminders = [];

		foreach ( $all_reminders as $reminder ) {
			foreach ( $reminder['related_people'] as $person ) {
				if ( $access_control->user_can_access_post( $person['id'], $user_id ) ) {
					$user_reminders[] = $reminder;
					break; // Only add once per reminder
				}
			}
		}

		return $user_reminders;
	}
}
