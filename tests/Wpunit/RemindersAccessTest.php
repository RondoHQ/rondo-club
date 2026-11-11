<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\REST\Reminders;
use Tests\Support\RondoTestCase;

/**
 * The /reminders route returns full birthdates, so it must carry the
 * jubilarissen section gate (like /anniversaries) and scope its results
 * through AccessControl instead of returning every member to any
 * logged-in user.
 */
class RemindersAccessTest extends RondoTestCase {
	private string $role;
	private int $user_id;

	protected function set_up(): void {
		parent::set_up();
		$this->role    = UserRoles::add_custom_role( 'Reminders test coordinator' );
		$this->user_id = $this->createRondoUser( [ 'role' => $this->role ] );
		update_option( 'rondo_age_group_access', [ $this->role => [ 'Onder 11' ] ] );
		delete_option( 'rondo_team_access' );
		wp_set_current_user( $this->user_id );
		$this->bootRestControllers( [ Reminders::class ] );
	}

	protected function tear_down(): void {
		UserRoles::remove_custom_role( $this->role );
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_team_access' );
		parent::tear_down();
	}

	private function request( string $route ): \WP_REST_Response {
		return rest_do_request( new \WP_REST_Request( 'GET', $route ) );
	}

	private function grant( string $cap ): void {
		get_role( $this->role )->add_cap( $cap );
		UserRoles::sync_role_capabilities( $this->role );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
	}

	private function createBirthdayPerson( string $title, string $age_group ): int {
		$person_id = $this->createPerson( [ 'post_title' => $title ], [ 'leeftijdsgroep' => $age_group ] );
		// Raw meta write for the birthdate, matching how BirthdayRemindersTest
		// seeds it: today, twenty years back, in the canonical compact shape.
		update_post_meta( $person_id, 'birthdate', ( new \DateTime( 'today', wp_timezone() ) )->modify( '-20 years' )->format( 'Ymd' ) );
		return $person_id;
	}

	public function test_reminders_requires_jubilarissen_section(): void {
		$this->assertSame( 403, $this->request( '/rondo/v1/reminders' )->get_status() );

		$this->grant( 'jubilarissen' );
		$this->assertSame( 200, $this->request( '/rondo/v1/reminders' )->get_status() );
	}

	public function test_reminders_results_are_scoped_to_permitted_age_groups(): void {
		$this->grant( 'jubilarissen' );
		$visible_id = $this->createBirthdayPerson( 'Visible Kid', 'Onder 11' );
		$hidden_id  = $this->createBirthdayPerson( 'Hidden Senior', 'Senioren' );

		$response = $this->request( '/rondo/v1/reminders?days_ahead=365' );
		$this->assertSame( 200, $response->get_status() );
		$ids = array_map( 'intval', wp_list_pluck( $response->get_data(), 'id' ) );
		$this->assertContains( $visible_id, $ids );
		$this->assertNotContains( $hidden_id, $ids );
	}
}
