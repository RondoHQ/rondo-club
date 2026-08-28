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
		update_post_meta( $quarterly, '_installment_count', 3 );
		update_post_meta( $quarterly, '_installment_1_status', 'betaald' );
		update_post_meta( $quarterly, '_installment_1_amount', 40.0 );
		update_post_meta( $quarterly, '_installment_1_admin_fee', 0.5 );
		update_post_meta( $quarterly, '_installment_2_status', 'pending' );
		update_post_meta( $quarterly, '_installment_3_status', 'pending' );

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

		$ignored_draft = $this->create_invoice( 60.0, $this->now->format( 'Ymd' ), 'rondo_draft' );
		Fields::update_for_post( $ignored_draft, 'invoice_type', 'membership' );
		update_post_meta( $ignored_draft, '_invoice_season', SeasonKey::current( $this->now->format( 'Y-m-d' ) ) );

		$ignored_cancelled = $this->create_invoice( 60.0, $this->now->format( 'Ymd' ), 'rondo_cancelled' );
		Fields::update_for_post( $ignored_cancelled, 'invoice_type', 'membership' );
		update_post_meta( $ignored_cancelled, '_invoice_season', SeasonKey::current( $this->now->format( 'Y-m-d' ) ) );

		$ignored_credit = $this->create_invoice( -60.0, $this->now->format( 'Ymd' ) );
		Fields::update_for_post( $ignored_credit, 'invoice_type', 'membership' );
		update_post_meta( $ignored_credit, '_invoice_season', SeasonKey::current( $this->now->format( 'Y-m-d' ) ) );
		update_post_meta( $ignored_credit, '_invoice_kind', 'credit' );

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
		$this->assertSame(
			[
				'season'       => SeasonKey::current( $this->now->format( 'Y-m-d' ) ),
				'paid'         => 1,
				'installments' => 2,
				'unpaid'       => 0,
				'total'        => 3,
			],
			$data['membership_payment_status']
		);
		$this->assertSame(
			[
				'season'      => SeasonKey::current( $this->now->format( 'Y-m-d' ) ),
				'collected'   => 200.0,
				'outstanding' => 200.0,
				'total'       => 400.0,
			],
			$data['membership_amount_status']
		);
		$this->assertCount( 30, $data['daily_income'] );
		$this->assertCount( 12, $data['monthly_income'] );
		$this->assertSame( $this->now->format( 'Y-m-d' ), $data['daily_income'][29]['date'] );
		$this->assertSame( $this->now->format( 'Y-m' ), $data['monthly_income'][11]['month'] );
	}

	public function test_statistics_can_be_filtered_by_invoice_type(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$membership = $this->create_invoice( 125.0, $this->now->modify( '-6 days' )->format( 'Ymd' ) );
		Fields::update_for_post( $membership, 'invoice_type', 'membership' );
		update_post_meta( $membership, '_mollie_paid_at', $this->now->modify( '-2 days' )->format( DATE_ATOM ) );

		$discipline = $this->create_invoice( 75.0, $this->now->modify( '-5 days' )->format( 'Ymd' ) );
		Fields::update_for_post( $discipline, 'invoice_type', 'discipline' );
		update_post_meta( $discipline, '_mollie_paid_at', $this->now->modify( '-1 day' )->format( DATE_ATOM ) );

		$request = new WP_REST_Request( 'GET', '/rondo/v1/invoices/statistics' );
		$request->set_param( 'invoice_type', 'membership' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'membership', $data['invoice_type'] );
		$this->assertSame( 125.0, $data['week']['received_amount'] );
		$this->assertSame( 1, $data['week']['payment_count'] );
		$this->assertSame( 125.0, array_sum( array_column( $data['daily_income'], 'amount' ) ) );
	}

	public function test_statistics_require_financial_read_access(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'geen_financien' ] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices/statistics' ) );

		$this->assertSame( 403, $response->get_status() );
	}
}
