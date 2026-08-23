<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Volunteer\VolunteerExemptionResolver;
use Rondo\Volunteer\VolunteerEligibilityService;
use Tests\Support\RondoTestCase;

/**
 * Tests for VolunteerEligibilityService::get_eligible_units_for_person().
 *
 * The rule under test: an O17+ player who is also the parent of a JO16- player owes
 * BOTH the spelersplicht and the ouderplicht. The speler unit is always listed first,
 * because completed shifts fill that duty before spilling into the gezin duty.
 */
class VolunteerObligationUnitsTest extends RondoTestCase {

	private const TYPE_PARENT = 2;
	private const TYPE_CHILD  = 3;

	private VolunteerEligibilityService $service;

	protected function set_up(): void {
		parent::set_up();
		$this->service = new VolunteerEligibilityService();
	}

	/**
	 * Create a person with a leeftijdsgroep. Null means "no spelactiviteit" — the
	 * shape of a non-member parent record.
	 */
	private function person( ?string $leeftijdsgroep, string $name = 'Test Persoon' ): int {
		$person_id = $this->createPerson( [ 'post_title' => $name ] );
		if ( $leeftijdsgroep !== null ) {
			update_post_meta( $person_id, 'leeftijdsgroep', $leeftijdsgroep );
		}
		return $person_id;
	}

	/**
	 * Wire a parent↔child relationship in both directions, as the CPT stores it.
	 */
	private function link_parent_child( int $parent_id, int $child_id ): void {
		$parent_rels   = \Rondo\Fields\Fields::get_for_post( $parent_id, 'relationships' ) ?: [];
		$parent_rels[] = [
			'related_person'    => $child_id,
			'relationship_type' => self::TYPE_CHILD,
		];
		\Rondo\Fields\Fields::update_for_post( $parent_id, 'relationships', $parent_rels );

		$child_rels   = \Rondo\Fields\Fields::get_for_post( $child_id, 'relationships' ) ?: [];
		$child_rels[] = [
			'related_person'    => $parent_id,
			'relationship_type' => self::TYPE_PARENT,
		];
		\Rondo\Fields\Fields::update_for_post( $child_id, 'relationships', $child_rels );
	}

	/** @return string[] */
	private function kinds( array $units ): array {
		return array_map( fn( $unit ) => $unit['kind'], $units );
	}

	public function test_adult_player_without_children_owes_only_the_speler_duty(): void {
		$person_id = $this->person( 'Senioren' );

		$units = $this->service->get_eligible_units_for_person( $person_id );

		$this->assertSame( [ 'speler' ], $this->kinds( $units ) );
		$this->assertSame( 2, $units[0]['required_count'] );
	}

	public function test_youth_player_owes_nothing_themselves(): void {
		$person_id = $this->person( 'Onder 12' );

		$this->assertSame( [], $this->service->get_eligible_units_for_person( $person_id ) );
	}

	public function test_non_playing_parent_owes_only_the_gezin_duty(): void {
		$parent_id = $this->person( null, 'Ouder' );
		$child_id  = $this->person( 'Onder 12', 'Kind' );
		$this->link_parent_child( $parent_id, $child_id );

		$units = $this->service->get_eligible_units_for_person( $parent_id );

		$this->assertSame( [ 'gezin' ], $this->kinds( $units ) );
		$this->assertSame( 2, $units[0]['required_count'] );
	}

