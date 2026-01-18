<?php
/**
 * Google OAuth Class
 *
 * Handles Google OAuth2 flow for calendar and contacts integration including:
 * - Authorization URL generation
 * - OAuth callback handling
 * - Token storage and refresh
 * - Incremental authorization for additional scopes
 */

namespace Caelis\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleOAuth {

	/**
	 * Google Calendar readonly scope
	 */
	private const SCOPES = [ 'https://www.googleapis.com/auth/calendar.readonly' ];

	/**
	 * Google Contacts readonly scope (People API)
	 */
	public const CONTACTS_SCOPE_READONLY = 'https://www.googleapis.com/auth/contacts.readonly';

	/**
	 * Google Contacts read/write scope (People API)
	 */
	public const CONTACTS_SCOPE_READWRITE = 'https://www.googleapis.com/auth/contacts';

	/**
	 * Check if Google OAuth is configured
	 *
	 * @return bool True if client ID and secret are configured
	 */
	public static function is_configured(): bool {
		return defined( 'GOOGLE_OAUTH_CLIENT_ID' )
			&& ! empty( GOOGLE_OAUTH_CLIENT_ID )
			&& defined( 'GOOGLE_OAUTH_CLIENT_SECRET' )
			&& ! empty( GOOGLE_OAUTH_CLIENT_SECRET );
	}

	/**
	 * Get Google API client instance configured with credentials
	 *
	 * @return \Google\Client|null Configured client or null if not configured
	 */
	public static function get_client(): ?\Google\Client {
		if ( ! self::is_configured() ) {
			return null;
		}

		$client = new \Google\Client();
		$client->setClientId( GOOGLE_OAUTH_CLIENT_ID );
		$client->setClientSecret( GOOGLE_OAUTH_CLIENT_SECRET );
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
		return [
			'access_token'  => $token['access_token'] ?? '',
			'refresh_token' => $token['refresh_token'] ?? '',
			'expires_at'    => time() + ( $token['expires_in'] ?? 3600 ),
			'token_type'    => $token['token_type'] ?? 'Bearer',
			'scope'         => $token['scope'] ?? implode( ' ', self::SCOPES ),
		];
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

		$credentials = \Caelis\Data\CredentialEncryption::decrypt( $connection['credentials'] );
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
						\PRM_Calendar_Connections::update_credentials(
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
						\PRM_Calendar_Connections::update_connection(
							$user_id,
							$connection['id'],
							[ 'last_error' => $e->getMessage() ]
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

		return [
			'access_token'  => $token['access_token'] ?? '',
			'refresh_token' => $token['refresh_token'] ?? '',
			'expires_at'    => time() + ( $token['expires_in'] ?? 3600 ),
			'token_type'    => $token['token_type'] ?? 'Bearer',
			'scope'         => $token['scope'] ?? implode( ' ', self::SCOPES ),
		];
	}

	/**
	 * Get Google API client configured for Contacts authorization
	 *
	 * Creates a client configured for Google Contacts (People API) scope with
	 * incremental authorization enabled to preserve existing Calendar scopes.
	 *
	 * @param bool $include_granted_scopes Enable incremental auth to preserve existing scopes.
	 * @param bool $readonly               Request read-only or read-write access.
	 * @return \Google\Client|null Configured client or null if not configured.
	 */
	public static function get_contacts_client( bool $include_granted_scopes = true, bool $readonly = true ): ?\Google\Client {
		if ( ! self::is_configured() ) {
			return null;
		}

		$client = new \Google\Client();
		$client->setClientId( GOOGLE_OAUTH_CLIENT_ID );
		$client->setClientSecret( GOOGLE_OAUTH_CLIENT_SECRET );
		$client->setRedirectUri( rest_url( 'prm/v1/google-contacts/callback' ) );

		// Set contacts scope based on access mode, plus email for user identification
		$contacts_scope = $readonly ? self::CONTACTS_SCOPE_READONLY : self::CONTACTS_SCOPE_READWRITE;
		$client->setScopes( [ $contacts_scope, 'email' ] );

		// CRITICAL: Enable incremental authorization to preserve existing Calendar scopes
		$client->setIncludeGrantedScopes( $include_granted_scopes );

		$client->setAccessType( 'offline' );
		$client->setPrompt( 'consent' ); // Ensure refresh token is returned

		return $client;
	}

	/**
	 * Generate OAuth authorization URL for Contacts scope
	 *
	 * @param int  $user_id  WordPress user ID.
	 * @param bool $readonly Request read-only or read-write access.
	 * @return string Authorization URL or empty string on failure.
	 */
	public static function get_contacts_auth_url( int $user_id, bool $readonly = true ): string {
		$client = self::get_contacts_client( true, $readonly );
		if ( ! $client ) {
			return '';
		}

		// Create state with a random token stored in transient
		// Use separate transient key to distinguish from calendar OAuth
		$token = wp_generate_password( 32, false );
		set_transient(
			'google_contacts_oauth_' . $token,
			[
				'user_id'  => $user_id,
				'readonly' => $readonly,
			],
			10 * MINUTE_IN_SECONDS
		);

		$client->setState( $token );

		return $client->createAuthUrl();
	}

	/**
	 * Exchange authorization code for tokens (contacts-specific)
	 *
	 * @param string $code     Authorization code from Google.
	 * @param int    $user_id  WordPress user ID (for logging/verification).
	 * @param bool   $readonly Whether readonly scope was requested.
	 * @return array Token data array with access_token, refresh_token, expires_at, etc.
	 * @throws Exception On token exchange failure.
	 */
	public static function handle_contacts_callback( string $code, int $user_id, bool $readonly = true ): array {
		$client = self::get_contacts_client( true, $readonly );
		if ( ! $client ) {
			throw new \Exception( 'Google OAuth is not configured' );
		}

		// Exchange code for tokens
		$token = $client->fetchAccessTokenWithAuthCode( $code );

		if ( isset( $token['error'] ) ) {
			throw new \Exception( $token['error_description'] ?? $token['error'] );
		}

		// Build token credential structure
		return [
			'access_token'  => $token['access_token'] ?? '',
			'refresh_token' => $token['refresh_token'] ?? '',
			'expires_at'    => time() + ( $token['expires_in'] ?? 3600 ),
			'token_type'    => $token['token_type'] ?? 'Bearer',
			'scope'         => $token['scope'] ?? '',
			'id_token'      => $token['id_token'] ?? '',
		];
	}

	/**
	 * Check if credentials include any contacts scope
	 *
	 * @param array $credentials Token credentials with scope field.
	 * @return bool True if contacts scope is present.
	 */
	public static function has_contacts_scope( array $credentials ): bool {
		$scope_string = $credentials['scope'] ?? '';
		$scopes       = explode( ' ', $scope_string );

		return in_array( self::CONTACTS_SCOPE_READONLY, $scopes, true )
			|| in_array( self::CONTACTS_SCOPE_READWRITE, $scopes, true );
	}

	/**
	 * Get the contacts access mode from credentials
	 *
	 * @param array $credentials Token credentials with scope field.
	 * @return string 'readwrite', 'readonly', or 'none'.
	 */
	public static function get_contacts_access_mode( array $credentials ): string {
		$scope_string = $credentials['scope'] ?? '';
		$scopes       = explode( ' ', $scope_string );

		if ( in_array( self::CONTACTS_SCOPE_READWRITE, $scopes, true ) ) {
			return 'readwrite';
		}
		if ( in_array( self::CONTACTS_SCOPE_READONLY, $scopes, true ) ) {
			return 'readonly';
		}
		return 'none';
	}
}
