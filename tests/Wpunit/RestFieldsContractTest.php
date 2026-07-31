<?php

namespace Tests\Wpunit;

use Rondo\Fields\Formatter;
use Rondo\Fields\RestFields;
use Tests\Support\RondoTestCase;

class RestFieldsContractTest extends RondoTestCase {

	private int $admin_id;

	protected function set_up(): void {
		parent::set_up();
		$this->ignoreIncorrectUsage( 'rest_handle_multi_type_schema' );
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
		$this->bootRestControllers( [ RestFields::class ] );
	}

	public function test_get_emits_canonical_fields_beside_acf(): void {
		$person_id = $this->createPerson(
			[],
			[
				'first_name'          => 'Jan',
				'knvb-id'             => '1234567',
				'datum-vog'           => '20260731',
				'huidig-vrijwilliger' => 0,
			]
		);

		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/people/' . $person_id ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'acf', $data );
		$this->assertArrayHasKey( 'fields', $data );
		$this->assertSame( '1234567', $data['fields']['knvb_id'] );
		$this->assertSame( '2026-07-31', $data['fields']['datum_vog'] );
		$this->assertFalse( $data['fields']['huidig_vrijwilliger'] );
		$this->assertArrayNotHasKey( 'knvb-id', $data['fields'] );
	}

	public function test_fields_write_is_partial_and_visible_through_acf(): void {
		$person_id = $this->createPerson(
			[],
			[
				'first_name' => 'Jan',
				'last_name'  => 'Jansen',
			]
			);
		$request   = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person_id );
		$request->set_body_params(
			[
				'fields' => [
					'first_name' => 'Piet',
					'datum_vog'  => '2026-07-31',
				],
			]
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $data ) );
		$this->assertSame( 'Piet', get_field( 'first_name', $person_id ) );
		$this->assertSame( 'Jansen', get_field( 'last_name', $person_id ), 'An omitted field must stay unchanged.' );
		$this->assertSame( '20260731', get_post_meta( $person_id, 'datum-vog', true ) );
		$this->assertSame( 'Piet', $data['acf']['first_name'] );
		$this->assertSame( 'Piet', $data['fields']['first_name'] );
	}

	public function test_empty_null_false_zero_and_projection_shapes_are_stable(): void {
		$person_id = $this->createPerson(
			[],
			[
				'first_name'          => 'Jan',
				'addresses'           => [],
				'freescout-id'        => 0,
				'huidig-vrijwilliger' => false,
			]
		);
		$request   = new \WP_REST_Request( 'GET', '/wp/v2/people/' . $person_id );
		$request->set_param( '_fields', 'id,fields.first_name,fields.birthdate,fields.addresses,fields.freescout_id,fields.huidig_vrijwilliger' );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( [ 'id', 'fields' ], array_keys( $data ) );
		$this->assertSame( 'Jan', $data['fields']['first_name'] );
		$this->assertNull( $data['fields']['birthdate'] );
		$this->assertSame( [], $data['fields']['addresses'] );
		$this->assertSame( 0, $data['fields']['freescout_id'] );
		$this->assertFalse( $data['fields']['huidig_vrijwilliger'] );

		$clear = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person_id );
		$clear->set_body_params(
			[
				'fields' => [
					'birthdate' => null,
					'addresses' => [],
				],
			]
			);
		$clear_response = rest_do_request( $clear );
		$this->assertSame( 200, $clear_response->get_status(), wp_json_encode( $clear_response->get_data() ) );
		$this->assertSame( '', get_post_meta( $person_id, 'birthdate', true ) );
		$this->assertSame( '', get_post_meta( $person_id, 'addresses', true ) );
		$this->assertEmpty( get_field( 'addresses', $person_id ) );
	}

	public function test_mixed_payload_is_rejected_before_either_provider_writes(): void {
		$person_id = $this->createPerson( [], [ 'first_name' => 'Jan' ] );
		$request   = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person_id );
		$request->set_body_params(
			[
				'acf'    => [ 'first_name' => 'Legacy' ],
				'fields' => [ 'first_name' => 'Canonical' ],
			]
		);

		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'ambiguous_field_payload', $response->get_data()['code'] );
		$this->assertSame( 'Jan', get_field( 'first_name', $person_id ) );
	}

	public function test_unknown_and_read_only_relationship_fields_return_field_specific_errors(): void {
		$person_id = $this->createPerson();

		$unknown = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person_id );
		$unknown->set_body_params( [ 'fields' => [ 'future_secret' => 'nope' ] ] );
		$unknown_response = rest_do_request( $unknown );

		$this->assertSame( 400, $unknown_response->get_status() );
		$this->assertSame( 'fields.future_secret', $unknown_response->get_data()['data']['field'] );

		$read_only = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person_id );
		$read_only->set_body_params(
			[
				'fields' => [
					'relationships' => [
						[
							'related_person_id' => 22,
							'person_name'       => 'Injected',
						],
					],
				],
			]
		);
		$read_only_response = rest_do_request( $read_only );

		$this->assertSame( 400, $read_only_response->get_status() );
		$this->assertSame( 'fields.relationships.0.person_name', $read_only_response->get_data()['data']['field'] );
		$this->assertStringContainsString( 'fields.relationships.0.person_name', $read_only_response->get_data()['data']['detail'] );
	}

	public function test_datetime_wire_format_preserves_dst_offset_and_round_trips_to_storage(): void {
		update_option( 'timezone_string', 'Europe/Amsterdam' );

		$wire    = Formatter::for_wire(
			'dienst_shift',
			[
				'start_datetime' => '2026-03-29 03:30:00',
				'end_datetime'   => '2026-10-25 02:30:00',
			]
		);
		$storage = Formatter::for_storage(
			'dienst_shift',
			[
				'start_datetime' => '2026-03-29T03:30:00+02:00',
				'end_datetime'   => '2026-10-25T02:30:00+01:00',
			]
		);

		$this->assertSame( '2026-03-29T03:30:00+02:00', $wire['start_datetime'] );
		$this->assertMatchesRegularExpression( '/^2026-10-25T02:30:00\+(?:01|02):00$/', $wire['end_datetime'] );
		$this->assertSame( '2026-03-29 03:30:00', $storage['start_datetime'] );
		$this->assertSame( '2026-10-25 02:30:00', $storage['end_datetime'] );
	}
}
