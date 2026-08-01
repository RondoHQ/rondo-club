<?php

namespace Tests\Wpunit;

use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Rondo\Volunteer\ShiftCancellationService;
use Tests\Support\RondoTestCase;

/**
 * Tests for shift attribution in VolunteerObligationCalculator.
 *
 * Every completed shift must count toward exactly ONE unit. A playing parent fills
 * their own spelersplicht first, then spills into the gezinsplicht — so someone who
 * owes 2 + 3 is only done after 5 shifts, not 3.
 */
class VolunteerShiftAttributionTest extends RondoTestCase {

	private const TYPE_PARENT = 2;
	private const TYPE_CHILD  = 3;

	private VolunteerEligibilityService $service;
	private VolunteerObligationCalculator $calculator;
	private string $season;

	protected function set_up(): void {
		parent::set_up();
		$this->service    = new VolunteerEligibilityService();
		$this->calculator = new VolunteerObligationCalculator();
		$this->season     = '2026-2027';
	}

	private function person( ?string $leeftijdsgroep, string $name = 'Test Persoon' ): int {
		$person_id = $this->createPerson( [ 'post_title' => $name ] );
		if ( $leeftijdsgroep !== null ) {
			update_post_meta( $person_id, 'leeftijdsgroep', $leeftijdsgroep );
		}
		return $person_id;
	}

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

