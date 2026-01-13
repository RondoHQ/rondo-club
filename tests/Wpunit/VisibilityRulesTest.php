<?php

namespace Tests\Wpunit;

use Tests\Support\CaelisTestCase;
use PRM_Access_Control;
use PRM_Visibility;
use PRM_Workspace_Members;
use PRM_User_Roles;

/**
 * Tests for visibility rules - private, workspace, and shared access patterns.
 *
 * Tests the three visibility modes:
 * - private: Only author can access
 * - workspace: All workspace members can access
 * - shared: Direct shares override visibility restrictions
 */
class VisibilityRulesTest extends CaelisTestCase {

    /**
     * PRM_Access_Control instance for access checks.
     *
     * @var PRM_Access_Control
     */
    private PRM_Access_Control $access_control;

    protected function set_up(): void {
        parent::set_up();
        $this->access_control = new PRM_Access_Control();
    }

    /**
     * Helper: Create an approved Caelis user.
     *
     * @param array $args Optional user arguments.
     * @return int User ID.
     */
    protected function createApprovedUser( array $args = [] ): int {
        $user_id = $this->createCaelisUser( $args );
        // Mark user as approved
        update_user_meta( $user_id, PRM_User_Roles::APPROVAL_META_KEY, '1' );
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

        // Create the workspace_access term (normally done by taxonomies class hook)
        $term_slug = 'workspace-' . $workspace_id;
        $term_name = get_the_title( $workspace_id );
        wp_insert_term( $term_name, 'workspace_access', [ 'slug' => $term_slug ] );

        // Add owner as admin member
        PRM_Workspace_Members::add( $workspace_id, $owner_id, PRM_Workspace_Members::ROLE_ADMIN );

        return $workspace_id;
    }

    /**
     * Helper: Assign a person post to a workspace.
     *
     * @param int $post_id      Person post ID.
     * @param int $workspace_id Workspace post ID.
     */
    protected function assignToWorkspace( int $post_id, int $workspace_id ): void {
        $term_slug = 'workspace-' . $workspace_id;
        $term = get_term_by( 'slug', $term_slug, 'workspace_access' );

        if ( $term && ! is_wp_error( $term ) ) {
            wp_set_object_terms( $post_id, [ $term->term_id ], 'workspace_access' );
        }
    }

    // =========================================================================
    // Task 1: Test private visibility
    // =========================================================================

