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

	public function test_assistant_trainer_coach_parent_exempts_the_shared_family_duty(): void {
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
					'job_title'   => 'Assistent-trainer/coach',
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

	public function test_child_referee_exempts_both_parents_and_siblings_including_profile_response(): void {
		$first_parent  = $this->person( null, 'Eerste ouder' );
		$second_parent = $this->person( null, 'Tweede ouder' );
		$referee       = $this->person( 'Onder 15', 'Jeugdscheidsrechter' );
		$sibling       = $this->person( 'Onder 10', 'Broer of zus' );
		foreach ( [ $first_parent, $second_parent ] as $parent ) {
			$this->link_parent_child( $parent, $referee );
			$this->link_parent_child( $parent, $sibling );
		}
		$this->set_child_role( $referee, 'Verenigingsscheidsrechter' );

		$server = $this->bootRestControllers( [ \Rondo\REST\MemberShifts::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		foreach ( [ $first_parent, $second_parent, $referee, $sibling ] as $person_id ) {
			$response = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/people/' . $person_id . '/shifts' ) );
			$this->assertSame( 200, $response->get_status() );
			$obligations = $response->get_data()['obligations'];
			$this->assertCount( 1, $obligations );
			$this->assertSame( 'gezin', $obligations[0]['kind'] );
			$this->assertSame( $referee, $obligations[0]['exemption']['person_id'] );
			$this->assertSame( 'staff', $obligations[0]['exemption']['reason'] );
		}

		$units = $this->service->get_eligible_units_for_person( $first_parent );
		$this->assertSame( 2, $units[0]['child_count'] );
		$partition = VolunteerExemptionResolver::partition_units( $units, '2026-2027' );
		$this->assertSame( 0, $partition['required_count'] );
		$this->assertCount( 1, $partition['exempt'] );
	}

	public function test_any_child_volunteer_role_exempts_family_but_not_parents_own_player_duty(): void {
		$parent = $this->person( 'Senioren', 'Spelende ouder' );
		$child  = $this->person( 'Onder 12', 'Vrijwilliger' );
		$this->link_parent_child( $parent, $child );
		$units = $this->service->get_eligible_units_for_person( $parent );

		foreach ( [ 'Materiaalbeheerder', 'Assistent-trainer/coach', 'Verenigingsscheidsrechter' ] as $role ) {
			$this->set_child_role( $child, $role );
			$this->assertNull( VolunteerExemptionResolver::resolve_unit( $units[0], '2026-2027' ), $role );
			$this->assertSame( $child, VolunteerExemptionResolver::resolve_unit( $units[1], '2026-2027' )['person_id'], $role );
		}

		$this->set_child_role( $child, 'Lid', [ 'entity_type' => 'commissie' ] );
		$this->assertSame( 'commissie', VolunteerExemptionResolver::resolve_unit( $units[1], '2026-2027' )['reason'] );
	}

	public function test_inactive_future_player_and_honorary_child_roles_do_not_exempt_family(): void {
		$parent = $this->person( null );
		$child  = $this->person( 'Onder 12' );
		$this->link_parent_child( $parent, $child );
		$unit = $this->service->get_eligible_units_for_person( $parent )[0];

		foreach ( [ 'Verenigingsscheidsrechter', 'Materiaalbeheerder' ] as $role ) {
			foreach ( [ [ 'end_date' => '2020-01-01' ], [ 'start_date' => '2099-01-01' ], [ 'is_current' => false ] ] as $inactive ) {
				$this->set_child_role( $child, $role, $inactive );
				$this->assertNull( VolunteerExemptionResolver::resolve_unit( $unit, '2026-2027' ) );
			}
		}
		foreach ( [ 'Teamspeler', 'Donateur', 'Erelid' ] as $role ) {
			$this->set_child_role( $child, $role );
			$this->assertNull( VolunteerExemptionResolver::resolve_unit( $unit, '2026-2027' ), $role );
		}
	}

	public function test_child_personal_exemption_and_cached_volunteer_flag_do_not_transfer_to_parents(): void {
		$parent = $this->person( null );
		$child  = $this->person( 'Onder 12' );
		$this->link_parent_child( $parent, $child );
		Fields::update_for_post( $child, 'vrijgesteld_handmatig', true );
		Fields::update_for_post( $child, 'betaalde_vrijwilliger', true );
		Fields::update_for_post( $child, 'huidig_vrijwilliger', true );
		$unit = $this->service->get_eligible_units_for_person( $parent )[0];
		$this->assertNull( VolunteerExemptionResolver::resolve_unit( $unit, '2026-2027' ) );

		// An orphan unit still retains the child's own personal exemption.
		$unit['person_ids'] = [ $child ];
		$this->assertSame( 'betaald', VolunteerExemptionResolver::resolve_unit( $unit, '2026-2027' )['reason'] );
	}

	public function test_child_role_does_not_exempt_another_family_or_a_stale_person_reference(): void {
		$parent = $this->person( null );
		$child  = $this->person( 'Onder 12' );
		$other  = $this->person( 'Onder 12' );
		$this->link_parent_child( $parent, $child );
		$this->set_child_role( $other, 'Verenigingsscheidsrechter' );
		$unit                         = $this->service->get_eligible_units_for_person( $parent )[0];
		$unit['trigger_person_ids'][] = $other;
		$this->assertNull( VolunteerExemptionResolver::resolve_unit( $unit, '2026-2027' ) );

		$this->set_child_role( $child, 'Verenigingsscheidsrechter' );
		Fields::update_for_post( $child, 'relationships', [] );
		$this->assertNotFalse( wp_trash_post( $child ) );
		$this->assertNull( VolunteerExemptionResolver::resolve_unit( $unit, '2026-2027' ) );
	}

	private function set_child_role( int $child, string $role, array $overrides = [] ): void {
		Fields::update_for_post(
			$child,
			'work_history',
			[
				array_merge(
					[
						'entity_type' => 'team',
						'job_title'   => $role,
						'is_current'  => true,
					],
					$overrides
				),
			]
		);
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
	 * Volunteer integrations depend on this shape.
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
