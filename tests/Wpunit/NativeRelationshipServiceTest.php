<?php

namespace Tests\Wpunit;

use Rondo\Data\InverseRelationships;
use Rondo\Fields\Fields;
use Tests\Support\RondoTestCase;

class NativeRelationshipServiceTest extends RondoTestCase {

	public function test_add_change_and_delete_synchronize_inverse_rows_without_recursion(): void {
		new InverseRelationships();
		$parent_type = wp_insert_term( 'Test parent', 'relationship_type', [ 'slug' => 'test-parent' ] );
		$child_type  = wp_insert_term( 'Test child', 'relationship_type', [ 'slug' => 'test-child' ] );
		$friend_type = wp_insert_term( 'Test friend', 'relationship_type', [ 'slug' => 'test-friend' ] );
		$this->assertIsArray( $parent_type );
		$this->assertIsArray( $child_type );
		$this->assertIsArray( $friend_type );
		$parent_type_id = (int) $parent_type['term_id'];
		$child_type_id  = (int) $child_type['term_id'];
		$friend_type_id = (int) $friend_type['term_id'];
		Fields::update_for_term( 'relationship_type', $parent_type_id, 'inverse_relationship_type', $child_type_id );
		Fields::update_for_term( 'relationship_type', $child_type_id, 'inverse_relationship_type', $parent_type_id );
		Fields::update_for_term( 'relationship_type', $friend_type_id, 'inverse_relationship_type', $friend_type_id );

		$child  = $this->createPerson( [ 'post_title' => 'Child' ] );
		$parent = $this->createPerson( [ 'post_title' => 'Parent' ] );
		$this->assertTrue(
			Fields::update_for_post(
				$child,
				'relationships',
				[
					[
						'related_person'     => $parent,
						'relationship_type'  => $parent_type_id,
						'relationship_label' => 'Ouder',
					],
				]
			)
		);

		$inverse = Fields::get_for_post( $parent, 'relationships' );
		$this->assertCount( 1, $inverse );
		$this->assertSame( $child, $inverse[0]['related_person'] );
		$this->assertSame( $child_type_id, $inverse[0]['relationship_type'] );

		$this->assertTrue(
			Fields::update_for_post(
				$child,
				'relationships',
				[
					[
						'related_person'     => $parent,
						'relationship_type'  => $friend_type_id,
						'relationship_label' => 'Vriend',
					],
				]
			)
		);
		$changed_inverse = Fields::get_for_post( $parent, 'relationships' );
		$this->assertCount( 1, $changed_inverse );
		$this->assertSame( $friend_type_id, $changed_inverse[0]['relationship_type'] );

		$this->assertTrue( Fields::update_for_post( $child, 'relationships', [] ) );
		$this->assertSame( [], Fields::get_for_post( $parent, 'relationships' ) );
	}

	public function test_validation_removes_self_links_and_duplicate_rows_before_storage(): void {
		new InverseRelationships();
		$type = wp_insert_term( 'Test symmetric', 'relationship_type', [ 'slug' => 'test-symmetric' ] );
		$this->assertIsArray( $type );
		$type_id = (int) $type['term_id'];
		Fields::update_for_term( 'relationship_type', $type_id, 'inverse_relationship_type', $type_id );
		$person = $this->createPerson();
		$other  = $this->createPerson();

		$this->assertTrue(
			Fields::update_for_post(
				$person,
				'relationships',
				[
					[
						'related_person'    => $person,
						'relationship_type' => $type_id,
					],
					[
						'related_person'    => $other,
						'relationship_type' => $type_id,
					],
					[
						'related_person'    => $other,
						'relationship_type' => $type_id,
					],
				]
			)
		);
		$this->assertCount( 1, Fields::get_for_post( $person, 'relationships' ) );
	}
}
