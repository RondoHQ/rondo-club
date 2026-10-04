<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\REST\TwelveReports;
use Rondo\Twelve\BusinessclubInvoicing;
use Rondo\Twelve\ReportRepository;
use Tests\Support\RondoTestCase;

/** Tests for the Twelve REST endpoints and the businessclub invoicing. */
class TwelveReportsRestTest extends RondoTestCase {

	private function user( string $role ): int {
		$user_id = self::factory()->user->create(
			[
				'role'       => $role,
				'user_login' => uniqid( $role . '_', true ),
			]
		);
		UserRoles::sync_role_capabilities( $role );
		return $user_id;
	}

	private function import_fixture_report(): int {
		$parsed     = json_decode( file_get_contents( __DIR__ . '/../fixtures/twelve-report.json' ), true );
		$repository = new ReportRepository();
		$post_id    = $repository->store( $parsed, 'msg-123', 'rapportage.pdf', "%PDF-1.4\n%fake\n" );
		$this->assertNotInstanceOf( \WP_Error::class, $post_id );
		return $post_id;
	}

	public function test_endpoints_require_financieel_read(): void {
		$server = $this->bootRestControllers( [ TwelveReports::class ] );

		wp_set_current_user( $this->user( UserRoles::ROLE_NAME ) );
		foreach ( [ '/rondo/v1/twelve/reports', '/rondo/v1/twelve/summary', '/rondo/v1/twelve/categories', '/rondo/v1/twelve/products', '/rondo/v1/twelve/vat', '/rondo/v1/twelve/product-groups' ] as $route ) {
			$response = $server->dispatch( new \WP_REST_Request( 'GET', $route ) );
			$this->assertSame( 403, $response->get_status(), $route );
		}

		$request = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/businessclub' );
		$request->set_param( 'month', '2026-09' );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
	}

	public function test_summary_and_categories(): void {
		$this->import_fixture_report();
		$server = $this->bootRestControllers( [ TwelveReports::class ] );
		wp_set_current_user( $this->user( 'rondo_bestuur' ) );

		$request = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/summary' );
		$request->set_param( 'from', '2026-09-01' );
		$request->set_param( 'to', '2026-09-30' );
		$data = $server->dispatch( $request )->get_data();

		$this->assertCount( 1, $data['buckets'] );
		$this->assertSame( '2026-09-29', $data['buckets'][0]['periode'] );
		$this->assertSame( 15.75, $data['buckets'][0]['omzet_excl_nosale'] );
		$this->assertSame( 43.45, $data['buckets'][0]['omzet_incl_nosale'] );
		$this->assertSame( 20, $data['buckets'][0]['producten'] );

		$request = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/categories' );
		$request->set_param( 'from', '2026-09-01' );
		$request->set_param( 'to', '2026-09-30' );
		$data = $server->dispatch( $request )->get_data();

		$by_name = [];
		foreach ( $data['categories'] as $category ) {
			$by_name[ $category['categorie'] ] = $category;
		}
		$this->assertSame( 27.70, $by_name['Bestuur']['bedrag'] );
		$this->assertSame( 0.0, $by_name['Businessclub']['bedrag'] );
	}

	public function test_products_and_vat(): void {
		$this->import_fixture_report();
		$server = $this->bootRestControllers( [ TwelveReports::class ] );
		wp_set_current_user( $this->user( 'rondo_bestuur' ) );

		$request = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/products' );
		$request->set_param( 'from', '2026-09-01' );
		$request->set_param( 'to', '2026-09-30' );
		$data = $server->dispatch( $request )->get_data();

		$this->assertSame( 'Snoepzakje', $data['products'][0]['product'] );
		$this->assertSame( 6, $data['products'][0]['aantal'] );

		$request = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/vat' );
		$request->set_param( 'from', '2026-09-01' );
		$request->set_param( 'to', '2026-09-30' );
		$data = $server->dispatch( $request )->get_data();

		$this->assertCount( 1, $data['vat'] );
		$this->assertSame( 1.30, $data['vat'][0]['btw_laag'] );
	}

	public function test_businessclub_endpoint_validates_month(): void {
		$server = $this->bootRestControllers( [ TwelveReports::class ] );
		wp_set_current_user( $this->user( 'rondo_bestuur' ) );

		$request = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/businessclub' );
		$request->set_param( 'month', 'september' );
		$this->assertSame( 400, $server->dispatch( $request )->get_status() );

		$request = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/businessclub' );
		$request->set_param( 'month', '2026-09' );
		$data = $server->dispatch( $request )->get_data();
		$this->assertSame( '2026-09', $data['month'] );
		$this->assertSame( 0.0, $data['total'] );
	}

