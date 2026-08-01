<?php

namespace Tests\Wpunit;

use Rondo\CustomFields\Manager;
use Rondo\CustomFields\Validation;
use Rondo\Fields\Fields;
use Rondo\Fields\Registry;
use Tests\Support\RondoTestCase;

class DynamicFieldsStoreTest extends RondoTestCase {
	private Manager $manager;

	protected function set_up(): void {
		parent::set_up();
		delete_option( Manager::OPTION_NAME );
		Registry::reset();
		$this->manager = new Manager();
	}

	protected function tear_down(): void {
		delete_option( Manager::OPTION_NAME );
		Registry::reset();
		parent::tear_down();
	}

	public function test_crud_preserves_immutable_identity_and_soft_deletes(): void {
		$field = $this->manager->create_field(
			'person',
			[
				'label'          => 'Shirt size',
				'name'           => 'shirt-size',
				'canonical_name' => 'shirt_size',
				'type'           => 'select',
				'choices'        => [
					's' => 'S',
					'm' => 'M',
				],
			]
		);
		$this->assertIsArray( $field );
		$key = $field['key'];

		$updated = $this->manager->update_field(
			$key,
			[
				'label'          => 'Jersey size',
				'name'           => 'renamed-storage',
				'canonical_name' => 'renamed_api',
				'type'           => 'text',
			]
		);
		$this->assertSame( 'Jersey size', $updated['label'] );
		$this->assertSame( 'shirt-size', $updated['storage_key'] );
		$this->assertSame( 'shirt_size', $updated['canonical_name'] );
		$this->assertSame( 'select', $updated['type'] );

		$this->assertIsArray( $this->manager->deactivate_field( $key ) );
		$this->assertSame( [], $this->manager->get_fields( 'person' ) );
		$this->assertCount( 1, $this->manager->get_fields( 'person', true ) );
		$person_id = $this->createPerson();
		update_post_meta( $person_id, 'shirt-size', 'm' );
		$this->assertIsArray( $this->manager->reactivate_field( $key ) );
		$this->assertCount( 1, $this->manager->get_fields( 'person' ) );
		$this->assertSame( 'm', Fields::get_for_post( $person_id, 'shirt_size' ), 'Soft deletion must preserve stored values.' );
	}

	public function test_collisions_reorder_and_backup_round_trip(): void {
		$first  = $this->manager->create_field(
			'team',
			[
				'label' => 'External code',
				'type'  => 'text',
			]
			);
		$second = $this->manager->create_field(
			'team',
			[
				'label' => 'Jersey note',
				'type'  => 'textarea',
			]
			);
		$this->assertIsArray( $first );
		$this->assertIsArray( $second );

		$static_collision = $this->manager->create_field(
			'team',
			[
				'label'          => 'Website duplicate',
				'name'           => 'other-key',
				'canonical_name' => 'website',
				'type'           => 'url',
			]
		);
		$this->assertWPError( $static_collision );
		$this->assertSame( 'field_name_collision', $static_collision->get_error_code() );

		$this->assertTrue( $this->manager->reorder_fields( 'team', [ $second['key'], $first['key'] ] ) );
		$this->assertSame( [ $second['key'], $first['key'] ], array_column( $this->manager->get_fields( 'team' ), 'key' ) );

		$backup = $this->manager->export_store();
		delete_option( Manager::OPTION_NAME );
		Registry::reset();
		$this->assertTrue( $this->manager->import_store( $backup, true ) );
		$this->assertSame( [ $second['key'], $first['key'] ], array_column( $this->manager->get_fields( 'team' ), 'key' ) );
	}

	public function test_preexisting_dynamic_collision_is_skipped_at_runtime(): void {
		update_option(
			Manager::OPTION_NAME,
			[
				'schema_version' => Manager::SCHEMA_VERSION,
				'contexts'       => [
					'person'    => [
						[
							'id'             => 'field_legacy_first_name',
							'key'            => 'field_legacy_first_name',
							'label'          => 'Legacy first name',
							'name'           => 'legacy-first-name',
							'storage_key'    => 'legacy-first-name',
							'canonical_name' => 'first_name',
							'type'           => 'text',
							'active'         => true,
							'menu_order'     => 1,
						],
					],
					'team'      => [],
					'commissie' => [],
				],
			],
			false
		);
		Registry::reset();

		$this->assertSame( 'first_name', Registry::fields_for( 'person' )['first_name']['canonical_name'] );
		$this->assertArrayNotHasKey( 'legacy-first-name', Registry::fields_for( 'person' ) );
	}

	public function test_import_rejects_static_collisions_and_unsupported_types(): void {
		$document = [
			'schema_version' => Manager::SCHEMA_VERSION,
			'contexts'       => [
				'person'    => [],
				'commissie' => [],
				'team'      => [
					[
						'id'             => 'field_imported_website',
						'key'            => 'field_imported_website',
						'label'          => 'Website duplicate',
						'name'           => 'imported-website',
						'storage_key'    => 'imported-website',
						'canonical_name' => 'website',
						'type'           => 'text',
						'active'         => true,
					],
				],
			],
		];

		$result = $this->manager->import_store( $document, true );
		$this->assertWPError( $result );
		$this->assertSame( 'field_name_collision', $result->get_error_code() );
		$this->assertWPError(
			$this->manager->create_field(
			'person',
			[
				'label' => 'Executable',
				'type'  => 'php',
			]
			)
			);
	}

	public function test_unique_dynamic_value_validation_excludes_the_current_post(): void {
		new Validation();
		$field = $this->manager->create_field(
			'person',
			[
				'label'  => 'External identity',
				'type'   => 'text',
				'unique' => true,
			]
		);
		$this->assertIsArray( $field );
		$first  = $this->createPerson();
		$second = $this->createPerson();

		$this->assertTrue( Fields::update_many_for_post( $first, [ $field['canonical_name'] => 'unique-42' ] ) );
		$this->assertTrue( Fields::update_many_for_post( $first, [ $field['canonical_name'] => 'unique-42' ] ) );
		$result = Fields::update_many_for_post( $second, [ $field['canonical_name'] => 'unique-42' ] );
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_duplicate_field', $result->get_error_code() );
	}
}
