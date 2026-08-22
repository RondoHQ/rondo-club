<?php
/**
 * REST API Endpoints for Lettermint (email integration)
 *
 * Provides endpoints for managing Lettermint projects, webhooks, and sending
 * test/verification emails via the Lettermint transport.
 */

namespace Rondo\REST;

use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lettermint extends Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
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
					'route_id'   => [
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
							return in_array(
								$param,
								[
									'regular_invoice',
									'discipline',
									'membership',
									'installment',
									'reminder_1',
									'reminder_2',
									'invoice_reminder_1',
									'invoice_reminder_2',
									'generic_invoice_reminder_1',
									'generic_invoice_reminder_2',
									'credit',
								],
								true
							);
						},
					],
					'recipient'     => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return is_email( $param );
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
					'todo_id'   => [
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
				'id'                 => $project_id,
				'name'               => $project_name,
				'is_default'         => isset( $project['is_default'] ) ? rest_sanitize_boolean( $project['is_default'] ) : false,
				'default_route_id'   => $default_route_id,
				'default_route_name' => $default_route_name,
				'route_count'        => $route_count,
				'route_error'        => $route_error,
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
				'message'      => $secret !== ''
					? 'Lettermint-webhook aangemaakt. Geheim automatisch opgeslagen.'
					: 'Lettermint-webhook aangemaakt, maar het geheim is niet meegeleverd door de API.',
				'webhook'      => [
					'id'           => $webhook_id,
					'url'          => $resolved_url,
					'project_id'   => $project_id,
					'project_name' => $project_name,
					'route_id'     => $resolved_route_id,
					'route_name'   => $route_name,
					'events'       => $resolved_events,
				],
				'secret_saved' => $secret !== '',
				'config'       => \Rondo\Config\ClubConfig::get_all_settings(),
			]
		);
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

		$subject        = sprintf( '[Rondo Club] Lettermint testmail - %s', wp_date( 'Y-m-d H:i:s' ) );
		$route_override = \Rondo\Notifications\LettermintConfig::get_route_id();
		$project_id     = \Rondo\Config\ClubConfig::get_lettermint_project_id();
		$route_label    = $route_override !== ''
			? sprintf( '%s (handmatige override)', $route_override )
			: 'automatisch via project default route';
		$body           = implode(
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

		$body = EmailTemplate::render(
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
			'{naam}'                => 'Jan Jansen',
			'{voornaam}'            => 'Jan',
			'{factuur_nummer}'      => 'C-2025-0042',
			'{totaal_bedrag}'       => '&euro; 230,00',
			'{betaallink}'          => '<a href="https://example.com/betaling/test" style="color:#0891b2;text-decoration:underline;">https://example.com/betaling/test</a>',
			'{betaalknop}'          => EmailTemplate::render_cta_button( 'https://example.com/betaling/test', 'Open betaallink' ),
			'{qr_code}'             => $qr_code_html,
			'{organisatie_naam}'    => esc_html( $org_name ),
			'{tuchtzaken_lijst}'    => '<table style="width:100%;border-collapse:collapse;font-size:14px;"><thead><tr style="background-color:#f3f4f6;"><th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Datum</th><th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Wedstrijd</th><th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Kaart</th><th style="padding:8px 12px;text-align:right;border-bottom:2px solid #d1d5db;">Bedrag</th></tr></thead><tbody><tr><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">01-03-2025</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">Club A - Club B</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">Geel</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">&euro; 15,00</td></tr></tbody></table>',
			'{termijn_nummer}'      => '2',
			'{totaal_termijnen}'    => '3',
			'{termijn_bedrag}'      => '&euro; 76,67',
			'{vervaldatum}'         => '25 maart 2025',
			'{dagen_te_laat}'       => '14',
			'{factuurdatum}'        => '1 februari 2025',
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
			case 'generic_invoice_reminder_1':
				$template = $config->get_generic_invoice_reminder_1_email_template();
				break;
			case 'generic_invoice_reminder_2':
				$template = $config->get_generic_invoice_reminder_2_email_template();
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

		$current_user_id  = get_current_user_id();
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

		$person_id       = 0;
		$related_persons = \Rondo\Fields\Fields::get_for_post( $todo_id, 'related_persons' );
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

		if ( $person_id > 0 && ! \Rondo\People\CommunicationPolicy::may_contact( $person_id ) ) {
			return new \WP_Error(
				'rondo_deceased_communication_blocked',
				'Deze persoon is als overleden geregistreerd. Rondo verstuurt daarom geen e-mail.',
				[ 'status' => 409 ]
			);
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
			$first_name  = trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) );
			$last_name   = trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'last_name' ) );
			$person_name = trim( $first_name . ' ' . $last_name );
		}
		if ( $person_name === '' ) {
			$person_name = $recipient;
		}

		$current_user              = wp_get_current_user();
		$verification_from_email   = \Rondo\Config\ClubConfig::get_lettermint_verification_from_email();
		$verification_from_name    = \Rondo\Config\ClubConfig::get_lettermint_verification_from_name();
		$default_from_email        = \Rondo\Config\ClubConfig::get_lettermint_from_email();
		$default_from_name         = \Rondo\Config\ClubConfig::get_lettermint_from_name();
		$current_user_sender_name  = sanitize_text_field( (string) ( $current_user->display_name ?? '' ) );
		$current_user_sender_email = sanitize_email( (string) ( $current_user->user_email ?? '' ) );
		$resolved_sender_email     = '';
		$resolved_sender_name      = '';

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
			'flow'             => 'email_verification',
			'sender_user_id'   => (int) $current_user_id,
			'source_todo_id'   => (int) $todo_id,
			'source_person_id' => (int) $person_id,
		];

		$body = EmailTemplate::render(
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
		$route_override   = sanitize_text_field( $route_override );
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
			$project_names        = array_values(
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

		if ( $default_route === null && $route_count === 1 && is_array( $routes[0] ?? null ) ) {
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
	 * Get first email address from person fixed fields.
	 *
	 * @param int $person_id Person post ID.
	 * @return string
	 */
	private function get_person_email_address( int $person_id ): string {
		return \Rondo\People\CommunicationPolicy::primary_email( $person_id ) ?? '';
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
}
