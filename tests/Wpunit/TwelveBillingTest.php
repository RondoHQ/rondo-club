<?php
namespace Tests\Wpunit;

use Rondo\Twelve\BusinessclubInvoicing;
use Rondo\Twelve\ReportRepository;
use Tests\Support\RondoTestCase;

class TwelveBillingTest extends RondoTestCase {
	private function report( string $date, string $message ): int {
		$data                 = json_decode( file_get_contents( __DIR__ . '/../fixtures/twelve-report.json' ), true );
		$data['period_start'] = $date . ' 06:00';
		$data['period_end']   = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) ) . ' 06:00';
		foreach ( $data['omzet'] as &$row ) {
			if ( $row['label'] === 'Businessclub' ) {
				$row['bedrag'] = 109;
				$row['netto']  = 100;
				$row['laag']   = 9;
			}
		}
		return ( new ReportRepository() )->store( $data, $message, 'report.pdf', '%PDF test' );
	}

	public function test_draft_reserves_sent_consumes_and_new_days_remain(): void {
		$service = new BusinessclubInvoicing();
		$first   = $this->report( '2026-08-29', 'a' );
		$id      = $service->create_draft_invoice();
		$this->assertIsInt( $id );
		$this->assertSame( 109.0, $service->overview()['total'] );
		$this->assertSame( 0.0, $service->overview()['available'] );
		$this->assertInstanceOf( \WP_Error::class, $service->create_draft_invoice() );
		$this->report( '2026-09-29', 'b' );
		$this->assertSame( 218.0, $service->overview()['total'] );
		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'rondo_sent',
			]
			);
		$this->assertSame( 109.0, $service->overview()['total'] );
		$this->assertSame( $id, (int) get_post_meta( $first, BusinessclubInvoicing::BILLED, true ) );
		wp_delete_post( $id, true );
		$this->assertSame( 109.0, $service->overview()['available'] );
		$this->assertIsInt( $service->create_draft_invoice() );
	}

	public function test_deleted_draft_releases_and_lock_prevents_duplicates(): void {
		$service = new BusinessclubInvoicing();
		$this->report( '2026-09-29', 'a' );
		$id = $service->create_draft_invoice();
		wp_delete_post( $id, true );
		$this->assertSame( 109.0, $service->overview()['available'] );
		add_option( 'rondo_twelve_invoice_lock', 'other-request' );
		try {
			$error = $service->create_draft_invoice();
			$this->assertSame( 'twelve_invoice_busy', $error->get_error_code() );
		} finally {
			delete_option( 'rondo_twelve_invoice_lock' );
		}
	}

	public function test_capability_is_independent_of_finance(): void {
		$server = $this->bootRestControllers( [ \Rondo\REST\TwelveReports::class ] );
		$user   = self::factory()->user->create( [ 'role' => 'rondo_financieel' ] );
		wp_set_current_user( $user );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/twelve/billing' ) )->get_status() );
		get_userdata( $user )->add_cap( 'kassaomzet' );
		$this->assertSame( 200, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/twelve/billing' ) )->get_status() );
		get_userdata( $user )->add_cap( 'financieel', false );
		wp_set_current_user( 0 );
		wp_set_current_user( $user );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'POST', '/rondo/v1/twelve/billing' ) )->get_status() );
	}
	public function test_send_guard_preserves_draft_when_amounts_change_or_send_is_busy(): void {
		$service = new BusinessclubInvoicing();
		$this->report( '2026-09-29', 'a' );
		$id         = $service->create_draft_invoice();
		$controller = new \Rondo\REST\Invoices();
		$request    = new \WP_REST_Request( 'POST' );
		$request->set_param( 'id', $id );
		\Rondo\Fields\Fields::update_for_post( $id, 'total_amount', 1 );
		$this->assertSame( 'twelve_invoice_changed', $controller->send_invoice( $request )->get_error_code() );
		$this->assertSame( 109.0, $service->overview()['total'] );
		\Rondo\Fields\Fields::update_for_post( $id, 'total_amount', 109 );
		$key = 'rondo_twelve_send_lock_' . $id;
		add_option( $key, 'another-request' );
		try {
			$this->assertSame( 'twelve_send_busy', $controller->send_invoice( $request )->get_error_code() );
			$this->assertSame( 'rondo_draft', get_post_status( $id ) );
		} finally {
			delete_option( $key );
		}
	}

	public function test_restored_old_draft_cannot_bill_reallocated_reports(): void {
		$service = new BusinessclubInvoicing();
		$this->report( '2026-09-29', 'a' );
		$old = $service->create_draft_invoice();
		wp_trash_post( $old );
		$new = $service->create_draft_invoice();
		$this->assertIsInt( $new );
		wp_update_post(
			[
				'ID'          => $old,
				'post_status' => 'rondo_draft',
			]
			);
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'id', $old );
		$this->assertSame( 'twelve_invoice_allocation', ( new \Rondo\REST\Invoices() )->send_invoice( $request )->get_error_code() );
	}
}
