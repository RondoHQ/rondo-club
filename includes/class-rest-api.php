<?php
/**
 * Extended REST API Endpoints
 */

namespace Caelis\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_acf_fields' ] );
	}

	/**
	 * Register custom REST routes
	 */
	public function register_routes() {
		// Upcoming reminders
		register_rest_route(
			'prm/v1',
			'/reminders',
			[
				'methods'             => WP_REST_Server::READABLE,
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
			'prm/v1',
			'/reminders/trigger',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'trigger_reminders' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Check cron status (admin only)
		register_rest_route(
			'prm/v1',
			'/reminders/cron-status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_cron_status' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Reschedule all user reminder cron jobs (admin only)
		register_rest_route(
			'prm/v1',
			'/reminders/reschedule-cron',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reschedule_all_cron_jobs' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Get user notification channels
		register_rest_route(
			'prm/v1',
			'/user/notification-channels',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_notification_channels' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user notification channels
		register_rest_route(
			'prm/v1',
			'/user/notification-channels',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_notification_channels' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'channels' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					],
				],
			]
		);

		// Update notification time
		register_rest_route(
			'prm/v1',
			'/user/notification-time',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_notification_time' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'time' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							// Validate HH:MM format
							return preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $param );
						},
					],
				],
			]
		);

		// Update mention notification preference
		register_rest_route(
			'prm/v1',
			'/user/mention-notifications',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_mention_notifications' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'preference' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return in_array( $param, [ 'digest', 'immediate', 'never' ], true );
						},
					],
				],
			]
		);

		// Get user theme preferences
		register_rest_route(
			'prm/v1',
			'/user/theme-preferences',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_theme_preferences' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user theme preferences
		register_rest_route(
			'prm/v1',
			'/user/theme-preferences',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_theme_preferences' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'color_scheme' => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return in_array( $param, [ 'light', 'dark', 'system' ], true );
						},
					],
					'accent_color' => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return in_array( $param, [ 'orange', 'teal', 'indigo', 'emerald', 'violet', 'pink', 'fuchsia', 'rose' ], true );
						},
					],
				],
			]
		);

		// Search across all content
		register_rest_route(
			'prm/v1',
			'/search',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'global_search' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'q' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( $param ) >= 2;
						},
					],
				],
			]
		);

		// Dashboard summary
		register_rest_route(
			'prm/v1',
			'/dashboard',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dashboard_summary' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// Version check (public endpoint for cache invalidation)
		register_rest_route(
			'prm/v1',
			'/version',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_version' ],
				'permission_callback' => '__return_true',
			]
		);

		// Get companies where a person or company is an investor
		register_rest_route(
			'prm/v1',
			'/investments/(?P<investor_id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_investments' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'investor_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Restore default relationship type configurations
		register_rest_route(
			'prm/v1',
			'/relationship-types/restore-defaults',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'restore_relationship_type_defaults' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// Current user info
		// Allow logged-in users (not just approved) so we can check approval status
		register_rest_route(
			'prm/v1',
			'/user/me',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_current_user' ],
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			]
		);

		// User management (admin only)
		register_rest_route(
			'prm/v1',
			'/users',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_users' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			'prm/v1',
			'/users/(?P<user_id>\d+)/approve',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'approve_user' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'user_id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		register_rest_route(
			'prm/v1',
			'/users/(?P<user_id>\d+)/deny',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'deny_user' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'user_id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		register_rest_route(
			'prm/v1',
			'/users/(?P<user_id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_user' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'user_id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// User search (for sharing)
		register_rest_route(
			'prm/v1',
			'/users/search',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'search_users' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'q' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( $param ) >= 2;
						},
					],
				],
			]
		);
	}

	/**
	 * Register ACF fields to REST API
	 */
	public function register_acf_fields() {
		// Expose ACF fields in REST API for taxonomy terms
		add_filter( 'rest_prepare_relationship_type', [ $this, 'add_acf_to_relationship_type' ], 10, 3 );

		// Allow updating ACF fields via REST API
		add_action( 'rest_insert_relationship_type', [ $this, 'update_relationship_type_acf' ], 10, 3 );
	}

	/**
	 * Add ACF fields to relationship_type REST response
	 */
	public function add_acf_to_relationship_type( $response, $term, $request ) {
		$acf_data = get_fields( 'relationship_type_' . $term->term_id );
		if ( $acf_data ) {
			$response->data['acf'] = $acf_data;
		}
		return $response;
	}

	/**
	 * Update ACF fields when relationship_type is updated via REST API
	 */
	public function update_relationship_type_acf( $term, $request, $creating ) {
		$acf_data = $request->get_param( 'acf' );
		if ( is_array( $acf_data ) ) {
			foreach ( $acf_data as $field_name => $value ) {
				update_field( $field_name, $value, 'relationship_type_' . $term->term_id );
			}
		}
	}

	/**
	 * Restore default relationship type configurations
	 */
	public function restore_relationship_type_defaults( $request ) {
		// Get the taxonomies class instance
		$taxonomies = new PRM_Taxonomies();

		// Call the setup method (make it public or add a public wrapper)
		if ( method_exists( $taxonomies, 'setup_default_relationship_configurations' ) ) {
			$taxonomies->setup_default_relationship_configurations();

			return new WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Default relationship type configurations have been restored.', 'caelis' ),
				],
				200
			);
		}

		return new WP_Error(
			'restore_failed',
			__( 'Failed to restore defaults.', 'caelis' ),
			[ 'status' => 500 ]
		);
	}
	/**
	 * Get upcoming reminders
	 */
	public function get_upcoming_reminders( $request ) {
		$days_ahead = (int) $request->get_param( 'days_ahead' );

		$reminders_handler = new PRM_Reminders();
		$upcoming          = $reminders_handler->get_upcoming_reminders( $days_ahead );

		return rest_ensure_response( $upcoming );
	}

	/**
	 * Manually trigger reminder emails for today (admin only)
	 */
	public function trigger_reminders( $request ) {
		$reminders_handler = new PRM_Reminders();

		// Get all users who should receive reminders
		$users_to_notify = $this->get_all_users_to_notify_for_trigger();

		$users_processed    = 0;
		$notifications_sent = 0;

		foreach ( $users_to_notify as $user_id ) {
			// Get weekly digest for this user
			$digest_data = $reminders_handler->get_weekly_digest( $user_id );

			// Send via all enabled channels
			$email_channel = new PRM_Email_Channel();
			$slack_channel = new PRM_Slack_Channel();

			if ( $email_channel->is_enabled_for_user( $user_id ) ) {
				if ( $email_channel->send( $user_id, $digest_data ) ) {
					++$notifications_sent;
				}
			}

			if ( $slack_channel->is_enabled_for_user( $user_id ) ) {
				if ( $slack_channel->send( $user_id, $digest_data ) ) {
					++$notifications_sent;
				}
			}

			++$users_processed;
		}

		return rest_ensure_response(
			[
				'success'            => true,
				'message'            => sprintf(
					__( 'Processed %1$d user(s), sent %2$d notification(s).', 'caelis' ),
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
	 */
	private function get_all_users_to_notify_for_trigger() {
		// Use direct database query to bypass access control filters
		// Admin trigger endpoint needs to see all dates regardless of user
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
	 * Get cron job status for reminders
	 */
	public function get_cron_status( $request ) {
		$reminders       = new PRM_Reminders();
		$users_to_notify = $reminders->get_all_users_to_notify();

		// Count users with scheduled cron jobs
		$scheduled_users = [];
		foreach ( $users_to_notify as $user_id ) {
			$next_run = wp_next_scheduled( 'prm_user_reminder', [ $user_id ] );
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
		$legacy_scheduled = wp_next_scheduled( 'prm_daily_reminder_check' );

		return rest_ensure_response(
			[
				'total_users'           => count( $users_to_notify ),
				'scheduled_users'       => count( $scheduled_users ),
				'users'                 => $scheduled_users,
				'current_time'          => gmdate( 'Y-m-d H:i:s', time() ),
				'current_timestamp'     => time(),
				'legacy_cron_scheduled' => false !== $legacy_scheduled,
				'legacy_next_run'       => $legacy_scheduled ? gmdate( 'Y-m-d H:i:s', $legacy_scheduled ) : null,
			]
		);
	}

	/**
	 * Reschedule all user reminder cron jobs (admin only)
	 */
	public function reschedule_all_cron_jobs( $request ) {
		$reminders = new PRM_Reminders();

		// Reschedule all user cron jobs
		$scheduled_count = $reminders->schedule_all_user_reminders();

		return rest_ensure_response(
			[
				'success'         => true,
				'message'         => sprintf(
					__( 'Successfully rescheduled reminder cron jobs for %d user(s).', 'caelis' ),
					$scheduled_count
				),
				'users_scheduled' => $scheduled_count,
			]
		);
	}

	/**
	 * Get user's notification channel preferences
	 */
	public function get_notification_channels( $request ) {
		$user_id = get_current_user_id();

		$channels = get_user_meta( $user_id, 'caelis_notification_channels', true );
		if ( ! is_array( $channels ) ) {
			// Default to email only
			$channels = [ 'email' ];
		}

		$slack_webhook = get_user_meta( $user_id, 'caelis_slack_webhook', true );

		$notification_time = get_user_meta( $user_id, 'caelis_notification_time', true );
		if ( empty( $notification_time ) ) {
			// Default to 9:00 AM
			$notification_time = '09:00';
		}

		$mention_notifications = get_user_meta( $user_id, 'caelis_mention_notifications', true );
		if ( empty( $mention_notifications ) ) {
			// Default to digest
			$mention_notifications = 'digest';
		}

		return rest_ensure_response(
			[
				'channels'              => $channels,
				'slack_webhook'         => $slack_webhook ?: '',
				'notification_time'     => $notification_time,
				'mention_notifications' => $mention_notifications,
			]
		);
	}

	/**
	 * Update user's notification channel preferences
	 */
	public function update_notification_channels( $request ) {
		$user_id  = get_current_user_id();
		$channels = $request->get_param( 'channels' );

		// Validate channels
		$valid_channels = [ 'email', 'slack' ];
		$channels       = array_intersect( $channels, $valid_channels );

		// If Slack is enabled, check if webhook is configured
		if ( in_array( 'slack', $channels ) ) {
			$webhook = get_user_meta( $user_id, 'caelis_slack_webhook', true );
			if ( empty( $webhook ) ) {
				return new WP_Error(
					'slack_webhook_required',
					__( 'Slack webhook URL must be configured before enabling Slack notifications.', 'caelis' ),
					[ 'status' => 400 ]
				);
			}
		}

		update_user_meta( $user_id, 'caelis_notification_channels', $channels );

		return rest_ensure_response(
			[
				'success'  => true,
				'channels' => $channels,
			]
		);
	}

	/**
	 * Update user's notification time preference
	 */
	public function update_notification_time( $request ) {
		$user_id = get_current_user_id();
		$time    = $request->get_param( 'time' );

		// Validate time format (HH:MM)
		if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
			return new WP_Error(
				'invalid_time',
				__( 'Invalid time format. Please use HH:MM format (e.g., 09:00).', 'caelis' ),
				[ 'status' => 400 ]
			);
		}

		update_user_meta( $user_id, 'caelis_notification_time', $time );

		// Reschedule user's reminder cron job at the new time
		$reminders       = new PRM_Reminders();
		$schedule_result = $reminders->schedule_user_reminder( $user_id );

		if ( is_wp_error( $schedule_result ) ) {
			return rest_ensure_response(
				[
					'success'           => true,
					'notification_time' => $time,
					'message'           => __( 'Notification time updated, but failed to reschedule cron job.', 'caelis' ),
					'cron_error'        => $schedule_result->get_error_message(),
				]
			);
		}

		return rest_ensure_response(
			[
				'success'           => true,
				'notification_time' => $time,
				'message'           => __( 'Notification time updated and cron job rescheduled successfully.', 'caelis' ),
			]
		);
	}

	/**
	 * Update user's mention notification preference
	 */
	public function update_mention_notifications( $request ) {
		$user_id    = get_current_user_id();
		$preference = sanitize_text_field( $request->get_param( 'preference' ) );

		// Validate the preference value
		$valid_preferences = [ 'digest', 'immediate', 'never' ];
		if ( ! in_array( $preference, $valid_preferences, true ) ) {
			return new WP_Error(
				'invalid_preference',
				__( 'Invalid mention notification preference.', 'caelis' ),
				[ 'status' => 400 ]
			);
		}

		update_user_meta( $user_id, 'caelis_mention_notifications', $preference );

		return rest_ensure_response(
			[
				'success'               => true,
				'mention_notifications' => $preference,
			]
		);
	}

	/**
	 * Get user's theme preferences
	 */
	public function get_theme_preferences( $request ) {
		$user_id = get_current_user_id();

		$color_scheme = get_user_meta( $user_id, 'caelis_color_scheme', true );
		if ( empty( $color_scheme ) ) {
			$color_scheme = 'system';
		}

		$accent_color = get_user_meta( $user_id, 'caelis_accent_color', true );
		if ( empty( $accent_color ) ) {
			$accent_color = 'orange';
		}

		return rest_ensure_response(
			[
				'color_scheme' => $color_scheme,
				'accent_color' => $accent_color,
			]
		);
	}

	/**
	 * Update user's theme preferences
	 */
	public function update_theme_preferences( $request ) {
		$user_id = get_current_user_id();

		// Valid values for validation
		$valid_color_schemes = [ 'light', 'dark', 'system' ];
		$valid_accent_colors = [ 'orange', 'teal', 'indigo', 'emerald', 'violet', 'pink', 'fuchsia', 'rose' ];

		$color_scheme = $request->get_param( 'color_scheme' );
		$accent_color = $request->get_param( 'accent_color' );

		// Update color scheme if provided and valid
		if ( $color_scheme !== null ) {
			if ( ! in_array( $color_scheme, $valid_color_schemes, true ) ) {
				return new WP_Error(
					'invalid_color_scheme',
					__( 'Invalid color scheme. Valid values: light, dark, system.', 'caelis' ),
					[ 'status' => 400 ]
				);
			}
			update_user_meta( $user_id, 'caelis_color_scheme', $color_scheme );
		}

		// Update accent color if provided and valid
		if ( $accent_color !== null ) {
			if ( ! in_array( $accent_color, $valid_accent_colors, true ) ) {
				return new WP_Error(
					'invalid_accent_color',
					__( 'Invalid accent color.', 'caelis' ),
					[ 'status' => 400 ]
				);
			}
			update_user_meta( $user_id, 'caelis_accent_color', $accent_color );
		}

		// Return updated preferences
		$updated_color_scheme = get_user_meta( $user_id, 'caelis_color_scheme', true );
		if ( empty( $updated_color_scheme ) ) {
			$updated_color_scheme = 'system';
		}

		$updated_accent_color = get_user_meta( $user_id, 'caelis_accent_color', true );
		if ( empty( $updated_accent_color ) ) {
			$updated_accent_color = 'orange';
		}

		return rest_ensure_response(
			[
				'color_scheme' => $updated_color_scheme,
				'accent_color' => $updated_accent_color,
			]
		);
	}

	/**
	 * Global search across people, companies, and dates
	 */
	public function global_search( $request ) {
		$query = sanitize_text_field( $request->get_param( 'q' ) );

		$results = [
			'people'    => [],
			'companies' => [],
		];

		// Search people with scoring to prioritize first name matches
		$people_results = [];

		// Query 1: First name matches (highest priority)
		$first_name_matches = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'first_name',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $first_name_matches as $person ) {
			$first_name  = strtolower( get_field( 'first_name', $person->ID ) ?: '' );
			$query_lower = strtolower( $query );

			// Score: exact = 100, starts with = 80, contains = 60
			if ( $first_name === $query_lower ) {
				$score = 100;
			} elseif ( strpos( $first_name, $query_lower ) === 0 ) {
				$score = 80;
			} else {
				$score = 60;
			}

			$people_results[ $person->ID ] = [
				'person' => $person,
				'score'  => $score,
			];
		}

		// Query 2: Last name matches (lower priority)
		$last_name_matches = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'last_name',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $last_name_matches as $person ) {
			if ( ! isset( $people_results[ $person->ID ] ) ) {
				$people_results[ $person->ID ] = [
					'person' => $person,
					'score'  => 40,
				];
			}
		}

		// Query 3: General WordPress search (catches title, content)
		$general_matches = get_posts(
			[
				'post_type'      => 'person',
				's'              => $query,
				'posts_per_page' => 20,
				'post_status'    => 'publish',
			]
		);

		foreach ( $general_matches as $person ) {
			if ( ! isset( $people_results[ $person->ID ] ) ) {
				$people_results[ $person->ID ] = [
					'person' => $person,
					'score'  => 20,
				];
			}
		}

		// Sort by score descending, take top 10
		uasort(
			$people_results,
			function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);

		$people_results = array_slice( $people_results, 0, 10, true );

		foreach ( $people_results as $item ) {
			$results['people'][] = $this->format_person_summary( $item['person'] );
		}

		// Search companies
		$companies = get_posts(
			[
				'post_type'      => 'company',
				's'              => $query,
				'posts_per_page' => 10,
				'post_status'    => 'publish',
			]
		);

		foreach ( $companies as $company ) {
			$results['companies'][] = $this->format_company_summary( $company );
		}

		return rest_ensure_response( $results );
	}

	/**
	 * Get current theme version and build time
	 * Used for cache invalidation on PWA/mobile apps
	 */
	public function get_version( $request ) {
		// Get build time from manifest file modification time
		$build_time    = null;
		$manifest_path = PRM_THEME_DIR . '/dist/.vite/manifest.json';
		if ( file_exists( $manifest_path ) ) {
			$build_time = gmdate( 'c', filemtime( $manifest_path ) );
		} else {
			// Fallback to current time for dev mode.
			$build_time = gmdate( 'c' );
		}

		return rest_ensure_response(
			[
				'version'   => PRM_THEME_VERSION,
				'buildTime' => $build_time,
			]
		);
	}

	/**
	 * Get dashboard summary
	 */
	public function get_dashboard_summary( $request ) {
		$user_id = get_current_user_id();

		// Get accessible post counts (respects access control)
		$access_control = new PRM_Access_Control();

		// For admins, use wp_count_posts for efficiency
		// For regular users, count only their accessible posts
		if ( current_user_can( 'manage_options' ) ) {
			$total_people    = wp_count_posts( 'person' )->publish;
			$total_companies = wp_count_posts( 'company' )->publish;
			$total_dates     = wp_count_posts( 'important_date' )->publish;
		} else {
			$total_people    = count( $access_control->get_accessible_post_ids( 'person', $user_id ) );
			$total_companies = count( $access_control->get_accessible_post_ids( 'company', $user_id ) );
			$total_dates     = count( $access_control->get_accessible_post_ids( 'important_date', $user_id ) );
		}

		// Recent people
		$recent_people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 5,
				'post_status'    => 'publish',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		// Upcoming reminders
		$reminders_handler  = new PRM_Reminders();
		$upcoming_reminders = $reminders_handler->get_upcoming_reminders( 14 );

		// Favorites
		$favorites = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 10,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'   => 'is_favorite',
						'value' => '1',
					],
				],
			]
		);

		// Get open todos count
		$open_todos_count = $this->count_open_todos();

		// Get awaiting todos count
		$awaiting_todos_count = $this->count_awaiting_todos();

		// Recently contacted (people with most recent activities)
		$recently_contacted = $this->get_recently_contacted_people( 5 );

		return rest_ensure_response(
			[
				'stats'              => [
					'total_people'         => $total_people,
					'total_companies'      => $total_companies,
					'total_dates'          => $total_dates,
					'open_todos_count'     => $open_todos_count,
					'awaiting_todos_count' => $awaiting_todos_count,
				],
				'recent_people'      => array_map( [ $this, 'format_person_summary' ], $recent_people ),
				'upcoming_reminders' => array_slice( $upcoming_reminders, 0, 5 ),
				'favorites'          => array_map( [ $this, 'format_person_summary' ], $favorites ),
				'recently_contacted' => $recently_contacted,
			]
		);
	}

	/**
	 * Count open (non-completed) todos for current user
	 *
	 * Uses the prm_todo CPT with access control filtering.
	 * Only counts todos with 'prm_open' status (not awaiting or completed).
	 */
	private function count_open_todos() {
		// Query todos with access control (PRM_Access_Control hooks into WP_Query)
		$todos = get_posts(
			[
				'post_type'      => 'prm_todo',
				'post_status'    => 'prm_open',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		return count( $todos );
	}

	/**
	 * Count awaiting todos for current user
	 *
	 * Uses the prm_todo CPT with access control filtering.
	 * Only counts todos with 'prm_awaiting' status.
	 */
	private function count_awaiting_todos() {
		// Query todos with access control (PRM_Access_Control hooks into WP_Query)
		$todos = get_posts(
			[
				'post_type'      => 'prm_todo',
				'post_status'    => 'prm_awaiting',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		return count( $todos );
	}

	/**
	 * Get people with most recent activities
	 *
	 * @param int $limit Number of people to return
	 * @return array Array of person summaries with last activity info
	 */
	private function get_recently_contacted_people( $limit = 5 ) {
		global $wpdb;

		$user_id           = get_current_user_id();
		$access_control    = new PRM_Access_Control();
		$accessible_people = $access_control->get_accessible_post_ids( 'person', $user_id );

		if ( empty( $accessible_people ) ) {
			return [];
		}

		// Get the most recent activity for each person
		$placeholders = implode( ',', array_fill( 0, count( $accessible_people ), '%d' ) );

		// Query to get people with their most recent activity date
		$query = $wpdb->prepare(
			"SELECT c.comment_post_ID as person_id, MAX(cm.meta_value) as last_activity_date
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'activity_date'
             WHERE c.comment_type = 'prm_activity'
             AND c.comment_approved = '1'
             AND c.comment_post_ID IN ($placeholders)
             GROUP BY c.comment_post_ID
             ORDER BY last_activity_date DESC
             LIMIT %d",
			...array_merge( $accessible_people, [ $limit ] )
		);

		$results = $wpdb->get_results( $query );

		if ( empty( $results ) ) {
			return [];
		}

		$recently_contacted = [];
		foreach ( $results as $row ) {
			$person = get_post( $row->person_id );
			if ( $person && $person->post_status === 'publish' ) {
				$summary                       = $this->format_person_summary( $person );
				$summary['last_activity_date'] = $row->last_activity_date;
				$recently_contacted[]          = $summary;
			}
		}

		return $recently_contacted;
	}
	/**
	 * Get companies where a person or company is listed as an investor
	 */
	public function get_investments( $request ) {
		$investor_id = (int) $request->get_param( 'investor_id' );
		$user_id     = get_current_user_id();

		// Get all companies accessible by this user
		$access_control       = new PRM_Access_Control();
		$accessible_companies = $access_control->get_accessible_post_ids( 'company', $user_id );

		if ( empty( $accessible_companies ) ) {
			return rest_ensure_response( [] );
		}

		// Query companies where this ID appears in the investors field
		$companies = get_posts(
			[
				'post_type'      => 'company',
				'post__in'       => $accessible_companies,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'investors',
						'value'   => sprintf( '"%d"', $investor_id ),
						'compare' => 'LIKE',
					],
				],
			]
		);

		// Also check with serialized format (ACF stores as serialized array)
		$companies_serialized = get_posts(
			[
				'post_type'      => 'company',
				'post__in'       => $accessible_companies,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'investors',
						'value'   => serialize( strval( $investor_id ) ),
						'compare' => 'LIKE',
					],
				],
			]
		);

		// Merge and dedupe
		$all_companies    = array_merge( $companies, $companies_serialized );
		$seen_ids         = [];
		$unique_companies = [];
		foreach ( $all_companies as $company ) {
			if ( ! in_array( $company->ID, $seen_ids ) ) {
				$seen_ids[]         = $company->ID;
				$unique_companies[] = $company;
			}
		}

		// Format response
		$investments = [];
		foreach ( $unique_companies as $company ) {
			$thumbnail_id  = get_post_thumbnail_id( $company->ID );
			$thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

			$investments[] = [
				'id'        => $company->ID,
				'name'      => $this->sanitize_text( $company->post_title ),
				'industry'  => $this->sanitize_text( get_field( 'industry', $company->ID ) ),
				'website'   => $this->sanitize_url( get_field( 'website', $company->ID ) ),
				'thumbnail' => $this->sanitize_url( $thumbnail_url ),
			];
		}

		// Sort alphabetically by name
		usort(
			$investments,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return rest_ensure_response( $investments );
	}

	/**
	 * Get current user information
	 */
	public function get_current_user( $request ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'User is not logged in.', 'caelis' ), [ 'status' => 401 ] );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'User not found.', 'caelis' ), [ 'status' => 404 ] );
		}

		// Get avatar URL
		$avatar_url = get_avatar_url( $user_id, [ 'size' => 96 ] );

		// Check if user is admin
		$is_admin = current_user_can( 'manage_options' );

		// Get profile edit URL
		$profile_url = admin_url( 'profile.php' );

		// Get admin URL
		$admin_url = admin_url();

		// Check approval status
		$is_approved = PRM_User_Roles::is_user_approved( $user_id );

		return rest_ensure_response(
			[
				'id'          => $user_id,
				'name'        => $user->display_name,
				'email'       => $user->user_email,
				'avatar_url'  => $avatar_url,
				'is_admin'    => $is_admin,
				'is_approved' => $is_approved,
				'profile_url' => $profile_url,
				'admin_url'   => $admin_url,
			]
		);
	}

	/**
	 * Get list of users (admin only)
	 */
	public function get_users( $request ) {
		$users = get_users( [ 'role' => PRM_User_Roles::ROLE_NAME ] );

		$user_list = [];
		foreach ( $users as $user ) {
			$user_list[] = [
				'id'          => $user->ID,
				'name'        => $user->display_name,
				'email'       => $user->user_email,
				'is_approved' => PRM_User_Roles::is_user_approved( $user->ID ),
				'registered'  => $user->user_registered,
			];
		}

		return rest_ensure_response( $user_list );
	}

	/**
	 * Approve a user (admin only)
	 */
	public function approve_user( $request ) {
		$user_id    = (int) $request->get_param( 'user_id' );
		$user_roles = new PRM_User_Roles();
		$user_roles->approve_user( $user_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'User approved.', 'caelis' ),
			]
		);
	}

	/**
	 * Deny a user (admin only)
	 */
	public function deny_user( $request ) {
		$user_id    = (int) $request->get_param( 'user_id' );
		$user_roles = new PRM_User_Roles();
		$user_roles->deny_user( $user_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'User denied.', 'caelis' ),
			]
		);
	}

	/**
	 * Delete a user and all their related data (admin only)
	 */
	public function delete_user( $request ) {
		$user_id = (int) $request->get_param( 'user_id' );

		// Prevent deleting yourself
		if ( $user_id === get_current_user_id() ) {
			return new WP_Error(
				'cannot_delete_self',
				__( 'You cannot delete your own account.', 'caelis' ),
				[ 'status' => 400 ]
			);
		}

		// Check if user exists
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'User not found.', 'caelis' ),
				[ 'status' => 404 ]
			);
		}

		// Delete all user's posts (people, organizations, dates)
		$this->delete_user_posts( $user_id );

		// Delete the user
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$result = wp_delete_user( $user_id );

		if ( ! $result ) {
			return new WP_Error(
				'delete_failed',
				__( 'Failed to delete user.', 'caelis' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'User and all related data deleted.', 'caelis' ),
			]
		);
	}

	/**
	 * Delete all posts belonging to a user
	 */
	private function delete_user_posts( $user_id ) {
		$post_types = [ 'person', 'company', 'important_date' ];

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				[
					'post_type'      => $post_type,
					'author'         => $user_id,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				]
			);

			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true ); // Force delete (bypass trash)
			}
		}
	}

	/**
	 * Search users for sharing functionality
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing matched users.
	 */
	public function search_users( $request ) {
		$query = sanitize_text_field( $request->get_param( 'q' ) );

		if ( strlen( $query ) < 2 ) {
			return rest_ensure_response( [] );
		}

		$users = get_users(
			[
				'search'         => '*' . $query . '*',
				'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
				'number'         => 10,
				'exclude'        => [ get_current_user_id() ],
			]
		);

		$result = [];
		foreach ( $users as $user ) {
			$result[] = [
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'avatar_url'   => get_avatar_url( $user->ID, [ 'size' => 48 ] ),
			];
		}

		return rest_ensure_response( $result );
	}
}
