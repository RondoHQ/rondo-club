<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\Dashboard\RoleDashboard;
use Rondo\Fields\Fields;
use Rondo\REST\Api;
use Rondo\REST\UserSettings;
use Rondo\Teams\TeamMatches;
use Rondo\Training\Schedules;
use Tests\Support\RondoTestCase;

class RoleDashboardTest extends RondoTestCase {

	private string $coordinator;
	private string $secretary;
	private int $user_id;
	private int $team;
	private int $other_team;

	protected function set_up(): void {
		parent::set_up();
		$this->coordinator = UserRoles::add_custom_role( 'Dashboard coordinator' );
		$this->secretary   = UserRoles::add_custom_role( 'Dashboard secretary' );
		get_role( $this->secretary )->add_cap( 'wedstrijdzaken' );
		$this->team       = $this->createOrganization( [ 'post_title' => 'JO13-1' ] );
		$this->other_team = $this->createOrganization( [ 'post_title' => 'JO19-1' ] );
		update_option( 'rondo_age_group_access', [ $this->coordinator => [ 'Onder 13' ] ] );
		update_option( 'rondo_team_access', [ $this->coordinator => [ $this->team ] ] );
		$this->user_id = $this->createRondoUser( [ 'role' => $this->coordinator ] );
		wp_set_current_user( $this->user_id );
		$this->bootRestControllers( [ Api::class, UserSettings::class ] );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	protected function tear_down(): void {
		UserRoles::remove_custom_role( $this->coordinator );
		UserRoles::remove_custom_role( $this->secretary );
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_team_access' );
		delete_option( 'rondo_narrowcasting_matchday_cache' );
		delete_option( Schedules::ACTIVE );
		parent::tear_down();
	}

	private function request( string $path, ?array $body = null ): \WP_REST_Response {
		$request = new \WP_REST_Request( $body === null ? 'GET' : 'POST', '/rondo/v1/' . $path );
		if ( $body !== null ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	}

	private function roles( array $roles ): void {
		$user = get_userdata( $this->user_id );
		$user->set_role( array_shift( $roles ) );
		foreach ( $roles as $role ) {
			$user->add_role( $role );
		}
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
	}

	private function person( string $age, int $team, array $extra = [] ): int {
		return $this->createPerson(
			[],
			array_merge(
			[
				'first_name'     => 'Fixture',
				'leeftijdsgroep' => $age,
				'birthdate'      => current_datetime()->modify( '-12 years' )->format( 'Y-m-d' ),
				'work_history'   => [
					[
						'team'       => $team,
						'job_title'  => 'Speler',
						'is_current' => true,
					],
				],
			],
			$extra
			)
			);
	}

	public function test_coordinator_birthdays_and_counts_respect_record_scope_and_current_players(): void {
		$own          = $this->person( 'Onder 13', $this->team );
		$dispensation = $this->person( 'Onder 15', $this->team );
		$age_only     = $this->person( 'Onder 13', $this->other_team );
		$hidden       = $this->person( 'Onder 19', $this->other_team );
		$former       = $this->person( 'Onder 13', $this->team, [ 'former_member' => true ] );
		$this->person(
			'Onder 13',
			$this->team,
			[
				'work_history' => [
					[
						'team'       => $this->team,
						'job_title'  => 'Trainer',
						'is_current' => true,
					],
				],
			]
			);
		$this->person(
			'Onder 19',
			$this->team,
			[
				'work_history' => [
					[
						'team'       => $this->team,
						'job_title'  => 'Speler',
						'is_current' => false,
						'end_date'   => '2020-01-01',
					],
				],
			]
			);
		$response = $this->request( 'dashboard/workspace' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
		$data = $response->get_data();
		$this->assertSame( [ $this->team ], array_column( $data['teams'], 'id' ) );
		$this->assertSame( 2, $data['teams'][0]['player_count'] );
		$birthdays = array_map( 'intval', array_column( $data['birthdays'], 'id' ) );
		foreach ( [ $own, $dispensation, $age_only ] as $id ) {
			$this->assertContains( $id, $birthdays );
		}
		$this->assertNotContains( $hidden, $birthdays );
		$this->assertNotContains( $former, $birthdays );
		$this->assertSame( 403, $this->request( 'dashboard/matches' )->get_status() );
	}

	public function test_secretary_gets_club_matches_without_person_team_or_finance_access(): void {
		$hidden = $this->person( 'Onder 19', $this->other_team );
		$this->roles( [ $this->secretary ] );
		$data = $this->request( 'dashboard/workspace' )->get_data();
		$this->assertSame( [], $data['birthdays'] );
		$this->assertSame( [], $data['teams'] );
		$this->assertSame( [], $data['training'] );
		$this->assertSame( [ 'attention', 'matches' ], $data['layout']['order'] );
		$this->assertFalse( AccessControl::can_view_person( $hidden ) );
		$this->assertFalse( current_user_can( 'financieel' ) );
		$this->assertFalse( AccessControl::can_access_teams() );
		$this->assertSame( 200, $this->request( 'dashboard/matches' )->get_status() );
		$this->assertTrue( $this->request( 'user/me' )->get_data()['can_access_dashboard'] );
	}

	public function test_multiple_roles_combine_once_and_revocation_removes_blocks_and_data(): void {
		$this->roles( [ $this->coordinator, $this->secretary ] );
		$data = $this->request( 'dashboard/workspace' )->get_data();
		$this->assertTrue( $data['context']['coordinator'] );
		$this->assertTrue( $data['context']['secretary'] );
		$this->assertSame( [ 'attention', 'birthdays', 'matches', 'teams' ], $data['layout']['order'] );
		$layout = [
			'order'  => [ 'teams', 'matches', 'birthdays', 'attention' ],
			'hidden' => [ 'birthdays' ],
		];
		$this->assertSame( $layout, $this->request( 'dashboard/layout', $layout )->get_data() );
		$this->roles( [ $this->secretary ] );
		$data = $this->request( 'dashboard/workspace' )->get_data();
		$this->assertSame( [ 'matches', 'attention' ], $data['layout']['order'] );
		$this->assertSame( [], $data['layout']['hidden'] );
		$this->assertSame( [], $data['teams'] );
		$this->assertSame( 400, $this->request( 'dashboard/layout', $layout )->get_status() );
		$all_hidden = [
			'order'  => [],
			'hidden' => [ 'attention', 'matches' ],
		];
		$this->assertSame( $all_hidden['hidden'], $this->request( 'dashboard/layout', $all_hidden )->get_data()['hidden'] );
		$this->assertSame( $all_hidden['hidden'], $this->request( 'dashboard/workspace' )->get_data()['layout']['hidden'] );
		$this->roles( [ 'rondo_user' ] );
		$this->assertSame( 403, $this->request( 'dashboard/workspace' )->get_status() );
		$this->assertSame( 403, $this->request( 'dashboard/matches' )->get_status() );
		$this->assertSame( 403, $this->request( 'dashboard/layout', $all_hidden )->get_status() );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'dashboard/workspace' )->get_status() );
	}

	public function test_match_route_validates_team_scope_and_uses_only_seven_local_dates(): void {
		$rows = [];
		foreach ( [ -1, 0, 6, 7 ] as $offset ) {
			$date   = current_datetime()->modify( "{$offset} days" )->format( 'Y-m-d' );
			$rows[] = [
				'id'        => (string) $offset,
				'date'      => $date,
				'starts_at' => $date . 'T12:00:00+02:00',
			];
		}
		update_post_meta(
			$this->team,
			'_rondo_team_matches_cache',
			[
				'season'           => TeamMatches::season()['key'],
				'identity'         => '|',
				'duration_version' => 1,
				'matchdays'        => [],
				'matches'          => $rows,
				'retry_after'      => time() + 60,
				'matched'          => true,
				'stale'            => false,
				'updated_at'       => gmdate( DATE_RFC3339 ),
			]
			);
		foreach ( [
			$this->team       => 200,
			$this->other_team => 403,
			-1                => 400,
			'invalid'         => 400,
		] as $id => $status ) {
			$request = new \WP_REST_Request( 'GET', '/rondo/v1/dashboard/matches' );
			$request->set_param( 'team_id', $id );
			$response = rest_do_request( $request );
			$this->assertSame( $status, $response->get_status() );
			if ( $status === 200 ) {
				$this->assertSame( [ '0', '6' ], array_column( $response->get_data()['matches'], 'id' ) );
				$this->assertArrayNotHasKey( 'matchdays', $response->get_data() );
			}
		}
	}

	public function test_tasks_are_personal_sorted_before_limiting_and_do_not_expose_hidden_relations(): void {
		$hidden = $this->person( 'Onder 19', $this->other_team );
		$other  = $this->createRondoUser();
		self::factory()->post->create(
			[
				'post_type'   => 'rondo_todo',
				'post_status' => 'rondo_open',
				'post_title'  => 'Someone else',
				'post_author' => $other,
			]
			);
		for ( $i = 0; $i < 12; ++$i ) {
			$id = self::factory()->post->create(
				[
					'post_type'   => 'rondo_todo',
					'post_status' => 'rondo_open',
					'post_author' => $this->user_id,
				]
				);
			Fields::update_for_post( $id, 'due_date', current_datetime()->modify( "-{$i} days" )->format( 'Y-m-d H:i:s' ) );
			Fields::update_for_post( $id, 'related_persons', [ $hidden ] );
		}
		$tasks = $this->request( 'dashboard/workspace' )->get_data()['tasks'];
		$this->assertCount( 10, $tasks );
		$this->assertSame( $id, $tasks[0]['id'] );
		$this->assertSame( [], $tasks[0]['persons'] );
		$this->assertNull( $tasks[0]['person_id'] );
	}

	public function test_training_excludes_other_teams_and_shared_block_labels(): void {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_training',
				'post_status' => 'private',
			]
			);
		update_option( Schedules::ACTIVE, $id );
		Fields::update_for_post(
			$id,
			'blocks',
			[
				[
					'block_id' => 'shared',
					'day'      => 1,
					'start'    => '18:00',
					'duration' => 60,
					'team_ids' => [ $this->team, $this->other_team ],
					'label'    => 'Hidden team details',
					'pitch_id' => 'one',
					'size'     => 2,
					'offset'   => 0,
				],
			]
			);
		$data = RoleDashboard::overview();
		$this->assertCount( 1, $data['training'] );
		$this->assertSame( [ $this->team ], $data['training'][0]['team_ids'] );
		$this->assertArrayNotHasKey( 'label', $data['training'][0] );
		$this->assertArrayNotHasKey( 'team_names', $data['training'][0] );
	}

	public function test_club_week_deduplicates_cancellations_and_uses_oldest_relevant_feed(): void {
		$this->roles( [ $this->secretary ] );
		$rows = [];
		foreach ( [ -1, 0, 6, 7 ] as $offset ) {
			$date   = current_datetime()->modify( "{$offset} days" )->format( 'Y-m-d' );
			$rows[] = [
				'id'        => (string) $offset,
				'date'      => $date,
				'starts_at' => $date . 'T12:00:00+02:00',
				'cancelled' => false,
			];
		}
		$old          = gmdate( DATE_RFC3339, time() - 2 * DAY_IN_SECONDS );
		$fresh        = [
			'fetched_at'  => gmdate( DATE_RFC3339 ),
			'fresh_until' => gmdate( DATE_RFC3339, time() + 60 ),
		];
		$cancellation = array_merge( $rows[1], [ 'cancelled' => true ] );
		update_option(
			'rondo_narrowcasting_matchday_cache',
			[
				'feeds' => [
					'matches'       => $fresh + [ 'items' => $rows ],
					'cancellations' => [
						'items'       => [ $cancellation, array_merge( $cancellation, [ 'id' => 'cancel-only' ] ) ],
						'fetched_at'  => $old,
						'fresh_until' => $old,
					],
					'results'       => $fresh + [ 'items' => [] ],
				],
			]
			);
		$data = $this->request( 'dashboard/matches' )->get_data();
		$this->assertSame( [ '0', 'cancel-only', '6' ], array_column( $data['matches'], 'id' ) );
		$this->assertTrue( $data['matches'][0]['cancelled'] );
		$this->assertTrue( $data['matches'][1]['cancelled'] );
		$this->assertSame( $old, $data['updated_at'] );
		$this->assertTrue( $data['stale'] );
		$this->assertTrue( $data['expired'] );
	}

	public function test_role_upgrade_grants_only_match_secretaries_without_broadening_record_rights(): void {
		$slug = UserRoles::add_custom_role( 'Wedstrijdzaken' );
		try {
			$this->assertSame( 'rondo_wedstrijdzaken', $slug );
			update_option( UserRoles::ROLES_VERSION_OPTION, 15 );
			( new UserRoles() )->maybe_upgrade_roles();
			$this->assertTrue( get_role( $slug )->has_cap( 'wedstrijdzaken' ) );
			$this->assertFalse( get_role( 'rondo_user' )->has_cap( 'wedstrijdzaken' ) );
			$this->assertFalse( get_role( $this->coordinator )->has_cap( 'wedstrijdzaken' ) );
			$this->assertFalse( get_role( $slug )->has_cap( 'financieel' ) );
			$this->assertFalse( get_role( $slug )->has_cap( 'teams' ) );
		} finally {
			UserRoles::remove_custom_role( $slug );
		}
	}
}
