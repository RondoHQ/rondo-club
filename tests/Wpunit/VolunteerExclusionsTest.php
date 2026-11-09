<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Fields\RestFields;
use Rondo\REST\Volunteer;
use Tests\Support\RondoTestCase;

class VolunteerExclusionsTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		$this->bootRestControllers( [ Volunteer::class ] );
	}

	private function request( string $method, string $path = '', array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rondo/v1/volunteer-exclusions' . $path );
		$request->set_body_params( $params );
		return rest_do_request( $request );
	}

	public function test_create_list_repeat_and_remove_use_canonical_fields_and_storage(): void {
		$id = $this->createPerson( [ 'post_title' => 'Anne Voorbeeld' ] );
		$this->createPerson( [ 'post_title' => 'Niet uitgesloten' ] );
		$response = $this->request(
			'PUT',
			'/' . $id,
			[
				'enabled' => true,
				'reason'  => 'Herhaald geweigerd',
			]
			);
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$fields = $response->get_data()['fields'];
		$this->assertTrue( $fields['planner_excluded'] );
		$this->assertSame( 'Herhaald geweigerd', $fields['planner_exclusion_reason'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $fields['planner_excluded_since'] );
		$this->assertSame( '1', get_post_meta( $id, 'planner_excluded', true ) );
		$this->assertSame( 'Herhaald geweigerd', get_post_meta( $id, 'planner_exclusion_reason', true ) );
		$this->assertSame( current_time( 'Y-m-d' ), substr( get_post_meta( $id, 'planner_excluded_since', true ), 0, 10 ) );
		$listed = $this->request( 'GET' );
		$this->assertSame( [ $id ], array_column( $listed->get_data(), 'id' ) );
		$this->assertSame( 'private, no-store', $listed->get_headers()['Cache-Control'] );
		Fields::update_for_post( $id, 'planner_excluded_since', '2026-10-01 10:00:00' );
		$again = $this->request(
			'PUT',
			'/' . $id,
			[
				'enabled' => true,
				'reason'  => 'Bijgewerkt',
			]
			);
		$this->assertSame( '2026-10-01T10:00:00+02:00', $again->get_data()['fields']['planner_excluded_since'] );
		$removed = $this->request( 'PUT', '/' . $id, [ 'enabled' => false ] );
		$this->assertFalse( $removed->get_data()['fields']['planner_excluded'] );
		$this->assertEmpty( $removed->get_data()['fields']['planner_exclusion_reason'] );
		$this->assertEmpty( $removed->get_data()['fields']['planner_excluded_since'] );
		$this->assertEmpty( $this->request( 'GET' )->get_data() );
	}

	public function test_invalid_inputs_and_unavailable_records_do_not_write(): void {
		$id = $this->createPerson();
		foreach ( [
			[ 'enabled' => true ],
			[
				'enabled' => true,
				'reason'  => '   ',
			],
			[
				'enabled' => 'garbage',
				'reason'  => 'Test',
			],
			[
				'enabled' => true,
				'reason'  => str_repeat( 'a', 1001 ),
			],
		] as $params ) {
			$this->assertSame( 400, $this->request( 'PUT', '/' . $id, $params )->get_status(), wp_json_encode( $params ) );
		}
		$this->assertFalse( (bool) Fields::get_for_post( $id, 'planner_excluded' ) );
		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'draft',
			]
			);
		$this->assertSame(
			404,
			$this->request(
			'PUT',
			'/' . $id,
			[
				'enabled' => true,
				'reason'  => 'Test',
			]
			)->get_status()
			);
		$team = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
			]
			);
		$this->assertSame(
			404,
			$this->request(
			'PUT',
			'/' . $team,
			[
				'enabled' => true,
				'reason'  => 'Test',
			]
			)->get_status()
			);
	}

	public function test_other_roles_cannot_read_or_change_exclusions_even_with_person_access(): void {
		$id = $this->createPerson();
		$this->request(
			'PUT',
			'/' . $id,
			[
				'enabled' => true,
				'reason'  => 'Privéreden',
			]
			);
		$user = $this->createRondoUser();
		( new \WP_User( $user ) )->add_cap( 'ledenadministratie' );
		wp_set_current_user( $user );
		$this->assertSame( 403, $this->request( 'GET' )->get_status() );
		$this->assertSame( 403, $this->request( 'PUT', '/' . $id, [ 'enabled' => false ] )->get_status() );
		$fields = RestFields::for_post( 'person', $id );
		foreach ( [ 'planner_excluded', 'planner_exclusion_reason', 'planner_excluded_since' ] as $name ) {
			$this->assertArrayNotHasKey( $name, $fields );
		}
		$this->assertTrue( (bool) Fields::get_for_post( $id, 'planner_excluded' ) );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET' )->get_status() );
	}

	public function test_general_person_endpoint_cannot_bypass_exclusion_management(): void {
		$id = $this->createPerson();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $id );
		$request->set_body_params( [ 'fields' => [ 'planner_excluded' => true ] ] );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		$this->assertFalse( (bool) Fields::get_for_post( $id, 'planner_excluded' ) );
	}
}
