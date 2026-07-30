<?php

namespace Tests\Wpunit;

use Rondo\Config\ClubConfig;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * The signup window at the REST layer.
 *
 * Two properties matter beyond "the POST is refused":
 *
 * 1. Closed diensten stay **visible**. The window must never be routed through
 *    member_shift_block_reason(), whose callers all `continue` — that would hide
 *    the spring from members instead of showing them when it opens.
 * 2. Coordinators are **not** gated. They plan; they are not racing for a slot.
 */
class ShiftSignupWindowEnforcementTest extends RondoTestCase {

	private int $person_id;
	private int $user_id;

	protected function set_up(): void {
		parent::set_up();

		do_action( 'rest_api_init' );

		$this->person_id = $this->createPerson(
			[ 'post_title' => 'Jan Jansen' ],
			[
				'first_name' => 'Jan',
				'type-lid'   => 'Senior',
				'email_1'    => 'jan@example.com',
			]
		);

		$this->user_id = $this->createRondoUser();
		update_user_meta( $this->user_id, 'rondo_linked_person_id', $this->person_id );
		wp_set_current_user( $this->user_id );
	}

	protected function tear_down(): void {
		delete_option( ClubConfig::OPTION_VOLUNTEER_SECOND_HALF_OPENS );
		parent::tear_down();
	}

	/**
	 * A shift in the second half of the current season — i.e. one that is closed
	 * whenever "now" is before this season's opening date.
	 */
	private function create_second_half_shift(): int {
		$now        = current_datetime();
		$season_end = (int) $now->format( 'n' ) >= 7
			? (int) $now->format( 'Y' ) + 1
			: (int) $now->format( 'Y' );
		$start      = sprintf( '%d-03-06 10:00:00', $season_end );

		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Kantinedienst maart',
			]
		);

		update_post_meta( $shift_id, 'start_datetime', $start );
		update_post_meta( $shift_id, 'end_datetime', sprintf( '%d-03-06 14:00:00', $season_end ) );
		update_post_meta( $shift_id, 'capacity', 2 );
		update_post_meta( $shift_id, 'status', 'open' );
		update_post_meta( $shift_id, 'assigned_persons', [] );

		return $shift_id;
	}

	/** Force the window shut regardless of when the suite runs. */
	private function close_the_window(): void {
		update_option( ClubConfig::OPTION_VOLUNTEER_SECOND_HALF_OPENS, '12-31' );
	}

	/** Force it open regardless of when the suite runs. */
	private function open_the_window(): void {
		update_option( ClubConfig::OPTION_VOLUNTEER_SECOND_HALF_OPENS, '07-01' );
	}

	private function signup( int $shift_id ) {
		return rest_get_server()->dispatch(
			new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/signup' )
		);
	}

	public function test_signup_is_refused_while_the_window_is_shut(): void {
		$this->close_the_window();
		$shift_id = $this->create_second_half_shift();

		$response = $this->signup( $shift_id );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'signup_not_open_yet', $response->as_error()->get_error_code() );
		$this->assertNotEmpty( $response->as_error()->get_error_data()['opens_at'] );
		$this->assertSame( [], (array) get_post_meta( $shift_id, 'assigned_persons', true ) );
	}

	public function test_signup_succeeds_once_the_window_is_open(): void {
		$this->open_the_window();
		$shift_id = $this->create_second_half_shift();

		$this->assertSame( 200, $this->signup( $shift_id )->get_status() );
	}

	/**
	 * The regression guard: a closed dienst must appear in the calendar with a
	 * date attached, not vanish from it.
	 */
	public function test_closed_shifts_stay_visible_with_an_opening_date(): void {
		$this->close_the_window();
		$shift_id = $this->create_second_half_shift();

		$request = new WP_REST_Request( 'GET', '/rondo/v1/shifts/calendar' );
		$request->set_param( 'view', 'signup' );
		$request->set_param( 'from', current_datetime()->format( 'Y-m-d' ) );
		$request->set_param( 'to', current_datetime()->modify( '+360 days' )->format( 'Y-m-d' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$found = null;
		foreach ( $response->get_data()['days'] as $day ) {
			foreach ( $day['shifts'] as $shift ) {
				if ( (int) $shift['id'] === $shift_id ) {
					$found = [ $day, $shift ];
				}
			}
		}

		$this->assertNotNull( $found, 'a closed dienst must remain visible to members' );
		$this->assertFalse( $found[1]['can_signup'] );
		$this->assertNotEmpty( $found[1]['signup_opens_at'] );
		$this->assertSame( 'locked', $found[0]['state'], 'a day of closed diensten is neither open nor full' );
	}

	/** Coordinators plan the season; the window is not theirs to wait for. */
	public function test_coordinator_assignment_ignores_the_window(): void {
		$this->close_the_window();
		$shift_id = $this->create_second_half_shift();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_vrijwilligers' ] ) );

		$request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/assignees' );
		$request->set_param( 'person_id', $this->person_id );

		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
	}

	/**
	 * An assignment made before the window opened stays valid, and the member can
	 * still get out of it — the window gates new signups, it never evicts anyone.
	 */
	public function test_existing_assignment_survives_and_stays_cancellable(): void {
		$this->close_the_window();
		$shift_id = $this->create_second_half_shift();
		update_post_meta( $shift_id, 'assigned_persons', [ $this->person_id ] );
		update_post_meta( $shift_id, '_shift_signup_at_' . $this->person_id, time() );

		wp_set_current_user( $this->user_id );

		$mine = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/my-shifts' ) );
		$this->assertSame( 200, $mine->get_status() );
		$this->assertNotEmpty(
			array_filter( $mine->get_data()['shifts'], fn( $s ) => (int) $s['id'] === $shift_id )
		);

		$cancel = rest_get_server()->dispatch(
			new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/cancel' )
		);
		$this->assertSame( 200, $cancel->get_status() );
	}
}
