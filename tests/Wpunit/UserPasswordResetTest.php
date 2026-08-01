<?php

namespace Tests\Wpunit;

use Rondo\REST\UserSettings;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Tests for requesting a password-set link from the authenticated profile.
 */
class UserPasswordResetTest extends RondoTestCase {

	/** @var string[] Recipients captured from wp_mail(). */
	private array $sent_to = [];

	protected function set_up(): void {
		parent::set_up();
		$this->sent_to = [];

		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->sent_to[] = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'];
				return true;
			},
			10,
			2
		);
	}

	public function test_logged_in_user_can_request_a_password_set_link(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'magic-link-member',
				'user_email' => 'member@example.com',
			]
		);
		wp_set_current_user( $user_id );

		$response = ( new UserSettings() )->request_password_reset();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( [ 'member@example.com' ], $this->sent_to );
	}

	public function test_household_placeholder_is_rerouted_to_contact_email(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'household-member',
				'user_email' => 'person-123@members.rondo.invalid',
			]
		);
		update_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, 'household@example.com' );
		wp_set_current_user( $user_id );

		$response = ( new UserSettings() )->request_password_reset();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'household@example.com' ], $this->sent_to );
	}

	public function test_account_without_contact_email_is_rejected(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'no-contact-email',
				'user_email' => 'person-456@members.rondo.invalid',
			]
		);
		wp_set_current_user( $user_id );

		$response = ( new UserSettings() )->request_password_reset();

		$this->assertWPError( $response );
		$this->assertSame( 'no_contact_email', $response->get_error_code() );
		$this->assertSame( [], $this->sent_to );
	}

	public function test_demo_user_cannot_request_a_password_set_link(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'demo',
				'user_email' => 'demo@example.com',
			]
		);
		wp_set_current_user( $user_id );

		$response = ( new UserSettings() )->request_password_reset();

		$this->assertWPError( $response );
		$this->assertSame( 'demo_user', $response->get_error_code() );
		$this->assertSame( [], $this->sent_to );
	}

	public function test_password_reset_route_requires_authentication(): void {
		$server = $this->bootRestControllers( [ UserSettings::class ] );
		wp_set_current_user( 0 );

		$response = $server->dispatch( new WP_REST_Request( 'POST', '/rondo/v1/user/password-reset' ) );

		$this->assertSame( 401, $response->get_status() );
	}
}
