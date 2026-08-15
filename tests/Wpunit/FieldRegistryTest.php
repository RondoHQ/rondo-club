<?php

namespace Tests\Wpunit;

use InvalidArgumentException;
use Rondo\Fields\Registry;
use Tests\Support\RondoTestCase;

class FieldRegistryTest extends RondoTestCase {

	public function test_registry_contains_every_static_context(): void {
		$this->assertSame(
			[
				'commissie',
				'dienst_shift',
				'dienst_type',
				'discipline_case',
				'person',
				'relationship_type',
				'rondo_display',
				'rondo_feedback',
				'rondo_invoice',
				'rondo_todo',
				'shift_template',
				'taakuitleg',
				'team',
			],
			Registry::contexts()
		);
	}

	public function test_legacy_names_and_keys_resolve_to_explicit_canonical_names(): void {
		$this->assertSame( 'knvb_id', Registry::resolve( 'person', 'knvb-id' )['canonical_name'] );
		$this->assertSame( 'knvb_id', Registry::resolve( 'person', 'field_knvb_id' )['canonical_name'] );
		$this->assertSame( 'datum_vog', Registry::resolve( 'person', 'datum-vog' )['canonical_name'] );
	}

	public function test_duplicate_names_are_scoped_by_context(): void {
		$this->assertSame( 'website', Registry::resolve( 'team', 'website' )['canonical_name'] );
		$this->assertSame( 'website', Registry::resolve( 'commissie', 'website' )['canonical_name'] );
		$this->assertSame( 'status', Registry::resolve( 'rondo_feedback', 'status' )['canonical_name'] );
		$this->assertSame( 'status', Registry::resolve( 'rondo_invoice', 'status' )['canonical_name'] );
	}

	public function test_nested_relationship_contract_is_explicit(): void {
		$canonical = Registry::canonicalize(
			'person',
			[
				'relationships' => [
					[
						'related_person'     => 42,
						'relationship_type'  => 7,
						'relationship_label' => 'Ouder',
						'person_name'        => 'Jan Jansen',
					],
				],
			]
		);

		$this->assertSame( 42, $canonical['relationships'][0]['related_person_id'] );
		$this->assertSame( 7, $canonical['relationships'][0]['relationship_type_id'] );
		$this->assertSame( 'Jan Jansen', $canonical['relationships'][0]['person_name'] );
	}

	public function test_read_only_relationship_enrichment_is_dropped_on_write(): void {
		$storage = Registry::to_storage(
			'person',
			[
				'relationships' => [
					[
						'related_person_id' => 42,
						'person_name'       => 'Jan',
					],
				],
			]
		);

		$this->assertSame( [ [ 'related_person' => 42 ] ], $storage['relationships'] );
	}

	public function test_unknown_write_identifies_the_canonical_field_path(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'fields.not_a_field' );

		Registry::to_storage( 'person', [ 'not_a_field' => 'value' ] );
	}

	public function test_date_and_time_policies_are_recorded(): void {
		$birthdate = Registry::resolve( 'person', 'birthdate' );
		$shift     = Registry::resolve( 'dienst_shift', 'start_datetime' );
		$time      = Registry::resolve( 'shift_template', 'start_time' );

		$this->assertSame( 'Ymd', $birthdate['storage_format'] );
		$this->assertSame( 'Y-m-d', $birthdate['wire_format'] );
		$this->assertSame( DATE_RFC3339, $shift['wire_format'] );
		$this->assertSame( 'site', $shift['timezone'] );
		$this->assertSame( 'H:i', $time['wire_format'] );
	}
}
