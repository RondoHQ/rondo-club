<?php

namespace Tests\Wpunit;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\Volunteer\VolunteerExemptionResolver;
use Tests\Support\RondoTestCase;

/**
 * Tests current-role date handling for volunteer status and exemptions.
 */
class VolunteerStatusTest extends RondoTestCase {

	/**
	 * Both supported work-history date representations must compare identically.
	 */
	public function test_current_position_comparison_supports_storage_and_wire_date_formats(): void {
		$today     = current_datetime();
		$yesterday = $today->modify( '-1 day' );
		$tomorrow  = $today->modify( '+1 day' );

		$this->assertFalse( VolunteerStatus::is_position_current( [ 'end_date' => $yesterday->format( 'Ymd' ) ] ) );
		$this->assertFalse( VolunteerStatus::is_position_current( [ 'end_date' => $yesterday->format( 'Y-m-d' ) ] ) );
		$this->assertTrue( VolunteerStatus::is_position_current( [ 'end_date' => $tomorrow->format( 'Ymd' ) ] ) );
		$this->assertTrue( VolunteerStatus::is_position_current( [ 'end_date' => $tomorrow->format( 'Y-m-d' ) ] ) );
	}

	/**
	 * A committee role ending today no longer grants an exemption, even though
	 * the native field layer returns its date in compact storage format.
	 */
	public function test_committee_role_ending_today_does_not_grant_exemption(): void {
		$person_id    = $this->createPerson( [ 'post_title' => 'Voormalig Commissielid' ] );
		$committee_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Jeugdcommissie',
			]
		);

		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'team'        => $committee_id,
					'entity_type' => 'commissie',
					'job_title'   => 'Jeugdbegeleider',
					'start_date'  => current_datetime()->modify( '-1 year' )->format( 'Y-m-d' ),
					'end_date'    => current_datetime()->format( 'Y-m-d' ),
					'is_current'  => false,
				],
			]
		);

		$stored_history = Fields::get_for_post( $person_id, 'work_history' );

		$this->assertMatchesRegularExpression( '/^\d{8}$/', $stored_history[0]['end_date'] );
		$this->assertFalse( VolunteerExemptionResolver::has_active_commissie( $person_id ) );
		$this->assertNull( VolunteerExemptionResolver::resolve( $person_id, '2026-2027' ) );
	}
}
