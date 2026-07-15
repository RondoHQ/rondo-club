<?php

namespace Tests\Wpunit;

use Rondo\Collaboration\CommentTypes;
use Rondo\REST\Volunteer;
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
		$this->assertSame( 'Je IVA-certificaat is goedgekeurd', $this->sent_mail[0]['subject'] );
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

	public function test_expired_iva_does_not_send_eligibility_email(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Expired IVA Member' ],
			[
				'email_1'   => 'expired@example.com',
				'datum-iva' => '2010-01-01',
			]
		);

		$response = $this->approve( $person_id, true );

		$this->assertSame( 'expired', $response->get_data()['status'] );
		$this->assertSame( 'not_applicable', $response->get_data()['notification']['status'] );
		$this->assertCount( 0, $this->sent_mail );
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
