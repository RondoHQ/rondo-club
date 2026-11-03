<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Fields\Registry;
use Tests\Support\RondoTestCase;

class IntegerFieldContractTest extends RondoTestCase {

	public function test_integer_formatter_preserves_whole_values_and_rejects_invalid_values(): void {
		foreach ( [ 0, 0.0, '0', 42, 42.0, '42', PHP_INT_MAX ] as $value ) {
			$this->assertSame( (int) $value, Formatter::for_storage( 'dienst_type', [ 'survey_id' => $value ] )['survey_id'] );
			$this->assertSame( (int) $value, Formatter::for_wire( 'dienst_type', [ 'survey_id' => $value ] )['survey_id'] );
		}
		foreach ( [ null, '' ] as $value ) {
			$this->assertNull( Formatter::for_storage( 'dienst_type', [ 'survey_id' => $value ] )['survey_id'] );
			$this->assertNull( Formatter::for_wire( 'dienst_type', [ 'survey_id' => $value ] )['survey_id'] );
		}
		foreach ( [ 1.5, '1.5', -1, true, false, [], 'invalid', INF, NAN, '9223372036854775808' ] as $value ) {
			try {
				Formatter::for_storage( 'dienst_type', [ 'survey_id' => $value ] );
				$this->fail( 'Invalid integer must be rejected.' );
			} catch ( \InvalidArgumentException $error ) {
				$this->assertStringContainsString( 'fields.survey_id', $error->getMessage() );
			}
		}
		$this->assertSame( 1.5, Formatter::for_storage( 'person', [ 'freescout_id' => 1.5 ] )['freescout_id'], 'Number fields must still support fractions.' );
	}

	public function test_integer_bounds_and_required_values_are_enforced(): void {
		$definition = [
			'min'      => 2,
			'max'      => 4,
			'required' => true,
		];
		$this->assertSame( 3, Formatter::normalize_integer( $definition, '3', 'fields.test' ) );
		foreach ( [ 1, 5, null, '' ] as $value ) {
			try {
				Formatter::normalize_integer( $definition, $value, 'fields.test' );
				$this->fail( 'Invalid bounded integer must be rejected.' );
			} catch ( \InvalidArgumentException $error ) {
				$this->assertStringContainsString( 'fields.test', $error->getMessage() );
			}
		}
	}

	public function test_php_writes_normalize_before_domain_validation_and_preserve_storage(): void {
		$type   = self::factory()->post->create( [ 'post_type' => 'dienst_type' ] );
		$seen   = [];
		$filter = static function ( $value, $post_id, $definition ) use ( $type, &$seen ) {
			if ( $post_id === $type && $definition['canonical_name'] === 'survey_id' ) {
				$seen[] = $value;
			}
			return $value;
		};
		add_filter( 'rondo_fields_validate_value', $filter, 9, 3 );
		try {
			foreach ( [ 0, 0.0, '0' ] as $value ) {
				$this->assertTrue( Fields::update_for_post( $type, 'survey_id', $value ) );
				$this->assertSame( 0, Fields::get_for_post( $type, 'survey_id' ) );
			}
		} finally {
			remove_filter( 'rondo_fields_validate_value', $filter, 9 );
		}
		$this->assertSame( [ 0, 0, 0 ], $seen );
		$this->assertSame( '0', get_post_meta( $type, 'survey_id', true ) );
		$this->assertSame( 'field_dienst_type_survey_id', get_post_meta( $type, '_survey_id', true ) );
		$this->assertWPError(
			Fields::update_many_for_post(
			$type,
			[
				'description' => 'Must not persist',
				'survey_id'   => 1.5,
			]
			)
			);
		$this->assertSame( '', get_post_meta( $type, 'description', true ) );
		$this->assertSame( 0, Fields::get_for_post( $type, 'survey_id' ) );
		$this->assertTrue( Fields::update_for_post( $type, 'survey_id', null ) );
		$this->assertNull( Fields::get_for_post( $type, 'survey_id' ) );
	}

	public function test_rest_and_wordpress_metadata_advertise_integer_contract(): void {
			( new \Rondo\Fields\FieldSchema() )->register();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$server   = $this->bootRestControllers( [] );
		$response = $server->dispatch( new \WP_REST_Request( 'OPTIONS', '/wp/v2/dienst-types' ) );
		$this->assertSame( 200, $response->get_status() );
		$schema = $response->get_data()['schema']['properties']['fields']['properties']['survey_id'];
		$this->assertSame( [ 'integer', 'null' ], $schema['type'] );
		$this->assertSame( 0, $schema['minimum'] );
		$this->assertSame( 'integer', get_registered_meta_keys( 'post', 'dienst_type' )['survey_id']['type'] );
		$this->assertSame( 'integer', Registry::resolve( 'dienst_type', 'survey_id' )['type'] );
	}

	public function test_rest_rejects_fractional_ids_before_creating_or_updating_a_duty_type(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$server = $this->bootRestControllers( [] );
		$before = (int) wp_count_posts( 'dienst_type' )->publish;
		foreach ( [ 1.5, '1.5', -1, true, 'invalid' ] as $value ) {
			$request = new \WP_REST_Request( 'POST', '/wp/v2/dienst-types' );
			$request->set_body_params(
				[
					'title'  => 'Must not be created',
					'status' => 'publish',
					'fields' => [ 'survey_id' => $value ],
				]
				);
			$this->assertSame( 400, $server->dispatch( $request )->get_status() );
		}
		$this->assertSame( $before, (int) wp_count_posts( 'dienst_type' )->publish );

		$type = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
			]
			);
		update_post_meta( $type, 'survey_id', '0' );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/dienst-types/' . $type );
		$request->set_body_params(
			[
				'fields' => [
					'description' => 'Must not persist',
					'survey_id'   => 1.5,
				],
			]
			);
		$this->assertSame( 400, $server->dispatch( $request )->get_status() );
		$this->assertSame( '', get_post_meta( $type, 'description', true ) );
		$this->assertSame( '0', get_post_meta( $type, 'survey_id', true ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$request->set_body_params( [ 'fields' => [ 'survey_id' => 0 ] ] );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
	}
}
