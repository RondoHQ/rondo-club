<?php
/**
 * REST API Endpoints for Finance Settings and Branding
 *
 * Handles finance configuration (org details, payment providers, email
 * templates) and branding settings (logo, accent colors).
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FinanceSettings extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register finance settings REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/finance/credential-file/(?P<type>apple|google)',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_credential_file' ],
				'permission_callback' => [ $this, 'check_financieel_permission' ],
			]
		);

		// Finance settings (financieel capability required).
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
						'org_name'                         => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'org_address'                      => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'contact_email'                    => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_email',
						],
						'iban'                             => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'mollie_accounts'                  => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
						'payment_term_days'                => [
							'required' => false,
							'type'     => 'integer',
						],
						'payment_clause'                   => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'membership_payment_clause'        => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'email_template'                   => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'membership_email_template'        => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'membership_email_subject'         => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'installment_email_template'       => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'reminder_1_email_template'        => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'reminder_2_email_template'        => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'generic_invoice_reminder_1_email_template' => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'generic_invoice_reminder_2_email_template' => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'credit_email_template'            => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'regular_invoice_email_subject'    => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'regular_invoice_email_body'       => [
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						],
						'regular_invoice_email_heading'    => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'discipline_email_heading'         => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'membership_email_heading'         => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'installment_email_heading'        => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'reminder_1_email_heading'         => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'reminder_2_email_heading'         => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'invoice_reminder_1_email_heading' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'invoice_reminder_2_email_heading' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'generic_invoice_reminder_1_email_heading' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'generic_invoice_reminder_2_email_heading' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'credit_email_heading'             => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'rabobank_client_id'               => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'rabobank_client_secret'           => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'rabobank_environment'             => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'mollie_redirect_url'              => [
							'required'          => false,
							'sanitize_callback' => 'esc_url_raw',
						],
						'mollie_default_membership_account_id' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						],
						'mollie_default_discipline_account_id' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						],
						'mollie_default_manual_account_id' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						],
						'mollie_default_tournament_account_id' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						],
						'active_payment_provider'          => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return in_array( $param, [ 'rabobank', 'mollie' ], true );
							},
						],
						'club_logo_id'                     => [
							'required' => false,
							'type'     => 'integer',
						],
						'businessclub_logo_id'             => [
							'required' => false,
							'type'     => 'integer',
						],
						'accent_color'                     => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'accent_background_color'          => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'bcc_email'                        => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_email',
						],
						'admin_fee'                        => [
							'required' => false,
							'type'     => 'number',
						],
						'installment_admin_fee'            => [
							'required' => false,
							'type'     => 'number',
						],
						'membership_pass_apple_cert_attachment_id' => [
							'required' => false,
							'type'     => 'integer',
						],
						'membership_pass_apple_cert_password' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'membership_pass_apple_pass_type_identifier' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'membership_pass_apple_team_identifier' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'membership_pass_apple_organization_name' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'membership_pass_google_service_account_attachment_id' => [
							'required' => false,
							'type'     => 'integer',
						],
						'membership_pass_google_issuer_id' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'membership_pass_google_class_suffix' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Finance branding settings (admin only).
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
						'club_logo_id'            => [
							'required' => false,
							'type'     => 'integer',
						],
						'businessclub_logo_id'    => [
							'required' => false,
							'type'     => 'integer',
						],
						'accent_color'            => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'accent_background_color' => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);
	}

	/**
	 * Get finance configuration settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Finance configuration settings.
	 */
	public function get_finance_settings( $request ) {
		$finance_config = new \Rondo\Config\FinanceConfig();
		return rest_ensure_response( $finance_config->get_all_settings() );
	}

	/**
	 * Update finance configuration settings.
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

	/** Store an Apple or Google credential file encrypted. */
	public function upload_credential_file( $request ) {
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		$type  = (string) $request->get_param( 'type' );
		if ( ! is_array( $file ) || (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'rondo_credential_upload_failed', __( 'Het bestand kon niet worden geüpload.', 'rondo' ), [ 'status' => 400 ] );
		}

		$tmp_name = (string) ( $file['tmp_name'] ?? '' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = $tmp_name !== '' ? file_get_contents( $tmp_name ) : false;
		if ( $contents === false || ! \Rondo\Data\PrivateCredentialStorage::store( $type, $contents, (string) ( $file['name'] ?? '' ) ) ) {
			return new \WP_Error( 'rondo_credential_invalid', __( 'Het bestand is ongeldig of kon niet versleuteld worden opgeslagen.', 'rondo' ), [ 'status' => 400 ] );
		}

		if ( $type === \Rondo\Data\PrivateCredentialStorage::APPLE ) {
			delete_option( 'rondo_membership_pass_apple_cert_path' );
			update_option( 'rondo_membership_pass_apple_cert_attachment_id', 0, false );
		} else {
			delete_option( 'rondo_membership_pass_google_service_account_path' );
			update_option( 'rondo_membership_pass_google_service_account_attachment_id', 0, false );
		}

		return rest_ensure_response(
			[
				'configured' => true,
				'filename'   => \Rondo\Data\PrivateCredentialStorage::filename( $type ),
			]
		);
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
				'club_logo_id'            => (int) ( $settings['club_logo_id'] ?? 0 ),
				'club_logo_url'           => isset( $settings['club_logo_url'] ) ? (string) $settings['club_logo_url'] : '',
				'businessclub_logo_id'    => (int) ( $settings['businessclub_logo_id'] ?? 0 ),
				'businessclub_logo_url'   => isset( $settings['businessclub_logo_url'] ) ? (string) $settings['businessclub_logo_url'] : '',
				'accent_color'            => isset( $settings['accent_color'] ) ? (string) $settings['accent_color'] : '',
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
		if ( $club_logo_id !== null ) {
			$data['club_logo_id'] = (int) $club_logo_id;
		}

		$businessclub_logo_id = $request->get_param( 'businessclub_logo_id' );
		if ( $businessclub_logo_id !== null ) {
			$data['businessclub_logo_id'] = (int) $businessclub_logo_id;
		}

		$accent_color = $request->get_param( 'accent_color' );
		if ( $accent_color !== null ) {
			$data['accent_color'] = (string) $accent_color;
		}

		$accent_background_color = $request->get_param( 'accent_background_color' );
		if ( $accent_background_color !== null ) {
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
}
