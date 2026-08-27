<?php

namespace Tests\Wpunit;

use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Tests for the one-time feedback introduction.
 */
class FeedbackIntroTest extends RondoTestCase {

	public function test_feedback_intro_is_unseen_for_an_account_without_acknowledgement(): void {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$user = ( new UserSettings() )->get_current_user_data( $user_id );

		$this->assertFalse( $user['feedback_intro_seen'] );
	}

	public function test_logged_in_user_can_acknowledge_feedback_intro(): void {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$controller = new UserSettings();

		$response = $controller->mark_feedback_intro_seen();
		$user     = $controller->get_current_user_data( $user_id );

		$this->assertTrue( $response->get_data()['feedback_intro_seen'] );
		$this->assertNotEmpty( get_user_meta( $user_id, 'rondo_feedback_intro_seen_at', true ) );
		$this->assertTrue( $user['feedback_intro_seen'] );
	}

	public function test_feedback_intro_route_requires_authentication(): void {
		$server = $this->bootRestControllers( [ UserSettings::class ] );
		wp_set_current_user( 0 );

		$response = $server->dispatch( new WP_REST_Request( 'POST', '/rondo/v1/user/feedback-intro-seen' ) );

		$this->assertSame( 401, $response->get_status() );
	}
}
