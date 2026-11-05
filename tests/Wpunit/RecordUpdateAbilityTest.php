<?php

namespace Tests\Wpunit;

use Rondo\Abilities\Registrar;
use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/** Writes must retain the app's permissions, field services and sync detection. */
class RecordUpdateAbilityTest extends RondoTestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->bootRestControllers( [ People::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	private function person( array $fields = [] ): int {
		return $this->createPerson(
			[],
			array_merge(
			[
				'first_name' => 'Test',
				'email_1'    => 'old@example.test',
			],
			$fields
			)
			);
	}

	private function update( int $id, array $fields, ?array $expected = null ) {
		if ( $expected === null ) {
			$record = wp_get_ability( 'rondo/get-record' )->execute(
				[
					'id'     => $id,
					'fields' => array_keys( $fields ),
				]
				);
			$this->assertNotWPError( $record );
			$expected = $record['fields'];
		}
		return wp_get_ability( 'rondo/update-record' )->execute(
			[
				'id'              => $id,
				'fields'          => $fields,
				'expected_fields' => $expected,
			]
			);
	}

	public function test_email_write_runs_field_hooks_updates_modified_and_is_detectable_by_reverse_sync(): void {
		$id       = $this->person( [ 'email_2' => 'other@example.test' ] );
		$backdate = static function ( $data ) {
			$data['post_modified']     = '2020-01-01 00:00:00';
			$data['post_modified_gmt'] = '2020-01-01 00:00:00';
			return $data;
		};
		add_filter( 'wp_insert_post_data', $backdate );
		wp_update_post(
			[
				'ID'            => $id,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			]
			);
		remove_filter( 'wp_insert_post_data', $backdate );
		$old_time = get_post( $id )->post_modified_gmt;
		$changes  = [];
		add_action(
			'rondo_fields_updated',
			static function ( $post_id, $field ) use ( &$changes ) {
				$changes[] = [ $post_id, $field ];
			},
			10,
			2
			);
		$result = $this->update( $id, [ 'email_1' => 'new@example.test' ] );
		$this->assertNotWPError( $result );
		$this->assertSame( [ 'email_1' => 'new@example.test' ], $result['fields'] );
		$this->assertSame( 'new@example.test', get_post_meta( $id, 'email_1', true ) );
		$this->assertSame( 'other@example.test', Fields::get_for_post( $id, 'email_2' ) );
		$this->assertContains( [ $id, 'email_1' ], $changes );
		$this->assertGreaterThan( $old_time, get_post( $id )->post_modified_gmt );
		$request = new \WP_REST_Request( 'GET', '/wp/v2/people' );
		$request->set_query_params(
			[
				'modified_after' => '2021-01-01T00:00:00',
				'former_member'  => '0',
				'_fields'        => 'id,modified_gmt,fields',
			]
			);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertContains( $id, array_column( $response->get_data(), 'id' ) );
		$this->assertFalse( wp_get_ability( 'rondo/update-record' )->get_meta_item( 'annotations' )['readonly'] );
	}

	public function test_stale_expected_values_block_every_field_without_touching_record(): void {
		$id       = $this->person();
		$modified = get_post( $id )->post_modified_gmt;
		$result   = $this->update(
			$id,
			[
				'email_1'    => 'new@example.test',
				'first_name' => 'Changed',
			],
			[
				'email_1'    => 'stale@example.test',
				'first_name' => 'Test',
			]
			);
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_record_conflict', $result->get_error_code() );
		$this->assertSame( 'old@example.test', Fields::get_for_post( $id, 'email_1' ) );
		$this->assertSame( 'Test', Fields::get_for_post( $id, 'first_name' ) );
		$this->assertSame( $modified, get_post( $id )->post_modified_gmt );
	}

	public function test_rejects_unknown_legacy_readonly_and_invalid_values_before_saving(): void {
		$id = $this->person();
		foreach ( [ [ 'not_a_field' => 'x' ], [ 'first-name' => 'x' ], [ 'birthdate' => '2026-99-99' ], [ 'email_1' => [ 'wrong' ] ] ] as $fields ) {
			$fields   = array_merge( [ 'email_2' => 'should-not-save@example.test' ], $fields );
			$current  = ( new Registrar() )->get_record( [ 'id' => $id ] )['fields'];
			$expected = array_intersect_key( $current, $fields ) + array_fill_keys( array_keys( $fields ), null );
			$this->assertWPError( $this->update( $id, $fields, $expected ) );
			$this->assertSame( '', (string) Fields::get_for_post( $id, 'email_2' ) );
		}
		foreach ( \Rondo\Fields\Registry::fields_for( 'person' ) as $name => $definition ) {
			if ( ! empty( $definition['read_only'] ) ) {
				$this->assertWPError( $this->update( $id, [ $name => 'invalid' ] ) );
				return;
			}
		}
		$this->fail( 'Expected a read-only person field fixture.' );
	}

	public function test_rejects_core_meta_empty_and_mismatched_payloads_including_direct_calls(): void {
		$id   = $this->person();
		$base = [
			'id'              => $id,
			'fields'          => [ 'email_1' => 'new@example.test' ],
			'expected_fields' => [ 'email_1' => 'old@example.test' ],
		];
		foreach ( [ $base + [ 'meta' => [ 'email_1' => 'bad@example.test' ] ], $base + [ 'status' => 'trash' ], array_replace( $base, [ 'fields' => [] ] ), array_replace( $base, [ 'expected_fields' => [] ] ) ] as $input ) {
			$this->assertWPError( ( new Registrar() )->update_record( $input ) );
		}
		$this->assertSame( 'old@example.test', Fields::get_for_post( $id, 'email_1' ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_anonymous_unapproved_and_readonly_users_cannot_write(): void {
		$id = $this->person();
		foreach ( [ 0, self::factory()->user->create( [ 'role' => 'subscriber' ] ), $this->createRondoUser() ] as $user ) {
			wp_set_current_user( $user );
			$this->assertWPError( $this->update( $id, [ 'email_1' => 'new@example.test' ], [ 'email_1' => 'old@example.test' ] ) );
			$this->assertWPError(
				( new Registrar() )->update_record(
				[
					'id'              => $id,
					'fields'          => [ 'email_1' => 'new@example.test' ],
					'expected_fields' => [ 'email_1' => 'old@example.test' ],
				]
				)
				);
		}
		$this->assertSame( 'old@example.test', Fields::get_for_post( $id, 'email_1' ) );
	}

	public function test_former_member_guard_and_contact_field_scope_are_preserved(): void {
		$id = $this->person( [ 'former_member' => true ] );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_ledenadministratie' ] ) );
		$result = $this->update( $id, [ 'email_1' => 'new@example.test' ] );
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_former_member_readonly', $result->get_error_code() );
		Fields::update_for_post( $id, 'former_member', false );
		$user = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		get_user_by( 'id', $user )->add_cap( 'vrijwilligers' );
		wp_set_current_user( $user );
		AccessControl::flush_visible_person_ids_cache();
		$this->assertNotWPError( $this->update( $id, [ 'email_1' => 'new@example.test' ] ) );
		$this->assertWPError( $this->update( $id, [ 'first_name' => 'Forbidden' ], [ 'first_name' => 'Test' ] ) );
		$this->assertSame( 'Test', Fields::get_for_post( $id, 'first_name' ) );
	}

	public function test_repeater_replacement_clearing_and_canonical_dates_use_native_storage(): void {
		$id     = $this->person();
		$result = $this->update(
			$id,
			[
				'birthdate' => '2010-03-04',
				'addresses' => [
					[
						'address_label' => 'Home',
						'city'          => 'Test City',
					],
				],
			]
			);
		$this->assertNotWPError( $result );
		$this->assertSame( '2010-03-04', $result['fields']['birthdate'] );
		$this->assertSame( '20100304', get_post_meta( $id, 'birthdate', true ) );
		$this->assertSame( 'Test City', get_post_meta( $id, 'addresses_0_city', true ) );
		$this->assertNotWPError(
			$this->update(
			$id,
			[
				'birthdate' => null,
				'addresses' => [],
			]
			)
			);
		$this->assertSame( '', get_post_meta( $id, 'addresses_0_city', true ) );
		$this->assertSame( [], Fields::get_for_post( $id, 'addresses' ) );
	}

	public function test_rest_ability_execution_requires_post_and_returns_saved_fields(): void {
		$id    = $this->person();
		$input = [
			'id'              => $id,
			'fields'          => [ 'email_1' => 'new@example.test' ],
			'expected_fields' => [ 'email_1' => 'old@example.test' ],
		];
		$get   = new \WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/rondo/update-record/run' );
		$get->set_query_params( [ 'input' => $input ] );
		$this->assertGreaterThanOrEqual( 400, rest_do_request( $get )->get_status() );
		$this->assertSame( 'old@example.test', Fields::get_for_post( $id, 'email_1' ) );
		$post = new \WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/rondo/update-record/run' );
		$post->set_header( 'Content-Type', 'application/json' );
		$post->set_body( wp_json_encode( [ 'input' => $input ] ) );
		$response = rest_do_request( $post );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( [ 'email_1' => 'new@example.test' ], $response->get_data()['fields'] );
	}

	public function test_teams_and_committees_use_their_own_rest_routes(): void {
		foreach ( [ 'team', 'commissie' ] as $type ) {
			$id     = self::factory()->post->create(
				[
					'post_type'   => $type,
					'post_status' => 'publish',
					'post_title'  => 'Test record',
				]
				);
			$result = $this->update( $id, [ 'website' => 'https://example.test/' ] );
			$this->assertNotWPError( $result );
			$this->assertSame( $type, $result['type'] );
			$this->assertSame( [ 'website' => 'https://example.test/' ], $result['fields'] );
			$this->assertSame( 'https://example.test/', get_post_meta( $id, 'website', true ) );
		}
	}

	public function test_hidden_fields_cannot_be_written_even_when_the_record_is_editable(): void {
		$id = $this->person( [ 'financiele_blokkade' => false ] );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		$result = $this->update( $id, [ 'financiele_blokkade' => true ], [ 'financiele_blokkade' => false ] );
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_ability_field_unavailable', $result->get_error_code() );
		$this->assertFalse( (bool) Fields::get_for_post( $id, 'financiele_blokkade' ) );
	}

	public function test_incomplete_rest_pipeline_cannot_silently_write_or_skip_policies(): void {
		$id = $this->person();
		global $wp_filter, $wp_rest_additional_fields;
		$hooks = clone $wp_filter['rest_pre_insert_person'];
		remove_all_filters( 'rest_pre_insert_person' );
		add_filter( 'rest_pre_insert_person', static fn( $post ) => $post );
		try {
			$result = $this->update( $id, [ 'email_1' => 'new@example.test' ] );
			$this->assertWPError( $result );
			$this->assertSame( 'rondo_record_rest_unavailable', $result->get_error_code() );
		} finally {
			$wp_filter['rest_pre_insert_person'] = $hooks;
		}
		$provider = $wp_rest_additional_fields['person']['fields'];
		unset( $wp_rest_additional_fields['person']['fields'] );
		try {
			$this->assertWPError( $this->update( $id, [ 'email_1' => 'new@example.test' ] ) );
		} finally {
			$wp_rest_additional_fields['person']['fields'] = $provider;
		}
		$this->assertSame( 'old@example.test', Fields::get_for_post( $id, 'email_1' ) );
	}

	public function test_unsupported_and_trashed_records_are_rejected(): void {
		foreach ( [ self::factory()->post->create(), $this->person() ] as $id ) {
			if ( get_post_type( $id ) === 'person' ) {
				wp_trash_post( $id ); }
			$this->assertWPError( $this->update( $id, [ 'email_1' => 'new@example.test' ], [ 'email_1' => 'old@example.test' ] ) );
		}
	}
}
