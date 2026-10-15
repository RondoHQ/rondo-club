<?php
/**
 * Bounded REST performance measurements without request or member data.
 *
 * @package Rondo
 */

namespace Rondo;

use Sentry\Event;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;

/** Instrument registered REST routes and WordPress HTTP calls. */
class PerformanceMonitoring {
	/** @var \Sentry\Tracing\Transaction|null Current REST transaction. */
	private static $transaction;
	/** @var string Registered route name, never a concrete request URL. */
	private static $name = '';
	/** @var array Active external requests, indexed by a local hash. */
	private static $http = [];
	/** @var \Sentry\Tracing\Span|null REST callback. */
	private static $callback;
	/** @var int Number of recorded HTTP spans. */
	private static $span_count = 0;
	/** @var \WP_REST_Request|null Outer request. */
	private static $request;

	/** Register once during early monitoring initialization. */
	public static function boot() {
		add_filter( 'rest_pre_dispatch', [ self::class, 'start' ], 1, 3 );
		add_filter( 'rest_request_before_callbacks', [ self::class, 'before_callback' ], 1, 3 );
		add_filter( 'rest_request_after_callbacks', [ self::class, 'after_callback' ], PHP_INT_MAX, 3 );
		add_filter( 'rest_post_dispatch', [ self::class, 'finish' ], PHP_INT_MAX, 3 );
		add_filter( 'http_request_args', [ self::class, 'http_start' ], PHP_INT_MAX, 2 );
		add_action( 'http_api_debug', [ self::class, 'http_finish' ], 10, 5 );
		add_action( 'shutdown', [ self::class, 'shutdown' ], PHP_INT_MAX );
	}

	/** Whether REST tracing is enabled. */
	public static function enabled(): bool {
		return ! defined( 'RONDO_GLITCHTIP_PHP_PERFORMANCE' ) || RONDO_GLITCHTIP_PHP_PERFORMANCE;
	}

	/**
	 * Start only known routes, with sampled parent propagation from our frontend.
	 *
	 * @param mixed            $result Existing dispatch result.
	 * @param \WP_REST_Server  $server REST server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function start( $result, $server, $request ) {
		if ( ! self::enabled() || self::$transaction || ! preg_match( '#^/(rondo/v1|wp/v2)/#', $request->get_route() ) ) {
			return $result;
		}
		try {
			foreach ( array_keys( $server->get_routes() ) as $route ) {
				if ( ! preg_match( '@^' . $route . '$@i', $request->get_route() ) ) {
					continue;
				}
				$name             = preg_replace( '/\(\?P<[^>]+>[^)]+\)/', ':id', $route );
				self::$request    = $request;
				self::$span_count = 0;
				self::$name       = $request->get_method() . ' ' . $name;
				$header           = (string) $request->get_header( 'sentry-trace' );
				$context          = TransactionContext::fromHeaders( preg_match( '/^[a-f0-9]{32}-[a-f0-9]{16}-[01]$/', $header ) ? $header : '', '' );
				$context->setName( self::$name );
				$context->setOp( 'http.server' );
				$context->setStartTimestamp( microtime( true ) );
				self::$transaction = \Sentry\startTransaction( $context );
				\Sentry\SentrySdk::getCurrentHub()->setSpan( self::$transaction );
				break;
			}
		} catch ( \Throwable $error ) { // Monitoring must never change a REST result.
			self::$transaction = null;
		}
		return $result;
	}

	/**
	 * Measure permission checks and controller work.
	 *
	 * @param mixed $response Existing response.
	 * @return mixed
	 */
	public static function before_callback( $response, $handler = null, $request = null ) {
		if ( $request === self::$request && self::$transaction && self::$transaction->getSampled() ) {
			self::$callback = self::$transaction->startChild( ( new SpanContext() )->setOp( 'function' )->setDescription( 'rest.callback' ) );
		}
		return $response;
	}

	/**
	 * Close callback measurement.
	 *
	 * @param mixed $response Existing response.
	 * @return mixed
	 */
	public static function after_callback( $response, $handler = null, $request = null ) {
		if ( ( $request === null || $request === self::$request ) && self::$callback ) {
			self::$callback->finish();
			self::$callback = null;
		}
		return $response;
	}

