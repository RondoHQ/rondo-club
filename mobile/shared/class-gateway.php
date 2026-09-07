<?php
/** Shared, isolated native authorization and personal-member adapter.
 * @package Rondo\Mobile
 */
namespace Rondo\Mobile;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/** Narrow development adapter; no change to the confidential FreeScout OIDC provider. */
abstract class Gateway {
	public const CLIENT        = 'rondo-mobile-spike';
	public const CALLBACK      = 'club.rondo.spike://oauth/callback';
	public const SCOPE         = 'rondo:spike:read';
	public const MEMBER_SCOPE  = 'rondo:spike:read rondo:spike:volunteer';
	public const PROFILE_SCOPE = 'rondo:spike:read rondo:spike:volunteer rondo:spike:profile';
	public const NS            = 'rondo-mobile-spike/v1';
	public const ACTION        = 'rondo_mobile_spike_authorize';
	public const CLEANUP       = 'rondo_mobile_spike_cleanup';
	public const PROTOCOL      = 'rondo-mobile-spike-v1';
	protected const USED       = 'rondo_mobile_used_';
	protected const ROTATED    = 'rondo_mobile_rotated_';
	protected const CODE       = 'rondo_mobile_code_';
	protected const SESSION    = 'rondo_mobile_session_';
	protected const FAMILY     = 'rondo_mobile_family_';
	protected const REFRESH    = 'rondo_mobile_refresh_';
	protected const DEVICE_TTL = 30 * DAY_IN_SECONDS;

	abstract public static function enabled(): bool;

	protected static function scopes(): array {
		return [ static::SCOPE, static::MEMBER_SCOPE, static::PROFILE_SCOPE ]; }
	protected static function user_allowed( \WP_User $user ): bool {
		return user_can( $user, 'read' ); }
	protected static function session_policy(): string {
		return ''; }
	protected static function writable(): bool {
		return true; }

