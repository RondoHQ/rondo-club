<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\REST\Api;
use Rondo\REST\Capabilities;
use Rondo\REST\Commissies;
use Rondo\REST\Feedback;
use Rondo\REST\Reminders;
use Rondo\REST\Teams;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

class SectionAccessTest extends RondoTestCase {
	private string $role;
	private int $user_id;

	protected function set_up(): void {
		parent::set_up();
		$this->role    = UserRoles::add_custom_role( 'Section test coordinator' );
		$this->user_id = $this->createRondoUser( [ 'role' => $this->role ] );
		update_option( 'rondo_age_group_access', [ $this->role => [ 'Onder 11' ] ] );
		delete_option( 'rondo_team_access' );
		wp_set_current_user( $this->user_id );
		$this->bootRestControllers( [ Api::class, Capabilities::class, Commissies::class, Feedback::class, Reminders::class, Teams::class, UserSettings::class ] );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	protected function tear_down(): void {
		UserRoles::remove_custom_role( $this->role );
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_team_access' );
		parent::tear_down();
	}

	private function request( string $route, array $params = [], string $method = 'GET' ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	private function grant( string $cap ): void {
		get_role( $this->role )->add_cap( $cap );
		UserRoles::sync_role_capabilities( $this->role );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
	}

	public function test_sections_are_independent_and_block_direct_reads(): void {
		$committee = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Scope committee',
			]
			);
		$routes    = [ '/wp/v2/commissies', '/wp/v2/commissies/' . $committee, '/rondo/v1/commissies/member-counts', '/rondo/v1/commissies/' . $committee . '/people', '/rondo/v1/entity/' . $committee, '/rondo/v1/anniversaries', '/rondo/v1/feedback', '/wp/v2/feedback' ];
		foreach ( $routes as $route ) {
			$this->assertSame( 403, $this->request( $route )->get_status(), $route );
		}
		$this->assertWPError( wp_get_ability( 'rondo/get-record' )->execute( [ 'id' => $committee ] ) );
		$this->grant( 'commissies' );
		$this->assertSame( 200, $this->request( '/wp/v2/commissies/' . $committee )->get_status() );
		$this->assertSame( 200, $this->request( '/rondo/v1/commissies/' . $committee . '/people' )->get_status() );
		$this->assertSame( 403, $this->request( '/rondo/v1/anniversaries' )->get_status() );
		$this->assertSame( 403, $this->request( '/rondo/v1/feedback' )->get_status() );
		$this->grant( 'jubilarissen' );
		$this->assertSame( 200, $this->request( '/rondo/v1/anniversaries' )->get_status() );
		$this->assertSame( 403, $this->request( '/rondo/v1/feedback' )->get_status() );
		$this->grant( 'feedback' );
		$this->assertSame( 200, $this->request( '/rondo/v1/feedback' )->get_status() );
		$this->assertSame( [ 'Onder 11' ], AccessControl::get_permitted_age_groups() );
	}

	public function test_every_member_can_submit_and_follow_own_feedback_only(): void {
		wp_set_current_user( $this->createRondoUser() );
		$response = $this->request(
			'/rondo/v1/feedback',
			[
				'title'         => 'Test feedback',
				'feedback_type' => 'bug',
			],
			'POST'
			);
		$this->assertSame( 200, $response->get_status() );
		$id = $response->get_data()['id'];
		$this->assertSame( 200, $this->request( '/rondo/v1/feedback/' . $id )->get_status() );
		$this->assertSame( 200, $this->request( '/rondo/v1/feedback/' . $id . '/comments' )->get_status() );
		$this->assertSame( 403, $this->request( '/rondo/v1/feedback' )->get_status() );
		wp_set_current_user( $this->user_id );
		$this->assertSame( 403, $this->request( '/rondo/v1/feedback/' . $id )->get_status() );
		$this->assertSame( 403, $this->request( '/rondo/v1/feedback/' . $id . '/comments' )->get_status() );
		$this->assertSame( 403, $this->request( '/wp/v2/feedback/' . $id )->get_status() );
		$this->grant( 'feedback' );
		$this->assertSame( 200, $this->request( '/rondo/v1/feedback/' . $id )->get_status() );
	}

