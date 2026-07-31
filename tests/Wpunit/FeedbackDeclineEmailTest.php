<?php

namespace Tests\Wpunit;

use Rondo\Feedback\DeclineEmailSender;
use Rondo\Feedback\StatusService;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

class FeedbackDeclineEmailTest extends RondoTestCase {
	private const DECLINE_REASON = 'Dit kan al via de knop rechtsboven op het dashboard, dus we bouwen het niet nog een keer.';

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

	public function test_declining_feedback_sends_one_branded_email_to_its_author(): void {
		$reporter_id = self::factory()->user->create(
			[
				'user_login' => 'decline_reporter',
				'user_email' => 'declined@example.com',
				'first_name' => 'Sanne',
			]
		);
		$feedback_id = $this->create_feedback( $reporter_id, 'Tweede dashboardknop' );

		$result = ( new StatusService() )->update( $feedback_id, 'declined', '', self::DECLINE_REASON );

		$this->assertSame( 'declined', $result['status'] );
		$this->assertSame( 'sent', $result['decline_email']['status'] );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( [ 'declined@example.com' ], $this->sent_mail[0]['to'] );
		$this->assertSame( 'Je feedback is afgewezen: Tweede dashboardknop', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( 'Hoi Sanne,', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Waarom we dit niet doen', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( self::DECLINE_REASON, $this->sent_mail[0]['message'] );
		$this->assertNotEmpty( get_post_meta( $feedback_id, '_feedback_declined_at', true ) );
		$this->assertNotEmpty( get_post_meta( $feedback_id, DeclineEmailSender::META_SENT_AT, true ) );
	}

	public function test_feedback_cannot_be_declined_without_a_reason(): void {
		$feedback_id = $this->create_feedback( self::factory()->user->create(), 'Zonder reden' );

		$result = ( new StatusService() )->update( $feedback_id, 'declined' );

		$this->assertWPError( $result );
		$this->assertSame( 'feedback_decline_reason_required', $result->get_error_code() );
		$this->assertSame( 'approved', get_field( 'status', $feedback_id ) );
		$this->assertCount( 0, $this->sent_mail );
	}

	public function test_a_stored_reason_survives_reopening_and_declining_again(): void {
		$reporter_id = self::factory()->user->create( [ 'user_email' => 'again@example.com' ] );
		$feedback_id = $this->create_feedback( $reporter_id, 'Nogmaals afgewezen' );

		$status_service = new StatusService();
		$this->assertSame( 'sent', $status_service->update( $feedback_id, 'declined', '', self::DECLINE_REASON )['decline_email']['status'] );

		$status_service->update( $feedback_id, 'approved' );

		// The reason is still on the item, so declining again must not demand it
		// a second time — but the author has already been told once.
		$second = $status_service->update( $feedback_id, 'declined' );
		$this->assertSame( 'declined', $second['status'] );
		$this->assertSame( 'already_sent', $second['decline_email']['status'] );
		$this->assertCount( 1, $this->sent_mail, 'Reopening and declining again must not send a second mail.' );
	}

	public function test_resolving_is_unaffected_by_the_decline_reason_requirement(): void {
		$reporter_id = self::factory()->user->create( [ 'user_email' => 'resolved-still@example.com' ] );
		$feedback_id = $this->create_feedback( $reporter_id, 'Gewoon opgelost' );

		$result = ( new StatusService() )->update( $feedback_id, 'resolved', 'We hebben het aangepast.' );

		$this->assertSame( 'resolved', $result['status'] );
		$this->assertSame( 'sent', $result['resolution_email']['status'] );
		$this->assertArrayNotHasKey( 'decline_email', $result );
	}

	private function create_feedback( int $author_id, string $title ): int {
		update_user_meta( $author_id, UserProvisioning::META_CONTACT_EMAIL, get_userdata( $author_id )->user_email );
		$feedback_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_feedback',
				'post_status' => 'publish',
				'post_author' => $author_id,
				'post_title'  => $title,
			]
		);
		update_field( 'status', 'approved', $feedback_id );

		return $feedback_id;
	}
}
