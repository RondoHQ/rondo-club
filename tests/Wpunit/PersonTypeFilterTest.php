<?php

namespace Tests\Wpunit;

use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/**
 * Covers the explicit Rondo person type used for external address-book contacts.
 */
class PersonTypeFilterTest extends RondoTestCase {

	private People $controller;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new People();
	}

	private function filtered_ids( string $person_type ): array {
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'ownership', 'all' );
		$request->set_param( 'orderby', 'first_name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'person_type', $person_type );

		$response = $this->controller->get_filtered_people( $request );
		$data     = $response->get_data();

		return array_column( $data['people'], 'id' );
	}

	public function test_contact_filter_only_returns_explicit_contacts(): void {
		$member_id  = $this->createPerson( [ 'post_title' => 'Bestaand lid' ], [ 'first_name' => 'Bestaand' ] );
		$contact_id = $this->createPerson(
			[ 'post_title' => 'Extern contact' ],
			[
				'first_name'  => 'Extern',
				'person_type' => 'contact',
			]
		);

		$ids = $this->filtered_ids( 'contact' );

		$this->assertContains( $contact_id, $ids );
		$this->assertNotContains( $member_id, $ids );
	}

	public function test_member_filter_keeps_legacy_people_without_person_type(): void {
		$legacy_id  = $this->createPerson( [ 'post_title' => 'Legacy lid' ], [ 'first_name' => 'Legacy' ] );
		$member_id  = $this->createPerson(
			[ 'post_title' => 'Expliciet lid' ],
			[
				'first_name'  => 'Expliciet',
				'person_type' => 'member',
			]
		);
		$contact_id = $this->createPerson(
			[ 'post_title' => 'Extern contact' ],
			[
				'first_name'  => 'Extern',
				'person_type' => 'contact',
			]
		);

		$ids = $this->filtered_ids( 'member' );

		$this->assertContains( $legacy_id, $ids );
		$this->assertContains( $member_id, $ids );
		$this->assertNotContains( $contact_id, $ids );
	}
}