	/**
	 * A completed shift inside the 2026-2027 season, assigned to the given people.
	 */
	private function completed_shift( array $person_ids, string $date = '2026-09-12 10:00:00' ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $shift_id, 'start_datetime', $date );
		update_post_meta( $shift_id, 'status', 'voltooid' );
		update_post_meta( $shift_id, 'assigned_persons', $person_ids );
		return $shift_id;
	}

	/**
	 * Progress is cached per unit for 5 minutes; a test that adds shifts between
	 * two reads must clear it, exactly as flush_cache_for_person() does in prod.
	 */
	private function flush_obligation_cache(): void {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\\_transient\\_rondo_vobligation_%'
			    OR option_name LIKE '\\_transient\\_timeout\\_rondo_vobligation_%'"
		);
		wp_cache_flush();
	}

	/**
	 * Decorate a person's units and return them keyed by kind.
	 */
	private function progress_by_kind( int $person_id ): array {
		$this->flush_obligation_cache();
		$units     = $this->service->get_eligible_units_for_person( $person_id, $this->season );
		$decorated = $this->calculator->decorate_units( $units, $this->season );

		$by_kind = [];
		foreach ( $decorated as $unit ) {
			$by_kind[ $unit['kind'] ] = $unit;
		}
		return $by_kind;
	}

	public function test_lone_player_keeps_every_shift_on_their_speler_unit(): void {
		$player_id = $this->person( 'Senioren' );
		for ( $i = 0; $i < 3; $i++ ) {
			$this->completed_shift( [ $player_id ] );
		}

		$units = $this->progress_by_kind( $player_id );

		$this->assertSame( 2, $units['speler']['required_count'] );
		$this->assertSame(
			3,
			$units['speler']['completed_count'],
			'A player with no gezin to spill into keeps every shift — extra work must not vanish'
		);
		$this->assertSame( 'voldaan', $units['speler']['status'] );
	}

	public function test_playing_parent_fills_speler_duty_before_gezin(): void {
		$parent_id = $this->person( 'Senioren', 'Spelende ouder' );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 12', 'Kind' ) );

		// One shift: goes to the speler duty, nothing spills.
		$this->completed_shift( [ $parent_id ] );

		$units = $this->progress_by_kind( $parent_id );
		$this->assertSame( 1, $units['speler']['completed_count'] );
		$this->assertSame( 0, $units['gezin']['completed_count'], 'Nothing spills until the speler duty is full' );
	}

	public function test_playing_parent_spills_surplus_into_the_gezin_duty(): void {
		$parent_id = $this->person( 'Senioren', 'Spelende ouder' );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 12', 'Kind' ) );

		// Three shifts: 2 fill the speler duty, the third spills.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->completed_shift( [ $parent_id ] );
		}

		$units = $this->progress_by_kind( $parent_id );
		$this->assertSame( 2, $units['speler']['completed_count'] );
		$this->assertSame( 'voldaan', $units['speler']['status'] );
		$this->assertSame( 1, $units['gezin']['completed_count'], 'The surplus shift spills into the gezin' );
		$this->assertSame( 2, $units['gezin']['required_count'] );
		$this->assertNotSame( 'voldaan', $units['gezin']['status'], 'One of two gezin diensten is not enough' );
	}

	/**
	 * The regression this whole change exists for: with double-crediting, three
	 * shifts satisfied a 2 + 3 duty. It must now take five.
	 */
	public function test_playing_parent_of_two_is_not_done_until_all_five_shifts(): void {
		$parent_id = $this->person( 'Senioren', 'Spelende ouder' );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 12', 'Kind 1' ) );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 9', 'Kind 2' ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->completed_shift( [ $parent_id ] );
		}

		$units = $this->progress_by_kind( $parent_id );
		$this->assertSame( 2, $units['speler']['required_count'] );
		$this->assertSame( 3, $units['gezin']['required_count'] );
		$this->assertSame( 2, $units['speler']['completed_count'] );
		$this->assertSame( 1, $units['gezin']['completed_count'] );
		$this->assertSame( 'voldaan', $units['speler']['status'] );
		$this->assertNotSame( 'voldaan', $units['gezin']['status'], 'Three shifts must not discharge a 2+3 duty' );

		// Two more shifts finish the job.
		$this->completed_shift( [ $parent_id ] );
		$this->completed_shift( [ $parent_id ] );

		$units = $this->progress_by_kind( $parent_id );
		$this->assertSame( 3, $units['gezin']['completed_count'] );
		$this->assertSame( 'voldaan', $units['gezin']['status'] );
	}

	public function test_non_playing_parent_puts_every_shift_into_the_gezin(): void {
		$parent_id = $this->person( null, 'Ouder' );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 12', 'Kind' ) );

		$this->completed_shift( [ $parent_id ] );
		$this->completed_shift( [ $parent_id ] );

		$units = $this->progress_by_kind( $parent_id );
		$this->assertArrayNotHasKey( 'speler', $units );
		$this->assertSame( 2, $units['gezin']['completed_count'] );
		$this->assertSame( 'voldaan', $units['gezin']['status'] );
	}

	public function test_a_youths_own_shift_counts_toward_the_family_duty(): void {
		$parent_id = $this->person( null, 'Ouder' );
		$child_id  = $this->person( 'Onder 12', 'Kind' );
		$this->link_parent_child( $parent_id, $child_id );

		// The child does a shift themselves.
		$this->completed_shift( [ $child_id ] );

		$units = $this->progress_by_kind( $parent_id );
		$this->assertSame( 1, $units['gezin']['completed_count'] );
	}

	public function test_both_parents_shifts_accumulate_on_one_gezin_unit(): void {
		$mother_id = $this->person( null, 'Moeder' );
		$father_id = $this->person( null, 'Vader' );
		$child_id  = $this->person( 'Onder 12', 'Kind' );
		$this->link_parent_child( $mother_id, $child_id );
		$this->link_parent_child( $father_id, $child_id );

		$this->completed_shift( [ $mother_id ] );
		$this->completed_shift( [ $father_id ] );

		$units = $this->progress_by_kind( $mother_id );
		$this->assertSame( 2, $units['gezin']['completed_count'], 'Parents fill the family duty together' );
		$this->assertSame( 'voldaan', $units['gezin']['status'] );
	}

	public function test_a_no_show_is_counted_once_on_the_persons_own_duty(): void {
		$parent_id = $this->person( 'Senioren', 'Spelende ouder' );
		$this->link_parent_child( $parent_id, $this->person( 'Onder 12', 'Kind' ) );

		$shift_id = $this->completed_shift( [ $parent_id ] );
		update_post_meta( $shift_id, VolunteerObligationCalculator::NO_SHOW_META_PREFIX . $parent_id, 1 );

		$units = $this->progress_by_kind( $parent_id );
		$this->assertSame( 1, $units['speler']['no_show_count'] );
		$this->assertSame( 0, $units['gezin']['no_show_count'], 'A no-show must not be counted on both units' );
		$this->assertSame( 0, $units['speler']['completed_count'] );
		$this->assertSame( 0, $units['gezin']['completed_count'] );
	}

	public function test_only_last_minute_cancelled_shifts_count_as_completed(): void {
		$player_id        = $this->person( 'Senioren' );
		$credited_shift   = $this->completed_shift( [ $player_id ] );
		$uncredited_shift = $this->completed_shift( [ $player_id ] );

		update_post_meta( $credited_shift, 'status', 'geannuleerd' );
		update_post_meta( $credited_shift, ShiftCancellationService::META_CREDIT, '1' );
		update_post_meta( $uncredited_shift, 'status', 'geannuleerd' );
		update_post_meta( $uncredited_shift, ShiftCancellationService::META_CREDIT, '0' );

		$units = $this->progress_by_kind( $player_id );

		$this->assertSame( 1, $units['speler']['completed_count'] );
		$this->assertSame( 0, $units['speler']['pending_count'] );
	}
}
