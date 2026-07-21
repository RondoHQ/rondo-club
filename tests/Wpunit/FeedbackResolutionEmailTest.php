<?php

namespace Tests\Wpunit;

use Rondo\Feedback\ResolutionEmailSender;
use Rondo\REST\Feedback;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class FeedbackResolutionEmailTest extends RondoTestCase {

	/** @var array<int, array<string, mixed>> */
	private array $sent_mail = [];

	protected function set_up(): void {
		parent::set_up();

		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->sent_mail[] = $atts;
				return true;
			},
			10,
			2
		);
	}

	public function test_resolving_feedback_sends_one_branded_email_to_its_author(): void {
		$reporter_id = self::factory()->user->create(
			[
				'user_login' => 'feedback_reporter',
				'user_email' => 'reporter@example.com',
				'first_name' => 'Anne',
			]
		);
		$feedback_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_feedback',
				'post_status' => 'publish',
				'post_author' => $reporter_id,
				'post_title'  => 'Makkelijker plannen',
			]
		);
		update_field( 'status', 'approved', $feedback_id );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$response = $this->resolve( $feedback_id );
		$data     = $response->get_data();

		$this->assertSame( 'resolved', $data['meta']['status'] );
		$this->assertSame( 'sent', $data['resolution_email']['status'] );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( [ 'reporter@example.com' ], $this->sent_mail[0]['to'] );
		$this->assertSame( 'Je feedback is opgelost: Makkelijker plannen', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( '<!doctype html>', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Hoi Anne,', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Bekijk je feedback', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( home_url( '/feedback/' . $feedback_id ), $this->sent_mail[0]['message'] );
		$this->assertSame( [ 'Content-Type: text/html; charset=UTF-8' ], $this->sent_mail[0]['headers'] );
		$this->assertNotEmpty( get_post_meta( $feedback_id, ResolutionEmailSender::META_SENT_AT, true ) );

		$second_response = $this->resolve( $feedback_id );
		$this->assertArrayNotHasKey( 'resolution_email', $second_response->get_data() );
		$this->assertCount( 1, $this->sent_mail, 'A repeated resolved status must not send another email.' );
	}

	public function test_resolution_email_uses_the_real_household_contact_address(): void {
		$reporter_id = self::factory()->user->create(
			[
				'user_login' => 'household_feedback_reporter',
				'user_email' => 'person-123@members.rondo.invalid',
			]
		);
		update_user_meta( $reporter_id, UserProvisioning::META_CONTACT_EMAIL, 'family@example.com' );
		$feedback_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_feedback',
				'post_status' => 'publish',
				'post_author' => $reporter_id,
				'post_title'  => 'Feedback van gezinslid',
			]
		);

		$result = ( new ResolutionEmailSender() )->send( $feedback_id );

		$this->assertSame( 'sent', $result['status'] );
		$this->assertSame( [ 'family@example.com' ], $this->sent_mail[0]['to'] );
	}

	private function resolve( int $feedback_id ) {
		$request = new WP_REST_Request( 'PUT', '/rondo/v1/feedback/' . $feedback_id );
		$request->set_param( 'id', $feedback_id );
		$request->set_param( 'status', 'resolved' );

		return ( new Feedback() )->update_feedback( $request );
	}
}