	public function test_team_scope_covers_lists_direct_links_search_and_abilities(): void {
		$assigned = $this->createOrganization( [ 'post_title' => 'Scope O11' ] );
		$own      = $this->createOrganization( [ 'post_title' => 'Scope senior' ] );
		$hidden   = $this->createOrganization( [ 'post_title' => 'Scope O13' ] );
		update_option( 'rondo_team_access', [ $this->role => [ $assigned ] ] );
		$person = $this->createPerson(
			[],
			[
				'first_name'   => 'Coach',
				'work_history' => [
					[
						'team'       => $own,
						'job_title'  => 'Trainer/coach',
						'is_current' => true,
					],
				],
			]
			);
		update_user_meta( $this->user_id, 'rondo_linked_person_id', $person );
		$this->assertEqualsCanonicalizing( [ $assigned, $own ], AccessControl::visible_team_ids_or_null() );
		$list = $this->request( '/wp/v2/teams' );
		$this->assertSame( 200, $list->get_status() );
		$this->assertEqualsCanonicalizing( [ $assigned, $own ], array_column( $list->get_data(), 'id' ) );
		$this->assertSame( 2, $list->get_headers()['X-WP-Total'] );
		$this->assertSame( [], $this->request( '/wp/v2/teams', [ 'include' => [ $hidden ] ] )->get_data() );
		foreach ( [ '/wp/v2/teams/', '/rondo/v1/entity/' ] as $prefix ) {
			$this->assertSame( 200, $this->request( $prefix . $assigned )->get_status() );
			$this->assertSame( 200, $this->request( $prefix . $own )->get_status() );
			$this->assertSame( 403, $this->request( $prefix . $hidden )->get_status() );
		}
		$this->assertSame( 200, $this->request( '/rondo/v1/teams/' . $assigned . '/people' )->get_status() );
		$this->assertSame( 403, $this->request( '/rondo/v1/teams/' . $hidden . '/people' )->get_status() );
		$search = $this->request( '/rondo/v1/search', [ 'q' => 'Scope' ] );
		$this->assertEqualsCanonicalizing( [ $assigned, $own ], array_column( $search->get_data()['teams'], 'id' ) );
		$query = new \WP_Query(
			[
				'post_type'      => [ 'team', 'commissie' ],
				's'              => 'Scope',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
			);
		$this->assertEqualsCanonicalizing( [ $assigned, $own ], $query->posts );
		$ability = wp_get_ability( 'rondo/search-records' )->execute(
			[
				'query'    => 'Scope',
				'contexts' => [ 'team' ],
			]
			);
		$this->assertNotWPError( $ability );
		$this->assertEqualsCanonicalizing( [ $assigned, $own ], array_column( $ability['records'], 'id' ) );
		$this->assertWPError( wp_get_ability( 'rondo/get-record' )->execute( [ 'id' => $hidden ] ) );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertNull( AccessControl::visible_team_ids_or_null() );
		$this->assertSame( 200, $this->request( '/wp/v2/teams/' . $hidden )->get_status() );
	}

	public function test_empty_and_deleted_team_selections_do_not_open_all_teams(): void {
		$team = $this->createOrganization( [ 'post_title' => 'Other year' ] );
		$this->assertSame( [], AccessControl::visible_team_ids_or_null() );
		$this->assertSame( [], $this->request( '/wp/v2/teams' )->get_data() );
		delete_option( 'rondo_age_group_access' );
		update_option( 'rondo_team_access', [ $this->role => [ 999999 ] ] );
		$this->assertSame( [], AccessControl::visible_team_ids_or_null() );
		$this->assertSame( 403, $this->request( '/wp/v2/teams/' . $team )->get_status() );
		$query = new \WP_Query(
			[
				'post_type' => [ 'team', 'commissie' ],
				'post__in'  => [ $team ],
				'fields'    => 'ids',
			]
			);
		$this->assertSame( [], $query->posts );
	}

	public function test_upgrade_grants_sections_only_to_admin_and_matrix_can_assign_them(): void {
		update_option( UserRoles::ROLES_VERSION_OPTION, 13 );
		( new UserRoles() )->maybe_upgrade_roles();
		foreach ( array_keys( UserRoles::SECTION_CAPABILITIES ) as $cap ) {
			$this->assertTrue( get_role( 'administrator' )->has_cap( $cap ) );
			$this->assertFalse( get_role( $this->role )->has_cap( $cap ) );
			$this->assertFalse( get_role( 'rondo_user' )->has_cap( $cap ) );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$caps     = array_fill_keys( array_keys( UserRoles::SECTION_CAPABILITIES ), true );
		$response = $this->request( '/rondo/v1/settings/capability-matrix', [ 'roles' => [ $this->role => [ 'capabilities' => $caps ] ] ], 'POST' );
		$this->assertSame( 200, $response->get_status() );
		foreach ( $caps as $cap => $enabled ) {
			$this->assertTrue( $response->get_data()['roles'][ $this->role ]['capabilities'][ $cap ] );
		}
		wp_set_current_user( $this->user_id );
		$this->assertSame( 200, $this->request( '/wp/v2/commissies' )->get_status() );
		$this->assertSame( [ 'Onder 11' ], AccessControl::get_permitted_age_groups() );
	}

	public function test_dashboard_flag_hides_only_scoped_coordinator_dashboard(): void {
		$this->assertFalse( $this->request( '/rondo/v1/user/me' )->get_data()['can_access_dashboard'] );
		delete_option( 'rondo_age_group_access' );
		$team = $this->createOrganization();
		update_option( 'rondo_team_access', [ $this->role => [ $team ] ] );
		$this->assertFalse( $this->request( '/rondo/v1/user/me' )->get_data()['can_access_dashboard'] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_bestuur' ] ) );
		$this->assertTrue( $this->request( '/rondo/v1/user/me' )->get_data()['can_access_dashboard'] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( $this->request( '/rondo/v1/user/me' )->get_data()['can_access_dashboard'] );
	}

	public function test_dashboard_cache_changes_when_section_rights_are_revoked(): void {
		$this->grant( 'jubilarissen' );
		$before = $this->request( '/rondo/v1/dashboard' );
		$this->assertSame( 200, $before->get_status() );
		$this->assertTrue( $before->get_data()['current_user']['can_access_jubilarissen'] );
		get_role( $this->role )->remove_cap( 'jubilarissen' );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
		$after = $this->request( '/rondo/v1/dashboard' )->get_data();
		$this->assertFalse( $after['current_user']['can_access_jubilarissen'] );
		$this->assertSame( [], $after['upcoming_anniversaries'] );
		$this->assertSame( 0, $after['stats']['total_commissies'] );
		$this->assertSame( 0, $after['stats']['open_feedback_count'] );
	}
}
