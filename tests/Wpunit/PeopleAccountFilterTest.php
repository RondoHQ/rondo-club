<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\People;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/** Account filtering must respect both link directions, pagination and record access. */
class PeopleAccountFilterTest extends RondoTestCase {

	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->server = $this->bootRestControllers( [ People::class ] );
	}

	private function request( array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/people/filtered' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	private function data( array $params = [] ): array {
		$response = $this->request( $params );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	private function link( int $person_id, bool $legacy = false ): int {
		$user_id = self::factory()->user->create();
		if ( $legacy ) {
			update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		} else {
			update_post_meta( $person_id, UserProvisioning::META_USER_ID, $user_id );
		}
		return $user_id;
	}

	public function test_both_link_directions_count_and_missing_or_deleted_accounts_do_not(): void {
		$post_link = $this->createPerson();
		$legacy    = $this->createPerson();
		$missing   = $this->createPerson();
		$deleted   = $this->createPerson();
		$zero      = $this->createPerson();
		$user_id   = $this->link( $post_link );
		update_user_meta( $user_id, 'rondo_linked_person_id', $post_link );
		$this->link( $legacy, true );
		update_post_meta( $deleted, UserProvisioning::META_USER_ID, 99999999 );
		update_post_meta( $zero, UserProvisioning::META_USER_ID, 0 );

		$this->assertEqualsCanonicalizing( [ $post_link, $legacy ], array_column( $this->data( [ 'has_rondo_account' => '1' ] )['people'], 'id' ) );
		$this->assertEqualsCanonicalizing( [ $missing, $deleted, $zero ], array_column( $this->data( [ 'has_rondo_account' => '0' ] )['people'], 'id' ) );
		$this->assertSame( 5, $this->data()['total'] );
		$this->assertSame( 5, $this->data( [ 'has_rondo_account' => '' ] )['total'] );
		$this->assertFalse( metadata_exists( 'post', $legacy, UserProvisioning::META_USER_ID ), 'Filtering must not backfill legacy links.' );
	}

	public function test_empty_account_set_and_invalid_filter(): void {
		$person_id = $this->createPerson();
		$this->assertSame( [], $this->data( [ 'has_rondo_account' => '1' ] )['people'] );
		$this->assertSame( [ $person_id ], array_column( $this->data( [ 'has_rondo_account' => '0' ] )['people'], 'id' ) );
		$this->assertSame( 400, $this->request( [ 'has_rondo_account' => 'invalid' ] )->get_status() );
	}

	public function test_parent_filter_pagination_and_totals_use_the_same_selection(): void {
		$child = $this->createPerson();
		$term  = term_exists( 'child', 'relationship_type' ) ?: wp_insert_term( 'Child', 'relationship_type', [ 'slug' => 'child' ] );
		$ids   = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$id = $this->createPerson( [], [ 'first_name' => 'Parent ' . $i ] );
			Fields::update_for_post(
				$id,
				'relationships',
				[
					[
						'related_person'    => $child,
						'relationship_type' => (int) $term['term_id'],
					],
				]
				);
			if ( $i === 0 ) {
				$this->link( $id );
			} else {
				$ids[] = $id;
			}
		}
		$params = [
			'is_parent'         => '1',
			'has_rondo_account' => '0',
			'per_page'          => 1,
		];
		$first  = $this->data( $params );
		$second = $this->data( array_merge( $params, [ 'page' => 2 ] ) );
		$this->assertSame( 2, $first['total'] );
		$this->assertSame( 2, $first['total_pages'] );
		$this->assertEqualsCanonicalizing( $ids, array_merge( array_column( $first['people'], 'id' ), array_column( $second['people'], 'id' ) ) );
	}

	public function test_both_account_filters_preserve_age_group_access(): void {
		$visible_with    = $this->createPerson( [], [ 'leeftijdsgroep' => 'Onder 13' ] );
		$visible_without = $this->createPerson( [], [ 'leeftijdsgroep' => 'Onder 13' ] );
		$hidden_with     = $this->createPerson( [], [ 'leeftijdsgroep' => 'Onder 14' ] );
		$this->createPerson( [], [ 'leeftijdsgroep' => 'Onder 14' ] );
		$this->link( $visible_with, true );
		$this->link( $hidden_with );
		$user_id = $this->createRondoUser();
		update_option( 'rondo_age_group_access', [ 'rondo_user' => [ 'Onder 13' ] ] );
		wp_set_current_user( $user_id );
		try {
			$this->assertSame( [ $visible_with ], array_column( $this->data( [ 'has_rondo_account' => '1' ] )['people'], 'id' ) );
			$this->assertSame( [ $visible_without ], array_column( $this->data( [ 'has_rondo_account' => '0' ] )['people'], 'id' ) );
		} finally {
			delete_option( 'rondo_age_group_access' );
		}
	}
}
