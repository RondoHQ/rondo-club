<?php

namespace Tests\Wpunit;

use Rondo\Core\AutoTitle;
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

	public function test_sponsor_filter_only_returns_explicit_sponsors(): void {
		$contact_id = $this->createPerson(
			[ 'post_title' => 'Extern contact' ],
			[
				'first_name'  => 'Extern',
				'person_type' => 'contact',
			]
		);
		$sponsor_id = $this->createPerson(
			[ 'post_title' => 'Businessclublid' ],
			[
				'first_name'  => 'Businessclub',
				'person_type' => 'sponsor',
			]
		);

		$ids = $this->filtered_ids( 'sponsor' );

		$this->assertContains( $sponsor_id, $ids );
		$this->assertNotContains( $contact_id, $ids );
	}

	public function test_company_only_contact_uses_company_as_display_name(): void {
		$contact_id = $this->createPerson(
			[ 'post_title' => 'Tijdelijke titel' ],
			[
				'company_name' => 'Voorbeeld BV',
				'person_type'  => 'contact',
			]
		);

		$auto_title = new AutoTitle();
		$auto_title->auto_generate_person_title_rest( get_post( $contact_id ), new \WP_REST_Request() );

		$this->assertSame( 'Voorbeeld BV', get_the_title( $contact_id ) );

		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'ownership', 'all' );
		$request->set_param( 'orderby', 'first_name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'person_type', 'contact' );

		$data    = $this->controller->get_filtered_people( $request )->get_data();
		$contact = current( array_filter( $data['people'], static fn( $person ) => $person['id'] === $contact_id ) );

		$this->assertSame( 'Voorbeeld BV', $contact['name'] );
		$this->assertSame( 'Voorbeeld BV', $contact['acf']['company_name'] );
	}

	public function test_rest_identity_validation_accepts_company_without_first_name(): void {
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param(
			'acf',
			[
				'first_name'   => '',
				'company_name' => 'Voorbeeld BV',
				'person_type'  => 'contact',
			]
		);

		$prepared = new \stdClass();
		$this->assertSame( $prepared, $this->controller->validate_person_identity( $prepared, $request ) );
	}

	public function test_rest_identity_validation_accepts_company_only_sponsor(): void {
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param(
			'acf',
			[
				'first_name'   => '',
				'company_name' => 'Sponsor BV',
				'person_type'  => 'sponsor',
			]
		);

		$prepared = new \stdClass();
		$this->assertSame( $prepared, $this->controller->validate_person_identity( $prepared, $request ) );
	}

	public function test_rest_identity_validation_rejects_nameless_person(): void {
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param(
			'acf',
			[
				'first_name'   => '',
				'company_name' => '',
			]
		);

		$result = $this->controller->validate_person_identity( new \stdClass(), $request );

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_person_name_required', $result->get_error_code() );
	}

	public function test_rest_identity_validation_rejects_company_only_member(): void {
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param(
			'acf',
			[
				'first_name'   => '',
				'company_name' => 'Voorbeeld BV',
				'person_type'  => 'member',
			]
		);

		$result = $this->controller->validate_person_identity( new \stdClass(), $request );

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_company_only_contact_required', $result->get_error_code() );
	}
}
