<?php
/**
 * Plugin Name: Rondo AWC Mobile Pilot
 * Description: Explicitly enabled, allowlisted, read-only native AWC pilot. Never loaded by the theme.
 * Version: 0.8.0
 *
 * @package Rondo\MobilePilot
 */

namespace Rondo\MobilePilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once dirname( __DIR__ ) . '/shared/class-gateway.php';

final class Plugin extends \Rondo\Mobile\Gateway {
	public const CLIENT        = 'rondo-awc-pilot';
	public const CALLBACK      = 'https://rondo.svawc.nl/rondo-app/callback';
	public const SCOPE         = 'rondo:pilot:read';
	public const NS            = 'rondo-mobile-pilot/v1';
	public const ACTION        = 'rondo_mobile_pilot_authorize';
	public const CLEANUP       = 'rondo_mobile_pilot_cleanup';
	public const PROTOCOL      = 'rondo-mobile-pilot-v1';
	protected const CODE       = 'rondo_pilot_code_';
	protected const SESSION    = 'rondo_pilot_session_';
	protected const FAMILY     = 'rondo_pilot_family_';
	protected const REFRESH    = 'rondo_pilot_refresh_';
	protected const USED       = 'rondo_pilot_used_';
	protected const ROTATED    = 'rondo_pilot_rotated_';
	protected const DEVICE_TTL = 7 * DAY_IN_SECONDS;

	public function __construct() {
		parent::__construct();
		if ( ! self::enabled() ) {
			return;
		}
		add_filter( 'rest_post_dispatch', [ $this, 'no_store' ], 10, 3 );
		add_action( 'parse_request', [ $this, 'callback_fallback' ], 0 );
	}

	public function no_store( $response, $server, $request ) {
		if ( str_starts_with( $request->get_route(), '/' . self::NS . '/' ) ) {
			$response->header( 'Cache-Control', 'no-store' );
			$response->header( 'Pragma', 'no-cache' );
		}
		return $response;
	}

	/** Never echo authorization parameters, load analytics, or downgrade to an unverified scheme. */
	public function callback_fallback( $wp ): void {
		if ( $wp->request !== 'rondo-app/callback' ) {
			return;
		}
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( "Content-Security-Policy: default-src 'none'; frame-ancestors 'none'" );
		header( 'Content-Type: text/html; charset=UTF-8' );
		status_header( 200 );
		echo '<!doctype html><html lang="nl"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Open Rondo Pilot</title><h1>Open Rondo Pilot</h1><p>Je toestel heeft deze link nog niet aan de pilot-app gekoppeld. Open de app en start de aanmelding opnieuw. Blijft dit scherm verschijnen, meld dit aan de testbegeleider.</p></html>';
		exit;
	}

	public static function settings(): array {
		$value = get_option( 'rondo_mobile_pilot', [] );
		return is_array( $value ) ? $value : [];
	}

	public static function enabled(): bool {
		$config = self::settings();
		return defined( 'RONDO_MOBILE_PILOT' ) && RONDO_MOBILE_PILOT === true
			&& untrailingslashit( home_url() ) === 'https://rondo.svawc.nl'
			&& ( $config['enabled'] ?? false ) === true
			&& is_int( $config['ends_at'] ?? null ) && $config['ends_at'] > time()
			&& is_string( $config['epoch'] ?? null ) && strlen( $config['epoch'] ) >= 32
			&& is_array( $config['testers'] ?? null ) && count( $config['testers'] ) > 0 && count( $config['testers'] ) <= 20;
	}

	protected static function scopes(): array {
		return [ self::SCOPE ]; }
	protected static function writable(): bool {
		return false; }

	protected static function session_policy(): string {
		return hash( 'sha256', wp_json_encode( self::settings() ) );
	}

	protected static function user_allowed( \WP_User $user ): bool {
		$person_id = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
		$person    = $person_id ? get_post( $person_id ) : null;
		if ( ! self::enabled() || ! user_can( $user, 'read' ) || ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return false;
		}
		// Pin both identities: relinking an account never silently grants another household access.
		foreach ( self::settings()['testers'] as $tester ) {
			if ( is_array( $tester ) && ( $tester['user_id'] ?? null ) === $user->ID && ( $tester['person_id'] ?? null ) === $person_id ) {
				return true;
			}
		}
		return false;
	}

	/** Atomic, bounded per-IP token/Wallet rate limit; forwarded headers are never trusted. */
	public function route_permission( \WP_REST_Request $request ) {
		if ( ! self::enabled() ) {
			return false;
		}
		if ( ! in_array( $request->get_route(), [ '/' . self::NS . '/token', '/' . self::NS . '/wallet' ], true ) ) {
			return true;
		}
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$window = (int) floor( time() / MINUTE_IN_SECONDS );
		$prefix = 'rondo_pilot_rate_' . hash_hmac( 'sha256', $ip . ':' . $window, wp_salt( 'nonce' ) );
		for ( $slot = 0; $slot < 60; ++$slot ) {
			$key = $prefix . '_' . $slot;
			if ( add_option( $key, true, '', false ) ) {
				wp_schedule_single_event( ( $window + 2 ) * MINUTE_IN_SECONDS, self::CLEANUP, [ $key ] );
				return true;
			}
		}
		return self::error( 'rate_limited', 429 );
	}
}

new Plugin();
