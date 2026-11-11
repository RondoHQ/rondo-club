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

	public function test_birthdate_is_shifted_and_not_left_real(): void {
		$person = [
			'_ref'      => 'person_123',
			'title'     => 'Echt Persoon',
			'fields'    => [
				'first_name' => 'Echt',
				'last_name'  => 'Persoon',
				'gender'     => 'M',
				'birthdate'  => '2012-06-15',
				'addresses'  => [],
			],
			'post_meta' => [],
		];

		$result = $this->invoke( 'anonymize_person', $person );

		$this->assertNotSame( '2012-06-15', $result['fields']['birthdate'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-06-15$/', $result['fields']['birthdate'], 'a whole-year shift keeps month and day' );

		$again = $this->invoke( 'anonymize_person', $person );
		$this->assertSame( $result['fields']['birthdate'], $again['fields']['birthdate'], 'the shift is deterministic per person' );
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
