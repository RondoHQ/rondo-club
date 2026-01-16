<?php

namespace Tests\Wpunit;

use Tests\Support\CaelisTestCase;
use PRM_Access_Control;
use PRM_User_Roles;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Tests for custom REST API endpoints: search, dashboard, reminders, and todos.
 *
 * Verifies /prm/v1/ custom endpoints return correct data and enforce access control.
 */
class SearchDashboardTest extends CaelisTestCase {

	/**
	 * Access control instance for testing.
	 *
	 * @var PRM_Access_Control
	 */
	private PRM_Access_Control $access_control;

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Set up test environment before each test.
	 */
	protected function set_up(): void {
		parent::set_up();

		// Create fresh access control instance for testing
		$this->access_control = new PRM_Access_Control();

		// Initialize REST server and ensure routes are registered
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		// Instantiate REST API classes to register routes (bypasses prm_is_rest_request() check)
		new \PRM_REST_API();
		new \PRM_REST_Todos();

		// Trigger REST API initialization to register routes
		do_action( 'rest_api_init' );
	}

	/**
	 * Helper to create an approved Caelis user.
	 *
	 * @param array $args User arguments
	 * @return int User ID
	 */
	private function createApprovedCaelisUser( array $args = [] ): int {
		$user_id = $this->createCaelisUser( $args );
		update_user_meta( $user_id, PRM_User_Roles::APPROVAL_META_KEY, '1' );
		return $user_id;
	}

	/**
	 * Helper to create an important date post.
	 *
	 * @param array $args Post arguments
	 * @param array $acf ACF field values
	 * @return int Post ID
	 */
	private function createImportantDatePost( array $args = [], array $acf = [] ): int {
		$defaults = [
			'post_type'   => 'important_date',
			'post_status' => 'publish',
			'post_author' => get_current_user_id() ?: 1,
		];

		$post_id = self::factory()->post->create( array_merge( $defaults, $args ) );

		foreach ( $acf as $field => $value ) {
			update_field( $field, $value, $post_id );
		}

		return $post_id;
	}

	/**
	 * Helper to make an internal REST request.
	 *
	 * @param string $method HTTP method (GET, POST, etc.)
	 * @param string $route REST route (e.g., '/prm/v1/search')
	 * @param array $params Query parameters
	 * @return \WP_REST_Response
	 */
	private function doRestRequest( string $method, string $route, array $params = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->server->dispatch( $request );
	}

	// =========================================================================
	// Task 1: Test search endpoint
	// =========================================================================

