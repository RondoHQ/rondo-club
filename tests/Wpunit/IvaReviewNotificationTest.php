<?php

namespace Tests\Wpunit;

use Rondo\Collaboration\CommentTypes;
use Rondo\Core\UserRoles;
use Rondo\Users\UserProvisioning;
use Rondo\Volunteer\IvaReviewNotificationEmailSender;
use Tests\Support\RondoTestCase;

class IvaReviewNotificationTest extends RondoTestCase {

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

	public function test_upload_notification_reaches_each_unique_iva_approver_address(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Xander Notté' ] );

		$this->create_approver( 'approver-one@example.invalid', 'beheer@example.com' );
		$this->create_approver( 'approver-two@example.invalid', 'beheer@example.com' );

		$regular_user = self::factory()->user->create( [ 'user_email' => 'regular@example.com' ] );
		update_user_meta( $regular_user, UserProvisioning::META_CONTACT_EMAIL, 'regular@example.com' );

		$result = ( new IvaReviewNotificationEmailSender() )->send( $person_id );

		$manager_messages = array_values(
			array_filter(
				$this->sent_mail,
				fn( array $mail ): bool => in_array( 'beheer@example.com', (array) $mail['to'], true )
			)
		);
		$regular_messages = array_values(
			array_filter(
				$this->sent_mail,
				fn( array $mail ): bool => in_array( 'regular@example.com', (array) $mail['to'], true )
			)
		);

		$this->assertSame( 'sent', $result['status'] );
		$this->assertCount( 1, $manager_messages, 'Shared approver addresses must receive only one message.' );
		$this->assertCount( 0, $regular_messages, 'Users without IVA approval rights must not be notified.' );
		$this->assertSame( 'Nieuw IVA-certificaat van Xander Notté', $manager_messages[0]['subject'] );
		$this->assertStringContainsString( 'Bekijk en keur goed', $manager_messages[0]['message'] );
		$this->assertStringContainsString(
			esc_url( home_url( '/vrijwilligers/iva?review=' . $person_id ) ),
			$manager_messages[0]['message']
		);

		$email_logs  = get_comments(
			[
				'post_id' => $person_id,
				'type'    => CommentTypes::TYPE_EMAIL,
				'status'  => 'approve',
			]
		);
		$review_logs = array_filter(
			$email_logs,
			fn( $comment ): bool => get_comment_meta( $comment->comment_ID, 'email_template_type', true ) === 'iva_review_requested'
		);
		$this->assertNotEmpty( $review_logs );
	}

	public function test_a_resubmitted_upload_does_not_notify_the_approvers_again(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Jorrit Rutten' ] );
		$this->create_approver( 'approver-throttle@example.invalid', 'throttle@example.com' );

		$sender = new IvaReviewNotificationEmailSender();

		$this->assertSame( 'sent', $sender->send( $person_id )['status'] );

		// Reproduces the reported behaviour: four uploads inside four seconds,
		// each of which used to mail every approver.
		$repeats = [ $sender->send( $person_id ), $sender->send( $person_id ), $sender->send( $person_id ) ];
		foreach ( $repeats as $repeat ) {
			$this->assertSame( 'throttled', $repeat['status'] );
			$this->assertSame( 0, $repeat['sent'] );
		}

		$this->assertSame(
			1,
			$this->messages_to( 'throttle@example.com' ),
			'A resubmit within the throttle window must not send another mail.'
		);
	}

	public function test_a_later_upload_notifies_the_approvers_again(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Hans Velvis' ] );
		$this->create_approver( 'approver-later@example.invalid', 'later@example.com' );

		$sender = new IvaReviewNotificationEmailSender();
		$this->assertSame( 'sent', $sender->send( $person_id )['status'] );

		// An approver asked for a better scan and the member uploads again the
		// next day — that has to reach them.
		update_post_meta(
			$person_id,
			IvaReviewNotificationEmailSender::META_NOTIFIED_AT,
			time() - ( IvaReviewNotificationEmailSender::THROTTLE_SECONDS + 60 )
		);

		$this->assertSame( 'sent', $sender->send( $person_id )['status'] );
		$this->assertSame( 2, $this->messages_to( 'later@example.com' ) );
	}

	public function test_a_failed_send_does_not_suppress_the_next_attempt(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Bart Schraven' ] );
		$this->create_approver( 'approver-failed@example.invalid', 'failed@example.com' );

		// Priority 20 so this runs after the recorder in set_up() and has the
		// final say over what wp_mail() returns.
		$fail   = fn() => false;
		add_filter( 'pre_wp_mail', $fail, 20 );
		$result = ( new IvaReviewNotificationEmailSender() )->send( $person_id );
		remove_filter( 'pre_wp_mail', $fail, 20 );

		$this->assertSame( 'send_failed', $result['status'] );
		$this->assertSame(
			'',
			get_post_meta( $person_id, IvaReviewNotificationEmailSender::META_NOTIFIED_AT, true ),
			'A send that reached nobody must not leave a stamp that blocks the retry.'
		);

		$this->assertSame( 'sent', ( new IvaReviewNotificationEmailSender() )->send( $person_id )['status'] );
	}

	private function messages_to( string $email ): int {
		return count(
			array_filter(
				$this->sent_mail,
				fn( array $mail ): bool => in_array( $email, (array) $mail['to'], true )
			)
		);
	}

	private function create_approver( string $user_email, string $contact_email ): int {
		$user_id = self::factory()->user->create( [ 'user_email' => $user_email ] );
		$user    = get_userdata( $user_id );
		$user->add_cap( UserRoles::IVA_APPROVE_CAPABILITY );
		update_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, $contact_email );

		return $user_id;
	}
}
