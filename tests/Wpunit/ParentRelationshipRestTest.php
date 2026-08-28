<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Data\InverseRelationships;
use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class ParentRelationshipRestTest extends RondoTestCase {

	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		new InverseRelationships();
		$this->ensure_relationship_terms();
		$this->server = $this->bootRestControllers( [ People::class ] );
	}

	public function test_administrator_can_create_parent_and_report_verified_slot(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );
		$child = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'REST123',
			]
			);

		$created = $this->request(
			'POST',
			'/rondo/v1/people/' . $child . '/parents',
			[
				'mode'  => 'new',
				'name'  => 'Rest Ouder',
				'email' => 'rest@example.org',
			]
		);
		$this->assertSame( 201, $created->get_status() );
		$parent_id = (int) $created->get_data()['parent_id'];

		$status = $this->request(
			'POST',
			'/rondo/v1/people/' . $child . '/parent-sync-status',
			[
				'parent_id' => $parent_id,
				'state'     => 'synced',
				'slot'      => 2,
			]
		);
		$this->assertSame( 200, $status->get_status() );

		$person = $this->request( 'GET', '/wp/v2/people/' . $child );
		$this->assertSame( 'synced', $person->get_data()['parent_sync_statuses'][0]['state'] );
		$this->assertSame( 2, $person->get_data()['parent_sync_statuses'][0]['slot'] );
	}

	public function test_plain_member_cannot_add_parent(): void {
		$user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user );
		$child    = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'REST124',
			]
			);
		$response = $this->request(
			'POST',
			'/rondo/v1/people/' . $child . '/parents',
			[
				'mode'  => 'new',
				'name'  => 'Geen Toegang',
				'email' => 'geen@example.org',
			]
		);
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_household_includes_the_other_parent_as_contact_only(): void {
		$current_parent = $this->createPerson( [], [ 'first_name' => 'Huidige ouder' ] );
		$other_parent   = $this->createPerson(
			[],
			[
				'first_name' => 'Andere ouder',
				'email_1'    => 'andere@example.org',
				'birthdate'  => '1980-01-01',
				'knvb_id'    => 'PRIVATE123',
			]
		);
		$child          = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'birthdate'  => gmdate( 'Y-m-d', strtotime( '-10 years' ) ),
				'knvb_id'    => 'CHILD123',
			]
		);
		$this->link_parent_to_child( $current_parent, $child );
		$this->link_parent_to_child( $other_parent, $child );
		$private_invoice = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_paid',
				'post_title'  => 'Private contribution',
			]
		);
		Fields::update_for_post( $private_invoice, 'invoice_number', 'C-PRIVATE' );
		Fields::update_for_post( $private_invoice, 'person', $other_parent );
		Fields::update_for_post( $private_invoice, 'status', 'paid' );
		Fields::update_for_post( $private_invoice, 'invoice_type', 'membership' );
		Fields::update_for_post( $private_invoice, 'total_amount', 200 );
		update_post_meta( $private_invoice, '_invoice_season', SeasonKey::current( wp_date( 'Y-m-d' ) ) );

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $current_parent );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$response = $this->request( 'GET', '/rondo/v1/people/household' );
		$people   = array_column( $response->get_data(), null, 'id' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'self', $people[ $current_parent ]['household_role'] );
		$this->assertSame( 'child', $people[ $child ]['household_role'] );
		$this->assertSame( 'other_parent', $people[ $other_parent ]['household_role'] );
		$this->assertFalse( $people[ $child ]['can_add_parent'] );
		$this->assertSame( 'andere@example.org', $people[ $other_parent ]['fields']['email_1'] );
		$this->assertArrayNotHasKey( 'birthdate', $people[ $other_parent ]['fields'] );
		$this->assertArrayNotHasKey( 'knvb_id', $people[ $other_parent ]['fields'] );
		$this->assertNull( $people[ $other_parent ]['membership_pass'] );
		$this->assertNull( $people[ $other_parent ]['contribution'] );
	}

	public function test_parent_can_add_a_new_other_parent_to_own_minor_child(): void {
		$current_parent = $this->createPerson( [], [ 'first_name' => 'Huidige ouder' ] );
		$child          = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'birthdate'  => gmdate( 'Y-m-d', strtotime( '-10 years' ) ),
				'knvb_id'    => 'CHILD124',
			]
		);
		$this->link_parent_to_child( $current_parent, $child );

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $current_parent );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$before = array_column( $this->request( 'GET', '/rondo/v1/people/household' )->get_data(), null, 'id' );
		$this->assertTrue( $before[ $child ]['can_add_parent'] );

		$created = $this->request(
			'POST',
			'/rondo/v1/people/' . $child . '/household-parent',
			[
				'name'  => 'Nieuwe ouder',
				'email' => 'nieuwe.ouder@example.org',
				'phone' => '0612345678',
			]
		);

		$this->assertSame( 201, $created->get_status() );
		$parent_id = (int) $created->get_data()['parent_id'];
		$after     = array_column( $this->request( 'GET', '/rondo/v1/people/household' )->get_data(), null, 'id' );
		$this->assertSame( 'other_parent', $after[ $parent_id ]['household_role'] );
		$this->assertFalse( $after[ $child ]['can_add_parent'] );
	}

	public function test_parent_cannot_add_a_parent_to_an_unrelated_child(): void {
		$current_parent = $this->createPerson( [], [ 'first_name' => 'Huidige ouder' ] );
		$unrelated      = $this->createPerson(
			[],
			[
				'first_name' => 'Onbekend kind',
				'birthdate'  => gmdate( 'Y-m-d', strtotime( '-10 years' ) ),
				'knvb_id'    => 'CHILD125',
			]
		);
		$user_id        = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $current_parent );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$response = $this->request(
			'POST',
			'/rondo/v1/people/' . $unrelated . '/household-parent',
			[
				'name'  => 'Niet toegestaan',
				'email' => 'niet.toegestaan@example.org',
			]
		);

		$this->assertSame( 403, $response->get_status() );
	}

	private function request( string $method, string $route, array $params = [] ) {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	private function ensure_relationship_terms(): void {
		$ids = [];
		foreach ( [
			'parent' => 'Ouder',
			'child'  => 'Kind',
		] as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, 'relationship_type' );
			if ( ! $term || is_wp_error( $term ) ) {
				$created      = wp_insert_term( $name, 'relationship_type', [ 'slug' => $slug ] );
				$ids[ $slug ] = (int) $created['term_id'];
			} else {
				$ids[ $slug ] = (int) $term->term_id;
			}
		}
		Fields::update_for_term( 'relationship_type', $ids['parent'], 'inverse_relationship_type', $ids['child'] );
		Fields::update_for_term( 'relationship_type', $ids['child'], 'inverse_relationship_type', $ids['parent'] );
	}

	private function link_parent_to_child( int $parent_id, int $child_id ): void {
		$relationships   = Fields::get_for_post( $parent_id, 'relationships' ) ?: [];
		$relationships[] = [
			'related_person'    => $child_id,
			'relationship_type' => InverseRelationships::TYPE_CHILD,
		];
		Fields::update_for_post( $parent_id, 'relationships', $relationships );
		AccessControl::flush_visible_person_ids_cache();
	}
}
