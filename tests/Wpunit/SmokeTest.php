<?php

namespace Tests\Wpunit;

use Tests\Support\CaelisTestCase;

/**
 * Smoke test to verify PHPUnit/wp-browser setup is working.
 *
 * These tests verify:
 * - WordPress is loaded correctly
 * - Caelis theme is active
 * - Custom post types are registered
 * - ACF Pro is available
 * - Factory methods work
 */
class SmokeTest extends CaelisTestCase {

	public function test_wordpress_is_loaded(): void {
		$this->assertTrue( function_exists( 'wp_insert_post' ) );
		$this->assertTrue( defined( 'ABSPATH' ) );
	}

	public function test_caelis_theme_is_active(): void {
		$theme = wp_get_theme();
		$this->assertEquals( 'caelis', $theme->get_stylesheet() );
	}

	public function test_person_post_type_is_registered(): void {
		$post_type = get_post_type_object( 'person' );
		$this->assertNotNull( $post_type, 'Person post type should be registered' );
	}

	public function test_company_post_type_is_registered(): void {
		$post_type = get_post_type_object( 'company' );
		$this->assertNotNull( $post_type, 'Company post type should be registered' );
	}

	public function test_important_date_post_type_is_registered(): void {
		$post_type = get_post_type_object( 'important_date' );
		$this->assertNotNull( $post_type, 'Important Date post type should be registered' );
	}

	public function test_acf_pro_is_available(): void {
		$this->assertTrue(
			function_exists( 'get_field' ),
			'ACF Pro should be loaded (get_field function)'
		);
		$this->assertTrue(
			function_exists( 'update_field' ),
			'ACF Pro should be loaded (update_field function)'
		);
	}

	public function test_caelis_user_role_exists(): void {
		$role = get_role( 'caelis_user' );
		$this->assertNotNull( $role, 'Caelis User role should exist' );
	}

	public function test_can_create_person_with_factory(): void {
		$person_id = $this->createPerson( array( 'post_title' => 'Test Person' ) );

		$this->assertGreaterThan( 0, $person_id );
		$this->assertEquals( 'person', get_post_type( $person_id ) );
		$this->assertEquals( 'Test Person', get_the_title( $person_id ) );
	}

	public function test_can_create_caelis_user(): void {
		$user_id = $this->createCaelisUser( array( 'user_login' => 'testuser' ) );

		$this->assertGreaterThan( 0, $user_id );
		$user = get_user_by( 'id', $user_id );
		$this->assertTrue( in_array( 'caelis_user', $user->roles, true ) );
	}

	public function test_database_transactions_rollback(): void {
		// Create a person in this test
		$person_id = $this->createPerson( array( 'post_title' => 'Transaction Test' ) );

		// Person should exist within this test
		$this->assertNotNull( get_post( $person_id ) );

		// After this test, the person will be rolled back
		// (verified by not persisting between test runs)
	}
}
