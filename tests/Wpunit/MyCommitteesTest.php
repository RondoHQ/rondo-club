<?php

namespace Tests\Wpunit;

use Rondo\Commissies\MyCommittees;
use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\REST\Commissies;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

class MyCommitteesTest extends RondoTestCase {

	private int $user_id;
	private int $person_id;
	private int $committee_id;

	protected function set_up(): void {
		parent::set_up();
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->committee_id = $this->createOrganization(
			[
				'post_type'  => 'commissie',
				'post_title' => 'Scheidsrechterscommissie',
			]
			);
		$this->person_id    = $this->createPerson(
			[],
			[
				'first_name'   => 'Voorzitter',
				'work_history' => [ $this->position( $this->committee_id, 'Voorzitter' ) ],
			]
			);
		$this->user_id      = $this->createRondoUser();
		update_user_meta( $this->user_id, 'rondo_linked_person_id', $this->person_id );
		$this->bootRestControllers( [ Commissies::class, UserSettings::class ] );
		wp_set_current_user( $this->user_id );
	}

	private function position( int $id, string $role = 'Commissielid', array $extra = [] ): array {
		return array_merge(
			[
				'team'       => $id,
				'job_title'  => $role,
				'is_current' => true,
			],
			$extra
			);
	}

	private function request( array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/my-committees' );
		$request->set_query_params( $params );
		return rest_do_request( $request );
	}

	public function test_chair_sees_only_current_own_members_and_allowlisted_contacts(): void {
		$member = $this->createPerson(
			[],
			[
				'first_name'   => 'Lid',
				'email_1'      => 'lid@example.org',
				'email_2'      => 'lid@example.org',
				'mobile_1'     => '0612345678',
				'work_history' => [ $this->position( $this->committee_id ), $this->position( $this->committee_id ), $this->position( $this->committee_id, 'Secretaris' ) ],
			]
			);
		$other  = $this->createOrganization( [ 'post_type' => 'commissie' ] );
		foreach ( [
			[ 'work_history' => [ $this->position( $other ) ] ],
			[ 'work_history' => [ $this->position( $this->committee_id, 'Lid', [ 'end_date' => '20000101' ] ) ] ],
			[ 'work_history' => [ $this->position( $this->committee_id, 'Lid', [ 'start_date' => '20990101' ] ) ] ],
			[ 'work_history' => [ $this->position( $this->committee_id, 'Lid', [ 'is_current' => false ] ) ] ],
			[
				'work_history'  => [ $this->position( $this->committee_id ) ],
				'former_member' => true,
			],
		] as $fields ) {
			$this->createPerson( [], $fields + [ 'email_1' => 'hidden@example.org' ] );
		}
		$this->createPerson( [ 'post_status' => 'trash' ], [ 'work_history' => [ $this->position( $this->committee_id ) ] ] );
		$response = $this->request(
			[
				'committee_id' => $other,
				'person_id'    => $member,
				'user_id'      => 1,
			]
			);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$rosters = $response->get_data();
		$this->assertSame( [ $this->committee_id ], array_column( $rosters, 'id' ) );
		$this->assertTrue( $rosters[0]['can_view_contacts'] );
		$this->assertCount( 2, $rosters[0]['members'] );
		$members = array_column( $rosters[0]['members'], null, 'id' );
		$this->assertSame( [ 'lid@example.org' ], $members[ $member ]['emails'] );
		$this->assertSame( [ '0612345678' ], $members[ $member ]['phones'] );
		$this->assertSame( [ 'Commissielid', 'Secretaris' ], $members[ $member ]['roles'] );
		$this->assertSame( [ 'id', 'name', 'emails', 'phones', 'mobile_phones', 'thumbnail', 'roles' ], array_keys( $members[ $member ] ) );
		$this->assertFalse( ( new AccessControl() )->user_can_access_post( $member, $this->user_id ) );
	}