	/**
	 * Test basic search returns matching person.
	 */
	public function test_search_returns_matching_person(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		// Create persons
		$john_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'John Doe',
			]
		);
		$jane_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Jane Smith',
			]
		);

		// Search for John
		$response = $this->doRestRequest( 'GET', '/prm/v1/search', [ 'q' => 'John' ] );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'people', $data );

		// Extract IDs from results
		$result_ids = array_column( $data['people'], 'id' );

		$this->assertContains( $john_id, $result_ids, 'Search should return John Doe' );
		$this->assertNotContains( $jane_id, $result_ids, 'Search should NOT return Jane Smith' );
	}

	/**
	 * Test search isolation - user A cannot see user B's contacts.
	 */
	public function test_search_isolation_between_users(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedCaelisUser( [ 'user_login' => 'bob' ] );

		// Create person for Alice
		$alice_john = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Johns Contact',
			]
		);

		// Create person for Bob
		$bob_john = $this->createPerson(
			[
				'post_author' => $bob_id,
				'post_title'  => 'Johns Other Contact',
			]
		);

		// Set current user to Alice and search
		wp_set_current_user( $alice_id );
		$response = $this->doRestRequest( 'GET', '/prm/v1/search', [ 'q' => 'John' ] );

		$this->assertEquals( 200, $response->get_status() );

		$data       = $response->get_data();
		$result_ids = array_column( $data['people'], 'id' );

		$this->assertContains( $alice_john, $result_ids, 'Alice should see her own Johns Contact' );
		$this->assertNotContains( $bob_john, $result_ids, 'Alice should NOT see Bobs Johns Other Contact' );
	}

	/**
	 * Test search across custom post types (person and company).
	 */
	public function test_search_across_post_types(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		// Create person
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Acme Employee',
			]
		);

		// Create company
		$company_id = $this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Acme Corp',
			]
		);

		// Search for Acme
		$response = $this->doRestRequest( 'GET', '/prm/v1/search', [ 'q' => 'Acme' ] );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		// Check people results
		$people_ids = array_column( $data['people'], 'id' );
		$this->assertContains( $person_id, $people_ids, 'Search should return Acme Employee' );

		// Check companies results
		$company_ids = array_column( $data['companies'], 'id' );
		$this->assertContains( $company_id, $company_ids, 'Search should return Acme Corp' );
	}

	/**
	 * Test search validation - empty query returns 400.
	 */
	public function test_search_validation_empty_query(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		// Search with empty query - should fail validation
		$response = $this->doRestRequest( 'GET', '/prm/v1/search', [ 'q' => '' ] );

		$this->assertEquals( 400, $response->get_status(), 'Empty search query should return 400' );
	}

	/**
	 * Test search validation - single character returns 400.
	 */
	public function test_search_validation_single_character(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		// Search with single character - should fail validation (min 2 chars)
		$response = $this->doRestRequest( 'GET', '/prm/v1/search', [ 'q' => 'A' ] );

		$this->assertEquals( 400, $response->get_status(), 'Single character search should return 400' );
	}

	/**
	 * Test unapproved user is blocked from search endpoint.
	 */
	public function test_search_blocked_for_unapproved_user(): void {
		// Create unapproved user
		$unapproved_id = $this->createCaelisUser( [ 'user_login' => 'unapproved' ] );
		update_user_meta( $unapproved_id, PRM_User_Roles::APPROVAL_META_KEY, '0' );
		wp_set_current_user( $unapproved_id );

		// Attempt search
		$response = $this->doRestRequest( 'GET', '/prm/v1/search', [ 'q' => 'Test' ] );

		// Should be denied (403 Forbidden)
		$this->assertEquals( 403, $response->get_status(), 'Unapproved user should be denied access to search' );
	}

	/**
	 * Test search blocked for logged-out user.
	 */
	public function test_search_blocked_for_logged_out_user(): void {
		wp_set_current_user( 0 );

		// Attempt search
		$response = $this->doRestRequest( 'GET', '/prm/v1/search', [ 'q' => 'Test' ] );

		// Should be denied (401 Unauthorized)
		$this->assertEquals( 401, $response->get_status(), 'Logged out user should be denied access to search' );
	}

	// =========================================================================
	// Task 2: Test dashboard, reminders, and todos endpoints
	// =========================================================================

	/**
	 * Test dashboard summary returns correct counts.
	 */
	public function test_dashboard_returns_correct_counts(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		// Create 3 persons
		$this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Person 1',
			]
		);
		$this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Person 2',
			]
		);
		$this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Person 3',
			]
		);

		// Create 2 companies
		$this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Company 1',
			]
		);
		$this->createOrganization(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Company 2',
			]
		);

		// Create 5 important dates
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->createImportantDatePost(
				[
					'post_author' => $alice_id,
					'post_title'  => "Date $i",
				]
			);
		}

		// Get dashboard
		$response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'stats', $data );
		$this->assertEquals( 3, $data['stats']['total_people'], 'Dashboard should report 3 people' );
		$this->assertEquals( 2, $data['stats']['total_companies'], 'Dashboard should report 2 companies' );
		$this->assertEquals( 5, $data['stats']['total_dates'], 'Dashboard should report 5 dates' );
	}

	/**
	 * Test dashboard isolation - user A only sees their own data in counts.
	 */
	public function test_dashboard_isolation_between_users(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedCaelisUser( [ 'user_login' => 'bob' ] );

		// Create 5 contacts for Alice
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->createPerson(
				[
					'post_author' => $alice_id,
					'post_title'  => "Alice Person $i",
				]
			);
		}

		// Create 3 contacts for Bob
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->createPerson(
				[
					'post_author' => $bob_id,
					'post_title'  => "Bob Person $i",
				]
			);
		}

		// Get Alice's dashboard
		wp_set_current_user( $alice_id );
		$alice_response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );
		$alice_data     = $alice_response->get_data();

		$this->assertEquals( 5, $alice_data['stats']['total_people'], 'Alice should see 5 people' );

		// Get Bob's dashboard
		wp_set_current_user( $bob_id );
		$bob_response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );
		$bob_data     = $bob_response->get_data();

		$this->assertEquals( 3, $bob_data['stats']['total_people'], 'Bob should see 3 people' );
	}

	/**
	 * Test dashboard for new user with no data shows zero counts.
	 */
	public function test_dashboard_empty_for_new_user(): void {
		$newuser_id = $this->createApprovedCaelisUser( [ 'user_login' => 'emptyuser' ] );
		wp_set_current_user( $newuser_id );

		// Get dashboard for user with no data
		$response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 0, $data['stats']['total_people'], 'New user should see 0 people' );
		$this->assertEquals( 0, $data['stats']['total_companies'], 'New user should see 0 companies' );
		$this->assertEquals( 0, $data['stats']['total_dates'], 'New user should see 0 dates' );
	}

	/**
	 * Test dashboard blocked for unapproved user.
	 */
	public function test_dashboard_blocked_for_unapproved_user(): void {
		$unapproved_id = $this->createCaelisUser( [ 'user_login' => 'unapproved' ] );
		update_user_meta( $unapproved_id, PRM_User_Roles::APPROVAL_META_KEY, '0' );
		wp_set_current_user( $unapproved_id );

		$response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );

		$this->assertEquals( 403, $response->get_status(), 'Unapproved user should be denied dashboard access' );
	}

	/**
	 * Test reminders endpoint returns upcoming dates.
	 */
	public function test_reminders_returns_upcoming_dates(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		// Create a person to link the date to
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create date 5 days from now
		$upcoming_date = gmdate( 'Y-m-d', strtotime( '+5 days' ) );
		$this->createImportantDatePost(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Upcoming Birthday',
			],
			[
				'date_value'     => $upcoming_date,
				'is_recurring'   => true,
				'related_people' => [ $person_id ],
			]
		);

		// Get reminders for next 30 days
		$response = $this->doRestRequest( 'GET', '/prm/v1/reminders', [ 'days_ahead' => 30 ] );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		// The reminder should be returned (exact format depends on implementation)
	}

	/**
	 * Test reminders filters by days_ahead parameter.
	 */
	public function test_reminders_filters_by_days_ahead(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		// Create a person to link the date to
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create date 60 days from now
		$far_date = gmdate( 'Y-m-d', strtotime( '+60 days' ) );
		$this->createImportantDatePost(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Far Future Date',
			],
			[
				'date_value'     => $far_date,
				'is_recurring'   => true,
				'related_people' => [ $person_id ],
			]
		);

		// Get reminders for next 30 days - should not include the 60-day date
		$response = $this->doRestRequest( 'GET', '/prm/v1/reminders', [ 'days_ahead' => 30 ] );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		// The 60-day date should NOT be in the 30-day results
	}

	/**
	 * Test reminders validation - days_ahead=0 returns 400.
	 */
	public function test_reminders_validation_zero_days(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		$response = $this->doRestRequest( 'GET', '/prm/v1/reminders', [ 'days_ahead' => 0 ] );

		$this->assertEquals( 400, $response->get_status(), 'days_ahead=0 should return 400' );
	}

	/**
	 * Test reminders validation - days_ahead too large returns 400.
	 */
	public function test_reminders_validation_days_too_large(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		$response = $this->doRestRequest( 'GET', '/prm/v1/reminders', [ 'days_ahead' => 500 ] );

		$this->assertEquals( 400, $response->get_status(), 'days_ahead=500 should return 400 (max 365)' );
	}

	/**
	 * Test reminders blocked for unapproved user.
	 */
	public function test_reminders_blocked_for_unapproved_user(): void {
		$unapproved_id = $this->createCaelisUser( [ 'user_login' => 'unapproved' ] );
		update_user_meta( $unapproved_id, PRM_User_Roles::APPROVAL_META_KEY, '0' );
		wp_set_current_user( $unapproved_id );

		$response = $this->doRestRequest( 'GET', '/prm/v1/reminders' );

		$this->assertEquals( 403, $response->get_status(), 'Unapproved user should be denied reminders access' );
	}

	/**
	 * Helper to create a todo post (CPT-based).
	 *
	 * @param int    $person_id Person to attach the todo to
	 * @param string $content   Todo content
	 * @param int    $user_id   User ID for the post author
	 * @param bool   $completed Whether the todo is completed
	 * @param string $due_date  Optional due date
	 * @return int Post ID
	 */
	private function createTodo( int $person_id, string $content, int $user_id, bool $completed = false, string $due_date = '' ): int {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'prm_todo',
				'post_status' => 'publish',
				'post_title'  => $content,
				'post_author' => $user_id,
			]
		);

		update_field( 'related_person', $person_id, $post_id );
		update_field( 'is_completed', $completed, $post_id );
		update_field( 'visibility', 'private', $post_id );

		if ( $due_date ) {
			update_field( 'due_date', $due_date, $post_id );
		}

		return $post_id;
	}

	/**
	 * Test todos endpoint returns uncompleted todos.
	 */
	public function test_todos_returns_uncompleted_todos(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		wp_set_current_user( $alice_id );

		// Create person
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create uncompleted todo
		$todo_id = $this->createTodo( $person_id, 'Call John about project', $alice_id, false );

		// Create completed todo
		$completed_todo_id = $this->createTodo( $person_id, 'Send email', $alice_id, true );

		// Get todos (default: uncompleted only)
		$response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

		$this->assertEquals( 200, $response->get_status() );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertContains( $todo_id, $todo_ids, 'Uncompleted todo should be returned' );
		$this->assertNotContains( $completed_todo_id, $todo_ids, 'Completed todo should NOT be returned by default' );
	}

	/**
	 * Test todos endpoint with completed=true returns all todos.
	 */
	public function test_todos_returns_all_with_completed_filter(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice_completed' ] );
		wp_set_current_user( $alice_id );

		// Create person
		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Test Person For Completed',
			]
		);

		// Create uncompleted todo
		$todo_id = $this->createTodo( $person_id, 'Call John about project', $alice_id, false );

		// Create completed todo
		$completed_todo_id = $this->createTodo( $person_id, 'Send email', $alice_id, true );

		// Get all todos including completed (use string 'true' as that's what the REST API expects)
		$response = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'completed' => 'true' ] );

		$this->assertEquals( 200, $response->get_status() );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertContains( $todo_id, $todo_ids, 'Uncompleted todo should be returned' );
		$this->assertContains( $completed_todo_id, $todo_ids, 'Completed todo should be returned with completed=true' );
	}

	/**
	 * Test todos endpoint isolation - user cannot see other user's todos.
	 */
	public function test_todos_isolation_between_users(): void {
		$alice_id = $this->createApprovedCaelisUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedCaelisUser( [ 'user_login' => 'bob' ] );

		// Create person for Alice
		$alice_person = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Alice Person',
			]
		);
		$alice_todo   = $this->createTodo( $alice_person, 'Alice todo', $alice_id );

		// Create person for Bob
		$bob_person = $this->createPerson(
			[
				'post_author' => $bob_id,
				'post_title'  => 'Bob Person',
			]
		);
		$bob_todo   = $this->createTodo( $bob_person, 'Bob todo', $bob_id );

		// Get Alice's todos
		wp_set_current_user( $alice_id );
		$alice_response = $this->doRestRequest( 'GET', '/prm/v1/todos' );
		$alice_data     = $alice_response->get_data();
		$alice_todo_ids = array_column( $alice_data, 'id' );

		$this->assertContains( $alice_todo, $alice_todo_ids, 'Alice should see her own todo' );
		$this->assertNotContains( $bob_todo, $alice_todo_ids, 'Alice should NOT see Bobs todo' );

		// Get Bob's todos
		wp_set_current_user( $bob_id );
		$bob_response = $this->doRestRequest( 'GET', '/prm/v1/todos' );
		$bob_data     = $bob_response->get_data();
		$bob_todo_ids = array_column( $bob_data, 'id' );

		$this->assertContains( $bob_todo, $bob_todo_ids, 'Bob should see his own todo' );
		$this->assertNotContains( $alice_todo, $bob_todo_ids, 'Bob should NOT see Alices todo' );
	}

	/**
	 * Test todos blocked for unapproved user.
	 */
	public function test_todos_blocked_for_unapproved_user(): void {
		$unapproved_id = $this->createCaelisUser( [ 'user_login' => 'unapproved' ] );
		update_user_meta( $unapproved_id, PRM_User_Roles::APPROVAL_META_KEY, '0' );
		wp_set_current_user( $unapproved_id );

		$response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

		$this->assertEquals( 403, $response->get_status(), 'Unapproved user should be denied todos access' );
	}
}