	/**
	 * Start bounded external-call measurements; URLs remain local.
	 *
	 * @param array  $args Request arguments.
	 * @param string $url Request URL.
	 * @return array
	 */
	public static function http_start( $args, $url ) {
		if ( self::$transaction && self::$transaction->getSampled() && self::$span_count < 99 ) {
			++self::$span_count;
			$host                                   = wp_parse_url( $url, PHP_URL_HOST );
			$allowed                                = [ 'api.mollie.com', 'api.laposta.nl', 'api.lettermint.co', 'www.googleapis.com', 'oauth2.googleapis.com' ];
			$name                                   = in_array( $host, $allowed, true ) ? $host : 'external';
			self::$http[ hash( 'sha256', $url ) ][] = self::$transaction->startChild( ( new SpanContext() )->setOp( 'http.client' )->setDescription( $name ) );
		}
		return $args;
	}

	/**
	 * End one external request without retaining its response.
	 *
	 * @param mixed  $response Response.
	 * @param string $context Debug context.
	 * @param string $class Transport class.
	 * @param array  $args Request arguments.
	 * @param string $url Request URL.
	 */
	public static function http_finish( $response, $context, $class, $args, $url ) {
		$key = hash( 'sha256', $url );
		if ( ! empty( self::$http[ $key ] ) ) {
			$span = array_pop( self::$http[ $key ] );
			$span->setStatus( is_wp_error( $response ) ? SpanStatus::internalError() : SpanStatus::createFromHttpStatusCode( wp_remote_retrieve_response_code( $response ) ) );
			$span->finish();
		}
	}

	/**
	 * Send the completed transaction, preserving the REST response.
	 *
	 * @param mixed $response Response.
	 * @return mixed
	 */
	public static function finish( $response, $server = null, $request = null ) {
		if ( ( $request === null || $request === self::$request ) && self::$transaction ) {
			try {
				self::after_callback( $response );
				foreach ( self::$http as $spans ) {
					foreach ( $spans as $span ) {
						$span->finish();
					}
				}
				$status = $response instanceof \WP_HTTP_Response ? $response->get_status() : 500;
				self::$transaction->setStatus( SpanStatus::createFromHttpStatusCode( $status ) );
				self::$transaction->finish();
			} catch ( \Throwable $error ) { // Observability is best effort.
				// The application response remains unchanged.
			} finally {
				self::$transaction = null;
				self::$http        = [];
				\Sentry\SentrySdk::getCurrentHub()->setSpan( null );
			}
		}
		return $response;
	}

	/** Close unfinished requests on shutdown (including fatal failures). */
	public static function shutdown() {
		self::finish( null );
	}

	/**
	 * Rebuild the transaction from timing, trace IDs and our fixed labels only.
	 *
	 * @param Event $event Outgoing transaction.
	 * @return Event|null
	 */
	public static function sanitize( Event $event ): ?Event {
		if ( ! self::enabled() || ! self::$name || $event->getTransaction() !== self::$name ) {
			return null;
		}
		$clean = Event::createTransaction( $event->getId() );
		$clean->setTransaction( self::$name );
		$clean->setStartTimestamp( $event->getStartTimestamp() );
		$clean->setTimestamp( $event->getTimestamp() );
		$clean->setRelease( $event->getRelease() );
		$clean->setEnvironment( $event->getEnvironment() );
		$trace = $event->getContexts()['trace'] ?? [];
		$clean->setContext( 'trace', array_intersect_key( $trace, array_flip( [ 'trace_id', 'span_id', 'parent_span_id', 'op', 'status' ] ) ) );
		$spans = array_slice( $event->getSpans(), 0, 100 );
		foreach ( $spans as $span ) {
			$span->setData( [] );
			$span->setTags( [] );
			if ( ! in_array( $span->getDescription(), [ 'rest.callback', 'api.mollie.com', 'api.laposta.nl', 'api.lettermint.co', 'www.googleapis.com', 'oauth2.googleapis.com' ], true ) ) {
				$span->setDescription( 'external' );
			}
		}
		$clean->setSpans( $spans );
		return $clean;
	}
}
