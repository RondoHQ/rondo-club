<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Fees\SeasonKey;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/** Covers receipt totals and paid-invoice lead time on the finance dashboard. */
class InvoiceStatisticsTest extends RondoTestCase {
	private WP_REST_Server $server;
	private \DateTimeImmutable $now;

	protected function set_up(): void {
		parent::set_up();
		$this->server = $this->bootRestControllers( [ Invoices::class ] );
		$this->now    = current_datetime();
	}

	private function create_invoice( float $amount, string $sent_date, string $status = 'rondo_paid' ): int {
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => $status,
				'post_title'  => 'Statistiekfactuur',
			]
		);

		Fields::update_for_post( $invoice_id, 'status', str_replace( 'rondo_', '', $status ) );
		Fields::update_for_post( $invoice_id, 'total_amount', $amount );
		Fields::update_for_post( $invoice_id, 'sent_date', $sent_date );

		return $invoice_id;
	}

	public function test_statistics_count_recent_receipts_and_paid_invoice_lead_time(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$direct = $this->create_invoice( 100.0, $this->now->modify( '-10 days' )->format( 'Ymd' ) );
		update_post_meta( $direct, '_mollie_paid_at', $this->now->modify( '-2 days' )->format( DATE_ATOM ) );

		$manual = $this->create_invoice( 40.0, $this->now->modify( '-15 days' )->format( 'Ymd' ) );
		update_post_meta( $manual, '_manually_marked_paid_at', $this->now->modify( '-1 day' )->format( 'Y-m-d H:i:s' ) );

		$older = $this->create_invoice( 70.0, $this->now->modify( '-20 days' )->format( 'Ymd' ) );
		update_post_meta( $older, '_mollie_paid_at', $this->now->modify( '-10 days' )->format( DATE_ATOM ) );

		$partial = $this->create_invoice( 100.0, $this->now->modify( '-12 days' )->format( 'Ymd' ), 'rondo_sent' );
		update_post_meta( $partial, '_installment_count', 2 );
		update_post_meta( $partial, '_installment_1_status', 'betaald' );
		update_post_meta( $partial, '_installment_1_amount', 50.0 );
		update_post_meta( $partial, '_installment_1_admin_fee', 0.5 );
		update_post_meta( $partial, '_installment_1_mollie_paid_at', $this->now->modify( '-3 days' )->format( DATE_ATOM ) );
		update_post_meta( $partial, '_installment_2_status', 'pending' );

		$outside = $this->create_invoice( 999.0, $this->now->modify( '-50 days' )->format( 'Ymd' ) );
		update_post_meta( $outside, '_mollie_paid_at', $this->now->modify( '-40 days' )->format( DATE_ATOM ) );

		$quarterly_person = $this->createPerson();
		$quarterly        = $this->create_invoice( 120.0, $this->now->modify( '-5 days' )->format( 'Ymd' ), 'rondo_sent' );
		Fields::update_for_post( $quarterly, 'invoice_type', 'membership' );
		Fields::update_for_post( $quarterly, 'person', $quarterly_person );
		update_post_meta( $quarterly, '_invoice_season', SeasonKey::current( $this->now->format( 'Y-m-d' ) ) );
		update_post_meta( $quarterly, '_installment_plan', 'quarterly_3' );

		$duplicate_quarterly = $this->create_invoice( 120.0, $this->now->modify( '-4 days' )->format( 'Ymd' ), 'rondo_overdue' );
		Fields::update_for_post( $duplicate_quarterly, 'invoice_type', 'membership' );
		Fields::update_for_post( $duplicate_quarterly, 'person', $quarterly_person );
		update_post_meta( $duplicate_quarterly, '_invoice_season', SeasonKey::current( $this->now->format( 'Y-m-d' ) ) );
		update_post_meta( $duplicate_quarterly, '_installment_plan', 'quarterly_3' );

		$monthly_person = $this->createPerson();
		$monthly        = $this->create_invoice( 160.0, $this->now->modify( '-6 days' )->format( 'Ymd' ), 'rondo_paid' );
		Fields::update_for_post( $monthly, 'invoice_type', 'membership' );
		Fields::update_for_post( $monthly, 'person', $monthly_person );
		update_post_meta( $monthly, '_invoice_season', SeasonKey::current( $this->now->format( 'Y-m-d' ) ) );
		update_post_meta( $monthly, '_installment_plan', 'monthly_8' );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices/statistics' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 190.5, $data['week']['received_amount'] );
		$this->assertSame( 3, $data['week']['payment_count'] );
		$this->assertSame( 260.5, $data['month']['received_amount'] );
		$this->assertSame( 4, $data['month']['payment_count'] );
		$this->assertSame( 10.7, $data['average_days_open'] );
		$this->assertSame( 3, $data['paid_invoice_count'] );
		$this->assertSame( 2, $data['installment_plans']['total_people'] );
		$this->assertSame( 1, $data['installment_plans']['quarterly_3'] );
		$this->assertSame( 1, $data['installment_plans']['monthly_8'] );
	}

	public function test_statistics_require_financial_read_access(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'geen_financien' ] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices/statistics' ) );

		$this->assertSame( 403, $response->get_status() );
	}
}
