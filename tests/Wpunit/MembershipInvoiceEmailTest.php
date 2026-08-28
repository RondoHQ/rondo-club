<?php

namespace Tests\Wpunit;

use Rondo\Config\FinanceConfig;
use Rondo\Fields\Fields;
use Rondo\Finance\InvoiceEmailSender;
use Rondo\REST\Lettermint;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Covers the configurable subject and wrapper used by contribution emails. */
class MembershipInvoiceEmailTest extends RondoTestCase {

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

		delete_option( FinanceConfig::OPTION_MEMBERSHIP_EMAIL_SUBJECT );
		update_option( FinanceConfig::OPTION_ORG_NAME, 'AWC' );
		update_option( FinanceConfig::OPTION_CONTACT_EMAIL, 'financien@example.com' );
	}

	protected function tear_down(): void {
		delete_option( FinanceConfig::OPTION_MEMBERSHIP_EMAIL_SUBJECT );
		delete_option( FinanceConfig::OPTION_ORG_NAME );
		delete_option( FinanceConfig::OPTION_CONTACT_EMAIL );
		parent::tear_down();
	}

	public function test_default_membership_subject_has_no_invoice_number(): void {
		$this->assertSame( 'Contributie van {organisatie_naam}', ( new FinanceConfig() )->get_membership_email_subject() );
	}

	public function test_membership_subject_is_fully_configurable_and_has_no_support_line(): void {
		update_option( FinanceConfig::OPTION_MEMBERSHIP_EMAIL_SUBJECT, 'Contributie voor {voornaam} van {organisatie_naam}' );

		$person_id  = $this->createPerson(
			[ 'post_title' => 'Jan Jansen' ],
			[
				'first_name' => 'Jan',
				'email_1'    => 'jan@example.com',
			]
		);
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_draft',
				'post_title'  => 'C-2025-0042',
			]
		);

		Fields::update_for_post( $invoice_id, 'invoice_number', 'C-2025-0042' );
		Fields::update_for_post( $invoice_id, 'invoice_type', 'membership' );
		Fields::update_for_post( $invoice_id, 'person', $person_id );
		Fields::update_for_post( $invoice_id, 'total_amount', 230 );
		Fields::update_for_post( $invoice_id, 'payment_link', 'https://example.com/betaling/test' );

		$result = InvoiceEmailSender::send( $invoice_id );

		$this->assertTrue( $result );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( 'Contributie voor Jan van AWC', $this->sent_mail[0]['subject'] );
		$this->assertStringNotContainsString( 'C-2025-0042', $this->sent_mail[0]['subject'] );
		$this->assertStringNotContainsString( 'Vragen? Reageer op deze mail', $this->sent_mail[0]['message'] );
	}

	public function test_membership_test_email_uses_the_configured_subject_and_wrapper(): void {
		update_option( FinanceConfig::OPTION_MEMBERSHIP_EMAIL_SUBJECT, 'Jouw contributie bij {organisatie_naam}' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'template_type', 'membership' );
		$request->set_param( 'recipient', 'test@example.com' );

		$response = ( new Lettermint() )->send_finance_test_email( $request );

		$this->assertNotWPError( $response );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( '[TEST] Jouw contributie bij AWC', $this->sent_mail[0]['subject'] );
		$this->assertStringNotContainsString( 'Vragen? Reageer op deze mail', $this->sent_mail[0]['message'] );
	}
}
