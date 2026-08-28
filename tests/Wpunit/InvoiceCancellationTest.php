<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Finance\InstallmentScheduler;
use Rondo\Finance\InvoiceReminderScheduler;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Covers the complete server-side lifecycle of a cancelled invoice. */
class InvoiceCancellationTest extends RondoTestCase {

	private Invoices $controller;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new Invoices();
	}

	private function create_invoice( string $post_status = 'rondo_sent', string $status = 'sent' ): int {
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => $post_status,
				'post_title'  => '2026C999',
			]
		);

		Fields::update_for_post( $invoice_id, 'invoice_number', '2026C999' );
		Fields::update_for_post( $invoice_id, 'invoice_type', 'membership' );
		Fields::update_for_post( $invoice_id, 'status', $status );
		Fields::update_for_post( $invoice_id, 'sent_date', '20260101' );
		Fields::update_for_post( $invoice_id, 'payment_link', 'https://example.com/betaling/test' );

		return $invoice_id;
	}

	private function update_status( int $invoice_id, string $status ) {
		$request = new WP_REST_Request( 'PUT' );
		$request->set_param( 'id', $invoice_id );
		$request->set_param( 'status', $status );

		return $this->controller->update_invoice_status( $request );
	}

	public function test_cancelling_disables_payment_and_records_the_audit_trail(): void {
		$invoice_id = $this->create_invoice();
		update_post_meta( $invoice_id, '_mollie_payment_link_id', 'pl_test' );
		update_post_meta( $invoice_id, '_rabobank_payment_request_id', 'rq_test' );
		update_post_meta( $invoice_id, '_payment_token', 'keep-for-expired-message' );

		$response = $this->update_status( $invoice_id, 'cancelled' );

		$this->assertNotWPError( $response );
		$this->assertSame( 'rondo_cancelled', get_post_status( $invoice_id ) );
		$this->assertSame( 'cancelled', Fields::get_for_post( $invoice_id, 'status' ) );
		$this->assertSame( '', Fields::get_for_post( $invoice_id, 'payment_link' ) );
		$this->assertSame( '', get_post_meta( $invoice_id, '_mollie_payment_link_id', true ) );
		$this->assertSame( '', get_post_meta( $invoice_id, '_rabobank_payment_request_id', true ) );
		$this->assertNotSame( '', get_post_meta( $invoice_id, '_cancelled_at', true ) );
		$this->assertSame( get_current_user_id(), (int) get_post_meta( $invoice_id, '_cancelled_by', true ) );
		$this->assertSame( 'keep-for-expired-message', get_post_meta( $invoice_id, '_payment_token', true ) );
	}

	public function test_cancelled_invoice_cannot_get_a_new_payment_link(): void {
		$invoice_id = $this->create_invoice( 'rondo_cancelled', 'cancelled' );
		$request    = new WP_REST_Request( 'POST' );
		$request->set_param( 'id', $invoice_id );

		$result = $this->controller->regenerate_payment_link( $request );

		$this->assertWPError( $result );
		$this->assertSame( 'invoice_cancelled', $result->get_error_code() );
	}

	public function test_cancelled_invoice_is_excluded_from_both_reminder_sweeps(): void {
		$invoice_id = $this->create_invoice( 'rondo_cancelled', 'cancelled' );
		update_post_meta( $invoice_id, '_installment_plan', 'monthly_8' );
		update_post_meta( $invoice_id, '_installment_count', 1 );
		update_post_meta( $invoice_id, '_installment_1_due_date', '2026-01-01' );
		update_post_meta( $invoice_id, '_installment_1_status', 'sent' );

		( new \ReflectionMethod( InvoiceReminderScheduler::class, 'process_invoices' ) )->invoke( new InvoiceReminderScheduler() );
		( new \ReflectionMethod( InstallmentScheduler::class, 'process_invoices' ) )->invoke( new InstallmentScheduler() );

		$this->assertSame( '', get_post_meta( $invoice_id, '_invoice_reminder_1_sent_at', true ) );
		$this->assertSame( '', get_post_meta( $invoice_id, '_invoice_reminder_2_sent_at', true ) );
		$this->assertSame( '', get_post_meta( $invoice_id, '_installment_1_reminder_1_sent_at', true ) );
		$this->assertSame( '', get_post_meta( $invoice_id, '_installment_1_reminder_2_sent_at', true ) );
	}

	public function test_reactivation_restores_sent_status_and_clears_cancellation_audit(): void {
		$invoice_id = $this->create_invoice();
		$this->update_status( $invoice_id, 'cancelled' );

		$response = $this->update_status( $invoice_id, 'sent' );

		$this->assertNotWPError( $response );
		$this->assertSame( 'rondo_sent', get_post_status( $invoice_id ) );
		$this->assertSame( 'sent', Fields::get_for_post( $invoice_id, 'status' ) );
		$this->assertSame( '', get_post_meta( $invoice_id, '_cancelled_at', true ) );
		$this->assertSame( '', get_post_meta( $invoice_id, '_cancelled_by', true ) );
	}

	public function test_paid_invoice_must_be_marked_unpaid_before_cancellation(): void {
		$invoice_id = $this->create_invoice( 'rondo_paid', 'paid' );

		$result = $this->update_status( $invoice_id, 'cancelled' );

		$this->assertWPError( $result );
		$this->assertSame( 'invoice_paid', $result->get_error_code() );
		$this->assertSame( 'rondo_paid', get_post_status( $invoice_id ) );
	}
}
