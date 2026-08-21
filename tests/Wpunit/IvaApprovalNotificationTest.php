<?php

namespace Tests\Wpunit;

use Rondo\Collaboration\CommentTypes;
use Rondo\Config\ClubConfig;
use Rondo\REST\Volunteer;
use Rondo\Volunteer\IvaStatus;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class IvaApprovalNotificationTest extends RondoTestCase {

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

		delete_option( ClubConfig::OPTION_IVA_APPROVAL_EMAIL_SUBJECT );
		delete_option( ClubConfig::OPTION_IVA_APPROVAL_EMAIL_BODY );
		delete_option( ClubConfig::OPTION_CLUB_NAME );
	}

	protected function tear_down(): void {
		delete_option( ClubConfig::OPTION_IVA_APPROVAL_EMAIL_SUBJECT );
		delete_option( ClubConfig::OPTION_IVA_APPROVAL_EMAIL_BODY );
		delete_option( ClubConfig::OPTION_CLUB_NAME );
		parent::tear_down();
	}

	public function test_approving_valid_iva_sends_one_actionable_email(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Xander Notté' ],
			[
				'first_name' => 'Xander',
				'email_1'    => 'xander@example.com',
				'datum-iva'  => current_time( 'Y-m-d' ),
			]
		);

		$response = $this->approve( $person_id, true );

		$this->assertSame( 'valid', $response->get_data()['status'] );
		$this->assertSame( 'sent', $response->get_data()['notification']['status'] );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( [ 'xander@example.com' ], $this->sent_mail[0]['to'] );
		$this->assertSame( 'Je bewijs voor verantwoord alcohol schenken is goedgekeurd', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( 'Je kunt je nu ook inschrijven', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( home_url( '/vrijwillig' ), $this->sent_mail[0]['message'] );

		$email_logs = get_comments(
			[
				'post_id' => $person_id,
				'type'    => CommentTypes::TYPE_EMAIL,
				'status'  => 'approve',
			]
		);
		$this->assertCount( 1, $email_logs );
		$this->assertSame( 'iva_approved', get_comment_meta( $email_logs[0]->comment_ID, 'email_template_type', true ) );

		$this->approve( $person_id, true );
		$this->assertCount( 1, $this->sent_mail, 'Repeated approval must not send a duplicate email.' );
	}

	public function test_reapproval_after_revocation_sends_a_new_email(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'IVA Member' ],
			[
				'email_1'   => 'member@example.com',
				'datum-iva' => current_time( 'Y-m-d' ),
			]
		);

		$this->approve( $person_id, true );
		$this->approve( $person_id, false );
		$this->approve( $person_id, true );

		$this->assertCount( 2, $this->sent_mail );
	}

	public function test_configured_email_text_replaces_person_and_club_placeholders(): void {
		ClubConfig::update_club_name( 'SV AWC' );
		ClubConfig::update_iva_approval_email_subject( 'IVA goedgekeurd voor {full_name}' );
		ClubConfig::update_iva_approval_email_body( "Dag {first_name},\n\nWelkom bij {club_name}." );

		$person_id = $this->createPerson(
			[ 'post_title' => 'Xander Notté' ],
			[
				'first_name' => 'Xander',
				'email_1'    => 'xander@example.com',
				'datum-iva'  => current_time( 'Y-m-d' ),
			]
		);

		$this->approve( $person_id, true );

		$this->assertSame( 'IVA goedgekeurd voor Xander Notté', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( 'Dag Xander,', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Welkom bij SV AWC.', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( home_url( '/vrijwillig' ), $this->sent_mail[0]['message'] );
	}

	public function test_old_social_hygiene_diploma_remains_valid_after_manual_approval(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Social Hygiene Diploma Holder' ],
			[
				'email_1'   => 'diploma@example.com',
				'datum-iva' => '2005-11-22',
			]
		);

		$response = $this->approve( $person_id, true );

		$this->assertSame( 'valid', $response->get_data()['status'] );
		$this->assertNull( $response->get_data()['expires_at'] );
		$this->assertSame( 'sent', $response->get_data()['notification']['status'] );
		$this->assertCount( 1, $this->sent_mail );
	}

	public function test_existing_approved_old_diploma_becomes_valid_without_reapproval(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Existing Social Hygiene Diploma Holder' ],
			[
				'datum-iva'    => '2005-11-22',
				'iva-approved' => 1,
			]
		);

		$this->assertSame( 'valid', IvaStatus::status( $person_id ) );
		$this->assertNull( IvaStatus::expires_at( $person_id ) );
	}

	public function test_valid_iva_without_email_is_still_approved(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'IVA Member Without Email' ],
			[ 'datum-iva' => current_time( 'Y-m-d' ) ]
		);

		$response = $this->approve( $person_id, true );

		$this->assertSame( 'valid', $response->get_data()['status'] );
		$this->assertSame( 'no_email', $response->get_data()['notification']['status'] );
		$this->assertCount( 0, $this->sent_mail );
	}

	private function approve( int $person_id, bool $approve ) {
		$request = new WP_REST_Request( 'POST', '/rondo/v1/iva/' . $person_id . '/approve' );
		$request->set_param( 'person_id', $person_id );
		$request->set_param( 'approve', $approve );

		return ( new Volunteer() )->approve_iva( $request );
	}
}
