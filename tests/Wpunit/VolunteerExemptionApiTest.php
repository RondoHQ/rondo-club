<?php

namespace Tests\Wpunit;

use Rondo\REST\Volunteer;
use Rondo\Volunteer\VolunteerExemptionResolver;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Tests the volunteer manager workflow for manual exemptions.
 */
class VolunteerExemptionApiTest extends RondoTestCase {

	private WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		$this->server = $this->bootRestControllers( [ Volunteer::class ] );

		$user_id = $this->createRondoUser( [ 'user_login' => 'manual_exemption_manager' ] );
		( new \WP_User( $user_id ) )->add_cap( 'vrijwilligers' );
		wp_set_current_user( $user_id );
	}

	private function request( string $method, int $person_id, array $params = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, '/rondo/v1/volunteer-exemption/' . $person_id );
		$request->set_body_params( $params );
		return $this->server->dispatch( $request );
	}

	public function test_volunteer_manager_can_create_and_read_manual_exemption(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Vrijgesteld Lid' ] );

		$response = $this->request(
			'PUT',
			$person_id,
			[
				'enabled' => true,
				'reason'  => 'Langdurig ziek',
				'season'  => '2026-2027',
			]
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['manual']['enabled'] );
		$this->assertSame( 'Langdurig ziek', $data['manual']['reason'] );
		$this->assertSame( '2026-2027', $data['manual']['season'] );
		$this->assertSame( 'handmatig', $data['reason'] );
		$this->assertTrue( VolunteerExemptionResolver::is_exempt( $person_id, '2026-2027' ) );
		$this->assertFalse( VolunteerExemptionResolver::is_exempt( $person_id, '2027-2028' ) );

		$get_response = $this->request( 'GET', $person_id );
		$get_data     = $get_response->get_data();

		$this->assertSame( 'Vrijgesteld Lid', $get_data['person_name'] );
		$this->assertTrue( $get_data['manual']['enabled'] );
	}

	public function test_disabling_manual_exemption_clears_its_details(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Tijdelijk Vrijgesteld' ] );
		update_field( 'field_vrijgesteld_handmatig', 1, $person_id );
		update_field( 'field_vrijstelling_reden', 'Tijdelijk', $person_id );
		update_field( 'field_vrijstelling_seizoen', '2026-2027', $person_id );

		$response = $this->request(
			'PUT',
			$person_id,
			[
				'enabled' => false,
				'reason'  => 'Wordt genegeerd',
				'season'  => '2026-2027',
			]
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $data['manual']['enabled'] );
		$this->assertSame( '', $data['manual']['reason'] );
		$this->assertSame( '', $data['manual']['season'] );
		$this->assertFalse( VolunteerExemptionResolver::is_exempt( $person_id, '2026-2027' ) );
	}

	public function test_invalid_season_is_rejected_without_writing(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Ongeldig Seizoen' ] );

		$response = $this->request(
			'PUT',
			$person_id,
			[
				'enabled' => true,
				'season'  => '2026-2028',
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( '', get_post_meta( $person_id, 'vrijgesteld_handmatig', true ) );
	}

	public function test_unknown_person_is_rejected(): void {
		$response = $this->request( 'PUT', 999999, [ 'enabled' => true ] );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'invalid_person', $response->get_data()['code'] );
	}
}
