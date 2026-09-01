<?php
/**
 * OpenID Connect authorization-code service.
 *
 * @package Rondo\Identity
 */

namespace Rondo\Identity;

use Rondo\Config\ClubConfig;
use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Implement the narrow first-party authorization-code flow used by FreeScout. */
final class OidcAuthorizationService {

	public const CODE_TTL_SECONDS         = 2 * MINUTE_IN_SECONDS;
	public const ACCESS_TOKEN_TTL_SECONDS = 5 * MINUTE_IN_SECONDS;
	public const VERIFICATION_TTL_SECONDS = 2 * HOUR_IN_SECONDS;
	public const PENDING_TTL_SECONDS      = 10 * MINUTE_IN_SECONDS;

	private const CODE_PREFIX         = 'rondo_oidc_code_';
	private const ACCESS_PREFIX       = 'rondo_oidc_access_';
	private const PENDING_PREFIX      = 'rondo_oidc_pending_';
	private const VERIFICATION_PREFIX = 'rondo_oidc_verify_';
	private const RATE_USER_PREFIX    = 'rondo_oidc_verify_user_';
	private const RATE_IP_PREFIX      = 'rondo_oidc_verify_ip_';
	private const TOKEN_LOCK_PREFIX   = 'rondo_oidc_token_used_';
	private const RATE_LIMIT          = 3;

	/** Return OpenID Provider and OAuth authorization-server metadata. */
	public static function metadata(): array {
		$issuer = self::issuer();

		return [
			'issuer'                                => $issuer,
			'authorization_endpoint'                => $issuer . '/oauth/authorize',
			'token_endpoint'                        => $issuer . '/oauth/token',
			'userinfo_endpoint'                     => $issuer . '/oauth/userinfo',
			'jwks_uri'                              => $issuer . '/oauth/jwks',
			'response_types_supported'              => [ 'code' ],
			'grant_types_supported'                 => [ 'authorization_code' ],
			'subject_types_supported'               => [ 'public' ],
			'id_token_signing_alg_values_supported' => [ 'RS256' ],
			'scopes_supported'                      => OidcClientRegistry::SCOPES,
			'token_endpoint_auth_methods_supported' => [ 'client_secret_basic' ],
			'code_challenge_methods_supported'      => [ 'S256' ],
			'claims_supported'                      => [ 'sub', 'iss', 'aud', 'iat', 'exp', 'nonce', 'auth_time', 'at_hash', 'email', 'email_verified', 'name', 'given_name', 'family_name', 'picture' ],
		];
	}

	/** Validate a browser authorization request and create an opaque pending handle. */
	public static function prepare_authorization( array $params, int $user_id ) {
		$client_id    = self::param( $params, 'client_id' );
		$redirect_uri = self::param( $params, 'redirect_uri' );
		$state        = self::param( $params, 'state' );
		$client       = OidcClientRegistry::find( $client_id );

		if ( ! is_array( $client ) || empty( $client['enabled'] ) ) {
			return self::error( 'invalid_request', 'De OpenID Connect-client is onbekend of uitgeschakeld.' );
		}
		if ( ! OidcClientRegistry::redirect_allowed( $client, $redirect_uri ) ) {
			return self::error( 'invalid_request', 'De redirect-URL is niet geregistreerd.' );
		}
		if ( self::param( $params, 'response_type' ) !== 'code' ) {
			return self::error( 'unsupported_response_type', 'Alleen authorization code wordt ondersteund.', $redirect_uri, $state );
		}

		$scopes = OidcClientRegistry::scopes( self::param( $params, 'scope' ), (array) ( $client['allowed_scopes'] ?? [] ) );
		if ( is_wp_error( $scopes ) ) {
			return self::error( 'invalid_scope', $scopes->get_error_message(), $redirect_uri, $state );
		}

		$nonce     = self::param( $params, 'nonce' );
		$challenge = self::param( $params, 'code_challenge' );
		$method    = self::param( $params, 'code_challenge_method' );
		if ( ! self::valid_correlation_value( $state ) || ! self::valid_correlation_value( $nonce ) ) {
			$safe_state = self::valid_correlation_value( $state ) ? $state : '';
			return self::error( 'invalid_request', 'State en nonce zijn verplicht.', $redirect_uri, $safe_state );
		}
		if ( $method !== 'S256' || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $challenge ) ) {
			return self::error( 'invalid_request', 'PKCE S256 is verplicht.', $redirect_uri, $state );
		}

