<?php

namespace Tests\Wpunit;

use Rondo\PerformanceMonitoring;
use Sentry\Event;
use Tests\Support\RondoTestCase;

/** End-to-end SDK envelope privacy and REST lifecycle contract. */
class PerformanceMonitoringTest extends RondoTestCase {
	public function test_rest_trace_propagation_privacy_and_nested_requests(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-performance-monitoring.php';
		$events = [];
		\Sentry\init(
			[
				'dsn'                     => 'https://public@example.invalid/1',
				'traces_sample_rate'      => 1.0,
				'before_send_transaction' => static function ( Event $event ) use ( &$events ) {
					$event->setRequest(
						[
							'data'    => 'PRIVATE',
							'headers' => [ 'Authorization' => 'SECRET' ],
						]
						);
					$event->setExtra( [ 'member' => 'PRIVATE' ] );
					$events[] = PerformanceMonitoring::sanitize( $event );
					return null;
				},
			]
		);
		$server = new \WP_REST_Server();
		$server->register_route( 'rondo/v1', '/rondo/v1/performance-test/(?P<id>\d+)', [] );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/performance-test/123456' );
		$request->set_header( 'sentry-trace', str_repeat( 'a', 32 ) . '-' . str_repeat( 'b', 16 ) . '-1' );
		PerformanceMonitoring::start( null, $server, $request );
		PerformanceMonitoring::before_callback( null, null, $request );
		$args = [ 'headers' => [ 'Authorization' => 'SECRET' ] ];
		$this->assertSame( $args, PerformanceMonitoring::http_start( $args, 'https://api.mollie.com/PRIVATE?key=SECRET' ) );
		PerformanceMonitoring::http_finish( [ 'response' => [ 'code' => 200 ] ], 'response', '', [], 'https://api.mollie.com/PRIVATE?key=SECRET' );
		$nested = new \WP_REST_Request( 'GET', '/rondo/v1/performance-test/789' );
		PerformanceMonitoring::finish( new \WP_REST_Response( [], 200 ), $server, $nested );
		$this->assertCount( 0, $events );
		$response = new \WP_REST_Response( [ 'private' => 'PRIVATE' ], 200 );
		$this->assertSame( $response, PerformanceMonitoring::finish( $response, $server, $request ) );
		$this->assertCount( 1, $events );
		$this->assertSame( 'GET /rondo/v1/performance-test/:id', $events[0]->getTransaction() );
		$this->assertSame( str_repeat( 'a', 32 ), (string) $events[0]->getContexts()['trace']['trace_id'] );
		$this->assertCount( 2, $events[0]->getSpans() );
		$payload = ( new \Sentry\Serializer\PayloadSerializer( new \Sentry\Options() ) )->serialize( $events[0] );
		foreach ( [ 'PRIVATE', 'SECRET', '123456', 'Authorization' ] as $private ) {
			$this->assertStringNotContainsString( $private, $payload );
		}
		$this->assertStringContainsString( 'api.mollie.com', $payload );
		PerformanceMonitoring::shutdown();
		$this->assertCount( 1, $events );
		\Sentry\init( [ 'default_integrations' => false ] );
	}

	public function test_parent_sampling_decision_is_respected_and_unknown_routes_are_ignored(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-performance-monitoring.php';
		$events = [];
		\Sentry\init(
			[
				'dsn'                     => 'https://public@example.invalid/1',
				'traces_sample_rate'      => 1.0,
				'before_send_transaction' => static function ( $event ) use ( &$events ) {
							$events[] = $event;
							return null;
				},
			]
			);
		$server = new \WP_REST_Server();
		$server->register_route( 'rondo/v1', '/rondo/v1/performance-test', [] );
		$server->register_route( 'rondo/v1', '/rondo/v1/diagnostics', [] );
		$tunnel_request = new \WP_REST_Request( 'POST', '/rondo/v1/diagnostics' );
		$tunnel_request->set_header( 'sentry-trace', str_repeat( 'a', 32 ) . '-' . str_repeat( 'b', 16 ) . '-1' );
		PerformanceMonitoring::start( null, $server, $tunnel_request );
		PerformanceMonitoring::finish( new \WP_REST_Response( [], 204 ) );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/performance-test' );
		$request->set_header( 'sentry-trace', str_repeat( 'a', 32 ) . '-' . str_repeat( 'b', 16 ) . '-0' );
		PerformanceMonitoring::start( null, $server, $request );
		PerformanceMonitoring::finish( new \WP_REST_Response( [], 200 ) );
		PerformanceMonitoring::start( null, $server, new \WP_REST_Request( 'GET', '/rondo/v1/PRIVATE' ) );
		PerformanceMonitoring::shutdown();
		$this->assertCount( 0, $events );
		\Sentry\init( [ 'default_integrations' => false ] );
	}
}
