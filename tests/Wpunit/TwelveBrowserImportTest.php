<?php
namespace Tests\Wpunit;

use Rondo\Twelve\BrowserImport;
use Rondo\Twelve\ReportRepository;
use Rondo\Twelve\BusinessclubInvoicing;
use Tests\Support\RondoTestCase;

class TwelveBrowserImportTest extends RondoTestCase {
	private function payload(): array {
		$data = json_decode( file_get_contents( __DIR__ . '/../fixtures/twelve-report.json' ), true );
		// The browser contract includes only consumed fields, not PDF cashflow tables.
		$data                         = array_intersect_key( $data, array_flip( [ 'club', 'period_start', 'period_end', 'omzet', 'producten', 'producten_totaal' ] ) );
		$data['no_sale_transactions'] = [];
		$data['source']               = [
			'type'           => 'twelve_browser',
			'client_id'      => '123',
			'observed_at'    => '2026-10-01T08:00:00.000Z',
			'coverage_end'   => $data['period_end'],
			'complete'       => true,
			'finance_sha256' => str_repeat( 'a', 64 ),
			'audit'          => array_fill_keys( [ 'transactions', 'products', 'raw' ], str_repeat( 'b', 64 ) ),
		];
		foreach ( $data['omzet'] as &$row ) {
			$row['transacties'] = (int) ( $row['transacties'] ?? 0 );
		}
		return $data;
	}
	private function save( array $data ) {
		return ( new BrowserImport() )->import( wp_json_encode( $data ) );
	}
	public function test_upserts_legacy_report_and_replays_without_duplicates_preserving_pdf(): void {
		$data   = $this->payload();
		$id     = ( new ReportRepository() )->store( $data, 'legacy', 'archive.pdf', '%PDF original' );
		$result = $this->save( $data );
		$this->assertIsArray( $result );
		$this->assertSame( $id, $result['id'] );
		$this->assertSame( 'updated', $result['status'] );
		$this->assertSame( 'unchanged', $this->save( $data )['status'] );
		$this->assertSame( '%PDF original', base64_decode( get_post_meta( $id, '_twelve_pdf_base64', true ) ) );
		$this->assertNotEmpty( get_post_meta( $id, '_twelve_original_report_data', true ) );
		$this->assertCount( 1, ( new ReportRepository() )->query( '2026-09-29', '2026-09-29' ) );
	}
	public function test_rejects_partial_financial_data_and_out_of_order_snapshots(): void {
		$data = $this->payload();
		$bad  = $data;
		unset( $bad['producten'][0]['netto'] );
		$this->assertSame( 'twelve_invalid_export', $this->save( $bad )->get_error_code() );
		$this->assertIsArray( $this->save( $data ) );
		$data['source']['observed_at'] = '2026-09-30T08:00:00.000Z';
		$this->assertSame( 'twelve_stale_export', $this->save( $data )->get_error_code() );
	}
	public function test_invoice_claim_prevents_changed_financial_values(): void {
		$data   = $this->payload();
		$result = $this->save( $data );
		$this->assertIsArray( $result );
		update_post_meta( $result['id'], BusinessclubInvoicing::CLAIM, 999 );
		foreach ( $data['omzet'] as &$row ) {
			if ( $row['label'] === 'Businessclub' ) {
				$row['bedrag'] = 1.09;
				$row['netto']  = 1;
				$row['laag']   = 0.09;
			}
		}
		$this->assertSame( 'twelve_invoice_conflict', $this->save( $data )->get_error_code() );
	}
	public function test_import_and_invoice_use_the_same_lock(): void {
		add_option( 'rondo_twelve_invoice_lock', time() );
		$this->assertSame( 'twelve_import_busy', $this->save( $this->payload() )->get_error_code() );
		delete_option( 'rondo_twelve_invoice_lock' );
	}
	public function test_only_admin_can_import_and_kassa_readers_can_read_details(): void {
		$server  = $this->bootRestControllers( [ \Rondo\REST\TwelveReports::class ] );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/twelve/import' );
		$request->set_param( 'report_json', wp_json_encode( $this->payload() ) );
		$this->assertSame( 401, $server->dispatch( $request )->get_status() );
		$user = self::factory()->user->create( [ 'role' => 'rondo_user' ] );
		wp_set_current_user( $user );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user );
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
	}
	public function test_historical_and_unfinished_days_are_not_available_to_invoice(): void {
		$data = $this->payload();
		foreach ( $data['omzet'] as &$row ) {
			if ( $row['label'] === 'Businessclub' ) {
				$row['bedrag'] = 1.09;
				$row['netto']  = 1;
				$row['laag']   = 0.09;
			}
		}
		update_option( 'rondo_twelve_billing_start', '2026-09-30' );
		$this->assertIsArray( $this->save( $data ) );
		$overview = ( new BusinessclubInvoicing() )->overview();
		$this->assertSame( 'historical', $overview['rows'][0]['status'] );
		$this->assertSame( 0.0, $overview['available'] );
		$data['period_start']           = '2026-09-30 06:00';
		$data['period_end']             = '2026-10-01 06:00';
		$data['source']['coverage_end'] = '2026-09-30 14:00';
		$data['source']['complete']     = false;
		$this->assertIsArray( $this->save( $data ) );
		$overview = ( new BusinessclubInvoicing() )->overview();
		$this->assertSame( 'provisional', $overview['rows'][1]['status'] );
		$this->assertSame( 0.0, $overview['available'] );
	}
	public function test_storage_retry_recovers_unpublished_report_without_duplicate(): void {
		$data   = $this->payload();
		$filter = static fn( $check, $id, $key ) => $key === ReportRepository::META_DATA ? false : $check;
		add_filter( 'update_post_metadata', $filter, 10, 3 );
		$result = $this->save( $data );
		remove_filter( 'update_post_metadata', $filter, 10 );
		$this->assertSame( 'twelve_storage_failed', $result->get_error_code() );
		$this->assertIsArray( $this->save( $data ) );
		$this->assertCount(
			1,
			get_posts(
			[
				'post_type'   => ReportRepository::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			]
			)
			);
	}
	public function test_retired_pdf_cron_is_unscheduled(): void {
		wp_schedule_single_event( time() + 100, 'rondo_twelve_daily_import' );
		BrowserImport::retire_pdf_import();
		$this->assertFalse( wp_next_scheduled( 'rondo_twelve_daily_import' ) );
	}
	public function test_no_sale_keeps_product_prices_and_validates_accounted_amount(): void {
		$data                         = $this->payload();
		$data['no_sale_transactions'] = [
			[
				'transactionId'  => 'allocation',
				'day'            => '2026-09-29',
				'localTime'      => '2026-09-29 12:00',
				'category'       => 'Businessclub',
				'partial'        => false,
				'grossCents'     => 100,
				'accountedCents' => 90,
				'products'       => [
					[
						'name'       => 'Drink',
						'count'      => 1,
						'grossCents' => 100,
					],
				],
			],
		];
		$this->assertTrue( BrowserImport::validate( $data ) );
		$data['no_sale_transactions'][0]['accountedCents'] = 90.5;
		$this->assertFalse( BrowserImport::validate( $data ) );
	}
}
