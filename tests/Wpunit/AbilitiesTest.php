<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Contract and access tests for Rondo's WordPress Abilities API surface.
 */
class AbilitiesTest extends RondoTestCase {

	public function test_registers_public_readonly_abilities(): void {
		$category = wp_get_ability_category( 'rondo-records' );
		$this->assertNotNull( $category );

		foreach ( [ 'rondo/search-records', 'rondo/get-record', 'rondo/get-field-schema' ] as $name ) {
			$ability = wp_get_ability( $name );
			$this->assertNotNull( $ability, "Ability {$name} should be registered." );
			$this->assertSame( 'rondo-records', $ability->get_category() );
			$this->assertTrue( $ability->get_meta_item( 'public' ) );
			$this->assertTrue( $ability->get_meta_item( 'show_in_rest' ) );
			$this->assertSame( [ 'public' => true ], $ability->get_meta_item( 'mcp' ) );
			$this->assertSame(
				[
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				$ability->get_meta_item( 'annotations' )
			);
		}
	}

	public function test_anonymous_user_cannot_execute_abilities(): void {
		wp_set_current_user( 0 );

		$result = wp_get_ability( 'rondo/search-records' )->execute( [ 'query' => 'Ajax' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );

		$server             = rest_get_server();
		$discovery_response = $server->dispatch( new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' ) );
		$this->assertSame( 401, $discovery_response->get_status() );

		$run = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/rondo/search-records/run' );
		$run->set_query_params( [ 'input' => [ 'query' => 'Ajax' ] ] );
		$run_response = $server->dispatch( $run );
		$this->assertSame( 401, $run_response->get_status() );
	}

	public function test_search_respects_person_visibility_and_context_filter(): void {
		$user_id = $this->createRondoUser();
		wp_set_current_user( $user_id );

		$visible_id = $this->createPerson(
			[ 'post_title' => 'Ajax Visible Member' ],
			[ 'first_name' => 'Ajax' ]
		);
		$this->createPerson(
			[ 'post_title' => 'Ajax Hidden Member' ],
			[ 'first_name' => 'Ajax' ]
		);
		update_user_meta( $user_id, 'rondo_linked_person_id', $visible_id );
		AccessControl::flush_visible_person_ids_cache();

		$result = wp_get_ability( 'rondo/search-records' )->execute(
			[
				'query'    => 'Ajax',
				'contexts' => [ 'person' ],
			]
		);

		$this->assertNotWPError( $result );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( [ $visible_id ], array_column( $result['records'], 'id' ) );
		$this->assertSame( [ 'person' ], array_values( array_unique( array_column( $result['records'], 'type' ) ) ) );
	}

	public function test_get_record_uses_canonical_field_visibility_and_selection(): void {
		$user_id = $this->createRondoUser();
		wp_set_current_user( $user_id );

		$person_id = $this->createPerson(
			[ 'post_title' => 'Visible Person' ],
			[
				'first_name'          => 'Visible',
				'last_name'           => 'Person',
				'financiele_blokkade' => true,
			]
		);
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		AccessControl::flush_visible_person_ids_cache();

		$all_fields = wp_get_ability( 'rondo/get-record' )->execute( [ 'id' => $person_id ] );
		$this->assertNotWPError( $all_fields );
		$this->assertSame( 'Visible', $all_fields['fields']['first_name'] );
		$this->assertArrayNotHasKey( 'financiele_blokkade', $all_fields['fields'] );

		$selected = wp_get_ability( 'rondo/get-record' )->execute(
			[
				'id'     => $person_id,
				'fields' => [ 'first_name' ],
			]
		);
		$this->assertNotWPError( $selected );
		$this->assertSame( [ 'first_name' => 'Visible' ], $selected['fields'] );

		$hidden = wp_get_ability( 'rondo/get-record' )->execute(
			[
				'id'     => $person_id,
				'fields' => [ 'financiele_blokkade' ],
			]
		);
		$this->assertWPError( $hidden );
		$this->assertSame( 'rondo_ability_field_unavailable', $hidden->get_error_code() );
	}

	public function test_get_record_denies_an_inaccessible_person(): void {
		$user_id = $this->createRondoUser();
		wp_set_current_user( $user_id );

		$self_id  = $this->createPerson( [ 'post_title' => 'Own Person' ] );
		$other_id = $this->createPerson( [ 'post_title' => 'Other Person' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $self_id );
		AccessControl::flush_visible_person_ids_cache();

		$result = wp_get_ability( 'rondo/get-record' )->execute( [ 'id' => $other_id ] );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_field_schema_is_client_safe_and_access_filtered(): void {
		$user_id = $this->createRondoUser();
		wp_set_current_user( $user_id );

		$result = wp_get_ability( 'rondo/get-field-schema' )->execute( [ 'context' => 'person' ] );

		$this->assertNotWPError( $result );
		$this->assertSame( 'person', $result['context'] );
		$this->assertGreaterThan( 0, $result['registry_version'] );

		$fields = array_column( $result['fields'], null, 'name' );
		$this->assertArrayHasKey( 'first_name', $fields );
		$this->assertArrayNotHasKey( 'financiele_blokkade', $fields );
		$this->assertArrayNotHasKey( 'freescout_id', $fields );
		$this->assertArrayNotHasKey( 'storage_name', $fields['first_name'] );
	}

	public function test_rest_discovery_and_readonly_execution(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );
		wp_set_current_user( $user_id );
		$person_id = $this->createPerson(
			[ 'post_title' => 'REST Ability Member' ],
			[ 'first_name' => 'REST' ]
		);

		$server    = rest_get_server();
		$discovery = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$discovery->set_param( 'namespace', 'rondo' );
		$discovery_response = $server->dispatch( $discovery );

		$this->assertSame( 200, $discovery_response->get_status() );
		$this->assertSame(
			[ 'rondo/search-records', 'rondo/get-record', 'rondo/get-field-schema' ],
			array_column( $discovery_response->get_data(), 'name' )
		);

		$run = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/rondo/search-records/run' );
		$run->set_query_params(
			[
				'input' => [
					'query'    => 'REST Ability',
					'contexts' => 'person',
					'limit'    => '5',
				],
			]
		);
		$run_response = $server->dispatch( $run );

		$this->assertSame( 200, $run_response->get_status() );
		$this->assertSame( [ $person_id ], array_column( $run_response->get_data()['records'], 'id' ) );
	}
}