	public function test_contact_permission_is_per_committee_and_not_a_general_role(): void {
		$other = $this->createOrganization(
			[
				'post_type'  => 'commissie',
				'post_title' => 'Wedstrijdzaken',
			]
			);
		Fields::update_for_post( $this->person_id, 'work_history', [ $this->position( $this->committee_id, ' VOORZITTER ' ), $this->position( $other ) ] );
		$this->createPerson(
			[],
			[
				'email_1'      => 'secret@example.org',
				'work_history' => [ $this->position( $this->committee_id ), $this->position( $other ) ],
			]
			);
		$rosters = array_column( $this->request()->get_data(), null, 'id' );
		$this->assertTrue( $rosters[ $this->committee_id ]['can_view_contacts'] );
		$this->assertFalse( $rosters[ $other ]['can_view_contacts'] );
		foreach ( $rosters[ $other ]['members'] as $member ) {
			$this->assertSame( [ 'id', 'name', 'thumbnail', 'roles' ], array_keys( $member ) );
		}
		$this->assertTrue( ( new UserSettings() )->get_current_user_data( $this->user_id )['has_my_committees'] );
	}

	public function test_ending_chair_role_removes_contacts_while_member_role_remains(): void {
		$this->assertTrue( $this->request()->get_data()[0]['can_view_contacts'] );
		Fields::update_for_post(
			$this->person_id,
			'work_history',
			[
				$this->position( $this->committee_id, 'Voorzitter', [ 'end_date' => '20000101' ] ),
				$this->position( $this->committee_id ),
			]
			);
		$roster = $this->request()->get_data()[0];
		$this->assertFalse( $roster['can_view_contacts'] );
		$this->assertArrayNotHasKey( 'emails', $roster['members'][0] );
		Fields::update_for_post( $this->person_id, 'work_history', [] );
		$this->assertSame( 403, $this->request()->get_status() );
		$this->assertFalse( ( new UserSettings() )->get_current_user_data( $this->user_id )['has_my_committees'] );
	}

	public function test_dates_inactive_flags_and_committee_status_restrict_access(): void {
		foreach ( [ [ 'start_date' => '20990101' ], [ 'end_date' => '20000101' ], [ 'is_current' => false ], [ 'start_date' => 'invalid' ] ] as $dates ) {
			Fields::update_for_post( $this->person_id, 'work_history', [ $this->position( $this->committee_id, 'Voorzitter', $dates ) ] );
			$this->assertSame( 403, $this->request()->get_status() );
		}
		Fields::update_for_post( $this->person_id, 'work_history', [ $this->position( $this->committee_id, 'Voorzitter', [ 'end_date' => current_datetime()->format( 'Ymd' ) ] ) ] );
		$this->assertSame( 200, $this->request()->get_status() );
		wp_update_post(
			[
				'ID'          => $this->committee_id,
				'post_status' => 'draft',
			]
			);
		$this->assertSame( 403, $this->request()->get_status() );
	}

	public function test_only_own_published_current_person_and_exact_chair_role_count(): void {
		Fields::update_for_post( $this->person_id, 'work_history', [ $this->position( $this->committee_id, 'Vicevoorzitter' ) ] );
		$this->assertFalse( $this->request()->get_data()[0]['can_view_contacts'] );
		Fields::update_for_post( $this->person_id, 'former_member', true );
		$this->assertSame( 403, $this->request()->get_status() );
		Fields::update_for_post( $this->person_id, 'former_member', false );
		wp_update_post(
			[
				'ID'          => $this->person_id,
				'post_status' => 'draft',
			]
			);
		$this->assertSame( 403, $this->request()->get_status() );
		delete_user_meta( $this->user_id, 'rondo_linked_person_id' );
		$this->assertSame( [], MyCommittees::committees_for_user() );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request()->get_status() );
	}

	public function test_team_chairmanship_and_admin_role_do_not_grant_committee_membership(): void {
		$team = $this->createOrganization();
		Fields::update_for_post( $this->person_id, 'work_history', [ $this->position( $team, 'Voorzitter' ) ] );
		$this->assertSame( 403, $this->request()->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( 403, $this->request()->get_status() );
		$this->assertSame( 404, rest_do_request( new \WP_REST_Request( 'POST', '/rondo/v1/my-committees' ) )->get_status() );
	}
}
