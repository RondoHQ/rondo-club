<?php
namespace Tests\Wpunit;

use Rondo\Twelve\BusinessclubInvoicing;
use Rondo\Twelve\ReportRepository;
use Rondo\Finance\FinanceServices;
use Rondo\Finance\MollieConfig;
use Tests\Support\RondoTestCase;

class TwelveBillingTest extends RondoTestCase {
	public function test_billing_endpoint_preserves_address_book_recipient_fields(): void {
		$this->configure_mollie_accounts();
		$this->report( '2026-09-29', 'address-book-recipient' );
		$server = $this->bootRestControllers( [ \Rondo\REST\TwelveReports::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request   = new \WP_REST_Request( 'POST', '/rondo/v1/twelve/billing' );
		$recipient = [
			'name'      => 'Businessclub Voorbeeld',
			'address'   => "Sportlaan 1\n1234 AB Voorbeeldstad",
			'email'     => 'facturen@example.org',
			'attention' => 'Penningmeester',
			'cc_email'  => 'administratie@example.org',
		];
		$request->set_body_params( $recipient );
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$id = $response->get_data()['id'];
		foreach ( $recipient as $key => $value ) {
			$this->assertSame( $value, get_post_meta( $id, '_customer_' . $key, true ), $key );
		}
		$this->assertSame( 'rondo_draft', get_post_status( $id ) );
		$this->assertSame( 'manual', get_post_meta( $id, '_payment_account_id', true ) );
	}

	public function test_billing_endpoint_rejects_invalid_cc_before_reserving_reports(): void {
		$this->configure_mollie_accounts();
		$report = $this->report( '2026-09-29', 'invalid-cc' );
		$server = $this->bootRestControllers( [ \Rondo\REST\TwelveReports::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/twelve/billing' );
		$request->set_body_params(
			[
				'name'     => 'Businessclub',
				'address'  => 'Sportlaan 1',
				'email'    => 'facturen@example.org',
				'cc_email' => 'ongeldig',
			]
			);
		$this->assertSame( 400, $server->dispatch( $request )->get_status() );
		$this->assertSame( '', get_post_meta( $report, BusinessclubInvoicing::CLAIM, true ) );
		$request->set_param( 'cc_email', '' );
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '', get_post_meta( $response->get_data()['id'], '_customer_cc_email', true ) );
		$this->assertSame( '', get_post_meta( $response->get_data()['id'], '_customer_attention', true ) );
	}

	private function configure_mollie_accounts(): void {
		$accounts = [];
		foreach ( [ 'membership', 'manual' ] as $id ) {
			$accounts[] = [
				'id'             => $id,
				'internal_name'  => $id,
				'account_holder' => 'Test club',
				'iban'           => 'NL91ABNA0417164300',
				'api_key'        => 'test_' . str_repeat( $id === 'manual' ? 'a' : 'b', 30 ),
			];
		}
		update_option( MollieConfig::OPTION_MOLLIE_ACCOUNTS, FinanceServices::mollie()->normalize_accounts_for_storage( $accounts ) );
		update_option( MollieConfig::OPTION_ACTIVE_PAYMENT_PROVIDER, 'mollie' );
		update_option( MollieConfig::OPTION_MOLLIE_DEFAULT_MANUAL_ACCOUNT_ID, 'manual' );
		update_option( MollieConfig::OPTION_MOLLIE_DEFAULT_MEMBERSHIP_ACCOUNT_ID, 'membership' );
	}

	public function test_draft_stores_manual_payment_account_and_keeps_it_when_default_changes(): void {
		$this->configure_mollie_accounts();
		$this->report( '2026-09-29', 'payment-account' );
		$id = ( new BusinessclubInvoicing() )->create_draft_invoice();
		$this->assertIsInt( $id );
		$this->assertSame( 'manual', get_post_meta( $id, '_payment_account_id', true ) );
		$this->assertSame( 'manual', get_post_meta( $id, '_payment_account_internal_name', true ) );
		$this->assertSame( 'Test club', get_post_meta( $id, '_payment_account_account_holder', true ) );
		$this->assertSame( 'NL91ABNA0417164300', get_post_meta( $id, '_payment_account_iban', true ) );
		$this->assertSame( 'mollie', get_post_meta( $id, '_payment_account_linked_provider', true ) );
		update_option( MollieConfig::OPTION_MOLLIE_DEFAULT_MANUAL_ACCOUNT_ID, 'membership' );
		$this->assertSame( 'test_' . str_repeat( 'a', 30 ), FinanceServices::mollie()->get_mollie_api_key_for_account( get_post_meta( $id, '_payment_account_id', true ) ) );
	}

	public function test_missing_payment_account_leaves_reports_available_for_retry(): void {
		update_option( MollieConfig::OPTION_ACTIVE_PAYMENT_PROVIDER, 'mollie' );
		update_option( MollieConfig::OPTION_MOLLIE_ACCOUNTS, [] );
		$report  = $this->report( '2026-09-29', 'missing-payment-account' );
		$service = new BusinessclubInvoicing();
		$result  = $service->create_draft_invoice();
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'mollie_account_not_configured', $result->get_error_code() );
		$this->assertSame( '', get_post_meta( $report, BusinessclubInvoicing::CLAIM, true ) );
		$this->assertSame( [], $service->overview()['invoices'] );
		$this->assertSame( 109.0, $service->overview()['available'] );
		$this->assertFalse( get_option( 'rondo_twelve_invoice_lock' ) );
		$this->configure_mollie_accounts();
		$this->assertIsInt( $service->create_draft_invoice() );
	}

	public function test_rabobank_draft_does_not_require_a_mollie_account(): void {
		update_option( MollieConfig::OPTION_ACTIVE_PAYMENT_PROVIDER, 'rabobank' );
		update_option( MollieConfig::OPTION_MOLLIE_ACCOUNTS, [] );
		$this->report( '2026-09-29', 'rabobank-payment-account' );
		$id = ( new BusinessclubInvoicing() )->create_draft_invoice();
		$this->assertIsInt( $id );
		$this->assertSame( 'rabobank', get_post_meta( $id, '_payment_account_linked_provider', true ) );
	}

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
