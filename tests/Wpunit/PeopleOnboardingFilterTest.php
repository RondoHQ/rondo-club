<?php

namespace Tests\Wpunit;

use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/**
 * Covers the mutually exclusive member and volunteer onboarding cohorts.
 */
class PeopleOnboardingFilterTest extends RondoTestCase {

	private People $controller;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new People();
	}

	/**
	 * @return int[]
	 */
	private function onboarding_ids( string $filter ): array {
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'ownership', 'all' );
		$request->set_param( 'orderby', 'first_name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( $filter, '1' );

		$data = $this->controller->get_filtered_people( $request )->get_data();
		return array_map( 'intval', array_column( $data['people'], 'id' ) );
	}

	public function test_new_volunteers_are_excluded_from_member_onboarding(): void {
		$recent_member_date    = current_datetime()->modify( '-5 days' )->format( 'Y-m-d' );
		$recent_volunteer_date = current_datetime()->modify( '-10 days' )->format( 'Y-m-d' );
		$old_volunteer_date    = current_datetime()->modify( '-61 days' )->format( 'Y-m-d' );

		$member_only_id           = $this->createPerson(
			[ 'post_title' => 'New member only' ],
			[ 'lid_sinds' => $recent_member_date ]
		);
		$new_volunteer_id         = $this->createPerson(
			[ 'post_title' => 'New member and volunteer' ],
			[
				'lid_sinds'           => $recent_member_date,
				'huidig_vrijwilliger' => true,
				'vrijwilliger_sinds'  => $recent_volunteer_date,
			]
		);
		$established_volunteer_id = $this->createPerson(
			[ 'post_title' => 'New member established volunteer' ],
			[
				'lid_sinds'           => $recent_member_date,
				'huidig_vrijwilliger' => true,
				'vrijwilliger_sinds'  => $old_volunteer_date,
			]
		);
		$undated_volunteer_id     = $this->createPerson(
			[ 'post_title' => 'New member undated volunteer' ],
			[
				'lid_sinds'           => $recent_member_date,
				'huidig_vrijwilliger' => true,
			]
		);

		$member_ids    = $this->onboarding_ids( 'onboarding_new_members' );
		$volunteer_ids = $this->onboarding_ids( 'onboarding_new_volunteers' );

		$this->assertContains( $member_only_id, $member_ids );
		$this->assertNotContains( $new_volunteer_id, $member_ids );
		$this->assertContains( $new_volunteer_id, $volunteer_ids );
		$this->assertNotContains( $established_volunteer_id, $member_ids );
		$this->assertNotContains( $established_volunteer_id, $volunteer_ids );
		$this->assertNotContains( $undated_volunteer_id, $member_ids );
		$this->assertNotContains( $undated_volunteer_id, $volunteer_ids );
	}

	public function test_sent_new_volunteer_does_not_fall_back_to_member_onboarding(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Onboarded volunteer' ],
			[
				'lid_sinds'                          => current_datetime()->modify( '-5 days' )->format( 'Y-m-d' ),
				'huidig_vrijwilliger'                => true,
				'vrijwilliger_sinds'                 => current_datetime()->modify( '-10 days' )->format( 'Y-m-d' ),
				'onboarding_email_vrijwilliger_sent' => current_datetime()->format( DATE_RFC3339 ),
			]
		);

		$this->assertNotContains( $person_id, $this->onboarding_ids( 'onboarding_new_members' ) );
		$this->assertNotContains( $person_id, $this->onboarding_ids( 'onboarding_new_volunteers' ) );
	}

	public function test_onboarding_windows_use_compact_storage_dates(): void {
		$old_member_id    = $this->createPerson(
			[ 'post_title' => 'Member outside window' ],
			[ 'lid_sinds' => current_datetime()->modify( '-31 days' )->format( 'Y-m-d' ) ]
		);
		$old_volunteer_id = $this->createPerson(
			[ 'post_title' => 'Volunteer outside window' ],
			[
				'huidig_vrijwilliger' => true,
				'vrijwilliger_sinds'  => current_datetime()->modify( '-61 days' )->format( 'Y-m-d' ),
			]
		);

		$this->assertNotContains( $old_member_id, $this->onboarding_ids( 'onboarding_new_members' ) );
		$this->assertNotContains( $old_volunteer_id, $this->onboarding_ids( 'onboarding_new_volunteers' ) );
	}
}
