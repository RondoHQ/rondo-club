<?php

namespace Tests\Wpunit;

use Rondo\Config\FinanceConfig;
use Rondo\Fields\Fields;
use Rondo\Finance\FinanceServices;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Covers automatic administration costs on new discipline invoices. */
class InvoiceAdministrationFeeTest extends RondoTestCase {

	private Invoices $controller;
	private int $person_id;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->person_id  = $this->createPerson( [ 'post_title' => 'Test member' ] );
		$this->controller = new Invoices();

		update_option( 'rondo_finance_active_payment_provider', 'rabobank' );
		update_option( 'rondo_finance_admin_fee', 0.32 );
		FinanceServices::reset();
	}

	protected function tear_down(): void {
		delete_option( 'rondo_finance_admin_fee' );
		delete_option( 'rondo_finance_active_payment_provider' );
		FinanceServices::reset();
		parent::tear_down();
	}

	private function create_invoice( string $invoice_type, array $line_items, string $invoice_kind = 'normal' ) {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'person_id', $this->person_id );
		$request->set_param( 'invoice_type', $invoice_type );
		$request->set_param( 'invoice_kind', $invoice_kind );
		$request->set_param( 'line_items', $line_items );

		return $this->controller->create_invoice( $request );
	}

	public function test_new_discipline_invoice_gets_configured_admin_fee(): void {
		$this->assertSame( 0.32, ( new FinanceConfig() )->get_admin_fee() );

		$response = $this->create_invoice(
			'discipline',
			[
				[
					'description'        => 'AWC O17-1 - De Treffers O17-1',
					'amount'             => 9.30,
					'discipline_case_id' => 123,
				],
			]
		);

		$this->assertNotWPError( $response );
		$data       = $response->get_data();
		$invoice_id = (int) $data['id'];

		$this->assertSame( 9.62, (float) Fields::get_for_post( $invoice_id, 'total_amount' ) );
		$this->assertSame(
			[
				[
					'amount'          => 9.30,
					'description'     => 'AWC O17-1 - De Treffers O17-1',
					'discipline_case' => 123,
				],
				[
					'amount'          => 0.32,
					'description'     => 'Administratiekosten',
					'discipline_case' => null,
				],
			],
			Fields::get_for_post( $invoice_id, 'line_items' )
		);
	}

	public function test_manual_and_credit_invoices_do_not_get_admin_fee(): void {
		foreach ( [ [ 'manual', 'normal' ], [ 'discipline', 'credit' ] ] as [ $invoice_type, $invoice_kind ] ) {
			$response = $this->create_invoice(
				$invoice_type,
				[
					[
						'description' => 'Regel',
						'amount'      => 10,
					],
				],
				$invoice_kind
			);

			$this->assertNotWPError( $response );
			$data       = $response->get_data();
			$invoice_id = (int) $data['id'];

			$this->assertSame( 10.0, (float) Fields::get_for_post( $invoice_id, 'total_amount' ) );
			$this->assertCount( 1, Fields::get_for_post( $invoice_id, 'line_items' ) );
		}
	}

	public function test_copied_discipline_admin_fee_is_not_duplicated(): void {
		$response = $this->create_invoice(
			'discipline',
			[
				[
					'description'        => 'Boete',
					'amount'             => 9.30,
					'discipline_case_id' => 123,
				],
				[
					'description' => 'Administratiekosten',
					'amount'      => 0.32,
				],
			]
		);

		$this->assertNotWPError( $response );
		$data       = $response->get_data();
		$invoice_id = (int) $data['id'];

		$this->assertSame( 9.62, (float) Fields::get_for_post( $invoice_id, 'total_amount' ) );
		$this->assertCount( 2, Fields::get_for_post( $invoice_id, 'line_items' ) );
	}
}
