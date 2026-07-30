<?php

namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Coordinator-side assignment: putting someone on a shift on their behalf.
 *
 * The member-facing rules are not relaxed for coordinators — a coordinator
 * records an agreement, they do not override policy — so the certificate, pool
 * and capacity refusals are asserted here as well as in the member flow.
 */
class ShiftAssignmentByCoordinatorTest extends RondoTestCase {

	private int $shift_id;
	private int $person_id;
	private int $dienst_type_id;

	protected function set_up(): void {
		parent::set_up();

		do_action( 'rest_api_init' );

		$this->dienst_type_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Kantinedienst',
			]
		);

		$this->shift_id  = $this->create_shift( '+14 days', 2 );
		$this->person_id = $this->createPerson(
			[ 'post_title' => 'Jan Jansen' ],
			[
				'first_name' => 'Jan',
				'last_name'  => 'Jansen',
				'email_1'    => 'jan@example.com',
				'type-lid'   => 'Senior',
			]
		);
	}

	private function create_shift( string $offset, int $capacity, string $status = 'open' ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Kantinedienst',
			]
		);

		$start = gmdate( 'Y-m-d H:i:s', strtotime( $offset . ' 10:00' ) );
		$end   = gmdate( 'Y-m-d H:i:s', strtotime( $offset . ' 14:00' ) );

		update_post_meta( $shift_id, 'dienst_type_id', $this->dienst_type_id );
		update_post_meta( $shift_id, 'start_datetime', $start );
		update_post_meta( $shift_id, 'end_datetime', $end );
		update_post_meta( $shift_id, 'capacity', $capacity );
		update_post_meta( $shift_id, 'status', $status );
		update_post_meta( $shift_id, 'assigned_persons', [] );

		return $shift_id;
	}

	private function as_coordinator(): int {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	private function add_assignee( int $shift_id, int $person_id, array $extra = [] ) {
		$request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/assignees' );
		$request->set_param( 'person_id', $person_id );
		foreach ( $extra as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_get_server()->dispatch( $request );
	}

	public function test_coordinator_can_assign_a_member(): void {
		$user_id  = $this->as_coordinator();
		$response = $this->add_assignee( $this->shift_id, $this->person_id );

		$this->assertSame( 200, $response->get_status() );

		$assigned = array_map( 'intval', (array) get_post_meta( $this->shift_id, 'assigned_persons', true ) );
		$this->assertSame( [ $this->person_id ], $assigned );

		// The member keeps a signup timestamp, so they can still cancel.
		$this->assertGreaterThan( 0, (int) get_post_meta( $this->shift_id, '_shift_signup_at_' . $this->person_id, true ) );
		// And the audit trail records who arranged it.
		$this->assertSame(
			$user_id,
			(int) get_post_meta( $this->shift_id, '_shift_assigned_by_' . $this->person_id, true )
		);
	}

	public function test_plain_member_may_not_assign_anyone(): void {
		wp_set_current_user( $this->createRondoUser() );

		$this->assertSame( 403, $this->add_assignee( $this->shift_id, $this->person_id )->get_status() );
	}

	public function test_assigning_twice_is_idempotent(): void {
		$this->as_coordinator();

		$this->add_assignee( $this->shift_id, $this->person_id );
		$second = $this->add_assignee( $this->shift_id, $this->person_id );

		$this->assertSame( 200, $second->get_status() );
		$this->assertTrue( $second->get_data()['already_assigned'] );
		$this->assertCount( 1, (array) get_post_meta( $this->shift_id, 'assigned_persons', true ) );
	}

	public function test_capacity_is_enforced_and_flips_status_to_vol(): void {
		$this->as_coordinator();

		$second_person = $this->createPerson( [ 'post_title' => 'Piet Pietersen' ], [ 'first_name' => 'Piet' ] );
		$third_person  = $this->createPerson( [ 'post_title' => 'Kees Keizer' ], [ 'first_name' => 'Kees' ] );

		$this->add_assignee( $this->shift_id, $this->person_id );
		$filling = $this->add_assignee( $this->shift_id, $second_person );

		$this->assertSame( 200, $filling->get_status() );
		$this->assertSame( 'vol', get_post_meta( $this->shift_id, 'status', true ) );

		$overflow = $this->add_assignee( $this->shift_id, $third_person );
		$this->assertSame( 409, $overflow->get_status() );
		$this->assertSame( 'shift_full', $overflow->as_error()->get_error_code() );
	}

	public function test_certificates_are_not_waived_for_coordinators(): void {
		update_post_meta( $this->dienst_type_id, 'iva_required', true );
		$this->as_coordinator();

		$response = $this->add_assignee( $this->shift_id, $this->person_id );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'iva_required', $response->as_error()->get_error_code() );
	}

	public function test_iva_waiver_on_the_shift_still_applies(): void {
		update_post_meta( $this->dienst_type_id, 'iva_required', true );
		update_post_meta( $this->shift_id, 'iva_waived', true );
		$this->as_coordinator();

		$this->assertSame( 200, $this->add_assignee( $this->shift_id, $this->person_id )->get_status() );
	}

	public function test_past_and_closed_shifts_are_refused(): void {
		$this->as_coordinator();

		$past = $this->create_shift( '-3 days', 2 );
		$this->assertSame( 409, $this->add_assignee( $past, $this->person_id )->get_status() );

		$cancelled = $this->create_shift( '+10 days', 2, 'geannuleerd' );
		$this->assertSame( 409, $this->add_assignee( $cancelled, $this->person_id )->get_status() );

		$completed = $this->create_shift( '+10 days', 2, 'voltooid' );
		$this->assertSame( 409, $this->add_assignee( $completed, $this->person_id )->get_status() );
	}

	public function test_overlap_warns_once_then_proceeds_when_forced(): void {
		$this->as_coordinator();

		$this->add_assignee( $this->shift_id, $this->person_id );

		// Same window, different shift.
		$overlapping = $this->create_shift( '+14 days', 2 );

		$warned = $this->add_assignee( $overlapping, $this->person_id );
		$this->assertSame( 409, $warned->get_status() );
		$this->assertSame( 'overlap_warning', $warned->as_error()->get_error_code() );

		$forced = $this->add_assignee( $overlapping, $this->person_id, [ 'force_overlap' => true ] );
		$this->assertSame( 200, $forced->get_status() );
	}

	public function test_missing_email_is_reported_rather_than_silently_ignored(): void {
		$this->as_coordinator();
		$no_email = $this->createPerson( [ 'post_title' => 'Geen Mail' ], [ 'first_name' => 'Geen' ] );

		$response = $this->add_assignee( $this->shift_id, $no_email );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['notification']['queued'] );
		$this->assertSame( 'no_email', $response->get_data()['notification']['reason'] );
	}

	public function test_assigning_does_not_detach_the_shift_from_its_template(): void {
		$template_id = self::factory()->post->create(
			[
				'post_type'   => 'shift_template',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $this->shift_id, 'template_id', $template_id );

		$this->as_coordinator();
		$this->add_assignee( $this->shift_id, $this->person_id );

		$this->assertSame(
			'',
			(string) get_post_meta( $this->shift_id, '_shift_customized', true ),
			'a coordinator assignment must not count as a manual edit of the shift'
		);
	}

	public function test_removing_an_assignee_clears_the_audit_meta(): void {
		$this->as_coordinator();
		$this->add_assignee( $this->shift_id, $this->person_id );

		$request = new WP_REST_Request(
			'DELETE',
			'/rondo/v1/shifts/' . $this->shift_id . '/assignees/' . $this->person_id
		);
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );

		$this->assertSame( '', (string) get_post_meta( $this->shift_id, '_shift_assigned_by_' . $this->person_id, true ) );
		$this->assertSame( 'open', get_post_meta( $this->shift_id, 'status', true ) );
	}

	/** The generic REST route must not be a second, unguarded way in. */
	public function test_writing_assigned_persons_directly_is_refused(): void {
		$this->as_coordinator();

		$request = new WP_REST_Request( 'POST', '/wp/v2/dienst-shifts/' . $this->shift_id );
		$request->set_param( 'acf', [ 'assigned_persons' => [ $this->person_id ] ] );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rondo_use_assignee_endpoint', $response->as_error()->get_error_code() );
		$this->assertSame( [], (array) get_post_meta( $this->shift_id, 'assigned_persons', true ) );
	}

	/**
	 * ACF relationship fields arrive as strings over REST. Comparing raw values
	 * would refuse an unchanged round-trip, breaking every ordinary shift edit.
	 */
	public function test_unchanged_assigned_persons_may_ride_along_as_strings(): void {
		$this->as_coordinator();
		$this->add_assignee( $this->shift_id, $this->person_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/dienst-shifts/' . $this->shift_id );
		$request->set_param(
			'acf',
			[
				'assigned_persons' => [ (string) $this->person_id ],
				'notes'            => 'Bijgewerkte notitie',
			]
		);

		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
	}

	public function test_assignable_people_reports_block_reasons_instead_of_hiding(): void {
		update_post_meta( $this->dienst_type_id, 'iva_required', true );
		$this->as_coordinator();

		$request = new WP_REST_Request( 'GET', '/rondo/v1/shifts/' . $this->shift_id . '/assignable-people' );
		$request->set_param( 'search', 'Jansen' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$people = $response->get_data()['people'];
		$match  = array_values( array_filter( $people, fn( $p ) => $p['id'] === $this->person_id ) );

		$this->assertNotEmpty( $match, 'a blocked person must still be listed' );
		$this->assertTrue( $match[0]['blocked'] );
		$this->assertNotEmpty( $match[0]['block_reason'] );
	}
}
