<?php

namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;
use RONDO_Access_Control;
use RONDO_User_Roles;
use WP_Query;

/**
 * Tests for user isolation in access control.
 *
 * Verifies that users can only see posts they authored - the fundamental
 * access control rule for Rondo Club. Tests both single-post access checks
 * and query filtering for all controlled post types.
 */
class UserIsolationTest extends RondoTestCase {

	/**
	 * Access control instance for testing.
	 *
	 * @var RONDO_Access_Control
	 */
	private RONDO_Access_Control $access_control;

	/**
	 * Set up test environment before each test.
	 */
	protected function set_up(): void {
		parent::set_up();

		// Create fresh access control instance for testing
		$this->access_control = new RONDO_Access_Control();
	}

	/**
	 * Helper to create an approved Rondo user.
	 *
	 * @param array $args User arguments
	 * @return int User ID
	 */
	private function createApprovedRondoUser( array $args = [] ): int {
		$user_id = $this->createRondoUser( $args );
		update_user_meta( $user_id, RONDO_User_Roles::APPROVAL_META_KEY, '1' );
		return $user_id;
	}

	// =========================================================================
	// Task 1: Test user_can_access_post() author check
	// =========================================================================

	/**
	 * Test that author can access their own person post.
	 */
	public function test_author_can_access_own_person_post(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );

		$person_id = $this->createPerson( [ 'post_author' => $alice_id ] );

