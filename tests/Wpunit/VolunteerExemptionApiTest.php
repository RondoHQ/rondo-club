<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Volunteer;
use Rondo\Volunteer\VolunteerEligibilityService;
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
		Fields::update_for_post( $person_id, 'vrijgesteld_handmatig', 1 );
		Fields::update_for_post( $person_id, 'vrijstelling_reden', 'Tijdelijk' );
		Fields::update_for_post( $person_id, 'vrijstelling_seizoen', '2026-2027' );

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

	public function test_stale_person_reference_is_not_treated_as_an_exemption(): void {
		$this->assertNull( VolunteerExemptionResolver::resolve( 999999, '2026-2027' ) );
	}

	public function test_obligations_expose_unit_exemption_for_sync_consumers(): void {
		$season    = '2026-2027';
		$active_id = $this->createPerson( [ 'post_title' => 'Actieve speler' ] );
		$exempt_id = $this->createPerson( [ 'post_title' => 'Vrijgestelde speler' ] );
		update_post_meta( $active_id, 'leeftijdsgroep', 'Senioren' );
		update_post_meta( $exempt_id, 'leeftijdsgroep', 'Senioren' );
		Fields::update_for_post( $exempt_id, 'vrijgesteld_handmatig', 1 );
		Fields::update_for_post( $exempt_id, 'vrijstelling_seizoen', $season );
		VolunteerEligibilityService::invalidate_cache();

		$request = new WP_REST_Request( 'GET', '/rondo/v1/volunteer-obligations' );
		$request->set_param( 'season', $season );
		$data = ( new Volunteer() )->get_obligations( $request )->get_data();

		$units_by_person = [];
		foreach ( $data['units'] as $unit ) {
			$units_by_person[ (int) $unit['person_ids'][0] ] = $unit;
		}

		$this->assertFalse( $units_by_person[ $active_id ]['is_exempt'] );
		$this->assertNull( $units_by_person[ $active_id ]['exemption'] );
		$this->assertTrue( $units_by_person[ $exempt_id ]['is_exempt'] );
		$this->assertSame( 'handmatig', $units_by_person[ $exempt_id ]['exemption']['reason'] );
		$this->assertSame( $exempt_id, $units_by_person[ $exempt_id ]['exemption']['person_id'] );
	}
}
