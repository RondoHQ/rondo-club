<?php

namespace Tests\Wpunit;

use Rondo\Fees\FeeServices;
use Rondo\Fields\Fields;
use Rondo\REST\Fees;
use Tests\Support\RondoTestCase;

/** Contract for the contribution fields consumed by the FreeScout sync. */
class FeeFreeScoutContractTest extends RondoTestCase {

	private const SEASON = '2026-2027';

	public function test_fee_list_exposes_rondo_invoice_balance_and_installment_progress(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$person_id  = $this->createPerson(
			[ 'post_title' => 'FreeScout contributielid' ],
			[
				'first_name' => 'Rondo',
				'last_name'  => 'Lid',
			]
		);
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_sent',
				'post_title'  => 'C2026-001',
			]
		);
		Fields::update_for_post( $invoice_id, 'invoice_number', 'C2026-001' );
		Fields::update_for_post( $invoice_id, 'person', $person_id );
		Fields::update_for_post( $invoice_id, 'status', 'sent' );
		Fields::update_for_post( $invoice_id, 'invoice_type', 'membership' );
		Fields::update_for_post( $invoice_id, 'total_amount', 240.0 );
		update_post_meta( $invoice_id, '_invoice_season', self::SEASON );
		update_post_meta( $invoice_id, '_installment_plan', 'quarterly_3' );
		update_post_meta( $invoice_id, '_installment_count', 3 );
		update_post_meta( $invoice_id, '_installment_1_status', 'betaald' );
		update_post_meta( $invoice_id, '_installment_1_amount', 80.0 );
		FeeServices::fee_cache()->save_fee_cache(
			$person_id,
			[
				'category'               => 'senioren',
				'base_fee'               => 240.0,
				'family_discount_amount' => 0.0,
				'final_fee'              => 240.0,
			],
			self::SEASON
		);
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'season', self::SEASON );
		$request->set_param( 'forecast', false );
		$data = ( new Fees() )->get_fee_list( $request )->get_data();
		$this->assertSame( 1, $data['total'] );
		$member = $data['members'][0];

		$this->assertSame( $invoice_id, $member['invoice_id'] );
		$this->assertSame( 'C2026-001', $member['invoice_number'] );
		$this->assertSame( 'sent', $member['invoice_status'] );
		$this->assertSame( 240.0, $member['invoice_total'] );
		$this->assertSame( 160.0, $member['invoice_outstanding'] );
		$this->assertSame( 'quarterly_3', $member['installment_plan'] );
		$this->assertSame( 3, $member['installment_count'] );
		$this->assertSame( 1, $member['paid_installments'] );
	}
}
