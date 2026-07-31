<?php

namespace Tests\Wpunit;

use Rondo\Data\PersonDeletionGuard;
use Tests\Support\RondoTestCase;

class PersonDeletionGuardTest extends RondoTestCase {

	private PersonDeletionGuard $guard;

	protected function set_up(): void {
		parent::set_up();
		$this->guard = new PersonDeletionGuard();
	}

	public function test_person_with_active_relationship_cannot_be_trashed(): void {
		$related_id = $this->createPerson( [ 'post_title' => 'Gerelateerd lid' ] );
		$person_id  = $this->personWithRelationship( $related_id );

		$this->assertFalse( wp_trash_post( $person_id ) );
		$this->assertSame( 'publish', get_post_status( $person_id ) );
	}

	public function test_person_with_active_relationship_cannot_be_permanently_deleted(): void {
		$related_id = $this->createPerson( [ 'post_title' => 'Gerelateerd lid' ] );
		$person_id  = $this->personWithRelationship( $related_id );

		$this->assertFalse( wp_delete_post( $person_id, true ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $person_id ) );
	}

	public function test_person_can_be_deleted_after_relationship_is_removed(): void {
		$related_id = $this->createPerson( [ 'post_title' => 'Gerelateerd lid' ] );
		$person_id  = $this->personWithRelationship( $related_id );

		\Rondo\Fields\Fields::update_for_post( $person_id, 'relationships', [] );

		$this->assertInstanceOf( \WP_Post::class, wp_delete_post( $person_id, true ) );
		$this->assertNull( get_post( $person_id ) );
	}

	public function test_relationship_to_trashed_person_does_not_block_deletion(): void {
		$related_id = $this->createPerson( [ 'post_title' => 'Verwijderd lid' ] );
		$person_id  = $this->personWithRelationship( $related_id );

		$this->assertInstanceOf( \WP_Post::class, wp_trash_post( $related_id ) );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_post( $person_id, true ) );
	}

	public function test_rest_delete_returns_descriptive_conflict(): void {
		$admin_id   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$related_id = $this->createPerson( [ 'post_title' => 'Sem Hummeling' ] );
		$person_id  = $this->personWithRelationship( $related_id );
		wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request( 'DELETE', '/wp/v2/people/' . $person_id );
		$result  = $this->guard->prevent_rest_delete( null, rest_get_server(), $request );

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_person_has_relationships', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertStringContainsString( 'Sem Hummeling', $result->get_error_message() );
	}

	private function personWithRelationship( int $related_id ): int {
		return $this->createPerson(
			[ 'post_title' => 'Te verwijderen persoon' ],
			[
				'relationships' => [
					[
						'related_person'     => $related_id,
						'relationship_type'  => 3,
						'relationship_label' => '',
					],
				],
			]
		);
	}
}
