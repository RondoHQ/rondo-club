<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Finance\CreditSepaExport;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;

class CreditSepaExportTest extends RondoTestCase {

	private int $invoice;
	private array $account = [
		'account_holder' => 'Testvereniging',
		'iban'           => 'NL44RABO0123456789',
	];

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->invoice = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_sent',
			]
			);
		update_post_meta( $this->invoice, '_invoice_kind', 'credit' );
		update_post_meta( $this->invoice, '_customer_name', 'Testontvanger' );
		Fields::update_for_post( $this->invoice, 'total_amount', -76.50 );
		Fields::update_for_post( $this->invoice, 'invoice_number', 'TEST-2026F085' );
		$this->bootRestControllers( [ Invoices::class ] );
	}

	private function payload(): array {
		return [
			'confirmed'             => true,
			'expected_amount_cents' => 7650,
			'request_id'            => wp_generate_uuid4(),
			'debtor_name'           => 'Testvereniging & vrienden',
			'debtor_iban'           => 'nl44 rabo 0123 4567 89',
			'creditor_name'         => 'René & Zoë',
			'creditor_iban'         => 'NL91ABNA0417164300',
			'execution_date'        => current_datetime()->modify( '+1 day' )->format( 'Y-m-d' ),
		];
	}

	private function request( string $method, array $data = [], ?int $id = null ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rondo/v1/invoices/' . ( $id ?? $this->invoice ) . '/sepa-export' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
		return rest_do_request( $request );
	}

	public function test_export_validates_against_rabobank_xsd_and_preserves_invoice(): void {
		$before = get_post( $this->invoice )->to_array();
		$fields = Fields::get_for_post( $this->invoice, 'total_amount' );
		$result = $this->request( 'POST', $this->payload() );
		$this->assertSame( 200, $result->get_status(), wp_json_encode( $result->get_data() ) );
		$this->assertSame( 'private, no-store', $result->get_headers()['Cache-Control'] );
		$xml = new \DOMDocument();
		$this->assertTrue( $xml->loadXML( $result->get_data()['xml'] ) );
		$this->assertTrue( $xml->schemaValidate( dirname( __DIR__ ) . '/fixtures/sepa/pain.001.001.09.xsd' ) );
		$xpath = new \DOMXPath( $xml );
		$xpath->registerNamespace( 's', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09' );
		foreach ( [
			'//s:InstdAmt'                     => '76.50',
			'//s:InstdAmt/@Ccy'                => 'EUR',
			'//s:DbtrAcct/s:Id/s:IBAN'         => 'NL44RABO0123456789',
			'//s:CdtrAcct/s:Id/s:IBAN'         => 'NL91ABNA0417164300',
			'//s:Cdtr/s:Nm'                    => 'René & Zoë',
			'//s:DbtrAgt/s:FinInstnId/s:BICFI' => 'RABONL2U',
			'//s:Ustrd'                        => 'Creditfactuur TEST-2026F085',
			'//s:EndToEndId'                   => $result->get_data()['export_id'],
		] as $path => $expected ) {
			$this->assertSame( $expected, $xpath->evaluate( 'string(' . $path . ')' ) );
		}
		$this->assertSame( 1, $xpath->query( '//s:CdtTrfTxInf' )->length );
		$this->assertSame( $before, get_post( $this->invoice )->to_array() );
		$this->assertSame( $fields, Fields::get_for_post( $this->invoice, 'total_amount' ) );
		$this->assertEmpty( Fields::get_for_post( $this->invoice, 'payment_link' ) );
		$context = $this->request( 'GET' )->get_data();
		$this->assertSame( get_current_user_id(), $context['export']['created_by'] );
		$this->assertArrayNotHasKey( 'xml', $context['export'] );
		$this->assertArrayNotHasKey( 'fingerprint', $context['export'] );
	}

	public function test_lost_response_replay_and_confirmed_redownload_are_identical(): void {
		$data  = $this->payload();
		$first = $this->request( 'POST', $data )->get_data();
		$this->assertArrayHasKey( 'xml', $first );
		$stored = get_post_meta( $this->invoice );
		$this->assertSame( $first, $this->request( 'POST', $data )->get_data() );
		$this->assertSame( $stored, get_post_meta( $this->invoice ) );
		$data['request_id'] = wp_generate_uuid4();
		$this->assertSame( 409, $this->request( 'POST', $data )->get_status() );
		$data['previous_export_id'] = $first['export_id'];
		$this->assertSame( $first, $this->request( 'POST', $data )->get_data() );
		$this->assertSame( $stored, get_post_meta( $this->invoice ) );
	}

	public function test_same_request_id_with_changed_data_is_rejected(): void {
		$data = $this->payload();
		$this->assertSame( 200, $this->request( 'POST', $data )->get_status() );
		$data['creditor_name'] = 'Andere ontvanger';
		$this->assertSame( 409, $this->request( 'POST', $data )->get_status() );
	}

	public function test_get_prefills_original_payer_and_is_read_only(): void {
		$source = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_paid',
			]
			);
		update_post_meta( $source, '_mollie_consumer_name', 'Oorspronkelijke betaler' );
		update_post_meta( $source, '_mollie_consumer_account', 'NL91ABNA0417164300' );
		update_post_meta( $this->invoice, '_credit_source_invoice_id', $source );
		$before = get_post_meta( $this->invoice );
		$data   = CreditSepaExport::prepare( $this->invoice, $this->account );
		$this->assertSame( 'Oorspronkelijke betaler', $data['defaults']['creditor_name'] );
		$this->assertSame( 'NL91ABNA0417164300', $data['defaults']['creditor_iban'] );
		$this->assertSame( $this->account['iban'], $data['defaults']['debtor_iban'] );
		$this->assertNull( $data['export'] );
		$this->assertSame( $before, get_post_meta( $this->invoice ) );
	}

	public function test_unlinked_credit_leaves_unknown_iban_empty(): void {
		$data = CreditSepaExport::prepare( $this->invoice, $this->account );
		$this->assertSame( 'Testontvanger', $data['defaults']['creditor_name'] );
		$this->assertSame( '', $data['defaults']['creditor_iban'] );
	}

	public function test_legacy_positive_credit_amount_is_exported_as_positive_payment(): void {
		Fields::update_for_post( $this->invoice, 'total_amount', 76.50 );
		$result = $this->request( 'POST', $this->payload() );
		$this->assertSame( 200, $result->get_status() );
		$this->assertStringContainsString( '<InstdAmt Ccy="EUR">76.50</InstdAmt>', $result->get_data()['xml'] );
	}

	public function test_invalid_inputs_do_not_create_export(): void {
		foreach ( [
			[ 'confirmed' => false ],
			[ 'confirmed' => 'true' ],
			[ 'expected_amount_cents' => 7651 ],
			[ 'creditor_iban' => 'NL92ABNA0417164300' ],
			[ 'creditor_iban' => 'NL91ABNA041716430000' ],
			[ 'creditor_iban' => 'NL44RABO0123456789' ],
			[ 'debtor_iban' => 'NL91ABNA0417164300' ],
			[ 'creditor_name' => '' ],
			[ 'creditor_name' => "Invalid\x01name" ],
			[ 'creditor_name' => str_repeat( 'a', 71 ) ],
			[ 'creditor_iban' => [ 'invalid' ] ],
			[ 'execution_date' => '2026-02-30' ],
			[ 'execution_date' => '2000-01-01' ],
			[ 'execution_date' => '2099-01-01' ],
			[ 'request_id' => '' ],
		] as $override ) {
			$this->assertGreaterThanOrEqual( 400, $this->request( 'POST', array_merge( $this->payload(), $override ) )->get_status(), wp_json_encode( $override ) );
			$this->assertEmpty( get_post_meta( $this->invoice, '_credit_sepa_export', true ) );
		}
	}

	public function test_closed_draft_and_noncredit_invoices_are_blocked(): void {
		foreach ( [ 'rondo_draft', 'rondo_paid', 'rondo_cancelled', 'trash' ] as $status ) {
			wp_update_post(
				[
					'ID'          => $this->invoice,
					'post_status' => $status,
				]
				);
			$this->assertSame( 409, $this->request( 'POST', $this->payload() )->get_status() );
		}
		wp_update_post(
			[
				'ID'          => $this->invoice,
				'post_status' => 'rondo_sent',
			]
			);
		delete_post_meta( $this->invoice, '_invoice_kind' );
		$this->assertSame( 404, $this->request( 'POST', $this->payload() )->get_status() );
		$this->assertSame( 404, $this->request( 'GET', [], self::factory()->post->create() )->get_status() );
	}

	public function test_changed_amount_or_paid_status_blocks_existing_export(): void {
		$data = $this->payload();
		$this->assertSame( 200, $this->request( 'POST', $data )->get_status() );
		Fields::update_for_post( $this->invoice, 'total_amount', -77 );
		$this->assertSame( 409, $this->request( 'POST', $data )->get_status() );
		Fields::update_for_post( $this->invoice, 'total_amount', -76.50 );
		wp_update_post(
			[
				'ID'          => $this->invoice,
				'post_status' => 'rondo_paid',
			]
			);
		$this->assertSame( 409, $this->request( 'POST', $data )->get_status() );
	}

	public function test_finance_readers_and_other_users_cannot_read_or_export_bank_details(): void {
		foreach ( [ [], [ 'financieel_read' ] ] as $capabilities ) {
			$user = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
			foreach ( $capabilities as $capability ) {
				$user->add_cap( $capability );
			}
			wp_set_current_user( $user->ID );
			$this->assertSame( 403, $this->request( 'GET' )->get_status() );
			$this->assertSame( 403, $this->request( 'POST', $this->payload() )->get_status() );
		}
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET' )->get_status() );
	}

	public function test_finance_manager_without_admin_can_export(): void {
		$user = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		$user->add_cap( 'financieel' );
		wp_set_current_user( $user->ID );
		$this->assertSame( 200, $this->request( 'POST', $this->payload() )->get_status() );
	}

	public function test_parallel_export_lock_rejects_second_writer(): void {
		$lock = fopen( get_temp_dir() . 'rondo-sepa-' . md5( ABSPATH . ':' . $this->invoice ) . '.lock', 'c' );
		flock( $lock, LOCK_EX );
		try {
			$this->assertSame( 409, $this->request( 'POST', $this->payload() )->get_status() );
			$this->assertEmpty( get_post_meta( $this->invoice, '_credit_sepa_export', true ) );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	public function test_missing_invoice_and_zero_amount_cannot_be_exported(): void {
		$this->assertSame( 404, $this->request( 'GET', [], 0 )->get_status() );
		$this->assertSame( 404, $this->request( 'GET', [], 999999999 )->get_status() );
		Fields::update_for_post( $this->invoice, 'total_amount', 0 );
		$this->assertSame( 409, $this->request( 'POST', $this->payload() )->get_status() );
	}
}
