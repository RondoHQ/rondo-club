<?php

namespace Tests\Wpunit;

use Rondo\Config\FinanceConfig;
use Rondo\Fields\Fields;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;

/** Covers payment terms when scheduling and rescheduling draft invoices. */
class InvoiceScheduledDueDateTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->bootRestControllers( [ Invoices::class ] );
		update_option( FinanceConfig::OPTION_PAYMENT_TERM_DAYS, 14 );
		update_option( 'timezone_string', 'Europe/Amsterdam' );
	}

	private function create_invoice(): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_draft',
			]
		);
		Fields::update_for_post( $id, 'invoice_type', 'manual' );
		Fields::update_for_post( $id, 'status', 'draft' );
		Fields::update_for_post( $id, 'due_date', '20991201' );
		return $id;
	}

	private function schedule( int $id, string $date ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/invoices/' . $id . '/schedule' );
		$request->set_param( 'scheduled_send_date', $date );
		return rest_do_request( $request );
	}

	public function test_scheduling_updates_due_date_and_invalidates_the_old_pdf(): void {
		$id = $this->create_invoice();
		Fields::update_for_post( $id, 'pdf_path', 'invoices/missing-scheduled-test.pdf' );

		$response = $this->schedule( $id, '2099-12-21' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '21000104', $response->get_data()['due_date'] );
		$this->assertSame( '21000104', Fields::get_for_post( $id, 'due_date' ) );
		$this->assertEmpty( $response->get_data()['pdf_path'] );
		$this->assertSame( 'rondo_draft', get_post_status( $id ) );
	}

	public function test_rescheduling_uses_the_current_configured_term(): void {
		$id = $this->create_invoice();
		$this->schedule( $id, '20991221' );
		update_option( FinanceConfig::OPTION_PAYMENT_TERM_DAYS, 21 );

		$response = $this->schedule( $id, '20990320' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '20990410', $response->get_data()['due_date'] );
		$this->assertSame( '20990320', $response->get_data()['scheduled_send_date'] );
	}

	public function test_clearing_the_schedule_preserves_the_due_date(): void {
		$id = $this->create_invoice();
		$this->schedule( $id, '20991221' );
		$response = $this->schedule( $id, '' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $response->get_data()['scheduled_send_date'] );
		$this->assertSame( '21000104', $response->get_data()['due_date'] );
	}

	public function test_invalid_dates_and_sent_invoices_do_not_change_the_due_date(): void {
		$id = $this->create_invoice();
		foreach ( [ '2099-02-30', '2000-01-01' ] as $date ) {
			$this->assertSame( 400, $this->schedule( $id, $date )->get_status() );
			$this->assertSame( '20991201', Fields::get_for_post( $id, 'due_date' ) );
		}

		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'rondo_sent',
			]
			);
		$this->assertSame( 400, $this->schedule( $id, '20991221' )->get_status() );
		$this->assertSame( '20991201', Fields::get_for_post( $id, 'due_date' ) );
	}

	public function test_draft_creation_and_editing_move_due_date_only_when_schedule_changes(): void {
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/invoices' );
		$payload = [
			'invoice_type'           => 'manual',
			'customer_name'          => 'Schedule test',
			'customer_address'       => 'Teststraat 1',
			'line_items'             => [
				[
					'description' => 'Test',
					'amount'      => 10,
				],
			],
			'payment_terms_due_date' => '20991201',
			'scheduled_send_date'    => '20991221',
		];
		$request->set_body_params( $payload );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '21000104', $response->get_data()['due_date'] );

		$id                             = $response->get_data()['id'];
		$request                        = new \WP_REST_Request( 'POST', '/rondo/v1/invoices/' . $id . '/draft-details' );
		$payload['scheduled_send_date'] = '20991121';
		$request->set_body_params( $payload );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '20991205', $response->get_data()['due_date'] );

		// An explicit due-date edit remains possible without changing the send date.
		$payload['payment_terms_due_date'] = '20991210';
		$request->set_body_params( $payload );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '20991210', $response->get_data()['due_date'] );
	}
}
