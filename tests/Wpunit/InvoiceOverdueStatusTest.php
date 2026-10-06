<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Finance\InstallmentScheduler;
use Rondo\Finance\InvoiceOverdueStatus;
use Rondo\Finance\MollieWebhook;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/** Regression coverage for overdue invoices with active installment plans. */
class InvoiceOverdueStatusTest extends RondoTestCase {
	private WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->server = $this->bootRestControllers( [ Invoices::class ] );
	}

	private function create_invoice( string $status = 'overdue', string $plan = 'monthly_8' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_' . $status,
				'post_title'  => '2026C222',
			]
		);
		Fields::update_for_post( $id, 'invoice_number', '2026C222' );
		Fields::update_for_post( $id, 'invoice_type', 'membership' );
		Fields::update_for_post( $id, 'status', $status );
		Fields::update_for_post( $id, 'due_date', current_datetime()->modify( '-1 month' )->format( 'Y-m-d' ) );
		update_post_meta( $id, '_installment_plan', $plan );
		update_post_meta( $id, '_installment_count', 2 );
		update_post_meta( $id, '_installment_1_status', 'betaald' );
		update_post_meta( $id, '_installment_1_due_date', current_datetime()->modify( '-1 week' )->format( 'Y-m-d' ) );
		update_post_meta( $id, '_installment_2_status', 'pending' );
		update_post_meta( $id, '_installment_2_due_date', current_datetime()->modify( '+1 week' )->format( 'Y-m-d' ) );
		return $id;
	}

	public function test_list_repairs_stale_status_before_filtering_and_stays_current(): void {
		$id      = $this->create_invoice();
		$request = new WP_REST_Request( 'GET', '/rondo/v1/invoices' );
		$request->set_param( 'status', 'sent' );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( $id, array_column( $response->get_data(), 'id' ) );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );
		$this->assertSame( 'sent', Fields::get_for_post( $id, 'status' ) );
		$this->server->dispatch( $request );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );
		$this->assertSame( 'betaald', get_post_meta( $id, '_installment_1_status', true ) );
	}

	public function test_detail_repairs_quarterly_plan_without_a_list_request(): void {
		$id       = $this->create_invoice( 'overdue', 'quarterly_3' );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices/' . $id ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'sent', $response->get_data()['status'] );
		$this->assertSame( current_datetime()->modify( '+1 week' )->format( 'Ymd' ), $response->get_data()['due_date'] );
		$this->assertSame( current_datetime()->modify( '-1 month' )->format( 'Ymd' ), get_post_meta( $id, 'due_date', true ) );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );
	}

	public function test_unpaid_installment_is_overdue_only_after_its_due_date(): void {
		$id = $this->create_invoice( 'sent' );
		update_post_meta( $id, '_installment_2_due_date', current_time( 'Y-m-d' ) );
		InvoiceOverdueStatus::refresh( $id );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );
		update_post_meta( $id, '_installment_2_due_date', current_datetime()->modify( '-1 day' )->format( 'Y-m-d' ) );
		InvoiceOverdueStatus::refresh( $id );
		$this->assertSame( 'rondo_overdue', get_post_status( $id ) );
		$this->assertSame( 'overdue', Fields::get_for_post( $id, 'status' ) );
	}

	public function test_payment_clears_overdue_only_when_no_other_installment_is_late(): void {
		$id = $this->create_invoice();
		update_post_meta( $id, '_installment_1_status', 'sent' );
		update_post_meta( $id, '_installment_2_mollie_payment_id', 'pl_existing' );
		$payment_link = new class() {
			public function payments(): array {
				return [];
			}
		};
		$handler      = new \ReflectionMethod( MollieWebhook::class, 'handle_installment_paid' );
		$handler->invoke( new MollieWebhook(), $id, 1, 'pl_first', $payment_link );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );

		$other = $this->create_invoice();
		update_post_meta( $other, '_installment_1_status', 'sent' );
		update_post_meta( $other, '_installment_2_due_date', current_datetime()->modify( '-1 day' )->format( 'Y-m-d' ) );
		update_post_meta( $other, '_installment_2_mollie_payment_id', 'pl_existing' );
		$handler->invoke( new MollieWebhook(), $other, 1, 'pl_first', $payment_link );
		$this->assertSame( 'rondo_overdue', get_post_status( $other ) );
	}

	public function test_duplicate_payment_notification_repairs_a_stale_status(): void {
		$id      = $this->create_invoice();
		$handler = new \ReflectionMethod( MollieWebhook::class, 'handle_installment_paid' );
		$handler->invoke( new MollieWebhook(), $id, 1, 'pl_first', null );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );
	}

	public function test_daily_scheduler_includes_overdue_plans(): void {
		$id      = $this->create_invoice();
		$process = new \ReflectionMethod( InstallmentScheduler::class, 'process_invoices' );
		$process->invoke( new InstallmentScheduler() );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );
		$this->assertSame( 'pending', get_post_meta( $id, '_installment_2_status', true ) );
	}

	public function test_incomplete_or_invalid_schedules_do_not_clear_overdue(): void {
		foreach ( [ '', '2026-02-30' ] as $due_date ) {
			$id = $this->create_invoice();
			update_post_meta( $id, '_installment_2_due_date', $due_date );
			InvoiceOverdueStatus::refresh( $id );
			$this->assertSame( 'rondo_overdue', get_post_status( $id ) );
		}
		$id = $this->create_invoice();
		delete_post_meta( $id, '_installment_count' );
		InvoiceOverdueStatus::refresh( $id );
		$this->assertSame( 'rondo_overdue', get_post_status( $id ) );
	}

	public function test_full_and_unselected_plans_keep_original_invoice_due_date(): void {
		foreach ( [ 'full', '' ] as $plan ) {
			$id = $this->create_invoice( 'sent', $plan );
			InvoiceOverdueStatus::refresh( $id );
			$this->assertSame( 'rondo_overdue', get_post_status( $id ) );
		}
	}

	public function test_paid_cancelled_draft_and_credit_invoices_are_unchanged(): void {
		foreach ( [ 'paid', 'cancelled', 'draft' ] as $status ) {
			$id = $this->create_invoice( $status );
			InvoiceOverdueStatus::refresh( $id );
			$this->assertSame( 'rondo_' . $status, get_post_status( $id ) );
		}
		$id = $this->create_invoice( 'sent', 'full' );
		update_post_meta( $id, '_invoice_kind', 'credit' );
		InvoiceOverdueStatus::refresh( $id );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );
	}
}
