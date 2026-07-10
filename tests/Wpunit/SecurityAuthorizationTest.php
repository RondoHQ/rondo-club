<?php

namespace Tests\Wpunit;

use Rondo\REST\Api;
use Rondo\REST\Fees;
use Rondo\REST\MemberShifts;
use Rondo\REST\UserSettings;
use Rondo\REST\Vog;
use Rondo\REST\Volunteer;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Regression coverage for member-facing authorization boundaries.
 */
class SecurityAuthorizationTest extends RondoTestCase {

	private WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		new Fees();
		new Vog();
		new Volunteer();
		new Api();
		do_action( 'rest_api_init' );
	}

	private function request( string $method, string $route, array $params = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	public function test_plain_member_cannot_read_club_finance_data(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'security_plain_finance' ] ) );

		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/fees' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/fees/summary' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/fees/person/123' )->get_status() );
	}

	public function test_plain_member_cannot_trigger_vog_bulk_operations(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'security_plain_vog' ] ) );

		$this->assertSame( 403, $this->request( 'POST', '/rondo/v1/vog/bulk-send', [ 'ids' => [ 1 ] ] )->get_status() );
		$this->assertSame( 403, $this->request( 'POST', '/rondo/v1/vog/bulk-mark-justis', [ 'ids' => [ 1 ] ] )->get_status() );
		$this->assertSame( 403, $this->request( 'POST', '/rondo/v1/vog/bulk-send-reminder', [ 'ids' => [ 1 ] ] )->get_status() );
	}

	public function test_plain_member_cannot_read_club_wide_volunteer_diagnostics(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'security_plain_volunteer' ] ) );

		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/volunteer-eligibility' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/volunteer-obligations' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/relationship-quality' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/volunteer-data-quality/orphan' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/volunteer-exemption/123' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/rondo/v1/iva/123/status' )->get_status() );
	}

	public function test_plain_member_cannot_use_email_enumeration_endpoint(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'security_plain_email' ] ) );

		$this->assertSame(
			403,
			$this->request( 'GET', '/rondo/v1/people/find-by-email', [ 'email' => 'member@example.com' ] )->get_status()
		);
	}

	public function test_specialist_capabilities_pass_their_permission_gates(): void {
		$finance_id = $this->createRondoUser( [ 'user_login' => 'security_finance_reader' ] );
		( new \WP_User( $finance_id ) )->add_cap( 'financieel_read' );
		wp_set_current_user( $finance_id );
		$this->assertTrue( ( new Fees() )->check_financieel_read_permission() );

		$vog_id = $this->createRondoUser( [ 'user_login' => 'security_vog_manager' ] );
		( new \WP_User( $vog_id ) )->add_cap( 'vog' );
		wp_set_current_user( $vog_id );
		$this->assertTrue( ( new Vog() )->check_vog_permission() );

		$volunteer_id = $this->createRondoUser( [ 'user_login' => 'security_volunteer_manager' ] );
		( new \WP_User( $volunteer_id ) )->add_cap( 'vrijwilligers' );
		wp_set_current_user( $volunteer_id );
		$this->assertTrue( ( new Volunteer() )->check_vrijwilligers_permission() );
	}

	public function test_member_cannot_remove_provisioned_person_link(): void {
		$user_id   = $this->createRondoUser( [ 'user_login' => 'security_linked_member' ] );
		$person_id = $this->createPerson( [ 'post_title' => 'Provisioned Member' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		update_post_meta( $person_id, UserProvisioning::META_USER_ID, $user_id );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/rondo/v1/user/linked-person' );
		$request->set_param( 'person_id', 0 );
		$result = ( new UserSettings() )->update_linked_person( $request );

		$this->assertWPError( $result );
		$this->assertSame( $person_id, (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
		$this->assertSame( $user_id, (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true ) );
	}

	public function test_iva_certificate_is_limited_to_self_or_specialist(): void {
		$owner_id  = $this->createRondoUser( [ 'user_login' => 'security_iva_owner' ] );
		$person_id = $this->createPerson( [ 'post_title' => 'IVA Owner' ] );
		$request   = new WP_REST_Request( 'GET', '/rondo/v1/iva/' . $person_id . '/certificate' );
		$request->set_param( 'person_id', $person_id );
		$controller = new Volunteer();

		update_user_meta( $owner_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $owner_id );
		$this->assertTrue( $controller->check_iva_certificate_permission( $request ) );

		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'security_iva_other' ] ) );
		$this->assertFalse( $controller->check_iva_certificate_permission( $request ) );

		$manager_id = $this->createRondoUser( [ 'user_login' => 'security_iva_manager' ] );
		( new \WP_User( $manager_id ) )->add_cap( 'vrijwilligers' );
		wp_set_current_user( $manager_id );
		$this->assertTrue( $controller->check_iva_certificate_permission( $request ) );
	}

	public function test_iva_migration_candidates_use_current_nonempty_meta(): void {
		$with_date = $this->createPerson( [ 'post_title' => 'IVA date' ] );
		$approved  = $this->createPerson( [ 'post_title' => 'IVA approved' ] );
		$empty     = $this->createPerson( [ 'post_title' => 'IVA empty' ] );
		update_post_meta( $with_date, 'datum-iva', '2026-01-01' );
		update_post_meta( $approved, 'iva-approved', '1' );
		update_post_meta( $empty, 'iva-certificaat', '' );

		$method = new \ReflectionMethod( Volunteer::class, 'collect_iva_person_ids' );
		$ids    = $method->invoke( new Volunteer() );

		$this->assertContains( $with_date, $ids );
		$this->assertContains( $approved, $ids );
		$this->assertNotContains( $empty, $ids );
	}

	public function test_member_shift_response_does_not_expose_other_assignees(): void {
		$user_id   = $this->createRondoUser( [ 'user_login' => 'security_shift_member' ] );
		$person_id = $this->createPerson( [ 'post_title' => 'Shift Member' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Private assignees',
			]
		);
		update_post_meta( $shift_id, 'start_datetime', '2026-08-01 10:00:00' );
		update_post_meta( $shift_id, 'end_datetime', '2026-08-01 12:00:00' );
		update_post_meta( $shift_id, 'assigned_persons', [ $person_id, 99999 ] );

		$response = ( new MemberShifts() )->get_my_shifts( new WP_REST_Request( 'GET', '/rondo/v1/my-shifts' ) );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data['shifts'] );
		$this->assertArrayNotHasKey( 'assigned_person_ids', $data['shifts'][0] );
	}
}
