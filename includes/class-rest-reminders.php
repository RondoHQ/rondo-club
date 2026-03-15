<?php
/**
 * Reminders & Anniversaries REST API Endpoints
 *
 * Extracted from class-rest-api.php to keep domain responsibilities separate.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reminders extends Base {
	private const VOLUNTEER_START_DATE_META_KEY = '_rondo_volunteer_start_date';
	private const VOLUNTEER_START_DATE_NONE     = '__none__';

	/**
	 * Default anniversary milestone settings.
	 */
	private const DEFAULT_ANNIVERSARY_MILESTONES = [
		'member'    => [ 5, 10, 15, 20, 25, 40, 50, 60, 75 ],
		'volunteer' => [ 12.5, 25, 40 ],
	];

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'save_post_person', [ $this, 'invalidate_cached_volunteer_start_date' ], 10, 3 );
	}

	/**
	 * Register custom REST routes for reminders and anniversaries.
	 */
	public function register_routes() {
		// Upcoming reminders
		register_rest_route(
			'rondo/v1',
			'/reminders',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_upcoming_reminders' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'days_ahead' => [
						'default'           => 30,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0 && $param <= 365;
						},
					],
				],
			]
		);

		// Trigger reminders manually (admin only)
		register_rest_route(
			'rondo/v1',
			'/reminders/trigger',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'trigger_reminders' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Check cron status (admin only)
		register_rest_route(
			'rondo/v1',
			'/reminders/cron-status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_cron_status' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Reschedule all user reminder cron jobs (admin only)
		register_rest_route(
			'rondo/v1',
			'/reminders/reschedule-cron',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reschedule_all_cron_jobs' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Upcoming anniversaries (jubilarissen)
		register_rest_route(
			'rondo/v1',
			'/anniversaries',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_upcoming_anniversaries' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'days_ahead' => [
						'default'           => 365,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param >= 0 && $param <= 730;
						},
					],
					'days_back'  => [
						'default'           => 0,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param >= 0 && $param <= 730;
						},
					],
					'limit'      => [
						'default'           => 100,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0 && $param <= 500;
						},
					],
				],
			]
		);

		// Anniversary milestone settings
		register_rest_route(
			'rondo/v1',
			'/anniversaries/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_anniversary_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_anniversary_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);
	}

	/**
	 * Get upcoming reminders
	 */
	public function get_upcoming_reminders( $request ) {
		$days_ahead = (int) $request->get_param( 'days_ahead' );

		$reminders_handler = new \Rondo\Collaboration\Reminders();
		$upcoming          = $reminders_handler->get_upcoming_reminders( $days_ahead );

		return rest_ensure_response( $upcoming );
	}

	/**
	 * Get upcoming anniversaries (jubilarissen).
	 */
	public function get_upcoming_anniversaries( $request ) {
		$days_ahead = (int) $request->get_param( 'days_ahead' );
		$days_back  = (int) $request->get_param( 'days_back' );
		$limit      = (int) $request->get_param( 'limit' );
		if ( $days_ahead <= 0 && $days_back <= 0 ) {
			$days_ahead = 365;
		}
		$anniversaries = $this->get_upcoming_anniversaries_data( $days_ahead, $limit, $days_back );

		return rest_ensure_response( $anniversaries );
	}

	/**
	 * Get anniversary milestone settings.
	 */
	public function get_anniversary_settings( $request ) {
		return rest_ensure_response(
			[
				'milestones' => $this->get_anniversary_milestones(),
			]
		);
	}

	/**
	 * Update anniversary milestone settings.
	 */
	public function update_anniversary_settings( $request ) {
		$milestones = $request->get_param( 'milestones' );
		if ( ! is_array( $milestones ) ) {
			return new \WP_Error(
				'invalid_milestones',
				__( 'Milestones must be provided as an object.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$normalized = [
			'member'    => self::DEFAULT_ANNIVERSARY_MILESTONES['member'],
			'volunteer' => self::DEFAULT_ANNIVERSARY_MILESTONES['volunteer'],
		];

		if ( array_key_exists( 'member', $milestones ) ) {
			if ( ! is_array( $milestones['member'] ) ) {
				return new \WP_Error(
					'invalid_member_milestones',
					__( 'Member milestones must be an array of year values.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}
			$normalized['member'] = $this->normalize_anniversary_milestones( $milestones['member'] );
		}

		if ( array_key_exists( 'volunteer', $milestones ) ) {
			if ( ! is_array( $milestones['volunteer'] ) ) {
				return new \WP_Error(
					'invalid_volunteer_milestones',
					__( 'Volunteer milestones must be an array of year values.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}
			$normalized['volunteer'] = $this->normalize_anniversary_milestones( $milestones['volunteer'] );
		}

		update_option( 'rondo_anniversary_milestones', $normalized, false );

		return rest_ensure_response(
			[
				'success'    => true,
				'milestones' => $normalized,
			]
		);
	}

	/**
	 * Manually trigger reminder emails for today (admin only)
	 */
	public function trigger_reminders( $request ) {
		$reminders_handler = new \Rondo\Collaboration\Reminders();

		// Get all users who should receive reminders
		$users_to_notify = $this->get_all_users_to_notify_for_trigger();

		$users_processed    = 0;
		$notifications_sent = 0;

		foreach ( $users_to_notify as $user_id ) {
			// Get weekly digest for this user
			$digest_data = $reminders_handler->get_weekly_digest( $user_id );

			// Send via all enabled channels
			$email_channel = new \Rondo\Notifications\EmailChannel();

			if ( $email_channel->is_enabled_for_user( $user_id ) ) {
				if ( $email_channel->send( $user_id, $digest_data ) ) {
					++$notifications_sent;
				}
			}

			++$users_processed;
		}

		return rest_ensure_response(
			[
				'success'            => true,
				'message'            => sprintf(
					// translators: %1$d is the number of users processed, %2$d is the number of notifications sent.
					__( 'Processed %1$d user(s), sent %2$d notification(s).', 'rondo' ),
					$users_processed,
					$notifications_sent
				),
				'users_processed'    => $users_processed,
				'notifications_sent' => $notifications_sent,
			]
		);
	}

	/**
	 * Get all users who should receive reminders (for trigger endpoint)
	 *
	 * Delegates to the Reminders class which handles birthdate-based notifications.
	 */
	private function get_all_users_to_notify_for_trigger() {
		$reminders = new \Rondo\Collaboration\Reminders();
		return $reminders->get_all_users_to_notify();
	}

	/**
	 * Get cron job status for reminders
	 */
	public function get_cron_status( $request ) {
		$reminders       = new \Rondo\Collaboration\Reminders();
		$users_to_notify = $reminders->get_all_users_to_notify();

		// Count users with scheduled cron jobs
		$scheduled_users = [];
		foreach ( $users_to_notify as $user_id ) {
			$next_run = wp_next_scheduled( 'rondo_user_reminder', [ $user_id ] );
			if ( $next_run !== false ) {
				$user              = get_userdata( $user_id );
				$scheduled_users[] = [
					'user_id'            => $user_id,
					'display_name'       => $user ? $user->display_name : "User $user_id",
					'next_run'           => gmdate( 'Y-m-d H:i:s', $next_run ),
					'next_run_timestamp' => $next_run,
				];
			}
		}

		// Check legacy cron (deprecated).
		$legacy_scheduled = wp_next_scheduled( 'rondo_daily_reminder_check' );

		return rest_ensure_response(
			[
				'total_users'           => count( $users_to_notify ),
				'scheduled_users'       => count( $scheduled_users ),
				'users'                 => $scheduled_users,
				'current_time'          => gmdate( 'Y-m-d H:i:s', time() ),
				'current_timestamp'     => time(),
				'legacy_cron_scheduled' => $legacy_scheduled !== false,
				'legacy_next_run'       => $legacy_scheduled ? gmdate( 'Y-m-d H:i:s', $legacy_scheduled ) : null,
			]
		);
	}

	/**
	 * Reschedule all user reminder cron jobs (admin only)
	 */
	public function reschedule_all_cron_jobs( $request ) {
		$reminders = new \Rondo\Collaboration\Reminders();

		// Reschedule all user cron jobs
		$scheduled_count = $reminders->schedule_all_user_reminders();

		return rest_ensure_response(
			[
				'success'         => true,
				'message'         => sprintf(
					// translators: %d is the number of users whose reminder cron jobs were rescheduled.
					__( 'Successfully rescheduled reminder cron jobs for %d user(s).', 'rondo' ),
					$scheduled_count
				),
				'users_scheduled' => $scheduled_count,
			]
		);
	}

	/**
	 * Get and compute upcoming anniversaries for active members.
	 *
	 * @param int $days_ahead Number of days ahead to include.
	 * @param int $limit      Maximum results.
	 * @param int $days_back  Number of days back to include.
	 * @return array
	 */
	public function get_upcoming_anniversaries_data( int $days_ahead, int $limit, int $days_back = 0 ): array {
		$today        = new \DateTimeImmutable( 'today', wp_timezone() );
		$window_start = $today->modify( '-' . max( 0, $days_back ) . ' days' );
		$cutoff       = $today->modify( '+' . max( 0, $days_ahead ) . ' days' );
		$milestones   = $this->get_anniversary_milestones();

		$people = get_posts(
			[
				'post_type'              => 'person',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			]
		);

		$results       = [];
		$person_ids    = array_map(
			static function ( $person ) {
				return (int) $person->ID;
			},
			$people
		);
		$volunteer_ids = [];
		foreach ( $person_ids as $person_id ) {
			if ( ! empty( get_post_meta( $person_id, 'huidig-vrijwilliger', true ) ) ) {
				$volunteer_ids[] = $person_id;
			}
		}
		$volunteer_start_dates = $this->get_cached_volunteer_start_dates_for_people( $volunteer_ids );

		foreach ( $people as $person ) {
			$person_id = (int) $person->ID;
			if ( ! empty( get_post_meta( $person_id, 'former_member', true ) ) ) {
				continue;
			}
			$person_summary    = $this->format_anniversary_person_summary( $person );
			$member_since      = get_post_meta( $person_id, 'lid-sinds', true );
			$member_start_date = null;
			if ( ! empty( $member_since ) ) {
				$member_start_date = \DateTimeImmutable::createFromFormat( 'Y-m-d', $member_since, wp_timezone() );
			}

			if ( $member_start_date ) {
				foreach ( $milestones['member'] as $milestone_years ) {
					$item = $this->build_anniversary_item( $person, $person_summary, 'member', $milestone_years, $member_start_date, $today, $window_start, $cutoff );
					if ( $item ) {
						$results[] = $item;
					}
				}
			}

			if ( ! empty( get_post_meta( $person_id, 'huidig-vrijwilliger', true ) ) ) {
				$volunteer_start_date = null;
				if ( ! empty( $volunteer_start_dates[ $person_id ] ) ) {
					$volunteer_start_date = \DateTimeImmutable::createFromFormat( 'Y-m-d', $volunteer_start_dates[ $person_id ], wp_timezone() );
				}

				if ( ! $volunteer_start_date ) {
					continue;
				}

				foreach ( $milestones['volunteer'] as $milestone_years ) {
					$item = $this->build_anniversary_item( $person, $person_summary, 'volunteer', $milestone_years, $volunteer_start_date, $today, $window_start, $cutoff );
					if ( $item ) {
						$results[] = $item;
					}
				}
			}
		}

		usort(
			$results,
			static function ( array $a, array $b ): int {
				$date_cmp = strcmp( $a['anniversary_date'], $b['anniversary_date'] );
				if ( $date_cmp !== 0 ) {
					return $date_cmp;
				}
				$years_cmp = $a['milestone_years'] <=> $b['milestone_years'];
				if ( $years_cmp !== 0 ) {
					return $years_cmp;
				}
				return strcasecmp( $a['person']['name'], $b['person']['name'] );
			}
		);

		if ( $limit > 0 ) {
			$results = array_slice( $results, 0, $limit );
		}

		return $results;
	}

	/**
	 * Build one anniversary record when it falls within the requested window.
	 *
	 * @param \WP_Post            $person          Person post object.
	 * @param array               $person_summary  Preformatted person payload.
	 * @param string              $type            Anniversary type: member|volunteer.
	 * @param float               $milestone_years Milestone in years (supports .5).
	 * @param \DateTimeImmutable  $start_date      Start date.
	 * @param \DateTimeImmutable  $today           Window start (inclusive).
	 * @param \DateTimeImmutable  $window_start    Lower bound (inclusive).
	 * @param \DateTimeImmutable  $cutoff          Window end (inclusive).
	 * @return array|null
	 */
	private function build_anniversary_item(
		\WP_Post $person,
		array $person_summary,
		string $type,
		float $milestone_years,
		\DateTimeImmutable $start_date,
		\DateTimeImmutable $today,
		\DateTimeImmutable $window_start,
		\DateTimeImmutable $cutoff
	): ?array {
		$anniversary_date = $this->calculate_anniversary_date( $start_date, $milestone_years );
		if ( ! $anniversary_date || $anniversary_date < $window_start || $anniversary_date > $cutoff ) {
			return null;
		}

		$interval = $today->diff( $anniversary_date );
		$days     = (int) $interval->format( '%a' );
		if ( (int) $interval->invert === 1 ) {
			$days = -$days;
		}
		$label = $this->format_milestone_years( $milestone_years );

		return [
			'id'               => sprintf( '%d-%s-%s', $person->ID, $type, str_replace( '.', '_', (string) $milestone_years ) ),
			'type'             => $type,
			'milestone_years'  => $milestone_years,
			'milestone_label'  => $label . ' jaar',
			'title'            => $type === 'member' ? $label . ' jaar lid' : $label . ' jaar vrijwilliger',
			'anniversary_date' => $anniversary_date->format( 'Y-m-d' ),
			'days_until'       => $days,
			'person'           => $person_summary,
		];
	}

	/**
	 * Format anniversary person summary without expensive ACF calls.
	 *
	 * @param \WP_Post $person Person post object.
	 * @return array
	 */
	private function format_anniversary_person_summary( \WP_Post $person ): array {
		$person_id = (int) $person->ID;

		return [
			'id'                  => $person_id,
			'name'                => $this->sanitize_text( $person->post_title ),
			'first_name'          => $this->sanitize_text( (string) get_post_meta( $person_id, 'first_name', true ) ),
			'last_name'           => $this->sanitize_text( (string) get_post_meta( $person_id, 'last_name', true ) ),
			'thumbnail'           => $this->sanitize_url( get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ),
			'former_member'       => ! empty( get_post_meta( $person_id, 'former_member', true ) ),
			'huidig_vrijwilliger' => ! empty( get_post_meta( $person_id, 'huidig-vrijwilliger', true ) ),
		];
	}

	/**
	 * Calculate anniversary date from a start date and milestone year value.
	 */
	private function calculate_anniversary_date( \DateTimeImmutable $start_date, float $milestone_years ): ?\DateTimeImmutable {
		$whole_years = (int) floor( $milestone_years );
		$fraction    = round( $milestone_years - $whole_years, 2 );

		$date = $start_date->modify( '+' . $whole_years . ' years' );
		if ( $date === false ) {
			return null;
		}

		if ( $fraction === 0.5 ) {
			$date = $date->modify( '+6 months' );
			if ( $date === false ) {
				return null;
			}
		}

		return $date;
	}

	/**
	 * Determine oldest work_history start dates for a set of people in one query.
	 *
	 * @param array<int> $person_ids Person post IDs.
	 * @return array<int,string> Map of person_id => oldest start date (Y-m-d).
	 */
	private function get_oldest_work_history_start_dates_for_people( array $person_ids ): array {
		$person_ids = array_values( array_filter( array_map( 'absint', $person_ids ) ) );
		if ( empty( $person_ids ) ) {
			return [];
		}

		$oldest_by_person = [];
		foreach ( $person_ids as $person_id ) {
			$all_meta = get_post_meta( $person_id );
			if ( empty( $all_meta ) || ! is_array( $all_meta ) ) {
				continue;
			}

			foreach ( $all_meta as $meta_key => $meta_values ) {
				if ( preg_match( '/^work_history_[0-9]+_start_date$/', (string) $meta_key ) !== 1 ) {
					continue;
				}

				foreach ( (array) $meta_values as $meta_value ) {
					$normalized_date = $this->normalize_iso_date_string( (string) $meta_value );
					if ( $normalized_date === null ) {
						continue;
					}

					$existing = $oldest_by_person[ $person_id ] ?? null;
					if ( $existing === null || $normalized_date < $existing ) {
						$oldest_by_person[ $person_id ] = $normalized_date;
					}
				}
			}
		}

		return $oldest_by_person;
	}

	/**
	 * Read cached volunteer start dates and backfill missing values in one pass.
	 *
	 * @param array<int> $person_ids Person post IDs.
	 * @return array<int,string> Map of person_id => volunteer start date (Y-m-d).
	 */
	private function get_cached_volunteer_start_dates_for_people( array $person_ids ): array {
		$person_ids = array_values( array_filter( array_map( 'absint', $person_ids ) ) );
		if ( empty( $person_ids ) ) {
			return [];
		}

		update_meta_cache( 'post', $person_ids );
		$cached  = [];
		$missing = [];

		foreach ( $person_ids as $person_id ) {
			$manual_raw  = trim( (string) get_post_meta( $person_id, 'vrijwilliger-sinds', true ) );
			$manual_date = $this->normalize_iso_date_string( $manual_raw );
			$cached_raw  = trim( (string) get_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, true ) );
			if ( self::VOLUNTEER_START_DATE_NONE === $cached_raw ) {
				if ( $manual_date !== null ) {
					$cached[ $person_id ] = $manual_date;
					update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, $manual_date );
				}
				continue;
			}

			$start_date = $this->normalize_iso_date_string( $cached_raw );
			if ( $start_date !== null ) {
				$best_date = $start_date;
				if ( $manual_date !== null && $manual_date < $best_date ) {
					$best_date = $manual_date;
				}
				$cached[ $person_id ] = $best_date;
				if ( $cached_raw !== $best_date ) {
					update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, $best_date );
				}
				continue;
			}

			if ( $manual_date !== null ) {
				$cached[ $person_id ] = $manual_date;
				update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, $manual_date );
				continue;
			}

			$missing[] = $person_id;
		}

		if ( empty( $missing ) ) {
			return $cached;
		}

		$calculated = $this->get_oldest_work_history_start_dates_for_people( $missing );
		foreach ( $missing as $person_id ) {
			if ( ! empty( $calculated[ $person_id ] ) ) {
				$work_history_date = $calculated[ $person_id ];
				$manual_date       = $this->normalize_iso_date_string( (string) get_post_meta( $person_id, 'vrijwilliger-sinds', true ) );
				$best_date         = $work_history_date;
				if ( $manual_date !== null && $manual_date < $best_date ) {
					$best_date = $manual_date;
				}
				$cached[ $person_id ] = $best_date;
				update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, $best_date );
				continue;
			}

			$manual_date = $this->normalize_iso_date_string( (string) get_post_meta( $person_id, 'vrijwilliger-sinds', true ) );
			if ( $manual_date !== null ) {
				$cached[ $person_id ] = $manual_date;
				update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, $manual_date );
				continue;
			}

			update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, self::VOLUNTEER_START_DATE_NONE );
		}

		return $cached;
	}

	/**
	 * Normalize supported date strings to ISO Y-m-d.
	 *
	 * Supports ACF date formats Y-m-d and Ymd.
	 *
	 * @param string $raw_date Raw date value.
	 * @return string|null
	 */
	private function normalize_iso_date_string( string $raw_date ): ?string {
		$raw_date = trim( $raw_date );
		if ( $raw_date === '' ) {
			return null;
		}

		$date = null;
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
			$date = \DateTimeImmutable::createFromFormat( 'Y-m-d', $raw_date, wp_timezone() );
		} elseif ( preg_match( '/^\d{8}$/', $raw_date ) ) {
			$date = \DateTimeImmutable::createFromFormat( 'Ymd', $raw_date, wp_timezone() );
		}

		if ( ! $date ) {
			return null;
		}

		return $date->format( 'Y-m-d' );
	}

	/**
	 * Invalidate cached volunteer start date when a person record is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post update.
	 * @return void
	 */
	public function invalidate_cached_volunteer_start_date( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );

		if ( $post->post_type !== 'person' || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		delete_post_meta( $post_id, self::VOLUNTEER_START_DATE_META_KEY );
	}

	/**
	 * Normalize milestone list to sorted unique float values.
	 *
	 * @param array $values Raw milestone values.
	 * @return array
	 */
	private function normalize_anniversary_milestones( array $values ): array {
		$normalized = [];

		foreach ( $values as $value ) {
			if ( ! is_numeric( $value ) ) {
				continue;
			}

			$float_value = (float) $value;
			if ( $float_value <= 0 || $float_value > 120 ) {
				continue;
			}

			$fraction = round( $float_value - floor( $float_value ), 2 );
			if ( ! in_array( $fraction, [ 0.0, 0.5 ], true ) ) {
				continue;
			}

			$normalized[] = round( $float_value, 1 );
		}

		$normalized = array_values( array_unique( $normalized ) );
		sort( $normalized, SORT_NUMERIC );

		return $normalized;
	}

	/**
	 * Get anniversary milestone settings merged with defaults.
	 *
	 * @return array
	 */
	private function get_anniversary_milestones(): array {
		$stored = get_option( 'rondo_anniversary_milestones', [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$member    = $this->normalize_anniversary_milestones( is_array( $stored['member'] ?? null ) ? $stored['member'] : self::DEFAULT_ANNIVERSARY_MILESTONES['member'] );
		$volunteer = $this->normalize_anniversary_milestones( is_array( $stored['volunteer'] ?? null ) ? $stored['volunteer'] : self::DEFAULT_ANNIVERSARY_MILESTONES['volunteer'] );

		return [
			'member'    => ! empty( $member ) ? $member : self::DEFAULT_ANNIVERSARY_MILESTONES['member'],
			'volunteer' => ! empty( $volunteer ) ? $volunteer : self::DEFAULT_ANNIVERSARY_MILESTONES['volunteer'],
		];
	}

	/**
	 * Format milestone years for Dutch labels (12.5 -> 12,5).
	 */
	private function format_milestone_years( float $milestone_years ): string {
		$rounded = round( $milestone_years, 1 );
		if ( abs( $rounded - (int) $rounded ) < 0.001 ) {
			return (string) (int) $rounded;
		}
		return str_replace( '.', ',', number_format( $rounded, 1, '.', '' ) );
	}

	/**
	 * Limit upcoming items to $limit while always including all items for today.
	 *
	 * Input arrays are expected to be sorted by date ascending and include
	 * a `days_until` field.
	 *
	 * @param array $items Sorted items with `days_until`.
	 * @param int   $limit Default maximum items to return.
	 * @return array
	 */
	public function limit_items_with_all_today( array $items, int $limit ): array {
		$today_count = 0;
		foreach ( $items as $item ) {
			if ( (int) ( $item['days_until'] ?? -1 ) === 0 ) {
				++$today_count;
			} else {
				break; // Reminders are sorted by date, so no more today entries after this.
			}
		}

		return array_slice( $items, 0, max( $limit, $today_count ) );
	}
}
