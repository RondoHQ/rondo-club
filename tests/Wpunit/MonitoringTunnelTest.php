<?php

namespace Tests\Wpunit;

use Rondo\MonitoringTunnel;
use Tests\Support\RondoTestCase;

/** Transport boundary: fixed destination, bounded input, no credential forwarding. */
class MonitoringTunnelTest extends RondoTestCase {
	private $old_ip;
	private $http_filter;

	public function set_up(): void {
		parent::set_up();
		if ( ! defined( 'RONDO_GLITCHTIP_REACT_DSN' ) ) {
			define( 'RONDO_GLITCHTIP_REACT_DSN', 'https://' . str_repeat( 'a', 32 ) . '@errors.rondo.club/1' );
		}
		$this->old_ip           = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR'] = '192.0.2.123';
		$this->clear_budget();
		// Never send a test event over the network, even if an assertion fails.
		$this->http_filter = static function () {
			return new \WP_Error( 'test_network_disabled' );
		};
		add_filter( 'pre_http_request', $this->http_filter );
	}

	public function tear_down(): void {
		$this->clear_budget();
		remove_filter( 'pre_http_request', $this->http_filter );
		if ( $this->old_ip === null ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->old_ip;
		}
		parent::tear_down();
	}

	private function clear_budget(): void {
		delete_transient( 'rondo_diag_day_' . gmdate( 'Ymd' ) );
		$minute = (int) floor( time() / 60 );
		foreach ( [ $minute - 1, $minute, $minute + 1 ] as $value ) {
			delete_transient( 'rondo_diag_min_' . hash_hmac( 'sha256', $value . ':192.0.2.123', wp_salt() ) );
		}
	}

	private function envelope( string $type = 'transaction' ): string {
		$id    = str_repeat( 'b', 32 );
		$event = [ 'event_id' => $id ];
		if ( $type === 'transaction' ) {
			$event['type']        = $type;
			$event['transaction'] = '/people';
		}
		return wp_json_encode(
			[
				'dsn'      => RONDO_GLITCHTIP_REACT_DSN,
				'event_id' => $id,
			]
			) . "\n"
			. wp_json_encode( [ 'type' => $type ] ) . "\n" . wp_json_encode( $event );
	}

	private function request( ?string $body = null ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/diagnostics' );
		$request->set_header( 'Origin', home_url() );
		$request->set_header( 'Cookie', 'PRIVATE' );
		$request->set_header( 'Authorization', 'SECRET' );
		$request->set_body( $body ?? $this->envelope() );
		return $request;
	}

	public function test_route_accepts_errors_and_transactions_without_forwarding_browser_credentials(): void {
		$server = $this->bootRestControllers( [ MonitoringTunnel::class ] );
		$sent   = 0;
		$filter = function ( $result, $args, $url ) use ( &$sent ) {
			++$sent;
			$this->assertSame( 'errors.rondo.club', wp_parse_url( $url, PHP_URL_HOST ) );
			$this->assertSame( '/api/1/envelope/', wp_parse_url( $url, PHP_URL_PATH ) );
			$this->assertSame( 0, $args['redirection'] );
			$this->assertSame( 3, $args['timeout'] );
			$this->assertSame( 1024, $args['limit_response_size'] );
			$this->assertSame( [], $args['cookies'] );
			$this->assertSame( [ 'Content-Type' => 'application/x-sentry-envelope' ], $args['headers'] );
			$this->assertStringNotContainsString( 'PRIVATE', $args['body'] );
			return [
				'response' => [ 'code' => 200 ],
				'headers'  => [ 'set-cookie' => 'SECRET' ],
				'body'     => 'PRIVATE upstream',
			];
		};
		add_filter( 'pre_http_request', $filter, 20, 3 );
		try {
			foreach ( [ 'event', 'transaction' ] as $type ) {
				$response = $server->dispatch( $this->request( $this->envelope( $type ) ) );
				$this->assertSame( 204, $response->get_status() );
				$this->assertNull( $response->get_data() );
				$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
				$this->assertArrayNotHasKey( 'set-cookie', $response->get_headers() );
			}
			$this->assertSame( 2, $sent );
		} finally {
			remove_filter( 'pre_http_request', $filter, 20 );
		}
	}

	public function test_wrong_origin_project_type_and_size_are_rejected_before_forwarding(): void {
		$server = $this->bootRestControllers( [ MonitoringTunnel::class ] );
		foreach ( [ '', 'https://evil.invalid', home_url() . '.evil.invalid' ] as $origin ) {
			$request = $this->request();
			$request->set_header( 'Origin', $origin );
			$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		}
		$tunnel = new MonitoringTunnel();
		foreach ( [ 'invalid', str_replace( 'errors.rondo.club', '127.0.0.1', $this->envelope() ), $this->envelope( 'attachment' ), $this->envelope() . "\n{}\n{}" ] as $body ) {
			$this->assertSame( 400, $tunnel->forward( $this->request( $body ) )->get_status() );
		}
		$this->assertSame( 413, $tunnel->forward( $this->request( str_repeat( 'x', 65537 ) ) )->get_status() );
	}

	public function test_budgets_throttle_and_upstream_backoff_is_preserved(): void {
		$tunnel = new MonitoringTunnel();
		$filter = static function () {
			return [
				'response' => [ 'code' => 429 ],
				'headers'  => [
					'retry-after'          => '120',
					'x-sentry-rate-limits' => '120:transaction',
				],
				'body'     => 'PRIVATE',
			];
		};
		add_filter( 'pre_http_request', $filter, 20 );
		try {
			$response = $tunnel->forward( $this->request() );
			$this->assertSame( 429, $response->get_status() );
			$this->assertSame( '120', $response->get_headers()['Retry-After'] );
			$this->assertSame( '120:transaction', $response->get_headers()['X-Sentry-Rate-Limits'] );
		} finally {
			remove_filter( 'pre_http_request', $filter, 20 );
		}
		$minute_key = 'rondo_diag_min_' . hash_hmac( 'sha256', (int) floor( time() / 60 ) . ':192.0.2.123', wp_salt() );
		set_transient( $minute_key, 20, 120 );
		$this->assertSame( 429, $tunnel->forward( $this->request() )->get_status() );
		delete_transient( $minute_key );
		set_transient( 'rondo_diag_day_' . gmdate( 'Ymd' ), 2000, DAY_IN_SECONDS );
		$this->assertSame( 429, $tunnel->forward( $this->request() )->get_status() );
		$this->clear_budget();
		$this->assertSame( 503, $tunnel->forward( $this->request() )->get_status() );
	}
}
