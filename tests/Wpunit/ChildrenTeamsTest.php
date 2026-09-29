<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\People\ParentRelationshipService;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/** Parent filtering and profile summaries must agree without disclosing hidden children or teams. */
class ChildrenTeamsTest extends RondoTestCase {
	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->server = $this->bootRestControllers( [ People::class ] );
	}

	private function player( int $team, array $position = [], array $fields = [] ): int {
		return $this->createPerson(
			[],
			array_merge(
			[
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

	private function parent_of( array $children, array $fields = [], string $slug = 'child' ): int {
		$term = term_exists( $slug, 'relationship_type' ) ?: wp_insert_term( ucfirst( $slug ), 'relationship_type', [ 'slug' => $slug ] );
		return $this->createPerson(
			[],
			array_merge(
			$fields,
			[
				'relationships' => array_map(
				static fn( $id ) => [
					'related_person'    => $id,
					'relationship_type' => (int) $term['term_id'],
				],
				$children
				),
			]
			)
			);
	}

	private function filtered( array $params ): array {
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/people/filtered' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	private function profile( int $id ): array {
		$response = $this->server->dispatch( new \WP_REST_Request( 'GET', '/wp/v2/people/' . $id ) );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	public function test_multiple_children_and_teams_are_deduplicated_and_preserve_names(): void {
		$team      = $this->createOrganization( [ 'post_title' => 'JO13-1' ] );
		$other     = $this->createOrganization( [ 'post_title' => 'JO13-1' ] );
		$child     = $this->player( $team );
		$positions = Fields::get_for_post( $child, 'work_history' );
		Fields::update_for_post(
			$child,
			'work_history',
			array_merge(
			$positions,
			$positions,
			[
				[
					'team'       => $other,
					'job_title'  => 'Teamspeler',
					'is_current' => true,
				],
			]
			)
			);
		$sibling       = $this->player( $team );
		$parent        = $this->parent_of( [ $child, $sibling, $child ] );
		$second        = $this->parent_of( [ $sibling ] );
		$own_team_only = $this->player( $team );
		$this->parent_of( [ $own_team_only ], [], 'sibling' );
		$this->assertEqualsCanonicalizing( [ $parent, $second ], array_column( $this->filtered( [ 'child_team' => $team ] )['people'], 'id' ) );
		$this->assertSame( [ $parent ], array_column( $this->filtered( [ 'child_team' => $other ] )['people'], 'id' ) );
		$data = $this->profile( $parent );
		$this->assertCount( 2, $data['children_teams'] );
		$this->assertSame( $child, $data['children_teams'][0]['child_id'] );
		$this->assertEqualsCanonicalizing(
			[
				[
					'id'   => $team,
					'name' => 'JO13-1',
				],
				[
					'id'   => $other,
					'name' => 'JO13-1',
				],
			],
			$data['children_teams'][0]['teams']
			);
		$this->assertSame(
			[
				[
					'id'   => $team,
					'name' => 'JO13-1',
				],
			],
			$data['children_teams'][1]['teams']
			);
		$this->assertArrayNotHasKey( 'children_teams', $data['fields'] );
	}

	public function test_only_current_player_roles_and_active_children_qualify(): void {
		$team     = $this->createOrganization();
		$excluded = [
			$this->player( $team, [ 'job_title' => 'Trainer/coach' ] ),
			$this->player( $team, [ 'start_date' => '2099-01-01' ] ),
			$this->player( $team, [ 'end_date' => '2000-01-01' ] ),
			$this->player( $team, [ 'is_current' => false ] ),
			$this->player( $team, [], [ 'former_member' => true ] ),
			$this->player( $team, [], [ 'datum_overlijden' => '2020-01-01' ] ),
		];
		$draft    = $this->player( $team );
		wp_update_post(
			[
				'ID'          => $draft,
				'post_status' => 'draft',
			]
			);
		$excluded[] = $draft;
		foreach ( $excluded as $id ) {
			$parent = $this->parent_of( [ $id ] );
			$this->assertSame( [], $this->profile( $parent )['children_teams'] );
		}
		$this->assertSame( 0, $this->filtered( [ 'child_team' => $team ] )['total'] );
		update_option( 'rondo_player_roles', [ 'Custom player' ] );
		try {
			$parent = $this->parent_of( [ $this->player( $team, [ 'job_title' => 'Custom player' ] ) ] );
			$this->assertSame( [ $parent ], array_column( $this->filtered( [ 'child_team' => $team ] )['people'], 'id' ) );
			$this->assertCount( 1, $this->profile( $parent )['children_teams'] );
		} finally {
			delete_option( 'rondo_player_roles' );
		}
	}

	public function test_account_filter_pagination_and_empty_invalid_teams(): void {
		$team  = $this->createOrganization();
		$child = $this->player( $team );
		$ids   = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->parent_of( [ $child ], [ 'first_name' => 'Parent ' . $i ] );
		}
		update_post_meta( $ids[0], '_rondo_wp_user_id', self::factory()->user->create() );
		$params = [
			'child_team'        => $team,
			'has_rondo_account' => '0',
			'per_page'          => 1,
		];
		$first  = $this->filtered( $params );
		$second = $this->filtered( array_merge( $params, [ 'page' => 2 ] ) );
		$this->assertSame( 2, $first['total'] );
		$this->assertSame( 2, $first['total_pages'] );
		$this->assertEqualsCanonicalizing( array_slice( $ids, 1 ), array_merge( array_column( $first['people'], 'id' ), array_column( $second['people'], 'id' ) ) );
		foreach ( [ 99999999, $child, $this->createOrganization() ] as $empty_team ) {
			$this->assertSame( 0, $this->filtered( [ 'child_team' => $empty_team ] )['total'] );
		}
		foreach ( [ 0, -1, 'invalid' ] as $invalid ) {
			$request = new \WP_REST_Request( 'GET', '/rondo/v1/people/filtered' );
			$request->set_param( 'child_team', $invalid );
			$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
		}
	}

	public function test_hidden_children_and_parents_do_not_leak_through_either_direction(): void {
		$team        = $this->createOrganization();
		$visible     = $this->player( $team, [], [ 'leeftijdsgroep' => 'Onder 13' ] );
		$hidden      = $this->player( $team, [], [ 'leeftijdsgroep' => 'Onder 14' ] );
		$parent      = $this->parent_of( [ $visible, $hidden ], [ 'leeftijdsgroep' => 'Onder 13' ] );
		$hidden_only = $this->parent_of( [ $hidden ], [ 'leeftijdsgroep' => 'Onder 13' ] );
		$this->parent_of( [ $visible ], [ 'leeftijdsgroep' => 'Onder 14' ] );
		$user_id = $this->createRondoUser();
		( new \WP_User( $user_id ) )->add_cap( 'teams' );
		wp_set_current_user( $user_id );
		update_option( 'rondo_age_group_access', [ 'rondo_user' => [ 'Onder 13' ] ] );
		AccessControl::flush_visible_person_ids_cache();
		try {
			$this->assertSame( [ $parent ], array_column( $this->filtered( [ 'child_team' => $team ] )['people'], 'id' ) );
			$this->assertSame( [ $visible ], array_column( $this->profile( $parent )['children_teams'], 'child_id' ) );
			$this->assertSame( [], $this->profile( $hidden_only )['children_teams'] );
		} finally {
			delete_option( 'rondo_age_group_access' );
		}
	}

	public function test_hidden_and_unpublished_teams_are_not_exposed(): void {
		$team   = $this->createOrganization();
		$child  = $this->player( $team, [], [ 'leeftijdsgroep' => 'Onder 13' ] );
		$parent = $this->parent_of( [ $child ], [ 'leeftijdsgroep' => 'Onder 13' ] );
		wp_set_current_user( $this->createRondoUser() );
		update_option( 'rondo_age_group_access', [ 'rondo_user' => [ 'Onder 13' ] ] );
		AccessControl::flush_visible_person_ids_cache();
		try {
			$this->assertSame( [], $this->profile( $parent )['children_teams'] );
			$this->assertSame( 0, $this->filtered( [ 'child_team' => $team ] )['total'] );
			$response = $this->server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/people/filter-options' ) );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( [], $response->get_data()['child_teams'] );
		} finally {
			delete_option( 'rondo_age_group_access' );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		wp_update_post(
			[
				'ID'          => $team,
				'post_status' => 'draft',
			]
			);
		$this->assertSame( [], $this->profile( $parent )['children_teams'] );
		$this->assertSame( 0, $this->filtered( [ 'child_team' => $team ] )['total'] );
	}

	public function test_scoped_members_cannot_infer_hidden_work_history(): void {
		$team   = $this->createOrganization();
		$parent = $this->parent_of( [ $this->player( $team ) ] );
		$user   = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user, 'rondo_linked_person_id', $parent );
		wp_set_current_user( $user );
		AccessControl::flush_visible_person_ids_cache();
		$service = new ParentRelationshipService();
		$this->assertTrue( AccessControl::is_scoped_member() );
		$this->assertSame( [], $service->get_children_teams( $parent ) );
		$this->assertSame( [], $service->get_child_team_options() );
		$this->assertSame( [], $service->get_parent_ids_for_team( $team ) );
	}
}
