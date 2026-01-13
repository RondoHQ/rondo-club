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
        $this->server = $wp_rest_server;

        // Instantiate REST API class to register routes (bypasses prm_is_rest_request() check)
        new \PRM_REST_API();

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
        $john_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'John Doe',
        ] );
        $jane_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Jane Smith',
        ] );

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
        $alice_john = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Johns Contact',
        ] );

        // Create person for Bob
        $bob_john = $this->createPerson( [
            'post_author' => $bob_id,
            'post_title'  => 'Johns Other Contact',
        ] );

        // Set current user to Alice and search
        wp_set_current_user( $alice_id );
        $response = $this->doRestRequest( 'GET', '/prm/v1/search', [ 'q' => 'John' ] );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
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
        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Acme Employee',
        ] );

        // Create company
        $company_id = $this->createOrganization( [
            'post_author' => $alice_id,
            'post_title'  => 'Acme Corp',
        ] );

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
}
