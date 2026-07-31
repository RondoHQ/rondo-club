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
		$this->shift( $normal_type, 1, [ $colleague_id ], 'vol' );

		$without_vog_shift = $this->controller->get_shift_calendar( $this->calendar_request( 'signup' ) )->get_data();
		$this->assertNotContains( 'vog', $without_vog_shift['block_reasons'] );
		$this->assertNotContains(
			'vog',
			$this->controller->get_available_shifts( new WP_REST_Request( 'GET', '/rondo/v1/shifts/available' ) )->get_data()['block_reasons']
		);

		$vog_type = $this->dienst_type( 'VOG-dienst', [ 'vog_required' => 1 ] );
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
		$this->assertContains(
			'vog',
			$this->controller->get_available_shifts( new WP_REST_Request( 'GET', '/rondo/v1/shifts/available' ) )->get_data()['block_reasons']
		);

		$normal_type_data = $this->controller->get_shift_calendar( $this->calendar_request( 'signup', $normal_type ) )->get_data();
		$this->assertNotContains( 'vog', $normal_type_data['block_reasons'] );
	}

	public function test_signup_calendar_shows_vog_shift_without_warning_when_vog_is_valid(): void {
		$user_id   = $this->createRondoUser();
		$person_id = $this->createPerson( [ 'post_title' => 'Lid met VOG' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'datum_vog', current_datetime()->format( 'Ymd' ) );
		wp_set_current_user( $user_id );

		$vog_type = $this->dienst_type( 'Toegankelijke VOG-dienst', [ 'vog_required' => 1 ] );
		$this->shift( $vog_type, 1, [] );

		$data = $this->controller->get_shift_calendar( $this->calendar_request( 'signup' ) )->get_data();

		$this->assertNotContains( 'vog', $data['block_reasons'] );
		$this->assertCount( 1, $data['days'][0]['shifts'] );
		$this->assertSame( $vog_type, $data['days'][0]['shifts'][0]['dienst_type_id'] );
		$this->assertTrue( $data['days'][0]['shifts'][0]['can_signup'] );
	}

	public function test_plain_member_cannot_open_manager_calendar_and_large_ranges_are_rejected(): void {
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 'rest_forbidden', $this->controller->get_shift_calendar( $this->calendar_request( 'manage' ) )->get_error_code() );

		$manager_id = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $manager_id );
		$request = $this->calendar_request( 'manage' );
		// The ceiling has to clear a full club year (1 Aug - 30 Jun) plus the
		// July edge, so it sits at 370 days rather than the original 190.
		$request->set_param( 'to', current_datetime()->modify( '+371 days' )->format( 'Y-m-d' ) );
		$this->assertSame( 'calendar_range_too_large', $this->controller->get_shift_calendar( $request )->get_error_code() );
	}

	/**
	 * The default range is the club year, not a rolling six months: coordinators
	 * plan the whole season and members are shown what is coming, even when part
	 * of it is not open for signup yet.
	 */
	public function test_default_calendar_range_covers_the_club_year(): void {
		$today = current_datetime()->setTime( 0, 0, 0 );

		$manager_id = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $manager_id );
		$manager_request = new WP_REST_Request( 'GET', '/rondo/v1/shifts/calendar' );
		$manager_request->set_param( 'view', 'manage' );
		$manager_data = $this->controller->get_shift_calendar( $manager_request )->get_data();

		$season_end = substr( \Rondo\Fees\SeasonKey::current( $today->format( 'Y-m-d' ) ), 5, 4 ) . '-06-30';

		$this->assertSame( $today->format( 'Y-m-d' ), $manager_data['from'] );
		$this->assertSame( $season_end, $manager_data['to'] );

		$user_id   = $this->createRondoUser();
		$person_id = $this->createPerson( [ 'post_title' => 'Kalenderlid' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );
		$member_request = new WP_REST_Request( 'GET', '/rondo/v1/shifts/calendar' );
		$member_request->set_param( 'view', 'signup' );
		$member_data = $this->controller->get_shift_calendar( $member_request )->get_data();

		$this->assertSame( $season_end, $member_data['to'] );
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

	public function test_direct_signup_still_rejects_missing_vog(): void {
		$user_id   = $this->createRondoUser();
		$person_id = $this->createPerson( [ 'post_title' => 'Lid zonder VOG' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$vog_type = $this->dienst_type( 'Beveiligde VOG-dienst', [ 'vog_required' => 1 ] );
		$shift_id = $this->shift( $vog_type, 1, [] );
		$request  = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/signup' );
		$request->set_param( 'id', $shift_id );

		$response = $this->controller->signup( $request );

		$this->assertSame( 'vog_required', $response->get_error_code() );
		$this->assertSame( [], get_post_meta( $shift_id, 'assigned_persons', true ) );
	}

	public function test_signup_allows_consecutive_shifts_with_mixed_datetime_precision(): void {
		$user_id   = $this->createRondoUser();
		$person_id = $this->createPerson( [ 'post_title' => 'Aansluitende vrijwilliger' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$type_id      = $this->dienst_type( 'Aansluitende diensten' );
		$existing_id  = $this->shift( $type_id, 1, [ $person_id ], 'vol', '10:00' );
		$candidate_id = $this->shift( $type_id, 1, [], 'open', '12:00' );
		update_post_meta( $candidate_id, 'end_datetime', $this->shift_date . ' 14:00:00' );

		$this->assertSame( $this->shift_date . ' 12:00:00', get_post_meta( $existing_id, 'end_datetime', true ) );
		$this->assertSame( $this->shift_date . ' 12:00', get_post_meta( $candidate_id, 'start_datetime', true ) );

		$request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $candidate_id . '/signup' );
		$request->set_param( 'id', $candidate_id );
		$response = $this->controller->signup( $request );

		$this->assertNotWPError( $response );
		$this->assertTrue( $response->get_data()['signed_up'] );
		$this->assertSame( [ $person_id ], get_post_meta( $candidate_id, 'assigned_persons', true ) );
	}

	public function test_overlap_warning_can_be_forced_and_returns_candidate_shift_id(): void {
		$user_id   = $this->createRondoUser();
		$person_id = $this->createPerson( [ 'post_title' => 'Overlappende vrijwilliger' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$type_id      = $this->dienst_type( 'Overlappende diensten' );
		$existing_id  = $this->shift( $type_id, 1, [ $person_id ], 'vol', '10:00:00' );
		$candidate_id = $this->shift( $type_id, 1, [], 'open', '11:00:00' );

		$request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $candidate_id . '/signup' );
		$request->set_param( 'id', $candidate_id );
		$response = $this->controller->signup( $request );

		$this->assertWPError( $response );
		$this->assertSame( 'overlap_warning', $response->get_error_code() );
		$this->assertSame( $candidate_id, $response->get_error_data()['shift_id'] );
		$this->assertSame( $existing_id, $response->get_error_data()['overlap_shift']['id'] );
		$this->assertTrue( $response->get_error_data()['can_force'] );

		$request->set_param( 'force_overlap', true );
		$forced_response = $this->controller->signup( $request );

		$this->assertNotWPError( $forced_response );
		$this->assertTrue( $forced_response->get_data()['signed_up'] );
		$this->assertSame( [ $person_id ], get_post_meta( $candidate_id, 'assigned_persons', true ) );
	}
}
