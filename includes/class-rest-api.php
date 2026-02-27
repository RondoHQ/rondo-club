<?php
/**
 * Extended REST API Endpoints
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api extends Base {
	private const VOLUNTEER_START_DATE_META_KEY = '_rondo_volunteer_start_date';
	private const VOLUNTEER_START_DATE_NONE     = '__none__';
	private const KADERLIJST_SNAPSHOT_OPTION    = 'rondo_kaderlijst_snapshot';
	private const KADERLIJST_UPDATED_OPTION     = 'rondo_kaderlijst_snapshot_updated_at';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_acf_fields' ] );
		add_action( 'save_post_person', [ $this, 'invalidate_cached_volunteer_start_date' ], 10, 3 );
	}

	/**
	 * Register custom REST routes
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
					'limit'     => [
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

		// Get user notification channels
		register_rest_route(
			'rondo/v1',
			'/user/notification-channels',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_notification_channels' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user notification channels
		register_rest_route(
			'rondo/v1',
			'/user/notification-channels',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
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
			'rondo/v1',
			'/user/notification-time',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
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
			'rondo/v1',
			'/user/mention-notifications',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
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

		// Get user dashboard settings
		register_rest_route(
			'rondo/v1',
			'/user/dashboard-settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dashboard_settings' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user dashboard settings
		register_rest_route(
			'rondo/v1',
			'/user/dashboard-settings',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_dashboard_settings' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'visible_cards' => [
						'required'          => false,
						'validate_callback' => [ $this, 'validate_dashboard_cards' ],
					],
					'card_order'    => [
						'required'          => false,
						'validate_callback' => [ $this, 'validate_dashboard_cards' ],
					],
				],
			]
		);

		// Get user's people list preferences
		register_rest_route(
			'rondo/v1',
			'/user/list-preferences',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_list_preferences' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user's people list preferences
		register_rest_route(
			'rondo/v1',
			'/user/list-preferences',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_list_preferences' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'visible_columns' => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return $param === null || is_array( $param );
						},
					],
					'column_order'    => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return $param === null || is_array( $param );
						},
					],
					'column_widths'   => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return $param === null || is_object( $param ) || is_array( $param );
						},
					],
					'reset'           => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return is_bool( $param );
						},
					],
				],
			]
		);

		// Get user's linked person ID
		register_rest_route(
			'rondo/v1',
			'/user/linked-person',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_linked_person' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user's linked person ID
		register_rest_route(
			'rondo/v1',
			'/user/linked-person',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_linked_person' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'person_id' => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							// Allow null/0 to unlink, or a valid numeric person ID
							return $param === null || $param === 0 || ( is_numeric( $param ) && $param > 0 );
						},
					],
				],
			]
		);

		// Search across all content
		register_rest_route(
			'rondo/v1',
			'/search',
			[
				'methods'             => \WP_REST_Server::READABLE,
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

		// Find person by email (for sync deduplication)
		// Uses check_authenticated instead of check_user_approved for sync scripts
		register_rest_route(
			'rondo/v1',
			'/people/find-by-email',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'find_person_by_email' ],
				'permission_callback' => function() {
					return is_user_logged_in();
				},
				'args'                => [
					'email' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					],
				],
			]
		);

		// Dashboard summary
		register_rest_route(
			'rondo/v1',
			'/dashboard',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dashboard_summary' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// Kaderlijst snapshot (database-backed)
		register_rest_route(
			'rondo/v1',
			'/kaderlijst/snapshot',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_kaderlijst_snapshot' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_kaderlijst_snapshot' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
					'args'                => [
						'snapshot' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param ) && isset( $param['teams'], $param['rows'] ) && is_array( $param['teams'] ) && is_array( $param['rows'] );
							},
						],
					],
				],
			]
		);

		// Version check (public endpoint for cache invalidation)
		register_rest_route(
			'rondo/v1',
			'/version',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_version' ],
				'permission_callback' => '__return_true',
			]
		);

		// Get teams where a person or company is an investor
		register_rest_route(
			'rondo/v1',
			'/investments/(?P<investor_id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
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
			'rondo/v1',
			'/relationship-types/restore-defaults',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'restore_relationship_type_defaults' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// Current user info
		// Allow all logged-in users
		register_rest_route(
			'rondo/v1',
			'/user/me',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_current_user' ],
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			]
		);

		register_rest_route(
			'rondo/v1',
			'/user/password',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'change_password' ],
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => [
					'current_password' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => function ( $v ) {
							return $v;
						},
					],
					'new_password'     => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => function ( $v ) {
							return $v;
						},
					],
				],
			]
		);

		// User management (admin only)
		register_rest_route(
			'rondo/v1',
			'/users',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_users' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/users/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
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

		// Provisionable users — people with email but no WP account (admin only)
		register_rest_route(
			'rondo/v1',
			'/users/provisionable',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_provisionable_users' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// User search (for sharing)
		register_rest_route(
			'rondo/v1',
			'/users/search',
			[
				'methods'             => \WP_REST_Server::READABLE,
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

		// Get entity (team or commissie) by ID - unified lookup
		register_rest_route(
			'rondo/v1',
			'/entity/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_entity_by_id' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					],
				],
			]
		);

		// VOG settings (admin only)
		register_rest_route(
			'rondo/v1',
			'/vog/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_vog_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_vog_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'from_email'       => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return empty( $param ) || is_email( $param );
							},
						],
						'from_name'        => [
							'required' => false,
						],
						'template_new'     => [
							'required' => false,
						],
						'template_renewal' => [
							'required' => false,
						],
						'reminder_template_new' => [
							'required' => false,
						],
						'reminder_template_renewal' => [
							'required' => false,
						],
						'exempt_commissies' => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
						'exempt_discipline_teams' => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Bulk send VOG emails
		register_rest_route(
			'rondo/v1',
			'/vog/bulk-send',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_send_vog_emails' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'ids' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					],
				],
			]
		);

		// Bulk mark VOG as submitted to Justis
		register_rest_route(
			'rondo/v1',
			'/vog/bulk-mark-justis',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_mark_vog_justis' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'ids' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					],
				],
			]
		);

		// Bulk send VOG reminder emails
		register_rest_route(
			'rondo/v1',
			'/vog/bulk-send-reminder',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_send_vog_reminders' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'ids' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					],
				],
			]
		);

		// Membership fee settings (admin only)
		register_rest_route(
			'rondo/v1',
			'/membership-fees/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_membership_fee_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_membership_fee_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'season'     => [
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => function ( $param, $request, $key ) {
								$membership_fees = new \Rondo\Fees\MembershipFees();
								$valid           = [ $membership_fees->get_season_key(), $membership_fees->get_next_season_key() ];
								return in_array( $param, $valid, true );
							},
						],
						'categories' => [
							'required' => true,
							'type'     => 'object',
						],
					],
				],
			]
		);

		// Copy season categories (admin only)
		register_rest_route(
			'rondo/v1',
			'/membership-fees/copy-season',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'copy_season_categories' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'from_season' => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
					'to_season'   => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Get membership fee list
		register_rest_route(
			'rondo/v1',
			'/fees',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fee_list' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'season'   => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
					'forecast' => [
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
						'validate_callback' => 'rest_is_boolean',
						'description'       => 'Calculate forecast for next season with 100% pro-rata',
					],
				],
			]
		);

		// Get fee summary (aggregated by category — lightweight for overview tab)
		register_rest_route(
			'rondo/v1',
			'/fees/summary',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fee_summary' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'season'   => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
					'forecast' => [
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
						'validate_callback' => 'rest_is_boolean',
					],
				],
			]
		);

		// Get single person fee data
		register_rest_route(
			'rondo/v1',
			'/fees/person/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_person_fee' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'id'     => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					],
					'season' => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Bulk recalculate fees endpoint
		register_rest_route(
			'rondo/v1',
			'/fees/recalculate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'recalculate_all_fees' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'season' => [
						'default'           => null,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Billing settings (GET/POST) — admin only
		register_rest_route(
			'rondo/v1',
			'/fees/billing-settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_billing_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'season' => [
							'default'           => null,
							'validate_callback' => function ( $param ) {
								return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
							},
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_billing_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'season'                      => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return preg_match( '/^\d{4}-\d{4}$/', $param );
							},
						],
						'billing_method'              => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return in_array( $param, [ 'nikki', 'rondo' ], true );
							},
						],
						'installment_plan_3_enabled'  => [
							'required'          => false,
							'type'              => 'boolean',
						],
						'installment_plan_8_enabled'  => [
							'required'          => false,
							'type'              => 'boolean',
						],
						'installment_admin_fee'      => [
							'required'          => false,
							'type'              => 'number',
						],
					],
				],
			]
		);

		// Bulk invoice creation — start job (admin only)
		register_rest_route(
			'rondo/v1',
			'/fees/bulk-create-invoices',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'start_bulk_invoice_job' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'season' => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Bulk invoice job progress (admin only)
		register_rest_route(
			'rondo/v1',
			'/fees/bulk-invoice-job',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_bulk_invoice_job_status' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Single-member invoice creation (admin only)
		register_rest_route(
			'rondo/v1',
			'/fees/create-membership-invoice',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_single_membership_invoice' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					],
					'season'    => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Get current season term
		register_rest_route(
			'rondo/v1',
			'/current-season',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_current_season' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// Club configuration (admin write, all-users read)
		register_rest_route(
			'rondo/v1',
			'/config',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_club_config' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_club_config' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'club_name'     => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'freescout_url' => [
							'required'          => false,
							'sanitize_callback' => 'esc_url_raw',
						],
						'freescout_api_key' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_api_token' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_team_api_token' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_project_id' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_route_id' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_from_email' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => function ( $param ) {
								return $param === null || $param === '' || is_email( $param );
							},
						],
						'lettermint_from_name' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_webhook_secret' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Lettermint webhook provisioning (admin only)
		register_rest_route(
			'rondo/v1',
			'/lettermint/webhook/create',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_lettermint_webhook' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'project_id' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'route_id' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Lettermint projects and default routes (admin only)
		register_rest_route(
			'rondo/v1',
			'/lettermint/projects',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_lettermint_projects' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Lettermint test email (admin only)
		register_rest_route(
			'rondo/v1',
			'/lettermint/test-email',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_lettermint_test_email' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'recipient' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return $param === null || $param === '' || is_email( $param );
						},
					],
				],
			]
		);

		// Finance settings (financieel capability required)
		register_rest_route(
			'rondo/v1',
			'/finance/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_finance_settings' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_finance_settings' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'org_name'              => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'org_address'           => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
						'contact_email'         => [ 'required' => false, 'sanitize_callback' => 'sanitize_email' ],
						'iban'                  => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'payment_term_days'     => [ 'required' => false, 'type' => 'integer' ],
						'payment_clause'        => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
						'membership_payment_clause' => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
						'email_template'             => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'membership_email_template'  => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'installment_email_template' => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'reminder_1_email_template'  => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'reminder_2_email_template'  => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'regular_invoice_email_subject' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'regular_invoice_email_body'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
						'rabobank_client_id'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'rabobank_client_secret' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'rabobank_environment'  => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'mollie_api_key'          => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'mollie_redirect_url'     => [ 'required' => false, 'sanitize_callback' => 'esc_url_raw' ],
						'active_payment_provider' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function( $param ) {
								return in_array( $param, [ 'rabobank', 'mollie' ], true );
							},
						],
						'club_logo_id'  => [ 'required' => false, 'type' => 'integer' ],
						'accent_color'  => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'accent_background_color' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'bcc_email'     => [ 'required' => false, 'sanitize_callback' => 'sanitize_email' ],
						'admin_fee'              => [ 'required' => false, 'type' => 'number' ],
						'installment_admin_fee'  => [ 'required' => false, 'type' => 'number' ],
						'membership_pass_apple_cert_attachment_id' => [ 'required' => false, 'type' => 'integer' ],
						'membership_pass_apple_cert_password'      => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'membership_pass_apple_pass_type_identifier' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'membership_pass_apple_team_identifier'      => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'membership_pass_apple_organization_name'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'membership_pass_google_service_account_attachment_id' => [ 'required' => false, 'type' => 'integer' ],
						'membership_pass_google_issuer_id'                     => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'membership_pass_google_class_suffix'                  => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
					],
				],
			]
		);

		// Finance branding settings (admin only)
		register_rest_route(
			'rondo/v1',
			'/finance/branding',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_finance_branding' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_finance_branding' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
						'args'                => [
							'club_logo_id' => [ 'required' => false, 'type' => 'integer' ],
							'accent_color' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
							'accent_background_color' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						],
					],
				]
		);

		// Volunteer role classification - available roles (admin only)
		register_rest_route(
			'rondo/v1',
			'/volunteer-roles/available',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_available_volunteer_roles' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Volunteer role classification settings (read: all users, write: admin)
		register_rest_route(
			'rondo/v1',
			'/volunteer-roles/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_volunteer_role_settings' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_volunteer_role_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'player_roles'   => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
							'sanitize_callback' => function ( $param ) {
								return array_values( array_unique( array_map( 'sanitize_text_field', $param ) ) );
							},
						],
						'excluded_roles' => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
							'sanitize_callback' => function ( $param ) {
								return array_values( array_unique( array_map( 'sanitize_text_field', $param ) ) );
							},
						],
					],
				],
			]
		);

		// Sportlink individual sync (admin only)
		register_rest_route(
			'rondo/v1',
			'/sportlink/sync-individual',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_individual_from_sportlink' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'knvb_id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Werkfuncties - available werkfuncties from database (admin only)
		register_rest_route(
			'rondo/v1',
			'/werkfuncties/available',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_available_werkfuncties' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Functie-to-capability mapping (admin only for both read and write)
		register_rest_route(
			'rondo/v1',
			'/functie-capability-map',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_functie_capability_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_functie_capability_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'map' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Commissie-to-capability mapping (admin only for both read and write)
		register_rest_route(
			'rondo/v1',
			'/commissie-capability-map',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_commissie_capability_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_commissie_capability_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'map' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// User provisioning (admin only)
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/provision',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'provision_user' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'person_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Provisioning email template settings (admin only)
		register_rest_route(
			'rondo/v1',
			'/provisioning/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_provisioning_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_provisioning_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		// Capability sync (admin only — called by rondo-sync per member)
		register_rest_route(
			'rondo/v1',
			'/capability-sync',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_user_capabilities' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'knvb_id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					],
					'functies' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					],
				],
			]
		);

		// Capability sync all (admin only — on-demand from Settings)
		register_rest_route(
			'rondo/v1',
			'/capability-sync/all',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_all_capabilities' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		// Capability sync for a single person (admin only — on-demand from AccountCard)
		register_rest_route(
			'rondo/v1',
			'/people/(?P<id>[\d]+)/capability-sync',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_person_capabilities' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
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

		// Add VOG post meta fields to person REST API response
		add_filter( 'rest_prepare_person', [ $this, 'add_vog_fields_to_person' ], 10, 3 );

		// Add computed discipline case charging exception status based on settings.
		add_filter( 'rest_prepare_discipline_case', [ $this, 'add_discipline_case_exception_status' ], 10, 3 );
	}

	/**
	 * Add VOG-related post meta fields to person REST API response
	 *
	 * These fields are stored as post meta (not ACF fields) and need to be
	 * exposed in the REST API for the VOG status card on the person detail page.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response Modified response with VOG fields.
	 */
	public function add_vog_fields_to_person( $response, $post, $request ) {
		// Bail early if response is an error (e.g., post is trashed)
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();

		// Ensure acf array exists
		if ( ! isset( $data['acf'] ) ) {
			$data['acf'] = [];
		}

		// Add VOG email sent date from post meta
		$vog_email_sent = get_post_meta( $post->ID, 'vog_email_sent_date', true );
		if ( $vog_email_sent ) {
			$data['acf']['vog_email_sent_date'] = $vog_email_sent;
		}

		// Add VOG Justis submitted date from post meta
		$vog_justis = get_post_meta( $post->ID, 'vog_justis_submitted_date', true );
		if ( $vog_justis ) {
			$data['acf']['vog_justis_submitted_date'] = $vog_justis;
		}

		// Add VOG reminder sent date from post meta
		$vog_reminder = get_post_meta( $post->ID, 'vog_reminder_sent_date', true );
		if ( $vog_reminder ) {
			$data['acf']['vog_reminder_sent_date'] = $vog_reminder;
		}

		$response->set_data( $data );
		return $response;
	}

	/**
	 * Add computed discipline case charging exception status to REST response.
	 *
	 * Cases belonging to configured exempt teams are exposed with is_charged = 'exception'
	 * so frontend can display "Uitzondering" without mutating stored ACF values.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response Modified response.
	 */
	public function add_discipline_case_exception_status( $response, $post, $request ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$vog_email     = new \Rondo\VOG\VOGEmail();
		$exempt_teams  = $vog_email->get_exempt_discipline_teams();
		if ( empty( $exempt_teams ) ) {
			return $response;
		}

		if ( ! $this->is_discipline_case_charging_exception( $post->ID, $exempt_teams ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! isset( $data['acf'] ) || ! is_array( $data['acf'] ) ) {
			$data['acf'] = [];
		}

		$data['acf']['is_charged'] = 'exception';
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Check whether a discipline case matches exempt charging teams.
	 *
	 * @param int   $case_id       Discipline case post ID.
	 * @param array $exempt_teams  Exempt team IDs.
	 * @return bool True when case should be treated as exception.
	 */
	private function is_discipline_case_charging_exception( int $case_id, array $exempt_teams ): bool {
		$team_id = $this->get_discipline_case_team_id( $case_id );
		if ( $team_id && in_array( $team_id, $exempt_teams, true ) ) {
			return true;
		}

		// Fallback: match by team_name text when home/away team IDs are missing.
		$team_name = get_field( 'team_name', $case_id );
		if ( ! is_string( $team_name ) || '' === trim( $team_name ) ) {
			return false;
		}
		$team_name = trim( wp_strip_all_tags( $team_name ) );

		foreach ( $exempt_teams as $exempt_team_id ) {
			$title = get_the_title( (int) $exempt_team_id );
			if ( ! is_string( $title ) || '' === $title ) {
				continue;
			}
			if ( 0 === strcasecmp( trim( $title ), $team_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve team ID for a discipline case.
	 *
	 * @param int $case_id Discipline case post ID.
	 * @return int|null Team post ID or null.
	 */
	private function get_discipline_case_team_id( int $case_id ): ?int {
		$home_team = get_field( 'home_team', $case_id );
		$away_team = get_field( 'away_team', $case_id );

		$home_id = is_numeric( $home_team ) ? (int) $home_team : 0;
		$away_id = is_numeric( $away_team ) ? (int) $away_team : 0;

		if ( $home_id > 0 ) {
			return $home_id;
		}
		if ( $away_id > 0 ) {
			return $away_id;
		}

		return null;
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
		$taxonomies = new \Rondo\Core\Taxonomies();

		// Call the setup method
		if ( method_exists( $taxonomies, 'setup_default_relationship_configurations' ) ) {
			$taxonomies->setup_default_relationship_configurations();

			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Default relationship type configurations have been restored.', 'rondo' ),
				],
				200
			);
		}

		return new \WP_Error(
			'restore_failed',
			__( 'Failed to restore defaults.', 'rondo' ),
			[ 'status' => 500 ]
		);
	}
	/**
	 * Get upcoming reminders
	 */
	public function get_upcoming_reminders( $request ) {
		$days_ahead = (int) $request->get_param( 'days_ahead' );

		$reminders_handler = new \RONDO_Reminders();
		$upcoming          = $reminders_handler->get_upcoming_reminders( $days_ahead );

		return rest_ensure_response( $upcoming );
	}

	/**
	 * Get upcoming anniversaries (jubilarissen).
	 */
	public function get_upcoming_anniversaries( $request ) {
		$days_ahead    = (int) $request->get_param( 'days_ahead' );
		$days_back     = (int) $request->get_param( 'days_back' );
		$limit         = (int) $request->get_param( 'limit' );
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
	 * Get and compute upcoming anniversaries for active members.
	 *
	 * @param int $days_ahead Number of days ahead to include.
	 * @param int $limit      Maximum results.
	 * @return array
	 */
	private function get_upcoming_anniversaries_data( int $days_ahead, int $limit, int $days_back = 0 ): array {
		$today      = new \DateTimeImmutable( 'today', wp_timezone() );
		$window_start = $today->modify( '-' . max( 0, $days_back ) . ' days' );
		$cutoff       = $today->modify( '+' . max( 0, $days_ahead ) . ' days' );
		$milestones = $this->get_anniversary_milestones();

		$people = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			]
		);

		$results = [];
		$person_ids = array_map(
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
			$person_id    = (int) $person->ID;
			if ( ! empty( get_post_meta( $person_id, 'former_member', true ) ) ) {
				continue;
			}
			$person_summary = $this->format_anniversary_person_summary( $person );
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
				if ( 0 !== $date_cmp ) {
					return $date_cmp;
				}
				$years_cmp = $a['milestone_years'] <=> $b['milestone_years'];
				if ( 0 !== $years_cmp ) {
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
		if ( 1 === (int) $interval->invert ) {
			$days = -$days;
		}
		$label    = $this->format_milestone_years( $milestone_years );

		return [
			'id'               => sprintf( '%d-%s-%s', $person->ID, $type, str_replace( '.', '_', (string) $milestone_years ) ),
			'type'             => $type,
			'milestone_years'  => $milestone_years,
			'milestone_label'  => $label . ' jaar',
			'title'            => 'member' === $type ? $label . ' jaar lid' : $label . ' jaar vrijwilliger',
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
			'id'                 => $person_id,
			'name'               => $this->sanitize_text( $person->post_title ),
			'first_name'         => $this->sanitize_text( (string) get_post_meta( $person_id, 'first_name', true ) ),
			'last_name'          => $this->sanitize_text( (string) get_post_meta( $person_id, 'last_name', true ) ),
			'thumbnail'          => $this->sanitize_url( get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ),
			'former_member'      => ! empty( get_post_meta( $person_id, 'former_member', true ) ),
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
		if ( false === $date ) {
			return null;
		}

		if ( 0.5 === $fraction ) {
			$date = $date->modify( '+6 months' );
			if ( false === $date ) {
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
				if ( 1 !== preg_match( '/^work_history_[0-9]+_start_date$/', (string) $meta_key ) ) {
					continue;
				}

				foreach ( (array) $meta_values as $meta_value ) {
					$normalized_date = $this->normalize_iso_date_string( (string) $meta_value );
					if ( null === $normalized_date ) {
						continue;
					}

					$existing = $oldest_by_person[ $person_id ] ?? null;
					if ( null === $existing || $normalized_date < $existing ) {
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
			$cached_raw = trim( (string) get_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, true ) );
			if ( self::VOLUNTEER_START_DATE_NONE === $cached_raw ) {
				if ( null !== $manual_date ) {
					$cached[ $person_id ] = $manual_date;
					update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, $manual_date );
				}
				continue;
			}

			$start_date = $this->normalize_iso_date_string( $cached_raw );
			if ( null !== $start_date ) {
				$best_date = $start_date;
				if ( null !== $manual_date && $manual_date < $best_date ) {
					$best_date = $manual_date;
				}
				$cached[ $person_id ] = $best_date;
				if ( $cached_raw !== $best_date ) {
					update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, $best_date );
				}
				continue;
			}

			if ( null !== $manual_date ) {
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
				if ( null !== $manual_date && $manual_date < $best_date ) {
					$best_date = $manual_date;
				}
				$cached[ $person_id ] = $best_date;
				update_post_meta( $person_id, self::VOLUNTEER_START_DATE_META_KEY, $best_date );
				continue;
			}

			$manual_date = $this->normalize_iso_date_string( (string) get_post_meta( $person_id, 'vrijwilliger-sinds', true ) );
			if ( null !== $manual_date ) {
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
		if ( '' === $raw_date ) {
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

		if ( 'person' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
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
	 * Manually trigger reminder emails for today (admin only)
	 */
	public function trigger_reminders( $request ) {
		$reminders_handler = new \RONDO_Reminders();

		// Get all users who should receive reminders
		$users_to_notify = $this->get_all_users_to_notify_for_trigger();

		$users_processed    = 0;
		$notifications_sent = 0;

		foreach ( $users_to_notify as $user_id ) {
			// Get weekly digest for this user
			$digest_data = $reminders_handler->get_weekly_digest( $user_id );

			// Send via all enabled channels
			$email_channel = new \RONDO_Email_Channel();

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
		$reminders = new \RONDO_Reminders();
		return $reminders->get_all_users_to_notify();
	}

	/**
	 * Get cron job status for reminders
	 */
	public function get_cron_status( $request ) {
		$reminders       = new \RONDO_Reminders();
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
				'legacy_cron_scheduled' => false !== $legacy_scheduled,
				'legacy_next_run'       => $legacy_scheduled ? gmdate( 'Y-m-d H:i:s', $legacy_scheduled ) : null,
			]
		);
	}

	/**
	 * Reschedule all user reminder cron jobs (admin only)
	 */
	public function reschedule_all_cron_jobs( $request ) {
		$reminders = new \RONDO_Reminders();

		// Reschedule all user cron jobs
		$scheduled_count = $reminders->schedule_all_user_reminders();

		return rest_ensure_response(
			[
				'success'         => true,
				'message'         => sprintf(
					__( 'Successfully rescheduled reminder cron jobs for %d user(s).', 'rondo' ),
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

		$channels = get_user_meta( $user_id, 'rondo_notification_channels', true );
		if ( ! is_array( $channels ) ) {
			// Default to email only
			$channels = [ 'email' ];
		}
		$channels = array_values( array_intersect( $channels, [ 'email' ] ) );
		if ( empty( $channels ) ) {
			$channels = [ 'email' ];
		}

		$notification_time = get_user_meta( $user_id, 'rondo_notification_time', true );
		if ( empty( $notification_time ) ) {
			// Default to 9:00 AM
			$notification_time = '09:00';
		}

		$mention_notifications = get_user_meta( $user_id, 'rondo_mention_notifications', true );
		if ( empty( $mention_notifications ) ) {
			// Default to digest
			$mention_notifications = 'digest';
		}

		return rest_ensure_response(
			[
				'channels'              => $channels,
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
		$valid_channels = [ 'email' ];
		$channels       = array_intersect( $channels, $valid_channels );

		update_user_meta( $user_id, 'rondo_notification_channels', $channels );

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
			return new \WP_Error(
				'invalid_time',
				__( 'Invalid time format. Please use HH:MM format (e.g., 09:00).', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		update_user_meta( $user_id, 'rondo_notification_time', $time );

		// Reschedule user's reminder cron job at the new time
		$reminders       = new \RONDO_Reminders();
		$schedule_result = $reminders->schedule_user_reminder( $user_id );

		if ( is_wp_error( $schedule_result ) ) {
			return rest_ensure_response(
				[
					'success'           => true,
					'notification_time' => $time,
					'message'           => __( 'Notification time updated, but failed to reschedule cron job.', 'rondo' ),
					'cron_error'        => $schedule_result->get_error_message(),
				]
			);
		}

		return rest_ensure_response(
			[
				'success'           => true,
				'notification_time' => $time,
				'message'           => __( 'Notification time updated and cron job rescheduled successfully.', 'rondo' ),
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
			return new \WP_Error(
				'invalid_preference',
				__( 'Invalid mention notification preference.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		update_user_meta( $user_id, 'rondo_mention_notifications', $preference );

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
	/**
	 * Default visible columns for People list.
	 * Name column is always visible and first - not included here.
	 */
	private const DEFAULT_LIST_COLUMNS = [ 'team', 'modified' ];

	/**
	 * Core columns (non-custom-field columns).
	 */
	private const CORE_LIST_COLUMNS = [
		[ 'id' => 'email', 'label' => 'E-mail', 'type' => 'core' ],
		[ 'id' => 'phone', 'label' => 'Telefoon', 'type' => 'core' ],
		[ 'id' => 'team', 'label' => 'Team', 'type' => 'core' ],
		[ 'id' => 'modified', 'label' => 'Laatst gewijzigd', 'type' => 'core' ],
	];

	/**
	 * Sportlink fields (ACF fields from the person field group synced from Sportlink).
	 * These are not user-created custom fields, so they must be explicitly listed here.
	 */
	private const SPORTLINK_FIELDS = [
		[ 'id' => 'knvb-id', 'label' => 'KNVB ID', 'type' => 'text' ],
		[ 'id' => 'type-lid', 'label' => 'Type lid', 'type' => 'text' ],
		[ 'id' => 'leeftijdsgroep', 'label' => 'Leeftijdsgroep', 'type' => 'text' ],
		[ 'id' => 'lid-sinds', 'label' => 'Lid sinds', 'type' => 'date' ],
		[ 'id' => 'vrijwilliger-sinds', 'label' => 'Vrijwilliger sinds', 'type' => 'date' ],
		[ 'id' => 'datum-foto', 'label' => 'Datum foto', 'type' => 'date' ],
		[ 'id' => 'datum-vog', 'label' => 'Datum VOG', 'type' => 'date' ],
		[ 'id' => 'isparent', 'label' => 'Is ouder', 'type' => 'true_false' ],
		[ 'id' => 'huidig-vrijwilliger', 'label' => 'Huidig vrijwilliger', 'type' => 'true_false' ],
		[ 'id' => 'financiele-blokkade', 'label' => 'Financiële blokkade', 'type' => 'true_false' ],
		[ 'id' => 'freescout-id', 'label' => 'FreeScout ID', 'type' => 'number' ],
	];

	/**
	 * Get Sportlink field definitions for use by other classes.
	 *
	 * @return array Sportlink field definitions.
	 */
	public static function get_sportlink_fields(): array {
		return self::SPORTLINK_FIELDS;
	}

	/**
	 * Valid dashboard card IDs
	 */
	private const VALID_DASHBOARD_CARDS = [
		'stats',
		'reminders',
		'anniversaries',
		'todos',
		'awaiting',
		'meetings',
		'recent-contacted',
		'recent-edited',
	];

	/**
	 * Default dashboard card order
	 */
	private const DEFAULT_DASHBOARD_ORDER = [
		'stats',
		'reminders',
		'anniversaries',
		'todos',
		'awaiting',
		'meetings',
		'recent-contacted',
		'recent-edited',
	];

	/**
	 * Default anniversary milestone settings.
	 */
	private const DEFAULT_ANNIVERSARY_MILESTONES = [
		'member'    => [ 5, 10, 15, 20, 25, 40, 50, 60, 75 ],
		'volunteer' => [ 12.5, 25, 40 ],
	];

	/**
	 * Validate dashboard cards array
	 *
	 * @param mixed $param The parameter value.
	 * @return bool
	 */
	public function validate_dashboard_cards( $param ) {
		if ( ! is_array( $param ) ) {
			return false;
		}

		foreach ( $param as $card ) {
			if ( ! in_array( $card, self::VALID_DASHBOARD_CARDS, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get user's dashboard settings
	 *
	 * @return \WP_REST_Response
	 */
	public function get_dashboard_settings() {
		$user_id = get_current_user_id();

		$visible_cards = get_user_meta( $user_id, 'rondo_dashboard_visible_cards', true );
		if ( empty( $visible_cards ) || ! is_array( $visible_cards ) ) {
			$visible_cards = self::DEFAULT_DASHBOARD_ORDER;
		}

		$card_order = get_user_meta( $user_id, 'rondo_dashboard_card_order', true );
		if ( empty( $card_order ) || ! is_array( $card_order ) ) {
			$card_order = self::DEFAULT_DASHBOARD_ORDER;
		}

		return rest_ensure_response(
			[
				'visible_cards' => $visible_cards,
				'card_order'    => $card_order,
			]
		);
	}

	/**
	 * Get persisted kaderlijst snapshot from WordPress options.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_kaderlijst_snapshot( $request ) {
		$snapshot   = get_option( self::KADERLIJST_SNAPSHOT_OPTION, null );
		$updated_at = get_option( self::KADERLIJST_UPDATED_OPTION, null );

		if ( ! is_array( $snapshot ) || ! isset( $snapshot['teams'], $snapshot['rows'] ) ) {
			$snapshot = null;
		}

		return rest_ensure_response(
			[
				'snapshot'   => $snapshot,
				'updated_at' => is_string( $updated_at ) ? $updated_at : null,
			]
		);
	}

	/**
	 * Persist kaderlijst snapshot in WordPress options.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_kaderlijst_snapshot( $request ) {
		$snapshot = $request->get_param( 'snapshot' );
		if ( ! is_array( $snapshot ) || ! isset( $snapshot['teams'], $snapshot['rows'] ) || ! is_array( $snapshot['teams'] ) || ! is_array( $snapshot['rows'] ) ) {
			return new \WP_Error(
				'invalid_snapshot',
				__( 'Invalid kaderlijst snapshot payload.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		update_option( self::KADERLIJST_SNAPSHOT_OPTION, $snapshot, false );
		$updated_at = gmdate( 'c' );
		update_option( self::KADERLIJST_UPDATED_OPTION, $updated_at, false );

		return rest_ensure_response(
			[
				'snapshot'   => $snapshot,
				'updated_at' => $updated_at,
			]
		);
	}

	/**
	 * Update user's dashboard settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_dashboard_settings( $request ) {
		$user_id = get_current_user_id();

		$visible_cards = $request->get_param( 'visible_cards' );
		$card_order    = $request->get_param( 'card_order' );

		// Update visible cards if provided
		if ( $visible_cards !== null ) {
			// Filter to only valid card IDs
			$visible_cards = array_values( array_intersect( $visible_cards, self::VALID_DASHBOARD_CARDS ) );
			update_user_meta( $user_id, 'rondo_dashboard_visible_cards', $visible_cards );
		}

		// Update card order if provided
		if ( $card_order !== null ) {
			// Filter to only valid card IDs and remove duplicates
			$card_order = array_values( array_unique( array_intersect( $card_order, self::VALID_DASHBOARD_CARDS ) ) );
			update_user_meta( $user_id, 'rondo_dashboard_card_order', $card_order );
		}

		// Return updated settings
		$updated_visible = get_user_meta( $user_id, 'rondo_dashboard_visible_cards', true );
		if ( empty( $updated_visible ) || ! is_array( $updated_visible ) ) {
			$updated_visible = self::DEFAULT_DASHBOARD_ORDER;
		}

		$updated_order = get_user_meta( $user_id, 'rondo_dashboard_card_order', true );
		if ( empty( $updated_order ) || ! is_array( $updated_order ) ) {
			$updated_order = self::DEFAULT_DASHBOARD_ORDER;
		}

		return rest_ensure_response(
			[
				'visible_cards' => $updated_visible,
				'card_order'    => $updated_order,
			]
		);
	}

	/**
	 * Get user's people list column preferences
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with visible_columns, column_order, column_widths, and available_columns.
	 */
	public function get_list_preferences( $request ) {
		$user_id = get_current_user_id();

		// Get stored preferences
		$visible_columns = get_user_meta( $user_id, 'rondo_people_list_preferences', true );
		$column_order    = get_user_meta( $user_id, 'rondo_people_list_column_order', true );
		$column_widths   = get_user_meta( $user_id, 'rondo_people_list_column_widths', true );

		// Default visible columns if not set or empty
		if ( empty( $visible_columns ) || ! is_array( $visible_columns ) ) {
			$visible_columns = self::DEFAULT_LIST_COLUMNS;
		}

		// Get available columns for UI rendering
		$available_columns = $this->get_available_columns_metadata();
		$valid_column_ids  = array_column( $available_columns, 'id' );

		// Filter out stale column IDs (e.g. removed features) from stored preferences
		$visible_columns = array_values( array_intersect( $visible_columns, $valid_column_ids ) );

		// If all previously selected columns are now invalid, fall back to defaults
		if ( empty( $visible_columns ) ) {
			$visible_columns = self::DEFAULT_LIST_COLUMNS;
			delete_user_meta( $user_id, 'rondo_people_list_preferences' );
		}

		// Default column order if not set: use available_columns order (excluding name which is always first)
			if ( empty( $column_order ) || ! is_array( $column_order ) ) {
				$column_order = array_column( $available_columns, 'id' );
			} else {
				$column_order = array_values( array_intersect( $column_order, $valid_column_ids ) );
				if ( empty( $column_order ) ) {
					$column_order = array_column( $available_columns, 'id' );
				}
			}

			// Ensure new columns are appended for users with older saved column_order.
			$ordered_set = array_fill_keys( $column_order, true );
			foreach ( $valid_column_ids as $column_id ) {
				if ( ! isset( $ordered_set[ $column_id ] ) ) {
					$column_order[] = $column_id;
				}
			}

		// Default column widths if not set or empty
		if ( empty( $column_widths ) || ! is_array( $column_widths ) ) {
			$column_widths = new \stdClass(); // Empty object for JSON encoding
		}

		return rest_ensure_response(
			[
				'visible_columns'   => $visible_columns,
				'column_order'      => $column_order,
				'column_widths'     => $column_widths,
				'available_columns' => $available_columns,
			]
		);
	}

	/**
	 * Update user's people list column preferences
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with updated preferences.
	 */
	public function update_list_preferences( $request ) {
		$user_id = get_current_user_id();

		// Handle reset action
		if ( $request->get_param( 'reset' ) === true ) {
			delete_user_meta( $user_id, 'rondo_people_list_preferences' );
			delete_user_meta( $user_id, 'rondo_people_list_column_order' );
			delete_user_meta( $user_id, 'rondo_people_list_column_widths' );

			$available_columns = $this->get_available_columns_metadata();

			return rest_ensure_response(
				[
					'visible_columns'   => self::DEFAULT_LIST_COLUMNS,
					'column_order'      => array_column( $available_columns, 'id' ),
					'column_widths'     => new \stdClass(),
					'available_columns' => $available_columns,
					'reset'             => true,
				]
			);
		}

		$available_columns = $this->get_available_columns_metadata();
		$valid_columns     = array_column( $available_columns, 'id' );

		// Handle visible_columns update
		$visible_columns = $request->get_param( 'visible_columns' );
		if ( $visible_columns !== null ) {
			// Empty array = reset to defaults (per CONTEXT.md)
			if ( ! is_array( $visible_columns ) || count( $visible_columns ) === 0 ) {
				delete_user_meta( $user_id, 'rondo_people_list_preferences' );
			} else {
				// Validate columns against available fields
				$validated_columns = array_values( array_intersect( $visible_columns, $valid_columns ) );

				// Log if filtering occurred (deleted fields)
				if ( count( $validated_columns ) !== count( $visible_columns ) ) {
					error_log(
						sprintf(
							'Rondo: Filtered %d invalid column IDs from user %d visible_columns preferences',
							count( $visible_columns ) - count( $validated_columns ),
							$user_id
						)
					);
				}

				// Persist validated preferences
				update_user_meta( $user_id, 'rondo_people_list_preferences', $validated_columns );
			}
		}

		// Handle column_order update
		$column_order = $request->get_param( 'column_order' );
		if ( $column_order !== null ) {
			// Empty array = reset to defaults
			if ( ! is_array( $column_order ) || count( $column_order ) === 0 ) {
				delete_user_meta( $user_id, 'rondo_people_list_column_order' );
			} else {
				// Validate column IDs (silently filter invalid)
				$validated_order = array_values( array_intersect( $column_order, $valid_columns ) );

				// Log if filtering occurred
				if ( count( $validated_order ) !== count( $column_order ) ) {
					error_log(
						sprintf(
							'Rondo: Filtered %d invalid column IDs from user %d column_order preferences',
							count( $column_order ) - count( $validated_order ),
							$user_id
						)
					);
				}

				// Only store if non-empty after validation
				if ( count( $validated_order ) > 0 ) {
					update_user_meta( $user_id, 'rondo_people_list_column_order', $validated_order );
				} else {
					delete_user_meta( $user_id, 'rondo_people_list_column_order' );
				}
			}
		}

		// Handle column_widths update
		$column_widths = $request->get_param( 'column_widths' );
		if ( $column_widths !== null ) {
			// Convert to array if object
			$widths_array = (array) $column_widths;

			// Empty object/array = reset to defaults
			if ( count( $widths_array ) === 0 ) {
				delete_user_meta( $user_id, 'rondo_people_list_column_widths' );
			} else {
				// Validate: filter to valid column IDs and ensure values are positive integers
				$validated_widths = [];
				foreach ( $widths_array as $column_id => $width ) {
					if ( in_array( $column_id, $valid_columns, true ) && is_numeric( $width ) && (int) $width > 0 ) {
						$validated_widths[ $column_id ] = (int) $width;
					}
				}

				// Log if filtering occurred
				if ( count( $validated_widths ) !== count( $widths_array ) ) {
					error_log(
						sprintf(
							'Rondo: Filtered %d invalid entries from user %d column_widths preferences',
							count( $widths_array ) - count( $validated_widths ),
							$user_id
						)
					);
				}

				// Only store if non-empty after validation
				if ( count( $validated_widths ) > 0 ) {
					update_user_meta( $user_id, 'rondo_people_list_column_widths', $validated_widths );
				} else {
					delete_user_meta( $user_id, 'rondo_people_list_column_widths' );
				}
			}
		}

		// Return current state
		$stored_visible  = get_user_meta( $user_id, 'rondo_people_list_preferences', true );
		$stored_order    = get_user_meta( $user_id, 'rondo_people_list_column_order', true );
		$stored_widths   = get_user_meta( $user_id, 'rondo_people_list_column_widths', true );

		// Apply defaults for response
		if ( empty( $stored_visible ) || ! is_array( $stored_visible ) ) {
			$stored_visible = self::DEFAULT_LIST_COLUMNS;
		}
			if ( empty( $stored_order ) || ! is_array( $stored_order ) ) {
				$stored_order = array_column( $available_columns, 'id' );
			} else {
				$valid_column_ids = array_column( $available_columns, 'id' );
				$stored_order     = array_values( array_intersect( $stored_order, $valid_column_ids ) );
				$ordered_set      = array_fill_keys( $stored_order, true );
				foreach ( $valid_column_ids as $column_id ) {
					if ( ! isset( $ordered_set[ $column_id ] ) ) {
						$stored_order[] = $column_id;
					}
				}
			}
		if ( empty( $stored_widths ) || ! is_array( $stored_widths ) ) {
			$stored_widths = new \stdClass();
		}

		return rest_ensure_response(
			[
				'visible_columns'   => $stored_visible,
				'column_order'      => $stored_order,
				'column_widths'     => $stored_widths,
				'available_columns' => $available_columns,
			]
		);
	}

	/**
	 * Get metadata for all available columns
	 *
	 * @return array Column definitions with id, label, type, custom flag
	 */
	private function get_available_columns_metadata(): array {
		$columns = [];

		// Core columns (always available, order matters for UI)
		$columns = array_merge( $columns, self::CORE_LIST_COLUMNS );

		// Sportlink fields (read-only, synced from external system)
		foreach ( self::SPORTLINK_FIELDS as $field ) {
			$columns[] = [
				'id'     => $field['id'],
				'label'  => $field['label'],
				'type'   => $field['type'],
				'custom' => true,
			];
		}

		// Custom fields from ACF
		$manager       = new \Rondo\CustomFields\Manager();
		$custom_fields = $manager->get_fields( 'person', false ); // active only

		foreach ( $custom_fields as $field ) {
			$columns[] = [
				'id'     => $field['name'],
				'label'  => $field['label'],
				'type'   => $field['type'],
				'custom' => true,
			];
		}

		return $columns;
	}

	/**
	 * Get user's linked person ID
	 *
	 * Returns the person record linked to the current user.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_linked_person() {
		$user_id   = get_current_user_id();
		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );

		$response = [
			'person_id' => $person_id ?: null,
		];

		// If linked, include basic person info
		if ( $person_id ) {
			$person = get_post( $person_id );
			if ( $person && $person->post_type === 'person' && $person->post_status === 'publish' ) {
				$first_name = get_field( 'first_name', $person_id ) ?: '';
				$last_name  = get_field( 'last_name', $person_id ) ?: '';
				$thumbnail  = get_the_post_thumbnail_url( $person_id, 'thumbnail' );

				$response['person'] = [
					'id'        => $person_id,
					'name'      => trim( $first_name . ' ' . $last_name ),
					'thumbnail' => $thumbnail ?: null,
				];
			} else {
				// Person no longer exists or is invalid - clear the link
				$response['person_id'] = null;
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Update user's linked person ID
	 *
	 * Links the current user to a person record.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_linked_person( $request ) {
		$user_id   = get_current_user_id();
		$person_id = $request->get_param( 'person_id' );

		// Handle unlinking
		if ( ! $person_id || $person_id === 0 ) {
			// Remove bidirectional link: clear user meta and person post meta.
			$old_person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
			delete_user_meta( $user_id, 'rondo_linked_person_id' );
			if ( $old_person_id ) {
				delete_post_meta( $old_person_id, \Rondo\Users\UserProvisioning::META_USER_ID );
			}
			return rest_ensure_response(
				[
					'success'   => true,
					'person_id' => null,
					'message'   => __( 'Person link removed.', 'rondo' ),
				]
			);
		}

		// Validate that the person exists and belongs to this user
		$person = get_post( (int) $person_id );
		if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return new \WP_Error(
				'invalid_person',
				__( 'Invalid person ID.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Check if the user owns this person record (or is admin)
		if ( $person->post_author != $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'permission_denied',
				__( 'You can only link to your own person records.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		// Save the bidirectional link: user meta AND person post meta.
		// This mirrors what UserProvisioning::provision() does (PROV-03), ensuring
		// that AccountCard can always find the link via _rondo_wp_user_id on the person.
		update_user_meta( $user_id, 'rondo_linked_person_id', (int) $person_id );
		update_post_meta( (int) $person_id, \Rondo\Users\UserProvisioning::META_USER_ID, $user_id );

		$first_name = get_field( 'first_name', $person_id ) ?: '';
		$last_name  = get_field( 'last_name', $person_id ) ?: '';
		$thumbnail  = get_the_post_thumbnail_url( $person_id, 'thumbnail' );

		return rest_ensure_response(
			[
				'success'   => true,
				'person_id' => (int) $person_id,
				'person'    => [
					'id'        => (int) $person_id,
					'name'      => trim( $first_name . ' ' . $last_name ),
					'thumbnail' => $thumbnail ?: null,
				],
				'message'   => __( 'Person linked successfully.', 'rondo' ),
			]
		);
	}

	/**
	 * Find a person by email address (for sync deduplication)
	 *
	 * Searches all people for a matching email in contact_info.
	 * Returns the person ID if found, null otherwise.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with person ID or null.
	 */
	public function find_person_by_email( $request ) {
		$email = strtolower( trim( sanitize_email( $request->get_param( 'email' ) ) ) );

		if ( empty( $email ) ) {
			return new \WP_REST_Response( [ 'id' => null ], 200 );
		}

		// Search all people (bypass access control for sync operations)
		$people = get_posts(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'post_status'      => 'publish',
				'suppress_filters' => true,
			]
		);

		foreach ( $people as $person ) {
			$contact_info = get_field( 'contact_info', $person->ID ) ?: [];

			foreach ( $contact_info as $contact ) {
				if ( 'email' === $contact['contact_type'] ) {
					$person_email = strtolower( trim( $contact['contact_value'] ?? '' ) );
					if ( $person_email === $email ) {
						return new \WP_REST_Response( [ 'id' => $person->ID ], 200 );
					}
				}
			}
		}

		return new \WP_REST_Response( [ 'id' => null ], 200 );
	}

	/**
	 * Global search across people, teams, and dates
	 */
	public function global_search( $request ) {
		$query = sanitize_text_field( $request->get_param( 'q' ) );

		$results = [
			'people'   => [],
			'teams'    => [],
			'invoices' => [],
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

		// Query 2: Infix matches (score: 50)
		$infix_matches = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'infix',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $infix_matches as $person ) {
			if ( ! isset( $people_results[ $person->ID ] ) ) {
				$people_results[ $person->ID ] = [
					'person' => $person,
					'score'  => 50,
				];
			}
		}

		// Query 3: Last name matches (lower priority)
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

		// Query 4: General WordPress search (catches title, content)
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

		// Query 5: Custom field matches (score: 30)
		$custom_field_names = $this->get_searchable_custom_fields( 'person' );
		if ( ! empty( $custom_field_names ) ) {
			$custom_meta_query = $this->build_custom_field_meta_query( $custom_field_names, $query );

			$custom_field_matches = get_posts(
				[
					'post_type'      => 'person',
					'posts_per_page' => 20,
					'post_status'    => 'publish',
					'meta_query'     => $custom_meta_query,
				]
			);

			foreach ( $custom_field_matches as $person ) {
				if ( ! isset( $people_results[ $person->ID ] ) ) {
					$people_results[ $person->ID ] = [
						'person' => $person,
						'score'  => 30,
					];
				}
			}
		}

		// Apply former member penalty to prioritize current members
		foreach ( $people_results as $person_id => &$item ) {
			if ( get_field( 'former_member', $person_id ) ) {
				$item['score'] -= 50;
			}
		}
		unset( $item ); // Break reference

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

		// Search teams with scoring (similar to people)
		$team_results = [];

		// Query 1: Name field matches (highest priority, score: 60)
		$name_matches = get_posts(
			[
				'post_type'      => 'team',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_query'     => [
					[
						'key'     => 'name',
						'value'   => $query,
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $name_matches as $team ) {
			$team_results[ $team->ID ] = [
				'team' => $team,
				'score'   => 60,
			];
		}

		// Query 2: General WordPress search (score: 20)
		$general_company_matches = get_posts(
			[
				'post_type'      => 'team',
				's'              => $query,
				'posts_per_page' => 20,
				'post_status'    => 'publish',
			]
		);

		foreach ( $general_company_matches as $team ) {
			if ( ! isset( $team_results[ $team->ID ] ) ) {
				$team_results[ $team->ID ] = [
					'team' => $team,
					'score'   => 20,
				];
			}
		}

		// Query 3: Custom field matches (score: 30)
		$team_custom_fields = $this->get_searchable_custom_fields( 'team' );
		if ( ! empty( $team_custom_fields ) ) {
			$team_meta_query = $this->build_custom_field_meta_query( $team_custom_fields, $query );

			$team_custom_matches = get_posts(
				[
					'post_type'      => 'team',
					'posts_per_page' => 20,
					'post_status'    => 'publish',
					'meta_query'     => $team_meta_query,
				]
			);

			foreach ( $team_custom_matches as $team ) {
				if ( ! isset( $team_results[ $team->ID ] ) ) {
					$team_results[ $team->ID ] = [
						'team' => $team,
						'score'   => 30,
					];
				}
			}
		}

		// Sort by score descending, take top 10
		uasort(
			$team_results,
			function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);

		$team_results = array_slice( $team_results, 0, 10, true );

		foreach ( $team_results as $item ) {
			$results['teams'][] = $this->format_company_summary( $item['team'] );
		}

		// Search invoices (only for users with financieel capability)
		if ( current_user_can( 'financieel' ) ) {
			$invoice_posts = get_posts(
				[
					'post_type'      => 'rondo_invoice',
					'posts_per_page' => 10,
					'post_status'    => [ 'publish', 'rondo_sent', 'rondo_paid', 'rondo_overdue', 'draft' ],
					'meta_query'     => [
						[
							'key'     => 'invoice_number',
							'value'   => $query,
							'compare' => 'LIKE',
						],
					],
				]
			);

			$invoice_results = [];
			foreach ( $invoice_posts as $invoice ) {
				$invoice_number = get_field( 'invoice_number', $invoice->ID );
				$person_id      = get_field( 'person', $invoice->ID );
				$person_name    = null;

				if ( ! empty( $person_id ) ) {
					$first_name  = get_field( 'first_name', $person_id ) ?: '';
					$infix       = get_field( 'infix', $person_id ) ?: '';
					$last_name   = get_field( 'last_name', $person_id ) ?: '';
					$name_parts  = array_filter( [ $first_name, $infix, $last_name ] );
					$person_name = implode( ' ', $name_parts ) ?: null;
				}

				$invoice_results[] = [
					'id'             => $invoice->ID,
					'invoice_number' => $invoice_number,
					'person_name'    => $person_name,
					'total_amount'   => (float) get_field( 'total_amount', $invoice->ID ),
					'status'         => get_field( 'status', $invoice->ID ),
				];
			}

			// Sort by invoice_number descending (most recent first)
			usort(
				$invoice_results,
				function ( $a, $b ) {
					return strcmp( $b['invoice_number'] ?? '', $a['invoice_number'] ?? '' );
				}
			);

			$results['invoices'] = $invoice_results;
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
		$manifest_path = \RONDO_THEME_DIR . '/dist/.vite/manifest.json';
		if ( file_exists( $manifest_path ) ) {
			$build_time = gmdate( 'c', filemtime( $manifest_path ) );
		} else {
			// Fallback to current time for dev mode.
			$build_time = gmdate( 'c' );
		}

		return rest_ensure_response(
			[
				'version'   => \RONDO_THEME_VERSION,
				'buildTime' => $build_time,
			]
		);
	}

	/**
	 * Get dashboard summary
	 */
	public function get_dashboard_summary( $request ) {
		$user_id = get_current_user_id();

		// Get post counts (all approved users see all data)
		// Access control is already applied via WP_Query filters
		// Exclude former members from people count
		$people_query = new \WP_Query(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => 'former_member',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => 'former_member',
						'value'   => '1',
						'compare' => '!=',
					],
				],
			]
		);
		$total_people     = $people_query->found_posts;
		$total_teams      = wp_count_posts( 'team' )->publish;
		$total_commissies = wp_count_posts( 'commissie' )->publish;

		// Recent people (exclude former members)
		$recent_people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 5,
				'post_status'    => 'publish',
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => 'former_member',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => 'former_member',
						'value'   => '1',
						'compare' => '!=',
					],
				],
			]
		);

		// Upcoming reminders
		$reminders_handler  = new \RONDO_Reminders();
		$upcoming_reminders = $reminders_handler->get_upcoming_reminders( 14 );
		$upcoming_anniversaries = $this->get_upcoming_anniversaries_data( 365, 20 );

		// Get open todos count
		$open_todos_count = $this->count_open_todos();

		// Get awaiting todos count
		$awaiting_todos_count = $this->count_awaiting_todos();

		// Get total volunteers count
		$total_volunteers = $this->count_volunteers();

		// Get open feedback count
		$open_feedback_count = $this->count_open_feedback();

		// Recently contacted (people with most recent activities)
		$recently_contacted = $this->get_recently_contacted_people( 5 );

		return rest_ensure_response(
			[
				'stats'              => [
					'total_people'         => $total_people,
					'total_teams'          => $total_teams,
					'total_commissies'     => $total_commissies,
					'open_todos_count'     => $open_todos_count,
					'awaiting_todos_count' => $awaiting_todos_count,
					'total_volunteers'     => $total_volunteers,
					'open_feedback_count'  => $open_feedback_count,
				],
				'recent_people'      => array_map( [ $this, 'format_person_summary' ], $recent_people ),
				'upcoming_reminders' => $this->limit_items_with_all_today( $upcoming_reminders, 5 ),
				'upcoming_anniversaries' => $this->limit_items_with_all_today( $upcoming_anniversaries, 5 ),
				'recently_contacted' => $recently_contacted,
			]
		);
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
	private function limit_items_with_all_today( array $items, int $limit ): array {
		$today_count = 0;
		foreach ( $items as $item ) {
			if ( 0 === (int) ( $item['days_until'] ?? -1 ) ) {
				$today_count++;
			} else {
				break; // Reminders are sorted by date, so no more today entries after this.
			}
		}

		return array_slice( $items, 0, max( $limit, $today_count ) );
	}

	/**
	 * Count open (non-completed) todos
	 *
	 * Uses prepared SQL query with post_author filter for user isolation.
	 * Only counts todos with 'rondo_open' status (not awaiting or completed).
	 */
	private function count_open_todos() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = %s
			 AND post_status = %s
			 AND post_author = %d",
			'rondo_todo',
			'rondo_open',
			get_current_user_id()
		) );
	}

	/**
	 * Get entity (team or commissie) by ID
	 *
	 * Unified lookup that determines the post type and returns the appropriate data.
	 * Used by frontend to avoid 404 errors when entity type is unknown.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_entity_by_id( $request ) {
		$id = (int) $request->get_param( 'id' );

		$post = get_post( $id );

		if ( ! $post ) {
			return new \WP_Error(
				'not_found',
				'Entity not found',
				[ 'status' => 404 ]
			);
		}

		// Check if it's a team or commissie
		if ( ! in_array( $post->post_type, [ 'team', 'commissie' ], true ) ) {
			return new \WP_Error(
				'invalid_type',
				'Entity is not a team or commissie',
				[ 'status' => 400 ]
			);
		}

		// Build response similar to WP REST API
		$response = [
			'id'           => $post->ID,
			'title'        => [ 'rendered' => get_the_title( $post ) ],
			'slug'         => $post->post_name,
			'status'       => $post->post_status,
			'type'         => $post->post_type,
			'_entity_type' => $post->post_type,
		];

		// Add featured image if available
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
			$full_url      = wp_get_attachment_image_url( $thumbnail_id, 'full' );
			$response['_embedded'] = [
				'wp:featuredmedia' => [
					[
						'source_url'    => $full_url,
						'media_details' => [
							'sizes' => [
								'thumbnail' => [
									'source_url' => $thumbnail_url,
								],
							],
						],
					],
				],
			];
		}

		// Add ACF fields if available
		if ( function_exists( 'get_fields' ) ) {
			$acf_fields = get_fields( $post->ID );
			if ( $acf_fields ) {
				$response['acf'] = $acf_fields;
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Count awaiting todos
	 *
	 * Uses prepared SQL query with post_author filter for user isolation.
	 * Only counts todos with 'rondo_awaiting' status.
	 */
	private function count_awaiting_todos() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = %s
			 AND post_status = %s
			 AND post_author = %d",
			'rondo_todo',
			'rondo_awaiting',
			get_current_user_id()
		) );
	}

	/**
	 * Count current volunteers.
	 *
	 * Counts published person posts with huidig-vrijwilliger meta set to true,
	 * excluding former members.
	 */
	private function count_volunteers() {
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => 'huidig-vrijwilliger',
						'value' => '1',
					],
					[
						'relation' => 'OR',
						[
							'key'     => 'former_member',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => 'former_member',
							'value'   => '1',
							'compare' => '!=',
						],
					],
				],
			]
		);
		return $query->found_posts;
	}

	/**
	 * Count open feedback items.
	 *
	 * Counts published feedback posts with status NOT IN ('resolved', 'declined').
	 * Also includes posts without a status field (which default to 'new').
	 */
	private function count_open_feedback() {
		$query = new \WP_Query(
			[
				'post_type'      => 'rondo_feedback',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => 'status',
						'value'   => [ 'resolved', 'declined' ],
						'compare' => 'NOT IN',
					],
					[
						'key'     => 'status',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);
		return $query->found_posts;
	}

	/**
	 * Get people with most recent activities
	 *
	 * @param int $limit Number of people to return
	 * @return array Array of person summaries with last activity info
	 */
	private function get_recently_contacted_people( $limit = 5 ) {
		global $wpdb;

		// Check if user has access (all approved users see all data)
		if ( ! is_user_logged_in() ) {
			return [];
		}

		// Query to get people with their most recent activity date
		// No post__in filter needed - approved users see all people
		// Exclude former members from recently contacted
		$query = $wpdb->prepare(
			"SELECT c.comment_post_ID as person_id, MAX(cm.meta_value) as last_activity_date
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'activity_date'
             INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
             LEFT JOIN {$wpdb->postmeta} fm ON p.ID = fm.post_id AND fm.meta_key = 'former_member'
             WHERE c.comment_type = 'rondo_activity'
             AND c.comment_approved = '1'
             AND p.post_type = 'person'
             AND p.post_status = 'publish'
             AND (fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0')
             GROUP BY c.comment_post_ID
             ORDER BY last_activity_date DESC
             LIMIT %d",
			$limit
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
	 * Get teams and commissies where a person or company is listed as an investor
	 */
	public function get_investments( $request ) {
		$investor_id = (int) $request->get_param( 'investor_id' );

		// Query both teams and commissies where this ID appears in the investors field
		// Access control applied automatically via WP_Query filters (all approved users see all data)
		$entities = get_posts(
			[
				'post_type'      => [ 'team', 'commissie' ],
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
		$entities_serialized = get_posts(
			[
				'post_type'      => [ 'team', 'commissie' ],
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
		$all_entities   = array_merge( $entities, $entities_serialized );
		$seen_ids       = [];
		$unique_entities = [];
		foreach ( $all_entities as $entity ) {
			if ( ! in_array( $entity->ID, $seen_ids ) ) {
				$seen_ids[]        = $entity->ID;
				$unique_entities[] = $entity;
			}
		}

		// Format response
		$investments = [];
		foreach ( $unique_entities as $entity ) {
			$thumbnail_id  = get_post_thumbnail_id( $entity->ID );
			$thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

			$investments[] = [
				'id'        => $entity->ID,
				'type'      => $entity->post_type,
				'name'      => $this->sanitize_text( $entity->post_title ),
				'website'   => $this->sanitize_url( get_field( 'website', $entity->ID ) ),
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
			return new \WP_Error( 'not_logged_in', __( 'User is not logged in.', 'rondo' ), [ 'status' => 401 ] );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		// Get avatar URL
		$avatar_url = get_avatar_url( $user_id, [ 'size' => 96 ] );

		// Check if user is admin
		$is_admin = current_user_can( 'manage_options' );

		// Get profile edit URL
		$profile_url = admin_url( 'profile.php' );

		// Get admin URL
		$admin_url = admin_url();

		// Get linked person name, photo, and active functies
		$person_id          = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		$linked_person_name = null;
		$linked_person_photo = null;
		$active_functies    = [];
		if ( $person_id ) {
			$person = get_post( $person_id );
			if ( $person && 'person' === $person->post_type ) {
				$first              = get_field( 'first_name', $person_id ) ?: '';
				$infix              = get_field( 'infix', $person_id ) ?: '';
				$last               = get_field( 'last_name', $person_id ) ?: '';
				$linked_person_name = implode( ' ', array_filter( [ $first, $infix, $last ] ) ) ?: null;
				$linked_person_photo = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null;

				$work_history = get_field( 'work_history', $person_id ) ?: [];
				foreach ( $work_history as $job ) {
					if ( ! empty( $job['is_current'] ) && ! empty( $job['job_title'] ) ) {
						$active_functies[] = $job['job_title'];
					}
				}
			}
		}

		return rest_ensure_response(
			[
				'id'                    => $user_id,
				'name'                  => $user->display_name,
				'email'                 => $user->user_email,
				'avatar_url'            => $avatar_url,
				'is_admin'              => $is_admin,
				'can_access_fairplay'   => current_user_can( 'fairplay' ),
				'can_access_vog'        => current_user_can( 'vog' ),
				'can_access_financieel' => current_user_can( 'financieel' ),
				'can_access_toegangscontrole' => current_user_can( 'toegangscontrole' ),
				'can_access_clothing'   => current_user_can( 'manage_clothing' ) || current_user_can( 'manage_options' ),
				'profile_url'           => $profile_url,
				'admin_url'             => $admin_url,
				'linked_person_name'    => $linked_person_name,
				'active_functies'       => $active_functies,
				'linked_person_photo'   => $linked_person_photo,
			]
		);
	}

	/**
	 * Change the current user's password.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response or WP_Error.
	 */
	public function change_password( $request ) {
		$user_id          = get_current_user_id();
		$user             = get_userdata( $user_id );
		$current_password = $request->get_param( 'current_password' );
		$new_password     = $request->get_param( 'new_password' );

		// Demo guard
		if ( $user->user_login === 'demo' ) {
			return new \WP_Error( 'demo_user', 'Wachtwoord wijzigen is niet beschikbaar in de demo.', [ 'status' => 403 ] );
		}

		// Verify current password
		if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
			return new \WP_Error( 'wrong_password', 'Huidig wachtwoord is onjuist.', [ 'status' => 400 ] );
		}

		// Change password
		wp_set_password( $new_password, $user_id );

		// Destroy all sessions so the old session is invalidated
		$sessions = \WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();

		return rest_ensure_response( [ 'success' => true, 'message' => 'Wachtwoord succesvol gewijzigd. Log opnieuw in.' ] );
	}

	/**
	 * Get list of users (admin only)
	 */
	public function get_users( $request ) {
		$users = get_users(
			[
				'meta_key'     => 'rondo_linked_person_id',
				'meta_compare' => '!=',
				'meta_value'   => '',
				'number'       => -1,
			]
		);

		$user_list = [];
		foreach ( $users as $user ) {
			$linked_person_id   = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
			$linked_person_name = null;
			if ( $linked_person_id ) {
				$person = get_post( $linked_person_id );
				if ( $person && 'person' === $person->post_type ) {
					$first              = get_field( 'first_name', $linked_person_id ) ?: '';
					$infix              = get_field( 'infix', $linked_person_id ) ?: '';
					$last               = get_field( 'last_name', $linked_person_id ) ?: '';
					$linked_person_name = implode( ' ', array_filter( [ $first, $infix, $last ] ) );
				}
			}

			$user_list[] = [
				'id'                 => $user->ID,
				'name'               => $user->display_name,
				'email'              => $user->user_email,
				'registered'         => $user->user_registered,
				'last_active'        => get_user_meta( $user->ID, 'rondo_last_active', true ) ?: null,
				'linked_person_id'   => $linked_person_id ?: null,
				'linked_person_name' => $linked_person_name,
			];
		}

		return rest_ensure_response( $user_list );
	}

	/**
	 * Search people eligible for provisioning: have a KNVB ID, email, no WP account,
	 * are published, and are not former members. Requires a search query of at least 2 chars.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response List of matching provisionable people.
	 */
	public function get_provisionable_users( $request ) {
		$search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

		// Require at least 2 characters to search.
		if ( strlen( $search ) < 2 ) {
			return rest_ensure_response( [] );
		}

		$meta_key = \Rondo\Users\UserProvisioning::META_USER_ID;

		$people = get_posts( [
			'post_type'      => 'person',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			's'              => $search,
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'knvb-id',
					'compare' => '!=',
					'value'   => '',
				],
			],
			'fields'         => 'ids',
		] );

		$result = [];
		foreach ( $people as $person_id ) {
			// Skip former members.
			if ( get_field( 'former_member', $person_id ) == true ) {
				continue;
			}

			// Find email in contact_info ACF repeater.
			$contact_info = get_field( 'contact_info', $person_id );
			$email        = null;
			if ( is_array( $contact_info ) ) {
				foreach ( $contact_info as $contact ) {
					if ( isset( $contact['contact_type'] ) && 'email' === $contact['contact_type'] ) {
						$value = $contact['contact_value'] ?? '';
						if ( is_email( $value ) ) {
							$email = $value;
							break;
						}
					}
				}
			}

			if ( ! $email ) {
				continue;
			}

			$first     = get_field( 'first_name', $person_id ) ?: '';
			$last      = get_field( 'last_name', $person_id ) ?: '';
			$thumbnail = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null;

			$result[] = [
				'id'        => $person_id,
				'name'      => trim( $first . ' ' . $last ),
				'email'     => $email,
				'thumbnail' => $thumbnail,
			];
		}

		// Sort alphabetically by name.
		usort( $result, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

		return rest_ensure_response( $result );
	}

	/**
	 * Provision a WordPress user account for a person (admin only).
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with status, user_id, message or WP_Error.
	 */
	public function provision_user( $request ) {
		$person_id   = (int) $request->get_param( 'person_id' );
		$provisioner = new \Rondo\Users\UserProvisioning();
		$result      = $provisioner->provision( $person_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get provisioning email template settings (admin only).
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Current settings array.
	 */
	public function get_provisioning_settings( $request ) {
		$provisioner = new \Rondo\Users\UserProvisioning();
		return rest_ensure_response( $provisioner->get_settings() );
	}

	/**
	 * Update provisioning email template settings (admin only).
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Updated settings array.
	 */
	public function update_provisioning_settings( $request ) {
		$provisioner = new \Rondo\Users\UserProvisioning();

		$settings = array_filter(
			[
				'subject'    => $request->get_param( 'subject' ),
				'body'       => $request->get_param( 'body' ),
				'from_email' => $request->get_param( 'from_email' ),
				'from_name'  => $request->get_param( 'from_name' ),
			],
			function ( $v ) {
				return $v !== null;
			}
		);

		return rest_ensure_response( $provisioner->update_settings( $settings ) );
	}

	/**
	 * Delete a user and all their related data (admin only)
	 */
	public function delete_user( $request ) {
		$user_id = (int) $request->get_param( 'user_id' );

		// Prevent deleting yourself
		if ( $user_id === get_current_user_id() ) {
			return new \WP_Error(
				'cannot_delete_self',
				__( 'You cannot delete your own account.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Check if user exists
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error(
				'user_not_found',
				__( 'User not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Delete all user's posts (people, organizations, dates)
		$this->delete_user_posts( $user_id );

		// Delete the user
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$result = wp_delete_user( $user_id );

		if ( ! $result ) {
			return new \WP_Error(
				'delete_failed',
				__( 'Failed to delete user.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'User and all related data deleted.', 'rondo' ),
			]
		);
	}

	/**
	 * Delete all posts belonging to a user
	 */
	private function delete_user_posts( $user_id ) {
		$post_types = [ 'person', 'team' ];

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
	 * Get searchable custom field names for a post type.
	 *
	 * Retrieves active custom fields that contain user-searchable text content.
	 * Fields like Image, File, Color, Relationship, Link, Date, True/False are excluded.
	 *
	 * @param string $post_type 'person' or 'team'.
	 * @return array Array of field names (meta keys) to search.
	 */
	private function get_searchable_custom_fields( string $post_type ): array {
		$manager = new \Rondo\CustomFields\Manager();
		$fields  = $manager->get_fields( $post_type, false ); // Active only.

		// Searchable field types (text-based content).
		$searchable_types = array(
			'text',
			'textarea',
			'email',
			'url',
			'number',
			'select',
			'checkbox',
		);

		$field_names = array();
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'], $searchable_types, true ) ) {
				$field_names[] = $field['name'];
			}
		}

		return $field_names;
	}

	/**
	 * Build meta_query array for custom field search.
	 *
	 * Creates an OR-relation meta query to search multiple custom fields.
	 *
	 * @param array  $field_names Array of field names to search.
	 * @param string $query       Search query string.
	 * @return array Meta query array for get_posts().
	 */
	private function build_custom_field_meta_query( array $field_names, string $query ): array {
		if ( empty( $field_names ) ) {
			return array();
		}

		$meta_query = array( 'relation' => 'OR' );

		foreach ( $field_names as $field_name ) {
			$meta_query[] = array(
				'key'     => $field_name,
				'value'   => $query,
				'compare' => 'LIKE',
			);
		}

		return $meta_query;
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

	/**
	 * Get VOG settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with VOG settings.
	 */
	public function get_vog_settings( $request ) {
		$vog_email = new \Rondo\VOG\VOGEmail();
		return rest_ensure_response( $vog_email->get_all_settings() );
	}

	/**
	 * Update VOG settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with updated VOG settings.
	 */
	public function update_vog_settings( $request ) {
		$vog_email = new \Rondo\VOG\VOGEmail();

		$from_email              = $request->get_param( 'from_email' );
		$from_name               = $request->get_param( 'from_name' );
		$template_new            = $request->get_param( 'template_new' );
		$template_renewal        = $request->get_param( 'template_renewal' );
		$reminder_template_new   = $request->get_param( 'reminder_template_new' );
		$reminder_template_renewal = $request->get_param( 'reminder_template_renewal' );
		$exempt_commissies       = $request->get_param( 'exempt_commissies' );
		$exempt_discipline_teams = $request->get_param( 'exempt_discipline_teams' );

		// Update provided settings
		if ( $from_email !== null ) {
			$vog_email->update_from_email( $from_email );
		}

		if ( $from_name !== null ) {
			$vog_email->update_from_name( $from_name );
		}

		if ( $template_new !== null ) {
			$vog_email->update_template_new( $template_new );
		}

		if ( $template_renewal !== null ) {
			$vog_email->update_template_renewal( $template_renewal );
		}

		if ( $reminder_template_new !== null ) {
			$vog_email->update_reminder_template_new( $reminder_template_new );
		}

		if ( $reminder_template_renewal !== null ) {
			$vog_email->update_reminder_template_renewal( $reminder_template_renewal );
		}

		// Track if exempt commissies changed for recalculation
		$people_recalculated = null;
		if ( $exempt_commissies !== null ) {
			$old_exempt = $vog_email->get_exempt_commissies();
			$vog_email->update_exempt_commissies( $exempt_commissies );

			// If exempt commissies changed, trigger volunteer status recalculation
			$new_exempt = $vog_email->get_exempt_commissies();
			if ( $old_exempt !== $new_exempt ) {
				$people_recalculated = $this->trigger_vog_recalculation();
			}
		}

		if ( $exempt_discipline_teams !== null ) {
			$vog_email->update_exempt_discipline_teams( $exempt_discipline_teams );
		}

		// Return updated settings
		$response = $vog_email->get_all_settings();
		if ( $people_recalculated !== null ) {
			$response['people_recalculated'] = $people_recalculated;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get membership fee settings
	 *
	 * Returns settings for both current and next season.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with membership fee settings for both seasons.
	 */
	public function get_membership_fee_settings( $request ) {
		$membership_fees = new \Rondo\Fees\MembershipFees();
		$current_season  = $membership_fees->get_season_key();
		$next_season     = $membership_fees->get_next_season_key();

		return rest_ensure_response(
			[
				'current_season' => [
					'key'             => $current_season,
					'categories'      => $membership_fees->get_categories_for_season( $current_season ),
					'family_discount' => $membership_fees->get_family_discount_config( $current_season ),
				],
				'next_season'    => [
					'key'             => $next_season,
					'categories'      => $membership_fees->get_categories_for_season( $next_season ),
					'family_discount' => $membership_fees->get_family_discount_config( $next_season ),
				],
			]
		);
	}

	/**
	 * Update membership fee settings
	 *
	 * Updates settings for a specific season (current or next).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with updated membership fee settings for both seasons.
	 */
	public function update_membership_fee_settings( $request ) {
		$membership_fees = new \Rondo\Fees\MembershipFees();
		$current_season  = $membership_fees->get_season_key();
		$next_season     = $membership_fees->get_next_season_key();
		$season          = $request->get_param( 'season' );
		$categories      = $request->get_param( 'categories' );
		$family_discount = $request->get_param( 'family_discount' );

		// Validate category structure
		$validation = $this->validate_category_config( $categories );

		// Validate family discount config (if provided)
		$discount_validation = $this->validate_family_discount_config( $family_discount );

		// Merge all errors and warnings
		$all_errors   = array_merge( $validation['errors'], $discount_validation['errors'] );
		$all_warnings = array_merge( $validation['warnings'], $discount_validation['warnings'] );

		if ( ! empty( $all_errors ) ) {
			return new \WP_Error(
				'invalid_settings',
				'Settings validation failed',
				[
					'status'   => 400,
					'errors'   => $all_errors,
					'warnings' => $all_warnings,
				]
			);
		}

		// Save categories for the specified season
		$membership_fees->save_categories_for_season( $categories, $season );

		// Save family discount config (if provided)
		if ( $family_discount !== null ) {
			$membership_fees->save_family_discount_config(
				[
					'second_child_percent' => (float) ( $family_discount['second_child_percent'] ?? 25 ),
					'third_child_percent'  => (float) ( $family_discount['third_child_percent'] ?? 50 ),
				],
				$season
			);
		}

		// Return updated settings for both seasons
		$response = [
			'current_season' => [
				'key'             => $current_season,
				'categories'      => $membership_fees->get_categories_for_season( $current_season ),
				'family_discount' => $membership_fees->get_family_discount_config( $current_season ),
			],
			'next_season'    => [
				'key'             => $next_season,
				'categories'      => $membership_fees->get_categories_for_season( $next_season ),
				'family_discount' => $membership_fees->get_family_discount_config( $next_season ),
			],
		];

		// Include warnings if any
		if ( ! empty( $all_warnings ) ) {
			$response['warnings'] = $all_warnings;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Copy season categories from one season to another
	 *
	 * Copies both fee categories and family discount configuration from a source
	 * season to a destination season. Validates that destination is empty.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response with updated settings or error.
	 */
	public function copy_season_categories( $request ) {
		$membership_fees = new \Rondo\Fees\MembershipFees();
		$from_season     = $request->get_param( 'from_season' );
		$to_season       = $request->get_param( 'to_season' );

		// Validate seasons are different
		if ( $from_season === $to_season ) {
			return new \WP_Error(
				'invalid_copy',
				'Bron- en bestemmingsseizoen moeten verschillend zijn',
				[ 'status' => 400 ]
			);
		}

		// Check if destination season already has categories
		$existing_categories = $membership_fees->get_categories_for_season( $to_season );
		if ( ! empty( $existing_categories ) ) {
			return new \WP_Error(
				'destination_not_empty',
				'Bestemmingsseizoen heeft al categorieën gedefinieerd',
				[ 'status' => 400 ]
			);
		}

		// Get source season data
		$source_categories = $membership_fees->get_categories_for_season( $from_season );
		if ( empty( $source_categories ) ) {
			return new \WP_Error(
				'source_empty',
				'Bronseizoen heeft geen categorieën om te kopiëren',
				[ 'status' => 400 ]
			);
		}

		// Copy categories
		$membership_fees->save_categories_for_season( $source_categories, $to_season );

		// Copy family discount config
		$source_discount = $membership_fees->get_family_discount_config( $from_season );
		$membership_fees->save_family_discount_config( $source_discount, $to_season );

		// Return updated settings for both seasons
		$current_season = $membership_fees->get_season_key();
		$next_season    = $membership_fees->get_next_season_key();

		return rest_ensure_response(
			[
				'current_season' => [
					'key'             => $current_season,
					'categories'      => $membership_fees->get_categories_for_season( $current_season ),
					'family_discount' => $membership_fees->get_family_discount_config( $current_season ),
				],
				'next_season'    => [
					'key'             => $next_season,
					'categories'      => $membership_fees->get_categories_for_season( $next_season ),
					'family_discount' => $membership_fees->get_family_discount_config( $next_season ),
				],
			]
		);
	}

	/**
	 * Validate category configuration structure
	 *
	 * Checks for required fields, duplicate slugs, and duplicate age class assignments.
	 * Returns both errors (block save) and warnings (informational).
	 *
	 * @param mixed $categories The categories data to validate.
	 * @return array Array with 'errors' and 'warnings' keys.
	 */
	private function validate_category_config( $categories ) {
		$errors   = [];
		$warnings = [];

		// Must be an array/object
		if ( ! is_array( $categories ) ) {
			$errors[] = [ 'field' => 'categories', 'message' => 'Categories must be an object' ];
			return [ 'errors' => $errors, 'warnings' => $warnings ];
		}

		// Empty array is valid (per Phase 156 pattern: silent for missing config)
		if ( empty( $categories ) ) {
			return [ 'errors' => [], 'warnings' => [] ];
		}

		$seen_slugs    = [];
		$age_class_map = [];

		foreach ( $categories as $slug => $category ) {
			// Validate slug is not empty
			if ( empty( $slug ) || ! is_string( $slug ) ) {
				$errors[] = [ 'field' => 'slug', 'message' => 'Category slug is required and must be a string' ];
				continue;
			}

			// Validate slug format (lowercase, no spaces — use sanitize_title for normalization check)
			$normalized_slug = sanitize_title( $slug );
			if ( $normalized_slug !== $slug ) {
				$errors[] = [
					'field'   => "categories.{$slug}",
					'message' => "Invalid slug format. Use lowercase letters, numbers, and hyphens only. Suggested: '{$normalized_slug}'",
				];
			}

			// Check for duplicate slugs (case-insensitive)
			$lower_slug = strtolower( $slug );
			if ( isset( $seen_slugs[ $lower_slug ] ) ) {
				$errors[] = [
					'field'   => "categories.{$slug}",
					'message' => "Duplicate slug '{$slug}'",
				];
			}
			$seen_slugs[ $lower_slug ] = true;

			// Validate required field: label
			if ( ! isset( $category['label'] ) || ! is_string( $category['label'] ) || trim( $category['label'] ) === '' ) {
				$errors[] = [
					'field'   => "categories.{$slug}.label",
					'message' => 'Label is required',
				];
			}

			// Validate required field: amount (must be numeric, non-negative)
			if ( ! isset( $category['amount'] ) || ! is_numeric( $category['amount'] ) || (float) $category['amount'] < 0 ) {
				$errors[] = [
					'field'   => "categories.{$slug}.amount",
					'message' => 'Amount is required and must be a non-negative number',
				];
			}

			// Track age class assignments for overlap detection (warning, not error per API-04)
			if ( isset( $category['age_classes'] ) && is_array( $category['age_classes'] ) ) {
				foreach ( $category['age_classes'] as $age_class ) {
					if ( ! is_string( $age_class ) ) {
						continue;
					}
					$normalized_class = strtolower( trim( $age_class ) );
					if ( isset( $age_class_map[ $normalized_class ] ) ) {
						$warnings[] = [
							'field'      => "categories.{$slug}.age_classes",
							'message'    => "Age class '{$age_class}' is also assigned to category '{$age_class_map[ $normalized_class ]}'",
							'categories' => [ $age_class_map[ $normalized_class ], $slug ],
						];
					} else {
						$age_class_map[ $normalized_class ] = $slug;
					}
				}
			}

			// Validate matching_teams (optional, must be array of integers if present)
			if ( isset( $category['matching_teams'] ) ) {
				if ( ! is_array( $category['matching_teams'] ) ) {
					$errors[] = [
						'field'   => "categories.{$slug}.matching_teams",
						'message' => 'matching_teams must be an array',
					];
				} else {
					foreach ( $category['matching_teams'] as $team_id ) {
						if ( ! is_numeric( $team_id ) || (int) $team_id <= 0 ) {
							$errors[] = [
								'field'   => "categories.{$slug}.matching_teams",
								'message' => 'matching_teams must contain valid team IDs (positive integers)',
							];
							break;
						}
					}
				}
			}

			// Validate matching_werkfuncties (optional, must be array of strings if present)
			if ( isset( $category['matching_werkfuncties'] ) ) {
				if ( ! is_array( $category['matching_werkfuncties'] ) ) {
					$errors[] = [
						'field'   => "categories.{$slug}.matching_werkfuncties",
						'message' => 'matching_werkfuncties must be an array',
					];
				} else {
					foreach ( $category['matching_werkfuncties'] as $wf ) {
						if ( ! is_string( $wf ) || trim( $wf ) === '' ) {
							$errors[] = [
								'field'   => "categories.{$slug}.matching_werkfuncties",
								'message' => 'matching_werkfuncties must contain non-empty strings',
							];
							break;
						}
					}
				}
			}
		}

		return [ 'errors' => $errors, 'warnings' => $warnings ];
	}

	/**
	 * Validate family discount configuration
	 *
	 * Ensures percentages are valid numbers between 0 and 100.
	 * Null/missing config is valid (defaults will be used).
	 *
	 * @param mixed $config The family_discount config to validate.
	 * @return array Array with 'errors' and 'warnings' keys.
	 */
	private function validate_family_discount_config( $config ) {
		$errors   = [];
		$warnings = [];

		// Null/missing is valid (use defaults)
		if ( $config === null ) {
			return [ 'errors' => [], 'warnings' => [] ];
		}

		// Must be an array
		if ( ! is_array( $config ) ) {
			$errors[] = [
				'field'   => 'family_discount',
				'message' => 'Familiekorting configuratie moet een object zijn',
			];
			return [ 'errors' => $errors, 'warnings' => $warnings ];
		}

		// Validate second_child_percent
		if ( isset( $config['second_child_percent'] ) ) {
			$value = $config['second_child_percent'];
			if ( ! is_numeric( $value ) || $value < 0 || $value > 100 ) {
				$errors[] = [
					'field'   => 'family_discount.second_child_percent',
					'message' => 'Korting tweede kind moet tussen 0 en 100 procent zijn',
				];
			}
		}

		// Validate third_child_percent
		if ( isset( $config['third_child_percent'] ) ) {
			$value = $config['third_child_percent'];
			if ( ! is_numeric( $value ) || $value < 0 || $value > 100 ) {
				$errors[] = [
					'field'   => 'family_discount.third_child_percent',
					'message' => 'Korting derde kind en verder moet tussen 0 en 100 procent zijn',
				];
			}
		}

		// Warning if second child discount >= third child discount
		$second = is_numeric( $config['second_child_percent'] ?? null ) ? (float) $config['second_child_percent'] : 25;
		$third  = is_numeric( $config['third_child_percent'] ?? null ) ? (float) $config['third_child_percent'] : 50;
		if ( $second > 0 && $third > 0 && $second >= $third ) {
			$warnings[] = [
				'field'   => 'family_discount',
				'message' => 'Korting tweede kind is doorgaans lager dan korting derde kind',
			];
		}

		return [ 'errors' => $errors, 'warnings' => $warnings ];
	}

	/**
	 * Get club configuration settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with club configuration settings.
	 */
	public function get_club_config( $request ) {
		return rest_ensure_response( \Rondo\Config\ClubConfig::get_all_settings() );
	}

	/**
	 * Update club configuration settings
	 *
	 * Supports partial updates - only provided fields will be updated.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with updated club configuration settings.
	 */
	public function update_club_config( $request ) {
		// Update club_name if provided
		$club_name = $request->get_param( 'club_name' );
		if ( $club_name !== null ) {
			\Rondo\Config\ClubConfig::update_club_name( $club_name );
		}

		// Update freescout_url if provided
		$freescout_url = $request->get_param( 'freescout_url' );
		if ( $freescout_url !== null ) {
			\Rondo\Config\ClubConfig::update_freescout_url( $freescout_url );
		}

		$freescout_api_key = $request->get_param( 'freescout_api_key' );
		if ( $freescout_api_key !== null ) {
			\Rondo\Config\ClubConfig::update_freescout_api_key( $freescout_api_key );
		}

		$lettermint_api_token = $request->get_param( 'lettermint_api_token' );
		if ( $lettermint_api_token !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_api_token( $lettermint_api_token );
		}

		$lettermint_team_api_token = $request->get_param( 'lettermint_team_api_token' );
		if ( $lettermint_team_api_token !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_team_api_token( $lettermint_team_api_token );
		}

		$lettermint_project_id = $request->get_param( 'lettermint_project_id' );
		if ( $lettermint_project_id !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_project_id( $lettermint_project_id );
		}

		$lettermint_route_id = $request->get_param( 'lettermint_route_id' );
		if ( $lettermint_route_id !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_route_id( $lettermint_route_id );
		}

		$lettermint_from_email = $request->get_param( 'lettermint_from_email' );
		if ( $lettermint_from_email !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_from_email( $lettermint_from_email );
		}

		$lettermint_from_name = $request->get_param( 'lettermint_from_name' );
		if ( $lettermint_from_name !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_from_name( $lettermint_from_name );
		}

		$lettermint_webhook_secret = $request->get_param( 'lettermint_webhook_secret' );
		if ( $lettermint_webhook_secret !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_webhook_secret( $lettermint_webhook_secret );
		}

		return rest_ensure_response( \Rondo\Config\ClubConfig::get_all_settings() );
	}

	/**
	 * List Lettermint projects and resolved default routes for project selection.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_lettermint_projects( $request ) {
		$token = \Rondo\Notifications\LettermintConfig::get_team_api_token();
		if ( $token === '' ) {
			return new \WP_Error(
				'lettermint_missing_token',
				'Lettermint team API token ontbreekt. Sla eerst een token op onder Instellingen > Koppelingen > Lettermint.',
				[ 'status' => 400 ]
			);
		}

		$projects_response = $this->request_lettermint_team_api( $token, 'GET', '/projects' );
		if ( is_wp_error( $projects_response ) ) {
			return $projects_response;
		}

		$projects = $this->extract_lettermint_data_list( $projects_response );
		$results  = [];

		foreach ( $projects as $project ) {
			if ( ! is_array( $project ) ) {
				continue;
			}

			$project_id   = sanitize_text_field( (string) ( $project['id'] ?? '' ) );
			$project_name = sanitize_text_field( (string) ( $project['name'] ?? '' ) );
			if ( $project_id === '' ) {
				continue;
			}

			$default_route_id   = '';
			$default_route_name = '';
			$route_count        = 0;
			$route_error        = '';

			$route_result = $this->get_lettermint_project_default_route( $token, $project_id );
			if ( is_wp_error( $route_result ) ) {
				$route_error = $route_result->get_error_message();
			} else {
				$default_route_id   = $route_result['id'];
				$default_route_name = $route_result['name'];
				$route_count        = (int) $route_result['route_count'];
			}

			$results[] = [
				'id'                => $project_id,
				'name'              => $project_name,
				'is_default'        => isset( $project['is_default'] ) ? rest_sanitize_boolean( $project['is_default'] ) : false,
				'default_route_id'  => $default_route_id,
				'default_route_name'=> $default_route_name,
				'route_count'       => $route_count,
				'route_error'       => $route_error,
			];
		}

		$selected_project_id = \Rondo\Config\ClubConfig::get_lettermint_project_id();
		if ( $selected_project_id === '' && count( $results ) === 1 ) {
			$selected_project_id = $results[0]['id'];
		}

		return rest_ensure_response(
			[
				'projects'            => $results,
				'selected_project_id' => $selected_project_id,
			]
		);
	}

	/**
	 * Create a Lettermint webhook for this Rondo install.
	 *
	 * Uses the Lettermint Team API and stores returned identifiers/secrets in
	 * WordPress options for immediate use by the inbound webhook verifier.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_lettermint_webhook( $request ) {
		$token = \Rondo\Notifications\LettermintConfig::get_team_api_token();
		if ( $token === '' ) {
			return new \WP_Error(
				'lettermint_missing_token',
				'Lettermint team API token ontbreekt. Sla eerst een token op onder Instellingen > Koppelingen > Lettermint.',
				[ 'status' => 400 ]
			);
		}

		$resolved_route = $this->resolve_lettermint_project_and_route(
			$token,
			sanitize_text_field( (string) $request->get_param( 'project_id' ) ),
			sanitize_text_field( (string) $request->get_param( 'route_id' ) )
		);
		if ( is_wp_error( $resolved_route ) ) {
			return $resolved_route;
		}

		$route_id     = $resolved_route['route_id'];
		$route_name   = $resolved_route['route_name'];
		$project_id   = $resolved_route['project_id'];
		$project_name = $resolved_route['project_name'];

		$webhook_url = rest_url( 'rondo/v1/lettermint/webhook' );
		$site_host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$name        = sprintf( 'Rondo Club (%s)', $site_host ?: 'site' );

		$payload = [
			'name'     => $name,
			'url'      => $webhook_url,
			'route_id' => $route_id,
			'events'   => array_values( \Rondo\Notifications\LettermintWebhook::ACTIONABLE_EVENTS ),
			'enabled'  => true,
		];

		$body = $this->request_lettermint_team_api( $token, 'POST', '/webhooks', [], $payload );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$data = is_array( $body['data'] ?? null ) ? $body['data'] : [];

		$resolved_route_id = sanitize_text_field( (string) ( $data['route_id'] ?? $route_id ) );
		$webhook_id        = sanitize_text_field( (string) ( $data['id'] ?? '' ) );
		$secret            = sanitize_text_field( (string) ( $data['secret'] ?? $data['signing_secret'] ?? $data['webhook_secret'] ?? '' ) );
		$resolved_url      = esc_url_raw( (string) ( $data['url'] ?? $webhook_url ) );
		$resolved_events   = isset( $data['events'] ) && is_array( $data['events'] )
			? array_values( array_map( 'sanitize_text_field', $data['events'] ) )
			: array_values( \Rondo\Notifications\LettermintWebhook::ACTIONABLE_EVENTS );

		if ( $project_id !== '' ) {
			\Rondo\Config\ClubConfig::update_lettermint_project_id( $project_id );
		}
		\Rondo\Config\ClubConfig::update_lettermint_route_id( $resolved_route_id );
		if ( $webhook_id !== '' ) {
			\Rondo\Config\ClubConfig::update_lettermint_webhook_id( $webhook_id );
		}
		if ( $secret !== '' ) {
			\Rondo\Config\ClubConfig::update_lettermint_webhook_secret( $secret );
		}

		return rest_ensure_response(
			[
				'message' => $secret !== ''
					? 'Lettermint-webhook aangemaakt. Geheim automatisch opgeslagen.'
					: 'Lettermint-webhook aangemaakt, maar het geheim is niet meegeleverd door de API.',
				'webhook' => [
					'id'       => $webhook_id,
					'url'      => $resolved_url,
					'project_id' => $project_id,
					'project_name' => $project_name,
					'route_id' => $resolved_route_id,
					'route_name' => $route_name,
					'events'   => $resolved_events,
				],
				'secret_saved' => $secret !== '',
				'config'       => \Rondo\Config\ClubConfig::get_all_settings(),
			]
		);
	}

	/**
	 * Resolve a project and route ID for webhook creation.
	 *
	 * Priority:
	 * 1. Explicit route override (advanced/manual).
	 * 2. Explicit project override.
	 * 3. Stored project selection.
	 * 4. Single available project fallback.
	 *
	 * @param string $token            Team API token.
	 * @param string $project_override Optional project override.
	 * @param string $route_override   Optional route override.
	 * @return array<string, string>|\WP_Error
	 */
	private function resolve_lettermint_project_and_route(
		string $token,
		string $project_override = '',
		string $route_override = ''
	) {
		$project_override = sanitize_text_field( $project_override );
		$route_override = sanitize_text_field( $route_override );
		if ( $route_override !== '' ) {
			return [
				'project_id'   => $project_override ?: \Rondo\Config\ClubConfig::get_lettermint_project_id(),
				'project_name' => '',
				'route_id'     => $route_override,
				'route_name'   => 'Handmatige override',
			];
		}

		$projects_response = $this->request_lettermint_team_api( $token, 'GET', '/projects' );
		if ( is_wp_error( $projects_response ) ) {
			return $projects_response;
		}

		$projects = $this->extract_lettermint_data_list( $projects_response );
		if ( empty( $projects ) ) {
			return new \WP_Error(
				'lettermint_missing_route_id',
				'Kon geen Lettermint-projecten vinden om automatisch een route ID te bepalen.',
				[ 'status' => 400 ]
			);
		}

		$projects_by_id = [];
		foreach ( $projects as $project ) {
			if ( ! is_array( $project ) ) {
				continue;
			}
			$project_id = sanitize_text_field( (string) ( $project['id'] ?? '' ) );
			if ( $project_id === '' ) {
				continue;
			}
			$projects_by_id[ $project_id ] = [
				'id'   => $project_id,
				'name' => sanitize_text_field( (string) ( $project['name'] ?? '' ) ),
			];
		}

		$project_id = $project_override;
		if ( $project_id === '' ) {
			$project_id = \Rondo\Config\ClubConfig::get_lettermint_project_id();
		}

		if ( $project_id === '' && count( $projects_by_id ) === 1 ) {
			$project_id = array_key_first( $projects_by_id );
		}

		if ( $project_id === '' && count( $projects_by_id ) > 1 ) {
			$project_names = array_values(
				array_filter(
					array_map(
						static function ( array $project ): string {
							return trim( (string) ( $project['name'] ?? '' ) );
						},
						$projects_by_id
					)
				)
			);
			$project_names_suffix = '';
			if ( ! empty( $project_names ) ) {
				$project_names_suffix = ' Gevonden projecten: ' . implode( ', ', array_slice( $project_names, 0, 8 ) ) . '.';
			}

			return new \WP_Error(
				'lettermint_project_selection_required',
				'Meerdere Lettermint-projecten gevonden. Kies eerst een project in de dropdown.' . $project_names_suffix,
				[ 'status' => 400 ]
			);
		}

		if ( $project_id === '' || ! isset( $projects_by_id[ $project_id ] ) ) {
			return new \WP_Error(
				'lettermint_invalid_project',
				'Het geselecteerde Lettermint-project bestaat niet of is niet toegankelijk.',
				[ 'status' => 400 ]
			);
		}

		$route = $this->get_lettermint_project_default_route( $token, $project_id );
		if ( is_wp_error( $route ) ) {
			return $route;
		}

		return [
			'project_id'   => $project_id,
			'project_name' => $projects_by_id[ $project_id ]['name'],
			'route_id'     => $route['id'],
			'route_name'   => $route['name'],
		];
	}

	/**
	 * Resolve the default route for a specific Lettermint project.
	 *
	 * @param string $token      Team API token.
	 * @param string $project_id Lettermint project ID.
	 * @return array<string, int|string>|\WP_Error
	 */
	private function get_lettermint_project_default_route( string $token, string $project_id ) {
		$routes_response = $this->request_lettermint_team_api(
			$token,
			'GET',
			'/projects/' . rawurlencode( $project_id ) . '/routes'
		);
		if ( is_wp_error( $routes_response ) ) {
			return $routes_response;
		}

		$routes      = $this->extract_lettermint_data_list( $routes_response );
		$route_count = count( $routes );
		if ( $route_count === 0 ) {
			return new \WP_Error(
				'lettermint_missing_project_routes',
				'Geen routes gevonden voor het geselecteerde Lettermint-project.',
				[ 'status' => 400 ]
			);
		}

		$default_route = null;
		foreach ( $routes as $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}
			$is_default = isset( $route['is_default'] ) ? rest_sanitize_boolean( $route['is_default'] ) : false;
			if ( $is_default ) {
				$default_route = $route;
				break;
			}
		}

		if ( null === $default_route && $route_count === 1 && is_array( $routes[0] ?? null ) ) {
			$default_route = $routes[0];
		}

		if ( ! is_array( $default_route ) ) {
			return new \WP_Error(
				'lettermint_missing_default_route',
				'Het geselecteerde project heeft geen default route.',
				[ 'status' => 400 ]
			);
		}

		$route_id = sanitize_text_field( (string) ( $default_route['id'] ?? '' ) );
		if ( $route_id === '' ) {
			return new \WP_Error(
				'lettermint_missing_default_route',
				'Default route gevonden, maar zonder geldig route ID.',
				[ 'status' => 400 ]
			);
		}

		return [
			'id'          => $route_id,
			'name'        => sanitize_text_field( (string) ( $default_route['name'] ?? '' ) ),
			'route_count' => $route_count,
		];
	}

	/**
	 * Call a Lettermint Team API endpoint.
	 *
	 * @param string                    $token   Team API token.
	 * @param string                    $method  HTTP method.
	 * @param string                    $path    API path, e.g. /projects.
	 * @param array<string, string>     $query   Optional query parameters.
	 * @param array<string, mixed>|null $payload Optional JSON payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function request_lettermint_team_api(
		string $token,
		string $method,
		string $path,
		array $query = [],
		?array $payload = null
	) {
		$api_url = 'https://api.lettermint.co/v1/' . ltrim( $path, '/' );
		if ( ! empty( $query ) ) {
			$api_url = add_query_arg( $query, $api_url );
		}

		$args = [
			'method'  => strtoupper( $method ),
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			],
			'timeout' => 20,
		];

		if ( ! empty( $payload ) ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$response = wp_remote_request( $api_url, $args );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'lettermint_request_failed',
				$response->get_error_message(),
				[ 'status' => 502 ]
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body_raw    = (string) wp_remote_retrieve_body( $response );
		$body        = json_decode( $body_raw, true );
		if ( ! is_array( $body ) ) {
			$body = [];
		}

		if ( $status_code >= 400 ) {
			$message = $this->extract_lettermint_api_error_message( $body );
			return new \WP_Error(
				'lettermint_api_error',
				$message !== '' ? $message : 'Lettermint API retourneerde een fout.',
				[ 'status' => $status_code ]
			);
		}

		return $body;
	}

	/**
	 * Normalize Lettermint response data into a list.
	 *
	 * @param array<string, mixed> $body Parsed API response.
	 * @return array
	 */
	private function extract_lettermint_data_list( array $body ): array {
		$data = $body['data'] ?? null;
		if ( ! is_array( $data ) ) {
			return [];
		}

		if ( $this->is_list_array( $data ) ) {
			return $data;
		}

		return [ $data ];
	}

	/**
	 * Check whether an array uses a sequential numeric index.
	 *
	 * @param array $items Input array.
	 * @return bool
	 */
	private function is_list_array( array $items ): bool {
		$index = 0;
		foreach ( array_keys( $items ) as $key ) {
			if ( $key !== $index ) {
				return false;
			}
			++$index;
		}

		return true;
	}

	/**
	 * Send a Lettermint test email through wp_mail transport.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_lettermint_test_email( $request ) {
		if ( \Rondo\Notifications\LettermintConfig::get_api_token() === '' ) {
			return new \WP_Error(
				'lettermint_missing_api_token',
				'Lettermint API token ontbreekt. Sla eerst de instellingen op.',
				[ 'status' => 400 ]
			);
		}

		if ( ! class_exists( '\Lettermint\Lettermint' ) ) {
			return new \WP_Error(
				'lettermint_sdk_missing',
				'Lettermint SDK niet gevonden op de server.',
				[ 'status' => 500 ]
			);
		}

		$recipient = sanitize_email( (string) $request->get_param( 'recipient' ) );
		if ( $recipient === '' ) {
			$current_user = wp_get_current_user();
			$recipient    = sanitize_email( (string) ( $current_user->user_email ?? '' ) );
		}

		if ( ! is_email( $recipient ) ) {
			return new \WP_Error(
				'lettermint_invalid_recipient',
				'Geen geldig ontvanger e-mailadres opgegeven.',
				[ 'status' => 400 ]
			);
		}

		$subject = sprintf( '[Rondo Club] Lettermint testmail - %s', wp_date( 'Y-m-d H:i:s' ) );
		$route_override = \Rondo\Notifications\LettermintConfig::get_route_id();
		$project_id     = \Rondo\Config\ClubConfig::get_lettermint_project_id();
		$route_label    = $route_override !== ''
			? sprintf( '%s (handmatige override)', $route_override )
			: 'automatisch via project default route';
		$body    = implode(
			"\n",
			[
				'Dit is een testmail vanuit Rondo Club.',
				'',
				'Als je dit bericht ontvangt, werkt de Lettermint-transportlaag voor wp_mail().',
				'',
				'Site: ' . home_url(),
				'Project ID: ' . ( $project_id !== '' ? $project_id : '(niet geselecteerd)' ),
				'Route: ' . $route_label,
				'Tijd: ' . wp_date( DATE_RFC3339 ),
			]
		);

		$headers = [
			'Content-Type: text/plain; charset=UTF-8',
			'X-Rondo-Email-Tag: lettermint-test',
		];

		$sent = wp_mail( $recipient, $subject, $body, $headers );
		if ( ! $sent ) {
			return new \WP_Error(
				'lettermint_test_failed',
				'Testmail kon niet worden verzonden. Controleer Lettermint-instellingen en serverlogs.',
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'message'   => 'Testmail verzonden.',
				'recipient' => $recipient,
			]
		);
	}

	/**
	 * Extract a human-readable Lettermint API error message from response body.
	 *
	 * @param array<string, mixed> $body Parsed Lettermint API response.
	 * @return string
	 */
	private function extract_lettermint_api_error_message( array $body ): string {
		$message = sanitize_text_field( (string) ( $body['message'] ?? '' ) );
		if ( $message !== '' ) {
			return $message;
		}

		if ( isset( $body['errors'] ) && is_array( $body['errors'] ) ) {
			foreach ( $body['errors'] as $error ) {
				if ( is_array( $error ) && isset( $error['message'] ) ) {
					$error_message = sanitize_text_field( (string) $error['message'] );
					if ( $error_message !== '' ) {
						return $error_message;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Get finance configuration settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with finance configuration settings.
	 */
	public function get_finance_settings( $request ) {
		$finance_config = new \Rondo\Config\FinanceConfig();
		return rest_ensure_response( $finance_config->get_all_settings() );
	}

	/**
	 * Update finance configuration settings
	 *
	 * Supports partial updates - only provided fields will be updated.
	 * Rabobank credentials are encrypted at rest using sodium.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with updated finance configuration settings.
	 */
	public function update_finance_settings( $request ) {
		$finance_config = new \Rondo\Config\FinanceConfig();
		$finance_config->update_settings( $request->get_params() );
		return rest_ensure_response( $finance_config->get_all_settings() );
	}

	/**
	 * Get finance branding settings (admin only).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Branding settings.
	 */
	public function get_finance_branding( $request ) {
		$finance_config = new \Rondo\Config\FinanceConfig();
		$settings       = $finance_config->get_all_settings();

		return rest_ensure_response(
			[
				'club_logo_id'  => (int) ( $settings['club_logo_id'] ?? 0 ),
				'club_logo_url' => isset( $settings['club_logo_url'] ) ? (string) $settings['club_logo_url'] : '',
				'accent_color'  => isset( $settings['accent_color'] ) ? (string) $settings['accent_color'] : '',
				'accent_background_color' => isset( $settings['accent_background_color'] ) ? (string) $settings['accent_background_color'] : '',
			]
		);
	}

	/**
	 * Update finance branding settings (admin only).
	 *
	 * Supports partial updates - only provided fields will be updated.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Branding settings.
	 */
	public function update_finance_branding( $request ) {
		$finance_config = new \Rondo\Config\FinanceConfig();
		$data           = [];

		$club_logo_id = $request->get_param( 'club_logo_id' );
		if ( null !== $club_logo_id ) {
			$data['club_logo_id'] = (int) $club_logo_id;
		}

		$accent_color = $request->get_param( 'accent_color' );
		if ( null !== $accent_color ) {
			$data['accent_color'] = (string) $accent_color;
		}

		$accent_background_color = $request->get_param( 'accent_background_color' );
		if ( null !== $accent_background_color ) {
			$data['accent_background_color'] = (string) $accent_background_color;
		}

		if ( ! empty( $data ) ) {
			$finance_config->update_settings( $data );
		}

		return $this->get_finance_branding( $request );
	}

	/**
	 * Get membership fee list for all calculable members
	 *
	 * Supports forecast mode via ?forecast=true parameter which:
	 * - Returns next season key instead of current season
	 * - Uses 100% pro-rata for all members (full year assumption)
	 * - Omits Nikki billing data (not available for future season)
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with fee list.
	 */
	public function get_fee_list( $request ) {
		$forecast = $request->get_param( 'forecast' );
		$fees     = new \Rondo\Fees\MembershipFees();

		// Determine season
		if ( $forecast ) {
			$season = $fees->get_next_season_key();
		} else {
			$season = $request->get_param( 'season' );
			if ( $season === null ) {
				$season = $fees->get_season_key();
			}
		}

		$fee_cache_key = $fees->get_fee_cache_meta_key( $season );
		$nikki_year    = substr( $season, 0, 4 );

		// Use fields => 'ids' for a lightweight query, then prime the meta cache
		// in a single query so all subsequent get_post_meta() calls are O(1).
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			]
		);

		// Prime meta cache for all person IDs in one query.
		// fields => 'ids' skips automatic meta cache priming, so we do it explicitly.
		update_meta_cache( 'post', $query->posts );

		// Season end date for former-member eligibility check
		$season_end_year = (int) substr( $season, 5, 4 );
		$season_end_ts   = strtotime( $season_end_year . '-07-01' );

		$results      = [];
		$uncached_ids = [];

		foreach ( $query->posts as $person_id ) {
			$is_former = ! empty( get_post_meta( $person_id, 'former_member', true ) );

			// Former members: check season eligibility inline
			if ( $is_former ) {
				if ( $forecast ) {
					continue;
				}
				$lid_sinds = get_post_meta( $person_id, 'lid-sinds', true );
				if ( empty( $lid_sinds ) ) {
					continue;
				}
				$lid_sinds_ts = strtotime( $lid_sinds );
				if ( $lid_sinds_ts === false || $lid_sinds_ts >= $season_end_ts ) {
					continue;
				}
			}

			// Read cached fee directly from meta (already in object cache)
			$fee_data = get_post_meta( $person_id, $fee_cache_key, true );

			if ( ! is_array( $fee_data ) || empty( $fee_data['category'] ) ) {
				$uncached_ids[] = $person_id;
				continue;
			}

			$result = [
				'id'                     => $person_id,
				'first_name'             => get_post_meta( $person_id, 'first_name', true ) ?: '',
				'last_name'              => get_post_meta( $person_id, 'last_name', true ) ?: '',
				'category'               => $fee_data['category'],
				'leeftijdsgroep'         => $fee_data['leeftijdsgroep'] ?? null,
				'base_fee'               => $fee_data['base_fee'],
				'family_discount_rate'   => $fee_data['family_discount_rate'] ?? 0.0,
				'family_discount_amount' => $fee_data['family_discount_amount'] ?? 0,
				'fee_after_discount'     => $fee_data['fee_after_discount'] ?? $fee_data['final_fee'],
				'prorata_percentage'     => $fee_data['prorata_percentage'] ?? 1.0,
				'final_fee'              => $fee_data['final_fee'],
				'family_key'             => $fee_data['family_key'] ?? null,
				'family_size'            => $fee_data['family_size'] ?? null,
				'family_position'        => $fee_data['family_position'] ?? null,
				'lid_sinds'              => $fee_data['registration_date'] ?? null,
				'from_cache'             => true,
				'calculated_at'          => $fee_data['calculated_at'] ?? null,
				'is_former_member'       => $is_former,
			];

			if ( ! $forecast ) {
				$nikki_total           = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_total', true );
				$nikki_saldo           = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_saldo', true );
				$result['nikki_total'] = $nikki_total !== '' ? (float) $nikki_total : null;
				$result['nikki_saldo'] = $nikki_saldo !== '' ? (float) $nikki_saldo : null;
			}

			$results[] = $result;
		}

		// Fallback: calculate fees for uncached members (rare after background recalculation)
		foreach ( $uncached_ids as $person_id ) {
			if ( $forecast ) {
				$fee_data = $fees->calculate_fee_with_family_discount( $person_id, $season );
				if ( $fee_data === null ) {
					continue;
				}
				$fee_data['prorata_percentage'] = 1.0;
				$fee_data['final_fee']          = $fee_data['fee_after_discount'] ?? $fee_data['final_fee'];
				$fee_data['registration_date']  = null;
				$fee_data['from_cache']         = false;
				$fee_data['calculated_at']      = current_time( 'Y-m-d H:i:s' );
			} else {
				$fee_data = $fees->get_fee_for_person_cached( $person_id, $season );
				if ( $fee_data === null ) {
					continue;
				}
			}

			$result = [
				'id'                     => $person_id,
				'first_name'             => get_post_meta( $person_id, 'first_name', true ) ?: '',
				'last_name'              => get_post_meta( $person_id, 'last_name', true ) ?: '',
				'category'               => $fee_data['category'],
				'leeftijdsgroep'         => $fee_data['leeftijdsgroep'] ?? null,
				'base_fee'               => $fee_data['base_fee'],
				'family_discount_rate'   => $fee_data['family_discount_rate'] ?? 0.0,
				'family_discount_amount' => $fee_data['family_discount_amount'] ?? 0,
				'fee_after_discount'     => $fee_data['fee_after_discount'] ?? $fee_data['final_fee'],
				'prorata_percentage'     => $fee_data['prorata_percentage'] ?? 1.0,
				'final_fee'              => $fee_data['final_fee'],
				'family_key'             => $fee_data['family_key'] ?? null,
				'family_size'            => $fee_data['family_size'] ?? null,
				'family_position'        => $fee_data['family_position'] ?? null,
				'lid_sinds'              => $fee_data['registration_date'] ?? null,
				'from_cache'             => $fee_data['from_cache'] ?? false,
				'calculated_at'          => $fee_data['calculated_at'] ?? null,
				'is_former_member'       => false,
			];

			if ( ! $forecast ) {
				$nikki_total           = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_total', true );
				$nikki_saldo           = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_saldo', true );
				$result['nikki_total'] = $nikki_total !== '' ? (float) $nikki_total : null;
				$result['nikki_saldo'] = $nikki_saldo !== '' ? (float) $nikki_saldo : null;
			}

			$results[] = $result;
		}

		// Look up existing membership invoices for this season (skip for forecast).
		if ( ! $forecast ) {
			$invoice_query = new \WP_Query(
				[
					'post_type'      => 'rondo_invoice',
					'posts_per_page' => -1,
					'post_status'    => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ],
					'no_found_rows'  => true,
					'fields'         => 'ids',
					'meta_query'     => [
						'relation' => 'AND',
						[
							'key'   => '_invoice_season',
							'value' => $season,
						],
						[
							'key'   => 'invoice_type',
							'value' => 'membership',
						],
					],
				]
			);

			// Build person_id => { invoice_id, invoice_status } lookup.
			$invoice_map = [];
			if ( ! empty( $invoice_query->posts ) ) {
				update_meta_cache( 'post', $invoice_query->posts );
				foreach ( $invoice_query->posts as $inv_id ) {
					$inv_person = get_post_meta( $inv_id, 'person', true );
					if ( $inv_person ) {
						$invoice_map[ (int) $inv_person ] = [
							'id'     => $inv_id,
							'status' => get_post_meta( $inv_id, 'status', true ) ?: 'draft',
						];
					}
				}
			}

			// Enrich results with invoice data.
			foreach ( $results as &$result ) {
				$inv = $invoice_map[ $result['id'] ] ?? null;
				$result['invoice_id']     = $inv ? $inv['id'] : null;
				$result['invoice_status'] = $inv ? $inv['status'] : null;
			}
			unset( $result );
		}

		// Sort by category priority, then name
		$category_order = $fees->get_category_sort_order( $season );
		usort(
			$results,
			function ( $a, $b ) use ( $category_order ) {
				$cat_cmp = ( $category_order[ $a['category'] ] ?? 99 ) <=> ( $category_order[ $b['category'] ] ?? 99 );
				if ( $cat_cmp !== 0 ) {
					return $cat_cmp;
				}
				return strcasecmp( $a['first_name'] . ' ' . $a['last_name'], $b['first_name'] . ' ' . $b['last_name'] );
			}
		);

		// Get category metadata for frontend
		$categories_raw  = $fees->get_categories_for_season( $season );
		$categories_meta = [];
		foreach ( $categories_raw as $slug => $category ) {
			$categories_meta[ $slug ] = [
				'label'      => $category['label'] ?? $slug,
				'sort_order' => $category['sort_order'] ?? 999,
				'is_youth'   => $category['is_youth'] ?? false,
			];
		}

		$billing_method             = $fees->get_billing_method( $season );
		$installment_plan_3_enabled = $fees->get_installment_plan_3_enabled( $season );
		$installment_plan_8_enabled = $fees->get_installment_plan_8_enabled( $season );

		return rest_ensure_response(
			[
				'season'                      => $season,
				'forecast'                    => (bool) $forecast,
				'total'                       => count( $results ),
				'members'                     => $results,
				'categories'                  => $categories_meta,
				'billing_method'              => $billing_method,
				'installment_plan_3_enabled'  => $installment_plan_3_enabled,
				'installment_plan_8_enabled'  => $installment_plan_8_enabled,
			]
		);
	}

	/**
	 * Get fee summary aggregated by category.
	 *
	 * Lightweight endpoint for the Overzicht tab — reads only the fee cache meta
	 * key from postmeta in a single SQL query, aggregates in PHP. No full post
	 * objects or meta cache priming needed.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_fee_summary( $request ) {
		global $wpdb;

		$forecast = $request->get_param( 'forecast' );
		$fees     = new \Rondo\Fees\MembershipFees();

		if ( $forecast ) {
			$season = $fees->get_next_season_key();
		} else {
			$season = $request->get_param( 'season' );
			if ( $season === null ) {
				$season = $fees->get_season_key();
			}
		}

		// Single SQL query to read only the fee cache meta values.
		// For forecast, we use the current season's cache but treat fee_after_discount
		// as final_fee (100% pro-rata) and exclude former members.
		$cache_season  = $forecast ? $fees->get_season_key() : $season;
		$fee_cache_key = $fees->get_fee_cache_meta_key( $cache_season );

		if ( $forecast ) {
			// Forecast: exclude members leaving before next season starts (lid-tot < July 1).
			$next_season_start = substr( $season, 0, 4 ) . '-07-01';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.meta_value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					LEFT JOIN {$wpdb->postmeta} lt ON lt.post_id = p.ID AND lt.meta_key = 'lid-tot'
					WHERE pm.meta_key = %s
					AND p.post_type = 'person'
					AND p.post_status = 'publish'
					AND (lt.meta_value IS NULL OR lt.meta_value = '' OR lt.meta_value >= %s)",
					$fee_cache_key,
					$next_season_start
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.meta_value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s
					AND p.post_type = 'person'
					AND p.post_status = 'publish'",
					$fee_cache_key
				)
			);
		}

		// Aggregate in PHP (unserialize each cached fee record)
		$aggregates    = [];
		$total_members = 0;

		// Pre-load youth slugs for forecast reclassification (only youth members age up)
		$current_youth_slugs = $forecast ? $fees->get_youth_category_slugs( $fees->get_season_key() ) : [];

		foreach ( $rows as $row ) {
			$fee_data = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $fee_data ) || empty( $fee_data['category'] ) ) {
				continue;
			}

			// Forecast: skip former members (they won't be members next season)
			if ( $forecast && ! empty( $fee_data['is_former_member'] ) ) {
				continue;
			}

			if ( $forecast ) {
				$current_cat = $fee_data['category'];

				// Only reclassify youth category members (non-youth are matched by team/werkfunctie)
				if ( in_array( $current_cat, $current_youth_slugs, true ) ) {
					$leeftijdsgroep = $fee_data['leeftijdsgroep'] ?? '';
					if ( ! empty( $leeftijdsgroep ) ) {
						$next_age_class = $fees->predict_next_season_age_class( $leeftijdsgroep );
						$next_cat       = $fees->get_category_by_age_class( $next_age_class, $season );
					} else {
						$next_cat = null;
					}
					$cat = $next_cat ?? $current_cat;
				} else {
					$cat = $current_cat;
				}
				$base_fee = $fees->get_fee( $cat, $season );

				// Recalculate family discount with new base fee but same rate
				$discount_rate   = $fee_data['family_discount_rate'] ?? 0;
				$discount_amount = round( $base_fee * $discount_rate, 2 );
				$final_fee       = $base_fee - $discount_amount;

				if ( ! isset( $aggregates[ $cat ] ) ) {
					$aggregates[ $cat ] = [ 'count' => 0, 'base_fee' => 0, 'family_discount' => 0, 'fee_after_discount' => 0, 'prorata_amount' => 0, 'final_fee' => 0 ];
				}
				$aggregates[ $cat ]['count']++;
				$aggregates[ $cat ]['base_fee']        += $base_fee;
				$aggregates[ $cat ]['family_discount'] += $discount_amount;
				$aggregates[ $cat ]['fee_after_discount'] += $final_fee; // Forecast assumes full season
				$aggregates[ $cat ]['prorata_amount']     += 0; // No pro-rata in forecast
				$aggregates[ $cat ]['final_fee']        += $final_fee;
			} else {
				$cat = $fee_data['category'];
				if ( ! isset( $aggregates[ $cat ] ) ) {
					$aggregates[ $cat ] = [ 'count' => 0, 'base_fee' => 0, 'family_discount' => 0, 'fee_after_discount' => 0, 'prorata_amount' => 0, 'final_fee' => 0 ];
				}
				$aggregates[ $cat ]['count']++;
				$aggregates[ $cat ]['base_fee']        += $fee_data['base_fee'] ?? 0;
				$aggregates[ $cat ]['family_discount'] += $fee_data['family_discount_amount'] ?? 0;

				// fee_after_discount exists in cache since calculate_full_fee (line 1702 in class-membership-fees.php)
				// Fallback calculation for older caches
				$fee_after_discount = $fee_data['fee_after_discount'] ?? ( $fee_data['base_fee'] - $fee_data['family_discount_amount'] );
				$aggregates[ $cat ]['fee_after_discount'] += $fee_after_discount;

				// prorata_amount = fee_after_discount - final_fee
				$final_fee     = $fee_data['final_fee'] ?? 0;
				$prorata_amount = $fee_after_discount - $final_fee;
				$aggregates[ $cat ]['prorata_amount'] += $prorata_amount;

				$aggregates[ $cat ]['final_fee']        += $final_fee;
			}
			$total_members++;
		}

		// Round aggregated values to avoid floating point artifacts
		foreach ( $aggregates as &$agg ) {
			$agg['base_fee']          = round( $agg['base_fee'], 2 );
			$agg['family_discount']   = round( $agg['family_discount'], 2 );
			$agg['fee_after_discount'] = round( $agg['fee_after_discount'], 2 );
			$agg['prorata_amount']    = round( $agg['prorata_amount'], 2 );
			$agg['final_fee']         = round( $agg['final_fee'], 2 );
		}
		unset( $agg );

		// Get category metadata for frontend
		$categories_raw  = $fees->get_categories_for_season( $season );
		$categories_meta = [];
		foreach ( $categories_raw as $slug => $category ) {
			$categories_meta[ $slug ] = [
				'label'      => $category['label'] ?? $slug,
				'sort_order' => $category['sort_order'] ?? 999,
				'is_youth'   => $category['is_youth'] ?? false,
			];
		}

		$billing_method             = $fees->get_billing_method( $season );
		$installment_plan_3_enabled = $fees->get_installment_plan_3_enabled( $season );
		$installment_plan_8_enabled = $fees->get_installment_plan_8_enabled( $season );

		return rest_ensure_response(
			[
				'season'                      => $season,
				'forecast'                    => false,
				'total'                       => $total_members,
				'aggregates'                  => $aggregates,
				'categories'                  => $categories_meta,
				'billing_method'              => $billing_method,
				'installment_plan_3_enabled'  => $installment_plan_3_enabled,
				'installment_plan_8_enabled'  => $installment_plan_8_enabled,
			]
		);
	}

	/**
	 * Get fee data for a single person
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response with fee data or error.
	 */
	public function get_person_fee( $request ) {
		$person_id = (int) $request->get_param( 'id' );
		$season    = $request->get_param( 'season' );

		// Verify person exists
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'not_found', 'Person not found', [ 'status' => 404 ] );
		}

		$fees = new \Rondo\Fees\MembershipFees();

		if ( $season === null ) {
			$season = $fees->get_season_key();
		}

		// Check if person is manually excluded from contributie
		if ( get_post_meta( $person_id, '_exclude_from_contributie', true ) ) {
			return rest_ensure_response(
				[
					'person_id'  => $person_id,
					'season'     => $season,
					'calculable' => false,
					'reason'     => 'manually_excluded',
					'message'    => 'Persoon is handmatig uitgesloten van contributie.',
				]
			);
		}

		// Check if person is a former member not in the requested season
		$is_former = ( get_field( 'former_member', $person_id ) == true );
		if ( $is_former && ! $fees->is_former_member_in_season( $person_id, $season ) ) {
			return rest_ensure_response(
				[
					'person_id'        => $person_id,
					'season'           => $season,
					'calculable'       => false,
					'is_former_member' => true,
					'message'          => 'Oud-lid valt niet binnen dit seizoen.',
				]
			);
		}

		// Get fee data with caching
		$fee_data = $fees->get_fee_for_person_cached( $person_id, $season );

		if ( $fee_data === null ) {
			// Person is not calculable (no valid category)
			return rest_ensure_response(
				[
					'person_id'  => $person_id,
					'season'     => $season,
					'calculable' => false,
					'message'    => 'Geen contributie berekening mogelijk voor deze persoon.',
				]
			);
		}

		// Look up category label from season config
		$season_categories = $fees->get_categories_for_season( $season );
		$category_label    = $season_categories[ $fee_data['category'] ]['label'] ?? $fee_data['category'];

		// Derive family_members and family_size from family_key if not already populated
		$family_members = $fee_data['family_members'] ?? [];
		$family_size    = $fee_data['family_size'];
		$family_key     = $fee_data['family_key'] ?? null;

		if ( $family_key !== null && empty( $family_members ) && ( $fee_data['family_position'] ?? 0 ) > 0 ) {
			// Derive siblings from family_key: find other youth persons at same address
			$groups         = $fees->build_family_groups( $season );
			$group_families = $groups['families'];
			$group_members  = $group_families[ $family_key ] ?? [];

			$family_size = count( $group_members );
			foreach ( $group_members as $member_id ) {
				if ( (int) $member_id !== $person_id ) {
					$first_name = get_field( 'first_name', $member_id ) ?: '';
					$infix      = get_field( 'infix', $member_id ) ?: '';
					$last_name  = get_field( 'last_name', $member_id ) ?: '';
					$name       = implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) );
					if ( empty( $name ) ) {
						$name = get_the_title( $member_id );
					}
					$family_members[] = [
						'id'   => (int) $member_id,
						'name' => $name,
					];
				}
			}
		}

		// Get Nikki data for this year
		$nikki_year  = substr( $season, 0, 4 );
		$nikki_total = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_total', true );
		$nikki_saldo = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_saldo', true );

		// Get financiele-blokkade field
		$financiele_blokkade = get_field( 'financiele-blokkade', $person_id );

		// Get billing method for this season
		$billing_method = $fees->get_billing_method( $season );

		return rest_ensure_response(
			[
				'person_id'              => $person_id,
				'season'                 => $season,
				'calculable'             => true,
				'category'               => $fee_data['category'],
				'category_label'         => $category_label,
				'leeftijdsgroep'         => $fee_data['leeftijdsgroep'],
				'base_fee'               => $fee_data['base_fee'],
				'family_discount_rate'   => $fee_data['family_discount_rate'],
				'family_discount_amount' => $fee_data['family_discount_amount'],
				'fee_after_discount'     => $fee_data['fee_after_discount'],
				'prorata_percentage'     => $fee_data['prorata_percentage'],
				'final_fee'              => $fee_data['final_fee'],
				'family_key'             => $family_key,
				'family_size'            => $family_size,
				'family_position'        => $fee_data['family_position'],
				'family_members'         => $family_members,
				'lid_sinds'              => $fee_data['registration_date'] ?? null,
				'from_cache'             => $fee_data['from_cache'] ?? false,
				'calculated_at'          => $fee_data['calculated_at'] ?? null,
				'nikki_total'            => $nikki_total !== '' ? (float) $nikki_total : null,
				'nikki_saldo'            => $nikki_saldo !== '' ? (float) $nikki_saldo : null,
				'financiele_blokkade'    => (bool) $financiele_blokkade,
				'is_former_member'       => $is_former,
				'billing_method'         => $billing_method,
			]
		);
	}

	/**
	 * Trigger bulk fee recalculation
	 *
	 * Admin-only endpoint to clear all fee caches and run recalculation synchronously.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with recalculation status.
	 */
	public function recalculate_all_fees( $request ) {
		$fees   = new \Rondo\Fees\MembershipFees();
		$season = $request->get_param( 'season' );

		if ( $season === null ) {
			$season = $fees->get_season_key();
		}

		// Clear all caches and family discount meta
		$cleared = $fees->clear_all_fee_caches( $season );
		$fees->clear_all_family_discount_meta();

		// Run recalculation synchronously
		$invalidator = new \Rondo\Fees\FeeCacheInvalidator();
		$invalidator->recalculate_all_fees_background( $season );

		return rest_ensure_response(
			[
				'success'       => true,
				'season'        => $season,
				'cleared_count' => $cleared,
				'message'       => sprintf(
					'%d contributies herberekend voor seizoen %s.',
					$cleared,
					$season
				),
			]
		);
	}

	/**
	 * Get billing settings for a season.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Billing settings.
	 */
	public function get_billing_settings( $request ) {
		$fees   = new \Rondo\Fees\MembershipFees();
		$season = $request->get_param( 'season' );

		if ( $season === null ) {
			$season = $fees->get_season_key();
		}

		return rest_ensure_response(
			[
				'season'                     => $season,
				'billing_method'             => $fees->get_billing_method( $season ),
				'installment_plan_3_enabled' => $fees->get_installment_plan_3_enabled( $season ),
				'installment_plan_8_enabled' => $fees->get_installment_plan_8_enabled( $season ),
				'installment_admin_fee'      => $fees->get_installment_admin_fee( $season ),
			]
		);
	}

	/**
	 * Update billing settings for a season.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Updated billing settings.
	 */
	public function update_billing_settings( $request ) {
		$fees   = new \Rondo\Fees\MembershipFees();
		$season = $request->get_param( 'season' );

		$billing_method = $request->get_param( 'billing_method' );
		if ( $billing_method !== null ) {
			$fees->set_billing_method( $billing_method, $season );
		}

		$plan_3_enabled = $request->get_param( 'installment_plan_3_enabled' );
		if ( $plan_3_enabled !== null ) {
			$fees->set_installment_plan_3_enabled( (bool) $plan_3_enabled, $season );
		}

		$plan_8_enabled = $request->get_param( 'installment_plan_8_enabled' );
		if ( $plan_8_enabled !== null ) {
			$fees->set_installment_plan_8_enabled( (bool) $plan_8_enabled, $season );
		}

		$installment_admin_fee = $request->get_param( 'installment_admin_fee' );
		if ( $installment_admin_fee !== null ) {
			$fees->set_installment_admin_fee( (float) $installment_admin_fee, $season );
		}

		return rest_ensure_response(
			[
				'season'                     => $season,
				'billing_method'             => $fees->get_billing_method( $season ),
				'installment_plan_3_enabled' => $fees->get_installment_plan_3_enabled( $season ),
				'installment_plan_8_enabled' => $fees->get_installment_plan_8_enabled( $season ),
				'installment_admin_fee'      => $fees->get_installment_admin_fee( $season ),
			]
		);
	}

	/**
	 * Start a bulk invoice creation job.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Job status or error if already running.
	 */
	public function start_bulk_invoice_job( $request ) {
		$fees   = new \Rondo\Fees\MembershipFees();
		$season = $request->get_param( 'season' );

		if ( $season === null ) {
			$season = $fees->get_season_key();
		}

		$result = \Rondo\Finance\BulkInvoiceCreator::start_job( $season );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get bulk invoice job status.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Job status.
	 */
	public function get_bulk_invoice_job_status( $request ) {
		return rest_ensure_response( \Rondo\Finance\BulkInvoiceCreator::get_job_status() );
	}

	/**
	 * Create a membership invoice for a single person.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Invoice data or error.
	 */
	public function create_single_membership_invoice( $request ) {
		$fees      = new \Rondo\Fees\MembershipFees();
		$person_id = (int) $request->get_param( 'person_id' );
		$season    = $request->get_param( 'season' );

		if ( $season === null ) {
			$season = $fees->get_season_key();
		}

		// Verify person exists.
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'not_found', 'Person not found', [ 'status' => 404 ] );
		}

		// Check fee first to return appropriate error codes.
		$fee_data = $fees->get_fee_for_person_cached( $person_id, $season );
		if ( $fee_data === null ) {
			return new \WP_Error(
				'no_fee',
				'Geen contributie berekening mogelijk voor deze persoon.',
				[ 'status' => 400 ]
			);
		}

		$creator = new \Rondo\Finance\BulkInvoiceCreator();
		$result  = $creator->create_membership_invoice( $person_id, $season );

		if ( $result === 'error' ) {
			return new \WP_Error(
				'invoice_creation_failed',
				'Factuur aanmaken mislukt.',
				[ 'status' => 500 ]
			);
		}

		if ( $result === 'skipped' ) {
			return new \WP_Error(
				'invoice_already_exists',
				'Er bestaat al een contributie factuur voor dit lid in dit seizoen.',
				[ 'status' => 409 ]
			);
		}

		// Created: find the new invoice.
		$invoices = get_posts(
			[
				'post_type'      => 'rondo_invoice',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'suppress_filters' => true,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => 'person',
						'value' => $person_id,
					],
					[
						'key'   => '_invoice_season',
						'value' => $season,
					],
					[
						'key'   => 'invoice_type',
						'value' => 'membership',
					],
				],
			]
		);

		$invoice_id = $invoices[0] ?? null;

		return rest_ensure_response(
			[
				'created'    => true,
				'invoice_id' => $invoice_id,
				'person_id'  => $person_id,
				'season'     => $season,
			]
		);
	}

	/**
	 * Trigger VOG recalculation for all people
	 *
	 * Recalculates volunteer status for all people to reflect changes in exempt commissies.
	 *
	 * @return int Number of people recalculated
	 */
	private function trigger_vog_recalculation(): int {
		$volunteer_status = new \Rondo\Core\VolunteerStatus();

		$people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			]
		);

		foreach ( $people as $person_id ) {
			$volunteer_status->calculate_and_update_status( $person_id );
		}

		return count( $people );
	}

	/**
	 * Get all distinct job_title values from work_history across all person posts.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response List of distinct role names.
	 */
	public function get_available_volunteer_roles( $request ) {
		global $wpdb;

		// ACF repeater stores work_history rows as post meta with keys like:
		// work_history_0_job_title, work_history_1_job_title, etc.
		$results = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key LIKE 'work_history_%_job_title'
			 AND meta_value != ''
			 ORDER BY meta_value ASC"
		);

		return rest_ensure_response( $results ?: [] );
	}

	/**
	 * Get current volunteer role classification settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Settings with current and default role arrays.
	 */
	public function get_volunteer_role_settings( $request ) {
		return rest_ensure_response(
			[
				'player_roles'           => \Rondo\Core\VolunteerStatus::get_player_roles(),
				'excluded_roles'         => \Rondo\Core\VolunteerStatus::get_excluded_roles(),
				'default_player_roles'   => \Rondo\Core\VolunteerStatus::get_default_player_roles(),
				'default_excluded_roles' => \Rondo\Core\VolunteerStatus::get_default_excluded_roles(),
			]
		);
	}

	/**
	 * Update volunteer role classification settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Updated settings with recalculation count.
	 */
	public function update_volunteer_role_settings( $request ) {
		$player_roles   = $request->get_param( 'player_roles' );
		$excluded_roles = $request->get_param( 'excluded_roles' );

		if ( $player_roles !== null ) {
			update_option( \Rondo\Core\VolunteerStatus::OPTION_PLAYER_ROLES, $player_roles );
		}

		if ( $excluded_roles !== null ) {
			update_option( \Rondo\Core\VolunteerStatus::OPTION_EXCLUDED_ROLES, $excluded_roles );
		}

		// Trigger volunteer status recalculation for all people
		$people_recalculated = $this->trigger_vog_recalculation();

		return rest_ensure_response(
			[
				'player_roles'        => \Rondo\Core\VolunteerStatus::get_player_roles(),
				'excluded_roles'      => \Rondo\Core\VolunteerStatus::get_excluded_roles(),
				'people_recalculated' => $people_recalculated,
			]
		);
	}

	/**
	 * Get all distinct werkfunctie values from the database.
	 *
	 * Derives job titles from current people work_history rows and returns
	 * unique werkfunctie values sorted alphabetically.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response List of distinct werkfunctie names.
	 */
	public function get_available_werkfuncties( $request ) {
		$people = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => 'work_history',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$all_werkfuncties = [];
		foreach ( $people as $person_id ) {
			$work_history = get_field( 'work_history', $person_id ) ?: [];
			foreach ( $work_history as $position ) {
				if ( ! empty( $position['job_title'] ) && is_string( $position['job_title'] ) ) {
					$job_title = trim( $position['job_title'] );
					if ( $job_title !== '' ) {
						$all_werkfuncties[ $job_title ] = true;
					}
				}
			}
		}

		$unique = array_keys( $all_werkfuncties );
		sort( $unique );

		return rest_ensure_response( $unique );
	}

	/**
	 * Get all Rondo roles as [ slug, label ] pairs.
	 *
	 * @return array[] Array of [ 'slug' => string, 'label' => string ].
	 */
	private function get_rondo_roles_list(): array {
		$roles = [];
		foreach ( \Rondo\Core\UserRoles::ROLES as $slug => $data ) {
			$roles[] = [ 'slug' => $slug, 'label' => $data[0] ];
		}
		return $roles;
	}

	/**
	 * Get the current Functie-to-Role capability mapping.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The current map plus all available Rondo roles.
	 */
	public function get_functie_capability_map( $request ) {
		return rest_ensure_response(
			[
				'map'   => \Rondo\Config\FunctieCapabilityMap::get_map(),
				'roles' => $this->get_rondo_roles_list(),
			]
		);
	}

	/**
	 * Update the Functie-to-Role capability mapping.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The updated map plus all available Rondo roles.
	 */
	public function update_functie_capability_map( $request ) {
		$raw_map = $request->get_param( 'map' );

		// Sanitize: ensure all keys are strings and all values are arrays of booleans
		$sanitized = [];
		if ( is_array( $raw_map ) ) {
			foreach ( $raw_map as $functie => $role_flags ) {
				$key = sanitize_text_field( $functie );
				if ( ! is_array( $role_flags ) ) {
					continue;
				}
				$sanitized[ $key ] = [];
				foreach ( $role_flags as $role_slug => $enabled ) {
					$sanitized[ $key ][ sanitize_text_field( $role_slug ) ] = (bool) $enabled;
				}
			}
		}

		\Rondo\Config\FunctieCapabilityMap::update_map( $sanitized );

		return rest_ensure_response(
			[
				'map'   => \Rondo\Config\FunctieCapabilityMap::get_map(),
				'roles' => $this->get_rondo_roles_list(),
			]
		);
	}

	/**
	 * Get the current Commissie-to-Role capability mapping.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The current map, all commissies, and available roles.
	 */
	public function get_commissie_capability_map( $request ) {
		$commissies = get_posts(
			[
				'post_type'      => 'commissie',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		$commissie_list = [];
		foreach ( $commissies as $c ) {
			$commissie_list[] = [
				'id'   => $c->ID,
				'name' => $c->post_title,
			];
		}

		return rest_ensure_response(
			[
				'map'        => \Rondo\Config\CommissieCapabilityMap::get_map(),
				'commissies' => $commissie_list,
				'roles'      => $this->get_rondo_roles_list(),
			]
		);
	}

	/**
	 * Update the Commissie-to-Role capability mapping.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The updated map plus all available Rondo roles.
	 */
	public function update_commissie_capability_map( $request ) {
		$raw_map = $request->get_param( 'map' );

		$sanitized = [];
		if ( is_array( $raw_map ) ) {
			foreach ( $raw_map as $commissie_id => $role_flags ) {
				$key = sanitize_text_field( $commissie_id );
				if ( ! is_array( $role_flags ) ) {
					continue;
				}
				$sanitized[ $key ] = [];
				foreach ( $role_flags as $role_slug => $enabled ) {
					$sanitized[ $key ][ sanitize_text_field( $role_slug ) ] = (bool) $enabled;
				}
			}
		}

		\Rondo\Config\CommissieCapabilityMap::update_map( $sanitized );

		// Re-fetch full response (reuse GET handler logic).
		return $this->get_commissie_capability_map( $request );
	}

	/**
	 * Bulk send VOG emails to multiple people
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with results per person.
	 */
	public function bulk_send_vog_emails( $request ) {
		$ids       = $request->get_param( 'ids' );
		$vog_email = new \Rondo\VOG\VOGEmail();

		$results = [];
		$sent    = 0;
		$failed  = 0;

		foreach ( $ids as $person_id ) {
			// Determine template type based on datum-vog
			$datum_vog     = get_field( 'datum-vog', $person_id );
			$template_type = empty( $datum_vog ) ? 'new' : 'renewal';

			$result = $vog_email->send( (int) $person_id, $template_type );

			if ( $result === true ) {
				++$sent;
				$results[] = [
					'id'      => $person_id,
					'success' => true,
					'type'    => $template_type,
				];
			} else {
				++$failed;
				$results[] = [
					'id'      => $person_id,
					'success' => false,
					'error'   => is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error',
				];
			}
		}

		return rest_ensure_response(
			[
				'results' => $results,
				'sent'    => $sent,
				'failed'  => $failed,
				'total'   => count( $ids ),
			]
		);
	}

	/**
	 * Bulk mark VOG as submitted to Justis
	 *
	 * Records the current date in the vog_justis_submitted_date post meta.
	 * Used to track when the VOG request was submitted to the Justis system.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with results.
	 */
	public function bulk_mark_vog_justis( $request ) {
		$ids          = $request->get_param( 'ids' );
		$current_date = current_time( 'Y-m-d' );

		$marked  = 0;
		$failed  = 0;
		$results = [];

		foreach ( $ids as $person_id ) {
			$person = get_post( (int) $person_id );

			if ( ! $person || 'person' !== $person->post_type ) {
				++$failed;
				$results[] = [
					'id'      => $person_id,
					'success' => false,
					'error'   => 'Invalid person ID',
				];
				continue;
			}

			// Update post meta for Justis submission date
			update_post_meta( $person_id, 'vog_justis_submitted_date', $current_date );
			++$marked;
			$results[] = [
				'id'      => $person_id,
				'success' => true,
			];
		}

		return rest_ensure_response(
			[
				'results' => $results,
				'marked'  => $marked,
				'failed'  => $failed,
				'total'   => count( $ids ),
			]
		);
	}

	/**
	 * Bulk send VOG reminder emails
	 *
	 * Sends VOG reminder emails to selected people. Determines the correct template
	 * (reminder_new or reminder_renewal) based on the presence of an existing VOG date.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with results.
	 */
	public function bulk_send_vog_reminders( $request ) {
		$ids       = $request->get_param( 'ids' );
		$vog_email = new \Rondo\VOG\VOGEmail();

		$results = [];
		$sent    = 0;
		$failed  = 0;

		foreach ( $ids as $person_id ) {
			// Determine template type based on datum-vog
			$datum_vog     = get_field( 'datum-vog', $person_id );
			$template_type = empty( $datum_vog ) ? 'reminder_new' : 'reminder_renewal';

			$result = $vog_email->send_reminder( (int) $person_id, $template_type );

			if ( $result === true ) {
				++$sent;
				$results[] = [
					'id'      => $person_id,
					'success' => true,
					'type'    => $template_type,
				];
			} else {
				++$failed;
				$results[] = [
					'id'      => $person_id,
					'success' => false,
					'error'   => is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error',
				];
			}
		}

		return rest_ensure_response(
			[
				'results' => $results,
				'sent'    => $sent,
				'failed'  => $failed,
				'total'   => count( $ids ),
			]
		);
	}

	/**
	 * Get the current season term
	 *
	 * @return \WP_REST_Response Response with current season data or null.
	 */
	public function get_current_season() {
		$taxonomies     = new \RONDO_Taxonomies();
		$current_season = $taxonomies->get_current_season();

		if ( ! $current_season ) {
			return rest_ensure_response( null );
		}

		return rest_ensure_response(
			[
				'id'   => $current_season->term_id,
				'name' => $current_season->name,
				'slug' => $current_season->slug,
			]
		);
	}

	/**
	 * Proxy a single-member sync request to the Rondo Sync server.
	 *
	 * After a successful Sportlink sync, also triggers a capability sync for
	 * the linked WordPress user so role mappings are applied immediately.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response from sync server or error.
	 */
	public function sync_individual_from_sportlink( $request ) {
		if ( ! defined( 'RONDO_SYNC_URL' ) || ! defined( 'RONDO_SYNC_API_KEY' ) ) {
			return new \WP_Error(
				'sync_not_configured',
				'Sportlink sync is not configured. Add RONDO_SYNC_URL and RONDO_SYNC_API_KEY to wp-config.php.',
				[ 'status' => 500 ]
			);
		}

		$knvb_id  = $request->get_param( 'knvb_id' );
		$response = wp_remote_post(
			rtrim( RONDO_SYNC_URL, '/' ) . '/api/sync/individual',
			[
				'headers' => [
					'Content-Type'   => 'application/json',
					'X-Sync-API-Key' => RONDO_SYNC_API_KEY,
				],
				'body'    => wp_json_encode( [ 'knvb_id' => $knvb_id ] ),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'sync_request_failed',
				$response->get_error_message(),
				[ 'status' => 502 ]
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			return new \WP_Error(
				'sync_error',
				$body['error'] ?? 'Sync server returned an error.',
				[ 'status' => $status_code ]
			);
		}

		// After a successful Sportlink sync, trigger capability sync for the linked WP user.
		// The Sportlink sync may have updated work_history; we re-apply role mappings now.
		// Look up the person by KNVB ID via ACF field meta, then sync via person ID.
		$person_id = $this->find_person_id_by_knvb_id( $knvb_id );
		if ( $person_id ) {
			$deduped_count = $this->dedupe_person_work_history_entries( $person_id );
			if ( $deduped_count > 0 ) {
				$body['work_history_deduped'] = $deduped_count;
			}

			$cap_sync        = new \Rondo\Users\CapabilitySync();
			$cap_sync_result = $cap_sync->sync_user_by_person_id( $person_id );
			if ( is_array( $cap_sync_result ) ) {
				$body['capability_sync'] = $cap_sync_result;
			}
		}

		return rest_ensure_response( $body );
	}

	/**
	 * Find a person post ID by their KNVB ID stored in ACF meta.
	 *
	 * @param string $knvb_id The KNVB member ID.
	 * @return int|null Person post ID, or null if not found.
	 */
	private function find_person_id_by_knvb_id( string $knvb_id ): ?int {
		// ACF stores the field key as meta for the 'knvb-id' field.
		// The raw post meta key is 'knvb-id'.
		$posts = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => 'knvb-id',
						'value' => $knvb_id,
					],
				],
			]
		);

		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	/**
	 * Normalize one work history row into a stable dedupe key.
	 *
	 * @param array $row Raw ACF work_history row.
	 * @return string Stable serialized key.
	 */
	private function get_work_history_dedupe_key( array $row ): string {
		$team = $row['team'] ?? '';
		if ( is_array( $team ) ) {
			$team = $team['ID'] ?? $team['id'] ?? $team['post_id'] ?? '';
		}

		$team = is_numeric( $team ) ? (string) (int) $team : trim( (string) $team );

		$normalized = [
			'team'        => $team,
			'entity_type' => strtolower( trim( (string) ( $row['entity_type'] ?? '' ) ) ),
			'job_title'   => trim( (string) ( $row['job_title'] ?? '' ) ),
			'description' => trim( (string) ( $row['description'] ?? '' ) ),
			'start_date'  => trim( (string) ( $row['start_date'] ?? '' ) ),
			'end_date'    => trim( (string) ( $row['end_date'] ?? '' ) ),
			'is_current'  => ! empty( $row['is_current'] ) ? '1' : '0',
		];

		return wp_json_encode( $normalized );
	}

	/**
	 * Remove exact duplicate work_history rows for a person.
	 *
	 * Duplicates are determined by normalized team/context + role + dates + current flag.
	 *
	 * @param int $person_id Person post ID.
	 * @return int Number of removed duplicate rows.
	 */
	private function dedupe_person_work_history_entries( int $person_id ): int {
		$work_history = get_field( 'work_history', $person_id );
		if ( ! is_array( $work_history ) || empty( $work_history ) ) {
			return 0;
		}

		$seen          = [];
		$deduped       = [];
		$removed_count = 0;

		foreach ( $work_history as $row ) {
			if ( ! is_array( $row ) ) {
				$deduped[] = $row;
				continue;
			}

			$key = $this->get_work_history_dedupe_key( $row );
			if ( isset( $seen[ $key ] ) ) {
				++$removed_count;
				continue;
			}

			$seen[ $key ] = true;
			$deduped[]    = $row;
		}

		if ( $removed_count > 0 ) {
			update_field( 'work_history', $deduped, $person_id );
		}

		return $removed_count;
	}

	/**
	 * Sync capabilities for a single user by KNVB ID (admin only).
	 *
	 * Called by rondo-sync per member during capability sync pipeline step.
	 * Returns { status: 'no_user' } with HTTP 200 if no WP user has the given KNVB ID.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Sync result or WP_Error.
	 */
	public function sync_user_capabilities( $request ) {
		$knvb_id  = sanitize_text_field( $request->get_param( 'knvb_id' ) );
		$functies = array_map( 'sanitize_text_field', (array) $request->get_param( 'functies' ) );

		$sync   = new \Rondo\Users\CapabilitySync();
		$result = $sync->sync_user_by_knvb_id( $knvb_id, $functies );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Sync capabilities for all provisioned users (admin only).
	 *
	 * Body-less endpoint: the server derives functies from each user's linked
	 * person's work_history ACF field (is_current entries). Used by the
	 * on-demand "Sync now" button in the Settings Functies tab.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Aggregated sync result.
	 */
	public function sync_all_capabilities( $request ) {
		$sync   = new \Rondo\Users\CapabilitySync();
		$result = $sync->sync_all();

		return rest_ensure_response( $result );
	}

	/**
	 * Sync capabilities for the WordPress user linked to a single person (admin only).
	 *
	 * Derives functies from the person's current work_history ACF field and
	 * applies the FunctieCapabilityMap. Used by the per-person "Sync rollen"
	 * button in AccountCard. Returns { status: 'no_user' } if the person has
	 * no linked WordPress account.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Sync result or WP_Error.
	 */
	public function sync_person_capabilities( $request ) {
		$person_id = (int) $request->get_param( 'id' );

		$sync   = new \Rondo\Users\CapabilitySync();
		$result = $sync->sync_user_by_person_id( $person_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
