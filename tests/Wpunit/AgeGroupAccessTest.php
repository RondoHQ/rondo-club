<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Tests\Support\RondoTestCase;

/**
 * Tests for AccessControl::get_permitted_age_groups()
 *
 * Verifies the age-group access filtering helper method returns correct
 * results for various role/capability combinations.
 */
class AgeGroupAccessTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();

		// Clean up the option before each test.
		delete_option( 'rondo_age_group_access' );

		// Reset the suppress flag.
		AccessControl::$suppress_age_group_filter = false;
	}

	protected function tear_down(): void {
		delete_option( 'rondo_age_group_access' );
		AccessControl::$suppress_age_group_filter = false;
		parent::tear_down();
	}

	/**
	 * Admin users (manage_options) should bypass age-group filtering.
	 */
	public function test_returns_null_for_admin_user(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		// Even with config present, admin bypasses.
		update_option(
			'rondo_age_group_access',
			[
				'rondo_user' => [ 'senioren' ],
			]
		);

		$result = AccessControl::get_permitted_age_groups( $admin_id );
		$this->assertNull( $result, 'Admin user should bypass age-group filtering' );
	}

	/**
	 * Users with the fairplay capability should bypass filtering.
	 */
	public function test_returns_null_for_fairplay_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_fairplay' ] );

		update_option(
			'rondo_age_group_access',
			[
				'rondo_fairplay' => [ 'senioren' ],
			]
		);

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'User with fairplay capability should bypass filtering' );
	}

	/**
	 * Users with VOG capability should bypass filtering.
	 */
	public function test_returns_null_for_vog_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_vog' ] );

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'User with vog capability should bypass filtering' );
	}

	/**
	 * Users with financieel capability should bypass filtering.
	 */
	public function test_returns_null_for_financieel_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_financieel' ] );

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'User with financieel capability should bypass filtering' );
	}

	/**
	 * Users with toegangscontrole capability should bypass filtering.
	 */
	public function test_returns_null_for_toegangscontrole_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_toegangscontrole' ] );

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'User with toegangscontrole capability should bypass filtering' );
	}

	/**
	 * Users with manage_clothing capability should bypass filtering.
	 */
	public function test_returns_null_for_clothing_manager(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_clothing_manager' ] );

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'User with manage_clothing capability should bypass filtering' );
	}

	/**
	 * Bestuur role (has all management caps) should bypass filtering.
	 */
	public function test_returns_null_for_bestuur_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_bestuur' ] );

		update_option(
			'rondo_age_group_access',
			[
				'rondo_bestuur' => [ 'senioren' ],
			]
		);

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'Bestuur user should bypass filtering (has management caps)' );
	}

	/**
	 * When no age-group config exists, should return null (no restriction).
	 */
	public function test_returns_null_when_no_config_exists(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'no_config_user' ] );

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'Should return null when no config exists' );
	}

	/**
	 * When user's role has an empty array in config, should return null (no restriction).
	 */
	public function test_returns_null_when_role_has_empty_config(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'empty_config_user' ] );

		update_option(
			'rondo_age_group_access',
			[
				'rondo_user' => [],
			]
		);

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'Should return null when role has empty array config' );
	}

	/**
	 * When user's role has configured age groups, should return them.
	 */
	public function test_returns_array_when_role_has_configured_age_groups(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'restricted_user' ] );

		update_option(
			'rondo_age_group_access',
			[
				'rondo_user' => [ 'senioren', 'junioren-a' ],
			]
		);

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertContains( 'senioren', $result );
		$this->assertContains( 'junioren-a', $result );
	}

	/**
	 * When user has multiple roles with different age-group configs,
	 * should return merged unique array.
	 */
	public function test_returns_merged_array_for_multi_role_user(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'multi_role_user' ] );

		// Add a second role (without management capabilities).
		$user = get_user_by( 'id', $user_id );
		// We use the same rondo_user role but simulate a second role config
		// by adding a custom role without management caps.
		$custom_role = 'rondo_test_role';
		add_role( $custom_role, 'Test Role', [ 'read' => true ] );
		$user->add_role( $custom_role );

		update_option(
			'rondo_age_group_access',
			[
				'rondo_user' => [ 'senioren', 'junioren-a' ],
				$custom_role => [ 'junioren-a', 'junioren-b' ],
			]
		);

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertIsArray( $result );
		// Should contain merged unique values: senioren, junioren-a, junioren-b
		$this->assertCount( 3, $result );
		$this->assertContains( 'senioren', $result );
		$this->assertContains( 'junioren-a', $result );
		$this->assertContains( 'junioren-b', $result );

		// Clean up custom role.
		remove_role( $custom_role );
	}

	/**
	 * Config stored as JSON string should be decoded properly.
	 */
	public function test_handles_json_string_config(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'json_config_user' ] );

		update_option(
			'rondo_age_group_access',
			json_encode(
				[
					'rondo_user' => [ 'senioren' ],
				]
			)
		);

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertIsArray( $result );
		$this->assertContains( 'senioren', $result );
	}

	/**
	 * When user's role is not in the config, should return null.
	 */
	public function test_returns_null_when_role_not_in_config(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'unconfigured_role_user' ] );

		update_option(
			'rondo_age_group_access',
			[
				'some_other_role' => [ 'senioren' ],
			]
		);

		$result = AccessControl::get_permitted_age_groups( $user_id );
		$this->assertNull( $result, 'Should return null when user role is not in config' );
	}

	/**
	 * The suppress flag should exist as a static property.
	 */
	public function test_suppress_flag_exists(): void {
		$this->assertFalse( AccessControl::$suppress_age_group_filter );

		AccessControl::$suppress_age_group_filter = true;
		$this->assertTrue( AccessControl::$suppress_age_group_filter );

		// Reset.
		AccessControl::$suppress_age_group_filter = false;
	}

	/**
	 * Returns null for user ID of 0 (not logged in).
	 */
	public function test_returns_null_for_no_user(): void {
		$result = AccessControl::get_permitted_age_groups( 0 );
		$this->assertNull( $result );
	}
}
