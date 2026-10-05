<?php
namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\REST\KantineMargins;
use Rondo\Twelve\MarginRepository;
use Rondo\Twelve\PurchaseParser;
use Tests\Support\RondoTestCase;

class KantineMarginsTest extends RondoTestCase {
	private function invoice( string $number = 'INV-1', string $date = '2026-09-01', float $amount = 24.00 ): array {
		$vat = round( $amount * 0.09, 2 );
		return [
			'invoice_number' => $number,
			'supplier'       => 'Testleverancier',
			'invoice_date'   => $date,
			'source_file'    => 'test.pdf',
			'vat_amount'     => $vat,
			'total_amount'   => $amount + $vat,
			'lines'          => [
				[
					'article'        => 'COLA',
					'description'    => 'Cola per 24 blikjes',
					'amount'         => $amount,
					'deposit'        => 0,
					'vat_rate'       => 9,
					'packs'          => 1,
					'pack_content'   => '24',
					'units_per_pack' => 24,
					'quantity'       => 24,
					'unit'           => 'stuk',
					'category'       => 'Drank',
					'note'           => '',
				],
			],
		];
	}

	private function product(): array {
		return [
			'twelve_id'    => '12345',
			'product_name' => 'Cola',
			'active'       => true,
			'cost_status'  => 'ready',
			'cost_note'    => '',
			'ingredients'  => [
				[
					'article'  => 'COLA',
					'quantity' => 1,
					'unit'     => 'stuk',
				],
			],
			'sale_prices'  => [
				[
					'effective_date' => '2026-09-01',
					'amount'         => 3.27,
					'vat_rate'       => 9,
					'source'         => 'Testexport',
				],
			],
		];
	}

	public function test_native_storage_replay_and_private_pdf(): void {
		$repository = new MarginRepository();
		$repository->register();
		$created = $repository->save_purchase( $this->invoice(), '%PDF-test' );
		$this->assertFalse( $created['unchanged'] );
		$this->assertSame( $created['id'], $repository->save_purchase( $this->invoice(), '%PDF-test' )['id'] );
		$this->assertSame( 1, (int) get_post_meta( $created['id'], 'lines', true ) );
		$this->assertSame( 'COLA', get_post_meta( $created['id'], 'lines_0_article', true ) );
		$this->assertSame( '%PDF-test', base64_decode( get_post_meta( $created['id'], '_kantine_pdf_base64', true ) ) );
		$this->assertFalse( get_post_type_object( MarginRepository::PURCHASE )->show_in_rest );
		$this->assertFalse( get_post_type_object( MarginRepository::PURCHASE )->public );
		$this->assertCount( 1, $repository->invoices() );
		$changed                       = $this->invoice();
		$changed['lines'][0]['amount'] = 25;
		$changed['total_amount']       = 27.16;
		$this->assertInstanceOf( \WP_Error::class, $repository->save_purchase( $changed ) );
		$this->assertCount( 1, $repository->invoices() );
	}

	public function test_prices_follow_invoice_date_and_recipe_quantities(): void {
		$repository = new MarginRepository();
		$repository->register();
		$repository->save_purchase( $this->invoice() );
		$repository->save_purchase( $this->invoice( 'INV-2', '2026-10-01', 36 ) );
		$created = $repository->save_product( $this->product() );
		$this->assertArrayHasKey( 'id', $created );
		$this->assertSame( '12345', Fields::get_for_post( $created['id'], 'twelve_id' ) );
		$september = $repository->catalog( '2026-09-30' )['products'][0];
		$this->assertEqualsWithDelta( 1, $september['cost'], 0.000001 );
		$this->assertEqualsWithDelta( 2, $september['margin'], 0.000001 );
		$this->assertEqualsWithDelta( 66.66666667, $september['margin_percentage'], 0.000001 );
		$this->assertEqualsWithDelta( 1.5, $repository->catalog( '2026-10-01' )['products'][0]['cost'], 0.000001 );
		$this->assertNull( $repository->catalog( '2026-08-31' )['products'][0]['margin'] );
		$this->assertCount( 2, $repository->catalog( '2026-10-01' )['articles'][0]['history'] );
	}

	public function test_unreviewed_new_packaging_blocks_old_price_and_supplier_collisions(): void {
		$repository = new MarginRepository();
		$repository->register();
		$repository->save_purchase( $this->invoice() );
		$repository->save_product( $this->product() );
		$changed                         = $this->invoice( 'INV-2', '2026-10-01', 36 );
		$changed['lines'][0]['quantity'] = null;
		$changed['lines'][0]['unit']     = '';
		$this->assertArrayHasKey( 'id', $repository->save_purchase( $changed ) );
		$this->assertNull( $repository->catalog( '2026-10-01' )['products'][0]['cost'] );
		$this->assertEqualsWithDelta( 1, $repository->catalog( '2026-09-30' )['products'][0]['cost'], 0.000001 );
		$other             = $this->invoice( 'OTHER' );
		$other['supplier'] = 'Andere leverancier';
		$this->assertInstanceOf( \WP_Error::class, $repository->save_purchase( $other ) );
		$this->assertCount( 2, $repository->invoices() );
	}

