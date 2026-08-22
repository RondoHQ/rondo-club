<?php

namespace Tests\Wpunit;

use Rondo\Finance\InvoiceEmailSender;
use Rondo\People\CommunicationPolicy;
use Rondo\REST\People;
use Rondo\Volunteer\VolunteerEligibilityService;
use Tests\Support\RondoTestCase;

/** Covers deceased-person visibility and communication safety. */
class DeceasedPeopleTest extends RondoTestCase {

	private People $controller;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new People();
	}

	/** @return array<string,mixed> */
	private function filtered_data( array $filters = [] ): array {
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'ownership', 'all' );
		$request->set_param( 'orderby', 'first_name' );
		$request->set_param( 'order', 'asc' );
		foreach ( $filters as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->controller->get_filtered_people( $request )->get_data();
	}

	public function test_filtered_people_excludes_deceased_by_default_and_can_include_them(): void {
		$living_id   = $this->createPerson(
			[ 'post_title' => 'Living person' ],
			[ 'first_name' => 'Living' ]
		);
		$deceased_id = $this->createPerson(
			[ 'post_title' => 'Deceased former member' ],
			[
				'first_name'       => 'Deceased',
				'former_member'    => true,
				'datum_overlijden' => '2026-08-20',
			]
		);

		$default_ids = array_map( 'intval', array_column( $this->filtered_data()['people'], 'id' ) );
		$this->assertContains( $living_id, $default_ids );
		$this->assertNotContains( $deceased_id, $default_ids );

		$included = $this->filtered_data( [ 'include_deceased' => '1' ] )['people'];
		$record   = current( array_filter( $included, static fn( array $person ): bool => $person['id'] === $deceased_id ) );
		$this->assertIsArray( $record );
		$this->assertTrue( $record['former_member'] );
		$this->assertTrue( $record['is_deceased'] );
	}

	public function test_deceased_people_have_no_automated_communication_address(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Deceased person' ],
			[
				'email_1'          => 'history@example.com',
				'email_2'          => 'archive@example.com',
				'datum_overlijden' => '2026-08-20',
			]
		);

		$this->assertTrue( CommunicationPolicy::is_deceased( $person_id ) );
		$this->assertFalse( CommunicationPolicy::may_contact( $person_id ) );
		$this->assertSame( [], CommunicationPolicy::email_addresses( $person_id ) );
		$this->assertSame( [], InvoiceEmailSender::resolve_invoice_recipient_emails( $person_id ) );
		$this->assertFalse( ( new VolunteerEligibilityService() )->may_volunteer( $person_id ) );
	}
}
