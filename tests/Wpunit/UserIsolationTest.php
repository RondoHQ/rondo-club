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
	// =========================================================================
	// Task 1: Test user_can_access_post() author check
	// =========================================================================

	/**
	 * Test that non-author cannot access another user's person post.
	 */
	public function test_non_author_cannot_access_person_post(): void {
		$alice_id = $this->createRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createRondoUser( [ 'user_login' => 'bob' ] );

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
		$alice_id = $this->createRondoUser( [ 'user_login' => 'alice' ] );

		$team_id = $this->createOrganization( [ 'post_author' => $alice_id ] );

		$this->assertTrue(
			$this->access_control->user_can_access_post( $team_id, $alice_id ),
			'Alice should have access to her own team post'
		);
	}

	/**
	 * The user-approval system this replaced is gone. What still holds is that a
	 * member with no linked person has no household, and therefore reaches no
	 * person records — not even ones they authored.
	 */
	public function test_member_without_linked_person_cannot_access_person_records(): void {
		$alice_id = $this->createRondoUser( [ 'user_login' => 'alice' ] );
		$loner_id = $this->createRondoUser( [ 'user_login' => 'loner' ] );

		$person_id     = $this->createPerson( [ 'post_author' => $alice_id ] );
		$own_person_id = $this->createPerson( [ 'post_author' => $loner_id ] );

		$this->assertFalse(
			$this->access_control->user_can_access_post( $person_id, $loner_id ),
			'Should not reach another member\'s person record'
		);
		$this->assertFalse(
			$this->access_control->user_can_access_post( $own_person_id, $loner_id ),
			'Authoring a person record does not put it in your household'
		);
	}

	/**
	 * Test that trashed posts are not accessible even by author.
	 */
	public function test_author_cannot_access_trashed_post(): void {
		$alice_id = $this->createRondoUser( [ 'user_login' => 'alice' ] );

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
		$alice_id = $this->createRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createRondoUser( [ 'user_login' => 'bob' ] );

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
	 * Test logged out user gets empty results from WP_Query.
	 */
	public function test_logged_out_user_gets_empty_wp_query_results(): void {
		$alice_id = $this->createRondoUser( [ 'user_login' => 'alice' ] );

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
		$alice_id = $this->createRondoUser( [ 'user_login' => 'alice' ] );

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

		$filtered_args = $this->access_control->filter_rest_query( $args, $request, 'person' );

		$this->assertArrayHasKey( 'post__in', $filtered_args );
		$this->assertEquals( [ 0 ], $filtered_args['post__in'], 'Logged out user should get post__in = [0]' );
	}

	/**
	 * Test that query filtering does not affect non-controlled post types.
	 */
	public function test_query_filtering_ignores_non_controlled_post_types(): void {
		$alice_id = $this->createRondoUser( [ 'user_login' => 'alice' ] );
		$bob_id   = $this->createRondoUser( [ 'user_login' => 'bob' ] );

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
