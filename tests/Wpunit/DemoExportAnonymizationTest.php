<?php

namespace Tests\Wpunit;

use Rondo\Demo\DemoAnonymizer;
use Rondo\Demo\DemoExport;
use Tests\Support\RondoTestCase;

/**
 * The demo export is built to be shared, so it must not carry real
 * birthdates or real discipline free text out of production.
 */
class DemoExportAnonymizationTest extends RondoTestCase {
	private DemoExport $exporter;

	protected function set_up(): void {
		parent::set_up();
		$this->exporter = new DemoExport( '/tmp/rondo-demo-export-test.json' );
		$property       = new \ReflectionProperty( DemoExport::class, 'anonymizer' );
		$property->setAccessible( true );
		$property->setValue( $this->exporter, new DemoAnonymizer() );
	}

	private function invoke( string $method, $argument ) {
		$reflection = new \ReflectionMethod( DemoExport::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invoke( $this->exporter, $argument );
	}

	private function person( $birthdate ): array {
		return [
			'_ref'      => 'person_123',
			'title'     => 'Echt Persoon',
			'fields'    => [
				'first_name'     => 'Echt',
				'last_name'      => 'Persoon',
				'gender'         => 'M',
				'birthdate'      => $birthdate,
				'leeftijdsgroep' => 'Onder 13',
				'addresses'      => [],
			],
			'post_meta' => [],
		];
	}

	public function test_birthdate_does_not_depend_on_original_year_month_or_day(): void {
		$result = $this->invoke( 'anonymize_person', $this->person( '2012-06-15' ) );
		$other  = $this->invoke( 'anonymize_person', $this->person( '1990-01-03' ) );

		$this->assertSame( $result['fields']['birthdate'], $other['fields']['birthdate'], 'the real date must not contribute to the synthetic date' );
		$this->assertSame( (int) wp_date( 'Y' ) - 12, (int) substr( $result['fields']['birthdate'], 0, 4 ), 'youth fixtures retain their declared age group' );
		$this->assertNotSame( '06-15', substr( $result['fields']['birthdate'], 5 ) );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $result['fields']['birthdate'] );

		$again = $this->invoke( 'anonymize_person', $this->person( '2012-06-15' ) );
		$this->assertSame( $result['fields']['birthdate'], $again['fields']['birthdate'] );
	}

	public function test_compact_and_leap_day_inputs_produce_the_same_synthetic_date(): void {
		$compact = $this->invoke( 'anonymize_person', $this->person( '20120229' ) );
		$dashed  = $this->invoke( 'anonymize_person', $this->person( '2000-12-31' ) );
		$this->assertSame( str_replace( '-', '', $dashed['fields']['birthdate'] ), $compact['fields']['birthdate'] );
	}

	public function test_missing_or_invalid_dates_are_cleared(): void {
		foreach ( [ null, '', 'personal text', '2011-02-29', '2012-02-30', '2012-0229', [] ] as $value ) {
			$result = $this->invoke( 'anonymize_person', $this->person( $value ) );
			$this->assertNull( $result['fields']['birthdate'] );
		}
	}

	public function test_different_people_have_varied_valid_birthdays(): void {
		$dates = [];
		for ( $i = 1; $i <= 30; $i++ ) {
			$person         = $this->person( '2012-06-15' );
			$person['_ref'] = 'person_' . $i;
			$result         = $this->invoke( 'anonymize_person', $person );
			$date           = $result['fields']['birthdate'];
			$this->assertSame( $date, ( new \DateTimeImmutable( $date ) )->format( 'Y-m-d' ) );
			$dates[] = $date;
		}
		$this->assertGreaterThan( 20, count( array_unique( $dates ) ) );
	}

	public function test_discipline_free_text_is_stripped(): void {
		$case = [
			'title'  => 'Echt Persoon - Echte Wedstrijd - 2026-01-01',
			'fields' => [
				'person'               => 'person_123',
				'match_description'    => 'Echte Wedstrijd',
				'match_date'           => '2026-01-01',
				'charge_description'   => 'Echte tenlastelegging met herkenbare details',
				'sanction_description' => 'Echte sanctie met herkenbare details',
			],
		];

		$result = $this->invoke( 'anonymize_discipline_case', $case );

		$this->assertStringNotContainsString( 'herkenbare details', $result['fields']['charge_description'] );
		$this->assertStringNotContainsString( 'herkenbare details', $result['fields']['sanction_description'] );
		$this->assertStringNotContainsString( 'Echte Wedstrijd', $result['title'] );
		$this->assertStringNotContainsString( 'Echt Persoon', $result['title'] );
	}
}