	public function __construct() {
		if ( ! static::enabled() ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'routes' ] );
		add_action( 'admin_post_' . static::ACTION, [ $this, 'authorize' ] );
		add_action( 'admin_post_nopriv_' . static::ACTION, [ $this, 'authorize' ] );
		add_action( static::CLEANUP, 'delete_option' );
		add_filter( 'login_redirect', [ $this, 'login_redirect' ], 20, 3 );
		add_filter( 'magic_login_create_login_link', [ $this, 'magic_login_link' ], 20, 3 );
		add_filter( 'magic_login_redirect', [ $this, 'magic_login_redirect' ], PHP_INT_MAX, 2 );
	}

	/** Preserve only this validated local authorization request after the WordPress login POST. */
	public function login_redirect( $redirect, $requested, $user ) {
		if ( ! static::enabled() || ! $user instanceof \WP_User || ! is_string( $requested ) ) {
			return $redirect;
		}
		return static::authorization_destination( $requested ) ?: $redirect;
	}

	/** Only the exact, validated local mobile authorization action is a return destination. */
	protected static function authorization_destination( $requested ): string {
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
		if ( ( $params['action'] ?? '' ) !== static::ACTION || is_wp_error( static::validate( $params ) ) ) {
			return '';
		}
		return $requested;
	}

	/** Preserve the app destination when Rondo's unified email flow creates its Magic Login link. */
	public function magic_login_link( $url, $user, $context ) {
		if ( ! static::enabled() || $context !== 'email' || ! $user instanceof \WP_User || ! is_string( $url ) ) {
			return $url;
		}
		// The provider already validates its request nonce, CAPTCHA and throttling before link creation.
		$requested = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$return    = static::authorization_destination( $requested );
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

	public function route_permission( \WP_REST_Request $request ) {
		return static::enabled(); }

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
				static::NS,
				'/' . $route,
				[
					'methods'             => $method,
					'callback'            => [ $this, $route ],
					'permission_callback' => [ $this, 'route_permission' ],
				]
			);
		}
	}

	public function config(): \WP_REST_Response {
		return static::response(
			[
				'protocol' => static::PROTOCOL,
				'club_url' => untrailingslashit( home_url() ),
				'timezone' => wp_timezone_string(),
				'logo_url' => wp_get_attachment_image_url( ( new \Rondo\Config\FinanceConfig() )->get_club_logo_id(), 'thumbnail' ) ?: '',
			]
			);
	}

	/** Validate every redirect and PKCE field before any login or consent redirect. */
	public static function validate( array $params ) {
		foreach ( [
			'client_id'             => static::CLIENT,
			'redirect_uri'          => static::CALLBACK,
			'response_type'         => 'code',
			'code_challenge_method' => 'S256',
		] as $key => $value ) {
			if ( ( $params[ $key ] ?? null ) !== $value ) {
				return static::error( 'invalid_request', 400 );
			}
		}
		if ( ! in_array( $params['scope'] ?? '', static::scopes(), true ) ) {
			return static::error( 'invalid_request', 400 );
		}
		foreach ( [ 'state', 'code_challenge' ] as $key ) {
			if ( ! is_string( $params[ $key ] ?? null ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $params[ $key ] ) ) {
				return static::error( 'invalid_request', 400 );
			}
		}
		return true;
	}

	public function authorize(): void {
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Frame-Options: DENY' );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( ! static::enabled() || ! in_array( $method, [ 'GET', 'POST' ], true ) ) {
			wp_die( 'Deze proef is niet beschikbaar.', '', [ 'response' => 403 ] );
		}
		// The public GET carries no mutation. The POST below requires the authenticated user's nonce.
		$params = $method === 'POST' ? wp_unslash( $_POST ) : wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
		$valid  = static::validate( $params );
		if ( is_wp_error( $valid ) ) {
			wp_die( 'Ongeldige app-aanvraag.', '', [ 'response' => 400 ] );
		}
		if ( ! is_user_logged_in() ) {
			$return = add_query_arg( array_intersect_key( $params, array_flip( [ 'action', 'client_id', 'redirect_uri', 'scope', 'response_type', 'code_challenge_method', 'state', 'code_challenge' ] ) ), admin_url( 'admin-post.php' ) );
			wp_safe_redirect( wp_login_url( $return ) );
			exit;
		}
		if ( ! static::user_allowed( wp_get_current_user() ) ) {
			wp_die( 'Dit account is niet toegelaten tot deze app-test.', '', [ 'response' => 403 ] );
		}
		if ( $method === 'POST' ) {
			if ( ! is_string( $params['_wpnonce'] ?? null ) || ! wp_verify_nonce( $params['_wpnonce'], static::ACTION ) ) {
				wp_die( 'De toestemming is verlopen.', '', [ 'response' => 403 ] );
			}
			$query = [
				'state' => $params['state'],
				'error' => 'access_denied',
			];
			if ( ( $params['decision'] ?? '' ) === 'approve' ) {
				$code = static::issue( $params, get_current_user_id() );
				if ( is_wp_error( $code ) ) {
					wp_die( 'Dit account is niet beschikbaar.', '', [ 'response' => 403 ] );
				}
				$query = [
					'state' => $params['state'],
					'code'  => $code,
				];
			}
			wp_redirect( static::CALLBACK . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed private-use callback, no input-controlled destination.
			exit;
		}
		echo '<!doctype html><html lang="nl"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rondo Proef verbinden</title><body><main><h1>Rondo Proef verbinden</h1><p>Je geeft de proefapp toegang om je eigen gegevens en die van je gezin bij deze club te lezen en je beschikbare passen aan Wallet toe te voegen. Je blijft op dit apparaat maximaal ' . (int) ( static::DEVICE_TTL / DAY_IN_SECONDS ) . ' dagen ingelogd, totdat je uitlogt of de club je toegang intrekt.</p><form method="post">';
		if ( in_array( $params['scope'], [ static::MEMBER_SCOPE, static::PROFILE_SCOPE ], true ) ) {
			echo '<p>Je geeft ook toestemming om jezelf via de app aan te melden en af te melden voor vrijwilligersdiensten, volgens de regels van je club.</p>';
		}
		if ( $params['scope'] === static::PROFILE_SCOPE ) {
			echo '<p>Je geeft toestemming om je eigen telefoonnummers, e-mailadressen en het woonadres van je gezin te wijzigen. Een nieuw e-mailadres wordt pas actief nadat je de verificatielink hebt geopend.</p>';
		}
		wp_nonce_field( static::ACTION );
		foreach ( [ 'action', 'client_id', 'redirect_uri', 'scope', 'response_type', 'code_challenge_method', 'state', 'code_challenge' ] as $key ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $params[ $key ] ) . '">';
		}
		echo '<button name="decision" value="approve">Verbinden</button> <button name="decision" value="deny">Annuleren</button></form></main></body></html>';
		exit;
	}

	public static function issue( array $params, int $user_id ) {
		$valid = static::validate( $params );
		$user  = get_userdata( $user_id );
		if ( ! static::enabled() || is_wp_error( $valid ) || ! $user instanceof \WP_User || ! static::user_allowed( $user ) ) {
			return static::error( 'access_denied', 403 );
		}
		return static::store(
			static::CODE,
			[
				'user_id'   => $user_id,
				'password'  => wp_hash( $user->user_pass ),
				'challenge' => $params['code_challenge'],
				'scope'     => $params['scope'],
				'policy'    => static::session_policy(),
			],
			120
			);
	}

	public function token( \WP_REST_Request $request ) {
		$params = $request->get_json_params() ?: [];
		if ( ( $params['grant_type'] ?? '' ) === 'refresh_token' ) {
			return $this->refresh( $params );
		}
		if ( ( $params['grant_type'] ?? null ) !== 'authorization_code' || ( $params['client_id'] ?? null ) !== static::CLIENT || ( $params['redirect_uri'] ?? null ) !== static::CALLBACK || ! is_string( $params['code_verifier'] ?? null ) || ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $params['code_verifier'] ) ) {
			return static::error( 'invalid_grant', 400 );
		}
		$code = is_string( $params['code'] ?? null ) ? $params['code'] : '';
		$data = static::load( static::CODE, $code );
		if ( ! $data || ! hash_equals( $data['challenge'], static::base64url( hash( 'sha256', $params['code_verifier'], true ) ) ) || ! static::user( $data ) ) {
			return static::error( 'invalid_grant', 400 );
		}
		// add_option is an atomic unique claim; a transient get/delete alone is replayable under concurrency.
		$lock = static::USED . hash( 'sha256', $code );
		if ( ! add_option( $lock, time(), '', false ) ) {
			return static::error( 'invalid_grant', 400 );
		}
		wp_schedule_single_event( time() + 600, static::CLEANUP, [ $lock ] );
		delete_transient( static::CODE . hash( 'sha256', $code ) );
		$family = bin2hex( random_bytes( 32 ) );
		$data   = [
			'user_id'    => $data['user_id'],
			'password'   => $data['password'],
			'scope'      => $data['scope'] ?? static::SCOPE,
			'policy'     => static::session_policy(),
			'expires_at' => time() + static::DEVICE_TTL,
			'audience'   => untrailingslashit( home_url() ),
		];
		if ( ! add_option( static::FAMILY . $family, $data, '', false ) ) {
			return static::error( 'session_unavailable', 503 );
		}
		wp_schedule_single_event( $data['expires_at'], static::CLEANUP, [ static::FAMILY . $family ] );
		return static::pair( $family, $data );
	}

	protected static function pair( string $family, array $data ) {
		$refresh = static::base64url( random_bytes( 32 ) );
		$key     = static::REFRESH . hash( 'sha256', $refresh );
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
			delete_option( static::FAMILY . $family );
			return static::error( 'session_unavailable', 503 );
		}
		wp_schedule_single_event( $data['expires_at'], static::CLEANUP, [ $key ] );
		$ttl   = min( 300, $data['expires_at'] - time() );
		$token = static::store(
			static::SESSION,
			[
				'family'   => $family,
				'user_id'  => $data['user_id'],
				'password' => $data['password'],
				'policy'   => static::session_policy(),
			],
			$ttl
			);
		return static::response(
			[
				'access_token'       => $token,
				'token_type'         => 'Bearer',
				'expires_in'         => $ttl,
				'refresh_token'      => $refresh,
				'refresh_expires_at' => $data['expires_at'],
				'scope'              => $data['scope'] ?? static::SCOPE,
			]
			);
	}

	protected static function refresh_record( $token ): ?array {
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $token ) ) {
			return null;
		}
		$data = get_option( static::REFRESH . hash( 'sha256', $token ) );
		return is_array( $data ) && $data['expires_at'] > time() && $data['audience'] === untrailingslashit( home_url() ) ? $data : null;
	}

	private function refresh( array $params ) {
		if ( ( $params['client_id'] ?? '' ) !== static::CLIENT ) {
			return static::error( 'invalid_grant', 400 );
		}
		$record = static::refresh_record( $params['refresh_token'] ?? null );
		$data   = $record ? get_option( static::FAMILY . $record['family'] ) : false;
		if ( ! is_array( $data ) || $data['expires_at'] <= time() || $data['audience'] !== untrailingslashit( home_url() ) || ! static::user( $data ) ) {
			return static::error( 'invalid_grant', 400 );
		}
		$claim = static::ROTATED . hash( 'sha256', $params['refresh_token'] );
		if ( ! add_option( $claim, true, '', false ) ) {
			// Keep consumed-token hashes until the absolute expiry so reuse revokes the whole device session.
			delete_option( static::FAMILY . $record['family'] );
			return static::error( 'invalid_grant', 400 );
		}
		wp_schedule_single_event( $record['expires_at'], static::CLEANUP, [ $claim ] );
		return static::pair( $record['family'], $data );
	}

	/** Keep native QR and Wallet access limited to the same personal household. */
	protected static function pass_access( \WP_REST_Request $request ) {
		$id   = $request->get_param( 'person_id' );
		$role = $request->get_param( 'role' ) ?? '';
		if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id <= 0 || ! is_string( $role ) || strlen( $role ) > 200 ) {
			return static::error( 'invalid_pass', 400 );
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
			return static::error( 'pass_forbidden', 403 );
		}
		return true;
	}

	/** Export one existing eligible pass; provider credentials remain on the club server. */
	public function wallet( \WP_REST_Request $request ) {
		$data = static::load( static::SESSION, static::bearer( $request ) );
		$user = $data ? static::user( $data ) : null;
		if ( ! $user ) {
			return static::error( 'invalid_token', 401 );
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || array_diff( array_keys( $params ), [ 'person_id', 'role', 'provider' ] ) || ! in_array( $params['provider'] ?? '', [ 'apple', 'google' ], true ) ) {
			return static::error( 'invalid_wallet', 400 );
		}
		$previous = get_current_user_id();
		try {
			wp_set_current_user( $user->ID );
			$input = new \WP_REST_Request( 'POST' );
			$input->set_body_params( $params );
			$access = static::pass_access( $input );
			$id     = (int) ( $params['person_id'] ?? 0 );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
			if ( ! in_array( $id, \Rondo\Core\AccessControl::get_visible_person_ids(), true ) ) {
				return static::error( 'pass_forbidden', 403 );
			}
			$selection = \Rondo\Passes\MembershipPassService::resolve_person_pass_selection( $id, $params['role'] ?? '' );
			if ( $selection === null ) {
				return static::error( 'membership_pass_choice_required', 400 );
			}
			$options  = [
				'work'        => $selection['work'],
				'member_tier' => $selection['member_tier'],
			];
			$provider = $params['provider'];
			$service  = $provider === 'apple' ? new \Rondo\Passes\MembershipPassApple() : new \Rondo\Passes\MembershipPassGoogle();
			if ( ! $service->is_configured() ) {
				return static::error( 'wallet_unavailable', 409 );
			}
			$result = $provider === 'apple' ? $service->generate_for_person( $id, $options ) : $service->get_add_to_wallet_url_for_person( $id, $options );
			if ( is_wp_error( $result ) ) {
				// Provider exceptions may contain credential paths or API diagnostics.
				return static::error( 'wallet_failed', 502 );
			}
			if ( $provider === 'google' ) {
				if ( ! is_string( $result ) || ! preg_match( '~^https://pay\.google\.com/gp/v/save/[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$~D', $result ) || strlen( $result ) > 65536 ) {
					return static::error( 'wallet_failed', 502 );
				}
				return static::response(
					[
						'provider' => 'google',
						'url'      => $result,
					]
					);
			}
			if ( ! is_array( $result ) || ! is_string( $result['content'] ?? null ) || strlen( $result['content'] ) > 4 * 1024 * 1024 ) {
				return static::error( 'wallet_failed', 502 );
			}
			return static::response(
				[
					'provider' => 'apple',
					'content'  => base64_encode( $result['content'] ),
				]
				);
		} catch ( \Throwable $error ) {
			return static::error( 'wallet_failed', 502 );
		} finally {
			wp_set_current_user( $previous );
		}
	}

	/** Dispatch only fixed reads through existing REST permission callbacks and field filters. */
	public function read( \WP_REST_Request $request ) {
		$data = static::load( static::SESSION, static::bearer( $request ) );
		$user = $data ? static::user( $data ) : null;
		if ( ! $user ) {
			return static::error( 'invalid_token', 401 );
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
			return static::error( 'invalid_resource', 400 );
		}
		$previous = get_current_user_id();
		try {
			wp_set_current_user( $user->ID );
			$inner = new \WP_REST_Request( 'GET', $routes[ $key ] );
			if ( $key === 'calendar' ) {
				// Never forward caller-selected views or arbitrary query parameters.
				$month = $request->get_param( 'month' );
				if ( ! is_string( $month ) || ! preg_match( '/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $month ) ) {
					return static::error( 'invalid_month', 400 );
				}
				$start = new \DateTimeImmutable( $month . '-01', wp_timezone() );
				$inner->set_param( 'from', $start->format( 'Y-m-d' ) );
				$inner->set_param( 'to', $start->format( 'Y-m-t' ) );
				$inner->set_param( 'view', 'signup' );
			}
			if ( $key === 'pass' ) {
				$access = static::pass_access( $request );
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
				return static::response(
					[
						'person'          => $person,
						'can_edit'        => static::writable() && $person !== null && ! is_wp_error( $editable ),
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
		if ( ! static::writable() ) {
			return static::error( 'read_only_pilot', 403 );
		}
		$data = static::load( static::SESSION, static::bearer( $request ) );
		$user = $data ? static::user( $data ) : null;
		if ( ! $user ) {
			return static::error( 'invalid_token', 401 );
		}
		$family = ! empty( $data['family'] ) ? get_option( static::FAMILY . $data['family'] ) : null;
		if ( ! is_array( $family ) || ! in_array( $family['scope'] ?? static::SCOPE, [ static::MEMBER_SCOPE, static::PROFILE_SCOPE ], true ) ) {
			return static::error( 'consent_required', 403 );
		}
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || array_diff( array_keys( $params ), [ 'shift_id', 'action', 'force_overlap' ] ) ) {
			return static::error( 'invalid_shift_request', 400 );
		}
		$id     = $params['shift_id'] ?? null;
		$action = $params['action'] ?? '';
		$force  = $params['force_overlap'] ?? false;
		if ( ! is_scalar( $id ) || is_bool( $id ) || ! ctype_digit( (string) $id ) || (int) $id <= 0 || ! in_array( $action, [ 'signup', 'cancel' ], true ) || ! is_bool( $force ) || ( $action === 'cancel' && $force ) ) {
			return static::error( 'invalid_shift_request', 400 );
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
		if ( ! static::writable() ) {
			return static::error( 'read_only_pilot', 403 );
		}
		$data = static::load( static::SESSION, static::bearer( $request ) );
		$user = $data ? static::user( $data ) : null;
		if ( ! $user ) {
			return static::error( 'invalid_token', 401 );
		}
		$family = ! empty( $data['family'] ) ? get_option( static::FAMILY . $data['family'] ) : null;
		if ( ! is_array( $family ) || ( $family['scope'] ?? static::SCOPE ) !== static::PROFILE_SCOPE ) {
			return static::error( 'consent_required', 403 );
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
			return static::error( 'invalid_profile_request', 400 );
		}
		[ $method, $path, $fields ] = $routes[ $params['action'] ];
		$values                     = $params['values'];
		// Require complete field groups: a missing phone slot must never accidentally clear it.
		if ( array_diff( array_keys( $values ), $fields ) || array_diff( $fields, array_keys( $values ) ) ) {
			return static::error( 'invalid_profile_request', 400 );
		}
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 254 ) {
				return static::error( 'invalid_profile_request', 400 );
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
		$token   = static::bearer( $request );
		$data    = static::load( static::SESSION, $token );
		$refresh = static::refresh_record( ( $request->get_json_params() ?: [] )['refresh_token'] ?? null );
		foreach ( [ $data, $refresh ] as $record ) {
			if ( ! empty( $record['family'] ) ) {
				delete_option( static::FAMILY . $record['family'] );
			}
		}
		if ( $token !== '' ) {
			delete_transient( static::SESSION . hash( 'sha256', $token ) );
		}
		return static::response( [ 'revoked' => true ] );
	}

	protected static function bearer( \WP_REST_Request $request ): string {
		return preg_match( '/^Bearer ([A-Za-z0-9_-]{43})$/', $request->get_header( 'authorization' ), $match ) ? $match[1] : '';
	}

	protected static function user( array $data ): ?\WP_User {
		if ( isset( $data['family'] ) ) {
			$family = get_option( static::FAMILY . $data['family'] );
			if ( ! is_array( $family ) || $family['expires_at'] <= time() || $family['audience'] !== untrailingslashit( home_url() ) ) {
				return null;
			}
		}
		$user = get_userdata( (int) $data['user_id'] );
		return $user instanceof \WP_User && static::enabled() && static::user_allowed( $user ) && ( $data['policy'] ?? '' ) === static::session_policy() && hash_equals( $data['password'], wp_hash( $user->user_pass ) ) ? $user : null;
	}

	protected static function store( string $prefix, array $data, int $ttl ): string {
		$token              = static::base64url( random_bytes( 32 ) );
		$data['expires_at'] = time() + $ttl;
		$data['audience']   = untrailingslashit( home_url() );
		set_transient( $prefix . hash( 'sha256', $token ), $data, $ttl );
		return $token;
	}

	protected static function load( string $prefix, string $token ): ?array {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{43}$/', $token ) ) {
			return null;
		}
		$data = get_transient( $prefix . hash( 'sha256', $token ) );
		return is_array( $data ) && (int) $data['expires_at'] > time() && ( $data['audience'] ?? '' ) === untrailingslashit( home_url() ) ? $data : null;
	}

	protected static function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	protected static function response( array $data ): \WP_REST_Response {
		return new \WP_REST_Response(
			$data,
			200,
			[
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			]
			);
	}

	protected static function error( string $code, int $status ): \WP_Error {
		return new \WP_Error( $code, 'Deze proefaanvraag is ongeldig of verlopen.', [ 'status' => $status ] );
	}
}
