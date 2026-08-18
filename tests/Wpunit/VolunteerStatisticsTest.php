<?php

namespace Tests\Wpunit;

use Rondo\Fees\SeasonKey;
use Rondo\REST\Volunteer;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Rondo\Volunteer\VolunteerStatistics;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Contract and aggregation coverage for volunteer statistics.
 */
class VolunteerStatisticsTest extends RondoTestCase {
	private WP_REST_Server $server;
	private string $season;
	private \DateTimeImmutable $now;

	protected function set_up(): void {
		parent::set_up();
		$this->server = $this->bootRestControllers( [ Volunteer::class ] );
		$this->now    = current_datetime();
		$this->season = SeasonKey::current( $this->now->format( 'Y-m-d' ) );

		VolunteerEligibilityService::invalidate_cache();
		VolunteerObligationCalculator::invalidate_cache();
	}

	private function create_type( string $title, string $color ): int {
		$type_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
		update_post_meta( $type_id, 'color', $color );
		return $type_id;
	}

	private function create_shift( int $type_id, string $start, int $capacity, array $assigned, string $status = 'open' ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Statistiekendienst',
			]
		);
		update_post_meta( $shift_id, 'dienst_type_id', $type_id );
		update_post_meta( $shift_id, 'start_datetime', $start );
		update_post_meta( $shift_id, 'end_datetime', gmdate( 'Y-m-d H:i:s', strtotime( $start ) + HOUR_IN_SECONDS ) );
		update_post_meta( $shift_id, 'capacity', $capacity );
		update_post_meta( $shift_id, 'assigned_persons', $assigned );
		update_post_meta( $shift_id, 'status', $status );
		return $shift_id;
	}

	public function test_statistics_group_current_assignments_and_exclude_cancelled_shifts(): void {
		$type_a   = $this->create_type( 'Kantine — bar', '#123456' );
		$type_b   = $this->create_type( 'Schoonmaak', '#abcdef' );
		$person_a = $this->createPerson( [ 'post_title' => 'Vrijwilliger A' ] );
		$person_b = $this->createPerson( [ 'post_title' => 'Vrijwilliger B' ] );
		$person_c = $this->createPerson( [ 'post_title' => 'Vrijwilliger C' ] );
		$future   = $this->now->modify( '+5 days' )->format( 'Y-m-d H:i:s' );
		$past     = substr( $this->season, 0, 4 ) . '-07-05 10:00:00';
		$outside  = ( (int) substr( $this->season, 0, 4 ) + 1 ) . '-07-05 10:00:00';

		$shift_a = $this->create_shift( $type_a, $future, 4, [ $person_a, $person_b ] );
		$shift_b = $this->create_shift( $type_a, $past, 2, [ $person_a ], 'voltooid' );
		$shift_c = $this->create_shift( $type_b, $future, 3, [ $person_c ] );
		$this->create_shift( $type_b, $future, 8, [ $person_a, $person_b, $person_c ], 'geannuleerd' );
		$this->create_shift( $type_b, $outside, 9, [ $person_a ] );

		$base = $this->now->modify( '-20 days' )->getTimestamp();
		update_post_meta( $shift_a, '_shift_signup_at_' . $person_a, $base );
		update_post_meta( $shift_a, '_shift_signup_at_' . $person_b, $base + DAY_IN_SECONDS );
		update_post_meta( $shift_b, '_shift_signup_at_' . $person_a, $base + ( 2 * DAY_IN_SECONDS ) );
		update_post_meta( $shift_c, '_shift_assigned_at_' . $person_c, $base + ( 3 * DAY_IN_SECONDS ) );

		$active_player = $this->createPerson( [ 'post_title' => 'Actieve speler' ] );
		$exempt_player = $this->createPerson( [ 'post_title' => 'Vrijgestelde speler' ] );
		update_post_meta( $active_player, 'leeftijdsgroep', 'Senioren' );
		update_post_meta( $exempt_player, 'leeftijdsgroep', 'Senioren' );
		update_post_meta( $exempt_player, 'vrijgesteld_handmatig', '1' );
		update_post_meta( $exempt_player, 'vrijstelling_seizoen', $this->season );
		VolunteerEligibilityService::invalidate_cache();

		$data             = ( new VolunteerStatistics() )->for_season( $this->season );
		$trend            = $data['signup_trend'];
		$last_trend_point = end( $trend );

		$this->assertSame( 3, $data['summary']['total_shifts'] );
		$this->assertSame( 9, $data['summary']['total_capacity'] );
		$this->assertSame( 4, $data['summary']['total_assignments'] );
		$this->assertSame( 3, $data['summary']['unique_volunteers'] );
		$this->assertSame( 1, $data['summary']['completed_assignments'] );
		$this->assertSame( 3, $data['summary']['upcoming_assignments'] );
		$this->assertSame( 44.4, $data['summary']['fill_rate'] );
		$this->assertSame( 1.33, $data['summary']['average_assignments_per_volunteer'] );

		$this->assertSame( 'Kantine — bar', $data['by_task_type'][0]['name'] );
		$this->assertSame( 3, $data['by_task_type'][0]['assignments'] );
		$this->assertSame( 2, $data['by_task_type'][0]['unique_volunteers'] );
		$this->assertSame( 6, $data['by_task_type'][0]['capacity'] );
		$this->assertSame( 50.0, $data['by_task_type'][0]['fill_rate'] );
		$this->assertSame( 4, $last_trend_point['cumulative'] );
		$this->assertSame(
			[
				'one'        => 2,
				'two'        => 1,
				'three_plus' => 0,
			],
			$data['assignment_distribution']
			);
		$this->assertSame( 2, $data['upcoming_shortages_total'] );

		$this->assertSame( 2, $data['obligation_progress']['total_units'] );
		$this->assertSame( 1, $data['obligation_progress']['exempt'] );
		$this->assertSame( 1, $data['obligation_progress']['not_started'] );
	}

	public function test_statistics_route_returns_aggregate_data_to_volunteer_managers(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'statistics_manager' ] );
		( new \WP_User( $user_id ) )->add_cap( 'vrijwilligers' );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/rondo/v1/volunteer-statistics' );
		$request->set_param( 'season', $this->season );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $this->season, $data['season'] );
		$this->assertArrayHasKey( 'summary', $data );
		$this->assertArrayHasKey( 'obligation_progress', $data );
		$this->assertArrayNotHasKey( 'people', $data );
	}

	public function test_statistics_ignore_stale_person_ids_in_cached_eligibility(): void {
		$missing_person_id = 999999;
		$cache_key         = VolunteerEligibilityService::CACHE_PREFIX . md5( $this->season );
		set_transient(
			$cache_key,
			[
				'units'       => [
					[
						'unit_id'            => 'stale-person',
						'kind'               => VolunteerEligibilityService::UNIT_KIND_SPELER,
						'person_ids'         => [ $missing_person_id ],
						'trigger_person_ids' => [ $missing_person_id ],
						'required_count'     => 2,
						'address_key'        => null,
					],
				],
				'diagnostics' => [],
			],
			MINUTE_IN_SECONDS
		);

		$data = ( new VolunteerStatistics() )->for_season( $this->season );

		$this->assertSame( 0, $data['obligation_progress']['total_units'] );
		$this->assertSame( 0, $data['obligation_progress']['total_required'] );
	}
}
