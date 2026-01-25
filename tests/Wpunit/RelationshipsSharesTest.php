<?php

namespace Tests\Wpunit;

use Tests\Support\StadionTestCase;
use STADION_Access_Control;
use STADION_Visibility;
use STADION_Workspace_Members;
use STADION_User_Roles;
use STADION_REST_People;
use STADION_REST_Companies;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Tests for CPT relationships, sharing endpoints, and bulk update operations.
 *
 * Covers:
 * - Person-company relationship via work_history
 * - Person-dates relationship via related_people
 * - Sharing endpoints for people and companies
 * - Bulk update operations with ownership checks
 */
class RelationshipsSharesTest extends StadionTestCase {

	/**
	 * STADION_Access_Control instance for access checks.
	 *
	 * @var STADION_Access_Control
	 */
	private STADION_Access_Control $access_control;

	/**
	 * REST People handler.
	 *
	 * @var STADION_REST_People
	 */
	private STADION_REST_People $rest_people;

	/**
	 * REST Companies handler.
	 *
	 * @var STADION_REST_Companies
	 */
	private STADION_REST_Companies $rest_companies;

	protected function set_up(): void {
		parent::set_up();
		$this->access_control = new STADION_Access_Control();

		// Manually initialize REST API classes for testing
		$this->rest_people    = new STADION_REST_People();
		$this->rest_companies = new STADION_REST_Companies();

		// Initialize the REST server and register routes
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Helper: Create an approved Stadion user.
	 *
	 * @param array $args Optional user arguments.
	 * @return int User ID.
	 */
	protected function createApprovedUser( array $args = [] ): int {
		$user_id = $this->createStadionUser( $args );
		update_user_meta( $user_id, STADION_User_Roles::APPROVAL_META_KEY, '1' );
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
		STADION_Workspace_Members::add( $workspace_id, $owner_id, STADION_Workspace_Members::ROLE_ADMIN );

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
	 * Test person-company relationship endpoint.
	 * GET /stadion/v1/companies/{id}/people should return people working at the company.
	 */
	public function test_company_people_endpoint_returns_employees(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_rel1' ] );
		wp_set_current_user( $alice_id );

		// Create company "Acme Corp"
		$company_id = $this->createOrganization(
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

		// Set work_history to link person to company
		$work_history = [
			[
				'company'    => $company_id,
				'job_title'  => 'Engineer',
				'start_date' => '2020-01-01',
				'end_date'   => '',
				'is_current' => true,
			],
		];
		update_field( 'work_history', $work_history, $person_id );

		// GET /stadion/v1/companies/{id}/people
		$response = $this->restRequest( 'GET', '/stadion/v1/companies/' . $company_id . '/people' );

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
	 * Test person-company relationship with former employees.
	 */
	public function test_company_people_endpoint_distinguishes_current_former(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_rel2' ] );
		wp_set_current_user( $alice_id );

		// Create company
		$company_id = $this->createOrganization(
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
		update_field(
			'work_history',
			[
				[
					'company'    => $company_id,
					'job_title'  => 'Developer',
					'start_date' => '2023-01-01',
					'end_date'   => '',
					'is_current' => true,
				],
			],
			$current_person_id
		);

		// Create former employee (has end_date in the past)
		$former_person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Former Employee',
			]
		);
		update_field(
			'work_history',
			[
				[
					'company'    => $company_id,
					'job_title'  => 'Manager',
					'start_date' => '2020-01-01',
					'end_date'   => '2022-12-31',
					'is_current' => false,
				],
			],
			$former_person_id
		);

		$response = $this->restRequest( 'GET', '/stadion/v1/companies/' . $company_id . '/people' );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['current'], 'Should have 1 current employee' );
		$this->assertCount( 1, $data['former'], 'Should have 1 former employee' );
		$this->assertEquals( $current_person_id, $data['current'][0]['id'] );
		$this->assertEquals( $former_person_id, $data['former'][0]['id'] );
	}

	/**
	 * Test person-dates relationship endpoint.
	 * GET /stadion/v1/people/{id}/dates should return dates linked to the person.
	 */
	public function test_person_dates_endpoint_returns_linked_dates(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_rel3' ] );
		wp_set_current_user( $alice_id );

		// Create person
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Jane Smith',
			]
		);

		// Create important_date linked to person
		$date_id = $this->createImportantDate(
			$person_id,
			[
				'post_author' => $alice_id,
				'post_title'  => 'Birthday',
			],
			[
				'date_value'   => '1990-05-15',
				'is_recurring' => true,
			]
		);

		// The createImportantDate links via 'person' field, but REST expects 'related_people'
		// Let's also set the related_people field explicitly
		update_field( 'related_people', [ $person_id ], $date_id );

		// GET /stadion/v1/people/{person_id}/dates
		$response = $this->restRequest( 'GET', '/stadion/v1/people/' . $person_id . '/dates' );

		$this->assertEquals( 200, $response->get_status(), 'Response should be 200 OK' );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'Response should be an array' );
		$this->assertCount( 1, $data, 'Should have 1 date' );
		$this->assertEquals( $date_id, $data[0]['id'], 'Date should match' );
	}

	/**
	 * Test person computed fields (is_deceased, birth_year).
	 */
	public function test_person_computed_fields_is_deceased(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_rel4' ] );
		wp_set_current_user( $alice_id );

		// Create person
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Deceased Person',
			]
		);

		// Create a death date linked to this person
		$death_date_id = self::factory()->post->create(
			[
				'post_type'   => 'important_date',
				'post_status' => 'publish',
				'post_author' => $alice_id,
				'post_title'  => 'Death',
			]
		);
		update_field( 'related_people', [ $person_id ], $death_date_id );
		update_field( 'date_value', '2020-01-15', $death_date_id );

		// Set date_type taxonomy to 'died'
		wp_set_object_terms( $death_date_id, 'died', 'date_type' );

		// GET person via REST API
		$response = $this->restRequest( 'GET', '/wp/v2/people/' . $person_id );

		$this->assertEquals( 200, $response->get_status(), 'Response should be 200 OK' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'is_deceased', $data, 'Response should have is_deceased field' );
		$this->assertTrue( $data['is_deceased'], 'Person with death date should be marked as deceased' );
	}

	/**
	 * Test person computed field birth_year.
	 */
	public function test_person_computed_fields_birth_year(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_rel5' ] );
		wp_set_current_user( $alice_id );

		// Create person
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Person with Birthday',
			]
		);

		// Create a birthday date linked to this person
		$birthday_id = self::factory()->post->create(
			[
				'post_type'   => 'important_date',
				'post_status' => 'publish',
				'post_author' => $alice_id,
				'post_title'  => 'Birthday',
			]
		);
		update_field( 'related_people', [ $person_id ], $birthday_id );
		update_field( 'date_value', '1985-03-20', $birthday_id );
		update_field( 'year_unknown', false, $birthday_id );

		// Set date_type taxonomy to 'birthday'
		wp_set_object_terms( $birthday_id, 'birthday', 'date_type' );

		// GET person via REST API
		$response = $this->restRequest( 'GET', '/wp/v2/people/' . $person_id );

		$this->assertEquals( 200, $response->get_status(), 'Response should be 200 OK' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'birth_year', $data, 'Response should have birth_year field' );
		$this->assertEquals( 1985, $data['birth_year'], 'Birth year should be 1985' );
	}

	/**
	 * Test that birth_year is null when year_unknown is true.
	 */
	public function test_person_birth_year_null_when_year_unknown(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_rel6' ] );
		wp_set_current_user( $alice_id );

		// Create person
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Person Unknown Year',
			]
		);

		// Create birthday with year_unknown = true
		$birthday_id = self::factory()->post->create(
			[
				'post_type'   => 'important_date',
				'post_status' => 'publish',
				'post_author' => $alice_id,
			]
		);
		update_field( 'related_people', [ $person_id ], $birthday_id );
		update_field( 'date_value', '1990-06-15', $birthday_id );
		update_field( 'year_unknown', true, $birthday_id );
		wp_set_object_terms( $birthday_id, 'birthday', 'date_type' );

		$response = $this->restRequest( 'GET', '/wp/v2/people/' . $person_id );
		$data     = $response->get_data();

		$this->assertNull( $data['birth_year'], 'Birth year should be null when year_unknown is true' );
	}

	// =========================================================================
	// Task 2: Test sharing and bulk update endpoints
	// =========================================================================

	/**
	 * Test sharing endpoint for people - add share.
	 * POST /stadion/v1/people/{id}/shares
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
			'/stadion/v1/people/' . $person_id . '/shares',
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
	 * Test sharing endpoint for people - get shares.
	 * GET /stadion/v1/people/{id}/shares
	 */
	public function test_people_share_get(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_share2' ] );
		$bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_share2' ] );
		wp_set_current_user( $alice_id );

		// Create person and share with Bob
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Get Shares Person',
			]
		);

		STADION_Visibility::add_share( $person_id, $bob_id, 'view' );

		// GET shares
		$response = $this->restRequest( 'GET', '/stadion/v1/people/' . $person_id . '/shares' );

		$this->assertEquals( 200, $response->get_status(), 'Get shares should return 200' );

		$data = $response->get_data();
		$this->assertCount( 1, $data, 'Should have 1 share' );
		$this->assertEquals( $bob_id, $data[0]['user_id'], 'Share should be with Bob' );
		$this->assertEquals( 'view', $data[0]['permission'], 'Permission should be view' );
	}

	/**
	 * Test sharing endpoint for people - remove share.
	 * DELETE /stadion/v1/people/{id}/shares/{user_id}
	 */
	public function test_people_share_remove(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_share3' ] );
		$bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_share3' ] );
		wp_set_current_user( $alice_id );

		// Create person and share with Bob
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Remove Share Person',
			]
		);

		STADION_Visibility::add_share( $person_id, $bob_id, 'view' );

		// Verify share exists
		$this->assertTrue( STADION_Visibility::user_has_share( $person_id, $bob_id ), 'Bob should have share before removal' );

		// DELETE share
		$response = $this->restRequest( 'DELETE', '/stadion/v1/people/' . $person_id . '/shares/' . $bob_id );

		$this->assertEquals( 200, $response->get_status(), 'Remove share should return 200' );

		// Verify share removed
		$this->assertFalse( STADION_Visibility::user_has_share( $person_id, $bob_id ), 'Bob should not have share after removal' );
	}

	/**
	 * Test share permission levels.
	 */
	public function test_share_permission_update(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_share4' ] );
		$bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_share4' ] );
		wp_set_current_user( $alice_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Permission Update Person',
			]
		);

		// Add share with view permission
		$this->restRequest(
			'POST',
			'/stadion/v1/people/' . $person_id . '/shares',
			[
				'user_id'    => $bob_id,
				'permission' => 'view',
			]
		);

		$this->assertEquals( 'view', STADION_Visibility::get_share_permission( $person_id, $bob_id ), 'Initial permission should be view' );

		// Update to edit permission
		$this->restRequest(
			'POST',
			'/stadion/v1/people/' . $person_id . '/shares',
			[
				'user_id'    => $bob_id,
				'permission' => 'edit',
			]
		);

		$this->assertEquals( 'edit', STADION_Visibility::get_share_permission( $person_id, $bob_id ), 'Permission should be updated to edit' );
	}

	/**
	 * Test sharing endpoint for companies.
	 * POST/GET/DELETE /stadion/v1/companies/{id}/shares
	 */
	public function test_companies_share_lifecycle(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_share5' ] );
		$bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_share5' ] );
		wp_set_current_user( $alice_id );

		// Create company as Alice
		$company_id = $this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Shared Company',
			]
		);

		// POST share
		$add_response = $this->restRequest(
			'POST',
			'/stadion/v1/companies/' . $company_id . '/shares',
			[
				'user_id'    => $bob_id,
				'permission' => 'view',
			]
		);
		$this->assertEquals( 200, $add_response->get_status(), 'Add share should return 200' );

		// GET shares
		$get_response = $this->restRequest( 'GET', '/stadion/v1/companies/' . $company_id . '/shares' );
		$shares       = $get_response->get_data();
		$this->assertCount( 1, $shares, 'Should have 1 share' );
		$this->assertEquals( $bob_id, $shares[0]['user_id'], 'Share should be with Bob' );

		// DELETE share
		$del_response = $this->restRequest( 'DELETE', '/stadion/v1/companies/' . $company_id . '/shares/' . $bob_id );
		$this->assertEquals( 200, $del_response->get_status(), 'Delete share should return 200' );

		// Verify share removed
		$get_response2 = $this->restRequest( 'GET', '/stadion/v1/companies/' . $company_id . '/shares' );
		$this->assertCount( 0, $get_response2->get_data(), 'Should have no shares after removal' );
	}

	/**
	 * Test bulk update for people - visibility change.
	 * POST /stadion/v1/people/bulk-update
	 */
	public function test_people_bulk_update_visibility(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_bulk1' ] );
		wp_set_current_user( $alice_id );

		// Create a workspace first (needed for workspace visibility)
		$workspace_id = $this->createWorkspace( $alice_id, [ 'post_title' => 'Bulk Workspace' ] );

		// Create 3 persons
		$person1_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Bulk Person 1',
			]
		);
		$person2_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Bulk Person 2',
			]
		);
		$person3_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Bulk Person 3',
			]
		);

		// All should start as private (default)
		$this->assertEquals( 'private', STADION_Visibility::get_visibility( $person1_id ) );
		$this->assertEquals( 'private', STADION_Visibility::get_visibility( $person2_id ) );
		$this->assertEquals( 'private', STADION_Visibility::get_visibility( $person3_id ) );

		// Bulk update visibility to workspace
		$response = $this->restRequest(
			'POST',
			'/stadion/v1/people/bulk-update',
			[
				'ids'     => [ $person1_id, $person2_id, $person3_id ],
				'updates' => [
					'visibility' => 'workspace',
				],
			]
		);

		$this->assertEquals( 200, $response->get_status(), 'Bulk update should return 200' );

		$data = $response->get_data();
		$this->assertTrue( $data['success'], 'Bulk update should succeed' );
		$this->assertCount( 3, $data['updated'], 'All 3 should be updated' );

		// Verify visibility changed
		$this->assertEquals( 'workspace', STADION_Visibility::get_visibility( $person1_id ) );
		$this->assertEquals( 'workspace', STADION_Visibility::get_visibility( $person2_id ) );
		$this->assertEquals( 'workspace', STADION_Visibility::get_visibility( $person3_id ) );
	}

	/**
	 * Test bulk update for people - workspace assignment.
	 */
	public function test_people_bulk_update_workspace_assignment(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_bulk2' ] );
		wp_set_current_user( $alice_id );

		// Create workspace
		$workspace_id = $this->createWorkspace( $alice_id, [ 'post_title' => 'Assignment Workspace' ] );

		// Create 2 persons
		$person1_id = $this->createPerson( [ 'post_author' => $alice_id ] );
		$person2_id = $this->createPerson( [ 'post_author' => $alice_id ] );

		// Bulk update workspace assignment
		$response = $this->restRequest(
			'POST',
			'/stadion/v1/people/bulk-update',
			[
				'ids'     => [ $person1_id, $person2_id ],
				'updates' => [
					'assigned_workspaces' => [ $workspace_id ],
				],
			]
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		// Verify workspace assignment
		$term_slug     = 'workspace-' . $workspace_id;
		$person1_terms = wp_get_object_terms( $person1_id, 'workspace_access', [ 'fields' => 'slugs' ] );
		$person2_terms = wp_get_object_terms( $person2_id, 'workspace_access', [ 'fields' => 'slugs' ] );

		$this->assertContains( $term_slug, $person1_terms, 'Person 1 should be assigned to workspace' );
		$this->assertContains( $term_slug, $person2_terms, 'Person 2 should be assigned to workspace' );
	}

	/**
	 * Test bulk update for people - add labels.
	 */
	public function test_people_bulk_update_add_labels(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_bulk3' ] );
		wp_set_current_user( $alice_id );

		// Create a label term
		$label_term = wp_insert_term( 'VIP', 'person_label' );
		$label_id   = $label_term['term_id'];

		// Create 2 persons
		$person1_id = $this->createPerson( [ 'post_author' => $alice_id ] );
		$person2_id = $this->createPerson( [ 'post_author' => $alice_id ] );

		// Bulk add label
		$response = $this->restRequest(
			'POST',
			'/stadion/v1/people/bulk-update',
			[
				'ids'     => [ $person1_id, $person2_id ],
				'updates' => [
					'labels_add' => [ $label_id ],
				],
			]
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		// Verify labels added
		$person1_labels = wp_get_object_terms( $person1_id, 'person_label', [ 'fields' => 'ids' ] );
		$person2_labels = wp_get_object_terms( $person2_id, 'person_label', [ 'fields' => 'ids' ] );

		$this->assertContains( $label_id, $person1_labels );
		$this->assertContains( $label_id, $person2_labels );
	}

	/**
	 * Test bulk update for people - remove labels.
	 */
	public function test_people_bulk_update_remove_labels(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_bulk4' ] );
		wp_set_current_user( $alice_id );

		// Create a label term
		$label_term = wp_insert_term( 'ToRemove', 'person_label' );
		$label_id   = $label_term['term_id'];

		// Create 2 persons with the label
		$person1_id = $this->createPerson( [ 'post_author' => $alice_id ] );
		$person2_id = $this->createPerson( [ 'post_author' => $alice_id ] );

		wp_set_object_terms( $person1_id, [ $label_id ], 'person_label' );
		wp_set_object_terms( $person2_id, [ $label_id ], 'person_label' );

		// Bulk remove label
		$response = $this->restRequest(
			'POST',
			'/stadion/v1/people/bulk-update',
			[
				'ids'     => [ $person1_id, $person2_id ],
				'updates' => [
					'labels_remove' => [ $label_id ],
				],
			]
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		// Verify labels removed
		$person1_labels = wp_get_object_terms( $person1_id, 'person_label', [ 'fields' => 'ids' ] );
		$person2_labels = wp_get_object_terms( $person2_id, 'person_label', [ 'fields' => 'ids' ] );

		$this->assertNotContains( $label_id, $person1_labels );
		$this->assertNotContains( $label_id, $person2_labels );
	}

	/**
	 * Test bulk update authorization - user cannot update others' posts.
	 */
	public function test_people_bulk_update_authorization_denied(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_bulk5' ] );
		$bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_bulk5' ] );

		// Alice creates persons
		wp_set_current_user( $alice_id );
		$person1_id = $this->createPerson( [ 'post_author' => $alice_id ] );
		$person2_id = $this->createPerson( [ 'post_author' => $alice_id ] );

		// Bob tries to bulk update Alice's persons
		wp_set_current_user( $bob_id );

		$response = $this->restRequest(
			'POST',
			'/stadion/v1/people/bulk-update',
			[
				'ids'     => [ $person1_id, $person2_id ],
				'updates' => [
					'visibility' => 'shared',
				],
			]
		);

		// Should be 403 Forbidden
		$this->assertEquals( 403, $response->get_status(), 'Bob should be denied access to Alice\'s persons' );
	}

	/**
	 * Test bulk update for companies - visibility change.
	 */
	public function test_companies_bulk_update_visibility(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_bulk6' ] );
		wp_set_current_user( $alice_id );

		// Create 2 companies
		$company1_id = $this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Company 1',
			]
		);
		$company2_id = $this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Company 2',
			]
		);

		// Bulk update visibility
		$response = $this->restRequest(
			'POST',
			'/stadion/v1/companies/bulk-update',
			[
				'ids'     => [ $company1_id, $company2_id ],
				'updates' => [
					'visibility' => 'shared',
				],
			]
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		// Verify visibility changed
		$this->assertEquals( 'shared', STADION_Visibility::get_visibility( $company1_id ) );
		$this->assertEquals( 'shared', STADION_Visibility::get_visibility( $company2_id ) );
	}

	/**
	 * Test bulk update for companies - add labels.
	 */
	public function test_companies_bulk_update_add_labels(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_bulk7' ] );
		wp_set_current_user( $alice_id );

		// Create a company label term
		$label_term = wp_insert_term( 'Partner', 'company_label' );
		$label_id   = $label_term['term_id'];

		// Create companies
		$company1_id = $this->createOrganization( [ 'post_author' => $alice_id ] );
		$company2_id = $this->createOrganization( [ 'post_author' => $alice_id ] );

		// Bulk add label
		$response = $this->restRequest(
			'POST',
			'/stadion/v1/companies/bulk-update',
			[
				'ids'     => [ $company1_id, $company2_id ],
				'updates' => [
					'labels_add' => [ $label_id ],
				],
			]
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		// Verify labels added
		$company1_labels = wp_get_object_terms( $company1_id, 'company_label', [ 'fields' => 'ids' ] );
		$company2_labels = wp_get_object_terms( $company2_id, 'company_label', [ 'fields' => 'ids' ] );

		$this->assertContains( $label_id, $company1_labels );
		$this->assertContains( $label_id, $company2_labels );
	}

	/**
	 * Test bulk update for companies - authorization denied for other users' companies.
	 */
	public function test_companies_bulk_update_authorization_denied(): void {
		$alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_bulk8' ] );
		$bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_bulk8' ] );

		// Alice creates companies
		wp_set_current_user( $alice_id );
		$company1_id = $this->createOrganization( [ 'post_author' => $alice_id ] );
		$company2_id = $this->createOrganization( [ 'post_author' => $alice_id ] );

		// Bob tries to bulk update Alice's companies
		wp_set_current_user( $bob_id );

		$response = $this->restRequest(
			'POST',
			'/stadion/v1/companies/bulk-update',
			[
				'ids'     => [ $company1_id, $company2_id ],
				'updates' => [
					'visibility' => 'workspace',
				],
			]
		);

		$this->assertEquals( 403, $response->get_status(), 'Bob should be denied access to Alice\'s companies' );
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
			'/stadion/v1/people/' . $person_id . '/shares',
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
			'/stadion/v1/people/' . $person_id . '/shares',
			[
				'user_id'    => $alice_id,
				'permission' => 'view',
			]
		);

		$this->assertEquals( 400, $response->get_status(), 'Should not be able to share with yourself' );
	}
}
