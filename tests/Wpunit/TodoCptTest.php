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
        $this->server = $wp_rest_server;

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
        $user_id = $this->createCaelisUser( [ 'user_login' => $unique_id ] );
        update_user_meta( $user_id, PRM_User_Roles::APPROVAL_META_KEY, '1' );
        return $user_id;
    }

    /**
     * Helper to create a todo post.
     *
     * @param int   $person_id Person to link the todo to
     * @param int   $user_id   User ID for the post author
     * @param array $data      Additional todo data (content, is_completed, due_date, awaiting_response)
     * @return int Post ID
     */
    private function createTodo( int $person_id, int $user_id, array $data = [] ): int {
        $defaults = [
            'content'           => 'Test todo',
            'is_completed'      => false,
            'due_date'          => '',
            'visibility'        => 'private',
            'awaiting_response' => false,
        ];
        $data = array_merge( $defaults, $data );

        $post_id = self::factory()->post->create( [
            'post_type'   => 'prm_todo',
            'post_status' => 'publish',
            'post_title'  => $data['content'],
            'post_author' => $user_id,
        ] );

        update_field( 'related_person', $person_id, $post_id );
        update_field( 'is_completed', $data['is_completed'], $post_id );
        update_field( 'visibility', $data['visibility'], $post_id );

        if ( ! empty( $data['due_date'] ) ) {
            update_field( 'due_date', $data['due_date'], $post_id );
        }

        // Handle awaiting_response with auto-timestamp
        if ( $data['awaiting_response'] ) {
            update_field( 'awaiting_response', true, $post_id );
            update_field( 'awaiting_response_since', gmdate( 'Y-m-d H:i:s' ), $post_id );
        } else {
            update_field( 'awaiting_response', false, $post_id );
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
        $alice_person = $this->createPerson( [ 'post_author' => $alice_id, 'post_title' => 'Alice Person' ] );
        $alice_todo = $this->createTodo( $alice_person, $alice_id, [ 'content' => 'Alice todo' ] );

        // Create person for Bob
        wp_set_current_user( $bob_id );
        $bob_person = $this->createPerson( [ 'post_author' => $bob_id, 'post_title' => 'Bob Person' ] );
        $bob_todo = $this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob todo' ] );

        // Query as Alice - should only see her own todos
        wp_set_current_user( $alice_id );
        $response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
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
        $admin_person = $this->createPerson( [ 'post_author' => $admin_id, 'post_title' => 'Admin Person' ] );
        $admin_todo = $this->createTodo( $admin_person, $admin_id, [ 'content' => 'Admin todo' ] );

        // Create person and todo for regular user
        wp_set_current_user( $regular_user_id );
        $regular_person = $this->createPerson( [ 'post_author' => $regular_user_id, 'post_title' => 'Regular Person' ] );
        $regular_todo = $this->createTodo( $regular_person, $regular_user_id, [ 'content' => 'Regular todo' ] );

        // Query as admin - in frontend context, admin sees only their own data
        wp_set_current_user( $admin_id );
        $response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
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
        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );
        $todo1 = $this->createTodo( $person_id, $user_id, [ 'content' => 'Todo 1' ] );
        $todo2 = $this->createTodo( $person_id, $user_id, [ 'content' => 'Todo 2' ] );

        $response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
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
        $person1 = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Person 1' ] );
        $person2 = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Person 2' ] );

        // Create todos for each
        $todo1 = $this->createTodo( $person1, $user_id, [ 'content' => 'Todo for Person 1' ] );
        $todo2 = $this->createTodo( $person2, $user_id, [ 'content' => 'Todo for Person 2' ] );

        // Query todos for person 1 only
        $response = $this->doRestRequest( 'GET', '/prm/v1/people/' . $person1 . '/todos' );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
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

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        $response = $this->doRestRequest( 'POST', '/prm/v1/people/' . $person_id . '/todos', [
            'content'  => 'New todo item',
            'due_date' => '2026-02-01',
        ] );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'id', $data, 'Response should include id' );
        $this->assertEquals( 'New todo item', $data['content'], 'Content should match' );
        $this->assertEquals( $person_id, $data['person_id'], 'Person ID should match' );
        $this->assertEquals( '2026-02-01', $data['due_date'], 'Due date should match' );
        $this->assertFalse( $data['is_completed'], 'New todo should not be completed' );

        // Verify post exists in database
        $post = get_post( $data['id'] );
        $this->assertNotNull( $post, 'Todo post should exist' );
        $this->assertEquals( 'prm_todo', $post->post_type, 'Post type should be prm_todo' );
        $this->assertEquals( $user_id, (int) $post->post_author, 'Author should be current user' );
    }

    /**
     * Test PUT /prm/v1/todos/{id} changes is_completed status.
     */
    public function test_update_todo_changes_is_completed(): void {
        $user_id = $this->createApprovedCaelisUser( 'updater_user' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );
        $todo_id = $this->createTodo( $person_id, $user_id, [ 'content' => 'Test todo', 'is_completed' => false ] );

        // Update to completed
        $response = $this->doRestRequest( 'PUT', '/prm/v1/todos/' . $todo_id, [
            'is_completed' => true,
        ] );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertTrue( $data['is_completed'], 'Todo should be marked as completed' );

        // Verify in database
        $is_completed = get_field( 'is_completed', $todo_id );
        $this->assertTrue( (bool) $is_completed, 'Database should reflect completed status' );
    }

    /**
     * Test DELETE /prm/v1/todos/{id} removes the post.
     */
    public function test_delete_todo_removes_post(): void {
        $user_id = $this->createApprovedCaelisUser( 'deleter_user' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );
        $todo_id = $this->createTodo( $person_id, $user_id, [ 'content' => 'To be deleted' ] );

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
        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );
        $this->createTodo( $person_id, $user_id, [ 'content' => 'Todo 1', 'is_completed' => false ] );
        $this->createTodo( $person_id, $user_id, [ 'content' => 'Todo 2', 'is_completed' => false ] );
        $this->createTodo( $person_id, $user_id, [ 'content' => 'Todo 3', 'is_completed' => false ] );

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

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        // Create 2 open and 2 completed todos
        $this->createTodo( $person_id, $user_id, [ 'content' => 'Open 1', 'is_completed' => false ] );
        $this->createTodo( $person_id, $user_id, [ 'content' => 'Open 2', 'is_completed' => false ] );
        $this->createTodo( $person_id, $user_id, [ 'content' => 'Completed 1', 'is_completed' => true ] );
        $this->createTodo( $person_id, $user_id, [ 'content' => 'Completed 2', 'is_completed' => true ] );

        $response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 2, $data['stats']['open_todos_count'], 'Should only count open (incomplete) todos' );
    }

    /**
     * Test dashboard counts only user's own todos.
     */
    public function test_dashboard_counts_only_own_todos(): void {
        $alice_id = $this->createApprovedCaelisUser( 'alice_dash' );
        $bob_id   = $this->createApprovedCaelisUser( 'bob_dash' );

        // Create 3 todos for Alice
        wp_set_current_user( $alice_id );
        $alice_person = $this->createPerson( [ 'post_author' => $alice_id, 'post_title' => 'Alice Person' ] );
        $this->createTodo( $alice_person, $alice_id, [ 'content' => 'Alice 1' ] );
        $this->createTodo( $alice_person, $alice_id, [ 'content' => 'Alice 2' ] );
        $this->createTodo( $alice_person, $alice_id, [ 'content' => 'Alice 3' ] );

        // Create 5 todos for Bob
        wp_set_current_user( $bob_id );
        $bob_person = $this->createPerson( [ 'post_author' => $bob_id, 'post_title' => 'Bob Person' ] );
        $this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 1' ] );
        $this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 2' ] );
        $this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 3' ] );
        $this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 4' ] );
        $this->createTodo( $bob_person, $bob_id, [ 'content' => 'Bob 5' ] );

        // Alice's dashboard should show 3 todos
        wp_set_current_user( $alice_id );
        $alice_response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );
        $alice_data = $alice_response->get_data();
        $this->assertEquals( 3, $alice_data['stats']['open_todos_count'], 'Alice should see 3 todos' );

        // Bob's dashboard should show 5 todos
        wp_set_current_user( $bob_id );
        $bob_response = $this->doRestRequest( 'GET', '/prm/v1/dashboard' );
        $bob_data = $bob_response->get_data();
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
    // Completion Filter Tests
    // =========================================================================

    /**
     * Test todos endpoint excludes completed by default.
     */
    public function test_todos_excludes_completed_by_default(): void {
        $user_id = $this->createApprovedCaelisUser( 'filter_complete' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        // Create open and completed todos
        $open_todo = $this->createTodo( $person_id, $user_id, [ 'content' => 'Open todo', 'is_completed' => false ] );
        $completed_todo = $this->createTodo( $person_id, $user_id, [ 'content' => 'Completed todo', 'is_completed' => true ] );

        // Default request (no completed filter)
        $response = $this->doRestRequest( 'GET', '/prm/v1/todos' );

        $data = $response->get_data();
        $todo_ids = array_column( $data, 'id' );

        $this->assertContains( $open_todo, $todo_ids, 'Open todo should be returned' );
        $this->assertNotContains( $completed_todo, $todo_ids, 'Completed todo should NOT be returned by default' );
    }

    /**
     * Test todos endpoint includes completed with filter.
     */
    public function test_todos_includes_completed_with_filter(): void {
        $user_id = $this->createApprovedCaelisUser( 'filter_all' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        $open_todo = $this->createTodo( $person_id, $user_id, [ 'content' => 'Open todo', 'is_completed' => false ] );
        $completed_todo = $this->createTodo( $person_id, $user_id, [ 'content' => 'Completed todo', 'is_completed' => true ] );

        // Request with completed=true
        $response = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'completed' => 'true' ] );

        $data = $response->get_data();
        $todo_ids = array_column( $data, 'id' );

        $this->assertContains( $open_todo, $todo_ids, 'Open todo should be returned' );
        $this->assertContains( $completed_todo, $todo_ids, 'Completed todo should be returned with completed=true' );
    }

    // =========================================================================
    // Awaiting Response Filter Tests
    // =========================================================================

    /**
     * Test awaiting_response=true filter returns only awaiting todos.
     */
    public function test_todos_with_awaiting_response_filter_returns_only_awaiting(): void {
        $user_id = $this->createApprovedCaelisUser( 'awaiting_filter1' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        // Create 2 awaiting and 1 non-awaiting todos
        $awaiting1 = $this->createTodo( $person_id, $user_id, [ 'content' => 'Awaiting 1', 'awaiting_response' => true ] );
        $awaiting2 = $this->createTodo( $person_id, $user_id, [ 'content' => 'Awaiting 2', 'awaiting_response' => true ] );
        $not_awaiting = $this->createTodo( $person_id, $user_id, [ 'content' => 'Not awaiting', 'awaiting_response' => false ] );

        // Request with awaiting_response=true
        $response = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'awaiting_response' => 'true' ] );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $todo_ids = array_column( $data, 'id' );

        $this->assertContains( $awaiting1, $todo_ids, 'First awaiting todo should be returned' );
        $this->assertContains( $awaiting2, $todo_ids, 'Second awaiting todo should be returned' );
        $this->assertNotContains( $not_awaiting, $todo_ids, 'Non-awaiting todo should NOT be returned' );
        $this->assertCount( 2, $data, 'Should return exactly 2 todos' );
    }

    /**
     * Test awaiting_response filter combined with completion filter.
     */
    public function test_todos_awaiting_response_filter_with_completed(): void {
        $user_id = $this->createApprovedCaelisUser( 'awaiting_filter2' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        // Create 4 todos: all combinations of completed and awaiting
        $open_awaiting = $this->createTodo( $person_id, $user_id, [
            'content'           => 'Open awaiting',
            'is_completed'      => false,
            'awaiting_response' => true,
        ] );
        $open_not_awaiting = $this->createTodo( $person_id, $user_id, [
            'content'           => 'Open not awaiting',
            'is_completed'      => false,
            'awaiting_response' => false,
        ] );
        $completed_awaiting = $this->createTodo( $person_id, $user_id, [
            'content'           => 'Completed awaiting',
            'is_completed'      => true,
            'awaiting_response' => true,
        ] );
        $completed_not_awaiting = $this->createTodo( $person_id, $user_id, [
            'content'           => 'Completed not awaiting',
            'is_completed'      => true,
            'awaiting_response' => false,
        ] );

        // Test 1: awaiting_response=true only (no completed param) - should return only open + awaiting
        $response1 = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'awaiting_response' => 'true' ] );
        $data1 = $response1->get_data();
        $todo_ids1 = array_column( $data1, 'id' );

        $this->assertContains( $open_awaiting, $todo_ids1, 'Open awaiting should be returned' );
        $this->assertNotContains( $open_not_awaiting, $todo_ids1, 'Open not awaiting should NOT be returned' );
        $this->assertNotContains( $completed_awaiting, $todo_ids1, 'Completed awaiting should NOT be returned without completed param' );
        $this->assertNotContains( $completed_not_awaiting, $todo_ids1, 'Completed not awaiting should NOT be returned' );
        $this->assertCount( 1, $data1, 'Should return exactly 1 todo' );

        // Test 2: awaiting_response=true AND completed=true - should return both awaiting todos
        $response2 = $this->doRestRequest( 'GET', '/prm/v1/todos', [
            'awaiting_response' => 'true',
            'completed'         => 'true',
        ] );
        $data2 = $response2->get_data();
        $todo_ids2 = array_column( $data2, 'id' );

        $this->assertContains( $open_awaiting, $todo_ids2, 'Open awaiting should be returned' );
        $this->assertContains( $completed_awaiting, $todo_ids2, 'Completed awaiting should be returned with completed=true' );
        $this->assertNotContains( $open_not_awaiting, $todo_ids2, 'Open not awaiting should NOT be returned' );
        $this->assertNotContains( $completed_not_awaiting, $todo_ids2, 'Completed not awaiting should NOT be returned' );
        $this->assertCount( 2, $data2, 'Should return exactly 2 todos' );
    }

    /**
     * Test awaiting_response=false filter returns only non-awaiting todos.
     */
    public function test_todos_awaiting_response_filter_false_returns_non_awaiting(): void {
        $user_id = $this->createApprovedCaelisUser( 'awaiting_filter3' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        // Create 1 awaiting and 1 non-awaiting todo
        $awaiting = $this->createTodo( $person_id, $user_id, [ 'content' => 'Awaiting', 'awaiting_response' => true ] );
        $not_awaiting = $this->createTodo( $person_id, $user_id, [ 'content' => 'Not awaiting', 'awaiting_response' => false ] );

        // Request with awaiting_response=false
        $response = $this->doRestRequest( 'GET', '/prm/v1/todos', [ 'awaiting_response' => 'false' ] );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $todo_ids = array_column( $data, 'id' );

        $this->assertContains( $not_awaiting, $todo_ids, 'Non-awaiting todo should be returned' );
        $this->assertNotContains( $awaiting, $todo_ids, 'Awaiting todo should NOT be returned' );
        $this->assertCount( 1, $data, 'Should return exactly 1 todo' );
    }

    // =========================================================================
    // Awaiting Response Tests
    // =========================================================================

    /**
     * Test creating todo with awaiting_response sets timestamp.
     */
    public function test_create_todo_with_awaiting_response_sets_timestamp(): void {
        $user_id = $this->createApprovedCaelisUser( 'awaiting_create' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        $response = $this->doRestRequest( 'POST', '/prm/v1/people/' . $person_id . '/todos', [
            'content'           => 'Waiting for reply',
            'awaiting_response' => true,
        ] );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertTrue( $data['awaiting_response'], 'Todo should be marked as awaiting response' );
        $this->assertNotNull( $data['awaiting_response_since'], 'Timestamp should be set' );
        $this->assertNotEmpty( $data['awaiting_response_since'], 'Timestamp should not be empty' );

        // Verify it's a valid datetime
        $timestamp = strtotime( $data['awaiting_response_since'] );
        $this->assertNotFalse( $timestamp, 'Timestamp should be a valid datetime' );
    }

    /**
     * Test updating todo sets awaiting_response timestamp.
     */
    public function test_update_todo_sets_awaiting_response_timestamp(): void {
        $user_id = $this->createApprovedCaelisUser( 'awaiting_update' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );
        $todo_id = $this->createTodo( $person_id, $user_id, [ 'content' => 'Test todo' ] );

        // Verify initial state (not awaiting)
        $initial = $this->doRestRequest( 'GET', '/prm/v1/todos/' . $todo_id );
        $this->assertFalse( $initial->get_data()['awaiting_response'], 'Todo should not be awaiting initially' );

        // Update to awaiting_response = true
        $before_update = time();
        $response = $this->doRestRequest( 'PUT', '/prm/v1/todos/' . $todo_id, [
            'awaiting_response' => true,
        ] );
        $after_update = time();

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertTrue( $data['awaiting_response'], 'Todo should be marked as awaiting response' );
        $this->assertNotNull( $data['awaiting_response_since'], 'Timestamp should be set' );

        // Verify timestamp is within 5 seconds of current time
        $timestamp = strtotime( $data['awaiting_response_since'] );
        $this->assertGreaterThanOrEqual( $before_update - 5, $timestamp, 'Timestamp should be around current time' );
        $this->assertLessThanOrEqual( $after_update + 5, $timestamp, 'Timestamp should be around current time' );
    }

    /**
     * Test updating todo clears awaiting_response timestamp.
     */
    public function test_update_todo_clears_awaiting_response_timestamp(): void {
        $user_id = $this->createApprovedCaelisUser( 'awaiting_clear' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );

        // Create todo with awaiting_response = true via REST
        $create_response = $this->doRestRequest( 'POST', '/prm/v1/people/' . $person_id . '/todos', [
            'content'           => 'Waiting todo',
            'awaiting_response' => true,
        ] );
        $todo_id = $create_response->get_data()['id'];

        // Verify it's awaiting
        $awaiting = $this->doRestRequest( 'GET', '/prm/v1/todos/' . $todo_id );
        $this->assertTrue( $awaiting->get_data()['awaiting_response'], 'Todo should be awaiting' );
        $this->assertNotNull( $awaiting->get_data()['awaiting_response_since'], 'Timestamp should be set' );

        // Update to awaiting_response = false
        $response = $this->doRestRequest( 'PUT', '/prm/v1/todos/' . $todo_id, [
            'awaiting_response' => false,
        ] );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertFalse( $data['awaiting_response'], 'Todo should no longer be awaiting' );
        $this->assertNull( $data['awaiting_response_since'], 'Timestamp should be cleared' );
    }

    /**
     * Test format_todo includes awaiting_response fields.
     */
    public function test_format_todo_includes_awaiting_response_fields(): void {
        $user_id = $this->createApprovedCaelisUser( 'format_awaiting' );
        wp_set_current_user( $user_id );

        $person_id = $this->createPerson( [ 'post_author' => $user_id, 'post_title' => 'Test Person' ] );
        $todo_id = $this->createTodo( $person_id, $user_id, [ 'content' => 'Test todo' ] );

        $response = $this->doRestRequest( 'GET', '/prm/v1/todos/' . $todo_id );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'awaiting_response', $data, 'Response should have awaiting_response key' );
        $this->assertArrayHasKey( 'awaiting_response_since', $data, 'Response should have awaiting_response_since key' );
    }
}
