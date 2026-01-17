<?php
/**
 * Google Contacts REST API Endpoints
 *
 * Handles REST API endpoints for Google Contacts OAuth connection management:
 * - Status check
 * - OAuth flow initiation
 * - OAuth callback handling
 * - Disconnect functionality
 */

namespace Caelis\REST;

use Caelis\Calendar\GoogleOAuth;
use Caelis\Contacts\GoogleContactsConnection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleContacts extends Base {

	/**
	 * Constructor
	 *
	 * Register routes for Google Contacts endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register custom REST routes for Google Contacts domain
	 */
	public function register_routes() {
		// GET /prm/v1/google-contacts/status - Check connection status
		register_rest_route(
			'prm/v1',
			'/google-contacts/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// GET /prm/v1/google-contacts/auth - Initiate OAuth flow
		register_rest_route(
			'prm/v1',
			'/google-contacts/auth',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'auth_init' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'readonly' => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => true,
					],
				],
			]
		);

		// GET /prm/v1/google-contacts/callback - OAuth callback (public for redirect)
		register_rest_route(
			'prm/v1',
			'/google-contacts/callback',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'auth_callback' ],
				'permission_callback' => '__return_true', // Public for OAuth redirect
			]
		);

		// DELETE /prm/v1/google-contacts - Disconnect
		register_rest_route(
			'prm/v1',
			'/google-contacts',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'disconnect' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
	}

	/**
	 * Get Google Contacts connection status
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response with connection status.
	 */
	public function get_status( $request ) {
		$user_id    = get_current_user_id();
		$connection = GoogleContactsConnection::get_connection( $user_id );
		$connected  = GoogleContactsConnection::is_connected( $user_id );

		$response = [
			'connected'          => $connected,
			'google_configured'  => GoogleOAuth::is_configured(),
			'access_mode'        => $connection['access_mode'] ?? 'none',
			'email'              => $connection['email'] ?? '',
			'last_sync'          => $connection['last_sync'] ?? null,
			'contact_count'      => $connection['contact_count'] ?? 0,
			'last_error'         => $connection['last_error'] ?? null,
			'has_pending_import' => GoogleContactsConnection::has_pending_import( $user_id ),
			'connected_at'       => $connection['connected_at'] ?? null,
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Initiate Google Contacts OAuth flow
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with auth URL or error.
	 */
	public function auth_init( $request ) {
		$user_id = get_current_user_id();

		// Check if Google OAuth is configured
		if ( ! GoogleOAuth::is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'Google integration is not configured. Please add GOOGLE_CALENDAR_CLIENT_ID and GOOGLE_CALENDAR_CLIENT_SECRET to wp-config.php.', 'caelis' ),
				[ 'status' => 400 ]
			);
		}

		// Check if already connected
		if ( GoogleContactsConnection::is_connected( $user_id ) ) {
			return new \WP_Error(
				'already_connected',
				__( 'Google Contacts is already connected. Please disconnect first to reconnect.', 'caelis' ),
				[ 'status' => 400 ]
			);
		}

		// Get requested access mode
		$readonly = $request->get_param( 'readonly' ) !== false;

		// Generate auth URL
		$auth_url = GoogleOAuth::get_contacts_auth_url( $user_id, $readonly );

		if ( empty( $auth_url ) ) {
			return new \WP_Error(
				'auth_url_failed',
				__( 'Failed to generate authorization URL.', 'caelis' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( [ 'auth_url' => $auth_url ] );
	}

	/**
	 * Handle Google OAuth callback
	 *
	 * Exchanges authorization code for tokens and creates the connection.
	 * Redirects to settings page with success or error status.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 */
	public function auth_callback( $request ) {
		// Check for error from Google (user denied access)
		$error = $request->get_param( 'error' );
		if ( $error ) {
			$error_desc = $request->get_param( 'error_description' ) ?? 'Authorization denied';
			$this->html_redirect( home_url( '/settings?tab=connections&subtab=contacts&error=' . urlencode( $error_desc ) ) );
		}

		// Get and validate state parameter (token stored in transient)
		$token = $request->get_param( 'state' );
		if ( empty( $token ) ) {
			$this->html_redirect( home_url( '/settings?tab=connections&subtab=contacts&error=' . urlencode( 'Invalid state parameter' ) ) );
		}

		// Retrieve state data from transient
		$state_data = get_transient( 'google_contacts_oauth_' . $token );
		if ( ! $state_data || ! is_array( $state_data ) ) {
			$this->html_redirect( home_url( '/settings?tab=connections&subtab=contacts&error=' . urlencode( 'Security verification failed or link expired. Please try again.' ) ) );
		}

		// Delete the transient to prevent reuse
		delete_transient( 'google_contacts_oauth_' . $token );

		$user_id  = absint( $state_data['user_id'] ?? 0 );
		$readonly = (bool) ( $state_data['readonly'] ?? true );

		if ( ! $user_id ) {
			$this->html_redirect( home_url( '/settings?tab=connections&subtab=contacts&error=' . urlencode( 'Invalid user session' ) ) );
		}

		// Get authorization code
		$code = $request->get_param( 'code' );
		if ( empty( $code ) ) {
			$this->html_redirect( home_url( '/settings?tab=connections&subtab=contacts&error=' . urlencode( 'No authorization code received' ) ) );
		}

		try {
			// Exchange code for tokens
			$tokens = GoogleOAuth::handle_contacts_callback( $code, $user_id, $readonly );

			// Determine actual access mode from granted scopes
			$access_mode = GoogleOAuth::get_contacts_access_mode( $tokens );

			// If user denied contacts scope but granted other scopes
			if ( $access_mode === 'none' ) {
				$this->html_redirect( home_url( '/settings?tab=connections&subtab=contacts&error=' . urlencode( 'Contacts permission was not granted. Please try again and allow access to your contacts.' ) ) );
			}

			// Get user email from token
			$email = $this->get_user_email_from_token( $tokens );

			// Create connection
			$connection = [
				'enabled'       => true,
				'access_mode'   => $access_mode,
				'credentials'   => $tokens,
				'email'         => $email,
				'connected_at'  => current_time( 'c' ),
				'last_sync'     => null,
				'last_error'    => null,
				'contact_count' => 0,
				'sync_token'    => null,
			];

			GoogleContactsConnection::save_connection( $user_id, $connection );

			// Set pending import flag for Phase 80 to pick up
			GoogleContactsConnection::set_pending_import( $user_id, true );

			// Redirect to settings page with success
			$this->html_redirect( home_url( '/settings?tab=connections&subtab=contacts&connected=google-contacts' ) );

		} catch ( \Exception $e ) {
			// Log error for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Google Contacts OAuth error: ' . $e->getMessage() );
			}
			$this->html_redirect( home_url( '/settings?tab=connections&subtab=contacts&error=' . urlencode( $e->getMessage() ) ) );
		}
	}

	/**
	 * Disconnect Google Contacts
	 *
	 * Removes the connection from user meta but does NOT revoke the token
	 * at Google (user might still have Calendar connected with same account).
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response with success message.
	 */
	public function disconnect( $request ) {
		$user_id = get_current_user_id();

		// Check if connected
		if ( ! GoogleContactsConnection::is_connected( $user_id ) ) {
			return new \WP_Error(
				'not_connected',
				__( 'Google Contacts is not connected.', 'caelis' ),
				[ 'status' => 400 ]
			);
		}

		// Delete connection (does NOT revoke at Google - user may have Calendar connected)
		GoogleContactsConnection::delete_connection( $user_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Google Contacts disconnected.', 'caelis' ),
			]
		);
	}

	/**
	 * Get user email from OAuth tokens
	 *
	 * Tries to extract email from id_token JWT or makes userinfo API call.
	 *
	 * @param array $tokens OAuth tokens including access_token and optionally id_token.
	 * @return string User email or empty string on failure.
	 */
	private function get_user_email_from_token( array $tokens ): string {
		// Try to decode email from id_token first (no API call needed)
		if ( ! empty( $tokens['id_token'] ) ) {
			$parts = explode( '.', $tokens['id_token'] );
			if ( count( $parts ) === 3 ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding JWT payload
				$payload = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true );
				if ( ! empty( $payload['email'] ) ) {
					return $payload['email'];
				}
			}
		}

		// Fallback: Make userinfo API call
		if ( ! empty( $tokens['access_token'] ) ) {
			$client = new \Google\Client();
			$client->setClientId( GOOGLE_CALENDAR_CLIENT_ID );
			$client->setClientSecret( GOOGLE_CALENDAR_CLIENT_SECRET );
			$client->setAccessToken( $tokens );

			try {
				$oauth2   = new \Google\Service\Oauth2( $client );
				$userinfo = $oauth2->userinfo->get();
				return $userinfo->getEmail() ?: '';
			} catch ( \Exception $e ) {
				// Log but don't fail - email is nice to have, not critical
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Google Contacts: Failed to get user email: ' . $e->getMessage() );
				}
				return '';
			}
		}

		return '';
	}

	/**
	 * Output an HTML redirect and exit
	 *
	 * Used in OAuth callbacks where wp_redirect() doesn't work because
	 * REST API endpoints process the response object rather than executing
	 * the HTTP redirect headers.
	 *
	 * @param string $url The URL to redirect to.
	 */
	private function html_redirect( $url ) {
		$safe_url = esc_url( $url );

		// Clear any output buffers that REST API may have started
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Send HTTP redirect header - most reliable method
		header( 'Location: ' . $safe_url, true, 302 );

		// Fallback HTML redirect if headers were already sent
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		echo '<!DOCTYPE html><html><head>';
		echo '<script>window.location.replace(' . wp_json_encode( $safe_url ) . ');</script>';
		echo '</head><body>Redirecting...</body></html>';
		exit;
	}
}
