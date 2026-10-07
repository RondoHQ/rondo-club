<?php

namespace Tests\Wpunit;

use Rondo\Fees\FeeServices;
use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\REST\Fees;
use Tests\Support\RondoTestCase;

/** Names on the profile's financial card must include surname prefixes. */
class FamilyMemberNameTest extends RondoTestCase {

	private int $lars;
	private int $person;
	private int $milan;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		FeeServices::settings()->save_categories_for_season(
			[
				'pupil' => [
					'label'       => 'Pupil',
					'amount'      => 100,
					'age_classes' => [ 'Onder 10' ],
					'is_youth'    => true,
					'sort_order'  => 10,
				],
			],
			SeasonKey::current()
		);
		$this->lars   = $this->member( 'Lars', 'van der', 'Meer' );
		$this->person = $this->member( 'Anne', '', 'Jansen' );
		$this->milan  = $this->member( 'Milan', 'van der', 'Meer' );
	}

	private function member( string $first, string $infix, string $last ): int {
		return $this->createPerson(
			[],
			[
				'first_name'     => $first,
				'infix'          => $infix,
				'last_name'      => $last,
				'leeftijdsgroep' => 'Onder 10',
				'spelactiviteit' => 'Veld - Algemeen',
				'addresses'      => [
					[
						'house_number' => '12',
						'postal_code'  => '1234 AB',
					],
				],
			]
		);
	}

	private function response(): array {
		$server   = $this->bootRestControllers( [ Fees::class ] );
		$response = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/fees/person/' . $this->person ) );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	private function assert_family_names( array $fee ): void {
		$this->assertSame( 2, $fee['family_position'] );
		$this->assertSame( 3, $fee['family_size'] );
		$this->assertSame( [ $this->lars, $this->milan ], array_column( $fee['family_members'], 'id' ) );
		$this->assertSame( [ 'Lars van der Meer', 'Milan van der Meer' ], array_column( $fee['family_members'], 'name' ) );
	}

	public function test_calculator_includes_infix_in_uncached_sibling_names(): void {
		$fee = FeeServices::fee_calculator()->calculate_fee_with_family_discount( $this->person );
		$this->assert_family_names( $fee );

		$fee = FeeServices::fee_calculator()->calculate_fee_with_family_discount( $this->lars );
		$this->assertSame( 'Anne Jansen', $fee['family_members'][0]['name'] );
	}

	public function test_rest_returns_name_parts_for_the_shared_frontend_formatter(): void {
		$fee = $this->response();
		$this->assert_family_names( $fee );
		$this->assertSame( 'Lars', $fee['family_members'][0]['first_name'] );
		$this->assertSame( 'van der', $fee['family_members'][0]['infix'] );
		$this->assertSame( 'Meer', $fee['family_members'][0]['last_name'] );
	}

	public function test_stored_discount_path_derives_complete_family_names(): void {
		update_post_meta( $this->person, '_family_discount_rate', '0.25' );
		update_post_meta( $this->person, '_family_discount_position', '2' );
		$this->assert_family_names( $this->response() );
	}

	public function test_rest_refreshes_shortened_names_in_existing_fee_caches(): void {
		$fee                              = FeeServices::fee_calculator()->calculate_full_fee( $this->person );
		$fee['family_members'][0]['name'] = 'Lars Meer';
		$fee['family_members'][1]['name'] = 'Milan Meer';
		FeeServices::fee_cache()->save_fee_cache( $this->person, $fee );

		$response = $this->response();
		$this->assertTrue( $response['from_cache'] );
		$this->assert_family_names( $response );
	}

	public function test_sibling_without_personal_fields_keeps_title_fallback(): void {
		foreach ( [ 'first_name', 'infix', 'last_name' ] as $field ) {
			Fields::delete_for_post( $this->lars, $field );
		}
		wp_update_post(
			[
				'ID'         => $this->lars,
				'post_title' => 'Naam ontbreekt',
			]
			);
		$fee = $this->response();
		$this->assertSame( 'Naam ontbreekt', $fee['family_members'][0]['name'] );
	}
}
