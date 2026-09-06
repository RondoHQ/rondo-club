<?php
/**
 * Plugin Name: Rondo Mobile Spike (development only)
 * Description: Opt-in, read-only native login experiment. Never loaded by the theme.
 * Version: 0.1.0
 *
 * @package Rondo\MobileSpike
 */

namespace Rondo\MobileSpike;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Narrow development adapter; no change to the confidential FreeScout OIDC provider. */
final class Plugin {
	public const CLIENT   = 'rondo-mobile-spike';
	public const CALLBACK = 'club.rondo.spike://oauth/callback';
	public const SCOPE    = 'rondo:spike:read';
	public const NS       = 'rondo-mobile-spike/v1';
	private const CODE    = 'rondo_mobile_code_';
	private const SESSION = 'rondo_mobile_session_';

	public static function enabled(): bool {
		return defined( 'RONDO_MOBILE_SPIKE' ) && RONDO_MOBILE_SPIKE === true && in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
	}

	public function __construct() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'routes' ] );
		add_action( 'admin_post_rondo_mobile_spike_authorize', [ $this, 'authorize' ] );
		add_action( 'admin_post_nopriv_rondo_mobile_spike_authorize', [ $this, 'authorize' ] );
		add_action( 'rondo_mobile_spike_cleanup', 'delete_option' );
	}

	public function routes(): void {
		foreach ( [
			'config' => 'GET',
			'token'  => 'POST',
			'read'   => 'GET',
			'revoke' => 'POST',
		] as $route => $method ) {
			register_rest_route(
				self::NS,
				'/' . $route,
				[
					'methods'             => $method,
					'callback'            => [ $this, $route ],
					'permission_callback' => [ self::class, 'enabled' ],
				]
			);
		}
	}

	public function config(): \WP_REST_Response {
		return self::response(
			[
				'protocol' => 'rondo-mobile-spike-v1',
				'club_url' => untrailingslashit( home_url() ),
			]
			);
	}

	/** Validate every redirect and PKCE field before any login or consent redirect. */
	public static function validate( array $params ) {
		foreach ( [
			'client_id'             => self::CLIENT,
			'redirect_uri'          => self::CALLBACK,
			'scope'                 => self::SCOPE,
			'response_type'         => 'code',
			'code_challenge_method' => 'S256',
		] as $key => $value ) {
			if ( ( $params[ $key ] ?? null ) !== $value ) {
				return self::error( 'invalid_request', 400 );
			}
		}
		foreach ( [ 'state', 'code_challenge' ] as $key ) {
			if ( ! is_string( $params[ $key ] ?? null ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $params[ $key ] ) ) {
				return self::error( 'invalid_request', 400 );
			}
		}
		return true;
	}

	public function authorize(): void {
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Frame-Options: DENY' );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( ! self::enabled() || ! in_array( $method, [ 'GET', 'POST' ], true ) ) {
			wp_die( 'Deze proef is niet beschikbaar.', '', [ 'response' => 403 ] );
		}
		// The public GET carries no mutation. The POST below requires the authenticated user's nonce.
		$params = $method === 'POST' ? wp_unslash( $_POST ) : wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
		$valid  = self::validate( $params );
		if ( is_wp_error( $valid ) ) {
			wp_die( 'Ongeldige app-aanvraag.', '', [ 'response' => 400 ] );
		}
		if ( ! is_user_logged_in() ) {
			$return = add_query_arg( array_intersect_key( $params, array_flip( [ 'action', 'client_id', 'redirect_uri', 'scope', 'response_type', 'code_challenge_method', 'state', 'code_challenge' ] ) ), admin_url( 'admin-post.php' ) );
			wp_safe_redirect( wp_login_url( $return ) );
			exit;
		}
		if ( $method === 'POST' ) {
			if ( ! is_string( $params['_wpnonce'] ?? null ) || ! wp_verify_nonce( $params['_wpnonce'], 'rondo_mobile_spike_authorize' ) ) {
				wp_die( 'De toestemming is verlopen.', '', [ 'response' => 403 ] );
			}
			$query = [
				'state' => $params['state'],
				'error' => 'access_denied',
			];
			if ( ( $params['decision'] ?? '' ) === 'approve' ) {
				$code = self::issue( $params, get_current_user_id() );
				if ( is_wp_error( $code ) ) {
					wp_die( 'Dit account is niet beschikbaar.', '', [ 'response' => 403 ] );
				}
				$query = [
					'state' => $params['state'],
					'code'  => $code,
				];
			}
			wp_redirect( self::CALLBACK . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed private-use callback, no input-controlled destination.
			exit;
		}
		echo '<!doctype html><html lang="nl"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rondo Proef verbinden</title><body><main><h1>Rondo Proef verbinden</h1><p>Je geeft de proefapp vijf minuten toegang om je eigen gegevens bij deze club te lezen.</p><form method="post">';
		wp_nonce_field( 'rondo_mobile_spike_authorize' );
		foreach ( [ 'action', 'client_id', 'redirect_uri', 'scope', 'response_type', 'code_challenge_method', 'state', 'code_challenge' ] as $key ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $params[ $key ] ) . '">';
		}
		echo '<button name="decision" value="approve">Verbinden</button> <button name="decision" value="deny">Annuleren</button></form></main></body></html>';
		exit;
	}

	public static function issue( array $params, int $user_id ) {
		$valid = self::validate( $params );
		$user  = get_userdata( $user_id );
		if ( ! self::enabled() || is_wp_error( $valid ) || ! $user instanceof \WP_User || ! user_can( $user, 'read' ) ) {
			return self::error( 'access_denied', 403 );
		}
		return self::store(
			self::CODE,
			[
				'user_id'   => $user_id,
				'password'  => wp_hash( $user->user_pass ),
				'challenge' => $params['code_challenge'],
			],
			120
			);
	}

	public function token( \WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: [];
		if ( ( $params['grant_type'] ?? null ) !== 'authorization_code' || ( $params['client_id'] ?? null ) !== self::CLIENT || ( $params['redirect_uri'] ?? null ) !== self::CALLBACK || ! is_string( $params['code_verifier'] ?? null ) || ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $params['code_verifier'] ) ) {
			return self::error( 'invalid_grant', 400 );
		}
		$code = is_string( $params['code'] ?? null ) ? $params['code'] : '';
		$data = self::load( self::CODE, $code );
		if ( ! $data || ! hash_equals( $data['challenge'], self::base64url( hash( 'sha256', $params['code_verifier'], true ) ) ) || ! self::user( $data ) ) {
			return self::error( 'invalid_grant', 400 );
		}
		// add_option is an atomic unique claim; a transient get/delete alone is replayable under concurrency.
		$lock = 'rondo_mobile_used_' . hash( 'sha256', $code );
		if ( ! add_option( $lock, time(), '', false ) ) {
			return self::error( 'invalid_grant', 400 );
		}
		wp_schedule_single_event( time() + 600, 'rondo_mobile_spike_cleanup', [ $lock ] );
		delete_transient( self::CODE . hash( 'sha256', $code ) );
		$token = self::store(
			self::SESSION,
			[
				'user_id'  => $data['user_id'],
				'password' => $data['password'],
			],
			300
			);
		return self::response(
			[
				'access_token' => $token,
				'token_type'   => 'Bearer',
				'expires_in'   => 300,
			]
			);
	}

	/** Dispatch only fixed reads through existing REST permission callbacks and field filters. */
	public function read( \WP_REST_Request $request ) {
		$data = self::load( self::SESSION, self::bearer( $request ) );
		$user = $data ? self::user( $data ) : null;
		if ( ! $user ) {
			return self::error( 'invalid_token', 401 );
		}
		$routes = [
			'me'        => '/rondo/v1/user/me',
			'household' => '/rondo/v1/people/household',
		];
		$key    = $request->get_param( 'resource' );
		if ( ! is_string( $key ) || ! isset( $routes[ $key ] ) ) {
			return self::error( 'invalid_resource', 400 );
		}
		$previous = get_current_user_id();
		try {
			wp_set_current_user( $user->ID );
			$response = rest_do_request( new \WP_REST_Request( 'GET', $routes[ $key ] ) );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		} finally {
			wp_set_current_user( $previous );
		}
	}

	public function revoke( \WP_REST_Request $request ): \WP_REST_Response {
		$token = self::bearer( $request );
		if ( $token !== '' ) {
			delete_transient( self::SESSION . hash( 'sha256', $token ) );
		}
		return self::response( [ 'revoked' => true ] );
	}

	private static function bearer( \WP_REST_Request $request ): string {
		return preg_match( '/^Bearer ([A-Za-z0-9_-]{43})$/', $request->get_header( 'authorization' ), $match ) ? $match[1] : '';
	}

	private static function user( array $data ): ?\WP_User {
		$user = get_userdata( (int) $data['user_id'] );
		return $user instanceof \WP_User && user_can( $user, 'read' ) && hash_equals( $data['password'], wp_hash( $user->user_pass ) ) ? $user : null;
	}

	private static function store( string $prefix, array $data, int $ttl ): string {
		$token              = self::base64url( random_bytes( 32 ) );
		$data['expires_at'] = time() + $ttl;
		$data['audience']   = untrailingslashit( home_url() );
		set_transient( $prefix . hash( 'sha256', $token ), $data, $ttl );
		return $token;
	}

	private static function load( string $prefix, string $token ): ?array {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $token ) ) {
			return null;
		}
		$data = get_transient( $prefix . hash( 'sha256', $token ) );
		return is_array( $data ) && (int) $data['expires_at'] > time() && ( $data['audience'] ?? '' ) === untrailingslashit( home_url() ) ? $data : null;
	}

	private static function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	private static function response( array $data ): \WP_REST_Response {
		return new \WP_REST_Response(
			$data,
			200,
			[
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			]
			);
	}

	private static function error( string $code, int $status ): \WP_Error {
		return new \WP_Error( $code, 'Deze proefaanvraag is ongeldig of verlopen.', [ 'status' => $status ] );
	}
}

new Plugin();
