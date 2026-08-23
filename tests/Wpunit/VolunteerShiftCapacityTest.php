<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Volunteer;
use Rondo\Volunteer\VolunteerEligibilityService;
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

	public function test_eligibility_counts_only_users_linked_to_a_person_as_rondo_accounts(): void {
		$baseline_request = new WP_REST_Request( 'GET', '/rondo/v1/volunteer-eligibility' );
		$baseline_request->set_param( 'season', '2026-2027' );
		$baseline = ( new Volunteer() )->get_eligibility( $baseline_request )->get_data()['rondo_account_count'];

		$linked_user_id = self::factory()->user->create();
		update_user_meta( $linked_user_id, 'rondo_linked_person_id', $this->createPerson() );
		self::factory()->user->create();

		$request = new WP_REST_Request( 'GET', '/rondo/v1/volunteer-eligibility' );
		$request->set_param( 'season', '2026-2027' );
		$response = ( new Volunteer() )->get_eligibility( $request );

		$this->assertSame( $baseline + 1, $response->get_data()['rondo_account_count'] );
	}

	public function test_dashboard_required_count_excludes_only_current_role_exemptions(): void {
		$season = '2026-2027';
		$active = $this->createPerson( [ 'post_title' => 'Actieve speler' ] );
		$staff  = $this->createPerson( [ 'post_title' => 'Teammanager' ] );
		update_post_meta( $active, 'leeftijdsgroep', 'Senioren' );
		update_post_meta( $staff, 'leeftijdsgroep', 'Senioren' );
		Fields::update_for_post(
			$staff,
			'work_history',
			[
				[
					'team'        => 123,
					'entity_type' => 'team',
					'job_title'   => 'Teammanager',
					'start_date'  => '2025-07-01',
					'end_date'    => '',
					'is_current'  => true,
				],
			]
		);
		VolunteerEligibilityService::invalidate_cache();

		$request = new WP_REST_Request( 'GET', '/rondo/v1/volunteer-eligibility' );
		$request->set_param( 'season', $season );
		$with_exemption = ( new Volunteer() )->get_eligibility( $request )->get_data()['obligation_summary'];

		$this->assertSame( 2, $with_exemption['total_units'] );
		$this->assertSame( 1, $with_exemption['exempt_units'] );
		$this->assertSame( 2, $with_exemption['required_count'] );

		Fields::update_for_post(
			$staff,
			'work_history',
			[
				[
					'team'        => 123,
					'entity_type' => 'team',
					'job_title'   => 'Teammanager',
					'start_date'  => '2025-07-01',
					'end_date'    => '2026-08-18',
					'is_current'  => false,
				],
			]
		);
		VolunteerEligibilityService::invalidate_cache();
		$without_exemption = ( new Volunteer() )->get_eligibility( $request )->get_data()['obligation_summary'];

		$this->assertSame( 0, $without_exemption['exempt_units'] );
		$this->assertSame( 4, $without_exemption['required_count'] );
	}

	public function test_dashboard_required_count_ignores_stale_person_references(): void {
		$season    = '2026-2027';
		$person_id = $this->createPerson( [ 'post_title' => 'Actieve speler' ] );
		$stale_id  = 999999;
		set_transient(
			VolunteerEligibilityService::cache_key( $season ),
			[
				'units'       => [
					[
						'unit_id'            => 'speler:' . $person_id,
						'kind'               => VolunteerEligibilityService::UNIT_KIND_SPELER,
						'person_ids'         => [ $person_id, $stale_id ],
						'trigger_person_ids' => [ $person_id, $stale_id ],
						'required_count'     => 2,
					],
				],
				'diagnostics' => [
					'skipped_no_leeftijdsgroep' => 0,
					'gezinnen_with_parents'     => 0,
					'gezinnen_via_address'      => 0,
					'gezinnen_orphan'           => 0,
					'speler_units'              => 1,
				],
			],
			VolunteerEligibilityService::CACHE_TTL_SECONDS
		);

		$request = new WP_REST_Request( 'GET', '/rondo/v1/volunteer-eligibility' );
		$request->set_param( 'season', $season );
		$summary = ( new Volunteer() )->get_eligibility( $request )->get_data()['obligation_summary'];

		$this->assertSame( 1, $summary['total_units'] );
		$this->assertSame( 0, $summary['exempt_units'] );
		$this->assertSame( 2, $summary['required_count'] );
	}
}
