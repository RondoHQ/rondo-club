<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\REST\Reminders;
use Tests\Support\RondoTestCase;

/**
 * The /reminders route returns full birthdates. It stays available to
 * logged-in members, but its results must be scoped through AccessControl:
 * a user only receives reminders for people they may view, instead of the
 * whole membership.
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

	private function request( array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/reminders' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	private function createBirthdayPerson( string $title, string $age_group ): int {
		$person_id = $this->createPerson( [ 'post_title' => $title ], [ 'leeftijdsgroep' => $age_group ] );
		// Raw meta write for the birthdate, matching how BirthdayRemindersTest
		// seeds it: today, twenty years back, in the canonical compact shape.
		update_post_meta( $person_id, 'birthdate', ( new \DateTime( 'today', wp_timezone() ) )->modify( '-20 years' )->format( 'Ymd' ) );
		return $person_id;
	}

	public function test_reminders_results_are_scoped_to_permitted_age_groups(): void {
		$visible_id = $this->createBirthdayPerson( 'Visible Kid', 'Onder 11' );
		$hidden_id  = $this->createBirthdayPerson( 'Hidden Senior', 'Senioren' );

		$response = $this->request( [ 'days_ahead' => 365 ] );
		$this->assertSame( 200, $response->get_status() );
		$ids = array_map( 'intval', wp_list_pluck( $response->get_data(), 'id' ) );
		$this->assertContains( $visible_id, $ids );
		$this->assertNotContains( $hidden_id, $ids );
	}

	public function test_plain_member_receives_no_other_members_birthdates(): void {
		$this->createBirthdayPerson( 'Some Member', 'Onder 11' );
		$this->createBirthdayPerson( 'Other Member', 'Senioren' );

		wp_set_current_user( $this->createRondoUser() );
		$response = $this->request( [ 'days_ahead' => 365 ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data(), 'a plain member has no linked person here, so nobody is in their scope' );
	}
}
