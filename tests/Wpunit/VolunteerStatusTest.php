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
	 * A first active staff role records the date used by volunteer onboarding.
	 */
	public function test_first_active_staff_role_sets_volunteer_since(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Nieuwe Trainer' ] );
		$team_id   = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC O11-1',
			]
		);

		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'team'        => $team_id,
					'entity_type' => 'team',
					'job_title'   => 'Trainer',
					'start_date'  => '2026-08-18',
					'end_date'    => '',
					'is_current'  => true,
				],
			]
		);
		( new VolunteerStatus() )->calculate_and_update_status( $person_id );

		$this->assertTrue( Fields::get_for_post( $person_id, 'huidig_vrijwilliger' ) );
		$this->assertSame( '20260818', Fields::get_for_post( $person_id, 'vrijwilliger_sinds' ) );
	}

	/**
	 * A new team-roster volunteer falls back to today when Sportlink has no start date.
	 */
	public function test_first_active_staff_role_without_date_uses_today(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Nieuwe Teammanager' ] );
		$team_id   = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC O12-1',
			]
		);

		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'team'        => $team_id,
					'entity_type' => 'team',
					'job_title'   => 'Teammanager',
					'start_date'  => '',
					'end_date'    => '',
					'is_current'  => true,
				],
			]
		);
		( new VolunteerStatus() )->calculate_and_update_status( $person_id );

		$this->assertTrue( Fields::get_for_post( $person_id, 'huidig_vrijwilliger' ) );
		$this->assertSame( current_datetime()->format( 'Ymd' ), Fields::get_for_post( $person_id, 'vrijwilliger_sinds' ) );
	}

	/**
	 * An existing source date is historical truth and must never be overwritten.
	 */
	public function test_existing_volunteer_since_is_preserved(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Bestaande Vrijwilliger' ] );
		$team_id   = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC O13-1',
			]
		);

		Fields::update_for_post( $person_id, 'vrijwilliger_sinds', '2020-07-01' );
		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'team'        => $team_id,
					'entity_type' => 'team',
					'job_title'   => 'Teammanager',
					'start_date'  => '2026-08-18',
					'end_date'    => '',
					'is_current'  => true,
				],
			]
		);
		( new VolunteerStatus() )->calculate_and_update_status( $person_id );

		$this->assertSame( '20200701', Fields::get_for_post( $person_id, 'vrijwilliger_sinds' ) );
	}

	/**
	 * Recalculation repairs an active volunteer whose start field is still empty.
	 */
	public function test_recalculation_backfills_missing_volunteer_since(): void {
		$person_id    = $this->createPerson( [ 'post_title' => 'Te Herstellen Vrijwilliger' ] );
		$committee_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Jeugdcommissie',
			]
		);

		update_post_meta( $person_id, 'huidig-vrijwilliger', '1' );
		update_post_meta( $person_id, 'work_history', 1 );
		update_post_meta( $person_id, 'work_history_0_team', $committee_id );
		update_post_meta( $person_id, 'work_history_0_entity_type', 'commissie' );
		update_post_meta( $person_id, 'work_history_0_job_title', 'Commissielid' );
		update_post_meta( $person_id, 'work_history_0_start_date', '20260810' );
		update_post_meta( $person_id, 'work_history_0_end_date', '' );
		update_post_meta( $person_id, 'work_history_0_is_current', '1' );

		( new VolunteerStatus() )->calculate_and_update_status( $person_id );

		$this->assertSame( '20260810', Fields::get_for_post( $person_id, 'vrijwilliger_sinds' ) );
	}

	/**
	 * An existing undated volunteer must not be presented as newly onboarded.
	 */
	public function test_existing_volunteer_without_source_date_stays_undated(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Bestaande Teammanager' ] );
		$team_id   = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC O14-1',
			]
		);

		update_post_meta( $person_id, 'huidig-vrijwilliger', '1' );
		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'team'        => $team_id,
					'entity_type' => 'team',
					'job_title'   => 'Teammanager',
					'start_date'  => '',
					'end_date'    => '',
					'is_current'  => true,
				],
			]
		);
		( new VolunteerStatus() )->calculate_and_update_status( $person_id );

		$this->assertSame( '', Fields::get_for_post( $person_id, 'vrijwilliger_sinds' ) );
	}

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
