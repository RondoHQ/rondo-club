<?php
/**
 * Early, privacy-minimal error monitoring.
 *
 * @package Rondo
 */

namespace Rondo;

use Sentry\Event;

/** Collect PHP errors without transmitting request or member data. */
class ErrorMonitoring {
	/** Initialize the SDK from deployment configuration. */
	public static function boot() {
		$theme = get_file_data( dirname( __DIR__ ) . '/style.css', [ 'Version' => 'Version' ] );
		\Sentry\init(
			[
				'dsn'                  => RONDO_GLITCHTIP_DSN,
				'environment'          => defined( 'RONDO_GLITCHTIP_ENVIRONMENT' ) ? RONDO_GLITCHTIP_ENVIRONMENT : 'production',
				'release'              => 'rondo-club@' . $theme['Version'],
				'send_default_pii'     => false,
				'max_breadcrumbs'      => 0,
				'attach_stacktrace'    => true,
				'context_lines'        => 0,
				'error_types'          => E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR,
				'http_timeout'         => 2,
				'http_connect_timeout' => 1,
				'before_send'          => [ self::class, 'sanitize_event' ],
			]
		);
	}

	/**
	 * Return an allowlisted diagnostic event; raw messages stay in local logs.
	 *
	 * @param Event $event Original SDK event.
	 * @return Event
	 */
	public static function sanitize_event( Event $event ): Event {
		$clean = Event::createEvent( $event->getId() );
		$clean->setTimestamp( $event->getTimestamp() );
		$clean->setLevel( $event->getLevel() );
		$clean->setRelease( $event->getRelease() );
		$clean->setEnvironment( $event->getEnvironment() );
		$exceptions = $event->getExceptions();
		foreach ( $exceptions as $exception ) {
			$exception->setValue( 'Rondo PHP error; details in private server logs' );
			$exception->setMechanism( null );
			$stack = $exception->getStacktrace();
			if ( $stack ) {
				foreach ( $stack->getFrames() as $frame ) {
					$frame->setVars( [] );
					$frame->setPreContext( [] );
					$frame->setPostContext( [] );
					$frame->setContextLine( null );
				}
			}
		}
		$clean->setExceptions( $exceptions );
		return $clean;
	}
}
