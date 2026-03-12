<?php

namespace Tests\Wpunit;

use Rondo\Core\RoleFinder;
use Tests\Support\RondoTestCase;

class RoleFinderTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();

		// Ensure the person post type is registered.
		new \RONDO_Post_Types();
	}

	/**
	 * Test that users with a matching current work_history role are returned.
	 */
	public function test_returns_user_ids_with_matching_current_role(): void {
		// Create a person with a current "secretaris" role.
		$person_id = $this->createPerson( [ 'post_title' => 'Jan Secretaris' ] );
		update_field(
			'work_history',
			[
				[
					'job_title'  => 'Secretaris',
					'is_current' => true,
					'start_date' => '',
					'end_date'   => '',
				],
			],
			$person_id
		);

		// Create a user linked to this person.
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );

		$result = RoleFinder::get_user_ids_by_role( 'Secretaris' );

		$this->assertContains( $user_id, $result );
	}

	/**
	 * Test that case-sensitive matching excludes wrong casing.
	 */
	public function test_case_sensitive_matching(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Piet Penningmeester' ] );
		update_field(
			'work_history',
			[
				[
					'job_title'  => 'Penningmeester Bestuur',
					'is_current' => true,
					'start_date' => '',
					'end_date'   => '',
				],
			],
			$person_id
		);

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );

		// Search with correct casing — should match.
		$result = RoleFinder::get_user_ids_by_role( 'Penningmeester' );
		$this->assertContains( $user_id, $result );

		// Search with wrong casing — should NOT match (falls back to admins).
		$admin_id     = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$result_lower = RoleFinder::get_user_ids_by_role( 'penningmeester' );
		$this->assertNotContains( $user_id, $result_lower );
		$this->assertContains( $admin_id, $result_lower );
	}

	/**
	 * Test that substring matching does not match unrelated roles.
	 */
	public function test_does_not_match_wedstrijdsecretaris(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Wedstrijdsecretaris' ] );
		update_field(
			'work_history',
			[
				[
					'job_title'  => 'Wedstrijdsecretaris algemeen',
					'is_current' => true,
					'start_date' => '',
					'end_date'   => '',
				],
			],
			$person_id
		);

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		// "Secretaris" should NOT match "Wedstrijdsecretaris" (case-sensitive, "s" != "S").
		$result = RoleFinder::get_user_ids_by_role( 'Secretaris' );
		$this->assertNotContains( $user_id, $result );
		$this->assertContains( $admin_id, $result );
	}

	/**
	 * Test that expired work_history entries are excluded.
	 */
	public function test_expired_work_history_entries_excluded(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Oud Secretaris' ] );
		update_field(
			'work_history',
			[
				[
					'job_title'  => 'Secretaris',
					'is_current' => false,
					'start_date' => '2020-01-01',
					'end_date'   => '2021-12-31', // Expired.
				],
			],
			$person_id
		);

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );

		// Create an admin so the fallback works.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$result = RoleFinder::get_user_ids_by_role( 'Secretaris' );

		// Should NOT contain the user with expired role.
		$this->assertNotContains( $user_id, $result );
		// Should fall back to admin.
		$this->assertContains( $admin_id, $result );
	}

	/**
	 * Test fallback to administrator IDs when no matching role users exist.
	 */
	public function test_falls_back_to_administrators(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		// No users with linked persons having the requested role.
		$result = RoleFinder::get_user_ids_by_role( 'Secretaris' );

		$this->assertContains( $admin_id, $result );
	}

	/**
	 * Test that a different keyword (Penningmeester) works for role lookup.
	 */
	public function test_different_keyword_works(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Klaas Geld' ] );
		update_field(
			'work_history',
			[
				[
					'job_title'  => 'Penningmeester',
					'is_current' => true,
					'start_date' => '',
					'end_date'   => '',
				],
			],
			$person_id
		);

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );

		$result = RoleFinder::get_user_ids_by_role( 'Penningmeester' );
		$this->assertContains( $user_id, $result );

		// The same user should NOT appear for a different keyword.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$result2  = RoleFinder::get_user_ids_by_role( 'Secretaris' );
		$this->assertNotContains( $user_id, $result2 );
		$this->assertContains( $admin_id, $result2 );
	}

	/**
	 * Test that users without a linked person are ignored.
	 */
	public function test_users_without_linked_person_ignored(): void {
		// Create a user with a linked person ID that doesn't exist.
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', 0 );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$result = RoleFinder::get_user_ids_by_role( 'Secretaris' );

		$this->assertNotContains( $user_id, $result );
		$this->assertContains( $admin_id, $result );
	}

	/**
	 * Test date-range based current detection (no is_current flag, within date range).
	 */
	public function test_date_range_current_detection(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Active By Dates' ] );
		update_field(
			'work_history',
			[
				[
					'job_title'  => 'Secretaris',
					'is_current' => false,
					'start_date' => '2020-01-01',
					'end_date'   => '2099-12-31', // Far future — still current.
				],
			],
			$person_id
		);

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );

		$result = RoleFinder::get_user_ids_by_role( 'Secretaris' );

		$this->assertContains( $user_id, $result );
	}
}
