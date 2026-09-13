<?php

namespace Tests\Wpunit;

use Rondo\Teams\KnvbMatchdays;
use Rondo\Teams\TeamMatches;
use Tests\Support\RondoTestCase;

class KnvbMatchdaysTest extends RondoTestCase {

	private function row( array $overrides = [] ): array {
		return array_merge(
			[
				'teamsoort'          => 'bond',
				'competitiesoort'    => 'regulier',
				'kalespelsoort'      => 'VE',
				'speeldag'           => 'Zaterdag',
				'competitienaam'     => 'Onder 12 (1e fase)',
				'klasse'             => '1e klasse',
				'leeftijdscategorie' => 'Onder 12',
				'geslacht'           => 'gemengd',
			],
			$overrides
		);
	}

	private function profile( string $key, string $day = 'zaterdag', bool $division = false ): array {
		return [
			'key'      => $key,
			'day'      => $day,
			'division' => $division,
		];
	}

	public function test_classifies_competitions_using_source_metadata(): void {
		$this->assertSame( 'pupil', KnvbMatchdays::profile( $this->row() )['key'] );
		$junior = $this->row(
			[
				'leeftijdscategorie' => 'Onder 17',
				'competitienaam'     => 'Onder 17 (najaar A-cat)',
				'klasse'             => 'Divisie 5',
			]
			);
		$this->assertSame( 'junior_division', KnvbMatchdays::profile( $junior )['key'] );
		$junior['klasse'] = 'Hoofdklasse (1e fase A-Cat)';
		$this->assertSame( 'junior', KnvbMatchdays::profile( $junior )['key'] );
		$junior['geslacht'] = 'vrouw';
		$this->assertSame( 'girls_top', KnvbMatchdays::profile( $junior )['key'] );
		$junior['klasse'] = '1e klasse';
		$this->assertSame( 'girls', KnvbMatchdays::profile( $junior )['key'] );
		$senior = $this->row(
			[
				'leeftijdscategorie' => 'Senioren',
				'competitienaam'     => '0376 Onder 23 (najaarsreeks)',
			]
			);
		$this->assertSame( 'u23_b', KnvbMatchdays::profile( $senior )['key'] );
		$senior['klasse'] = 'Divisie 3';
		$this->assertSame( 'u23_a', KnvbMatchdays::profile( $senior )['key'] );
		$senior['klasse']         = '5e klasse';
		$senior['competitienaam'] = '0212 Mannen Zaterdag standaard (A-cat)';
		$this->assertSame( 'senior_a12', KnvbMatchdays::profile( $senior, 12 )['key'] );
		$this->assertSame( 'senior_a14', KnvbMatchdays::profile( $senior, 14 )['key'] );
		$this->assertSame( 'senior_a', KnvbMatchdays::profile( $senior, 13 )['key'] );
		$senior['competitienaam'] = '0519 Mannen Zondag reserve';
		$this->assertSame( 'senior_b', KnvbMatchdays::profile( $senior )['key'] );
		$senior['speeldag']       = 'Vrijdag';
		$senior['competitienaam'] = '231 Vrouwen 30+ Vrijdag Toernooivorm 7x7 (najaar)';
		$this->assertSame( 'friday', KnvbMatchdays::profile( $senior )['key'] );
	}

	public function test_does_not_guess_unsupported_national_local_or_indoor_competitions(): void {
		foreach ( [
			[ 'kalespelsoort' => 'ZA' ],
			[ 'teamsoort' => 'lokaal' ],
			[ 'competitiesoort' => 'beker' ],
			[ 'speeldag' => '' ],
			[
				'leeftijdscategorie' => 'Senioren',
				'competitienaam'     => 'O23 Landelijk Onder 23 (najaar A-cat)',
				'klasse'             => 'Divisie 2',
			],
			[
				'leeftijdscategorie' => 'Senioren',
				'competitienaam'     => 'Mannen (landelijk A-cat)',
				'klasse'             => '4e Divisie',
			],
			[
				'leeftijdscategorie' => 'Onder 19',
				'competitienaam'     => 'Onder 19',
				'klasse'             => 'Divisie 4',
			],
			[
				'leeftijdscategorie' => 'Onder 13',
				'competitienaam'     => 'Onder 13',
				'klasse'             => 'Divisie 2',
			],
		] as $overrides ) {
			$this->assertNull( KnvbMatchdays::profile( $this->row( $overrides ) ), wp_json_encode( $overrides ) );
		}
		$this->assertSame( [], KnvbMatchdays::candidates( $this->profile( 'pupil' ), '2027-2028' ) );
	}

