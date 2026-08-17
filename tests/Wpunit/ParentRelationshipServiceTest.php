<?php

namespace Tests\Wpunit;

use Rondo\Data\InverseRelationships;
use Rondo\Fields\Fields;
use Rondo\People\ParentRelationshipService;
use Tests\Support\RondoTestCase;

class ParentRelationshipServiceTest extends RondoTestCase {

	private int $parent_type_id;
	private int $child_type_id;

	protected function set_up(): void {
		parent::set_up();
		new InverseRelationships();
		$this->parent_type_id = $this->relationship_type( 'parent', 'Ouder' );
		$this->child_type_id  = $this->relationship_type( 'child', 'Kind' );
		Fields::update_for_term( 'relationship_type', $this->parent_type_id, 'inverse_relationship_type', $this->child_type_id );
		Fields::update_for_term( 'relationship_type', $this->child_type_id, 'inverse_relationship_type', $this->parent_type_id );
	}

	public function test_new_parent_is_created_linked_and_marked_pending(): void {
		$child   = $this->createPerson(
			[ 'post_title' => 'Kind' ],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'TEST123',
			]
			);
		$service = new ParentRelationshipService();
		$result  = $service->add_parent(
			$child,
			[
				'mode'  => 'new',
				'name'  => 'Noor van Dijk',
				'email' => 'NOOR@EXAMPLE.ORG',
				'phone' => '06 12345678',
			]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['created'] );
		$parent = (int) $result['parent_id'];
		$this->assertSame( 'Noor van Dijk', Fields::get_for_post( $parent, 'first_name' ) );
		$this->assertSame( 'noor@example.org', Fields::get_for_post( $parent, 'email_1' ) );
		$this->assertSame( '06 12345678', Fields::get_for_post( $parent, 'telephone_1' ) );

		$relationships = Fields::get_for_post( $child, 'relationships' );
		$this->assertCount( 1, $relationships );
		$this->assertSame( $parent, $relationships[0]['related_person'] );
		$this->assertSame( $this->parent_type_id, $relationships[0]['relationship_type'] );

		$inverse = Fields::get_for_post( $parent, 'relationships' );
		$this->assertCount( 1, $inverse );
		$this->assertSame( $child, $inverse[0]['related_person'] );
		$this->assertSame( $this->child_type_id, $inverse[0]['relationship_type'] );
		$this->assertSame( 'pending', $service->get_sync_statuses( $child )[0]['state'] );
	}

	public function test_existing_parent_requires_name_and_email(): void {
		$child  = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'TEST124',
			]
			);
		$parent = $this->createPerson( [], [ 'first_name' => 'Zonder mail' ] );
		$result = ( new ParentRelationshipService() )->add_parent(
			$child,
			[
				'mode'      => 'existing',
				'parent_id' => $parent,
			]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_parent_contact_required', $result->get_error_code() );
	}

	public function test_new_parent_reuses_neither_existing_email_nor_person(): void {
		$this->createPerson(
			[],
			[
				'first_name' => 'Bestaand',
				'email_1'    => 'ouder@example.org',
			]
			);
		$child  = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'TEST125',
			]
			);
		$result = ( new ParentRelationshipService() )->add_parent(
			$child,
			[
				'mode'  => 'new',
				'name'  => 'Dubbel',
				'email' => 'ouder@example.org',
			]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_parent_email_exists', $result->get_error_code() );
	}

	public function test_third_parent_is_blocked_before_creation(): void {
		$child = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'TEST126',
			]
			);
		$rows  = [];
		foreach ( [ 1, 2 ] as $index ) {
			$parent = $this->createPerson(
				[],
				[
					'first_name' => 'Ouder ' . $index,
					'email_1'    => "ouder{$index}@example.org",
				]
				);
			$rows[] = [
				'related_person'    => $parent,
				'relationship_type' => $this->parent_type_id,
			];
		}
		Fields::update_for_post( $child, 'relationships', $rows );

		$result = ( new ParentRelationshipService() )->add_parent(
			$child,
			[
				'mode'  => 'new',
				'name'  => 'Derde ouder',
				'email' => 'derde@example.org',
			]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_parent_slots_full', $result->get_error_code() );
	}

	private function relationship_type( string $slug, string $name ): int {
		$term = get_term_by( 'slug', $slug, 'relationship_type' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $name, 'relationship_type', [ 'slug' => $slug ] );
		$this->assertIsArray( $created );
		return (int) $created['term_id'];
	}
}
