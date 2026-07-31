<?php

namespace Tests\Wpunit;

use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/**
 * Covers canonical people ordering and the bounded legacy aliases.
 */
class PeopleOrderbyContractTest extends RondoTestCase {

	private People $controller;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new People();
	}

	/**
	 * Return filtered person IDs for an order identifier.
	 *
	 * @return int[]
	 */
	private function ordered_ids( string $orderby ): array {
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'ownership', 'all' );
		$request->set_param( 'orderby', $orderby );
		$request->set_param( 'order', 'asc' );

		$data = $this->controller->get_filtered_people( $request )->get_data();
		return array_map( 'intval', array_column( $data['people'], 'id' ) );
	}

	public function test_canonical_and_legacy_static_sort_identifiers_are_accepted(): void {
		$this->assertTrue( $this->controller->validate_orderby_param( 'field_knvb_id' ) );
		$this->assertTrue( $this->controller->validate_orderby_param( 'custom_knvb-id' ) );
		$this->assertTrue( $this->controller->validate_orderby_param( 'field_datum_vog' ) );
		$this->assertTrue( $this->controller->validate_orderby_param( 'custom_datum-vog' ) );
		$this->assertFalse( $this->controller->validate_orderby_param( 'field_relationships' ) );
		$this->assertFalse( $this->controller->validate_orderby_param( 'custom_not-a-field' ) );
	}

	public function test_legacy_alias_and_canonical_identifier_produce_identical_order(): void {
		$first_id  = $this->createPerson(
			[ 'post_title' => 'Zulu' ],
			[
				'first_name' => 'Zulu',
				'knvb-id'    => '100',
			]
		);
		$second_id = $this->createPerson(
			[ 'post_title' => 'Alpha' ],
			[
				'first_name' => 'Alpha',
				'knvb-id'    => '200',
			]
		);

		$canonical = $this->ordered_ids( 'field_knvb_id' );
		$legacy    = $this->ordered_ids( 'custom_knvb-id' );

		$this->assertSame( $canonical, $legacy );
		$this->assertSame( [ $first_id, $second_id ], $canonical );
	}
}
