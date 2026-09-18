<?php

namespace Tests\Wpunit;

use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
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
		$this->assertArrayHasKey( 'account_trend', $data );
		$this->assertArrayHasKey( 'by_team', $data );
		foreach ( $data['account_trend'] as $point ) {
			$this->assertSame( [ 'date', 'count', 'cumulative' ], array_keys( $point ) );
		}
		$this->assertArrayNotHasKey( 'people', $data );
	}

	private function team_member( int $team_id, ?string $age = 'Senioren', array $position = [] ): int {
		$person_id = $this->createPerson();
		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				array_merge(
				[
					'team'       => $team_id,
					'job_title'  => 'Speler',
					'is_current' => true,
				],
				$position
			),
			]
			);
		if ( $age !== null ) {
			update_post_meta( $person_id, 'leeftijdsgroep', $age );
		}
		return $person_id;
	}

	public function test_team_overview_counts_unique_people_and_accounts_but_repeats_family_duties_per_child(): void {
		$team_a   = $this->createOrganization( [ 'post_title' => 'JO12-2' ] );
		$team_b   = $this->createOrganization( [ 'post_title' => 'JO12-10' ] );
		$parent_a = $this->createPerson();
		$parent_b = $this->createPerson();
		$children = [ $this->team_member( $team_a, 'Onder 12' ), $this->team_member( $team_a, 'Onder 12' ), $this->team_member( $team_b, 'Onder 12' ) ];
		foreach ( $children as $child ) {
			Fields::update_for_post(
				$child,
				'relationships',
				[
					[
						'related_person'    => $parent_a,
						'relationship_type' => 2,
					],
					[
						'related_person'    => $parent_b,
						'relationship_type' => 2,
					],
				]
				);
		}
		$positions = Fields::get_for_post( $children[0], 'work_history' );
		Fields::update_for_post( $children[0], 'work_history', array_merge( $positions, $positions ) );
		$user_a = $this->createRondoUser();
		update_post_meta( $parent_a, '_rondo_wp_user_id', $user_a );
		update_user_meta( $user_a, 'rondo_linked_person_id', $parent_a );
		update_user_meta( $this->createRondoUser(), 'rondo_linked_person_id', $parent_b );
		update_post_meta( $children[0], '_rondo_wp_user_id', $this->createRondoUser() );
		$type  = $this->create_type( 'Bar', '#123456' );
		$start = substr( $this->season, 0, 4 ) . '-09-01 10:00:00';
		$this->create_shift( $type, $start, 2, [ $parent_a ] );
		$completed = $this->create_shift( $type, $start, 2, [ $parent_b ], 'voltooid' );
		update_post_meta( $completed, '_no_show_' . $parent_b, [ 'marked_at' => gmdate( 'c' ) ] );
		$this->create_shift( $type, $start, 2, [ $children[0] ] );
		$this->create_shift( $type, $start, 2, [ $parent_a ], 'geannuleerd' );
		$this->create_shift( $type, '2001-09-01 10:00:00', 2, [ $parent_a ] );
		VolunteerEligibilityService::invalidate_cache();

		$data = ( new VolunteerStatistics() )->for_season( $this->season );
		$this->assertSame(
			[
				[
					'id'               => $team_a,
					'name'             => 'JO12-2',
					'people_count'     => 4,
					'account_count'    => 3,
					'required_count'   => 8,
					'assignment_count' => 6,
				],
				[
					'id'               => $team_b,
					'name'             => 'JO12-10',
					'people_count'     => 3,
					'account_count'    => 2,
					'required_count'   => 4,
					'assignment_count' => 3,
				],
			],
			$data['by_team']
			);
		$this->assertSame( 4, $data['obligation_progress']['total_required'] );
		$this->assertSame( 3, $data['summary']['total_assignments'] );
		$historical = ( new VolunteerStatistics() )->for_season( '2001-2002' )['by_team'];
		$this->assertSame( 4, $historical[0]['people_count'] );
		$this->assertSame( 3, $historical[0]['account_count'] );
		$this->assertSame( 2, $historical[0]['assignment_count'] );
	}

	public function test_team_overview_excludes_historical_memberships_and_honours_exemptions(): void {
		$team   = $this->createOrganization( [ 'post_title' => 'Team 1' ] );
		$empty  = $this->createOrganization( [ 'post_title' => 'Team 2' ] );
		$draft  = $this->createOrganization( [ 'post_status' => 'draft' ] );
		$player = $this->team_member( $team );
		$exempt = $this->team_member( $team );
		$this->team_member( $team, null, [ 'job_title' => 'Trainer/coach' ] );
		update_post_meta( $exempt, 'vrijgesteld_handmatig', '1' );
		update_post_meta( $exempt, 'vrijstelling_seizoen', $this->season );
		$this->team_member( $team, 'Senioren', [ 'end_date' => $this->now->modify( '-1 day' )->format( 'Y-m-d' ) ] );
		$this->team_member( $team, 'Senioren', [ 'start_date' => $this->now->modify( '+1 day' )->format( 'Y-m-d' ) ] );
		$this->team_member( $team, 'Senioren', [ 'is_current' => false ] );
		$this->team_member( $draft );
		$former = $this->team_member( $team );
		update_post_meta( $former, 'former_member', '1' );
		$trashed = $this->team_member( $team );
		wp_trash_post( $trashed );
		$deleted_user = $this->createRondoUser();
		wp_delete_user( $deleted_user );
		update_post_meta( $player, '_rondo_wp_user_id', $deleted_user );
		$type = $this->create_type( 'Bar', '#123456' );
		$this->create_shift( $type, substr( $this->season, 0, 4 ) . '-09-01 10:00:00', 2, [ $exempt ] );
		VolunteerEligibilityService::invalidate_cache();

		$rows = ( new VolunteerStatistics() )->for_season( $this->season )['by_team'];
		$this->assertSame(
			[
				[
					'id'               => $team,
					'name'             => 'Team 1',
					'people_count'     => 3,
					'account_count'    => 0,
					'required_count'   => 2,
					'assignment_count' => 1,
				],
				[
					'id'               => $empty,
					'name'             => 'Team 2',
					'people_count'     => 0,
					'account_count'    => 0,
					'required_count'   => 0,
					'assignment_count' => 0,
				],
			],
			$rows
			);
	}

	public function test_team_overview_deduplicates_parents_who_are_also_team_members_and_shared_accounts(): void {
		$team   = $this->createOrganization();
		$parent = $this->team_member( $team, null );
		$child  = $this->team_member( $team, 'Onder 12' );
		Fields::update_for_post(
			$child,
			'relationships',
			[
				[
					'related_person'    => $parent,
					'relationship_type' => 2,
				],
			]
			);
		$user = $this->createRondoUser();
		update_post_meta( $parent, '_rondo_wp_user_id', $user );
		update_user_meta( $user, 'rondo_linked_person_id', $child );
		VolunteerEligibilityService::invalidate_cache();
		$row = ( new VolunteerStatistics() )->for_season( $this->season )['by_team'][0];
		$this->assertSame( 2, $row['people_count'] );
		$this->assertSame( 1, $row['account_count'] );
	}

	public function test_team_overview_is_available_to_board_and_volunteer_roles_without_team_access(): void {
		$this->createOrganization();
		foreach ( [ 'rondo_bestuur', 'rondo_vrijwilligers', 'administrator' ] as $role ) {
			wp_set_current_user( $this->createRondoUser( [ 'role' => $role ] ) );
			$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/volunteer-statistics' ) );
			$this->assertSame( 200, $response->get_status(), $role );
			$this->assertCount( 1, $response->get_data()['by_team'] );
		}
		wp_set_current_user( 0 );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/volunteer-statistics' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_account_trend_groups_utc_registrations_by_local_day_across_seasons(): void {
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		$statistics = new VolunteerStatistics();
		$before     = $statistics->for_season( $this->season )['account_trend'];
		$counts     = array_column( $before, 'count', 'date' );
		$total      = array_sum( $counts );

		foreach ( [ '2001-06-30 21:30:00', '2001-06-30 22:30:00', '2001-07-01 00:30:00' ] as $registered ) {
			self::factory()->user->create( [ 'user_registered' => $registered ] );
		}

		$trend  = $statistics->for_season( $this->season )['account_trend'];
		$actual = array_column( $trend, 'count', 'date' );
		$this->assertSame( ( $counts['2001-06-30'] ?? 0 ) + 1, $actual['2001-06-30'] );
		$this->assertSame( ( $counts['2001-07-01'] ?? 0 ) + 2, $actual['2001-07-01'] );
		$this->assertSame( $total + 3, end( $trend )['cumulative'] );
		$this->assertSame( $trend, $statistics->for_season( '2001-2002' )['account_trend'] );

		$cumulative = 0;
		$previous   = '';
		foreach ( $trend as $point ) {
			$cumulative += $point['count'];
			$this->assertSame( $cumulative, $point['cumulative'] );
			$this->assertGreaterThan( $previous, $point['date'] );
			$previous = $point['date'];
		}
	}

	public function test_account_trend_excludes_deleted_invalid_and_future_registrations(): void {
		$statistics = new VolunteerStatistics();
		$before     = $statistics->for_season( $this->season )['account_trend'];

		$deleted = self::factory()->user->create( [ 'user_registered' => '2001-01-01 12:00:00' ] );
		wp_delete_user( $deleted );
		self::factory()->user->create( [ 'user_registered' => '0000-00-00 00:00:00' ] );
		self::factory()->user->create( [ 'user_registered' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ] );

		$this->assertSame( $before, $statistics->for_season( $this->season )['account_trend'] );
	}

	public function test_statistics_route_denies_members_without_volunteer_permission(): void {
		wp_set_current_user( $this->createRondoUser() );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/volunteer-statistics' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_statistics_ignore_stale_person_ids_in_cached_eligibility(): void {
		$missing_person_id = 999999;
		$cache_key         = VolunteerEligibilityService::cache_key( $this->season );
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
