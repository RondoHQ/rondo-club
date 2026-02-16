<?php
/**
 * Rabobank OAuth 2.0 Premium Flow Handler
 *
 * Manages the complete OAuth 2.0 Premium authorization flow for the Rabobank Payment Request API.
 * This includes authorization URL generation, callback handling, token exchange, refresh, and secure storage.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;
use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rabobank OAuth handler class
 */
class RabobankOAuth {

	/**
	 * WordPress option key for encrypted token storage
	 */
	const OPTION_RABOBANK_TOKENS = 'rondo_rabobank_oauth_tokens';

	/**
	 * OAuth scope required for payment requests
	 */
	const SCOPE = 'payment-request';

	/**
	 * Token refresh buffer in seconds (refresh 5 minutes before expiry)
	 */
	const REFRESH_BUFFER = 300;

	/**
	 * Finance configuration instance
	 *
	 * @var FinanceConfig
	 */
	private $config;

	/**
	 * Current environment (sandbox or production)
	 *
	 * @var string
	 */
	private $environment;

	/**
	 * API base URL for current environment
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * OAuth authorize URL for current environment
	 *
	 * @var string
	 */
	private $authorize_url;

	/**
	 * Token endpoint URL for current environment
	 *
	 * @var string
	 */
	private $token_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->config = new FinanceConfig();

		// Determine environment and set URLs
		$credentials = $this->config->get_rabobank_credentials();
		if ( $credentials ) {
			$this->environment = $credentials['environment'] ?? 'sandbox';
		} else {
			$this->environment = 'sandbox';
		}

		$this->set_environment_urls();