	public function test_source_dates_phases_cup_and_holidays_remain_distinct(): void {
		$pupils = KnvbMatchdays::candidates( $this->profile( 'pupil' ), '2026-2027' );
		$this->assertSame( 'Speeldag - Start Fase 1', $pupils['2026-09-05']['label'] );
		$this->assertSame( 'Speeldag - Start Fase 4', $pupils['2027-04-03']['label'] );
		$this->assertArrayNotHasKey( '2026-10-10', $pupils );
		$this->assertArrayNotHasKey( '2027-05-17', $pupils );
		$junior = KnvbMatchdays::candidates( $this->profile( 'junior' ), '2026-2027' );
		$this->assertSame( 'Inhaal', $junior['2026-10-10']['label'] );
		$this->assertSame( 'Inhaal / beker', $junior['2027-01-23']['label'] );
		$seniors = KnvbMatchdays::candidates( $this->profile( 'senior_a12', 'zondag' ), '2026-2027' );
		$this->assertSame( 'Beker poule', $seniors['2026-08-30']['label'] );
		$this->assertSame( '2026-11-02', $seniors['2026-11-01']['end_date'] );
		$this->assertSame( '2026-11-20', $seniors['2026-11-17']['end_date'] );
		$this->assertSame( 'Inhaal / beker', $seniors['2027-03-27']['label'] );
		$this->assertSame( 'Inhaal / beker', $seniors['2027-03-29']['label'] );
		$this->assertSame( 'Speeldag', $seniors['2027-05-17']['label'] );
		$this->assertArrayNotHasKey( '2027-05-16', $seniors );
		$this->assertArrayNotHasKey( '2027-05-15', $seniors );
		$fridays = KnvbMatchdays::candidates( $this->profile( 'friday', 'vrijdag' ), '2026-2027' );
		$this->assertCount( 10, $fridays );
		$this->assertSame( 'Toernooi 7x7', $fridays['2026-09-18']['label'] );
		$this->assertSame( 'Toernooi 7x7', $fridays['2027-05-21']['label'] );
	}

	public function test_girls_division_and_hoofdklasse_have_different_winter_days(): void {
		$division = KnvbMatchdays::candidates( $this->profile( 'girls_top', 'zaterdag', true ), '2026-2027' );
		$hoofd    = KnvbMatchdays::candidates( $this->profile( 'girls_top' ), '2026-2027' );
		$this->assertSame( 'Inhaal', $division['2026-12-19']['label'] );
		$this->assertArrayNotHasKey( '2026-12-19', $hoofd );
		$this->assertSame( 'Speeldag - Fase 2', $division['2027-01-23']['label'] );
		$this->assertSame( 'Speeldag - Fase 3', $hoofd['2027-01-23']['label'] );
	}

