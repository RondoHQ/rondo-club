<?php

namespace Tests\Wpunit;

use Rondo\Access\AdmissionService;
use Rondo\Core\UserRoles;
use Rondo\Passes\MembershipPassQr;
use Rondo\REST\AccessEvents;
use Rondo\REST\Api;
use Rondo\REST\MembershipPasses;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

class EntreeAccountTest extends RondoTestCase {

	private int $user_id;

	protected function set_up(): void {
		parent::set_up();
		( new UserRoles() )->register_role();
		$this->user_id = self::factory()->user->create( [ 'role' => UserRoles::ENTREE_ROLE ] );
		wp_set_current_user( $this->user_id );
		$this->bootRestControllers( [ AccessEvents::class, MembershipPasses::class, UserSettings::class, Api::class ] );
	}

	public function test_account_has_no_member_link_or_general_data_capabilities(): void {
		$this->assertFalse( UserRoles::is_kader() );
		$this->assertFalse( UserRoles::has_extra_staff_role() );
		$this->assertSame( '', get_user_meta( $this->user_id, 'rondo_linked_person_id', true ) );
		$this->assertTrue( current_user_can( 'toegangscontrole' ) );
		$capabilities = array_keys( array_filter( get_role( UserRoles::ENTREE_ROLE )->capabilities ) );
		sort( $capabilities );
		$this->assertSame( [ 'read', 'toegangscontrole' ], $capabilities );
		foreach ( [ 'edit_posts', 'upload_files', 'manage_options' ] as $cap ) {
			$this->assertFalse( current_user_can( $cap ), $cap );
		}
		$response = rest_do_request( '/rondo/v1/user/me' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['is_entree'] );
		$this->assertNull( $response->get_data()['linked_person_id'] );
		$this->assertTrue( $response->get_data()['can_access_toegangscontrole'] );
		$this->assertSame( home_url( '/lidpas-scanner' ), rondo_login_redirect( home_url( '/people' ), '', wp_get_current_user() ) );
	}

	public function test_only_scanner_routes_and_methods_are_accessible(): void {
		foreach ( [ '/rondo/v1/version', '/rondo/v1/access-events/matches' ] as $route ) {
			$this->assertSame( 200, rest_do_request( $route )->get_status(), $route );
		}
		foreach ( [
			'/wp/v2/people',
			'/wp/v2/users/me',
			'/wp/v2/teams',
			'/wp/v2/media',
			'/rondo/v1/dashboard',
			'/rondo/v1/search',
			'/rondo/v1/access-events',
			'/rondo/v1/fees',
			'/rondo/v1/user/linked-person',
			'/batch/v1',
		] as $route ) {
			foreach ( [ 'GET', 'POST', 'DELETE' ] as $method ) {
				$response = rest_do_request( new \WP_REST_Request( $method, $route ) );
				$this->assertSame( 403, $response->get_status(), $method . ' ' . $route );
				$this->assertSame( 'rondo_entree_only', $response->get_data()['code'] );
			}
		}
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'POST', '/rondo/v1/user/me' ) )->get_status() );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/access-events/select' ) )->get_status() );
		// The allowed selection route reaches normal parameter validation.
		$this->assertSame( 400, rest_do_request( new \WP_REST_Request( 'POST', '/rondo/v1/access-events/select' ) )->get_status() );
	}

	public function test_shared_account_can_verify_and_count_a_pass_once(): void {
		$person = $this->createPerson(
			[],
			[
				'type_lid' => 'Bondslid',
				'knvb_id'  => 'ENTREE1',
			]
			);
		$issued = ( new MembershipPassQr() )->issue_for_person( $person );
		$this->assertNotWPError( $issued );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/membership-passes/verify' );
		$request->set_param( 'token', $issued['token'] );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['valid'] );

		$event = ( new AdmissionService( false ) )->select_match(
			[
				'id'            => 'entree-test',
				'club_side'     => 'home',
				'home_team'     => 'AWC 1',
				'away_team'     => 'Bezoekers',
				'starts_at'     => gmdate( 'c' ),
				'is_selectable' => true,
			]
		);
		$this->assertNotWPError( $event );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/access-events/' . $event['id'] . '/scan' );
		$request->set_param( 'token', $issued['token'] );
		$first = rest_do_request( $request );
		$this->assertSame( 200, $first->get_status() );
		$this->assertTrue( $first->get_data()['admission']['counted'] );
		$second = rest_do_request( $request );
		$this->assertTrue( $second->get_data()['admission']['duplicate'] );
		$stats = rest_do_request( '/rondo/v1/access-events/' . $event['id'] . '/stats' );
		$this->assertSame( 200, $stats->get_status() );
		$this->assertSame( 1, $stats->get_data()['total'] );
		$this->assertSame( 403, rest_do_request( '/wp/v2/people/' . $person )->get_status() );
	}

	public function test_extra_roles_do_not_bypass_the_scanner_boundary(): void {
		wp_get_current_user()->add_role( 'rondo_bestuur' );
		$this->assertTrue( UserRoles::is_entree() );
		$this->assertFalse( UserRoles::is_kader() );
		$this->assertSame( 403, rest_do_request( '/rondo/v1/dashboard' )->get_status() );
	}

	public function test_email_login_does_not_require_a_linked_member(): void {
		$_POST['log'] = 'entree@example.test';
		try {
			$bridge = new \Rondo\Users\MagicLoginActivation();
			$this->assertNull( $bridge->intercept_send( null, wp_get_current_user() ) );
			$error = new \WP_Error( 'rate_limited', 'Too many requests' );
			$this->assertSame( $error, $bridge->intercept_send( $error, wp_get_current_user() ) );
		} finally {
			unset( $_POST['log'] );
		}
	}

	public function test_existing_staff_scanner_role_keeps_its_access(): void {
		wp_get_current_user()->set_role( 'rondo_toegangscontrole' );
		$this->assertFalse( UserRoles::is_entree() );
		$this->assertTrue( UserRoles::is_kader() );
		$this->assertSame( 200, rest_do_request( '/rondo/v1/access-events' )->get_status() );
		$this->assertArrayNotHasKey( 'is_entree', rest_do_request( '/rondo/v1/user/me' )->get_data() );
	}
}
