<?php
/**
 * Rabobank Payment Request Service
 *
 * Handles creating payment requests (betaalverzoeken) via the Rabobank API.
 * Uses OAuth tokens from RabobankOAuth for authentication.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\REST\Invoices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rabobank Payment service class
 */
class RabobankPayment {

	/**
	 * OAuth handler instance
	 *
	 * @var RabobankOAuth
	 */
	private $oauth;

	/**
	 * Whether to inject mTLS cert on next request
	 *
	 * @var bool
	 */
	private $inject_mtls = false;

	/**
	 * Constructor
	 *
	 * @param RabobankOAuth|null $oauth Optional OAuth handler instance
	 */
	public function __construct( $oauth = null ) {
		$this->oauth = $oauth ?: new RabobankOAuth();

		// Register REST routes
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Add mTLS certificate to Rabobank API requests
		add_action( 'http_api_curl', [ $this, 'add_mtls_cert' ], 10, 3 );
	}

	/**
	 * Inject mTLS client certificate into cURL handle for Rabobank requests
	 *
	 * @param resource $handle  cURL handle
	 * @param array    $parsed  Parsed request args
	 * @param string   $url     Request URL
	 */
	public function add_mtls_cert( $handle, $parsed, $url ) {
		if ( ! $this->inject_mtls ) {
			return;
		}

		$cert_dir = get_stylesheet_directory() . '/certs';
		$cert     = $cert_dir . '/sandbox-cert.pem';
		$key      = $cert_dir . '/sandbox-key.pem';

		if ( file_exists( $cert ) && file_exists( $key ) ) {
			curl_setopt( $handle, CURLOPT_SSLCERT, $cert );
			curl_setopt( $handle, CURLOPT_SSLKEY, $key );
		}
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Create payment link for an invoice
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/payment-link',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_payment_link_endpoint' ],
				'permission_callback' => [ $this, 'check_financieel_permission' ],
				'args'                => [
					'id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Get mTLS certificate for Rabobank Developer Portal
		register_rest_route(
			'rondo/v1',
			'/rabobank/certificate',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_certificate_endpoint' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * REST endpoint: Get or generate mTLS certificate
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_certificate_endpoint() {
		$cert_dir = get_stylesheet_directory() . '/certs';
		$cert     = $cert_dir . '/sandbox-cert.pem';
		$key      = $cert_dir . '/sandbox-key.pem';

		// Generate if not exists
		if ( ! file_exists( $cert ) || ! file_exists( $key ) ) {
			$generated = $this->generate_certificate();
			if ( is_wp_error( $generated ) ) {
				return $generated;
			}
		}

		if ( ! file_exists( $cert ) ) {
			return new \WP_Error(
				'cert_not_found',
				__( 'Certificaat niet gevonden.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$cert_content = file_get_contents( $cert );

		return rest_ensure_response( [
			'certificate' => $cert_content,
			'has_key'     => file_exists( $key ),
		] );
	}

	/**
	 * Generate a self-signed mTLS leaf certificate
	 *
	 * @return true|\WP_Error
	 */
	private function generate_certificate() {
		$cert_dir = get_stylesheet_directory() . '/certs';

		if ( ! is_dir( $cert_dir ) ) {
			wp_mkdir_p( $cert_dir );
		}

		$cert_path = $cert_dir . '/sandbox-cert.pem';
		$key_path  = $cert_dir . '/sandbox-key.pem';

		// Write a temporary OpenSSL config with leaf cert extensions (CA:FALSE)
		$openssl_conf = $cert_dir . '/openssl-tmp.cnf';
		$conf_content = "[req]\n"
			. "distinguished_name = req_dn\n"
			. "x509_extensions = v3_leaf\n"
			. "[req_dn]\n"
			. "[v3_leaf]\n"
			. "basicConstraints = critical,CA:FALSE\n"
			. "keyUsage = digitalSignature\n";
		file_put_contents( $openssl_conf, $conf_content );

		$site_name = sanitize_title( get_bloginfo( 'name' ) ) ?: 'rondo';
		$subject   = "/CN={$site_name}-mtls/O=" . ( get_bloginfo( 'name' ) ?: 'Rondo' ) . '/C=NL';

		// Use openssl command to generate a proper leaf certificate
		$cmd = sprintf(
			'openssl req -x509 -newkey rsa:4096 -keyout %s -out %s -days 365 -nodes -subj %s -config %s 2>&1',
			escapeshellarg( $key_path ),
			escapeshellarg( $cert_path ),
			escapeshellarg( $subject ),
			escapeshellarg( $openssl_conf )
		);

		$output  = [];
		$retcode = 0;
		exec( $cmd, $output, $retcode );

		// Clean up temp config
		unlink( $openssl_conf );

		if ( $retcode !== 0 ) {
			error_log( 'Certificate generation failed: ' . implode( "\n", $output ) );
			return new \WP_Error( 'openssl_error', __( 'Kon certificaat niet genereren.', 'rondo' ), [ 'status' => 500 ] );
		}

		// Restrict key file permissions
		if ( file_exists( $key_path ) ) {
			chmod( $key_path, 0600 );
		}

		return true;
	}

	/**
	 * Check if user has financieel capability
	 *
	 * @return bool True if user has financieel capability
	 */
	public function check_financieel_permission() {
		return current_user_can( 'financieel' );
	}

	/**
	 * REST endpoint: Create payment link for invoice
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_payment_link_endpoint( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		// Create payment request
		$result = $this->create_payment_request( $invoice_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Return updated invoice detail using Invoices class format
		$invoice = get_post( $invoice_id );
		if ( ! $invoice ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Invoice not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Use the Invoices class to format the response
		$invoices_controller = new Invoices();
		return rest_ensure_response( $invoices_controller->get_invoice( $request ) );
	}

	/**
	 * Create a payment request for an invoice
	 *
	 * @param int $invoice_id Invoice post ID
	 * @return string|\WP_Error Payment link URL on success, WP_Error on failure
	 */
	public function create_payment_request( $invoice_id ) {
		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'invalid_invoice',
				__( 'Ongeldige factuur.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Get access token
		$access_token = $this->oauth->get_access_token();

		if ( ! $access_token ) {
			return new \WP_Error(
				'rabobank_not_connected',
				__( 'Rabobank is niet gekoppeld. Autoriseer eerst via Instellingen.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Load invoice data
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$total_amount   = get_field( 'total_amount', $invoice_id );
		$person_id      = get_field( 'person', $invoice_id );
		$due_date       = get_field( 'due_date', $invoice_id );

		// Get person name
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error(
				'invalid_person',
				__( 'Ongeldige persoon gekoppeld aan factuur.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}
		$counterparty_name = $person->post_title;

		// Format due date
		if ( empty( $due_date ) ) {
			// Calculate from payment term if not set
			$finance_config = new \Rondo\Config\FinanceConfig();
			$payment_term_days = $finance_config->get_payment_term_days();
			$due_date = date( 'Y-m-d', strtotime( "+{$payment_term_days} days" ) );
		} else {
			// Convert ACF date format (Ymd) to API format (Y-m-d)
			$due_date = date( 'Y-m-d', strtotime( $due_date ) );
		}

		// Get finance config for IBAN and credentials
		$finance_config = new \Rondo\Config\FinanceConfig();
		$iban = $finance_config->get_iban();

		if ( empty( $iban ) ) {
			return new \WP_Error(
				'missing_iban',
				__( 'IBAN niet geconfigureerd. Stel deze in via Financiën > Instellingen.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Build request body
		$request_body = [
			'iban'             => $iban,
			'amount' => [
				'value'    => number_format( (float) $total_amount, 2, '.', '' ),
				'currency' => 'EUR',
			],
			'counterpartyName' => $counterparty_name,
			'description'      => 'Factuur ' . $invoice_number,
			'expiryDate'       => $due_date,
		];

		// Get credentials for client ID
		$credentials = $finance_config->get_rabobank_credentials();

		if ( ! $credentials || empty( $credentials['client_id'] ) ) {
			return new \WP_Error(
				'rabobank_not_configured',
				__( 'Rabobank credentials niet geconfigureerd.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Build API endpoint URL
		$api_path = $this->get_api_path();
		$api_url  = $this->oauth->get_base_url() . $api_path;

		// Make API request (enable mTLS for this request)
		$this->inject_mtls = true;
		$response = wp_remote_post(
			$api_url,
			[
				'headers' => [
					'Authorization'    => 'Bearer ' . $access_token,
					'Content-Type'     => 'application/json',
					'X-IBM-Client-Id'  => $credentials['client_id'],
				],
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 30,
			]
		);
		$this->inject_mtls = false;

		if ( is_wp_error( $response ) ) {
			error_log( 'Rabobank payment request error: ' . $response->get_error_message() );
			return new \WP_Error(
				'api_request_failed',
				sprintf( __( 'API request mislukt: %s', 'rondo' ), $response->get_error_message() ),
				[ 'status' => 500 ]
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		// Handle error responses
		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = $data['error_description'] ?? $data['message'] ?? $data['moreInformation'] ?? $data['error'] ?? 'Payment request creation failed';
			error_log( sprintf( 'Rabobank payment request failed (HTTP %d): %s', $status_code, $error_message ) );
			error_log( 'Rabobank request URL: ' . $api_url );
			error_log( 'Response body: ' . $body );

			return new \WP_Error(
				'payment_request_failed',
				sprintf( __( 'Betaalverzoek aanmaken mislukt: %s', 'rondo' ), $error_message ),
				[ 'status' => 502 ] // Always return 502 for upstream Rabobank errors (never pass through 401/403)
			);
		}

		// Extract payment link from response
		// The API may return 'paymentLink' or nested in 'links.paymentUrl'
		$payment_link = null;

		if ( ! empty( $data['paymentLink'] ) ) {
			$payment_link = $data['paymentLink'];
		} elseif ( ! empty( $data['links']['paymentUrl'] ) ) {
			$payment_link = $data['links']['paymentUrl'];
		} elseif ( ! empty( $data['links']['payment'] ) ) {
			$payment_link = $data['links']['payment'];
		}

		if ( empty( $payment_link ) ) {
			error_log( 'Rabobank payment request response missing payment link: ' . $body );
			return new \WP_Error(
				'invalid_response',
				__( 'Geen betaallink in API response.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		// Store payment link on invoice
		update_field( 'payment_link', $payment_link, $invoice_id );

		return $payment_link;
	}

	/**
	 * Get API path for payment requests endpoint
	 *
	 * @return string API path prefix
	 */
	private function get_api_path() {
		$environment = $this->oauth->get_environment();

		if ( $environment === 'production' ) {
			return '/openapi/payments/payment-requests';
		}

		return '/openapi/sandbox/payments/payment-requests';
	}
}
