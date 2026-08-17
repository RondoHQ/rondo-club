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

	/**
	 * Every sortable column the VOG list offers is a `date_time_picker`. Omitting
	 * that type from the allowlist turned each of those sorts into a hard 400.
	 */
	public function test_datetime_sort_identifiers_are_accepted(): void {
		$this->assertTrue( $this->controller->validate_orderby_param( 'field_vog_email_sent_date' ) );
		$this->assertTrue( $this->controller->validate_orderby_param( 'custom_vog_email_sent_date' ) );
		$this->assertTrue( $this->controller->validate_orderby_param( 'field_vog_justis_submitted_date' ) );
		$this->assertTrue( $this->controller->validate_orderby_param( 'field_vog_reminder_sent_date' ) );
	}

	public function test_datetime_sort_orders_chronologically(): void {
		$later_id   = $this->createPerson(
			[ 'post_title' => 'Later' ],
			[
				'first_name'          => 'Later',
				'vog_email_sent_date' => '2026-08-01T09:00:00+02:00',
			]
		);
		$earlier_id = $this->createPerson(
			[ 'post_title' => 'Earlier' ],
			[
				'first_name'          => 'Earlier',
				'vog_email_sent_date' => '2026-07-01T09:00:00+02:00',
			]
		);

		$ordered = $this->ordered_ids( 'field_vog_email_sent_date' );

		// Persons without the field sort first on an ascending text sort, so assert
		// the relative order of the two that have it rather than the whole list.
		$this->assertContains( $earlier_id, $ordered );
		$this->assertContains( $later_id, $ordered );
		$this->assertLessThan(
			array_search( $later_id, $ordered, true ),
			array_search( $earlier_id, $ordered, true ),
			'The earlier vog_email_sent_date must sort before the later one.'
		);
	}

	public function test_last_name_sort_ignores_infix_and_uses_first_name_as_tie_breaker(): void {
		$valk_b_id = $this->createPerson(
			[ 'post_title' => 'Bram de Valk' ],
			[
				'first_name' => 'Bram',
				'infix'      => 'de',
				'last_name'  => 'Valk',
			]
		);
		$akker_id  = $this->createPerson(
			[ 'post_title' => 'Zara van den Akker' ],
			[
				'first_name' => 'Zara',
				'infix'      => 'van den',
				'last_name'  => 'Akker',
			]
		);
		$valk_a_id = $this->createPerson(
			[ 'post_title' => 'Anne Valk' ],
			[
				'first_name' => 'Anne',
				'last_name'  => 'Valk',
			]
		);

		$ordered = $this->ordered_ids( 'last_name' );

		$this->assertLessThan( array_search( $valk_a_id, $ordered, true ), array_search( $akker_id, $ordered, true ) );
		$this->assertLessThan( array_search( $valk_b_id, $ordered, true ), array_search( $valk_a_id, $ordered, true ) );
	}

	public function test_first_and_displayed_surname_filters_are_applied_server_side(): void {
		$match_id = $this->createPerson(
			[ 'post_title' => 'Joost de Valk' ],
			[
				'first_name' => 'Joost',
				'infix'      => 'de',
				'last_name'  => 'Valk',
			]
		);
		$this->createPerson(
			[ 'post_title' => 'Joost Jansen' ],
			[
				'first_name' => 'Joost',
				'last_name'  => 'Jansen',
			]
		);

		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'ownership', 'all' );
		$request->set_param( 'orderby', 'last_name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'first_name', 'Joo' );
		$request->set_param( 'last_name', 'de Valk' );

		$data = $this->controller->get_filtered_people( $request )->get_data();
		$this->assertSame( [ $match_id ], array_map( 'intval', array_column( $data['people'], 'id' ) ) );
	}
}
