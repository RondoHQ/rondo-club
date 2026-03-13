<?php
/**
 * Extended REST API Endpoints
 */

namespace Rondo\REST;

use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api extends Base {

	private const KADERLIJST_SNAPSHOT_OPTION  = 'rondo_kaderlijst_snapshot';
	private const KADERLIJST_UPDATED_OPTION = 'rondo_kaderlijst_snapshot_updated_at';

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

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_acf_fields' ] );
		// NOTE: save_post_person hook for volunteer start date cache invalidation
		// moved to Rondo\REST\Reminders constructor
	}

	/**
	 * Register custom REST routes
	 */
	public function register_routes() {
		// NOTE: Reminders, anniversaries, user settings, users, VOG, and fees routes
		// have been extracted to dedicated controllers. See:
		// - class-rest-reminders.php (Rondo\REST\Reminders)
		// - class-rest-user-settings.php (Rondo\REST\UserSettings)
		// - class-rest-users.php (Rondo\REST\Users)
		// - class-rest-vog.php (Rondo\REST\Vog)
		// - class-rest-fees.php (Rondo\REST\Fees)
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
						'lettermint_verification_email_subject' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'lettermint_verification_email_body' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'lettermint_verification_from_email' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_email',
							'validate_callback' => function ( $param ) {
								return $param === null || $param === '' || is_email( $param );
							},
						],
						'lettermint_verification_from_name' => [
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

		// Lettermint verification email for bounced-address follow-up tasks.
		register_rest_route(
			'rondo/v1',
			'/lettermint/verify-email',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_lettermint_verification_email' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'todo_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					],
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
						'mollie_accounts'       => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
						'payment_term_days'     => [ 'required' => false, 'type' => 'integer' ],
						'payment_clause'        => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
						'membership_payment_clause' => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
						'email_template'             => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'membership_email_template'  => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'installment_email_template' => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'reminder_1_email_template'  => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'reminder_2_email_template'  => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'credit_email_template'      => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'regular_invoice_email_subject' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'regular_invoice_email_body'    => [ 'required' => false, 'sanitize_callback' => 'wp_kses_post' ],
						'regular_invoice_email_heading'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'discipline_email_heading'         => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'membership_email_heading'         => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'installment_email_heading'        => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'reminder_1_email_heading'         => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'reminder_2_email_heading'         => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'invoice_reminder_1_email_heading' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'invoice_reminder_2_email_heading' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'credit_email_heading'             => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'rabobank_client_id'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'rabobank_client_secret' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'rabobank_environment'  => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
						'mollie_redirect_url'     => [ 'required' => false, 'sanitize_callback' => 'esc_url_raw' ],
						'mollie_default_membership_account_id' => [ 'required' => false, 'sanitize_callback' => 'sanitize_key' ],
						'mollie_default_discipline_account_id' => [ 'required' => false, 'sanitize_callback' => 'sanitize_key' ],
						'mollie_default_manual_account_id'     => [ 'required' => false, 'sanitize_callback' => 'sanitize_key' ],
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

		// Finance template test email (financieel capability required)
		register_rest_route(
			'rondo/v1',
			'/finance/test-email',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_finance_test_email' ],
				'permission_callback' => [ $this, 'check_financieel_permission' ],
				'args'                => [
					'template_type' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return in_array( $param, [
								'regular_invoice',
								'discipline',
								'membership',
								'installment',
								'reminder_1',
								'reminder_2',
								'invoice_reminder_1',
								'invoice_reminder_2',
								'credit',
							], true );
						},
					],
					'recipient' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
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

		// Sportlink individual sync (admin and toegangscontrole users)
		register_rest_route(
			'rondo/v1',
			'/sportlink/sync-individual',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_individual_from_sportlink' ],
				'permission_callback' => [ $this, 'check_admin_or_toegangscontrole_permission' ],
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

		// Capability matrix (admin only — manage role×capability assignments)
		register_rest_route(
			'rondo/v1',
			'/settings/capability-matrix',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_capability_matrix' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_capability_matrix' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'roles' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Age-group access (admin only — per-role leeftijdsgroep restrictions)
		register_rest_route(
			'rondo/v1',
			'/settings/age-group-access',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_age_group_access' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_age_group_access' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'roles' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Custom role management (admin only — create/delete custom roles)
		register_rest_route(
			'rondo/v1',
			'/settings/roles',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_custom_role' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'label' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( trim( $param ) );
						},
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/settings/roles/(?P<slug>[a-z0-9_]+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_custom_role' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
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

		// NOTE: VOG response filters (rest_prepare_person, rest_prepare_discipline_case)
		// moved to Rondo\REST\Vog::register_response_filters()
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
	 * Find a person by email address (for sync deduplication)
	 *
	 * Searches all people for a matching email in fixed fields.
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

		// Search by email_1 first, then email_2.
		foreach ( [ 'email_1', 'email_2' ] as $field ) {
			$matches = get_posts(
				[
					'post_type'        => 'person',
					'posts_per_page'   => 1,
					'post_status'      => 'publish',
					'suppress_filters' => true,
					'meta_query'       => [
						[
							'key'     => $field,
							'value'   => $email,
							'compare' => '=',
						],
					],
				]
			);

			if ( ! empty( $matches ) ) {
				return new \WP_REST_Response( [ 'id' => $matches[0]->ID ], 200 );
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

			// Query 6: KNVB ID matches (score: 70)
			$knvb_matches = get_posts(
				[
					'post_type'      => 'person',
					'posts_per_page' => 20,
					'post_status'    => 'publish',
					'meta_query'     => [
						'relation' => 'OR',
						[
							'key'     => 'knvb-id',
							'value'   => $query,
							'compare' => 'LIKE',
						],
						[
							'key'     => 'custom_knvb-id',
							'value'   => $query,
							'compare' => 'LIKE',
						],
					],
				]
			);

			foreach ( $knvb_matches as $person ) {
				if ( ! isset( $people_results[ $person->ID ] ) ) {
					$people_results[ $person->ID ] = [
						'person' => $person,
						'score'  => 70,
					];
				}
			}

			// Query 7: Contact email matches in email_1/email_2 fields (score: 75)
			if ( strpos( $query, '@' ) !== false || is_email( $query ) ) {
				$email_matches = $this->find_people_by_contact_email_fragment( $query, 20 );

				foreach ( $email_matches as $person ) {
					if ( ! isset( $people_results[ $person->ID ] ) ) {
						$people_results[ $person->ID ] = [
							'person' => $person,
							'score'  => 75,
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
	 * Count open todos visible to the current user.
	 *
	 * Visibility rule:
	 * - user is post author (creator), OR
	 * - user is the assigned user in `assigned_user_id` post meta.
	 */
	private function count_open_todos() {
		global $wpdb;
		$current_user_id = get_current_user_id();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm
				   ON pm.post_id = p.ID
				  AND pm.meta_key = %s
				 WHERE p.post_type = %s
				   AND p.post_status = %s
				   AND (p.post_author = %d OR CAST(pm.meta_value AS UNSIGNED) = %d)",
				'assigned_user_id',
				'rondo_todo',
				'rondo_open',
				$current_user_id,
				$current_user_id
			)
		);
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
	 * Count awaiting todos visible to the current user.
	 *
	 * Visibility rule:
	 * - user is post author (creator), OR
	 * - user is the assigned user in `assigned_user_id` post meta.
	 */
	private function count_awaiting_todos() {
		global $wpdb;
		$current_user_id = get_current_user_id();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm
				   ON pm.post_id = p.ID
				  AND pm.meta_key = %s
				 WHERE p.post_type = %s
				   AND p.post_status = %s
				   AND (p.post_author = %d OR CAST(pm.meta_value AS UNSIGNED) = %d)",
				'assigned_user_id',
				'rondo_todo',
				'rondo_awaiting',
				$current_user_id,
				$current_user_id
			)
		);
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

		$lettermint_verification_email_subject = $request->get_param( 'lettermint_verification_email_subject' );
		if ( $lettermint_verification_email_subject !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_verification_email_subject( $lettermint_verification_email_subject );
		}

		$lettermint_verification_email_body = $request->get_param( 'lettermint_verification_email_body' );
		if ( $lettermint_verification_email_body !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_verification_email_body( $lettermint_verification_email_body );
		}

		$lettermint_verification_from_email = $request->get_param( 'lettermint_verification_from_email' );
		if ( $lettermint_verification_from_email !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_verification_from_email( $lettermint_verification_from_email );
		}

		$lettermint_verification_from_name = $request->get_param( 'lettermint_verification_from_name' );
		if ( $lettermint_verification_from_name !== null ) {
			\Rondo\Config\ClubConfig::update_lettermint_verification_from_name( $lettermint_verification_from_name );
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

		$body    = EmailTemplate::render(
			[
				'brand_name' => 'Rondo Club',
				'preheader'  => $subject,
				'eyebrow'    => 'Lettermint',
				'heading'    => 'Testmail',
				'body_html'  => EmailTemplate::format_plain_text( $body ),
				'cta_url'    => home_url( '/' ),
				'cta_label'  => 'Open site',
			]
		);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
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
	 * Send a finance template test email with dummy placeholder data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_finance_test_email( $request ) {
		$template_type = (string) $request->get_param( 'template_type' );
		$recipient     = sanitize_email( (string) $request->get_param( 'recipient' ) );

		$config   = new \Rondo\Config\FinanceConfig();
		$org_name = $config->get_display_name();

		// Generate a test QR code the same way real invoices do: save PNG to uploads, reference via public URL.
		$settings_url = admin_url( 'admin.php?page=rondo#/financien/instellingen' );
		$qr_code_html = '';
		$upload_dir   = wp_upload_dir();
		$invoices_dir = $upload_dir['basedir'] . '/invoices';
		wp_mkdir_p( $invoices_dir );

		$qr_test_path = $invoices_dir . '/qr-test.png';
		$qr_result    = \Rondo\Finance\QrCodeGenerator::generate_to_path( $settings_url, $qr_test_path );

		if ( ! is_wp_error( $qr_result ) ) {
			$qr_url       = $upload_dir['baseurl'] . '/invoices/qr-test.png';
			$qr_code_html = '<img src="' . esc_url( $qr_url ) . '" alt="QR Code betaallink" width="200" style="display:block;" />';
		}

		// Dummy data for placeholder substitution.
		$dummy = [
			'{naam}'               => 'Jan Jansen',
			'{voornaam}'           => 'Jan',
			'{factuur_nummer}'     => 'C-2025-0042',
			'{totaal_bedrag}'      => '&euro; 230,00',
			'{betaallink}'         => '<a href="https://example.com/betaling/test" style="color:#0891b2;text-decoration:underline;">https://example.com/betaling/test</a>',
			'{betaalknop}'         => EmailTemplate::render_cta_button( 'https://example.com/betaling/test', 'Open betaallink' ),
			'{qr_code}'            => $qr_code_html,
			'{organisatie_naam}'   => esc_html( $org_name ),
			'{tuchtzaken_lijst}'   => '<table style="width:100%;border-collapse:collapse;font-size:14px;"><thead><tr style="background-color:#f3f4f6;"><th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Datum</th><th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Wedstrijd</th><th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Kaart</th><th style="padding:8px 12px;text-align:right;border-bottom:2px solid #d1d5db;">Bedrag</th></tr></thead><tbody><tr><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">01-03-2025</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">Club A - Club B</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">Geel</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">&euro; 15,00</td></tr></tbody></table>',
			'{termijn_nummer}'     => '2',
			'{totaal_termijnen}'   => '3',
			'{termijn_bedrag}'     => '&euro; 76,67',
			'{vervaldatum}'        => '25 maart 2025',
			'{dagen_te_laat}'      => '14',
			'{factuurdatum}'       => '1 februari 2025',
			'{dagen_sinds_factuur}' => '22',
		];

		$eyebrow = 'Factuur C-2025-0042';

		// Select template and email wrapper args based on type.
		switch ( $template_type ) {
			case 'regular_invoice':
				$template = $config->get_regular_invoice_email_body();
				break;
			case 'discipline':
				$template = $config->get_email_template();
				break;
			case 'membership':
				$template = $config->get_membership_email_template();
				break;
			case 'installment':
				$template = $config->get_installment_email_template();
				break;
			case 'reminder_1':
				$template = $config->get_reminder_1_email_template();
				break;
			case 'reminder_2':
				$template = $config->get_reminder_2_email_template();
				break;
			case 'invoice_reminder_1':
				$template = $config->get_invoice_reminder_1_email_template();
				break;
			case 'invoice_reminder_2':
				$template = $config->get_invoice_reminder_2_email_template();
				break;
			case 'credit':
				$template = $config->get_credit_email_template();
				break;
			default:
				return new \WP_Error( 'invalid_type', 'Ongeldig template type.', [ 'status' => 400 ] );
		}

		$heading = $config->get_email_heading( $template_type );

		$email_body = str_replace( array_keys( $dummy ), array_values( $dummy ), $template );

		$email_body = EmailTemplate::render(
			[
				'brand_name'    => $org_name,
				'preheader'     => '[TEST] ' . $heading,
				'eyebrow'       => $eyebrow,
				'heading'       => $heading,
				'body_html'     => $email_body,
				'support_email' => $config->get_contact_email(),
			]
		);

		$subject = '[TEST] ' . $eyebrow . ' - ' . $heading . ' - ' . $org_name;

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $org_name . ' <' . $config->get_contact_email() . '>',
		];

		$sent = wp_mail( $recipient, $subject, $email_body, $headers );

		if ( ! $sent ) {
			return new \WP_Error(
				'email_send_failed',
				'Testmail kon niet worden verzonden.',
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
	 * Send a verification email for a bounced-address todo.
	 *
	 * Uses Lettermint via wp_mail transport and tags the message with metadata so
	 * a future bounce can create a follow-up task for the sender.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_lettermint_verification_email( $request ) {
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

		$todo_id = (int) $request->get_param( 'todo_id' );
		$todo    = get_post( $todo_id );
		if ( ! $todo || $todo->post_type !== 'rondo_todo' ) {
			return new \WP_Error(
				'todo_not_found',
				'Taak niet gevonden.',
				[ 'status' => 404 ]
			);
		}

		$current_user_id = get_current_user_id();
		$assigned_user_id = (int) get_post_meta( $todo_id, 'assigned_user_id', true );
		if ( (int) $todo->post_author !== $current_user_id && $assigned_user_id !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'todo_forbidden',
				'Je hebt geen toegang tot deze taak.',
				[ 'status' => 403 ]
			);
		}

		$recipient = sanitize_email( (string) $request->get_param( 'recipient' ) );
		if ( $recipient === '' ) {
			$recipient = sanitize_email( (string) get_post_meta( $todo_id, \Rondo\Notifications\LettermintWebhook::META_RECIPIENT, true ) );
		}

		$person_id = 0;
		$related_persons = get_field( 'related_persons', $todo_id );
		if ( is_array( $related_persons ) && ! empty( $related_persons ) ) {
			$person_id = (int) $related_persons[0];
		}

		if ( $recipient === '' ) {
			if ( $person_id > 0 ) {
				$recipient = $this->get_person_email_address( $person_id );
			}
		}

		if ( $recipient === '' && $person_id === 0 ) {
			$recipient_meta = sanitize_email( (string) get_post_meta( $todo_id, \Rondo\Notifications\LettermintWebhook::META_RECIPIENT, true ) );
			if ( $recipient_meta !== '' ) {
				$found_person_id = $this->find_person_id_by_email( $recipient_meta );
				if ( $found_person_id > 0 ) {
					$person_id = $found_person_id;
				}
			}
		}

		if ( ! is_email( $recipient ) ) {
			return new \WP_Error(
				'lettermint_invalid_recipient',
				'Geen geldig ontvanger e-mailadres gevonden. Voeg een e-mailadres toe aan de gekoppelde persoon of geef recipient mee.',
				[ 'status' => 400 ]
			);
		}

		$person_name = '';
		if ( $person_id > 0 ) {
			$first_name = trim( (string) get_field( 'first_name', $person_id ) );
			$last_name  = trim( (string) get_field( 'last_name', $person_id ) );
			$person_name = trim( $first_name . ' ' . $last_name );
		}
		if ( $person_name === '' ) {
			$person_name = $recipient;
		}

		$current_user                 = wp_get_current_user();
		$verification_from_email      = \Rondo\Config\ClubConfig::get_lettermint_verification_from_email();
		$verification_from_name       = \Rondo\Config\ClubConfig::get_lettermint_verification_from_name();
		$default_from_email           = \Rondo\Config\ClubConfig::get_lettermint_from_email();
		$default_from_name            = \Rondo\Config\ClubConfig::get_lettermint_from_name();
		$current_user_sender_name     = sanitize_text_field( (string) ( $current_user->display_name ?? '' ) );
		$current_user_sender_email    = sanitize_email( (string) ( $current_user->user_email ?? '' ) );
		$resolved_sender_email        = '';
		$resolved_sender_name         = '';

		if ( is_email( $verification_from_email ) ) {
			$resolved_sender_email = $verification_from_email;
		} elseif ( is_email( $default_from_email ) ) {
			$resolved_sender_email = $default_from_email;
		} elseif ( is_email( $current_user_sender_email ) ) {
			$resolved_sender_email = $current_user_sender_email;
		}

		if ( $verification_from_name !== '' ) {
			$resolved_sender_name = $verification_from_name;
		} elseif ( $default_from_name !== '' ) {
			$resolved_sender_name = $default_from_name;
		} elseif ( $current_user_sender_name !== '' ) {
			$resolved_sender_name = $current_user_sender_name;
		}

		if ( $resolved_sender_name === '' ) {
			$resolved_sender_name = 'Rondo Club';
		}

		$sender_name  = $resolved_sender_name;
		$sender_email = $resolved_sender_email;
		$club_name    = \Rondo\Config\ClubConfig::get_club_name();
		if ( $club_name === '' ) {
			$club_name = 'Rondo Club';
		}

		$replacements = [
			'{name}'         => $person_name,
			'{email}'        => $recipient,
			'{club_name}'    => $club_name,
			'{sender_name}'  => $sender_name,
			'{sender_email}' => $sender_email,
			'{date}'         => wp_date( 'Y-m-d H:i:s' ),
		];

		$subject_template = \Rondo\Config\ClubConfig::get_lettermint_verification_email_subject();
		$body_template    = \Rondo\Config\ClubConfig::get_lettermint_verification_email_body();
		$subject          = strtr( $subject_template, $replacements );
		$body             = strtr( $body_template, $replacements );

		if ( trim( $subject ) === '' ) {
			$subject = '[Rondo Club] Controle e-mailadres';
		}

		$metadata = [
			'flow'           => 'email_verification',
			'sender_user_id' => (int) $current_user_id,
			'source_todo_id' => (int) $todo_id,
			'source_person_id' => (int) $person_id,
		];

		$body    = EmailTemplate::render(
			[
				'brand_name'    => $club_name,
				'preheader'     => $subject,
				'eyebrow'       => 'Verificatie',
				'heading'       => 'Controleer dit e-mailadres',
				'body_html'     => EmailTemplate::format_plain_text( $body ),
				'support_email' => $resolved_sender_email,
			]
		);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'X-Rondo-Email-Tag: email-verification',
			'X-Rondo-Metadata: ' . wp_json_encode( $metadata ),
		];
		if ( is_email( $resolved_sender_email ) ) {
			$from_display = str_replace( [ "\r", "\n" ], '', trim( $resolved_sender_name ) );
			if ( $from_display !== '' ) {
				$headers[] = sprintf( 'From: %s <%s>', $from_display, $resolved_sender_email );
			} else {
				$headers[] = sprintf( 'From: %s', $resolved_sender_email );
			}
		}

		$sent = wp_mail( $recipient, $subject, $body, $headers );
		if ( ! $sent ) {
			return new \WP_Error(
				'lettermint_verification_send_failed',
				'Verificatiemail kon niet worden verzonden. Controleer Lettermint-instellingen en serverlogs.',
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'message'   => 'Verificatiemail verzonden.',
				'recipient' => $recipient,
				'todo_id'   => $todo_id,
				'person_id' => $person_id > 0 ? $person_id : null,
			]
		);
	}

	/**
	 * Get first email address from person fixed fields.
	 *
	 * @param int $person_id Person post ID.
	 * @return string
	 */
	private function get_person_email_address( int $person_id ): string {
		$email = sanitize_email( (string) get_field( 'email_1', $person_id ) );
		if ( is_email( $email ) ) {
			return $email;
		}

		$email = sanitize_email( (string) get_field( 'email_2', $person_id ) );
		if ( is_email( $email ) ) {
			return $email;
		}

		return '';
	}

	/**
	 * Find person by email address.
	 *
	 * @param string $email Email address.
	 * @return int
	 */
	private function find_person_id_by_email( string $email ): int {
		$email = strtolower( trim( sanitize_email( $email ) ) );
		if ( $email === '' ) {
			return 0;
		}

		foreach ( [ 'email_1', 'email_2' ] as $field ) {
			$matches = get_posts(
				[
					'post_type'        => 'person',
					'posts_per_page'   => 1,
					'post_status'      => 'publish',
					'suppress_filters' => true,
					'fields'           => 'ids',
					'meta_query'       => [
						[
							'key'     => $field,
							'value'   => $email,
							'compare' => '=',
						],
					],
				]
			);

			if ( ! empty( $matches ) ) {
				return (int) $matches[0];
			}
		}

		return 0;
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
		$result         = $finance_config->update_settings( $request->get_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

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
			$result = $finance_config->update_settings( $data );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $this->get_finance_branding( $request );
	}

	/**
	 * Get all distinct job_title values from work_history across all person posts.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response List of distinct role names.
	 */
	public function get_available_volunteer_roles( $request ) {
		global $wpdb;

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
		foreach ( \Rondo\Core\UserRoles::get_all_roles() as $slug => $data ) {
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
	 * Get the role × capability matrix for all Rondo roles + administrator.
	 *
	 * @return \WP_REST_Response Matrix of roles and their custom capabilities.
	 */
	public function get_capability_matrix() {
		$capability_labels = [
			'fairplay'          => 'FairPlay',
			'vog'               => 'VOG',
			'financieel'        => 'Financieel',
			'toegangscontrole'  => 'Toegangscontrole',
			'manage_clothing'   => 'Kledingbeheer',
		];

		$wp_roles     = wp_roles();
		$all_roles    = \Rondo\Core\UserRoles::get_all_roles();
		$custom_slugs = array_keys( \Rondo\Core\UserRoles::get_custom_roles() );
		$role_slugs   = array_keys( $all_roles );
		$role_slugs[] = 'administrator';

		$roles = [];
		foreach ( $role_slugs as $slug ) {
			$role_obj = $wp_roles->get_role( $slug );
			if ( ! $role_obj ) {
				continue;
			}

			if ( 'administrator' === $slug ) {
				$label = 'Administrator';
			} else {
				$label = $all_roles[ $slug ][0] ?? $slug;
			}

			$caps = [];
			foreach ( array_keys( $capability_labels ) as $cap ) {
				$caps[ $cap ] = ! empty( $role_obj->capabilities[ $cap ] );
			}

			$roles[ $slug ] = [
				'label'        => $label,
				'capabilities' => $caps,
				'is_custom'    => in_array( $slug, $custom_slugs, true ),
			];
		}

		return new \WP_REST_Response( [
			'roles'             => $roles,
			'capability_labels' => $capability_labels,
		] );
	}

	/**
	 * Update the role × capability matrix.
	 *
	 * Accepts { roles: { slug: { capabilities: { cap: bool } } } }.
	 * Diffs each role's desired capabilities against current state and applies changes.
	 * Administrator's manage_options capability is never removable.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Updated matrix or error.
	 */
	public function update_capability_matrix( $request ) {
		$submitted_roles = $request->get_param( 'roles' );

		if ( ! is_array( $submitted_roles ) ) {
			return new \WP_Error(
				'invalid_data',
				'The roles parameter must be an object of role slugs.',
				[ 'status' => 400 ]
			);
		}

		$allowed_caps = [ 'fairplay', 'vog', 'financieel', 'toegangscontrole', 'manage_clothing' ];
		$valid_slugs  = array_keys( \Rondo\Core\UserRoles::get_all_roles() );
		$valid_slugs[] = 'administrator';

		foreach ( $submitted_roles as $slug => $role_data ) {
			if ( ! in_array( $slug, $valid_slugs, true ) ) {
				continue;
			}

			$role_obj = get_role( $slug );
			if ( ! $role_obj ) {
				continue;
			}

			$desired_caps = $role_data['capabilities'] ?? [];
			if ( ! is_array( $desired_caps ) ) {
				continue;
			}

			foreach ( $desired_caps as $cap => $enabled ) {
				if ( ! in_array( $cap, $allowed_caps, true ) ) {
					continue;
				}

				// Never remove manage_options from administrator.
				if ( 'administrator' === $slug && 'manage_options' === $cap && ! $enabled ) {
					continue;
				}

				$has_cap = ! empty( $role_obj->capabilities[ $cap ] );

				if ( $enabled && ! $has_cap ) {
					$role_obj->add_cap( $cap );
				} elseif ( ! $enabled && $has_cap ) {
					$role_obj->remove_cap( $cap );
				}
			}
		}

		// Return fresh matrix state.
		return $this->get_capability_matrix();
	}

	/**
	 * Get age-group access configuration.
	 *
	 * Returns the per-role age-group restrictions and the list of available
	 * leeftijdsgroep values currently in use across person records.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_age_group_access() {
		global $wpdb;

		// Current per-role config (default: empty = no restrictions).
		$raw = get_option( 'rondo_age_group_access', [] );
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		// Query distinct leeftijdsgroep values in use.
		$rows = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = 'leeftijdsgroep'
			   AND pm.meta_value != ''
			   AND p.post_type = 'person'
			   AND p.post_status = 'publish'
			 ORDER BY pm.meta_value ASC"
		);

		// Sort age groups naturally (Onder 6, Onder 7, …, Onder 19, Senioren).
		usort( $rows, function ( $a, $b ) {
			$num_a = preg_match( '/(\d+)/', $a, $m ) ? (int) $m[1] : 999;
			$num_b = preg_match( '/(\d+)/', $b, $m ) ? (int) $m[1] : 999;
			return $num_a - $num_b;
		} );

		return rest_ensure_response( [
			'roles'                => (object) $raw,
			'available_age_groups' => array_values( $rows ),
		] );
	}

	/**
	 * Update age-group access configuration.
	 *
	 * Accepts per-role arrays of permitted leeftijdsgroep values. Empty arrays
	 * are removed (empty = no restriction for that role).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_age_group_access( $request ) {
		$submitted_roles = $request->get_param( 'roles' );

		if ( ! is_array( $submitted_roles ) ) {
			return new \WP_Error(
				'invalid_data',
				'The roles parameter must be an object of role slugs.',
				[ 'status' => 400 ]
			);
		}

		$valid_slugs   = array_keys( \Rondo\Core\UserRoles::get_all_roles() );
		$valid_slugs[] = 'administrator';

		$config = [];

		foreach ( $submitted_roles as $slug => $age_groups ) {
			if ( ! in_array( $slug, $valid_slugs, true ) ) {
				return new \WP_Error(
					'invalid_role_slug',
					sprintf( 'Invalid role slug: %s', $slug ),
					[ 'status' => 400 ]
				);
			}

			if ( ! is_array( $age_groups ) ) {
				continue;
			}

			// Sanitize values.
			$sanitized = array_values( array_filter( array_map( 'sanitize_text_field', $age_groups ) ) );

			// Only store non-empty arrays (empty = no restriction).
			if ( ! empty( $sanitized ) ) {
				$config[ $slug ] = $sanitized;
			}
		}

		update_option( 'rondo_age_group_access', $config );

		// Return fresh state.
		return $this->get_age_group_access();
	}

	/**
	 * Create a custom role.
	 *
	 * @param \WP_REST_Request $request The request object with 'label' param.
	 * @return \WP_REST_Response|\WP_Error Created role data or error.
	 */
	public function create_custom_role( $request ) {
		$label  = sanitize_text_field( $request->get_param( 'label' ) );
		$result = \Rondo\Core\UserRoles::add_custom_role( $label );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			[
				'slug'  => $result,
				'label' => $label,
			],
			201
		);
	}

	/**
	 * Delete a custom role.
	 *
	 * @param \WP_REST_Request $request The request object with 'slug' URL param.
	 * @return \WP_REST_Response|\WP_Error Success response or error.
	 */
	public function delete_custom_role( $request ) {
		$slug   = $request->get_param( 'slug' );
		$result = \Rondo\Core\UserRoles::remove_custom_role( $slug );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			[
				'deleted' => true,
				'slug'    => $slug,
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
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$is_timeout    = false !== stripos( $error_message, 'timed out' )
				|| false !== stripos( $error_message, 'cURL error 28' )
				|| false !== stripos( $error_message, 'operation timeout' );

			return new \WP_Error(
				'sync_request_failed',
				$is_timeout
					? 'Sportlink sync duurde te lang en is afgebroken. Probeer het opnieuw.'
					: $error_message,
				[ 'status' => $is_timeout ? 504 : 502 ]
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			$default_message = wp_remote_retrieve_response_message( $response );
			if ( empty( $default_message ) ) {
				$default_message = 'Sync server returned an error.';
			}

			return new \WP_Error(
				'sync_error',
				$body['error'] ?? $default_message,
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
