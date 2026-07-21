<?php

namespace Tests\Wpunit;

use ReflectionMethod;
use Rondo\Notifications\LettermintMailer;
use Tests\Support\RondoTestCase;

class LettermintMailerTest extends RondoTestCase {

	public function test_headerless_mail_defaults_to_plain_text_content_type(): void {
		$headers = $this->parse_headers( [] );

		$this->assertSame( 'text/plain', $headers['content_type'] );
	}

	public function test_empty_content_type_header_keeps_plain_text_default(): void {
		$headers = $this->parse_headers( [ 'Content-Type:' ] );

		$this->assertSame( 'text/plain', $headers['content_type'] );
	}

	public function test_html_content_type_is_normalized_without_charset(): void {
		$headers = $this->parse_headers( [ 'Content-Type: text/html; charset=UTF-8' ] );

		$this->assertSame( 'text/html', $headers['content_type'] );
	}

	/**
	 * Exercise the transport's header parser without sending an external email.
	 *
	 * @param string|array $headers Raw wp_mail headers.
	 * @return array
	 */
	private function parse_headers( $headers ): array {
		$mailer = ( new \ReflectionClass( LettermintMailer::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( LettermintMailer::class, 'parse_headers' );
		$method->setAccessible( true );

		return $method->invoke( $mailer, $headers );
	}
}
