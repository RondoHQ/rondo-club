<?php

namespace Tests\Wpunit;

use Rondo\REST\MemberShifts;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Regression coverage for the shared volunteer shift coverage calendar.
 */
class ShiftCalendarTest extends RondoTestCase {

	private MemberShifts $controller;
	private string $from;
	private string $to;
	private string $shift_date;

	protected function set_up(): void {
		parent::set_up();
		$this->controller = new MemberShifts();
		$this->from       = current_datetime()->format( 'Y-m-d' );
		$this->shift_date = current_datetime()->modify( '+2 days' )->format( 'Y-m-d' );
		$this->to         = current_datetime()->modify( '+7 days' )->format( 'Y-m-d' );
	}

	private function dienst_type( string $name, array $meta = [] ): int {
		$type_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => $name,
			]
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $type_id, $key, $value );
		}
		return $type_id;
	}

	private function shift( int $type_id, int $capacity, array $assigned, string $status = 'open', string $time = '10:00:00' ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Kalenderdienst',
			]
		);
		update_post_meta( $shift_id, 'dienst_type_id', $type_id );
		update_post_meta( $shift_id, 'start_datetime', $this->shift_date . ' ' . $time );
		update_post_meta( $shift_id, 'end_datetime', $this->shift_date . ' 12:00:00' );
		update_post_meta( $shift_id, 'capacity', $capacity );
		update_post_meta( $shift_id, 'assigned_persons', $assigned );
		update_post_meta( $shift_id, 'status', $status );
		return $shift_id;
	}

	private function calendar_request( string $view, int $dienst_type_id = 0 ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/rondo/v1/shifts/calendar' );
		$request->set_param( 'view', $view );
		$request->set_param( 'from', $this->from );
		$request->set_param( 'to', $this->to );
		if ( $dienst_type_id > 0 ) {
			$request->set_param( 'dienst_type_id', $dienst_type_id );
		}
		return $request;
	}

	public function test_manager_calendar_aggregates_open_spots_and_filters_by_type(): void {
		$manager_id = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $manager_id );
		$person_a  = $this->createPerson( [ 'post_title' => 'A' ] );
		$person_b  = $this->createPerson( [ 'post_title' => 'B' ] );
		$full_type = $this->dienst_type( 'Volle bardienst' );
		$open_type = $this->dienst_type( 'Open keukendienst' );
		$this->shift( $full_type, 2, [ $person_a, $person_b ], 'vol' );
		$this->shift( $open_type, 1, [] );

		$response = $this->controller->get_shift_calendar( $this->calendar_request( 'manage' ) );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['days'] );
		$this->assertSame( 'open', $data['days'][0]['state'] );
		$this->assertSame( 3, $data['days'][0]['capacity'] );
		$this->assertSame( 2, $data['days'][0]['assigned_count'] );
		$this->assertSame( 1, $data['days'][0]['spots_remaining'] );
		$this->assertCount( 2, $data['dienst_types'] );

		$filtered = $this->controller->get_shift_calendar( $this->calendar_request( 'manage', $full_type ) )->get_data();
		$this->assertSame( 'full', $filtered['days'][0]['state'] );
		$this->assertSame( 0, $filtered['days'][0]['spots_remaining'] );
		$this->assertCount( 2, $filtered['dienst_types'], 'Filteropties blijven compleet na selectie.' );
	}

	public function test_signup_calendar_includes_full_shifts_but_hides_ineligible_types_and_person_ids(): void {
		$user_id      = $this->createRondoUser();
		$person_id    = $this->createPerson( [ 'post_title' => 'Kalenderlid' ] );
		$colleague_id = $this->createPerson( [ 'post_title' => 'Collega' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$normal_type = $this->dienst_type( 'Algemene dienst' );
		$vog_type    = $this->dienst_type( 'VOG-dienst', [ 'vog_required' => 1 ] );
		$this->shift( $normal_type, 1, [ $colleague_id ], 'vol' );
		$this->shift( $vog_type, 1, [] );

		$response = $this->controller->get_shift_calendar( $this->calendar_request( 'signup' ) );
		$data     = $response->get_data();
		$shift    = $data['days'][0]['shifts'][0];

		$this->assertCount( 1, $data['days'][0]['shifts'] );
		$this->assertSame( $normal_type, $shift['dienst_type_id'] );
		$this->assertTrue( $shift['is_filled'] );
		$this->assertFalse( $shift['can_signup'] );
		$this->assertArrayNotHasKey( 'assigned_person_ids', $shift );
		$this->assertSame( [ 'Collega' ], $shift['fellow_volunteers'] );
		$this->assertContains( 'vog', $data['block_reasons'] );
	}

	public function test_plain_member_cannot_open_manager_calendar_and_large_ranges_are_rejected(): void {
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 'rest_forbidden', $this->controller->get_shift_calendar( $this->calendar_request( 'manage' ) )->get_error_code() );

		$manager_id = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $manager_id );
		$request = $this->calendar_request( 'manage' );
		$request->set_param( 'to', current_datetime()->modify( '+101 days' )->format( 'Y-m-d' ) );
		$this->assertSame( 'calendar_range_too_large', $this->controller->get_shift_calendar( $request )->get_error_code() );
	}

	public function test_direct_signup_rejects_former_members_and_missing_pool_membership(): void {
		$user_id   = $this->createRondoUser();
		$person_id = $this->createPerson( [ 'post_title' => 'Niet gerechtigd' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$type_id  = $this->dienst_type( 'Pooldienst' );
		$shift_id = $this->shift( $type_id, 1, [] );
		$request  = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/signup' );
		$request->set_param( 'id', $shift_id );

		update_post_meta( $person_id, 'former_member', 1 );
		$this->assertSame( 'not_eligible', $this->controller->signup( $request )->get_error_code() );

		update_post_meta( $person_id, 'former_member', 0 );
		update_post_meta( $type_id, 'required_pool', 99999 );
		$this->assertSame( 'pool_membership_required', $this->controller->signup( $request )->get_error_code() );
		$this->assertSame( [], get_post_meta( $shift_id, 'assigned_persons', true ) );
	}
}
