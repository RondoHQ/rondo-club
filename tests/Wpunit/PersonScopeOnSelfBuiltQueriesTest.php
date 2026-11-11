<?php

namespace Tests\Wpunit;

use Rondo\REST\Api;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Person scoping on the endpoints that assemble their own result sets.
 *
 * `AccessControl::filter_queries()` hooks `pre_get_posts` but returns early when
 * `suppress_filters` is set — and WordPress' own `get_posts()` sets that flag by
 * **default**. Every person query built with `get_posts()` is therefore unscoped
 * unless it opts back in, which is how search and the dashboard came to hand a
 * plain member the whole club by name.
 *
 * The rule these tests hold to: a member who may see nobody must learn nothing —
 * not names, not counts.
 */
class PersonScopeOnSelfBuiltQueriesTest extends RondoTestCase {

	private const PEOPLE = 5;

	private function seed_people(): void {
		$author_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		for ( $i = 1; $i <= self::PEOPLE; $i++ ) {
			$this->createPerson(
				[
					'post_author' => $author_id,
					'post_title'  => "Zoekbaar Persoon $i",
				],
				[
					'first_name' => 'Zoekbaar',
					'last_name'  => "Persoon $i",
				]
			);
		}
	}

	public function test_search_returns_nothing_to_a_member_who_may_see_nobody(): void {
		$server = $this->bootRestControllers( [ Api::class ] );
		$this->seed_people();

		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'scoped_member' ] ) );

		$request = new WP_REST_Request( 'GET', '/rondo/v1/search' );
		$request->set_param( 'q', 'Zoekbaar' );
		$response = $server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data()['people'] ?? [] );
	}

	public function test_dashboard_leaks_neither_names_nor_counts(): void {
		$server = $this->bootRestControllers( [ Api::class ] );
		$this->seed_people();

		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'scoped_member' ] ) );

		$data = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/dashboard' ) )->get_data();

		$this->assertSame( [], $data['recent_people'] ?? [], 'names must not appear in recent people' );
		$this->assertSame(
			0,
			(int) ( $data['stats']['total_people'] ?? -1 ),
			'a count is a disclosure too: the raw-SQL dashboard query needs the scope applied by hand'
		);
	}

	public function test_search_email_fragment_respects_age_group_scope(): void {
		$server = $this->bootRestControllers( [ Api::class ] );

		$role = \Rondo\Core\UserRoles::add_custom_role( 'Search scope coordinator' );
		try {
			update_option( 'rondo_age_group_access', [ $role => [ 'Onder 13' ] ] );
			delete_option( 'rondo_team_access' );

			$author_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
			$visible   = $this->createPerson(
				[ 'post_author' => $author_id, 'post_title' => 'Zichtbaar Lid' ],
				[ 'first_name' => 'Zichtbaar', 'last_name' => 'Lid', 'leeftijdsgroep' => 'Onder 13', 'email_1' => 'zichtbaar@voorbeeld.nl' ]
			);
			$hidden    = $this->createPerson(
				[ 'post_author' => $author_id, 'post_title' => 'Verborgen Lid' ],
				[ 'first_name' => 'Verborgen', 'last_name' => 'Lid', 'leeftijdsgroep' => 'Senioren', 'email_1' => 'verborgen@voorbeeld.nl' ]
			);

			wp_set_current_user( $this->createRondoUser( [ 'role' => $role ] ) );

			$request = new WP_REST_Request( 'GET', '/rondo/v1/search' );
			$request->set_param( 'q', 'voorbeeld.nl' );
			$people = $server->dispatch( $request )->get_data()['people'] ?? [];
			$ids    = array_map( 'intval', wp_list_pluck( $people, 'id' ) );

			$this->assertContains( $visible, $ids );
			$this->assertNotContains( $hidden, $ids, 'an OR meta query must not turn the age-group scope clause into an alternative match' );
		} finally {
			\Rondo\Core\UserRoles::remove_custom_role( $role );
			delete_option( 'rondo_age_group_access' );
			delete_option( 'rondo_team_access' );
		}
	}

	public function test_management_still_sees_everyone(): void {
		$server = $this->bootRestControllers( [ Api::class ] );
		$this->seed_people();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] ) );

		$request = new WP_REST_Request( 'GET', '/rondo/v1/search' );
		$request->set_param( 'q', 'Zoekbaar' );
		$people = $server->dispatch( $request )->get_data()['people'] ?? [];

		$this->assertCount( self::PEOPLE, $people, 'scoping must not narrow users who are entitled to everyone' );

		$data = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/dashboard' ) )->get_data();
		$this->assertSame( self::PEOPLE, (int) $data['stats']['total_people'] );
	}
}
