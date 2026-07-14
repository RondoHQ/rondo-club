<?php

namespace Tests\Wpunit;

use Rondo\REST\Volunteer;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Regression coverage for capacity statistics on the volunteer dashboard.
 */
class VolunteerShiftCapacityTest extends RondoTestCase {

	private function create_shift( string $date, int $capacity, array $assigned, string $status = 'open' ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Capaciteitstest',
			]
		);
		update_post_meta( $shift_id, 'start_datetime', $date . ' 10:00:00' );
		update_post_meta( $shift_id, 'end_datetime', $date . ' 12:00:00' );
		update_post_meta( $shift_id, 'capacity', $capacity );
		update_post_meta( $shift_id, 'assigned_persons', $assigned );
		update_post_meta( $shift_id, 'status', $status );
		return $shift_id;
	}

	public function test_eligibility_counts_capacity_and_assigned_people_for_the_season(): void {
		$person_a = $this->createPerson( [ 'post_title' => 'Vrijwilliger A' ] );
		$person_b = $this->createPerson( [ 'post_title' => 'Vrijwilliger B' ] );
		$person_c = $this->createPerson( [ 'post_title' => 'Vrijwilliger C' ] );
		$person_d = $this->createPerson( [ 'post_title' => 'Vrijwilliger D' ] );

		$this->create_shift( '2026-08-01', 4, [ $person_a, $person_b, $person_c, $person_d ] );
		$this->create_shift( '2027-05-01', 2, [ $person_a ], 'voltooid' );
		$this->create_shift( '2026-09-01', 8, [ $person_a, $person_b ], 'geannuleerd' );
		$this->create_shift( '2027-07-01', 9, [ $person_a ] );

		$request = new WP_REST_Request( 'GET', '/rondo/v1/volunteer-eligibility' );
		$request->set_param( 'season', '2026-2027' );
		$response = ( new Volunteer() )->get_eligibility( $request );

		$this->assertSame(
			[
				'total_slots'    => 6,
				'assigned_slots' => 5,
			],
			$response->get_data()['shift_capacity']
		);
	}
}
