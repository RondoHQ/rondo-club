<?php

namespace Tests\Wpunit;

use Rondo\Feedback\NewFeedbackEmailSender;
use Rondo\REST\Feedback;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class NewFeedbackEmailTest extends RondoTestCase {

	/** @var array<int, array<string, mixed>> */
	private array $sent_mail = [];

	private bool $mail_should_send = true;

	protected function set_up(): void {
		parent::set_up();

		update_option( 'admin_email', 'feedback-admin@example.com' );

		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->sent_mail[] = $atts;
				return $this->mail_should_send;
			},
			10,
			2
		);
	}

	public function test_creating_feedback_sends_one_branded_email_to_the_admin_address(): void {
		$reporter_id = self::factory()->user->create(
			[
				'user_login'   => 'new_feedback_reporter',
				'user_email'   => 'person-456@members.rondo.invalid',
				'display_name' => 'Anne Melder',
			]
		);
		update_user_meta( $reporter_id, UserProvisioning::META_CONTACT_EMAIL, 'anne@example.com' );
		wp_set_current_user( $reporter_id );

		$response    = $this->create_feedback();
		$data        = $response->get_data();
		$feedback_id = (int) $data['id'];

		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( [ 'feedback-admin@example.com' ], $this->sent_mail[0]['to'] );
		$this->assertSame( 'Nieuwe feedback #' . $feedback_id . ': Mobiele planning verbeteren', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( '<!doctype html>', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Anne Melder (anne@example.com)', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Functieverzoek', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'website', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Hoog', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'De planning moet beter werken op een telefoon.', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Bekijk feedback', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( home_url( '/feedback/' . $feedback_id ), $this->sent_mail[0]['message'] );
		$this->assertSame( [ 'Content-Type: text/html; charset=UTF-8' ], $this->sent_mail[0]['headers'] );
		$this->assertNotEmpty( get_post_meta( $feedback_id, NewFeedbackEmailSender::META_SENT_AT, true ) );

		$result = ( new NewFeedbackEmailSender() )->send( $feedback_id );

		$this->assertSame( 'already_sent', $result['status'] );
		$this->assertCount( 1, $this->sent_mail, 'The same feedback item must not send a duplicate notification.' );
	}

	public function test_mail_failure_does_not_prevent_feedback_creation(): void {
		$reporter_id = self::factory()->user->create(
			[
				'user_login' => 'failed_feedback_mail_reporter',
				'user_email' => 'reporter@example.com',
			]
		);
		wp_set_current_user( $reporter_id );
		$this->mail_should_send = false;

		$response    = $this->create_feedback();
		$data        = $response->get_data();
		$feedback_id = (int) $data['id'];

		$this->assertGreaterThan( 0, $feedback_id );
		$this->assertSame( 'rondo_feedback', get_post_type( $feedback_id ) );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( '', get_post_meta( $feedback_id, NewFeedbackEmailSender::META_SENT_AT, true ) );
	}

	private function create_feedback() {
		$request = new WP_REST_Request( 'POST', '/rondo/v1/feedback' );
		$request->set_param( 'title', 'Mobiele planning verbeteren' );
		$request->set_param( 'content', 'De planning moet beter werken op een telefoon.' );
		$request->set_param( 'feedback_type', 'feature_request' );
		$request->set_param( 'project', 'website' );
		$request->set_param( 'priority', 'high' );

		return ( new Feedback() )->create_feedback( $request );
	}
}
