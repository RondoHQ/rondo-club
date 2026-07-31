<?php

namespace Tests\Wpunit;

use RONDO_Access_Control;
use RONDO_User_Roles;
use Tests\Support\RondoTestCase;

/**
 * What survives of the old workspace/approval test suite.
 *
 * Workspaces (`RONDO_Workspace_Members`), the visibility taxonomy
 * (`RONDO_Visibility`) and the per-post permission levels
 * (`AccessControl::get_user_permission()`, `get_accessible_post_ids()`) were all
 * removed from the product. The 21 tests covering them could not pass — they
 * could not even load the classes — and have been dropped rather than left red.
 *
 * Access is now decided by capability plus person visibility; see
 * `AgeGroupAccessTest`, `PersonVisibilityTest` and `PersonFieldSensitivityTest`.
 * These two kept their meaning through that change.
 */
class WorkspacePermissionsTest extends RondoTestCase {

	private RONDO_Access_Control $access_control;
	private string $test_id;

	protected function set_up(): void {
		parent::set_up();
		$this->access_control = new RONDO_Access_Control();
		$this->test_id        = wp_generate_password( 6, false );
	}

	/**
	 * `is_user_approved()` outlived the approval system it was named for: it now
	 * answers "is this a real user", and administrators must pass it.
	 */
	public function test_admin_always_approved(): void {
		$admin_id = self::factory()->user->create( [ 'user_login' => 'test_admin_' . $this->test_id ] );

		// The user_register hook forces the rondo_user role, so set admin explicitly.
		$admin = new \WP_User( $admin_id );
		$admin->set_role( 'administrator' );
		clean_user_cache( $admin_id );

		$this->assertTrue( user_can( $admin_id, 'manage_options' ), 'Administrator should have manage_options' );
		$this->assertTrue(
			RONDO_User_Roles::is_user_approved( $admin_id ),
			'Administrator should be treated as a valid user'
		);
	}

	/**
	 * Access no longer hinges on authorship or an approval flag. What decides
	 * whether a plain member reaches a person record is whether it is in their
	 * own household — and a member with no linked person has none.
	 */
	public function test_member_without_a_linked_person_reaches_no_person_records(): void {
		$user_id   = $this->createRondoUser( [ 'user_login' => 'transition_' . $this->test_id ] );
		$person_id = $this->createPerson( [ 'post_author' => $user_id ] );

		$this->assertFalse(
			$this->access_control->user_can_access_post( $person_id, $user_id ),
			'Authoring a person record does not put it in your household'
		);
	}
}
