<?php

namespace Tests\Wpunit;

use Rondo\Core\VolunteerStatus;
use Rondo\Core\WorkHistory;
use Rondo\Fees\PersonFeeContext;
use Rondo\Fields\Fields;
use Rondo\REST\Api;
use Rondo\REST\Teams;
use Tests\Support\RondoTestCase;

class InactiveWorkHistoryTest extends RondoTestCase {

	public function test_inactive_undated_player_remains_in_history_and_not_current_membership(): void {
		wp_set_current_user( 1 );
		$this->bootRestControllers( [ Teams::class ] );
		$team_id   = $this->createOrganization( [ 'post_title' => 'Recreanten' ] );
		$person_id = $this->createPerson();
		$history   = [
			[
				'team'       => $team_id,
				'job_title'  => 'Teamspeler',
				'start_date' => '2019-11-05',
				'end_date'   => '',
				'is_current' => false,
			],
		];
		Fields::update_for_post( $person_id, 'work_history', $history );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/teams/' . $team_id . '/people' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data()['current'] );
		$this->assertSame( $person_id, $response->get_data()['former'][0]['id'] );
		$this->assertSame( [], ( new PersonFeeContext() )->get_current_teams( $person_id ) );
		$this->assertSame( 0, Teams::get_all_member_counts()[ $team_id ]['total'] ?? 0 );
		$stored = Fields::get_for_post( $person_id, 'work_history' )[0];
		$this->assertSame( $team_id, $stored['team'] );
		$this->assertSame( '20191105', $stored['start_date'] );
		$this->assertEmpty( $stored['end_date'] );
		$this->assertFalse( $stored['is_current'] );
	}

	public function test_inactive_undated_staff_does_not_grant_volunteer_status_or_kader_listing(): void {
		wp_set_current_user( 1 );
		$this->bootRestControllers( [ Api::class ] );
		$team_id   = $this->createOrganization();
		$person_id = $this->createPerson();
		$position  = [
			'team'       => $team_id,
			'job_title'  => 'Trainer',
			'start_date' => '2019-11-05',
			'end_date'   => '',
			'is_current' => false,
		];
		Fields::update_for_post( $person_id, 'work_history', [ $position ] );
		$this->assertFalse( VolunteerStatus::is_position_current( $position ) );
		$this->assertEmpty( Fields::get_for_post( $person_id, 'huidig_vrijwilliger' ) );
		$this->assertSame( [], ( new PersonFeeContext() )->get_effective_werkfuncties( $person_id ) );
		$api    = new Api();
		$method = new \ReflectionMethod( $api, 'kaderlijst_candidate_ids' );
		$method->setAccessible( true );
		$this->assertNotContains( $person_id, $method->invoke( $api ) );
		$player = [
			'team'       => $team_id,
			'job_title'  => 'teamspeler',
			'is_current' => true,
		];
		Fields::update_for_post( $person_id, 'work_history', [ $position, $player ] );
		$this->assertNotContains( $person_id, $method->invoke( $api ) );
	}

	public function test_missing_status_and_dated_roles_keep_existing_date_rules(): void {
		$this->assertFalse( WorkHistory::is_inactive_without_end_date( [ 'team' => 1 ] ) );
		$this->assertFalse( WorkHistory::is_inactive_without_end_date( [ 'is_current' => true ] ) );
		$this->assertFalse(
			WorkHistory::is_inactive_without_end_date(
			[
				'is_current' => false,
				'end_date'   => '2099-01-01',
			]
			)
			);
		foreach ( [ false, 0, '0' ] as $status ) {
			$this->assertTrue(
				WorkHistory::is_inactive_without_end_date(
				[
					'is_current' => $status,
					'end_date'   => null,
				]
				)
				);
		}
	}
}