	public function test_missing_costs_unit_changes_and_review_status_never_become_zero_cost(): void {
		$article = [
			'COLA' => [
				'description' => 'Cola',
				'latest'      => [
					'price' => 1,
					'unit'  => 'stuk',
				],
			],
		];
		$product = $this->product();
		foreach ( [ 'mapping', 'recipe', 'portion', 'vat', 'missing', 'excluded' ] as $status ) {
			$product['cost_status'] = $status;
			$this->assertNull( MarginRepository::calculate( $product, $article, '2026-10-01' )['margin'] );
		}
		$product['cost_status'] = 'ready';
		$this->assertNull( MarginRepository::calculate( $product, [], '2026-10-01' )['margin'] );
		$product['ingredients'][0]['unit'] = 'liter';
		$this->assertNull( MarginRepository::calculate( $product, $article, '2026-10-01' )['margin'] );
		$product['ingredients'][0] = [
			'article'  => 'COLA',
			'quantity' => null,
			'unit'     => 'stuk',
		];
		$this->assertNull( MarginRepository::calculate( $product, $article, '2026-10-01' )['margin'] );
	}

	public function test_product_revision_prevents_lost_edits_and_removes_stale_recipe_rows(): void {
		$repository = new MarginRepository();
		$repository->register();
		$created               = $repository->save_product( $this->product() );
		$data                  = array_merge(
			$this->product(),
			[
				'id'       => $created['id'],
				'revision' => Fields::get_for_post( $created['id'], 'revision' ),
			]
			);
		$data['ingredients'][] = [
			'article'  => 'SAUS',
			'quantity' => 0.02,
			'unit'     => 'liter',
		];
		$this->assertArrayHasKey( 'id', $repository->save_product( $data ) );
		$this->assertSame( 'SAUS', get_post_meta( $created['id'], 'ingredients_1_article', true ) );
		$this->assertSame( 409, $repository->save_product( $data )->get_error_data()['status'] );
		$data['revision']    = Fields::get_for_post( $created['id'], 'revision' );
		$data['ingredients'] = [];
		$repository->save_product( $data );
		$this->assertFalse( metadata_exists( 'post', $created['id'], 'ingredients_1_article' ) );
	}

	public function test_rest_read_write_permissions_and_validation(): void {
		$server = $this->bootRestControllers( [ KantineMargins::class ] );
		$reader = self::factory()->user->create( [ 'role' => 'rondo_bestuur' ] );
		UserRoles::sync_role_capabilities( 'rondo_bestuur' );
		wp_set_current_user( $reader );
		$this->assertSame( 200, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/twelve/margins' ) )->get_status() );
		remove_role( 'rondo_margin_reader_test' );
		add_role(
			'rondo_margin_reader_test',
			'Reader',
			[
				'read'       => true,
				'kassaomzet' => true,
			]
			);
		$read_only = self::factory()->user->create( [ 'role' => 'rondo_margin_reader_test' ] );
		update_user_meta( $read_only, 'rondo_user_approved', '1' );
		wp_set_current_user( $read_only );
		$this->assertFalse( ( new KantineMargins() )->can_write() );
		foreach ( [ 'product', 'purchase', 'preview' ] as $route ) {
			$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'POST', '/rondo/v1/twelve/margins/' . $route ) )->get_status() );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => UserRoles::ROLE_NAME ] ) );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/twelve/margins' ) )->get_status() );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/twelve/margins/invoice/123/pdf' ) )->get_status() );
		remove_role( 'rondo_margin_reader_test' );
	}

	public function test_invalid_invoices_dates_and_pdf_fail_without_storage(): void {
		$repository = new MarginRepository();
		$repository->register();
		$invoice                  = $this->invoice();
		$invoice['total_amount'] += 1;
		$this->assertInstanceOf( \WP_Error::class, $repository->save_purchase( $invoice ) );
		$this->assertCount( 0, $repository->invoices() );
		$this->assertFalse( MarginRepository::valid_date( '2026-02-30' ) );
		$this->assertInstanceOf( \WP_Error::class, ( new PurchaseParser() )->parse( 'not a pdf', 'invoice.pdf' ) );
	}
}
