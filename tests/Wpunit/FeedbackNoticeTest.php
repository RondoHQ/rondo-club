<?php

namespace Tests\Wpunit;

use Rondo\Config\ClubConfig;
use Rondo\REST\Api;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Tests for feedback notice configuration and administrator-only writes. */
class FeedbackNoticeTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		delete_option( ClubConfig::OPTION_FEEDBACK_NOTICE );
	}

	public function test_notice_defaults_do_not_direct_other_clubs_to_awc(): void {
		$notice = ClubConfig::get_feedback_notice();
		$this->assertFalse( $notice['enabled'] );
		$this->assertSame( '', $notice['email'] );
		$this->assertStringContainsString( '{email}', $notice['text'] );
		$this->assertSame( $notice, rondo_get_js_config()['feedbackNotice'] );
	}

	public function test_admin_can_save_disable_and_reenable_notice_without_losing_content(): void {
		$server = $this->bootRestControllers( [ Api::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request = new WP_REST_Request( 'POST', '/rondo/v1/config' );
		$request->set_param(
			'feedback_notice',
			[
				'enabled' => true,
				'title'   => 'Account <b>wijzigen</b>?',
				'text'    => "Mail {email}.\n\nAlleen feedback over Rondo.<script>alert(1)</script>",
				'email'   => 'leden@example.com',
			]
		);
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$saved = $response->get_data()['feedback_notice'];
		$this->assertSame( 'Account wijzigen?', $saved['title'] );
		$this->assertSame( "Mail {email}.\n\nAlleen feedback over Rondo.", $saved['text'] );
		$this->assertSame( $saved, get_option( ClubConfig::OPTION_FEEDBACK_NOTICE ) );

		foreach ( [ false, true, true ] as $enabled ) {
			$request = new WP_REST_Request( 'POST', '/rondo/v1/config' );
			$request->set_param( 'feedback_notice', [ 'enabled' => $enabled ] );
			$response = $server->dispatch( $request );
			$this->assertSame( 200, $response->get_status() );
			$saved['enabled'] = $enabled;
			$this->assertSame( $saved, $response->get_data()['feedback_notice'] );
		}
	}

	public function test_invalid_address_does_not_overwrite_saved_notice(): void {
		$server = $this->bootRestControllers( [ Api::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		ClubConfig::update_feedback_notice(
			[
				'enabled' => true,
				'email'   => 'leden@example.com',
			]
			);
		$saved = ClubConfig::get_feedback_notice();

		foreach ( [ '', 'invalid', "leden@example.com\r\nBcc: other@example.com" ] as $email ) {
			$request = new WP_REST_Request( 'POST', '/rondo/v1/config' );
			$request->set_param( 'feedback_notice', [ 'email' => $email ] );
			$this->assertSame( 400, $server->dispatch( $request )->get_status() );
			$this->assertSame( $saved, ClubConfig::get_feedback_notice() );
		}
	}

	public function test_enabled_notice_requires_title_and_text(): void {
		foreach ( [ 'title', 'text' ] as $field ) {
			$result = ClubConfig::update_feedback_notice(
				[
					'enabled' => true,
					'email'   => 'leden@example.com',
					$field    => '',
				]
				);
			$this->assertWPError( $result );
			$this->assertFalse( ClubConfig::get_feedback_notice()['enabled'] );
		}
	}

	public function test_non_admin_and_anonymous_users_cannot_change_notice(): void {
		$server = $this->bootRestControllers( [ Api::class ] );
		foreach ( [ $this->createRondoUser(), 0 ] as $user_id ) {
			wp_set_current_user( $user_id );
			$request = new WP_REST_Request( 'POST', '/rondo/v1/config' );
			$request->set_param( 'feedback_notice', [ 'title' => 'Changed' ] );
			$this->assertContains( $server->dispatch( $request )->get_status(), [ 401, 403 ] );
			$this->assertSame( ClubConfig::DEFAULT_FEEDBACK_NOTICE, ClubConfig::get_feedback_notice() );
		}
	}
}
