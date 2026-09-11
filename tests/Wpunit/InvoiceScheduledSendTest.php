<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Finance\InvoiceScheduledSendScheduler;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;

/** Protect scheduled invoices from premature manual and bulk sends. */
class InvoiceScheduledSendTest extends RondoTestCase {

	private array $mail        = [];
	private array $invoice_ids = [];

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->bootRestControllers( [ Invoices::class ] );
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		add_filter(
			'pre_wp_mail',
			function ( $result, $mail ) {
				$this->mail[] = $mail;
				return true;
			},
			10,
			2
		);
	}

	protected function tear_down(): void {
		foreach ( $this->invoice_ids as $id ) {
			$pdf = Fields::get_for_post( $id, 'pdf_path' );
			if ( $pdf ) {
				wp_delete_file( wp_upload_dir()['basedir'] . '/' . $pdf );
			}
		}
		parent::tear_down();
	}

	private function invoice( string $scheduled = '' ): int {
		$id                  = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_draft',
			]
		);
		$this->invoice_ids[] = $id;
		// Membership fixtures avoid any external payment-provider calls.
		Fields::update_for_post( $id, 'invoice_type', 'membership' );
		Fields::update_for_post( $id, 'invoice_number', 'SCHEDULE-TEST-' . $id );
		Fields::update_for_post( $id, 'status', 'draft' );
		Fields::update_for_post( $id, 'total_amount', 10 );
		update_post_meta( $id, '_customer_name', 'Schedule test' );
		update_post_meta( $id, '_customer_email', 'schedule@example.com' );
		if ( $scheduled !== '' ) {
			update_post_meta( $id, '_scheduled_send_date', $scheduled );
			update_post_meta( $id, '_scheduled_send_by_user_id', get_current_user_id() );
		}
		return $id;
	}

	private function send( int $id, array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/invoices/' . $id . '/send' );
		$request->set_body_params( $params );
		return rest_do_request( $request );
	}

	public function test_future_send_is_rejected_without_side_effects(): void {
		$id       = $this->invoice( '20991001' );
		$response = $this->send( $id );
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'invoice_send_scheduled', $response->get_data()['code'] );
		$this->assertSame( 'rondo_draft', get_post_status( $id ) );
		$this->assertSame( '20991001', get_post_meta( $id, '_scheduled_send_date', true ) );
		$this->assertSame( get_current_user_id(), (int) get_post_meta( $id, '_scheduled_send_by_user_id', true ) );
		$this->assertEmpty( Fields::get_for_post( $id, 'sent_date' ) );
		$this->assertEmpty( Fields::get_for_post( $id, 'pdf_path' ) );
		$this->assertEmpty( Fields::get_for_post( $id, 'payment_link' ) );
		$this->assertCount( 0, $this->mail );
	}

	public function test_due_and_unscheduled_invoices_can_be_sent(): void {
		foreach ( [ '', current_time( 'Ymd' ), '20000101' ] as $scheduled ) {
			$id       = $this->invoice( $scheduled );
			$response = $this->send( $id );
			$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
			$this->assertSame( 'rondo_sent', get_post_status( $id ) );
			$this->assertFalse( $response->get_data()['scheduled_send_pending'] );
			$this->assertSame( '', get_post_meta( $id, '_scheduled_send_date', true ) );
		}
		$this->assertCount( 3, $this->mail );
	}

	public function test_cancelling_schedule_allows_explicit_immediate_send(): void {
		$id      = $this->invoice( '20991001' );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/invoices/' . $id . '/schedule' );
		$request->set_param( 'scheduled_send_date', '' );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( 200, $this->send( $id )->get_status() );
		$this->assertCount( 1, $this->mail );
	}

	public function test_sweeper_sends_only_due_invoices_and_does_not_repeat(): void {
		$future      = $this->invoice( '20991001' );
		$today       = $this->invoice( current_time( 'Ymd' ) );
		$overdue     = $this->invoice( '20000101' );
		$unscheduled = $this->invoice();
		$sweeper     = new InvoiceScheduledSendScheduler();
		$sweeper->run_sweep();
		$sweeper->run_sweep();
		$this->assertSame( 'rondo_draft', get_post_status( $future ) );
		$this->assertSame( '20991001', get_post_meta( $future, '_scheduled_send_date', true ) );
		$this->assertSame( 'rondo_draft', get_post_status( $unscheduled ) );
		$this->assertSame( 'rondo_sent', get_post_status( $today ) );
		$this->assertSame( 'rondo_sent', get_post_status( $overdue ) );
		$this->assertCount( 2, $this->mail );
	}

	public function test_list_and_detail_expose_the_site_timezone_schedule_guard(): void {
		update_option( 'timezone_string', 'Pacific/Kiritimati' );
		$today  = $this->invoice( current_time( 'Ymd' ) );
		$future = $this->invoice( current_datetime()->modify( '+1 day' )->format( 'Ymd' ) );
		foreach ( [
			$today  => false,
			$future => true,
		] as $id => $pending ) {
			$response = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/invoices/' . $id ) );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( $pending, $response->get_data()['scheduled_send_pending'] );
		}
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/invoices' ) );
		$this->assertSame( 200, $response->get_status() );
		$rows = array_column( $response->get_data(), null, 'id' );
		$this->assertFalse( $rows[ $today ]['scheduled_send_pending'] );
		$this->assertTrue( $rows[ $future ]['scheduled_send_pending'] );
	}

	public function test_preview_email_preserves_future_schedule(): void {
		$id       = $this->invoice( '20991001' );
		$response = $this->send( $id, [ 'recipient' => 'preview@example.com' ] );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertTrue( $response->get_data()['test_send'] );
		$this->assertSame( 'rondo_draft', get_post_status( $id ) );
		$this->assertSame( '20991001', get_post_meta( $id, '_scheduled_send_date', true ) );
		$this->assertCount( 1, $this->mail );
		$this->assertContains( 'preview@example.com', (array) $this->mail[0]['to'] );
	}
}