	public function test_trainer_coach_parent_exempts_the_shared_family_duty(): void {
		$first_parent   = $this->person( null, 'Eerste ouder' );
		$trainer_parent = $this->person( null, 'Trainer ouder' );
		$child          = $this->person( 'Onder 10', 'Kind' );
		$this->link_parent_child( $first_parent, $child );
		$this->link_parent_child( $trainer_parent, $child );
		Fields::update_for_post(
			$trainer_parent,
			'work_history',
			[
				[
					'team'        => 123,
					'entity_type' => 'team',
					'job_title'   => 'Trainer/coach',
					'is_current'  => true,
				],
			]
		);

		$units = $this->service->get_eligible_units_for_person( $first_parent );
		$match = VolunteerExemptionResolver::resolve_unit( $units[0], '2026-2027' );

		$this->assertContains( $trainer_parent, $units[0]['person_ids'] );
		$this->assertSame(
			[
				'person_id' => $trainer_parent,
				'reason'    => VolunteerExemptionResolver::REASON_STAFF,
			],
			$match
		);
	}

	public function test_playing_parent_owes_both_duties_speler_first(): void {
		$parent_id = $this->person( 'Senioren', 'Spelende ouder' );
		$child_id  = $this->person( 'Onder 12', 'Kind' );
		$this->link_parent_child( $parent_id, $child_id );

		$units = $this->service->get_eligible_units_for_person( $parent_id );

		$this->assertSame( [ 'speler', 'gezin' ], $this->kinds( $units ), 'Speler duty must be attributed first' );
		$this->assertSame( 2, $units[0]['required_count'], 'Own spelersplicht' );
		$this->assertSame( 2, $units[1]['required_count'], 'Gezinsplicht for one child' );
	}

	public function test_playing_parent_of_two_youths_gets_multi_child_scaling(): void {
		$parent_id = $this->person( 'Veteranen', 'Spelende ouder' );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 12', 'Kind 1' ) );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 9', 'Kind 2' ) );

		$units = $this->service->get_eligible_units_for_person( $parent_id );

		$this->assertSame( [ 'speler', 'gezin' ], $this->kinds( $units ) );
		// Kid 1 = 2, kid 2 = 1.5 → floor(3.5) = 3.
		$this->assertSame( 3, $units[1]['required_count'] );
		$this->assertSame( 2, $units[1]['child_count'] );
	}

	public function test_adult_whose_children_have_aged_out_owes_nothing(): void {
		$parent_id = $this->person( null, 'Ouder' );
		$child_id  = $this->person( 'Senioren', 'Volwassen kind' );
		$this->link_parent_child( $parent_id, $child_id );

		$this->assertSame( [], $this->service->get_eligible_units_for_person( $parent_id ) );
	}

	/**
	 * The deprecated singular accessor still returns exactly what it always did.
	 * VolunteerFineGenerator depends on this shape.
	 */
	public function test_singular_accessor_is_unchanged_for_a_playing_parent(): void {
		$parent_id = $this->person( 'Senioren', 'Spelende ouder' );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 12', 'Kind' ) );

		$unit = $this->service->get_eligible_unit_for_person( $parent_id );

		$this->assertNotNull( $unit );
		$this->assertSame( 'speler', $unit['kind'] );
	}

	public function test_singular_accessor_still_returns_gezin_for_a_non_playing_parent(): void {
		$parent_id = $this->person( null, 'Ouder' );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 12', 'Kind' ) );

		$unit = $this->service->get_eligible_unit_for_person( $parent_id );

		$this->assertNotNull( $unit );
		$this->assertSame( 'gezin', $unit['kind'] );
	}

	public function test_any_active_person_may_volunteer_even_without_an_obligation(): void {
		$sponsor_id = $this->person( null, 'Sponsor' );

		$this->assertSame( [], $this->service->get_eligible_units_for_person( $sponsor_id ) );
		$this->assertTrue(
			$this->service->may_volunteer( $sponsor_id ),
			'Owing nothing must not stop someone from helping out'
		);
	}

	public function test_former_member_may_volunteer(): void {
		$person_id = $this->person( 'Senioren', 'Oud-lid' );
		update_post_meta( $person_id, 'former_member', '1' );

		$this->assertTrue(
			$this->service->may_volunteer( $person_id ),
			'Former-member status must not hide tasks from a current parent or willing helper'
		);
	}
}
