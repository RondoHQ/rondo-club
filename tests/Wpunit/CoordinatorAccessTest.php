<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\REST\Api;
use Rondo\REST\Capabilities;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/** Combined year-group access must agree across every public read surface. */
class CoordinatorAccessTest extends RondoTestCase {

	private string $role;
	private int $user_id;
	private int $team_id;
	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_team_access' );
		$this->role    = UserRoles::add_custom_role( 'Coordinator test' );
		$this->user_id = $this->createRondoUser( [ 'role' => $this->role ] );
		$this->team_id = $this->createOrganization( [ 'post_title' => 'JO13-2' ] );
		update_option( 'rondo_age_group_access', [ $this->role => [ 'Onder 13' ] ] );
		update_option( 'rondo_team_access', [ $this->role => [ $this->team_id ] ] );
		wp_set_current_user( $this->user_id );
		$this->server = $this->bootRestControllers( [ People::class, Api::class, Capabilities::class ] );
	}

	protected function tear_down(): void {
		UserRoles::remove_custom_role( $this->role );
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_team_access' );
		parent::tear_down();
	}

	private function person( string $group, array $jobs = [] ): int {
		return $this->createPerson(
			[ 'post_title' => 'Scope member' ],
			[
				'first_name'          => 'Scope',
				'leeftijdsgroep'      => $group,
				'work_history'        => $jobs,
				'financiele_blokkade' => true,
			]
		);
	}

	private function job( array $overrides = [] ): array {
		return array_merge(
			[
				'team'       => $this->team_id,
				'job_title'  => 'Speler',
				'is_current' => true,
			],
			$overrides
			);
	}

	private function request( string $route, array $params = [], string $method = 'GET' ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	public function test_union_includes_dispensation_younger_and_unassigned_players_only(): void {
		$visible = [
			$this->person( 'Onder 13' ),
			$this->person( 'Onder 14', [ $this->job() ] ),
			$this->person( 'Onder 12', [ $this->job() ] ),
			$this->person( 'Onder 13', [ $this->job() ] ),
		];
		$hidden  = [
			$this->person( 'Onder 14' ),
			$this->person( 'Senioren', [ $this->job( [ 'job_title' => 'Trainer' ] ) ] ),
			$this->person( 'Onder 14', [ $this->job( [ 'end_date' => '2020-01-01' ] ) ] ),
			$this->person( 'Onder 14', [ $this->job( [ 'start_date' => '2099-01-01' ] ) ] ),
			$this->person( 'Onder 14', [ $this->job( [ 'is_current' => false ] ) ] ),
		];
		foreach ( $visible as $id ) {
			$this->assertTrue( AccessControl::can_view_person( $id ) );
		}
		foreach ( $hidden as $id ) {
			$this->assertFalse( AccessControl::can_view_person( $id ) );
		}
		$this->assertEqualsCanonicalizing( $visible, AccessControl::visible_person_ids_or_null() );
		$this->assertEqualsCanonicalizing(
			$visible,
			get_posts(
			AccessControl::scope_person_query_args(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
			)
			)
			);

		$list = $this->request( '/rondo/v1/people/filtered', [ 'per_page' => 2 ] );
		$this->assertSame( 200, $list->get_status() );
		$this->assertSame( 4, $list->get_data()['total'] );
		$this->assertSame( 2, $list->get_data()['total_pages'] );
		$second = $this->request(
			'/rondo/v1/people/filtered',
			[
				'per_page' => 2,
				'page'     => 2,
			]
			);
		$this->assertEqualsCanonicalizing( $visible, array_column( array_merge( $list->get_data()['people'], $second->get_data()['people'] ), 'id' ) );
		foreach ( $list->get_data()['people'] as $person ) {
			$this->assertArrayNotHasKey( 'financiele_blokkade', $person['fields'] );
		}
		$search = $this->request( '/rondo/v1/search', [ 'q' => 'Scope' ] );
		$this->assertSame( 200, $search->get_status() );
		$this->assertEqualsCanonicalizing( $visible, array_column( $search->get_data()['people'], 'id' ) );
		$core = $this->request( '/wp/v2/people', [ 'per_page' => 100 ] );
		$this->assertSame( 200, $core->get_status() );
		$this->assertEqualsCanonicalizing( $visible, array_column( $core->get_data(), 'id' ) );
		$this->assertSame( 200, $this->request( '/wp/v2/people/' . $visible[1] )->get_status() );
		$this->assertSame( 403, $this->request( '/wp/v2/people/' . $hidden[0] )->get_status() );
		$this->assertSame( 403, $this->request( '/wp/v2/people/' . $visible[1], [ 'fields' => [ 'first_name' => 'Changed' ] ], 'POST' )->get_status() );
		$this->assertSame( 403, $this->request( '/wp/v2/people/' . $visible[1], [], 'DELETE' )->get_status() );
		$ability = wp_get_ability( 'rondo/search-records' )->execute(
			[
				'query'    => 'Scope',
				'contexts' => [ 'person' ],
			]
			);
		$this->assertNotWPError( $ability );
		$this->assertEqualsCanonicalizing( $visible, array_column( $ability['records'], 'id' ) );
		$this->assertNotWPError( wp_get_ability( 'rondo/get-record' )->execute( [ 'id' => $visible[1] ] ) );
		$this->assertWPError( wp_get_ability( 'rondo/get-record' )->execute( [ 'id' => $hidden[0] ] ) );
	}

	public function test_team_only_scope_updates_after_membership_and_team_changes(): void {
		delete_option( 'rondo_age_group_access' );
		$id = $this->person( 'Onder 14', [ $this->job() ] );
		$this->assertFalse( AccessControl::is_scoped_member() );
		$this->assertTrue( UserRoles::is_kader() );
		$this->assertTrue( AccessControl::can_view_person( $id ) );
		Fields::update_for_post( $id, 'work_history', [ $this->job( [ 'end_date' => '2020-01-01' ] ) ] );
		$this->assertFalse( AccessControl::can_view_person( $id ) );
		Fields::update_for_post( $id, 'work_history', [ $this->job() ] );
		$this->assertTrue( AccessControl::can_view_person( $id ) );
		wp_trash_post( $this->team_id );
		$this->assertFalse( AccessControl::can_view_person( $id ) );
		$this->assertSame( [ 0 ], AccessControl::visible_person_ids_or_null() );
	}

	public function test_include_and_age_filters_can_only_narrow_the_union(): void {
		$dispensation = $this->person( 'Onder 14', [ $this->job() ] );
		$hidden       = $this->person( 'Onder 14' );
		$this->person( 'Onder 13' );
		$response = $this->request( '/wp/v2/people', [ 'include' => [ $dispensation, $hidden ] ] );
		$this->assertSame( [ $dispensation ], array_column( $response->get_data(), 'id' ) );
		$response = $this->request( '/rondo/v1/people/filtered', [ 'leeftijdsgroep' => 'Onder 14' ] );
		$this->assertSame( [ $dispensation ], array_column( $response->get_data()['people'], 'id' ) );
	}

	public function test_other_roles_combine_but_kaderlijst_role_does_not_grant_directory_access(): void {
		$id = $this->person( 'Onder 14', [ $this->job() ] );
		get_role( $this->role )->add_cap( UserRoles::KADERLIJST_CAPABILITY );
		$this->assertSame( [], AccessControl::get_permitted_team_ids() );
		$this->assertFalse( AccessControl::can_view_person( $id ) );
		get_role( $this->role )->remove_cap( UserRoles::KADERLIJST_CAPABILITY );
		$user = get_userdata( $this->user_id );
		$user->add_role( 'rondo_user' );
		update_option( 'rondo_age_group_access', [ 'rondo_user' => [ 'Onder 12' ] ] );
		$younger = $this->person( 'Onder 12' );
		$this->assertEqualsCanonicalizing( [ $id, $younger ], AccessControl::visible_person_ids_or_null( $this->user_id ) );
		$user->add_cap( 'ledenadministratie' );
		$this->assertNull( AccessControl::visible_person_ids_or_null( $this->user_id ) );
	}

	public function test_settings_validate_all_inputs_before_saving_and_preserve_legacy_clients(): void {
		$this->assertSame( 403, $this->request( '/rondo/v1/settings/age-group-access' )->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$person_id = $this->person( 'Onder 13' );
		foreach ( [ [ $person_id ], [ -1 ], [ '123' ], [ 1.5 ], [ 99999999 ], 'invalid' ] as $ids ) {
			$response = $this->request(
				'/rondo/v1/settings/age-group-access',
				[
					'roles'      => [],
					'team_roles' => [ $this->role => $ids ],
				],
				'POST'
				);
			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( [ $this->role => [ 'Onder 13' ] ], get_option( 'rondo_age_group_access' ) );
		}
		$response = $this->request( '/rondo/v1/settings/age-group-access', [ 'roles' => [] ], 'POST' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ $this->role => [ $this->team_id ] ], (array) $response->get_data()['team_roles'] );
		$this->assertContains( $this->team_id, array_column( $response->get_data()['available_teams'], 'id' ) );
		$response = $this->request(
			'/rondo/v1/settings/age-group-access',
			[
				'roles'      => [],
				'team_roles' => [ $this->role => [ $this->team_id, $this->team_id ] ],
			],
			'POST'
			);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ $this->role => [ $this->team_id ] ], get_option( 'rondo_team_access' ) );
		$this->request(
			'/rondo/v1/settings/age-group-access',
			[
				'roles'      => [],
				'team_roles' => [],
			],
			'POST'
			);
		$this->assertSame( [], get_option( 'rondo_team_access' ) );
	}

	public function test_deleted_role_cannot_revive_previous_access_when_recreated(): void {
		UserRoles::remove_custom_role( $this->role );
		$this->role = UserRoles::add_custom_role( 'Coordinator test' );
		$this->assertArrayNotHasKey( $this->role, get_option( 'rondo_team_access' ) );
		$this->assertArrayNotHasKey( $this->role, get_option( 'rondo_age_group_access' ) );
	}
}
