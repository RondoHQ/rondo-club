<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Fields\Registry;
use Tests\Support\RondoTestCase;

class NativeFieldStorageTest extends RondoTestCase {

	public function test_scalar_write_preserves_legacy_storage_and_reference_rows(): void {
		$person_id = $this->createPerson();

		$this->assertTrue( Fields::update_for_post( $person_id, 'knvb_id', '1234567' ) );
		$this->assertSame( '1234567', get_post_meta( $person_id, 'knvb-id', true ) );
		$this->assertSame( 'field_knvb_id', get_post_meta( $person_id, '_knvb-id', true ) );
		$this->assertTrue( Fields::delete_for_post( $person_id, 'knvb_id' ) );
		$this->assertFalse( metadata_exists( 'post', $person_id, 'knvb-id' ) );
		$this->assertFalse( metadata_exists( 'post', $person_id, '_knvb-id' ) );
	}

	public function test_repeater_grows_shrinks_and_removes_stale_numbered_rows(): void {
		$person_id = $this->createPerson();
		$rows      = [
			[
				'address_label' => 'Thuis',
				'city'          => 'Amsterdam',
				'street_name'   => 'Eerste straat',
			],
			[
				'address_label' => 'Werk',
				'city'          => 'Utrecht',
				'street_name'   => 'Tweede straat',
			],
			[
				'address_label' => 'Post',
				'city'          => 'Leiden',
				'street_name'   => 'Derde straat',
			],
		];

		$this->assertTrue( Fields::update_for_post( $person_id, 'addresses', $rows ) );
		$this->assertSame( '3', (string) get_post_meta( $person_id, 'addresses', true ) );
		$this->assertSame( 'Utrecht', get_post_meta( $person_id, 'addresses_1_city', true ) );
		$this->assertSame( 'field_address_city', get_post_meta( $person_id, '_addresses_1_city', true ) );
		$this->assertCount( 3, Fields::get_for_post( $person_id, 'addresses' ) );

		$this->assertTrue( Fields::update_for_post( $person_id, 'addresses', [ $rows[0] ] ) );
		$this->assertSame( '1', (string) get_post_meta( $person_id, 'addresses', true ) );
		$this->assertFalse( metadata_exists( 'post', $person_id, 'addresses_1_city' ) );
		$this->assertFalse( metadata_exists( 'post', $person_id, '_addresses_1_city' ) );
		$this->assertFalse( metadata_exists( 'post', $person_id, 'addresses_2_city' ) );

		$this->assertTrue( Fields::update_for_post( $person_id, 'addresses', [] ) );
		$this->assertFalse( metadata_exists( 'post', $person_id, 'addresses' ) );
		$this->assertFalse( metadata_exists( 'post', $person_id, '_addresses' ) );
		$this->assertSame( [], Fields::get_for_post( $person_id, 'addresses' ) );
	}

	public function test_batched_update_validates_before_writing_and_fires_one_logical_save(): void {
		$person_id = $this->createPerson();
		$saves     = 0;
		$updates   = [];
		$on_save   = static function ( int $saved_post_id ) use ( $person_id, &$saves ): void {
			if ( $saved_post_id === $person_id ) {
				++$saves;
			}
		};
		$on_update = static function ( int $saved_post_id, string $field_name ) use ( $person_id, &$updates ): void {
			if ( $saved_post_id === $person_id ) {
				$updates[] = $field_name;
			}
		};
		add_action( 'rondo_fields_saved_post', $on_save, 99, 1 );
		add_action( 'rondo_fields_updated', $on_update, 99, 2 );

		$result = Fields::update_many_for_post(
			$person_id,
			[
				'first_name' => 'Ada',
				'last_name'  => 'Lovelace',
			]
		);

		remove_action( 'rondo_fields_saved_post', $on_save, 99 );
		remove_action( 'rondo_fields_updated', $on_update, 99 );
		$this->assertTrue( $result );
		$this->assertSame( 1, $saves );
		$this->assertSame( [ 'first_name', 'last_name' ], $updates );
	}

	public function test_term_and_multiple_object_values_keep_native_shapes(): void {
		$term = wp_insert_term( 'Native inverse', 'relationship_type' );
		$this->assertIsArray( $term );
		$term_id = (int) $term['term_id'];

		$this->assertTrue( Fields::update_for_term( 'relationship_type', $term_id, 'inverse_relationship_type', $term_id ) );
		$this->assertSame( $term_id, Fields::get_for_term( 'relationship_type', $term_id, 'inverse_relationship_type' ) );
		$this->assertSame( 'field_inverse_relationship_type', get_term_meta( $term_id, '_inverse_relationship_type', true ) );

		$todo_id   = self::factory()->post->create( [ 'post_type' => 'rondo_todo' ] );
		$person_id = $this->createPerson();
		$this->assertTrue( Fields::update_for_post( $todo_id, 'related_persons', [ (string) $person_id ] ) );
		$this->assertSame( [ $person_id ], Fields::get_for_post( $todo_id, 'related_persons' ) );
	}

	public function test_file_and_gallery_wire_values_are_native_attachment_objects(): void {
		$attachment_id = self::factory()->attachment->create_object(
			'test-photo.jpg',
			0,
			[
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test photo',
			]
		);
		$this->assertIsInt( $attachment_id );
		$fields = Formatter::for_wire(
			'person',
			[
				'iva_certificaat' => $attachment_id,
				'photo_gallery'   => [ $attachment_id ],
			]
		);

		$this->assertSame( $attachment_id, $fields['iva_certificaat']['id'] );
		$this->assertSame( 'Test photo', $fields['iva_certificaat']['title'] );
		$this->assertSame( $attachment_id, $fields['photo_gallery'][0]['id'] );
		$this->assertSame(
			[ $attachment_id ],
			Formatter::for_storage( 'person', [ 'photo_gallery' => [ $fields['photo_gallery'][0] ] ] )['photo_gallery']
		);
	}

	public function test_all_for_post_exposes_canonical_nested_names(): void {
		$person_id = $this->createPerson();
		Fields::update_for_post(
			$person_id,
			'relationships',
			[
				[
					'related_person'     => 42,
					'relationship_type'  => 7,
					'relationship_label' => 'Ouder',
				],
			]
		);

		$fields = Fields::all_for_post( $person_id );
		$this->assertSame( 42, $fields['relationships'][0]['related_person_id'] );
		$this->assertSame( 7, $fields['relationships'][0]['relationship_type_id'] );
		$this->assertSame( 'Ouder', $fields['relationships'][0]['relationship_label'] );
		$this->assertSame( 'relationships', Registry::resolve( 'person', 'relationships' )['storage_name'] );
	}
}
