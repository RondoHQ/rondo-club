<?php

namespace Tests\Wpunit;

use Rondo\Abilities\TeamAbilities;
use Rondo\Core\AccessControl;
use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Tests\Support\RondoTestCase;

class TeamAbilitiesTest extends RondoTestCase {
	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	private function player( int $team, array $position = [], array $fields = [] ): int {
		return $this->createPerson(
			[],
			array_merge(
			[
				'first_name'   => 'Player',
				'work_history' => [
					array_merge(
					[
						'team'       => $team,
						'job_title'  => 'Teamspeler',
						'is_current' => true,
					],
					$position
				),
				],
			],
			$fields
			)
			);
	}

	private function shift( array $people, string $date, string $status = 'open' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Test duty',
			]
			);
		foreach ( [
			'assigned_persons' => $people,
			'start_datetime'   => $date . ' 09:00:00',
			'end_datetime'     => $date . ' 12:00:00',
			'status'           => $status,
		] as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	public function test_roster_uses_current_player_roles_deduplicates_and_omits_private_fields(): void {
		$team      = $this->createOrganization();
		$player    = $this->player( $team, [], [ 'email_1' => 'private@example.org' ] );
		$positions = Fields::get_for_post( $player, 'work_history' );
		Fields::update_for_post( $player, 'work_history', array_merge( $positions, $positions ) );
		$this->player( $team, [ 'job_title' => 'Trainer/coach' ] );
		$this->player( $team, [ 'start_date' => '2099-01-01' ] );
		$this->player( $team, [ 'end_date' => '2000-01-01' ] );
		$this->player( $team, [ 'is_current' => false ] );
		$this->player( $team, [], [ 'former_member' => true ] );
		$this->player( $this->createOrganization() );
		$result = wp_get_ability( 'rondo/get-team-players' )->execute( [ 'team_ids' => [ $team ] ] );
		$this->assertNotWPError( $result );
		$this->assertSame( [ $player ], array_column( $result['teams'][0]['players'], 'id' ) );
		$this->assertSame( [ 'id', 'name', 'url' ], array_keys( $result['teams'][0]['players'][0] ) );
		update_option( 'rondo_player_roles', [ 'Custom player' ] );
		$custom = $this->player( $team, [ 'job_title' => 'Custom player' ] );
		$result = wp_get_ability( 'rondo/get-team-players' )->execute( [ 'team_ids' => [ $team ] ] );
		$this->assertSame( [ $custom ], array_column( $result['teams'][0]['players'], 'id' ) );
	}

	public function test_signups_are_scoped_to_players_season_and_non_cancelled_shifts(): void {
		$team   = $this->createOrganization();
		$player = $this->player( $team );
		$empty  = $this->player( $team );
		$other  = $this->player( $this->createOrganization() );
		$shift  = $this->shift( [ (string) $player, $other ], '2026-10-12' );
		update_post_meta( $shift, '_shift_signup_at_' . $player, 1790000000 );
		update_post_meta( $shift, '_shift_assignment_mode_' . $player, 'assigned' );
		$this->shift( [ $player ], '2026-10-13', 'geannuleerd' );
		$this->shift( [ $player ], '2025-10-12' );
		$this->shift( [ $other ], '2026-10-12' );
		$past   = $this->shift( [ $player ], '2026-07-01', 'afgerond' );
		$result = wp_get_ability( 'rondo/get-team-volunteer-signups' )->execute(
			[
				'team_ids' => [ $team ],
				'season'   => '2026-2027',
			]
			);
		$this->assertNotWPError( $result );
		$this->assertSame( 2, $result['teams'][0]['player_count'] );
		$this->assertSame( 1, $result['teams'][0]['registered_player_count'] );
		$players = array_column( $result['teams'][0]['players'], null, 'id' );
		$this->assertSame( [], $players[ $empty ]['signups'] );
		$this->assertSame( [ $past, $shift ], array_column( $players[ $player ]['signups'], 'id' ) );
		$this->assertSame( 'assigned', $players[ $player ]['signups'][1]['assignment_mode'] );
		$this->assertSame( 'signup', $players[ $player ]['signups'][0]['assignment_mode'] );
		$this->assertNull( $players[ $player ]['signups'][0]['signed_up_at'] );
		$this->assertArrayNotHasKey( 'signups', $players[ $player ]['signups'][0] );
		$this->assertStringNotContainsString( 'assigned_person', wp_json_encode( $result ) );
	}

	public function test_period_filters_and_default_season(): void {
		$team   = $this->createOrganization();
		$player = $this->player( $team );
		$season = SeasonKey::current( current_datetime()->format( 'Y-m-d' ) );
		$past   = $this->shift( [ $player ], current_datetime()->modify( '-1 day' )->format( 'Y-m-d' ) );
		$future = $this->shift( [ $player ], current_datetime()->modify( '+1 day' )->format( 'Y-m-d' ) );
		foreach ( [
			'past'     => $past,
			'upcoming' => $future,
		] as $period => $id ) {
			$date   = substr( get_post_meta( $id, 'start_datetime', true ), 0, 10 );
			$result = wp_get_ability( 'rondo/get-team-volunteer-signups' )->execute(
				[
					'team_ids' => [ $team ],
					'season'   => SeasonKey::current( $date ),
					'period'   => $period,
				]
				);
			$this->assertSame( [ $id ], array_column( $result['teams'][0]['players'][0]['signups'], 'id' ) );
		}
		$result = wp_get_ability( 'rondo/get-team-volunteer-signups' )->execute( [ 'team_ids' => [ $team ] ] );
		$this->assertSame( $season, $result['season'] );
	}

	public function test_anonymous_and_missing_volunteer_permission_are_rejected_even_directly(): void {
		$team = $this->createOrganization();
		$user = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		$user->add_cap( 'teams' );
		foreach ( [ 0, $user->ID ] as $id ) {
			wp_set_current_user( $id );
			$this->assertWPError( wp_get_ability( 'rondo/get-team-volunteer-signups' )->execute( [ 'team_ids' => [ $team ] ] ) );
			$this->assertWPError( ( new TeamAbilities() )->execute( 'get-team-volunteer-signups', [ 'team_ids' => [ $team ] ] ) );
		}
		wp_set_current_user( 0 );
		$this->assertWPError( wp_get_ability( 'rondo/get-team-players' )->execute( [ 'team_ids' => [ $team ] ] ) );
	}

	public function test_team_and_person_visibility_are_both_enforced(): void {
		$team    = $this->createOrganization();
		$visible = $this->player( $team );
		$this->player( $team );
		$user = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		$user->add_cap( 'teams' );
		update_user_meta( $user->ID, 'rondo_linked_person_id', $visible );
		wp_set_current_user( $user->ID );
		AccessControl::flush_visible_person_ids_cache();
		$result = wp_get_ability( 'rondo/get-team-players' )->execute( [ 'team_ids' => [ $team ] ] );
		$this->assertNotWPError( $result );
		$this->assertSame( [ $visible ], array_column( $result['teams'][0]['players'], 'id' ) );
		$user->remove_cap( 'teams' );
		$other_team = $this->createOrganization();
		$this->assertWPError( wp_get_ability( 'rondo/get-team-players' )->execute( [ 'team_ids' => [ $other_team ] ] ) );
	}

	public function test_empty_team_returns_zero_not_all_signups_and_invalid_inputs_fail(): void {
		$team = $this->createOrganization();
		$this->shift( [ $this->player( $this->createOrganization() ) ], '2026-10-12' );
		$ability = wp_get_ability( 'rondo/get-team-volunteer-signups' );
		$result  = $ability->execute( [ 'team_ids' => [ $team ] ] );
		$this->assertSame( 0, $result['teams'][0]['registered_player_count'] );
		$this->assertSame( [], $result['teams'][0]['players'] );
		foreach ( [
			[],
			[ 'team_ids' => [] ],
			[ 'team_ids' => range( 1, 11 ) ],
			[ 'team_ids' => [ $team, $team ] ],
			[
				'team_ids' => [ $team ],
				'season'   => '2026-2028',
			],
			[
				'team_ids' => [ $team ],
				'period'   => 'cancelled',
			],
			[
				'team_ids' => [ $team ],
				'unknown'  => true,
			],
			[ 'team_ids' => [ $this->createPerson() ] ],
		] as $input ) {
			$this->assertWPError( $ability->execute( $input ) );
			$this->assertWPError( ( new TeamAbilities() )->execute( 'get-team-volunteer-signups', $input ) );
		}
	}

	public function test_both_abilities_are_public_readonly_idempotent(): void {
		foreach ( [ 'get-team-players', 'get-team-volunteer-signups' ] as $name ) {
			$ability = wp_get_ability( 'rondo/' . $name );
			$this->assertNotNull( $ability );
			$this->assertTrue( $ability->get_meta_item( 'public' ) );
			$this->assertSame(
				[
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				$ability->get_meta_item( 'annotations' )
				);
		}
	}
}
