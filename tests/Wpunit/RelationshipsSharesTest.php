<?php

namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;
use RONDO_Access_Control;
use RONDO_Visibility;
use RONDO_Workspace_Members;
use RONDO_User_Roles;
use RONDO_REST_People;
use RONDO_REST_Teams;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Tests for CPT relationships, sharing endpoints, and bulk update operations.
 *
 * Covers:
 * - Person-team relationship via work_history
 * - Person-dates relationship via related_people
 * - Sharing endpoints for people and teams
 * - Bulk update operations with ownership checks
 */
class RelationshipsSharesTest extends RondoTestCase {

	/**
	 * RONDO_Access_Control instance for access checks.
	 *
	 * @var RONDO_Access_Control
	 */
	private RONDO_Access_Control $access_control;

	/**
	 * REST People handler.
	 *
	 * @var RONDO_REST_People
	 */
	private RONDO_REST_People $rest_people;

	/**
	 * REST Teams handler.
	 *
	 * @var RONDO_REST_Teams
	 */
	private RONDO_REST_Teams $rest_teams;

	protected function set_up(): void {
		parent::set_up();
		$this->access_control = new RONDO_Access_Control();

		// Manually initialize REST API classes for testing
		$this->rest_people = new RONDO_REST_People();
		$this->rest_teams  = new RONDO_REST_Teams();

		// Initialize the REST server and register routes
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Helper: Create an approved Rondo user.
	 *
	 * @param array $args Optional user arguments.
	 * @return int User ID.
	 */
	protected function createApprovedUser( array $args = [] ): int {
		$user_id = $this->createRondoUser( $args );
		return $user_id;
	}

	/**
	 * Helper: Create a workspace with owner.
	 *
	 * @param int   $owner_id User ID of workspace owner.
	 * @param array $args     Optional post arguments.
	 * @return int Workspace post ID.
	 */
	protected function createWorkspace( int $owner_id, array $args = [] ): int {
		$defaults = [
			'post_type'   => 'workspace',
			'post_status' => 'publish',
			'post_author' => $owner_id,
			'post_title'  => 'Test Workspace',
		];

		$workspace_id = self::factory()->post->create( array_merge( $defaults, $args ) );

		// Create the workspace_access term
		$term_slug = 'workspace-' . $workspace_id;
		$term_name = get_the_title( $workspace_id );
		wp_insert_term( $term_name, 'workspace_access', [ 'slug' => $term_slug ] );

		// Add owner as admin member
		RONDO_Workspace_Members::add( $workspace_id, $owner_id, RONDO_Workspace_Members::ROLE_ADMIN );

		return $workspace_id;
	}

	/**
	 * Helper: Assign a post to a workspace.
	 *
	 * @param int $post_id      Post ID.
	 * @param int $workspace_id Workspace post ID.
	 */
	protected function assignToWorkspace( int $post_id, int $workspace_id ): void {
		$term_slug = 'workspace-' . $workspace_id;
		$term      = get_term_by( 'slug', $term_slug, 'workspace_access' );

		if ( $term && ! is_wp_error( $term ) ) {
			wp_set_object_terms( $post_id, [ $term->term_id ], 'workspace_access' );
		}
	}

	/**
	 * Helper: Make a REST request and return the response.
	 *
	 * @param string $method  HTTP method.
	 * @param string $route   Route path (without /wp-json prefix).
	 * @param array  $params  Request parameters.
	 * @return WP_REST_Response The REST response.
	 */
	protected function restRequest( string $method, string $route, array $params = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	// =========================================================================
	// Task 1: Test CPT relationships via REST
	// =========================================================================

	/**
	 * Test person-team relationship endpoint.
	 * GET /rondo/v1/teams/{id}/people should return people working at the team.
	 */
	public function test_team_people_endpoint_returns_employees(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_rel1' ] );
		wp_set_current_user( $alice_id );

		// Create team "Acme Corp"
		$team_id = $this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Acme Corp',
			]
		);

		// Create person with work_history containing Acme Corp
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'John Doe',
			],
			[
				'first_name' => 'John',
				'last_name'  => 'Doe',
			]
		);

		// Set work_history to link person to team
		$work_history = [
			[
				'team'       => $team_id,
				'job_title'  => 'Engineer',
				'start_date' => '2020-01-01',
				'end_date'   => '',
				'is_current' => true,
			],
		];
		\Rondo\Fields\Fields::update_for_post( $person_id, 'work_history', $work_history );

		// GET /rondo/v1/teams/{id}/people
		$response = $this->restRequest( 'GET', '/rondo/v1/teams/' . $team_id . '/people' );

		$this->assertEquals( 200, $response->get_status(), 'Response should be 200 OK' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'current', $data, 'Response should have current employees' );
		$this->assertArrayHasKey( 'former', $data, 'Response should have former employees' );

		// John should be in current employees
		$this->assertCount( 1, $data['current'], 'Should have 1 current employee' );
		$this->assertEquals( $person_id, $data['current'][0]['id'], 'Current employee should be John' );
		$this->assertEquals( 'Engineer', $data['current'][0]['job_title'], 'Job title should be Engineer' );
	}

	/**
	 * Test person-team relationship with former employees.
	 */
	public function test_team_people_endpoint_distinguishes_current_former(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_rel2' ] );
		wp_set_current_user( $alice_id );

		// Create team
		$team_id = $this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'TechCorp',
			]
		);

		// Create current employee
		$current_person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Current Employee',
			]
		);
		\Rondo\Fields\Fields::update_for_post(
			$current_person_id,
			'work_history',
			[
				[
					'team'       => $team_id,
					'job_title'  => 'Developer',
					'start_date' => '2023-01-01',
					'end_date'   => '',
					'is_current' => true,
				],
			]
			);

		// Create former employee (has end_date in the past)
		$former_person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Former Employee',
			]
		);
		\Rondo\Fields\Fields::update_for_post(
			$former_person_id,
			'work_history',
			[
				[
					'team'       => $team_id,
					'job_title'  => 'Manager',
					'start_date' => '2020-01-01',
					'end_date'   => '2022-12-31',
					'is_current' => false,
				],
			]
			);

		$response = $this->restRequest( 'GET', '/rondo/v1/teams/' . $team_id . '/people' );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['current'], 'Should have 1 current employee' );
		$this->assertCount( 1, $data['former'], 'Should have 1 former employee' );
		$this->assertEquals( $current_person_id, $data['current'][0]['id'] );
		$this->assertEquals( $former_person_id, $data['former'][0]['id'] );
	}

	/**
	 * Test person computed field birth_year from birthdate field.
	 */
	public function test_person_computed_fields_birth_year(): void {
		$alice_id = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );
		wp_set_current_user( $alice_id );

		// Create person with birthdate
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Person with Birthday',
			],
			[
				'birthdate' => '1985-03-20',
			]
		);

		// GET person via REST API
		$response = $this->restRequest( 'GET', '/wp/v2/people/' . $person_id );

		$this->assertEquals( 200, $response->get_status(), 'Response should be 200 OK' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'birth_year', $data, 'Response should have birth_year field' );
		$this->assertEquals( 1985, $data['birth_year'], 'Birth year should be 1985' );
	}

	/**
	 * Test is_deceased is always false (death date feature removed).
	 */
	public function test_person_is_deceased_always_false(): void {
		$alice_id = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );
		wp_set_current_user( $alice_id );

		// Create person
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Test Person',
			]
		);

		// GET person via REST API
		$response = $this->restRequest( 'GET', '/wp/v2/people/' . $person_id );

		$this->assertEquals( 200, $response->get_status(), 'Response should be 200 OK' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'is_deceased', $data, 'Response should have is_deceased field' );
		$this->assertFalse( $data['is_deceased'], 'is_deceased should always be false (feature removed)' );
	}

	// =========================================================================
	// Task 2: Test sharing and bulk update endpoints
	// =========================================================================

	/**
	 * Test sharing endpoint for people - add share.
	 * POST /rondo/v1/people/{id}/shares
	 */
	public function test_people_share_add(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_share1' ] );
		$bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_share1' ] );
		wp_set_current_user( $alice_id );

		// Create person as Alice
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Shared Person',
			]
		);

		// POST share with Bob (view permission)
		$response = $this->restRequest(
			'POST',
			'/rondo/v1/people/' . $person_id . '/shares',
			[
				'user_id'    => $bob_id,
				'permission' => 'view',
			]
		);

		$this->assertEquals( 200, $response->get_status(), 'Add share should return 200' );

		$data = $response->get_data();
		$this->assertTrue( $data['success'], 'Share should be added successfully' );
	}

	/**
	 * Test sharing endpoint for teams.
	 * POST/GET/DELETE /rondo/v1/teams/{id}/shares
	 */
	public function test_teams_share_lifecycle(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_share5' ] );
		$bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_share5' ] );
		wp_set_current_user( $alice_id );

		// Create team as Alice
		$team_id = $this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Shared Team',
			]
		);

		// POST share
		$add_response = $this->restRequest(
			'POST',
			'/rondo/v1/teams/' . $team_id . '/shares',
			[
				'user_id'    => $bob_id,
				'permission' => 'view',
			]
		);
		$this->assertEquals( 200, $add_response->get_status(), 'Add share should return 200' );

		// GET shares
		$get_response = $this->restRequest( 'GET', '/rondo/v1/teams/' . $team_id . '/shares' );
		$shares       = $get_response->get_data();
		$this->assertCount( 1, $shares, 'Should have 1 share' );
		$this->assertEquals( $bob_id, $shares[0]['user_id'], 'Share should be with Bob' );

		// DELETE share
		$del_response = $this->restRequest( 'DELETE', '/rondo/v1/teams/' . $team_id . '/shares/' . $bob_id );
		$this->assertEquals( 200, $del_response->get_status(), 'Delete share should return 200' );

		// Verify share removed
		$get_response2 = $this->restRequest( 'GET', '/rondo/v1/teams/' . $team_id . '/shares' );
		$this->assertCount( 0, $get_response2->get_data(), 'Should have no shares after removal' );
	}

	/**
	 * Test that sharing endpoint denies access to non-owner.
	 */
	public function test_share_endpoint_denies_non_owner(): void {
		$alice_id   = $this->createApprovedUser( [ 'user_login' => 'alice_share6' ] );
		$bob_id     = $this->createApprovedUser( [ 'user_login' => 'bob_share6' ] );
		$charlie_id = $this->createApprovedUser( [ 'user_login' => 'charlie_share6' ] );

		// Alice creates person
		wp_set_current_user( $alice_id );
		$person_id = $this->createPerson( [ 'post_author' => $alice_id ] );

		// Bob tries to share Alice's person with Charlie
		wp_set_current_user( $bob_id );

		$response = $this->restRequest(
			'POST',
			'/rondo/v1/people/' . $person_id . '/shares',
			[
				'user_id'    => $charlie_id,
				'permission' => 'view',
			]
		);

		// Should fail with false (permission callback returns false, typically 403)
		$this->assertNotEquals( 200, $response->get_status(), 'Non-owner should not be able to share' );
	}

	/**
	 * Test cannot share with yourself.
	 */
	public function test_cannot_share_with_self(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_share7' ] );
		wp_set_current_user( $alice_id );

		$person_id = $this->createPerson( [ 'post_author' => $alice_id ] );

		// Alice tries to share with herself
		$response = $this->restRequest(
			'POST',
			'/rondo/v1/people/' . $person_id . '/shares',
			[
				'user_id'    => $alice_id,
				'permission' => 'view',
			]
		);

		$this->assertEquals( 400, $response->get_status(), 'Should not be able to share with yourself' );
	}
}