	public function test_scheduled_match_cancels_placeholder_and_rescheduling_restores_same_uid(): void {
		$candidates = KnvbMatchdays::candidates( $this->profile( 'junior' ), '2026-2027' );
		$first      = KnvbMatchdays::reconcile( $candidates, [], [], '2026-09-13' );
		$this->assertArrayNotHasKey( '2026-09-12', $first );
		$date    = '2026-10-10';
		$matches = [
			[
				'date'      => $date,
				'cancelled' => false,
			],
		];
		$second  = KnvbMatchdays::reconcile( $candidates, $matches, $first, '2026-09-13' );
		$this->assertTrue( $second[ $date ]['cancelled'] );
		$this->assertSame( 1, $second[ $date ]['sequence'] );
		$this->assertSame( $second, KnvbMatchdays::reconcile( $candidates, $matches, $second, '2026-09-13' ) );
		$matches[0]['cancelled'] = true;
		$third                   = KnvbMatchdays::reconcile( $candidates, $matches, $second, '2026-09-13' );
		$this->assertFalse( $third[ $date ]['cancelled'] );
		$this->assertSame( 2, $third[ $date ]['sequence'] );
		$team_id  = $this->createOrganization( [ 'post_title' => 'AWC O17-2' ] );
		$calendar = TeamMatches::calendar(
			$team_id,
			[
				'matches'   => [],
				'matchdays' => [ $date => $third[ $date ] ],
			]
			);
		$this->assertStringContainsString( 'SUMMARY:AWC O17-2 - Inhaal (voorlopig)', $calendar );
		$this->assertStringContainsString( 'STATUS:TENTATIVE', $calendar );
		$this->assertStringContainsString( 'DTSTART;VALUE=DATE:20261010', $calendar );
		$this->assertStringContainsString( 'DTEND;VALUE=DATE:20261011', $calendar );
		$this->assertStringContainsString( 'SEQUENCE:2', $calendar );
		$cancelled = TeamMatches::calendar(
			$team_id,
			[
				'matches'   => [],
				'matchdays' => [ $date => $second[ $date ] ],
			]
			);
		$this->assertStringContainsString( 'STATUS:CANCELLED', $cancelled );
		preg_match( '/UID:(.+)\r\n/', $calendar, $active_uid );
		preg_match( '/UID:(.+)\r\n/', $cancelled, $cancelled_uid );
		$this->assertSame( $active_uid[1], $cancelled_uid[1] );
		$removed = KnvbMatchdays::reconcile( [], [], $third, '2026-09-13' );
		$this->assertTrue( $removed[ $date ]['cancelled'] );
		$this->assertSame( 3, $removed[ $date ]['sequence'] );
	}

	public function test_midweek_match_suppresses_whole_reserved_range(): void {
		$candidates = KnvbMatchdays::candidates( $this->profile( 'senior_a12' ), '2026-2027' );
		$events     = KnvbMatchdays::reconcile(
			$candidates,
			[
				[
					'date'      => '2026-11-18',
					'cancelled' => false,
				],
			],
			[],
			'2026-09-13'
			);
		$this->assertArrayNotHasKey( '2026-11-17', $events );
	}

	public function test_resolver_uses_linked_union_metadata_and_rejects_conflicting_schedules(): void {
		$row   = $this->row(
			[
				'teamcode'    => 123,
				'local_names' => false,
			]
			);
		$local = array_merge(
			$row,
			[
				'local_names' => true,
				'teamsoort'   => 'lokaal',
			]
			);
		$this->assertSame( 'pupil', KnvbMatchdays::resolve( [ 'teamcode' => 123 ], [ $row, $local ] )['key'] );
		$this->assertNull( KnvbMatchdays::resolve( [ 'teamcode' => -1 ], [ $row, $local ] ) );
		$conflict = array_merge(
			$row,
			[
				'leeftijdscategorie' => 'Onder 17',
				'competitienaam'     => 'Onder 17',
			]
			);
		$this->assertNull( KnvbMatchdays::resolve( [ 'teamcode' => 123 ], [ $row, $conflict ] ) );
		$conflict['klasse'] = 'Divisie 1';
		$this->assertNull( KnvbMatchdays::resolve( [ 'teamcode' => 123 ], [ $row, $conflict ] ) );
		$senior = $this->row(
			[
				'teamcode'           => 123,
				'poulecode'          => 789,
				'leeftijdscategorie' => 'Senioren',
				'competitienaam'     => 'Mannen standaard (A-cat)',
			]
			);
		set_transient( 'rondo_knvb_pool_size_789', 12, HOUR_IN_SECONDS );
		$this->assertSame( 'senior_a12', KnvbMatchdays::resolve( [ 'teamcode' => 123 ], [ $senior ] )['key'] );
		set_transient( 'rondo_knvb_pool_size_789', 13, HOUR_IN_SECONDS );
		$this->assertNull( KnvbMatchdays::resolve( [ 'teamcode' => 123 ], [ $senior ] ) );
		delete_transient( 'rondo_knvb_pool_size_789' );
	}
}
