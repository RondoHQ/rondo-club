<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Tests\Support\RondoTestCase;

class FieldsApiTest extends RondoTestCase {

	public function test_post_api_resolves_canonical_names_to_legacy_storage(): void {
		$person_id = $this->createPerson();

		$this->assertTrue( Fields::update_for_post( $person_id, 'knvb_id', '1234567' ) );
		$this->assertSame( '1234567', get_post_meta( $person_id, 'knvb-id', true ) );
		$this->assertSame( '1234567', Fields::get_for_post( $person_id, 'knvb_id' ) );
		$this->assertTrue( Fields::delete_for_post( $person_id, 'knvb_id' ) );
		$this->assertSame( '', get_post_meta( $person_id, 'knvb-id', true ) );
	}

	public function test_term_api_never_overloads_a_post_id_with_a_string_context(): void {
		$term = wp_insert_term( 'Test inverse', 'relationship_type' );
		$this->assertIsArray( $term );
		$term_id = (int) $term['term_id'];

		$this->assertTrue( Fields::update_for_term( 'relationship_type', $term_id, 'inverse_relationship_type', $term_id ) );
		$this->assertSame( $term_id, Fields::get_for_term( 'relationship_type', $term_id, 'inverse_relationship_type' ) );
		$this->assertTrue( Fields::delete_for_term( 'relationship_type', $term_id, 'inverse_relationship_type' ) );
	}
}