		// Register REST endpoints
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Set environment-specific URLs
	 */
	private function set_environment_urls() {
		if ( $this->environment === 'production' ) {
			$this->base_url       = 'https://api.rabobank.nl';
			$this->authorize_url  = 'https://oauth.rabobank.nl/openapi/oauth2/authorize';
			$this->token_url      = 'https://api.rabobank.nl/openapi/oauth2/token';
		} else {
			$this->base_url       = 'https://api-sandbox.rabobank.nl';
			$this->authorize_url  = 'https://oauth-sandbox.rabobank.nl/openapi/sandbox/oauth2/authorize';
			$this->token_url      = 'https://api-sandbox.rabobank.nl/openapi/sandbox/oauth2/token';
		}
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Get authorize URL
		register_rest_route(
			'rondo/v1',
			'/rabobank/authorize',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_authorize_url_endpoint' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		// OAuth callback (browser redirect from Rabobank)
		register_rest_route(
			'rondo/v1',
			'/rabobank/callback',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_callback_endpoint' ],
				'permission_callback' => '__return_true', // State nonce provides security
			]
		);

		// Get connection status
		register_rest_route(
			'rondo/v1',
			'/rabobank/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status_endpoint' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		// Disconnect (clear tokens)
		register_rest_route(
			'rondo/v1',
			'/rabobank/disconnect',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'disconnect_endpoint' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * REST endpoint: Get authorize URL
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_authorize_url_endpoint() {
		$url = $this->get_authorize_url();

		if ( ! $url ) {
			return new \WP_Error(
				'rabobank_not_configured',
				__( 'Rabobank credentials niet geconfigureerd.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response( [ 'authorize_url' => $url ] );
	}

	/**
	 * REST endpoint: Handle OAuth callback
	 *
	 * @param \WP_REST_Request $request
	 * @return void Redirects to finance settings page
	 */
	public function handle_callback_endpoint( $request ) {
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );
		$error = $request->get_param( 'error' );

		// Check for OAuth error response
		if ( $error ) {
			$error_description = $request->get_param( 'error_description' );
			$redirect_url = home_url( '/financien/instellingen?rabobank=error&message=' . urlencode( $error_description ?: $error ) );
			wp_redirect( $redirect_url );
			exit;
		}

		// Validate required parameters
		if ( ! $code || ! $state ) {
			$redirect_url = home_url( '/financien/instellingen?rabobank=error&message=' . urlencode( 'Ontbrekende parameters' ) );
			wp_redirect( $redirect_url );
			exit;
		}

		// Exchange code for tokens
		$result = $this->handle_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			$redirect_url = home_url( '/financien/instellingen?rabobank=error&message=' . urlencode( $result->get_error_message() ) );
		} else {
			$redirect_url = home_url( '/financien/instellingen?rabobank=connected' );
		}

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * REST endpoint: Get connection status
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status_endpoint() {
		return rest_ensure_response(
			[
				'connected'   => $this->is_connected(),
				'environment' => $this->environment,
			]
		);
	}

	/**
	 * REST endpoint: Disconnect (clear stored tokens)
	 *
	 * @return \WP_REST_Response
	 */
	public function disconnect_endpoint() {
		$this->disconnect();
		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Build the OAuth authorize URL
	 *
	 * @return string|null Authorize URL or null if credentials not configured
	 */
	public function get_authorize_url() {
		$credentials = $this->config->get_rabobank_credentials();

		if ( ! $credentials || empty( $credentials['client_id'] ) ) {
			return null;
		}

		$params = [
			'response_type' => 'code',
			'client_id'     => $credentials['client_id'],
			'scope'         => self::SCOPE,
			'redirect_uri'  => rest_url( 'rondo/v1/rabobank/callback' ),
			'state'         => wp_create_nonce( 'rabobank_oauth' ),
		];

		return $this->authorize_url . '?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for tokens
	 *
	 * @param string $code  Authorization code from callback
	 * @param string $state State parameter for CSRF protection
	 * @return bool|\WP_Error True on success, WP_Error on failure
	 */
	public function handle_callback( $code, $state ) {
		// Verify state nonce
		if ( ! wp_verify_nonce( $state, 'rabobank_oauth' ) ) {
			return new \WP_Error(
				'invalid_state',
				__( 'Ongeldige state parameter.', 'rondo' )
			);
		}

		$credentials = $this->config->get_rabobank_credentials();

		if ( ! $credentials ) {
			return new \WP_Error(
				'no_credentials',
				__( 'Rabobank credentials niet geconfigureerd.', 'rondo' )
			);
		}

		// Build token request
		$body = [
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => rest_url( 'rondo/v1/rabobank/callback' ),
			'client_id'    => $credentials['client_id'],
			'client_secret' => $credentials['client_secret'],
		];

		$response = wp_remote_post(
			$this->token_url,
			[
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body'    => http_build_query( $body ),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Rabobank token exchange error: ' . $response->get_error_message() );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$error_message = $data['error_description'] ?? $data['error'] ?? 'Token exchange failed';
			error_log( 'Rabobank token exchange failed: ' . $error_message );
			return new \WP_Error(
				'token_exchange_failed',
				sprintf( __( 'Token exchange mislukt: %s', 'rondo' ), $error_message )
			);
		}

		if ( empty( $data['access_token'] ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Ongeldig token response.', 'rondo' )
			);
		}

		// Store the tokens
		$this->store_tokens( $data );

		return true;
	}

	/**
	 * Get a valid access token (refreshes if needed)
	 *
	 * @return string|null Access token or null if not connected
	 */
	public function get_access_token() {
		$tokens = $this->get_stored_tokens();

		if ( ! $tokens ) {
			return null;
		}

		// Check if token needs refresh
		if ( $this->is_token_expired( $tokens ) ) {
			$refresh_success = $this->refresh_token();

			if ( ! $refresh_success ) {
				return null;
			}

			// Reload tokens after refresh
			$tokens = $this->get_stored_tokens();
		}

		return $tokens['access_token'] ?? null;
	}

	/**
	 * Refresh the access token using refresh token
	 *
	 * @return bool True on success, false on failure
	 */
	public function refresh_token() {
		$tokens = $this->get_stored_tokens();

		if ( ! $tokens || empty( $tokens['refresh_token'] ) ) {
			return false;
		}

		$credentials = $this->config->get_rabobank_credentials();

		if ( ! $credentials ) {
			return false;
		}

		// Build refresh request
		$body = [
			'grant_type'    => 'refresh_token',
			'refresh_token' => $tokens['refresh_token'],
			'client_id'     => $credentials['client_id'],
			'client_secret' => $credentials['client_secret'],
		];

		$response = wp_remote_post(
			$this->token_url,
			[
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body'    => http_build_query( $body ),
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Rabobank token refresh error: ' . $response->get_error_message() );
			$this->disconnect(); // Clear invalid tokens
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$error_message = $data['error_description'] ?? $data['error'] ?? 'Token refresh failed';
			error_log( 'Rabobank token refresh failed: ' . $error_message );
			$this->disconnect(); // Clear invalid tokens - user must re-authorize
			return false;
		}

		if ( empty( $data['access_token'] ) ) {
			$this->disconnect();
			return false;
		}

		// Update stored tokens (merge with existing to preserve refresh_token if not returned)
		$updated_tokens = array_merge(
			$tokens,
			[
				'access_token' => $data['access_token'],
				'expires_in'   => $data['expires_in'] ?? 3600,
				'stored_at'    => time(),
			]
		);

		// If new refresh token provided, update it
		if ( ! empty( $data['refresh_token'] ) ) {
			$updated_tokens['refresh_token'] = $data['refresh_token'];
		}

		$this->store_tokens( $updated_tokens );

		return true;
	}

	/**
	 * Encrypt and store tokens
	 *
	 * @param array $token_response Token data from OAuth response
	 */
	public function store_tokens( $token_response ) {
		$tokens = [
			'access_token'  => $token_response['access_token'],
			'refresh_token' => $token_response['refresh_token'] ?? '',
			'expires_in'    => $token_response['expires_in'] ?? 3600,
			'token_type'    => $token_response['token_type'] ?? 'Bearer',
			'scope'         => $token_response['scope'] ?? self::SCOPE,
			'stored_at'     => time(),
		];

		$encrypted = CredentialEncryption::encrypt( $tokens );
		update_option( self::OPTION_RABOBANK_TOKENS, $encrypted );
	}

	/**
	 * Retrieve and decrypt stored tokens
	 *
	 * @return array|null Token array or null if not found/invalid
	 */
	public function get_stored_tokens() {
		$encrypted = get_option( self::OPTION_RABOBANK_TOKENS, '' );

		if ( empty( $encrypted ) ) {
			return null;
		}

		return CredentialEncryption::decrypt( $encrypted );
	}

	/**
	 * Check if access token is expired or within refresh buffer
	 *
	 * @param array $tokens Token data
	 * @return bool True if expired or needs refresh
	 */
	public function is_token_expired( $tokens ) {
		if ( ! isset( $tokens['stored_at'], $tokens['expires_in'] ) ) {
			return true;
		}

		$expires_at = $tokens['stored_at'] + $tokens['expires_in'];
		$now        = time();

		// Return true if expired or within refresh buffer (5 minutes)
		return ( $expires_at - self::REFRESH_BUFFER ) < $now;
	}

	/**
	 * Check if valid tokens exist
	 *
	 * @return bool True if tokens exist (even if expired - we can try to refresh)
	 */
	public function is_connected() {
		$tokens = $this->get_stored_tokens();
		return $tokens !== null && ! empty( $tokens['access_token'] );
	}

	/**
	 * Remove stored tokens
	 */
	public function disconnect() {
		delete_option( self::OPTION_RABOBANK_TOKENS );
	}

	/**
	 * Get API base URL for current environment
	 *
	 * @return string Base URL
	 */
	public function get_base_url() {
		return $this->base_url;
	}

	/**
	 * Get current environment
	 *
	 * @return string Environment (sandbox or production)
	 */
	public function get_environment() {
		return $this->environment;
	}
}
