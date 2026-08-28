<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/** Covers the canonical full-payment timestamp in invoice REST responses. */
class InvoicePaidAtTest extends RondoTestCase {
	private WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->server = $this->bootRestControllers( [ Invoices::class ] );
	}

	private function create_paid_invoice(): int {
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_paid',
				'post_title'  => 'Betaalde factuur',
			]
		);

		Fields::update_for_post( $invoice_id, 'invoice_number', 'C2026-999' );
		Fields::update_for_post( $invoice_id, 'status', 'paid' );

		return $invoice_id;
	}

	public function test_list_and_detail_expose_direct_payment_time(): void {
		$invoice_id = $this->create_paid_invoice();
		$paid_at    = current_datetime()->setTime( 12, 34, 56 )->format( DATE_ATOM );
		update_post_meta( $invoice_id, '_mollie_paid_at', $paid_at );

		$list_response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices' ) );
		$list_item     = array_values(
			array_filter(
				$list_response->get_data(),
				static fn( array $invoice ): bool => $invoice['id'] === $invoice_id
			)
		)[0];

		$detail_response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices/' . $invoice_id ) );

		$this->assertSame( 200, $list_response->get_status() );
		$this->assertSame( $paid_at, $list_item['paid_at'] );
		$this->assertSame( $paid_at, $detail_response->get_data()['paid_at'] );
	}

	public function test_completed_installments_use_the_last_payment_time(): void {
		$invoice_id = $this->create_paid_invoice();
		$first      = current_datetime()->modify( '-1 month' )->setTime( 9, 15 )->format( DATE_ATOM );
		$last       = current_datetime()->setTime( 16, 45 )->format( DATE_ATOM );

		update_post_meta( $invoice_id, '_installment_plan', 'quarterly_3' );
		update_post_meta( $invoice_id, '_installment_count', 2 );
		update_post_meta( $invoice_id, '_installment_1_status', 'betaald' );
		update_post_meta( $invoice_id, '_installment_1_mollie_paid_at', $first );
		update_post_meta( $invoice_id, '_installment_2_status', 'betaald' );
		update_post_meta( $invoice_id, '_installment_2_mollie_paid_at', $last );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices/' . $invoice_id ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $last, $data['paid_at'] );
		$this->assertSame( $first, $data['installments'][0]['paid_at'] );
		$this->assertSame( $last, $data['installments'][1]['paid_at'] );
	}

	public function test_manual_payment_time_takes_precedence(): void {
		$invoice_id = $this->create_paid_invoice();
		$manual     = current_datetime()->setTime( 11, 22, 33 )->format( 'Y-m-d H:i:s' );

		update_post_meta( $invoice_id, '_mollie_paid_at', current_datetime()->modify( '-1 day' )->format( DATE_ATOM ) );
		update_post_meta( $invoice_id, '_manually_marked_paid_at', $manual );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices/' . $invoice_id ) );
		$paid_at  = $response->get_data()['paid_at'];

		$this->assertSame( $manual, ( new \DateTimeImmutable( $paid_at ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) );
	}
}
