<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Volunteer;
use Tests\Support\RondoTestCase;

class IvaFileStreamingSecurityTest extends RondoTestCase {
	public function test_response_payload_cannot_stream_files_on_another_route(): void {
		$controller = new Volunteer();
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'administrator' ] ) );
		$result = new \WP_REST_Response(
			[
				'_rondo_private_file' => __FILE__,
				'mime_type'           => 'text/plain',
				'filename'            => 'private.txt',
			]
			);
		foreach ( [ '/rondo/v1/anything', '/rondo/v1/iva/123/certificate/extra' ] as $route ) {
			ob_start();
			$served = $controller->serve_private_iva_file( false, $result, new \WP_REST_Request( 'GET', $route ), rest_get_server() );
			$output = ob_get_clean();
			$this->assertFalse( $served );
			$this->assertSame( '', $output );
		}
	}

	public function test_certificate_response_does_not_expose_path_and_stream_rechecks_permission(): void {
		$this->bootRestControllers( [ Volunteer::class ] );
		$person     = $this->createPerson();
		$attachment = self::factory()->post->create(
			[
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
			]
			);
		Fields::update_for_post( $person, 'iva_certificaat', $attachment );
		update_post_meta( $attachment, '_rondo_private_iva_path', __FILE__ );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'administrator' ] ) );
		$request = new \WP_REST_Request( 'GET', "/rondo/v1/iva/$person/certificate" );
		$result  = rest_do_request( $request );
		$this->assertSame( 200, $result->get_status() );
		$this->assertSame( [ 'certificate' => true ], $result->get_data() );
		ob_start();
		$served = ( new Volunteer() )->serve_private_iva_file( false, $result, $request, rest_get_server() );
		$output = ob_get_clean();
		$this->assertTrue( $served );
		$this->assertSame( file_get_contents( __FILE__ ), $output );
		wp_set_current_user( 0 );
		ob_start();
		$served = ( new Volunteer() )->serve_private_iva_file( false, $result, $request, rest_get_server() );
		$output = ob_get_clean();
		$this->assertFalse( $served );
		$this->assertSame( '', $output );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
	}
}
