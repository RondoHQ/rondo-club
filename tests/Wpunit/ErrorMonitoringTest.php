<?php

namespace Tests\Wpunit;

use Rondo\ErrorMonitoring;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Tests\Support\RondoTestCase;

/** Privacy boundary for outgoing PHP diagnostics. */
class ErrorMonitoringTest extends RondoTestCase {
	public function test_private_payloads_are_removed_but_stack_locations_survive(): void {
		$event = Event::createEvent();
		$event->setRelease( 'rondo-club@test' );
		$event->setRequest( [ 'headers' => [ 'Authorization' => 'SECRET' ] ] );
		$event->setExtra( [ 'member' => 'PRIVATE' ] );
		$frame = new Frame( 'rondo_render', '/theme/file.php', 42, null, null, [ 'member' => 'PRIVATE' ] );
		$frame->setContextLine( 'PRIVATE' );
		$frame->setPreContext( [ 'PRIVATE' ] );
		$frame->setPostContext( [ 'PRIVATE' ] );
		$event->setExceptions( [ new ExceptionDataBag( new \RuntimeException( 'PRIVATE' ), new Stacktrace( [ $frame ] ) ) ] );
		$clean = ErrorMonitoring::sanitize_event( $event );
		$this->assertSame( [], $clean->getRequest() );
		$this->assertSame( [], $clean->getExtra() );
		$this->assertSame( 'rondo-club@test', $clean->getRelease() );
		$payload = ( new \Sentry\Serializer\PayloadSerializer( new \Sentry\Options() ) )->serialize( $clean );
		$this->assertStringNotContainsString( 'PRIVATE', $payload );
		$this->assertStringNotContainsString( 'SECRET', $payload );
		$this->assertStringContainsString( 'rondo_render', $payload );
		$this->assertStringContainsString( '42', $payload );
	}
}
