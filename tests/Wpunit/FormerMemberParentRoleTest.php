<?php

namespace Tests\Wpunit;

use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/**
 * Covers the computed current-parent role on former-member profiles.
 */
class FormerMemberParentRoleTest extends RondoTestCase {

	private int $child_relationship_type;
	private People $controller;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new People();

		$term = term_exists( 'child', 'relationship_type' );
		if ( ! $term ) {
			$term = wp_insert_term( 'Child', 'relationship_type', [ 'slug' => 'child' ] );
		}

		$this->child_relationship_type = (int) ( is_array( $term ) ? $term['term_id'] : $term );
	}

	public function test_former_member_with_current_child_has_current_parent_role(): void {
		$parent_id = $this->createPerson(
			[ 'post_title' => 'Former member parent' ],
			[ 'former_member' => true ]
		);
		$child_id  = $this->createPerson( [ 'post_title' => 'Current child' ] );

		update_field(
			'relationships',
			[
				[
					'related_person'    => $child_id,
					'relationship_type' => $this->child_relationship_type,
				],
			],
			$parent_id
		);

		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/people/' . $parent_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['is_current_parent'] );
	}

	public function test_former_child_does_not_create_current_parent_role(): void {
		$parent_id = $this->createPerson(
			[ 'post_title' => 'Former member parent' ],
			[ 'former_member' => true ]
		);
		$child_id  = $this->createPerson(
			[ 'post_title' => 'Former child' ],
			[ 'former_member' => true ]
		);

		update_field(
			'relationships',
			[
				[
					'related_person'    => $child_id,
					'relationship_type' => $this->child_relationship_type,
				],
			],
			$parent_id
		);

		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/people/' . $parent_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['is_current_parent'] );
	}
}