    public function test_private_visibility_author_can_access(): void {
        // Create two approved users
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_private' ] );

        // Create a person post authored by Alice
        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Private Person',
        ] );

        // Set visibility to private
        PRM_Visibility::set_visibility( $person_id, 'private' );

        // Alice can access (author always has access)
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $alice_id ),
            'Author should have access to their own private post'
        );
    }

    public function test_private_visibility_non_author_cannot_access(): void {
        // Create two approved users
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_priv2' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_priv2' ] );

        // Create a person post authored by Alice
        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Private Person 2',
        ] );

        // Set visibility to private
        PRM_Visibility::set_visibility( $person_id, 'private' );

        // Bob cannot access (private = author only)
        $this->assertFalse(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'Non-author should not have access to private post'
        );
    }

    public function test_get_visibility_returns_private(): void {
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_getvis' ] );

        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Test Get Visibility',
        ] );

        // Set visibility to private
        PRM_Visibility::set_visibility( $person_id, 'private' );

        // Verify get_visibility() returns 'private'
        $this->assertEquals(
            'private',
            PRM_Visibility::get_visibility( $person_id ),
            'get_visibility() should return private'
        );
    }

    public function test_default_visibility_is_private(): void {
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_default' ] );

        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Default Visibility Person',
        ] );

        // Do NOT set visibility - should default to private
        $this->assertEquals(
            'private',
            PRM_Visibility::get_visibility( $person_id ),
            'Default visibility should be private when not set'
        );
    }

    // =========================================================================
    // Task 2: Test workspace visibility
    // =========================================================================

    public function test_workspace_visibility_member_can_access(): void {
        // Create three approved users
        $alice_id   = $this->createApprovedUser( [ 'user_login' => 'alice_ws' ] );
        $bob_id     = $this->createApprovedUser( [ 'user_login' => 'bob_ws' ] );
        $charlie_id = $this->createApprovedUser( [ 'user_login' => 'charlie_ws' ] );

        // Create a workspace owned by Alice
        $workspace_id = $this->createWorkspace( $alice_id, [ 'post_title' => 'Team Workspace' ] );

        // Add Bob as member
        PRM_Workspace_Members::add( $workspace_id, $bob_id, PRM_Workspace_Members::ROLE_MEMBER );

        // Create a person post authored by Alice with workspace visibility
        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Workspace Person',
        ] );

        // Set visibility to workspace
        PRM_Visibility::set_visibility( $person_id, 'workspace' );

        // Assign to workspace
        $this->assignToWorkspace( $person_id, $workspace_id );

        // Alice can access (author)
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $alice_id ),
            'Author should have access to workspace post'
        );

        // Bob can access (workspace member)
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'Workspace member should have access to workspace post'
        );

        // Charlie cannot access (not a workspace member)
        $this->assertFalse(
            $this->access_control->user_can_access_post( $person_id, $charlie_id ),
            'Non-member should not have access to workspace post'
        );
    }

    public function test_workspace_visibility_viewer_role_can_access(): void {
        // Create users
        $alice_id  = $this->createApprovedUser( [ 'user_login' => 'alice_viewer' ] );
        $viewer_id = $this->createApprovedUser( [ 'user_login' => 'viewer_user' ] );

        // Create workspace
        $workspace_id = $this->createWorkspace( $alice_id, [ 'post_title' => 'Viewer Test Workspace' ] );

        // Add viewer as viewer role
        PRM_Workspace_Members::add( $workspace_id, $viewer_id, PRM_Workspace_Members::ROLE_VIEWER );

        // Create workspace-visible person
        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Viewer Test Person',
        ] );

        PRM_Visibility::set_visibility( $person_id, 'workspace' );
        $this->assignToWorkspace( $person_id, $workspace_id );

        // Viewer can access (read access)
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $viewer_id ),
            'Viewer role should have read access to workspace post'
        );
    }

    public function test_workspace_visibility_multiple_workspaces(): void {
        // Create users
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_multi' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_multi' ] );
        $dave_id  = $this->createApprovedUser( [ 'user_login' => 'dave_multi' ] );

        // Create two workspaces
        $workspace1_id = $this->createWorkspace( $alice_id, [ 'post_title' => 'Workspace One' ] );
        $workspace2_id = $this->createWorkspace( $alice_id, [ 'post_title' => 'Workspace Two' ] );

        // Bob is member of workspace1 only
        PRM_Workspace_Members::add( $workspace1_id, $bob_id, PRM_Workspace_Members::ROLE_MEMBER );

        // Dave is member of workspace2 only
        PRM_Workspace_Members::add( $workspace2_id, $dave_id, PRM_Workspace_Members::ROLE_MEMBER );

        // Create person assigned to both workspaces
        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Multi-Workspace Person',
        ] );

        PRM_Visibility::set_visibility( $person_id, 'workspace' );

        // Assign to both workspaces
        $term1 = get_term_by( 'slug', 'workspace-' . $workspace1_id, 'workspace_access' );
        $term2 = get_term_by( 'slug', 'workspace-' . $workspace2_id, 'workspace_access' );
        wp_set_object_terms( $person_id, [ $term1->term_id, $term2->term_id ], 'workspace_access' );

        // Bob can access (member of workspace1)
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'User in any assigned workspace should have access'
        );

        // Dave can access (member of workspace2)
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $dave_id ),
            'User in any assigned workspace should have access'
        );
    }

    // =========================================================================
    // Task 3: Test direct shares
    // =========================================================================

    public function test_direct_share_grants_access_to_private_post(): void {
        // Create users
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_share' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_share' ] );

        // Create private person post
        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Shared Private Person',
        ] );

        PRM_Visibility::set_visibility( $person_id, 'private' );

        // Before sharing, Bob cannot access
        $this->assertFalse(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'Non-author should not have access before sharing'
        );

        // Share with Bob
        PRM_Visibility::add_share( $person_id, $bob_id, 'view' );

        // After sharing, Bob can access
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'Shared user should have access despite private visibility'
        );

        // Alice can still access (author)
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $alice_id ),
            'Author should still have access'
        );
    }

    public function test_get_share_permission(): void {
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_perm' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_perm' ] );

        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Permission Test Person',
        ] );

        // Share with view permission
        PRM_Visibility::add_share( $person_id, $bob_id, 'view' );

        // Verify share permission
        $this->assertEquals(
            'view',
            PRM_Visibility::get_share_permission( $person_id, $bob_id ),
            'get_share_permission should return view'
        );
    }

    public function test_edit_share_permission(): void {
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_edit' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_edit' ] );

        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Edit Permission Person',
        ] );

        // Share with edit permission
        PRM_Visibility::add_share( $person_id, $bob_id, 'edit' );

        // Verify share permission
        $this->assertEquals(
            'edit',
            PRM_Visibility::get_share_permission( $person_id, $bob_id ),
            'get_share_permission should return edit'
        );

        // Bob can access
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'User with edit share should have access'
        );
    }

    public function test_remove_share_revokes_access(): void {
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_remove' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_remove' ] );

        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Remove Share Person',
        ] );

        PRM_Visibility::set_visibility( $person_id, 'private' );

        // Share with Bob
        PRM_Visibility::add_share( $person_id, $bob_id, 'view' );

        // Bob can access
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'Shared user should have access'
        );

        // Remove share
        PRM_Visibility::remove_share( $person_id, $bob_id );

        // Bob loses access
        $this->assertFalse(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'User should lose access after share is removed'
        );
    }

    public function test_user_has_share_returns_correct_boolean(): void {
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_has' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_has' ] );

        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Has Share Person',
        ] );

        // Before sharing
        $this->assertFalse(
            PRM_Visibility::user_has_share( $person_id, $bob_id ),
            'user_has_share should return false before sharing'
        );

        // After sharing
        PRM_Visibility::add_share( $person_id, $bob_id, 'view' );

        $this->assertTrue(
            PRM_Visibility::user_has_share( $person_id, $bob_id ),
            'user_has_share should return true after sharing'
        );

        // After removing share
        PRM_Visibility::remove_share( $person_id, $bob_id );

        $this->assertFalse(
            PRM_Visibility::user_has_share( $person_id, $bob_id ),
            'user_has_share should return false after share removal'
        );
    }

    public function test_share_overrides_workspace_visibility_for_non_member(): void {
        // Create users
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_wsshare' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_wsshare' ] );

        // Create workspace (Alice is member)
        $workspace_id = $this->createWorkspace( $alice_id, [ 'post_title' => 'Share Override Workspace' ] );

        // Create workspace-visible person
        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Workspace Share Person',
        ] );

        PRM_Visibility::set_visibility( $person_id, 'workspace' );
        $this->assignToWorkspace( $person_id, $workspace_id );

        // Bob is NOT a workspace member, cannot access
        $this->assertFalse(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'Non-member should not access workspace post'
        );

        // Share with Bob directly
        PRM_Visibility::add_share( $person_id, $bob_id, 'view' );

        // Now Bob can access via direct share
        $this->assertTrue(
            $this->access_control->user_can_access_post( $person_id, $bob_id ),
            'Non-member with direct share should have access'
        );
    }

    public function test_update_existing_share_permission(): void {
        $alice_id = $this->createApprovedUser( [ 'user_login' => 'alice_update' ] );
        $bob_id   = $this->createApprovedUser( [ 'user_login' => 'bob_update' ] );

        $person_id = $this->createPerson( [
            'post_author' => $alice_id,
            'post_title'  => 'Update Share Person',
        ] );

        // Share with view permission
        PRM_Visibility::add_share( $person_id, $bob_id, 'view' );

        $this->assertEquals(
            'view',
            PRM_Visibility::get_share_permission( $person_id, $bob_id ),
            'Initial permission should be view'
        );

        // Update to edit permission (add_share updates existing)
        PRM_Visibility::add_share( $person_id, $bob_id, 'edit' );

        $this->assertEquals(
            'edit',
            PRM_Visibility::get_share_permission( $person_id, $bob_id ),
            'Permission should be updated to edit'
        );
    }
}
