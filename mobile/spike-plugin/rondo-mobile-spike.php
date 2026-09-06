<?php
/**
 * Plugin Name: Rondo Mobile Spike (development only)
 * Description: Opt-in native member login experiment. Never loaded by the theme.
 * Version: 0.7.0
 *
 * @package Rondo\MobileSpike
 */

namespace Rondo\MobileSpike;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Narrow development adapter; no change to the confidential FreeScout OIDC provider. */
final class Plugin {
	public const CLIENT        = 'rondo-mobile-spike';
	public const CALLBACK      = 'club.rondo.spike://oauth/callback';
	public const SCOPE         = 'rondo:spike:read';
	public const MEMBER_SCOPE  = 'rondo:spike:read rondo:spike:volunteer';
	public const PROFILE_SCOPE = 'rondo:spike:read rondo:spike:volunteer rondo:spike:profile';
	public const NS            = 'rondo-mobile-spike/v1';
	private const CODE         = 'rondo_mobile_code_';
	private const SESSION      = 'rondo_mobile_session_';
	private const FAMILY       = 'rondo_mobile_family_';
	private const REFRESH      = 'rondo_mobile_refresh_';
	private const DEVICE_TTL   = 30 * DAY_IN_SECONDS;

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
		add_filter( 'login_redirect', [ $this, 'login_redirect' ], 20, 3 );
		add_filter( 'magic_login_create_login_link', [ $this, 'magic_login_link' ], 20, 3 );
		add_filter( 'magic_login_redirect', [ $this, 'magic_login_redirect' ], PHP_INT_MAX, 2 );
	}

	/** Preserve only this validated local authorization request after the WordPress login POST. */
	public function login_redirect( $redirect, $requested, $user ) {
		if ( ! self::enabled() || ! $user instanceof \WP_User || ! is_string( $requested ) ) {
			return $redirect;
		}
		return self::authorization_destination( $requested ) ?: $redirect;
	}

	/** Only the exact, validated local mobile authorization action is a return destination. */
	private static function authorization_destination( $requested ): string {
		if ( ! is_string( $requested ) ) {
			return '';
		}
		$parts = wp_parse_url( $requested );
		$base  = wp_parse_url( admin_url( 'admin-post.php' ) );
		foreach ( [ 'scheme', 'host', 'port', 'path', 'user', 'pass', 'fragment' ] as $key ) {
			if ( ( $parts[ $key ] ?? null ) !== ( $base[ $key ] ?? null ) ) {
				return '';
			}
		}
		parse_str( $parts['query'] ?? '', $params );
		if ( ( $params['action'] ?? '' ) !== 'rondo_mobile_spike_authorize' || is_wp_error( self::validate( $params ) ) ) {
			return '';
		}
		return $requested;
	}

	/** Preserve the app destination when Rondo's unified email flow creates its Magic Login link. */
	public function magic_login_link( $url, $user, $context ) {
		if ( ! self::enabled() || $context !== 'email' || ! $user instanceof \WP_User || ! is_string( $url ) ) {
			return $url;
		}
		// The provider already validates its request nonce, CAPTCHA and throttling before link creation.
		$requested = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$return    = self::authorization_destination( $requested );
		if ( $return === '' ) {
			return $url;
		}
		$parts = wp_parse_url( $url );
		$base  = wp_parse_url( wp_login_url() );
		foreach ( [ 'scheme', 'host', 'port', 'path', 'user', 'pass', 'fragment' ] as $key ) {
			if ( ( $parts[ $key ] ?? null ) !== ( $base[ $key ] ?? null ) ) {
				return $url;
			}
		}
		// Preserve the provider-issued token and its encoding convention, changing only the destination.
		return add_query_arg( 'redirect_to', rawurlencode( $return ), $url );
	}

	/** Apply the same narrow return validation after the provider's own redirect rules. */
	public function magic_login_redirect( $redirect, $user ) {
		$requested = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $this->login_redirect( $redirect, $requested, $user );
	}

	public function routes(): void {
		foreach ( [
			'config'  => 'GET',
			'token'   => 'POST',
			'read'    => 'GET',
			'shift'   => 'POST',
			'profile' => 'POST',
			'wallet'  => 'POST',
			'revoke'  => 'POST',
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
				'timezone' => wp_timezone_string(),
				'logo_url' => wp_get_attachment_image_url( ( new \Rondo\Config\FinanceConfig() )->get_club_logo_id(), 'thumbnail' ) ?: '',
			]
			);
	}

	/** Validate every redirect and PKCE field before any login or consent redirect. */
	public static function validate( array $params ) {
		foreach ( [
			'client_id'             => self::CLIENT,
			'redirect_uri'          => self::CALLBACK,
			'response_type'         => 'code',
			'code_challenge_method' => 'S256',
		] as $key => $value ) {
			if ( ( $params[ $key ] ?? null ) !== $value ) {
				return self::error( 'invalid_request', 400 );
			}
		}
		if ( ! in_array( $params['scope'] ?? '', [ self::SCOPE, self::MEMBER_SCOPE, self::PROFILE_SCOPE ], true ) ) {
			return self::error( 'invalid_request', 400 );
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
		echo '<!doctype html><html lang="nl"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rondo Proef verbinden</title><body><main><h1>Rondo Proef verbinden</h1><p>Je geeft de proefapp toegang om je eigen gegevens bij deze club te lezen. Je blijft op dit apparaat maximaal 30 dagen ingelogd, totdat je uitlogt of de club je toegang intrekt.</p><form method="post">';
		if ( in_array( $params['scope'], [ self::MEMBER_SCOPE, self::PROFILE_SCOPE ], true ) ) {
			echo '<p>Je geeft ook toestemming om jezelf via de app aan te melden en af te melden voor vrijwilligersdiensten, volgens de regels van je club.</p>';
		}
		if ( $params['scope'] === self::PROFILE_SCOPE ) {
			echo '<p>Je geeft toestemming om je eigen telefoonnummers, e-mailadressen en het woonadres van je gezin te wijzigen. Een nieuw e-mailadres wordt pas actief nadat je de verificatielink hebt geopend.</p>';
		}
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
				'scope'     => $params['scope'],
			],
			120
			);
	}

	public function token( \WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: [];
		if ( ( $params['grant_type'] ?? '' ) === 'refresh_token' ) {
			return $this->refresh( $params );
		}
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
		$family = bin2hex( random_bytes( 32 ) );
		$data   = [
			'user_id'    => $data['user_id'],
			'password'   => $data['password'],
			'scope'      => $data['scope'] ?? self::SCOPE,
			'expires_at' => time() + self::DEVICE_TTL,
			'audience'   => untrailingslashit( home_url() ),
		];
		if ( ! add_option( self::FAMILY . $family, $data, '', false ) ) {
			return self::error( 'session_unavailable', 503 );
		}
		wp_schedule_single_event( $data['expires_at'], 'rondo_mobile_spike_cleanup', [ self::FAMILY . $family ] );
		return self::pair( $family, $data );
	}

	private static function pair( string $family, array $data ) {
		$refresh = self::base64url( random_bytes( 32 ) );
		$key     = self::REFRESH . hash( 'sha256', $refresh );
		if ( ! add_option(
			$key,
			[
				'family'     => $family,
				'expires_at' => $data['expires_at'],
				'audience'   => $data['audience'],
			],
			'',
			false
			) ) {
			delete_option( self::FAMILY . $family );
			return self::error( 'session_unavailable', 503 );
		}
		wp_schedule_single_event( $data['expires_at'], 'rondo_mobile_spike_cleanup', [ $key ] );
		$ttl   = min( 300, $data['expires_at'] - time() );
		$token = self::store(
			self::SESSION,
			[
				'family'   => $family,
				'user_id'  => $data['user_id'],
				'password' => $data['password'],
			],
			$ttl
			);
		return self::response(
			[
				'access_token'       => $token,
				'token_type'         => 'Bearer',
				'expires_in'         => $ttl,
				'refresh_token'      => $refresh,
				'refresh_expires_at' => $data['expires_at'],
				'scope'              => $data['scope'] ?? self::SCOPE,
			]
			);
	}

	private static function refresh_record( $token ): ?array {
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $token ) ) {
			return null;
		}
		$data = get_option( self::REFRESH . hash( 'sha256', $token ) );
		return is_array( $data ) && $data['expires_at'] > time() && $data['audience'] === untrailingslashit( home_url() ) ? $data : null;
	}

	private function refresh( array $params ) {
		if ( ( $params['client_id'] ?? '' ) !== self::CLIENT ) {
			return self::error( 'invalid_grant', 400 );
		}
		$record = self::refresh_record( $params['refresh_token'] ?? null );
		$data   = $record ? get_option( self::FAMILY . $record['family'] ) : false;
		if ( ! is_array( $data ) || $data['expires_at'] <= time() || $data['audience'] !== untrailingslashit( home_url() ) || ! self::user( $data ) ) {
			return self::error( 'invalid_grant', 400 );
		}
		$claim = 'rondo_mobile_rotated_' . hash( 'sha256', $params['refresh_token'] );
		if ( ! add_option( $claim, true, '', false ) ) {
			// Keep consumed-token hashes until the absolute expiry so reuse revokes the whole device session.
			delete_option( self::FAMILY . $record['family'] );
			return self::error( 'invalid_grant', 400 );
		}
		wp_schedule_single_event( $record['expires_at'], 'rondo_mobile_spike_cleanup', [ $claim ] );
		return self::pair( $record['family'], $data );
	}

	/** Keep native QR and Wallet access limited to the same personal household. */
	private static function pass_access( \WP_REST_Request $request ) {
		$id   = $request->get_param( 'person_id' );
		$role = $request->get_param( 'role' ) ?? '';
		if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id <= 0 || ! is_string( $role ) || strlen( $role ) > 200 ) {
			return self::error( 'invalid_pass', 400 );
		}
		// Even administrators can only open passes offered in their personal household.
		$household = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/people/household' ) );
		$allowed   = false;
		if ( $household->get_status() === 200 ) {
			foreach ( $household->get_data() as $person ) {
				if ( (int) $person['id'] === (int) $id && ! empty( $person['membership_pass'] ) ) {
					$allowed = true;
					break;
				}
			}
		}
		if ( ! $allowed ) {
			return self::error( 'pass_forbidden', 403 );
		}
		return true;
	}

	/** Export one existing eligible pass; provider credentials remain on the club server. */
	public function wallet( \WP_REST_Request $request ) {
		$data = self::load( self::SESSION, self::bearer( $request ) );
		$user = $data ? self::user( $data ) : null;
		if ( ! $user ) {
			return self::error( 'invalid_token', 401 );
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || array_diff( array_keys( $params ), [ 'person_id', 'role', 'provider' ] ) || ! in_array( $params['provider'] ?? '', [ 'apple', 'google' ], true ) ) {
			return self::error( 'invalid_wallet', 400 );
		}
		$previous = get_current_user_id();
		try {
			wp_set_current_user( $user->ID );
			$input = new \WP_REST_Request( 'POST' );
			$input->set_body_params( $params );
			$access = self::pass_access( $input );
			$id     = (int) ( $params['person_id'] ?? 0 );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
			if ( ! in_array( $id, \Rondo\Core\AccessControl::get_visible_person_ids(), true ) ) {
				return self::error( 'pass_forbidden', 403 );
			}
			$selection = \Rondo\Passes\MembershipPassService::resolve_person_pass_selection( $id, $params['role'] ?? '' );
			if ( $selection === null ) {
				return self::error( 'membership_pass_choice_required', 400 );
			}
			$options  = [
				'work'        => $selection['work'],
				'member_tier' => $selection['member_tier'],
			];
			$provider = $params['provider'];
			$service  = $provider === 'apple' ? new \Rondo\Passes\MembershipPassApple() : new \Rondo\Passes\MembershipPassGoogle();
			if ( ! $service->is_configured() ) {
				return self::error( 'wallet_unavailable', 409 );
			}
			$result = $provider === 'apple' ? $service->generate_for_person( $id, $options ) : $service->get_add_to_wallet_url_for_person( $id, $options );
			if ( is_wp_error( $result ) ) {
				// Provider exceptions may contain credential paths or API diagnostics.
				return self::error( 'wallet_failed', 502 );
			}
			if ( $provider === 'google' ) {
				if ( ! is_string( $result ) || ! preg_match( '~^https://pay\.google\.com/gp/v/save/[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$~D', $result ) || strlen( $result ) > 65536 ) {
					return self::error( 'wallet_failed', 502 );
				}
				return self::response(
					[
						'provider' => 'google',
						'url'      => $result,
					]
					);
			}
			if ( ! is_array( $result ) || ! is_string( $result['content'] ?? null ) || strlen( $result['content'] ) > 4 * 1024 * 1024 ) {
				return self::error( 'wallet_failed', 502 );
			}
			return self::response(
				[
					'provider' => 'apple',
					'content'  => base64_encode( $result['content'] ),
				]
				);
		} catch ( \Throwable $error ) {
			return self::error( 'wallet_failed', 502 );
		} finally {
			wp_set_current_user( $previous );
		}
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
			'profile'   => '/rondo/v1/people/household',
			'my-shifts' => '/rondo/v1/my-shifts',
			'calendar'  => '/rondo/v1/shifts/calendar',
			'pass'      => '/rondo/v1/membership-passes/people/%d/qr-token',
		];
		$key    = $request->get_param( 'resource' );
		if ( ! is_string( $key ) || ! isset( $routes[ $key ] ) ) {
			return self::error( 'invalid_resource', 400 );
		}
		$previous = get_current_user_id();
		try {
			wp_set_current_user( $user->ID );
			$inner = new \WP_REST_Request( 'GET', $routes[ $key ] );
			if ( $key === 'calendar' ) {
				// Never forward caller-selected views or arbitrary query parameters.
				$month = $request->get_param( 'month' );
				if ( ! is_string( $month ) || ! preg_match( '/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $month ) ) {
					return self::error( 'invalid_month', 400 );
				}
				$start = new \DateTimeImmutable( $month . '-01', wp_timezone() );
				$inner->set_param( 'from', $start->format( 'Y-m-d' ) );
				$inner->set_param( 'to', $start->format( 'Y-m-t' ) );
				$inner->set_param( 'view', 'signup' );
			}
			if ( $key === 'pass' ) {
				$access = self::pass_access( $request );
				if ( is_wp_error( $access ) ) {
					return $access;
				}
				$id    = $request->get_param( 'person_id' );
				$role  = $request->get_param( 'role' ) ?? '';
				$inner = new \WP_REST_Request( 'GET', sprintf( $routes['pass'], (int) $id ) );
				$inner->set_param( 'role', $role );
			}
			$response = rest_do_request( $inner );
			if ( $key === 'profile' && $response->get_status() === 200 ) {
				$person_id = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
				$person    = null;
				foreach ( $response->get_data() as $item ) {
					if ( (int) $item['id'] === $person_id && $item['household_role'] === 'self' ) {
						$person = $item;
						break;
					}
				}
				$editable = \Rondo\Users\MemberProfileService::linked_person_id( $user->ID );
				return self::response(
					[
						'person'          => $person,
						'can_edit'        => $person !== null && ! is_wp_error( $editable ),
						'readonly_reason' => is_wp_error( $editable ) ? $editable->get_error_message() : '',
						'pending_email'   => $person ? \Rondo\Users\MemberProfileService::pending_email_change( $user->ID, $person_id ) : null,
					]
				);
			}
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		} finally {
			wp_set_current_user( $previous );
		}
	}

	/** Only the consented member's own signup/cancel routes; never administrative assignment. */
	public function shift( \WP_REST_Request $request ) {
		$data = self::load( self::SESSION, self::bearer( $request ) );
		$user = $data ? self::user( $data ) : null;
		if ( ! $user ) {
			return self::error( 'invalid_token', 401 );
		}
		$family = ! empty( $data['family'] ) ? get_option( self::FAMILY . $data['family'] ) : null;
		if ( ! is_array( $family ) || ! in_array( $family['scope'] ?? self::SCOPE, [ self::MEMBER_SCOPE, self::PROFILE_SCOPE ], true ) ) {
			return self::error( 'consent_required', 403 );
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || array_diff( array_keys( $params ), [ 'shift_id', 'action', 'force_overlap' ] ) ) {
			return self::error( 'invalid_shift_request', 400 );
		}
		$id     = $params['shift_id'] ?? null;
		$action = $params['action'] ?? '';
		$force  = $params['force_overlap'] ?? false;
		if ( ! is_scalar( $id ) || is_bool( $id ) || ! ctype_digit( (string) $id ) || (int) $id <= 0 || ! in_array( $action, [ 'signup', 'cancel' ], true ) || ! is_bool( $force ) || ( $action === 'cancel' && $force ) ) {
			return self::error( 'invalid_shift_request', 400 );
		}
		$previous = get_current_user_id();
		try {
			wp_set_current_user( $user->ID );
			$inner = new \WP_REST_Request( 'POST', sprintf( '/rondo/v1/shifts/%d/%s', (int) $id, $action ) );
			$inner->set_param( 'force_overlap', $force );
			$response = rest_do_request( $inner );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		} finally {
			wp_set_current_user( $previous );
		}
	}

	/** Fixed self-service operations only. Existing profile services own validation and household effects. */
	public function profile( \WP_REST_Request $request ) {
		$data = self::load( self::SESSION, self::bearer( $request ) );
		$user = $data ? self::user( $data ) : null;
		if ( ! $user ) {
			return self::error( 'invalid_token', 401 );
		}
		$family = ! empty( $data['family'] ) ? get_option( self::FAMILY . $data['family'] ) : null;
		if ( ! is_array( $family ) || ( $family['scope'] ?? self::SCOPE ) !== self::PROFILE_SCOPE ) {
			return self::error( 'consent_required', 403 );
		}
		$params = $request->get_json_params();
		$routes = [
			'phones'        => [ 'POST', '/user/profile-phones', [ 'mobile_1', 'mobile_2', 'telephone_1', 'telephone_2' ] ],
			'address'       => [ 'POST', '/user/household-address', [ 'street_name', 'house_number', 'house_number_addition', 'postal_code', 'city', 'state', 'country', 'country_code' ] ],
			'email_request' => [ 'POST', '/user/profile-email/request', [ 'slot', 'email' ] ],
			'email_cancel'  => [ 'DELETE', '/user/profile-email/pending', [] ],
			'email_remove'  => [ 'DELETE', '/user/profile-email/secondary', [] ],
		];
		if ( ! is_array( $params ) || array_diff( array_keys( $params ), [ 'action', 'values' ] ) || ! is_string( $params['action'] ?? null ) || ! isset( $routes[ $params['action'] ] ) || ! is_array( $params['values'] ?? null ) ) {
			return self::error( 'invalid_profile_request', 400 );
		}
		[ $method, $path, $fields ] = $routes[ $params['action'] ];
		$values                     = $params['values'];
		// Require complete field groups: a missing phone slot must never accidentally clear it.
		if ( array_diff( array_keys( $values ), $fields ) || array_diff( $fields, array_keys( $values ) ) ) {
			return self::error( 'invalid_profile_request', 400 );
		}
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 254 ) {
				return self::error( 'invalid_profile_request', 400 );
			}
		}
		$previous = get_current_user_id();
		try {
			wp_set_current_user( $user->ID );
			$person_id = \Rondo\Users\MemberProfileService::linked_person_id( $user->ID );
			if ( is_wp_error( $person_id ) ) {
				$response = rest_convert_error_to_response( $person_id );
			} else {
				// Ignore all caller-selected targets. Even pending email operations use the linked person.
				if ( str_starts_with( $params['action'], 'email_' ) ) {
					$values['person_id'] = $person_id;
				}
				$inner = new \WP_REST_Request( $method, '/rondo/v1' . $path );
				$inner->set_header( 'Content-Type', 'application/json' );
				$inner->set_body( wp_json_encode( $values ) );
				$response = rest_do_request( $inner );
			}
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		} finally {
			wp_set_current_user( $previous );
		}
	}

	public function revoke( \WP_REST_Request $request ): \WP_REST_Response {
		$token   = self::bearer( $request );
		$data    = self::load( self::SESSION, $token );
		$refresh = self::refresh_record( ( $request->get_json_params() ?: [] )['refresh_token'] ?? null );
		foreach ( [ $data, $refresh ] as $record ) {
			if ( ! empty( $record['family'] ) ) {
				delete_option( self::FAMILY . $record['family'] );
			}
		}
		if ( $token !== '' ) {
			delete_transient( self::SESSION . hash( 'sha256', $token ) );
		}
		return self::response( [ 'revoked' => true ] );
	}

	private static function bearer( \WP_REST_Request $request ): string {
		return preg_match( '/^Bearer ([A-Za-z0-9_-]{43})$/', $request->get_header( 'authorization' ), $match ) ? $match[1] : '';
	}

	private static function user( array $data ): ?\WP_User {
		if ( isset( $data['family'] ) ) {
			$family = get_option( self::FAMILY . $data['family'] );
			if ( ! is_array( $family ) || $family['expires_at'] <= time() || $family['audience'] !== untrailingslashit( home_url() ) ) {
				return null;
			}
		}
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
