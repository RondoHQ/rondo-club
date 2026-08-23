<?php

namespace Tests\Wpunit;

use Rondo\Volunteer\VolunteerEligibilityService;
use Tests\Support\RondoTestCase;

/**
 * Tests for the volunteer dashboard's data-quality scope.
 */
class VolunteerDataQualityTest extends RondoTestCase {

	private VolunteerEligibilityService $service;

	protected function set_up(): void {
		parent::set_up();
		VolunteerEligibilityService::invalidate_cache();
		$this->service = new VolunteerEligibilityService();
	}

	private function person( string $name, array $meta = [] ): int {
		$person_id = $this->createPerson( [ 'post_title' => $name ] );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $person_id, $key, $value );
		}
		return $person_id;
	}

	public function test_invalidation_advances_the_cache_generation(): void {
		$season    = '2026-2027';
		$cache_key = VolunteerEligibilityService::cache_key( $season );
		set_transient( $cache_key, [ 'sentinel' => true ], MINUTE_IN_SECONDS );

		VolunteerEligibilityService::invalidate_cache();

		$this->assertNotSame( $cache_key, VolunteerEligibilityService::cache_key( $season ) );
		$this->assertSame( [ 'sentinel' => true ], get_transient( $cache_key ) );
	}

	public function test_only_playing_members_without_an_age_group_are_reported(): void {
		$parent_id            = $this->person(
			'Niet-spelende ouder',
			[
				'isparent' => '1',
				'type-lid' => 'Ouder',
			]
		);
		$sponsor_id           = $this->person(
			'Sponsorcontact',
			[
				'person_type' => 'contact',
				'is_sponsor'  => '1',
			]
		);
		$player_id            = $this->person( 'Speler', [ 'spelactiviteit' => 'Veldvoetbal' ] );
		$dual_role_sponsor_id = $this->person(
			'Spelende sponsor',
			[
				'is_sponsor'     => '1',
				'spelactiviteit' => 'Veldvoetbal',
			]
		);
		$classified_player_id = $this->person(
			'Ingedelde speler',
			[
				'spelactiviteit' => 'Veldvoetbal',
				'leeftijdsgroep' => 'Senioren',
			]
		);
		$former_player_id     = $this->person(
			'Oud-speler',
			[
				'former_member'  => '1',
				'spelactiviteit' => 'Veldvoetbal',
			]
		);

		$reported = $this->service->get_skipped_no_leeftijdsgroep_ids();

		$this->assertContains( $player_id, $reported );
		$this->assertContains( $dual_role_sponsor_id, $reported );
		$this->assertNotContains( $parent_id, $reported );
		$this->assertNotContains( $sponsor_id, $reported );
		$this->assertNotContains( $classified_player_id, $reported );
		$this->assertNotContains( $former_player_id, $reported );
	}

	public function test_dashboard_diagnostics_match_the_drill_down_and_have_no_former_member_count(): void {
		$this->person( 'Niet-spelende ouder', [ 'isparent' => '1' ] );
		$this->person( 'Speler zonder groep', [ 'spelactiviteit' => 'Veldvoetbal' ] );

		$view     = $this->service->get_eligibility_view();
		$reported = $this->service->get_skipped_no_leeftijdsgroep_ids();

		$this->assertSame( count( $reported ), $view['diagnostics']['skipped_no_leeftijdsgroep'] );
		$this->assertArrayNotHasKey( 'skipped_former_members', $view['diagnostics'] );
	}
}