		$identity = OidcIdentity::resolve( $user_id, false );
		if ( is_wp_error( $identity ) ) {
			return self::error( 'access_denied', $identity->get_error_message(), $redirect_uri, $state );
		}

		$request = [
			'user_id'        => $user_id,
			'client_id'      => $client_id,
			'redirect_uri'   => $redirect_uri,
			'scopes'         => $scopes,
			'state'          => $state,
			'nonce'          => $nonce,
			'code_challenge' => $challenge,
			'auth_time'      => (int) $identity['auth_time'],
			'created_at'     => time(),
		];
		$pending = self::store_token( self::PENDING_PREFIX, $request, self::PENDING_TTL_SECONDS );

		return [
			'status'        => ! empty( $identity['email_verified'] ) ? 'consent' : 'verification_required',
			'pending_token' => $pending,
			'client_label'  => $client['label'],
			'scopes'        => $scopes,
			'email'         => $identity['email'],
		];
	}

	/** Approve or deny a pending request and return the exact redirect URL. */
	public static function decide( string $pending_token, int $user_id, bool $approved ) {
		$preview = self::read_token( self::PENDING_PREFIX, $pending_token );
		if ( ! is_array( $preview ) || (int) ( $preview['user_id'] ?? 0 ) !== $user_id ) {
			return new \WP_Error( 'rondo_oidc_request_expired', 'De autorisatieaanvraag is verlopen.', [ 'status' => 400 ] );
		}
		$request = self::consume_token( self::PENDING_PREFIX, $pending_token );
		if ( ! is_array( $request ) ) {
			return new \WP_Error( 'rondo_oidc_request_expired', 'De autorisatieaanvraag is verlopen.', [ 'status' => 400 ] );
		}

		$redirect_uri = (string) $request['redirect_uri'];
		$state        = (string) $request['state'];
		$client       = OidcClientRegistry::find( (string) $request['client_id'] );
		if ( ! is_array( $client ) || empty( $client['enabled'] ) || ! OidcClientRegistry::redirect_allowed( $client, $redirect_uri ) ) {
			return new \WP_Error( 'rondo_oidc_client_unavailable', 'De OpenID Connect-client is niet meer beschikbaar.', [ 'status' => 400 ] );
		}
		if ( ! $approved ) {
			return self::append_query(
				$redirect_uri,
				[
					'error' => 'access_denied',
					'state' => $state,
				]
				);
		}

		$identity = OidcIdentity::resolve( $user_id, true );
		if ( is_wp_error( $identity ) ) {
			return self::append_query(
				$redirect_uri,
				[
					'error' => 'access_denied',
					'state' => $state,
				]
				);
		}

		$code_payload = [
			'user_id'        => $user_id,
			'client_id'      => $request['client_id'],
			'redirect_uri'   => $redirect_uri,
			'scopes'         => $request['scopes'],
			'nonce'          => $request['nonce'],
			'code_challenge' => $request['code_challenge'],
			'auth_time'      => $request['auth_time'],
			'expires_at'     => time() + self::CODE_TTL_SECONDS,
		];
		$code         = self::store_token( self::CODE_PREFIX, $code_payload, self::CODE_TTL_SECONDS );
		update_user_meta( $user_id, 'rondo_oidc_last_authorized_at', gmdate( DATE_ATOM ) );
		update_user_meta( $user_id, 'rondo_oidc_last_authorized_client', hash( 'sha256', (string) $request['client_id'] ) );

		return self::append_query(
			$redirect_uri,
			[
				'code'  => $code,
				'state' => $state,
			]
			);
	}

	/** Send a dedicated proof link containing no OAuth parameters. */
	public static function send_verification( string $pending_token, int $user_id, string $ip ) {
		$request = self::read_token( self::PENDING_PREFIX, $pending_token );
		if ( ! is_array( $request ) || (int) ( $request['user_id'] ?? 0 ) !== $user_id ) {
			return new \WP_Error( 'rondo_oidc_request_expired', 'De autorisatieaanvraag is verlopen.', [ 'status' => 400 ] );
		}

		$identity = OidcIdentity::resolve( $user_id, false );
		if ( is_wp_error( $identity ) ) {
			return $identity;
		}
		if ( ! empty( $identity['email_verified'] ) ) {
			return [
				'already_verified' => true,
				'pending_token'    => $pending_token,
			];
		}
		if ( self::rate_limited( $user_id, $ip ) ) {
			return new \WP_Error( 'rondo_oidc_verification_rate_limited', 'Er zijn te veel verificatiemails aangevraagd. Probeer het later opnieuw.', [ 'status' => 429 ] );
		}

		self::record_rate( $user_id, $ip );
		$verification = self::store_token(
			self::VERIFICATION_PREFIX,
			[
				'user_id'    => $user_id,
				'email'      => $identity['email'],
				'request'    => $request,
				'expires_at' => time() + self::VERIFICATION_TTL_SECONDS,
			],
			self::VERIFICATION_TTL_SECONDS
		);
		$url          = home_url( '/oauth/verify-email/' . $verification );
		$club_name    = ClubConfig::get_club_name() ?: 'Rondo Club';
		$subject      = sprintf( 'Bevestig je e-mailadres voor FreeScout bij %s', $club_name );
		$html         = EmailTemplate::render(
			[
				'brand_name' => $club_name,
				'preheader'  => $subject,
				'eyebrow'    => 'FreeScout',
				'heading'    => 'Bevestig je e-mailadres',
				'body_html'  => '<p>Je wilt Rondo gebruiken om in te loggen bij FreeScout. Bevestig met de knop dat dit e-mailadres van jou is.</p><p>De link is twee uur geldig en bevat geen FreeScout-inloggegevens.</p>',
				'cta_url'    => $url,
				'cta_label'  => 'E-mailadres bevestigen',
			]
		);
		if ( ! wp_mail( $identity['email'], $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
			self::consume_token( self::VERIFICATION_PREFIX, $verification );
			return new \WP_Error( 'rondo_oidc_verification_send_failed', 'De verificatiemail kon niet worden verstuurd.', [ 'status' => 500 ] );
		}

		return [
			'sent'  => true,
			'email' => $identity['email'],
		];
	}

	/** Consume an emailed proof and create a fresh consent handle. */
	public static function consume_verification( string $token, int $user_id ) {
		$preview = self::read_token( self::VERIFICATION_PREFIX, $token );
		if ( ! is_array( $preview ) || (int) ( $preview['user_id'] ?? 0 ) !== $user_id || (int) ( $preview['expires_at'] ?? 0 ) < time() ) {
			return new \WP_Error( 'rondo_oidc_verification_invalid', 'Deze verificatielink is ongeldig of verlopen.', [ 'status' => 400 ] );
		}
		$payload = self::consume_token( self::VERIFICATION_PREFIX, $token );
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'rondo_oidc_verification_invalid', 'Deze verificatielink is ongeldig of verlopen.', [ 'status' => 400 ] );
		}

		$request = $payload['request'] ?? null;
		$client  = is_array( $request ) ? OidcClientRegistry::find( (string) ( $request['client_id'] ?? '' ) ) : null;
		if ( ! is_array( $request ) || ! is_array( $client ) || empty( $client['enabled'] ) || ! OidcClientRegistry::redirect_allowed( $client, (string) ( $request['redirect_uri'] ?? '' ) ) ) {
			return new \WP_Error( 'rondo_oidc_client_unavailable', 'De OpenID Connect-client is niet meer beschikbaar.', [ 'status' => 400 ] );
		}

		$identity = OidcIdentity::resolve( $user_id, false );
		if ( is_wp_error( $identity ) || ! hash_equals( (string) $payload['email'], (string) ( $identity['email'] ?? '' ) ) ) {
			return new \WP_Error( 'rondo_oidc_email_changed', 'Het e-mailadres of de accounttoegang is ondertussen gewijzigd.', [ 'status' => 400 ] );
		}
		$marked = OidcIdentity::mark_email_verified( $user_id, (string) $payload['email'], 'oidc_email' );
		if ( is_wp_error( $marked ) ) {
			return $marked;
		}

		return [
			'pending_token' => self::store_token( self::PENDING_PREFIX, $request, self::PENDING_TTL_SECONDS ),
			'client_label'  => $client['label'],
			'scopes'        => $request['scopes'],
			'email'         => $payload['email'],
		];
	}

	/** Exchange a single-use code for an access token and signed ID token. */
	public static function exchange_code( array $params, string $authorization_header ) {
		$credentials = self::basic_credentials( $authorization_header );
		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}
		$client = OidcClientRegistry::find( $credentials['client_id'] );
		if ( ! is_array( $client ) || empty( $client['enabled'] ) || ! OidcClientRegistry::verify_secret( $client, $credentials['client_secret'] ) ) {
			return self::token_error( 'invalid_client', 'De client kon niet worden geverifieerd.', 401 );
		}
		if ( isset( $params['client_secret'] ) ) {
			return self::token_error( 'invalid_request', 'Client secrets in de aanvraagbody worden niet geaccepteerd.' );
		}
		if ( isset( $params['client_id'] ) && ! hash_equals( $credentials['client_id'], self::param( $params, 'client_id' ) ) ) {
			return self::token_error( 'invalid_client', 'De client-ID in de aanvraagbody komt niet overeen.', 401 );
		}
		if ( self::param( $params, 'grant_type' ) !== 'authorization_code' ) {
			return self::token_error( 'unsupported_grant_type', 'Alleen authorization_code wordt ondersteund.' );
		}

		$code = self::param( $params, 'code' );
		if ( ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $code ) ) {
			return self::token_error( 'invalid_grant', 'De autorisatiecode is ongeldig.' );
		}
		$payload = self::consume_token( self::CODE_PREFIX, $code );
		if ( ! is_array( $payload ) ) {
			return self::token_error( 'invalid_grant', 'De autorisatiecode is ongeldig of al gebruikt.' );
		}

		$redirect_uri = self::param( $params, 'redirect_uri' );
		$verifier     = self::param( $params, 'code_verifier' );
		if ( (int) ( $payload['expires_at'] ?? 0 ) < time()
			|| ! hash_equals( $credentials['client_id'], (string) ( $payload['client_id'] ?? '' ) )
			|| ! hash_equals( (string) ( $payload['redirect_uri'] ?? '' ), $redirect_uri )
			|| ! self::verify_pkce( $verifier, (string) ( $payload['code_challenge'] ?? '' ) )
		) {
			return self::token_error( 'invalid_grant', 'De autorisatiecode kon niet worden gevalideerd.' );
		}

		$identity = OidcIdentity::resolve( (int) $payload['user_id'], true );
		if ( is_wp_error( $identity ) ) {
			return self::token_error( 'invalid_grant', 'De Rondo-identiteit is niet meer beschikbaar.' );
		}

		$access_token = self::random_value( 32 );
		$issued_at    = time();
		$expires_at   = $issued_at + self::ACCESS_TOKEN_TTL_SECONDS;
		$access_data  = [
			'user_id'    => $identity['user_id'],
			'client_id'  => $credentials['client_id'],
			'sub'        => $identity['sub'],
			'scopes'     => $payload['scopes'],
			'expires_at' => $expires_at,
		];
		set_transient( self::ACCESS_PREFIX . hash( 'sha256', $access_token ), $access_data, self::ACCESS_TOKEN_TTL_SECONDS );

		$id_claims = array_merge(
			OidcIdentity::claims( $identity, (array) $payload['scopes'] ),
			[
				'iss'       => self::issuer(),
				'aud'       => $credentials['client_id'],
				'iat'       => $issued_at,
				'exp'       => $expires_at,
				'nonce'     => $payload['nonce'],
				'auth_time' => (int) $payload['auth_time'],
				'at_hash'   => self::at_hash( $access_token ),
			]
		);
		$id_token  = OidcKeyStore::sign( $id_claims );
		if ( is_wp_error( $id_token ) ) {
			delete_transient( self::ACCESS_PREFIX . hash( 'sha256', $access_token ) );
			return self::token_error( 'server_error', 'Het ID-token kon niet worden uitgegeven.', 500 );
		}

		return [
			'access_token' => $access_token,
			'token_type'   => 'Bearer',
			'expires_in'   => self::ACCESS_TOKEN_TTL_SECONDS,
			'id_token'     => $id_token,
			'scope'        => implode( ' ', (array) $payload['scopes'] ),
		];
	}

	/** Resolve a bearer token into the current narrowly scoped UserInfo claims. */
	public static function userinfo( string $authorization_header ) {
		if ( ! preg_match( '/^Bearer\s+([A-Za-z0-9_-]{43})$/i', trim( $authorization_header ), $matches ) ) {
			return self::token_error( 'invalid_token', 'Een geldig bearer token is verplicht.', 401 );
		}
		$token   = $matches[1];
		$payload = get_transient( self::ACCESS_PREFIX . hash( 'sha256', $token ) );
		if ( ! is_array( $payload ) || (int) ( $payload['expires_at'] ?? 0 ) < time() ) {
			return self::token_error( 'invalid_token', 'Het access token is ongeldig of verlopen.', 401 );
		}

		$client   = OidcClientRegistry::find( (string) $payload['client_id'] );
		$identity = OidcIdentity::resolve( (int) $payload['user_id'], true );
		if ( ! is_array( $client ) || empty( $client['enabled'] ) || is_wp_error( $identity ) || ! hash_equals( (string) $payload['sub'], (string) ( $identity['sub'] ?? '' ) ) ) {
			return self::token_error( 'invalid_token', 'De identiteit is niet meer beschikbaar.', 401 );
		}

		return OidcIdentity::claims( $identity, (array) $payload['scopes'] );
	}

	/** Delete one atomic token-use marker after every possible replay window. */
	public static function cleanup_token_lock( string $option_name ): void {
		if ( str_starts_with( $option_name, self::TOKEN_LOCK_PREFIX ) ) {
			delete_option( $option_name );
		}
	}

	/** Return the configured issuer without a trailing slash. */
	public static function issuer(): string {
		return untrailingslashit( home_url( '/' ) );
	}

	/** Read an opaque pending handle for page rendering. */
	public static function pending( string $pending_token, int $user_id ): ?array {
		$request = self::read_token( self::PENDING_PREFIX, $pending_token );

		return is_array( $request ) && (int) ( $request['user_id'] ?? 0 ) === $user_id ? $request : null;
	}

	/** Build an OAuth redirect URL without altering registered URI components. */
	public static function append_query( string $url, array $params ): string {
		$separator = str_contains( $url, '?' ) ? '&' : '?';

		return $url . $separator . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	private static function error( string $code, string $description, string $redirect_uri = '', string $state = '' ): \WP_Error {
		return new \WP_Error(
			'rondo_oidc_authorization_error',
			$description,
			[
				'status'       => 400,
				'oauth_error'  => $code,
				'redirect_uri' => $redirect_uri,
				'state'        => $state,
			]
		);
	}

	private static function token_error( string $code, string $description, int $status = 400 ): \WP_Error {
		return new \WP_Error(
			'rondo_oidc_token_error',
			$description,
			[
				'status'      => $status,
				'oauth_error' => $code,
			]
		);
	}

	private static function basic_credentials( string $header ) {
		if ( ! preg_match( '/^Basic\s+([^\s]+)$/i', trim( $header ), $matches ) ) {
			return self::token_error( 'invalid_client', 'HTTP Basic-clientauthenticatie is verplicht.', 401 );
		}
		$decoded = base64_decode( $matches[1], true );
		if ( ! is_string( $decoded ) || ! str_contains( $decoded, ':' ) ) {
			return self::token_error( 'invalid_client', 'De clientauthenticatie is ongeldig.', 401 );
		}
		[ $client_id, $secret ] = explode( ':', $decoded, 2 );

		return [
			'client_id'     => rawurldecode( $client_id ),
			'client_secret' => rawurldecode( $secret ),
		];
	}

	private static function verify_pkce( string $verifier, string $challenge ): bool {
		if ( ! preg_match( '/^[A-Za-z0-9.\-_~]{43,128}$/', $verifier ) ) {
			return false;
		}
		$calculated = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );

		return hash_equals( $challenge, $calculated );
	}

	private static function at_hash( string $access_token ): string {
		$digest = hash( 'sha256', $access_token, true );

		return rtrim( strtr( base64_encode( substr( $digest, 0, intdiv( strlen( $digest ), 2 ) ) ), '+/', '-_' ), '=' );
	}

	private static function store_token( string $prefix, array $payload, int $ttl ): string {
		$token = self::random_value( 32 );
		set_transient( $prefix . hash( 'sha256', $token ), $payload, $ttl );

		return $token;
	}

	private static function read_token( string $prefix, string $token ) {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $token ) ) {
			return false;
		}

		return get_transient( $prefix . hash( 'sha256', $token ) );
	}

	private static function consume_token( string $prefix, string $token ) {
		$payload = self::read_token( $prefix, $token );
		if ( $payload === false ) {
			return false;
		}

		$lock = self::TOKEN_LOCK_PREFIX . substr( hash( 'sha256', $prefix . '|' . $token ), 0, 40 );
		if ( ! add_option( $lock, time(), '', false ) ) {
			return false;
		}
		wp_schedule_single_event( time() + 3 * HOUR_IN_SECONDS, 'rondo_oidc_cleanup_token_lock', [ $lock ] );
		delete_transient( $prefix . hash( 'sha256', $token ) );

		return $payload;
	}

	private static function valid_correlation_value( string $value ): bool {
		return strlen( $value ) >= 16 && strlen( $value ) <= 512 && preg_match( '/^[A-Za-z0-9._~-]+$/', $value ) === 1;
	}

	/** Return one scalar protocol parameter without triggering array conversions. */
	private static function param( array $params, string $key ): string {
		$value = $params[ $key ] ?? '';

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	private static function rate_limited( int $user_id, string $ip ): bool {
		return (int) get_transient( self::RATE_USER_PREFIX . $user_id ) >= self::RATE_LIMIT
			|| (int) get_transient( self::RATE_IP_PREFIX . hash( 'sha256', $ip ) ) >= self::RATE_LIMIT;
	}

	private static function record_rate( int $user_id, string $ip ): void {
		foreach ( [ self::RATE_USER_PREFIX . $user_id, self::RATE_IP_PREFIX . hash( 'sha256', $ip ) ] as $key ) {
			$count = (int) get_transient( $key );
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}
	}

	private static function random_value( int $bytes ): string {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}
}
