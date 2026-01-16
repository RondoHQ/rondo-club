<?php
/**
 * Google OAuth Class
 *
 * Handles Google OAuth2 flow for calendar integration including:
 * - Authorization URL generation
 * - OAuth callback handling
 * - Token storage and refresh
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Google_OAuth {

	/**
	 * Google Calendar readonly scope
	 */
	private const SCOPES = array( 'https://www.googleapis.com/auth/calendar.readonly' );

	/**
	 * Check if Google OAuth is configured
	 *
	 * @return bool True if client ID and secret are configured
	 */
	public static function is_configured(): bool {
		return defined( 'GOOGLE_CALENDAR_CLIENT_ID' )
			&& ! empty( GOOGLE_CALENDAR_CLIENT_ID )
			&& defined( 'GOOGLE_CALENDAR_CLIENT_SECRET' )
			&& ! empty( GOOGLE_CALENDAR_CLIENT_SECRET );
	}

	/**
	 * Get Google API client instance configured with credentials
	 *
	 * @return Google\Client|null Configured client or null if not configured
	 */
	public static function get_client(): ?Google\Client {
		if ( ! self::is_configured() ) {
			return null;
		}

		$client = new Google\Client();
		$client->setClientId( GOOGLE_CALENDAR_CLIENT_ID );
		$client->setClientSecret( GOOGLE_CALENDAR_CLIENT_SECRET );
		$client->setRedirectUri( self::get_redirect_uri() );
		$client->setScopes( self::SCOPES );
		$client->setAccessType( 'offline' );
		$client->setPrompt( 'consent' );

		return $client;
	}

	/**
	 * Get the OAuth redirect URI
	 *
	 * @return string The callback URL
	 */
	private static function get_redirect_uri(): string {
		return rest_url( 'prm/v1/calendar/auth/google/callback' );
	}

	/**
	 * Generate OAuth authorization URL with state parameter
	 *
	 * @param int $user_id WordPress user ID
	 * @return string Authorization URL or empty string on failure
	 */
	public static function get_auth_url( int $user_id ): string {
		$client = self::get_client();
		if ( ! $client ) {
			return '';
		}

		// Create state with a random token stored in transient (not session-dependent)
		// WordPress nonces don't work reliably with OAuth because cross-site redirects
		// may not preserve the authentication cookies needed for nonce verification
		$token = wp_generate_password( 32, false );
		set_transient( 'google_oauth_' . $token, $user_id, 10 * MINUTE_IN_SECONDS );

		$client->setState( $token );

		return $client->createAuthUrl();
	}

	/**
	 * Exchange authorization code for tokens
	 *
	 * @param string $code Authorization code from Google
	 * @param int    $user_id WordPress user ID (for logging/verification)
	 * @return array Token data array with access_token, refresh_token, expires_at, etc.
	 * @throws Exception On token exchange failure
	 */
	public static function handle_callback( string $code, int $user_id ): array {
		$client = self::get_client();
		if ( ! $client ) {
			throw new Exception( 'Google OAuth is not configured' );
		}

		// Exchange code for tokens
		$token = $client->fetchAccessTokenWithAuthCode( $code );

		if ( isset( $token['error'] ) ) {
			throw new Exception( $token['error_description'] ?? $token['error'] );
		}

		// Build token credential structure
		return array(
			'access_token'  => $token['access_token'] ?? '',
			'refresh_token' => $token['refresh_token'] ?? '',
			'expires_at'    => time() + ( $token['expires_in'] ?? 3600 ),
			'token_type'    => $token['token_type'] ?? 'Bearer',
			'scope'         => $token['scope'] ?? implode( ' ', self::SCOPES ),
		);
	}

	/**
	 * Get valid access token, refreshing if needed
	 *
	 * @param array $connection Calendar connection with encrypted credentials
	 * @return string|null Valid access token or null on failure
	 */
	public static function get_access_token( array $connection ): ?string {
		// Decrypt credentials
		if ( empty( $connection['credentials'] ) ) {
			return null;
		}

		$credentials = PRM_Credential_Encryption::decrypt( $connection['credentials'] );
		if ( ! $credentials || empty( $credentials['access_token'] ) ) {
			return null;
		}

		// Check if token expires within 5 minutes (proactive refresh)
		$buffer_time = 5 * 60; // 5 minutes
		$expires_at  = $credentials['expires_at'] ?? 0;

		if ( time() + $buffer_time >= $expires_at ) {
			// Token expired or about to expire, refresh it
			if ( empty( $credentials['refresh_token'] ) ) {
				return null;
			}

			try {
				$new_credentials = self::refresh_token( $credentials['refresh_token'] );

				// Preserve the refresh token if not returned in refresh response
				if ( empty( $new_credentials['refresh_token'] ) ) {
					$new_credentials['refresh_token'] = $credentials['refresh_token'];
				}

				// Update connection with new credentials
				if ( ! empty( $connection['id'] ) ) {
					// Find user_id for this connection
					// Connection must have a user context for update
					$user_id = $connection['user_id'] ?? get_current_user_id();
					if ( $user_id ) {
						PRM_Calendar_Connections::update_credentials(
							$user_id,
							$connection['id'],
							$new_credentials
						);
					}
				}

				return $new_credentials['access_token'];
			} catch ( Exception $e ) {
				// Log error but don't auto-delete - let user see error and choose to reconnect
				if ( ! empty( $connection['id'] ) ) {
					$user_id = $connection['user_id'] ?? get_current_user_id();
					if ( $user_id ) {
						PRM_Calendar_Connections::update_connection(
							$user_id,
							$connection['id'],
							array( 'last_error' => $e->getMessage() )
						);
					}
				}
				return null;
			}
		}

		return $credentials['access_token'];
	}

	/**
	 * Refresh expired token using refresh_token
	 *
	 * @param string $refresh_token The refresh token
	 * @return array New token credentials
	 * @throws Exception On refresh failure
	 */
	private static function refresh_token( string $refresh_token ): array {
		$client = self::get_client();
		if ( ! $client ) {
			throw new Exception( 'Google OAuth is not configured' );
		}

		$client->fetchAccessTokenWithRefreshToken( $refresh_token );
		$token = $client->getAccessToken();

		if ( isset( $token['error'] ) ) {
			throw new Exception( $token['error_description'] ?? $token['error'] );
		}

		return array(
			'access_token'  => $token['access_token'] ?? '',
			'refresh_token' => $token['refresh_token'] ?? '',
			'expires_at'    => time() + ( $token['expires_in'] ?? 3600 ),
			'token_type'    => $token['token_type'] ?? 'Bearer',
			'scope'         => $token['scope'] ?? implode( ' ', self::SCOPES ),
		);
	}
}