		$this->assertTrue(
			$this->access_control->user_can_access_post( $person_id, $alice_id ),
			'Alice should have access to her own person post'
		);
	}

	/**
	 * Test that non-author cannot access another user's person post.
	 */
	public function test_non_author_cannot_access_person_post(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		$person_id = $this->createPerson( [ 'post_author' => $alice_id ] );

		$this->assertFalse(
			$this->access_control->user_can_access_post( $person_id, $bob_id ),
			'Bob should NOT have access to Alice\'s person post'
		);
	}

	/**
	 * Test that author can access their own team post.
	 */
	public function test_author_can_access_own_team_post(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );

		$team_id = $this->createOrganization( [ 'post_author' => $alice_id ] );

		$this->assertTrue(
			$this->access_control->user_can_access_post( $team_id, $alice_id ),
			'Alice should have access to her own team post'
		);
	}

	/**
	 * Test that non-author cannot access another user's team post.
	 */
	public function test_non_author_cannot_access_team_post(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		$team_id = $this->createOrganization( [ 'post_author' => $alice_id ] );

		$this->assertFalse(
			$this->access_control->user_can_access_post( $team_id, $bob_id ),
			'Bob should NOT have access to Alice\'s team post'
		);
	}

	/**
	 * Test that unapproved user cannot access any posts.
	 */
	public function test_unapproved_user_cannot_access_posts(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );

		// Create unapproved user
		$unapproved_id = $this->createRondoUser( [ 'user_login' => 'unapproved' ] );
		update_user_meta( $unapproved_id, RONDO_User_Roles::APPROVAL_META_KEY, '0' );

		// Create posts by Alice
		$person_id = $this->createPerson( [ 'post_author' => $alice_id ] );
		$team_id   = $this->createOrganization( [ 'post_author' => $alice_id ] );

		// Even posts created by the unapproved user shouldn't be accessible
		$own_person_id = $this->createPerson( [ 'post_author' => $unapproved_id ] );

		$this->assertFalse(
			$this->access_control->user_can_access_post( $person_id, $unapproved_id ),
			'Unapproved user should NOT access other\'s person post'
		);
		$this->assertFalse(
			$this->access_control->user_can_access_post( $team_id, $unapproved_id ),
			'Unapproved user should NOT access other\'s team post'
		);
		$this->assertFalse(
			$this->access_control->user_can_access_post( $own_person_id, $unapproved_id ),
			'Unapproved user should NOT even access their own post'
		);
	}

	/**
	 * Test that trashed posts are not accessible even by author.
	 */
	public function test_author_cannot_access_trashed_post(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );

		$person_id = $this->createPerson(
			[
				'post_author' => $alice_id,
				'post_status' => 'trash',
			]
		);

		$this->assertFalse(
			$this->access_control->user_can_access_post( $person_id, $alice_id ),
			'Alice should NOT have access to her trashed post'
		);
	}

	/**
	 * Test that non-controlled post types are accessible.
	 */
	public function test_non_controlled_post_types_are_accessible(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		// Create a regular WordPress post (not a controlled post type)
		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $alice_id,
			]
		);

		$this->assertTrue(
			$this->access_control->user_can_access_post( $post_id, $bob_id ),
			'Bob should have access to regular WordPress posts (non-controlled type)'
		);
	}

	// =========================================================================
	// Task 2: Test query filtering for user isolation
	// =========================================================================

	/**
	 * Test get_accessible_post_ids returns only user's own posts.
	 */
	public function test_get_accessible_post_ids_returns_only_user_posts(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		// Create 3 persons for Alice
		$alice_person_1 = $this->createPerson( [ 'post_author' => $alice_id ] );
		$alice_person_2 = $this->createPerson( [ 'post_author' => $alice_id ] );
		$alice_person_3 = $this->createPerson( [ 'post_author' => $alice_id ] );

		// Create 2 persons for Bob
		$bob_person_1 = $this->createPerson( [ 'post_author' => $bob_id ] );
		$bob_person_2 = $this->createPerson( [ 'post_author' => $bob_id ] );

		// Test Alice's accessible IDs (cast to int for comparison - DB returns strings)
		$alice_ids = array_map( 'intval', $this->access_control->get_accessible_post_ids( 'person', $alice_id ) );
		$this->assertCount( 3, $alice_ids, 'Alice should have exactly 3 accessible persons' );
		$this->assertContains( $alice_person_1, $alice_ids );
		$this->assertContains( $alice_person_2, $alice_ids );
		$this->assertContains( $alice_person_3, $alice_ids );
		$this->assertNotContains( $bob_person_1, $alice_ids );
		$this->assertNotContains( $bob_person_2, $alice_ids );

		// Test Bob's accessible IDs
		$bob_ids = array_map( 'intval', $this->access_control->get_accessible_post_ids( 'person', $bob_id ) );
		$this->assertCount( 2, $bob_ids, 'Bob should have exactly 2 accessible persons' );
		$this->assertContains( $bob_person_1, $bob_ids );
		$this->assertContains( $bob_person_2, $bob_ids );
		$this->assertNotContains( $alice_person_1, $bob_ids );
		$this->assertNotContains( $alice_person_2, $bob_ids );
		$this->assertNotContains( $alice_person_3, $bob_ids );
	}

	/**
	 * Test get_accessible_post_ids works for all controlled post types.
	 */
	public function test_get_accessible_post_ids_works_for_all_post_types(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		// Create posts for Alice
		$alice_person = $this->createPerson( [ 'post_author' => $alice_id ] );
		$alice_team   = $this->createOrganization( [ 'post_author' => $alice_id ] );

		// Create posts for Bob
		$bob_person = $this->createPerson( [ 'post_author' => $bob_id ] );
		$bob_team   = $this->createOrganization( [ 'post_author' => $bob_id ] );

		// Test Alice's person access (cast to int - DB returns strings)
		$alice_person_ids = array_map( 'intval', $this->access_control->get_accessible_post_ids( 'person', $alice_id ) );
		$this->assertContains( $alice_person, $alice_person_ids );
		$this->assertNotContains( $bob_person, $alice_person_ids );

		// Test Alice's team access
		$alice_team_ids = array_map( 'intval', $this->access_control->get_accessible_post_ids( 'team', $alice_id ) );
		$this->assertContains( $alice_team, $alice_team_ids );
		$this->assertNotContains( $bob_team, $alice_team_ids );
	}

	/**
	 * Test WP_Query filtering returns only current user's posts.
	 */
	public function test_wp_query_filtering_returns_only_user_posts(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		// Create posts for Alice
		$alice_person_1 = $this->createPerson( [ 'post_author' => $alice_id ] );
		$alice_person_2 = $this->createPerson( [ 'post_author' => $alice_id ] );

		// Create posts for Bob
		$bob_person_1 = $this->createPerson( [ 'post_author' => $bob_id ] );

		// Set current user to Alice
		wp_set_current_user( $alice_id );

		// Query all persons - should only return Alice's
		$query = new WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$result_ids = $query->posts;

		$this->assertContains( $alice_person_1, $result_ids, 'Alice should see her first person' );
		$this->assertContains( $alice_person_2, $result_ids, 'Alice should see her second person' );
		$this->assertNotContains( $bob_person_1, $result_ids, 'Alice should NOT see Bob\'s person' );
	}

	/**
	 * Test WP_Query filtering works when switching users.
	 */
	public function test_wp_query_filtering_switches_with_user_context(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		// Create posts
		$alice_person = $this->createPerson( [ 'post_author' => $alice_id ] );
		$bob_person   = $this->createPerson( [ 'post_author' => $bob_id ] );

		// Query as Alice
		wp_set_current_user( $alice_id );
		$alice_query = new WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$this->assertContains( $alice_person, $alice_query->posts );
		$this->assertNotContains( $bob_person, $alice_query->posts );

		// Query as Bob
		wp_set_current_user( $bob_id );
		$bob_query = new WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$this->assertContains( $bob_person, $bob_query->posts );
		$this->assertNotContains( $alice_person, $bob_query->posts );
	}

	/**
	 * Test REST query filtering sets post__in to user's posts only.
	 */
	public function test_rest_query_filtering_restricts_to_user_posts(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		// Create posts
		$alice_person = $this->createPerson( [ 'post_author' => $alice_id ] );
		$bob_person   = $this->createPerson( [ 'post_author' => $bob_id ] );

		// Set current user to Alice
		wp_set_current_user( $alice_id );

		// Simulate REST API request args
		$args = [
			'post_type'      => 'person',
			'posts_per_page' => 10,
		];

		// Create mock WP_REST_Request
		$request = new \WP_REST_Request( 'GET', '/wp/v2/people' );

		// Call filter_rest_query directly
		$filtered_args = $this->access_control->filter_rest_query( $args, $request );

		// Cast post__in to integers for comparison (DB returns strings)
		$post_in_ids = array_map( 'intval', $filtered_args['post__in'] );

		$this->assertArrayHasKey( 'post__in', $filtered_args, 'REST query should have post__in set' );
		$this->assertContains( $alice_person, $post_in_ids, 'Alice\'s person should be in allowed IDs' );
		$this->assertNotContains( $bob_person, $post_in_ids, 'Bob\'s person should NOT be in allowed IDs' );
	}

	/**
	 * Test logged out user gets empty results from WP_Query.
	 */
	public function test_logged_out_user_gets_empty_wp_query_results(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );

		// Create a post
		$this->createPerson( [ 'post_author' => $alice_id ] );

		// Set no current user (logged out)
		wp_set_current_user( 0 );

		// Query - should return empty
		$query = new WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$this->assertEmpty( $query->posts, 'Logged out user should get no results' );
	}

	/**
	 * Test logged out user gets post__in = [0] from REST query filter.
	 */
	public function test_logged_out_user_gets_blocked_rest_query(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );

		// Create a post
		$this->createPerson( [ 'post_author' => $alice_id ] );

		// Set no current user (logged out)
		wp_set_current_user( 0 );

		// Simulate REST API request
		$args    = [
			'post_type'      => 'person',
			'posts_per_page' => 10,
		];
		$request = new \WP_REST_Request( 'GET', '/wp/v2/people' );

		$filtered_args = $this->access_control->filter_rest_query( $args, $request );

		$this->assertArrayHasKey( 'post__in', $filtered_args );
		$this->assertEquals( [ 0 ], $filtered_args['post__in'], 'Logged out user should get post__in = [0]' );
	}

	/**
	 * Test get_accessible_post_ids returns empty array for user with no posts.
	 */
	public function test_get_accessible_post_ids_returns_empty_for_new_user(): void {
		$alice_id   = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$newuser_id = $this->createApprovedRondoUser( [ 'user_login' => 'newuser' ] );

		// Create posts only for Alice
		$this->createPerson( [ 'post_author' => $alice_id ] );
		$this->createPerson( [ 'post_author' => $alice_id ] );

		// New user should have empty accessible IDs
		$newuser_ids = $this->access_control->get_accessible_post_ids( 'person', $newuser_id );

		$this->assertEmpty( $newuser_ids, 'New user with no posts should get empty array' );
	}

	/**
	 * Test that query filtering does not affect non-controlled post types.
	 */
	public function test_query_filtering_ignores_non_controlled_post_types(): void {
		$alice_id = $this->createApprovedRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createApprovedRondoUser( [ 'user_login' => 'bob' ] );

		// Create regular WordPress posts
		$alice_post = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $alice_id,
			]
		);
		$bob_post   = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $bob_id,
			]
		);

		// Set current user to Alice
		wp_set_current_user( $alice_id );

		// Query regular posts - Alice should see both (no access control)
		$query = new WP_Query(
			[
				'post_type'      => 'post',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		// Both posts should be accessible since 'post' is not a controlled type
		$this->assertContains( $alice_post, $query->posts );
		$this->assertContains( $bob_post, $query->posts );
	}
}
