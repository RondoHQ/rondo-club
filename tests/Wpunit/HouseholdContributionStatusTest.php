<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\Finance\MembershipContributionSummary;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/** Contract tests for current-season contribution status on Mijn gegevens. */
class HouseholdContributionStatusTest extends RondoTestCase {

	public function test_household_exposes_sent_current_season_contribution_only(): void {
		$member  = $this->createPerson( [ 'post_title' => 'Contributielid' ] );
		$user_id = $this->createRondoUser( [ 'user_login' => 'household_contribution' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $member );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$current_season = SeasonKey::current( wp_date( 'Y-m-d' ) );
		$this->create_membership_invoice( $member, '1900-1901', 'rondo_paid', 'C1900-001', 150.00 );
		$this->create_membership_invoice( $member, $current_season, 'rondo_draft', 'C-DRAFT', 175.00 );
		$invoice_id = $this->create_membership_invoice( $member, $current_season, 'rondo_sent', 'C-CURRENT', 195.00 );
		Fields::update_for_post( $invoice_id, 'due_date', '20260915' );
		Fields::update_for_post( $invoice_id, 'payment_link', 'https://example.org/betaling/token' );

		$response     = ( new People() )->get_household();
		$contribution = $response->get_data()[0]['contribution'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'C-CURRENT', $contribution['invoice_number'] );
		$this->assertSame( $current_season, $contribution['season'] );
		$this->assertSame( 'sent', $contribution['status'] );
		$this->assertSame( 195.00, $contribution['total_amount'] );
		$this->assertSame( '2026-09-15', $contribution['due_date'] );
		$this->assertSame( 'https://example.org/betaling/token', $contribution['payment_url'] );
	}

	public function test_installment_summary_exposes_progress_and_only_current_payment_link(): void {
		$member     = $this->createPerson( [ 'post_title' => 'Termijnlid' ] );
		$invoice_id = $this->create_membership_invoice( $member, SeasonKey::current( wp_date( 'Y-m-d' ) ), 'rondo_sent', 'C-TERMIJN', 240.00 );
		update_post_meta( $invoice_id, '_installment_plan', 'quarterly_3' );
		update_post_meta( $invoice_id, '_installment_count', 3 );
		update_post_meta( $invoice_id, '_installment_1_status', 'betaald' );
		update_post_meta( $invoice_id, '_installment_1_amount', 80 );
		update_post_meta( $invoice_id, '_installment_1_payment_link', 'https://example.org/oude-link' );
		update_post_meta( $invoice_id, '_installment_2_status', 'sent' );
		update_post_meta( $invoice_id, '_installment_2_amount', 80 );
		update_post_meta( $invoice_id, '_installment_2_admin_fee', 1.50 );
		update_post_meta( $invoice_id, '_installment_2_due_date', '2026-10-01' );
		update_post_meta( $invoice_id, '_installment_2_payment_link', 'https://example.org/huidige-link' );
		update_post_meta( $invoice_id, '_installment_3_status', 'pending' );

		$summary = MembershipContributionSummary::for_people( [ $member ] )[ $member ];

		$this->assertSame( 3, $summary['installment_count'] );
		$this->assertSame( 1, $summary['paid_installments'] );
		$this->assertSame( 240.00, $summary['total_amount'] );
		$this->assertSame( 160.00, $summary['outstanding_amount'] );
		$this->assertSame( 2, $summary['next_installment']['number'] );
		$this->assertSame( 81.50, $summary['next_installment']['amount'] );
		$this->assertSame( '2026-10-01', $summary['next_installment']['due_date'] );
		$this->assertSame( 'https://example.org/huidige-link', $summary['payment_url'] );
		$this->assertStringNotContainsString( 'oude-link', $summary['payment_url'] );
	}

	public function test_paid_invoice_has_no_payment_destination(): void {
		$member     = $this->createPerson( [ 'post_title' => 'Betaald lid' ] );
		$invoice_id = $this->create_membership_invoice( $member, SeasonKey::current( wp_date( 'Y-m-d' ) ), 'rondo_paid', 'C-BETAALD', 264.00 );
		Fields::update_for_post( $invoice_id, 'payment_link', 'https://example.org/betaling/token' );

		$summary = MembershipContributionSummary::for_people( [ $member ] )[ $member ];

		$this->assertSame( 'paid', $summary['status'] );
		$this->assertSame( 0.0, $summary['outstanding_amount'] );
		$this->assertNull( $summary['payment_url'] );
	}

	public function test_finance_summary_includes_latest_draft_invoice(): void {
		$member = $this->createPerson( [ 'post_title' => 'Conceptlid' ] );
		$season = SeasonKey::current( wp_date( 'Y-m-d' ) );
		$this->create_membership_invoice( $member, $season, 'rondo_cancelled', 'C-VERVALLEN', 180.00 );
		$draft_id = $this->create_membership_invoice( $member, $season, 'rondo_draft', 'C-CONCEPT', 195.00 );

		$summary = MembershipContributionSummary::for_finance_people( [ $member ], $season )[ $member ];

		$this->assertSame( $draft_id, $summary['invoice_id'] );
		$this->assertSame( 'draft', $summary['status'] );
		$this->assertSame( 'C-CONCEPT', $summary['invoice_number'] );
		$this->assertNull( $summary['outstanding_amount'] );
	}

	private function create_membership_invoice( int $person_id, string $season, string $post_status, string $number, float $amount ): int {
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => $post_status,
				'post_title'  => $number,
			]
		);
		Fields::update_for_post( $invoice_id, 'invoice_number', $number );
		Fields::update_for_post( $invoice_id, 'person', $person_id );
		Fields::update_for_post( $invoice_id, 'status', substr( $post_status, 6 ) );
		Fields::update_for_post( $invoice_id, 'invoice_type', 'membership' );
		Fields::update_for_post( $invoice_id, 'total_amount', $amount );
		update_post_meta( $invoice_id, '_invoice_season', $season );

		return $invoice_id;
	}
}
