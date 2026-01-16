<?php

namespace Tests\Wpunit;

use Tests\Support\CaelisTestCase;
use PRM_Access_Control;
use PRM_User_Roles;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Tests for todo custom post type functionality.
 *
 * Covers:
 * - CPT registration
 * - Access control (user isolation)
 * - REST API CRUD operations
 * - Dashboard integration (open_todos_count)
 */
class TodoCptTest extends CaelisTestCase {

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

		// Initialize REST server
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		// Instantiate REST API classes to register routes
		new \PRM_REST_API();
		new \PRM_REST_Todos();

		// Trigger REST API initialization
		do_action( 'rest_api_init' );
	}

	/**
	 * Helper to create an approved Caelis user.
	 *
	 * @param string $prefix User login prefix for uniqueness
	 * @return int User ID
	 */
	private function createApprovedCaelisUser( string $prefix = 'user' ): int {
		$unique_id = uniqid( $prefix . '_' );
		$user_id   = $this->createCaelisUser( [ 'user_login' => $unique_id ] );
		update_user_meta( $user_id, PRM_User_Roles::APPROVAL_META_KEY, '1' );
		return $user_id;
	}

	/**
	 * Helper to create a todo post.
	 *
	 * @param int   $person_id Person to link the todo to
	 * @param int   $user_id   User ID for the post author
	 * @param array $data      Additional todo data (content, status, due_date)
	 * @return int Post ID
	 */
	private function createTodo( int $person_id, int $user_id, array $data = [] ): int {
		$defaults = [
			'content'    => 'Test todo',
			'status'     => 'open', // 'open', 'awaiting', or 'completed'
			'due_date'   => '',
			'visibility' => 'private',
		];
		$data     = array_merge( $defaults, $data );

		// Map status to post_status
		$post_status = 'prm_' . $data['status'];

		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'prm_todo',
				'post_status' => $post_status,
				'post_title'  => $data['content'],
				'post_author' => $user_id,
			]
		);

		update_field( 'related_person', $person_id, $post_id );
		update_field( 'visibility', $data['visibility'], $post_id );

		if ( ! empty( $data['due_date'] ) ) {
			update_field( 'due_date', $data['due_date'], $post_id );
		}

		// Set awaiting_since timestamp for awaiting status
		if ( $data['status'] === 'awaiting' ) {
			update_field( 'awaiting_since', gmdate( 'Y-m-d H:i:s' ), $post_id );
		}

		return $post_id;
	}

	/**
	 * Helper to make an internal REST request.
	 *
	 * @param string $method HTTP method
	 * @param string $route  REST route
	 * @param array  $params Request parameters
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
	// CPT Registration Tests
	// =========================================================================

	/**
	 * Test that prm_todo post type is registered.
	 */
	public function test_prm_todo_post_type_registered(): void {
		$this->assertTrue(
			post_type_exists( 'prm_todo' ),
			'prm_todo post type should be registered'
		);
	}

	/**
	 * Test prm_todo post type supports REST API.
	 */
	public function test_prm_todo_has_rest_support(): void {
		$post_type_obj = get_post_type_object( 'prm_todo' );

		$this->assertTrue(
			$post_type_obj->show_in_rest,
			'prm_todo should be available in REST API'
		);
	}

	// =========================================================================
	// Access Control Tests
	// =========================================================================

	/**
	 * Test user can only see their own todos.
	 */
	public function test_user_can_only_see_own_todos(): void {
		$alice_id = $this->createApprovedCaelisUser( 'alice' );
		$bob_id   = $this->createApprovedCaelisUser( 'bob' );

		// Create person for Alice
		wp_set_current_user( $alice_id );
		$alice_person = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Alice Person',
			]
		);
		$alice_todo   = $this->createTodo( $alice_person, $alice_id, [ 'content' => 'Alice todo' ] );

		// Create person for Bob
		wp_set_current_user( $bob_id );
		$bob_person = $this->createPerson(
			[
				'post_author' => $bob_id,
				'post_title'  => 'Bob Person',
			]
		);
		$bob_todo   = $this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob todo' ] );

		// Query as Alice - should only see her own todos
		wp_set_current_user( $alice_id );
		$response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

		$this->assertEquals( 200, $response->get_status() );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertContains( $alice_todo, $todo_ids, 'Alice should see her own todo' );
		$this->assertNotContains( $bob_todo, $todo_ids, 'Alice should NOT see Bob\'s todo' );
	}

	/**
	 * Test admin bypasses access control for their own todos.
	 */
	public function test_admin_sees_own_todos(): void {
		// Create admin user
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		update_user_meta( $admin_id, PRM_User_Roles::APPROVAL_META_KEY, '1' );

		$regular_user_id = $this->createApprovedCaelisUser( 'regular' );

		// Create person and todo for admin
		wp_set_current_user( $admin_id );
		$admin_person = $this->createPerson(
			[
				'post_author' => $admin_id,
				'post_title'  => 'Admin Person',
			]
		);
		$admin_todo   = $this->createTodo( $admin_person, $admin_id, [ 'content' => 'Admin todo' ] );

		// Create person and todo for regular user
		wp_set_current_user( $regular_user_id );
		$regular_person = $this->createPerson(
			[
				'post_author' => $regular_user_id,
				'post_title'  => 'Regular Person',
			]
		);
		$regular_todo   = $this->createTodo( $regular_person, $regular_user_id, [ 'content' => 'Regular todo' ] );

		// Query as admin - in frontend context, admin sees only their own data
		wp_set_current_user( $admin_id );
		$response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

		$this->assertEquals( 200, $response->get_status() );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		// Admin sees their own todo
		$this->assertContains( $admin_todo, $todo_ids, 'Admin should see their own todo' );
	}

	// =========================================================================
	// REST API - CRUD Tests
	// =========================================================================

	/**
	 * Test GET /prm/v1/todos returns user's todos.
	 */
	public function test_get_todos_returns_user_todos(): void {
		$user_id = $this->createApprovedCaelisUser( 'todos_user' );
		wp_set_current_user( $user_id );

		// Create person and todos
		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);
		$todo1     = $this->createTodo( $person_id, $user_id, [ 'content' => 'Todo 1' ] );
		$todo2     = $this->createTodo( $person_id, $user_id, [ 'content' => 'Todo 2' ] );

		$response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

		$this->assertEquals( 200, $response->get_status() );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertContains( $todo1, $todo_ids, 'Should return first todo' );
		$this->assertContains( $todo2, $todo_ids, 'Should return second todo' );
	}

	/**
	 * Test GET /prm/v1/people/{id}/todos filters by person.
	 */
	public function test_get_person_todos_filters_by_person(): void {
		$user_id = $this->createApprovedCaelisUser( 'filter_user' );
		wp_set_current_user( $user_id );

		// Create two people
		$person1 = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Person 1',
			]
		);
		$person2 = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Person 2',
			]
		);

		// Create todos for each
		$todo1 = $this->createTodo( $person1, $user_id, [ 'content' => 'Todo for Person 1' ] );
		$todo2 = $this->createTodo( $person2, $user_id, [ 'content' => 'Todo for Person 2' ] );

		// Query todos for person 1 only
		$response = $this->doRestRequest( 'GET', '/prm/v1/people/' . $person1 . '/todos' );

		$this->assertEquals( 200, $response->get_status() );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertContains( $todo1, $todo_ids, 'Should return todo for Person 1' );
		$this->assertNotContains( $todo2, $todo_ids, 'Should NOT return todo for Person 2' );
	}

	/**
	 * Test POST /prm/v1/people/{id}/todos creates a prm_todo post.
	 */
	public function test_create_todo_creates_prm_todo_post(): void {
		$user_id = $this->createApprovedCaelisUser( 'creator_user' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		$response = $this->doRestRequest(
			'POST',
			'/prm/v1/people/' . $person_id . '/todos',
			[
				'content'  => 'New todo item',
				'due_date' => '2026-02-01',
			]
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data, 'Response should include id' );
		$this->assertEquals( 'New todo item', $data['content'], 'Content should match' );
		$this->assertEquals( $person_id, $data['person_id'], 'Person ID should match' );
		$this->assertEquals( '2026-02-01', $data['due_date'], 'Due date should match' );
		$this->assertEquals( 'open', $data['status'], 'New todo should have open status' );

		// Verify post exists in database
		$post = get_post( $data['id'] );
		$this->assertNotNull( $post, 'Todo post should exist' );
		$this->assertEquals( 'prm_todo', $post->post_type, 'Post type should be prm_todo' );
		$this->assertEquals( 'prm_open', $post->post_status, 'Post status should be prm_open' );
		$this->assertEquals( $user_id, (int) $post->post_author, 'Author should be current user' );
	}

	/**
	 * Test PUT /prm/v1/todos/{id} changes status.
	 */
	public function test_update_todo_changes_status(): void {
		$user_id = $this->createApprovedCaelisUser( 'updater_user' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);
		$todo_id   = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Test todo',
				'status'  => 'open',
			]
		);

		// Update to completed
		$response = $this->doRestRequest(
			'PUT',
			'/prm/v1/todos/' . $todo_id,
			[
				'status' => 'completed',
			]
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'completed', $data['status'], 'Todo should be marked as completed' );

		// Verify in database
		$post = get_post( $todo_id );
		$this->assertEquals( 'prm_completed', $post->post_status, 'Database should reflect completed status' );
	}

	/**
	 * Test DELETE /prm/v1/todos/{id} removes the post.
	 */
	public function test_delete_todo_removes_post(): void {
		$user_id = $this->createApprovedCaelisUser( 'deleter_user' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);
		$todo_id   = $this->createTodo( $person_id, $user_id, [ 'content' => 'To be deleted' ] );

		// Verify post exists
		$this->assertNotNull( get_post( $todo_id ), 'Todo should exist before delete' );

		// Delete
		$response = $this->doRestRequest( 'DELETE', '/prm/v1/todos/' . $todo_id );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['deleted'], 'Response should indicate deletion' );

		// Verify post is deleted (force delete, not trashed)
		$post = get_post( $todo_id );
		$this->assertNull( $post, 'Todo post should be deleted from database' );
	}

	// =========================================================================
	// Dashboard Integration Tests
	// =========================================================================

	/**
	 * Test dashboard counts open todos correctly.
	 */
	public function test_dashboard_counts_open_todos(): void {
		$user_id = $this->createApprovedCaelisUser( 'dashboard_user' );
		wp_set_current_user( $user_id );

		// Create person and 3 open todos
		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);
		$this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Todo 1',
				'status'  => 'open',
			]
		);
		$this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Todo 2',
				'status'  => 'open',
			]
		);
		$this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Todo 3',
				'status'  => 'open',
			]
		);

		// Get dashboard
		$response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'stats', $data );
		$this->assertEquals( 3, $data['stats']['open_todos_count'], 'Should count 3 open todos' );
	}

	/**
	 * Test completed todos are not counted in dashboard.
	 */
	public function test_completed_todos_not_counted(): void {
		$user_id = $this->createApprovedCaelisUser( 'completed_user' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create 2 open and 2 completed todos
		$this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Open 1',
				'status'  => 'open',
			]
		);
		$this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Open 2',
				'status'  => 'open',
			]
		);
		$this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Completed 1',
				'status'  => 'completed',
			]
		);
		$this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Completed 2',
				'status'  => 'completed',
			]
		);

		$response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 2, $data['stats']['open_todos_count'], 'Should only count open todos' );
	}

	/**
	 * Test dashboard counts only user's own todos.
	 */
	public function test_dashboard_counts_only_own_todos(): void {
		$alice_id = $this->createApprovedCaelisUser( 'alice_dash' );
		$bob_id   = $this->createApprovedCaelisUser( 'bob_dash' );

		// Create 3 todos for Alice
		wp_set_current_user( $alice_id );
		$alice_person = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_title'  => 'Alice Person',
			]
		);
		$this->createTodo( $alice_person, $alice_id, [ 'content' => 'Alice 1' ] );
		$this->createTodo( $alice_person, $alice_id, [ 'content' => 'Alice 2' ] );
		$this->createTodo( $alice_person, $alice_id, [ 'content' => 'Alice 3' ] );

		// Create 5 todos for Bob
		wp_set_current_user( $bob_id );
		$bob_person = $this->createPerson(
			[
				'post_author' => $bob_id,
				'post_title'  => 'Bob Person',
			]
		);
		$this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 1' ] );
		$this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 2' ] );
		$this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 3' ] );
		$this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 4' ] );
		$this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 5' ] );

		// Alice's dashboard should show 3 todos
		wp_set_current_user( $alice_id );
		$alice_response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );
		$alice_data     = $alice_response->get_data();
		$this->assertEquals( 3, $alice_data['stats']['open_todos_count'], 'Alice should see 3 todos' );

		// Bob's dashboard should show 5 todos
		wp_set_current_user( $bob_id );
		$bob_response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );
		$bob_data     = $bob_response->get_data();
		$this->assertEquals( 5, $bob_data['stats']['open_todos_count'], 'Bob should see 5 todos' );
	}

	// =========================================================================
	// Access Control - Unapproved User Tests
	// =========================================================================

	/**
	 * Test todos endpoint blocked for unapproved user.
	 */
	public function test_todos_blocked_for_unapproved_user(): void {
		$unapproved_id = $this->createCaelisUser( [ 'user_login' => 'unapproved_todo' ] );
		update_user_meta( $unapproved_id, PRM_User_Roles::APPROVAL_META_KEY, '0' );
		wp_set_current_user( $unapproved_id );

		$response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

		$this->assertEquals( 403, $response->get_status(), 'Unapproved user should be denied todos access' );
	}

	/**
	 * Test todos blocked for logged-out user.
	 */
	public function test_todos_blocked_for_logged_out_user(): void {
		wp_set_current_user( 0 );

		$response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

		$this->assertEquals( 401, $response->get_status(), 'Logged out user should be denied todos access' );
	}

	// =========================================================================
	// Status Filter Tests
	// =========================================================================

	/**
	 * Test todos endpoint returns open todos by default (status=open).
	 */
	public function test_todos_returns_open_by_default(): void {
		$user_id = $this->createApprovedCaelisUser( 'filter_complete' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create open and completed todos
		$open_todo      = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Open todo',
				'status'  => 'open',
			]
		);
		$completed_todo = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Completed todo',
				'status'  => 'completed',
			]
		);
		$awaiting_todo  = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Awaiting todo',
				'status'  => 'awaiting',
			]
		);

		// Default request (status=open)
		$response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertContains( $open_todo, $todo_ids, 'Open todo should be returned' );
		$this->assertNotContains( $completed_todo, $todo_ids, 'Completed todo should NOT be returned by default' );
		$this->assertNotContains( $awaiting_todo, $todo_ids, 'Awaiting todo should NOT be returned by default' );
	}

	/**
	 * Test todos endpoint includes all with status=all filter.
	 */
	public function test_todos_includes_all_with_status_all(): void {
		$user_id = $this->createApprovedCaelisUser( 'filter_all' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		$open_todo      = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Open todo',
				'status'  => 'open',
			]
		);
		$completed_todo = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Completed todo',
				'status'  => 'completed',
			]
		);
		$awaiting_todo  = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Awaiting todo',
				'status'  => 'awaiting',
			]
		);

		// Request with status=all
		$response = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'status' => 'all' ] );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertContains( $open_todo, $todo_ids, 'Open todo should be returned' );
		$this->assertContains( $completed_todo, $todo_ids, 'Completed todo should be returned with status=all' );
		$this->assertContains( $awaiting_todo, $todo_ids, 'Awaiting todo should be returned with status=all' );
	}

	/**
	 * Test todos endpoint returns only completed with status=completed.
	 */
	public function test_todos_returns_only_completed_with_status_completed(): void {
		$user_id = $this->createApprovedCaelisUser( 'filter_completed' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		$open_todo      = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Open todo',
				'status'  => 'open',
			]
		);
		$completed_todo = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Completed todo',
				'status'  => 'completed',
			]
		);
		$awaiting_todo  = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Awaiting todo',
				'status'  => 'awaiting',
			]
		);

		// Request with status=completed
		$response = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'status' => 'completed' ] );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertNotContains( $open_todo, $todo_ids, 'Open todo should NOT be returned' );
		$this->assertContains( $completed_todo, $todo_ids, 'Completed todo should be returned' );
		$this->assertNotContains( $awaiting_todo, $todo_ids, 'Awaiting todo should NOT be returned' );
	}

	// =========================================================================
	// Awaiting Status Filter Tests
	// =========================================================================

	/**
	 * Test status=awaiting filter returns only awaiting todos.
	 */
	public function test_todos_with_status_awaiting_returns_only_awaiting(): void {
		$user_id = $this->createApprovedCaelisUser( 'awaiting_filter1' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create 2 awaiting, 1 open, and 1 completed todos
		$awaiting1      = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Awaiting 1',
				'status'  => 'awaiting',
			]
		);
		$awaiting2      = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Awaiting 2',
				'status'  => 'awaiting',
			]
		);
		$open_todo      = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Open',
				'status'  => 'open',
			]
		);
		$completed_todo = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Completed',
				'status'  => 'completed',
			]
		);

		// Request with status=awaiting
		$response = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'status' => 'awaiting' ] );

		$this->assertEquals( 200, $response->get_status() );

		$data     = $response->get_data();
		$todo_ids = array_column( $data, 'id' );

		$this->assertContains( $awaiting1, $todo_ids, 'First awaiting todo should be returned' );
		$this->assertContains( $awaiting2, $todo_ids, 'Second awaiting todo should be returned' );
		$this->assertNotContains( $open_todo, $todo_ids, 'Open todo should NOT be returned' );
		$this->assertNotContains( $completed_todo, $todo_ids, 'Completed todo should NOT be returned' );
		$this->assertCount( 2, $data, 'Should return exactly 2 todos' );
	}

	/**
	 * Test status filters are mutually exclusive.
	 */
	public function test_todos_status_filters_are_mutually_exclusive(): void {
		$user_id = $this->createApprovedCaelisUser( 'awaiting_filter2' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create 3 todos: one for each status
		$open_todo      = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Open',
				'status'  => 'open',
			]
		);
		$awaiting_todo  = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Awaiting',
				'status'  => 'awaiting',
			]
		);
		$completed_todo = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Completed',
				'status'  => 'completed',
			]
		);

		// Test status=open returns only open
		$response1 = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'status' => 'open' ] );
		$data1     = $response1->get_data();
		$this->assertCount( 1, $data1, 'status=open should return 1 todo' );
		$this->assertEquals( $open_todo, $data1[0]['id'], 'Should return open todo' );

		// Test status=awaiting returns only awaiting
		$response2 = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'status' => 'awaiting' ] );
		$data2     = $response2->get_data();
		$this->assertCount( 1, $data2, 'status=awaiting should return 1 todo' );
		$this->assertEquals( $awaiting_todo, $data2[0]['id'], 'Should return awaiting todo' );

		// Test status=completed returns only completed
		$response3 = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'status' => 'completed' ] );
		$data3     = $response3->get_data();
		$this->assertCount( 1, $data3, 'status=completed should return 1 todo' );
		$this->assertEquals( $completed_todo, $data3[0]['id'], 'Should return completed todo' );

		// Test status=all returns all
		$response4 = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'status' => 'all' ] );
		$data4     = $response4->get_data();
		$this->assertCount( 3, $data4, 'status=all should return all 3 todos' );
	}

	// =========================================================================
	// Awaiting Status Timestamp Tests
	// =========================================================================

	/**
	 * Test creating todo with awaiting status sets timestamp.
	 */
	public function test_create_todo_with_awaiting_status_sets_timestamp(): void {
		$user_id = $this->createApprovedCaelisUser( 'awaiting_create' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		$response = $this->doRestRequest(
			'POST',
			'/prm/v1/people/' . $person_id . '/todos',
			[
				'content' => 'Waiting for reply',
				'status'  => 'awaiting',
			]
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'awaiting', $data['status'], 'Todo should have awaiting status' );
		$this->assertNotNull( $data['awaiting_since'], 'Timestamp should be set' );
		$this->assertNotEmpty( $data['awaiting_since'], 'Timestamp should not be empty' );

		// Verify it's a valid datetime
		$timestamp = strtotime( $data['awaiting_since'] );
		$this->assertNotFalse( $timestamp, 'Timestamp should be a valid datetime' );
	}

	/**
	 * Test updating todo to awaiting status sets timestamp.
	 */
	public function test_update_todo_to_awaiting_sets_timestamp(): void {
		$user_id = $this->createApprovedCaelisUser( 'awaiting_update' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);
		$todo_id   = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Test todo',
				'status'  => 'open',
			]
		);

		// Verify initial state (open, not awaiting)
		$initial = $this->doRestRequest( 'GET', '/prm/v1/todos/' . $todo_id );
		$this->assertEquals( 'open', $initial->get_data()['status'], 'Todo should be open initially' );

		// Update to awaiting status
		$before_update = time();
		$response      = $this->doRestRequest(
			'PUT',
			'/prm/v1/todos/' . $todo_id,
			[
				'status' => 'awaiting',
			]
		);
		$after_update  = time();

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'awaiting', $data['status'], 'Todo should have awaiting status' );
		$this->assertNotNull( $data['awaiting_since'], 'Timestamp should be set' );

		// Verify timestamp is within 5 seconds of current time
		$timestamp = strtotime( $data['awaiting_since'] );
		$this->assertGreaterThanOrEqual( $before_update - 5, $timestamp, 'Timestamp should be around current time' );
		$this->assertLessThanOrEqual( $after_update + 5, $timestamp, 'Timestamp should be around current time' );
	}

	/**
	 * Test completing awaiting todo clears timestamp.
	 */
	public function test_complete_awaiting_todo_clears_timestamp(): void {
		$user_id = $this->createApprovedCaelisUser( 'awaiting_clear' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create todo with awaiting status via REST
		$create_response = $this->doRestRequest(
			'POST',
			'/prm/v1/people/' . $person_id . '/todos',
			[
				'content' => 'Waiting todo',
				'status'  => 'awaiting',
			]
		);
		$todo_id         = $create_response->get_data()['id'];

		// Verify it's awaiting
		$awaiting = $this->doRestRequest( 'GET', '/prm/v1/todos/' . $todo_id );
		$this->assertEquals( 'awaiting', $awaiting->get_data()['status'], 'Todo should be awaiting' );
		$this->assertNotNull( $awaiting->get_data()['awaiting_since'], 'Timestamp should be set' );

		// Update to completed status
		$response = $this->doRestRequest(
			'PUT',
			'/prm/v1/todos/' . $todo_id,
			[
				'status' => 'completed',
			]
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'completed', $data['status'], 'Todo should be completed' );
		$this->assertNull( $data['awaiting_since'], 'Timestamp should be cleared' );
	}

	/**
	 * Test reopening awaiting todo clears timestamp.
	 */
	public function test_reopen_awaiting_todo_clears_timestamp(): void {
		$user_id = $this->createApprovedCaelisUser( 'awaiting_reopen' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create todo with awaiting status
		$create_response = $this->doRestRequest(
			'POST',
			'/prm/v1/people/' . $person_id . '/todos',
			[
				'content' => 'Waiting todo',
				'status'  => 'awaiting',
			]
		);
		$todo_id         = $create_response->get_data()['id'];

		// Update to open status (reopen)
		$response = $this->doRestRequest(
			'PUT',
			'/prm/v1/todos/' . $todo_id,
			[
				'status' => 'open',
			]
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'open', $data['status'], 'Todo should be open' );
		$this->assertNull( $data['awaiting_since'], 'Timestamp should be cleared' );
	}

	/**
	 * Test format_todo includes status and awaiting_since fields.
	 */
	public function test_format_todo_includes_status_and_awaiting_fields(): void {
		$user_id = $this->createApprovedCaelisUser( 'format_awaiting' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);
		$todo_id   = $this->createTodo(
			$person_id,
			$user_id,
			[
				'content' => 'Test todo',
				'status'  => 'open',
			]
		);

		$response = $this->doRestRequest( 'GET', '/prm/v1/todos/' . $todo_id );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'status', $data, 'Response should have status key' );
		$this->assertArrayHasKey( 'awaiting_since', $data, 'Response should have awaiting_since key' );
		$this->assertEquals( 'open', $data['status'], 'Status should be open' );
		$this->assertNull( $data['awaiting_since'], 'awaiting_since should be null for non-awaiting todo' );
	}

	/**
	 * Test status transitions follow expected flow.
	 */
	public function test_status_transition_flow(): void {
		$user_id = $this->createApprovedCaelisUser( 'status_flow' );
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[
				'post_author' => $user_id,
				'post_title'  => 'Test Person',
			]
		);

		// Create open todo
		$create_response = $this->doRestRequest(
			'POST',
			'/prm/v1/people/' . $person_id . '/todos',
			[
				'content' => 'Test todo',
			]
		);
		$todo_id         = $create_response->get_data()['id'];
		$this->assertEquals( 'open', $create_response->get_data()['status'], 'New todo should be open' );

		// Open -> Awaiting
		$response1 = $this->doRestRequest( 'PUT', '/prm/v1/todos/' . $todo_id, [ 'status' => 'awaiting' ] );
		$this->assertEquals( 'awaiting', $response1->get_data()['status'], 'Todo should transition to awaiting' );
		$this->assertNotNull( $response1->get_data()['awaiting_since'], 'awaiting_since should be set' );

		// Awaiting -> Completed
		$response2 = $this->doRestRequest( 'PUT', '/prm/v1/todos/' . $todo_id, [ 'status' => 'completed' ] );
		$this->assertEquals( 'completed', $response2->get_data()['status'], 'Todo should transition to completed' );
		$this->assertNull( $response2->get_data()['awaiting_since'], 'awaiting_since should be cleared' );

		// Completed -> Open (reopen)
		$response3 = $this->doRestRequest( 'PUT', '/prm/v1/todos/' . $todo_id, [ 'status' => 'open' ] );
		$this->assertEquals( 'open', $response3->get_data()['status'], 'Todo should transition back to open' );

		// Open -> Completed (direct completion without awaiting)
		$response4 = $this->doRestRequest( 'PUT', '/prm/v1/todos/' . $todo_id, [ 'status' => 'completed' ] );
		$this->assertEquals( 'completed', $response4->get_data()['status'], 'Todo should transition directly to completed' );
	}
}
