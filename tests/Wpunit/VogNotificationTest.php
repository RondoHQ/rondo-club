<?php
namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\UserSettings;
use Rondo\VOG\VOGEmail;
use Rondo\VOG\VogSubmissions as Store;
use Tests\Support\RondoTestCase;

class VogNotificationTest extends RondoTestCase {

	private int $person;
	private int $member;
	private array $mail        = [];
	private bool $mail_success = true;

	protected function set_up(): void {
		parent::set_up();
		Store::register();
		$this->person = $this->createPerson();
		$this->member = $this->createRondoUser();
		update_user_meta( $this->member, 'rondo_linked_person_id', $this->person );
		update_option( VOGEmail::OPTION_FROM_EMAIL, 'vog@example.com' );
		wp_set_current_user( $this->member );
		add_filter(
			'pre_wp_mail',
			function ( $result, $mail ) {
				$this->mail[] = $mail;
				return $this->mail_success;
			},
			10,
			2
		);
	}

	private function submission( string $status = 'review' ): int {
		$id             = Store::create( $this->person, $this->member, 'digital', [], [] );
		$data           = Store::get( $id );
		$data['status'] = $status;
		Store::save( $id, $data );
		return $id;
	}

	public function test_manual_review_sends_one_private_notification_to_vog_mailbox(): void {
		$id = $this->submission();
		$this->assertNotFalse( wp_next_scheduled( Store::REVIEW_NOTICE_HOOK, [ $id ] ) );
		$this->assertCount( 0, $this->mail );
		Store::notify_reviewer( $id );
		Store::notify_reviewer( $id );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( [ 'vog@example.com' ], $this->mail[0]['to'] );
		$this->assertSame( 'VOG wacht op beoordeling', $this->mail[0]['subject'] );
		$this->assertStringContainsString( 'inzending ' . $id, $this->mail[0]['message'] );
		$this->assertStringContainsString( home_url( '/vrijwilligers/vog/beoordelen' ), $this->mail[0]['message'] );
		$this->assertEmpty( $this->mail[0]['attachments'] );
		$this->assertNotEmpty( get_post_meta( $id, Store::REVIEW_NOTICE_SENT, true ) );
		$this->assertFalse( wp_next_scheduled( Store::REVIEW_NOTICE_HOOK, [ $id ] ) );
	}

	public function test_paper_and_scan_uploads_queue_notifications(): void {
		foreach ( [ 'paper', 'digital_scan', 'unknown' ] as $source ) {
			$id = Store::create( $this->person, $this->member, $source, [], [] );
			$this->assertNotFalse( wp_next_scheduled( Store::REVIEW_NOTICE_HOOK, [ $id ] ) );
			Store::notify_reviewer( $id );
		}
		$this->assertCount( 3, $this->mail );
	}

	public function test_technical_failure_notifies_only_after_automatic_retries(): void {
		$id               = $this->submission( 'technical' );
		$data             = Store::get( $id );
		$data['attempts'] = 2;
		Store::save( $id, $data );
		Store::notify_reviewer( $id );
		$this->assertCount( 0, $this->mail );
		$data['attempts'] = 3;
		Store::save( $id, $data );
		Store::notify_reviewer( $id );
		Store::notify_reviewer( $id );
		$this->assertCount( 1, $this->mail );
	}

	public function test_finished_replaced_expired_unlinked_and_demo_submissions_do_not_send(): void {
		foreach ( [ 'approved', 'rejected', 'expired', 'replaced' ] as $status ) {
			$id = $this->submission();
			Store::finish( $id, Store::get( $id ), $status );
			Store::notify_reviewer( $id );
			$this->assertFalse( wp_next_scheduled( Store::REVIEW_NOTICE_HOOK, [ $id ] ) );
		}
		$id = $this->submission();
		$this->submission();
		Store::notify_reviewer( $id );
		$id              = $this->submission();
		$data            = Store::get( $id );
		$data['expires'] = time() - 1;
		Store::save( $id, $data );
		Store::notify_reviewer( $id );
		$id = $this->submission();
		update_user_meta( $this->member, 'rondo_linked_person_id', $this->createPerson() );
		Store::notify_reviewer( $id );
		update_user_meta( $this->member, 'rondo_linked_person_id', $this->person );
		update_option( 'rondo_is_demo_site', true );
		Store::notify_reviewer( $id );
		$this->assertCount( 0, $this->mail );
	}

	public function test_failed_mail_retries_are_bounded_and_do_not_claim_success(): void {
		$id                 = $this->submission();
		$this->mail_success = false;
		for ( $i = 0; $i < 4; ++$i ) {
			wp_clear_scheduled_hook( Store::REVIEW_NOTICE_HOOK, [ $id ] );
			Store::notify_reviewer( $id );
			$this->assertSame( $i < 2, (bool) wp_next_scheduled( Store::REVIEW_NOTICE_HOOK, [ $id ] ) );
		}
		$this->assertCount( 3, $this->mail );
		$this->assertEmpty( get_post_meta( $id, Store::REVIEW_NOTICE_SENT, true ) );
		$this->assertSame( 'review', Store::get( $id )['status'] );
	}

	public function test_successful_retry_stops_future_mail(): void {
		$id                 = $this->submission();
		$this->mail_success = false;
		Store::notify_reviewer( $id );
		$this->mail_success = true;
		Store::notify_reviewer( $id );
		Store::notify_reviewer( $id );
		$this->assertCount( 2, $this->mail );
		$this->assertNotEmpty( get_post_meta( $id, Store::REVIEW_NOTICE_SENT, true ) );
	}

	public function test_upload_prompt_follows_justis_date_and_pending_submission(): void {
		$settings = new UserSettings();
		$this->assertFalse( $settings->get_current_user_data( $this->member )['needs_vog_upload'] );
		update_post_meta( $this->person, 'vog_justis_submitted_date', gmdate( 'Y-m-d' ) );
		$this->assertTrue( $settings->get_current_user_data( $this->member )['needs_vog_upload'] );
		$id = $this->submission();
		foreach ( [ 'checking', 'review', 'technical', 'waiting_paper', 'awaiting_member' ] as $status ) {
			$data           = Store::get( $id );
			$data['status'] = $status;
			Store::save( $id, $data );
			$this->assertFalse( Store::needs_upload( $this->person, $this->member ), $status );
		}
		foreach ( [ 'needs_original', 'rejected', 'expired', 'replaced' ] as $status ) {
			$data           = Store::get( $id );
			$data['status'] = $status;
			Store::save( $id, $data );
			$this->assertTrue( Store::needs_upload( $this->person, $this->member ), $status );
		}
		$data            = Store::get( $id );
		$data['status']  = 'review';
		$data['expires'] = time() - 1;
		Store::save( $id, $data );
		$this->assertTrue( Store::needs_upload( $this->person, $this->member ) );
		delete_post_meta( $this->person, 'vog_justis_submitted_date' );
		$this->assertFalse( Store::needs_upload( $this->person, $this->member ) );
	}

	public function test_upload_prompt_respects_member_eligibility(): void {
		update_post_meta( $this->person, 'vog_justis_submitted_date', gmdate( 'Y-m-d' ) );
		$this->assertFalse( Store::needs_upload( $this->person, $this->createRondoUser() ) );
		Fields::update_for_post( $this->person, 'former_member', true );
		$this->assertFalse( Store::needs_upload( $this->person, $this->member ) );
		Fields::update_for_post( $this->person, 'former_member', false );
		update_option( 'rondo_is_demo_site', true );
		$this->assertFalse( Store::needs_upload( $this->person, $this->member ) );
	}
}
