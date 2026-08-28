<?php

namespace Tests\Wpunit;

use Rondo\REST\Volunteer;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Tests\Support\RondoTestCase;

/**
 * Query-scaling coverage for ID-only queries followed by post-meta reads.
 */
class MetadataPrimingPerformanceTest extends RondoTestCase {

	private function create_shift( array $assigned_person_ids ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Metadata priming test',
			]
		);

		update_post_meta( $shift_id, 'start_datetime', '2026-09-01 10:00:00' );
		update_post_meta( $shift_id, 'status', 'voltooid' );
		update_post_meta( $shift_id, 'capacity', 2 );
		update_post_meta( $shift_id, 'assigned_persons', $assigned_person_ids );

		return $shift_id;
	}

	private function count_cold_queries( callable $callback ): int {
		global $wpdb;

		wp_cache_flush();
		$before = (int) $wpdb->num_queries;
		$callback();

		return (int) $wpdb->num_queries - $before;
	}

	private function invoke_private( object $object, string $method, array $arguments = [] ) {
		$reflection = new \ReflectionMethod( $object, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $object, $arguments );
	}

	private function assert_query_count_is_constant( int $single, int $many, string $context ): void {
		$this->assertGreaterThan( 0, $single );
		$this->assertLessThanOrEqual(
			$single + 1,
			$many,
			sprintf( '%s should bulk-prime caches instead of adding queries per record.', $context )
		);
	}

	public function test_shift_capacity_query_count_does_not_scale_with_shift_count(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Vrijwilliger' ] );
		$this->create_shift( [ $person_id ] );
		$volunteer = new Volunteer();

		$single = $this->count_cold_queries(
			fn() => $this->invoke_private( $volunteer, 'get_shift_capacity_stats', [ '2026-2027' ] )
		);

		for ( $index = 0; $index < 7; ++$index ) {
			$this->create_shift( [ $person_id ] );
		}

		$many = $this->count_cold_queries(
			fn() => $this->invoke_private( $volunteer, 'get_shift_capacity_stats', [ '2026-2027' ] )
		);

		$this->assert_query_count_is_constant( $single, $many, 'Shift capacity' );
	}

	public function test_no_email_query_count_does_not_scale_with_person_count(): void {
		$this->createPerson( [ 'post_title' => 'Geen e-mail 1' ] );
		$volunteer = new Volunteer();

		$single = $this->count_cold_queries(
			fn() => $this->invoke_private( $volunteer, 'get_no_email_person_ids' )
		);

		for ( $index = 2; $index <= 8; ++$index ) {
			$this->createPerson( [ 'post_title' => 'Geen e-mail ' . $index ] );
		}

		$many = $this->count_cold_queries(
			fn() => $this->invoke_private( $volunteer, 'get_no_email_person_ids' )
		);

		$this->assert_query_count_is_constant( $single, $many, 'No-email diagnostics' );
	}

	public function test_eligibility_query_count_does_not_scale_with_person_count(): void {
		$this->createPerson( [ 'post_title' => 'Speler 1' ], [ 'leeftijdsgroep' => 'Senioren' ] );
		$service = new VolunteerEligibilityService();

		$single = $this->count_cold_queries(
			fn() => $this->invoke_private( $service, 'compute_eligibility_view', [ '2026-2027' ] )
		);

		for ( $index = 2; $index <= 8; ++$index ) {
			$this->createPerson( [ 'post_title' => 'Speler ' . $index ], [ 'leeftijdsgroep' => 'Senioren' ] );
		}

		$many = $this->count_cold_queries(
			fn() => $this->invoke_private( $service, 'compute_eligibility_view', [ '2026-2027' ] )
		);

		$this->assert_query_count_is_constant( $single, $many, 'Volunteer eligibility' );
	}

	public function test_obligation_tally_query_count_does_not_scale_with_shift_count(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Speler' ] );
		$this->create_shift( [ $person_id ] );
		$calculator = new VolunteerObligationCalculator();

		$single = $this->count_cold_queries(
			fn() => $this->invoke_private( $calculator, 'tally_per_person', [ [ $person_id ], '2026-2027' ] )
		);

		for ( $index = 0; $index < 7; ++$index ) {
			$this->create_shift( [ $person_id ] );
		}

		$many = $this->count_cold_queries(
			fn() => $this->invoke_private( $calculator, 'tally_per_person', [ [ $person_id ], '2026-2027' ] )
		);

		$this->assert_query_count_is_constant( $single, $many, 'Obligation tally' );
	}
}
