<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Covers manual discount corrections on sent membership invoices. */
class InvoiceMembershipDiscountTest extends RondoTestCase {

	private Invoices $controller;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new Invoices();
	}

	private function create_invoice(): int {
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_sent',
				'post_title'  => '2026C999',
			]
		);

		Fields::update_for_post( $invoice_id, 'invoice_number', '2026C999' );
		Fields::update_for_post( $invoice_id, 'invoice_type', 'membership' );
		Fields::update_for_post( $invoice_id, 'status', 'sent' );
		Fields::update_for_post(
			$invoice_id,
			'line_items',
			[
				[
					'description'     => 'Contributie 2026-2027 - Mini\'s',
					'amount'          => 134,
					'discipline_case' => null,
				],
			]
		);
		Fields::update_for_post( $invoice_id, 'total_amount', 134 );

		return $invoice_id;
	}

	private function update_discount( int $invoice_id, float $family_discount ) {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'id', $invoice_id );
		$request->set_param( 'family_discount_percent', $family_discount );
		$request->set_param( 'entry_discount_percent', 0 );

		return $this->controller->update_membership_discount( $request );
	}

	public function test_family_discount_update_recalculates_invoice_without_fatal_error(): void {
		$invoice_id = $this->create_invoice();

		$response = $this->update_discount( $invoice_id, 25 );

		$this->assertNotWPError( $response );
		$this->assertSame( 100.5, (float) Fields::get_for_post( $invoice_id, 'total_amount' ) );
		$this->assertSame(
			[
				[
					'amount'          => 134,
					'description'     => 'Contributie 2026-2027 - Mini\'s',
					'discipline_case' => null,
				],
				[
					'amount'          => -33.5,
					'description'     => 'Gezinskorting (25%)',
					'discipline_case' => null,
				],
			],
			Fields::get_for_post( $invoice_id, 'line_items' )
		);
	}

	public function test_discount_update_stays_blocked_after_an_installment_was_paid(): void {
		$invoice_id = $this->create_invoice();
		update_post_meta( $invoice_id, '_installment_count', 8 );
		update_post_meta( $invoice_id, '_installment_1_status', 'betaald' );

		$result = $this->update_discount( $invoice_id, 25 );

		$this->assertWPError( $result );
		$this->assertSame( 'installment_paid', $result->get_error_code() );
		$this->assertSame( 134.0, (float) Fields::get_for_post( $invoice_id, 'total_amount' ) );
	}
}