	public function test_businessclub_invoice_creates_draft(): void {
		$this->import_fixture_report();

		// The fixture has no businessclub turnover; inject some via a second report.
		$parsed = json_decode( file_get_contents( __DIR__ . '/../fixtures/twelve-report.json' ), true );
		foreach ( $parsed['omzet'] as &$row ) {
			if ( $row['label'] === 'Businessclub' ) {
				$row['bedrag'] = 120.00;
				$row['netto']  = 110.09;
				$row['laag']   = 9.91;
			}
		}
		unset( $row );
		$parsed['period_start'] = '2026-09-30 06:00';
		$parsed['period_end']   = '2026-10-01 06:00';
		$repository             = new ReportRepository();
		$second                 = $repository->store( $parsed, 'msg-124', 'b.pdf', "%PDF-1.4\n%fake\n" );
		$this->assertNotInstanceOf( \WP_Error::class, $second );

		$invoicing  = new BusinessclubInvoicing();
		$invoice_id = $invoicing->create_draft_invoice( '2026-09' );
		$this->assertNotInstanceOf( \WP_Error::class, $invoice_id );

		$this->assertSame( 'rondo_invoice', get_post_type( $invoice_id ) );
		$this->assertSame( 'rondo_draft', get_post_status( $invoice_id ) );
		$this->assertSame( 'manual', \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_type' ) );
		$this->assertSame( 120.0, (float) \Rondo\Fields\Fields::get_for_post( $invoice_id, 'total_amount' ) );
		$this->assertSame( '2026-09', get_post_meta( $invoice_id, BusinessclubInvoicing::META_MONTH, true ) );

		$line_items = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'line_items' );
		$this->assertCount( 2, $line_items );
		$this->assertSame( 110.09, (float) $line_items[0]['amount'] );
		$this->assertSame( 9.91, (float) $line_items[1]['amount'] );

		// Second run for the same month is rejected (idempotent).
		$again = $invoicing->create_draft_invoice( '2026-09' );
		$this->assertInstanceOf( \WP_Error::class, $again );
		$this->assertSame( 'twelve_duplicate_invoice', $again->get_error_code() );
	}

	public function test_businessclub_invoice_refuses_empty_month(): void {
		$invoicing = new BusinessclubInvoicing();
		$result    = $invoicing->create_draft_invoice( '2026-09' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'twelve_nothing_to_invoice', $result->get_error_code() );
	}
	public function test_product_groups_require_admin_and_recalculate_history(): void {
		$id       = $this->import_fixture_report();
		$original = get_post_meta( $id, '_twelve_report_data', true );
		$server   = $this->bootRestControllers( [ TwelveReports::class ] );
		wp_set_current_user( $this->user( 'rondo_bestuur' ) );
		$get     = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/product-groups' );
		$catalog = $server->dispatch( $get )->get_data()['products'];
		$this->assertCount( 9, $catalog );
		$this->assertSame( [ 'unassigned' ], array_values( array_unique( array_column( $catalog, 'group' ) ) ) );
		$post = new \WP_REST_Request( 'POST', '/rondo/v1/twelve/product-groups' );
		$post->set_param( 'id', hash( 'sha256', 'Snoepzakje' ) );
		$post->set_param( 'group', 'food' );
		$this->assertSame( 403, $server->dispatch( $post )->get_status() );
		wp_set_current_user( $this->user( 'administrator' ) );
		$this->assertSame( 200, $server->dispatch( $post )->get_status() );
		$summary = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/summary' );
		$summary->set_param( 'from', '2026-09-01' );
		$summary->set_param( 'to', '2026-09-30' );
		$mix = $server->dispatch( $summary )->get_data()['product_mix'];
		$this->assertEquals( 43.45, $mix['total'] );
		$groups = array_column( $mix['groups'], null, 'group' );
		$this->assertEquals( 10.50, $groups['food']['amount'] );
		$this->assertEquals( 24.17, $groups['food']['percentage'] );
		$this->assertEquals( 32.95, $groups['unassigned']['amount'] );
		$this->assertSame( $original, get_post_meta( $id, '_twelve_report_data', true ) );
		$post->set_param( 'group', 'entree' );
		$this->assertSame( 200, $server->dispatch( $post )->get_status() );
		$groups = array_column( $server->dispatch( $summary )->get_data()['product_mix']['groups'], null, 'group' );
		$this->assertEquals( 10.50, $groups['entree']['amount'] );
		$this->assertEquals( 0, $groups['food']['amount'] );
		$post->set_param( 'group', 'unassigned' );
		$this->assertSame( 200, $server->dispatch( $post )->get_status() );
		$post->set_param( 'group', 'invalid' );
		$this->assertSame( 400, $server->dispatch( $post )->get_status() );
		$post->set_param( 'group', 'food' );
		$post->set_param( 'id', hash( 'sha256', 'Unknown product' ) );
		$this->assertSame( 404, $server->dispatch( $post )->get_status() );
	}

	public function test_product_mix_zero_and_new_products(): void {
		$summary = \Rondo\Twelve\ProductClassification::summary( [] );
		$this->assertSame( [ null, null, null, null ], array_column( $summary['groups'], 'percentage' ) );
		update_option( \Rondo\Twelve\ProductClassification::OPTION, [ hash( 'sha256', 'Known' ) => 'non_food' ] );
		$summary = \Rondo\Twelve\ProductClassification::summary(
			[
				[
					'product' => 'Known',
					'bruto'   => 3.00,
				],
				[
					'product' => 'New',
					'bruto'   => 1.00,
				],
			]
			);
		$groups  = array_column( $summary['groups'], null, 'group' );
		$this->assertEquals( 75, $groups['non_food']['percentage'] );
		$this->assertEquals( 25, $groups['unassigned']['percentage'] );
	}
}
