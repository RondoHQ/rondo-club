<?php

namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;

class RestErrorLoggingTest extends RondoTestCase {

	public function test_expected_client_responses_are_not_logged(): void {
		foreach ( [ 'rest_forbidden', 'rest_not_logged_in', 'overlap_warning', 'rest_post_invalid_page_number' ] as $code ) {
			$this->assertFalse( rondo_should_log_rest_error( new \WP_Error( $code, 'Expected response' ) ) );
		}
	}

	public function test_validation_and_server_errors_are_logged(): void {
		$this->assertTrue( rondo_should_log_rest_error( new \WP_Error( 'rest_invalid_param', 'Invalid ACF value' ) ) );
		$this->assertTrue( rondo_should_log_rest_error( new \WP_Error( 'internal_server_error', 'Unexpected failure' ) ) );
	}
}
