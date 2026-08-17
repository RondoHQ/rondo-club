<?php

namespace Tests\Wpunit;

use Rondo\Data\InverseRelationships;
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
}
