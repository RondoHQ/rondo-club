<?php
/**
 * Same-origin, bounded browser diagnostics transport.
 *
 * @package Rondo
 */

namespace Rondo;

/** Forward only the configured React project's error and transaction envelopes. */
class MonitoringTunnel {

	/** Register the public ingestion route; it never reads club records. */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/** Register the route independently of login/nonce state, including early app failures. */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/diagnostics',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'forward' ],
				'permission_callback' => [ $this, 'check_origin' ],
			]
		);
	}

	/**
	 * Only same-origin browser POSTs; this is not a substitute for the volume limits.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function check_origin( $request ) {
		$site   = wp_parse_url( home_url() );
		$origin = $site['scheme'] . '://' . $site['host'] . ( isset( $site['port'] ) ? ':' . $site['port'] : '' );
		if ( $request->get_header( 'origin' ) !== $origin || $request->get_header( 'sec-fetch-site' ) === 'cross-site' ) {
			return new \WP_Error( 'rondo_diagnostics_origin', 'Invalid diagnostics origin.', [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Relay a validated envelope without cookies, authentication, or upstream response bodies.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function forward( $request ) {
		$dsn = defined( 'RONDO_GLITCHTIP_REACT_DSN' ) ? RONDO_GLITCHTIP_REACT_DSN : '';
		if ( ( defined( 'RONDO_GLITCHTIP_TUNNEL' ) && ! RONDO_GLITCHTIP_TUNNEL ) || ! preg_match( '#^https://([a-f0-9]{32})@errors\.rondo\.club/([0-9]+)$#', $dsn, $destination ) ) {
			return $this->reply( 503 );
		}
		$body = $request->get_body();
		if ( strlen( $body ) > 65536 ) {
			return $this->reply( 413 );
		}
		if ( ! $this->valid_envelope( $body, $dsn ) ) {
			return $this->reply( 400 );
		}
		if ( ! $this->within_budget() ) {
			return $this->reply( 429, [ 'Retry-After' => '60' ] );
		}
		$url      = 'https://errors.rondo.club/api/' . $destination[2] . '/envelope/?sentry_key=' . $destination[1] . '&sentry_version=7';
		$response = wp_remote_post(
			$url,
			[
				'body'                => $body,
				'headers'             => [ 'Content-Type' => 'application/x-sentry-envelope' ],
				'timeout'             => 3,
				'redirection'         => 0,
				'limit_response_size' => 1024,
				'cookies'             => [],
			]
		);
		if ( is_wp_error( $response ) ) {
			return $this->reply( 503 );
		}
		$status  = wp_remote_retrieve_response_code( $response );
		$headers = [];
		foreach ( [ 'Retry-After', 'X-Sentry-Rate-Limits' ] as $name ) {
			$value = wp_remote_retrieve_header( $response, strtolower( $name ) );
			if ( $value ) {
				$headers[ $name ] = $value;
			}
		}
		return $this->reply( $status >= 200 && $status < 300 ? 204 : ( $status === 429 ? 429 : 502 ), $headers );
	}

	/**
	 * Accept one JSON error/transaction only: no attachments, replays, or arbitrary projects.
	 *
	 * @param string $body Envelope bytes.
	 * @param string $dsn Configured project DSN.
	 * @return bool
	 */
	private function valid_envelope( string $body, string $dsn ): bool {
		$lines = explode( "\n", $body );
		if ( end( $lines ) === '' ) {
			array_pop( $lines );
		}
		if ( count( $lines ) !== 3 ) {
			return false;
		}
		$header = json_decode( $lines[0], true );
		$item   = json_decode( $lines[1], true );
		$event  = json_decode( $lines[2], true );
		if ( ! is_array( $header ) || ! is_array( $item ) || ! is_array( $event ) ) {
			return false;
		}
		return ( $header['dsn'] ?? '' ) === $dsn
			&& is_string( $header['event_id'] ?? null )
			&& preg_match( '/^[a-f0-9]{32}$/i', $header['event_id'] )
			&& ( $event['event_id'] ?? '' ) === $header['event_id']
			&& in_array( $item['type'] ?? '', [ 'event', 'transaction' ], true )
			&& ( $item['type'] === 'transaction' ? ( $event['type'] ?? '' ) === 'transaction' : in_array( $event['type'] ?? 'event', [ 'event' ], true ) )
			&& ( ! isset( $item['length'] ) || $item['length'] === strlen( $lines[2] ) );
	}

	/** Soft counters: WordPress transients are not atomic concurrent-request quotas. */
	private function within_budget(): bool {
		$minute = (int) floor( time() / 60 );
		// Never trust forwarded IP headers or persist raw IP addresses.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$hash = hash_hmac( 'sha256', $minute . ':' . $ip, wp_salt() );
		foreach ( [
			[ 'rondo_diag_min_' . $hash, 20, 120 ],
			[ 'rondo_diag_day_' . gmdate( 'Ymd' ), 2000, 2 * DAY_IN_SECONDS ],
		] as [ $key, $limit, $ttl ] ) {
			$count = get_transient( $key );
			if ( $count !== false && ( ! is_numeric( $count ) || $count < 0 || $count >= $limit ) ) {
				return false;
			}
			if ( ! set_transient( $key, (int) $count + 1, $ttl ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Empty, non-cacheable transport result.
	 *
	 * @param int   $status HTTP status.
	 * @param array $headers Transport backoff headers.
	 * @return \WP_REST_Response
	 */
	private function reply( int $status, array $headers = [] ): \WP_REST_Response {
		return new \WP_REST_Response( null, $status, array_merge( [ 'Cache-Control' => 'no-store' ], $headers ) );
	}
}
