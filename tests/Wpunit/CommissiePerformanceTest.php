<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Commissies;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/** Regression coverage for the optimized commissie read paths. */
class CommissiePerformanceTest extends RondoTestCase {

	private WP_REST_Server $server;
	private int $commissie_id;

	protected function set_up(): void {
		parent::set_up();

		$this->server       = $this->bootRestControllers( [ Commissies::class ] );
		$this->commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Snelle commissie',
			]
		);

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	private function request( string $route ): WP_REST_Response {
		return $this->server->dispatch( new WP_REST_Request( 'GET', $route ) );
	}

	public function test_people_endpoint_only_loads_matching_people(): void {
		$other_commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Andere commissie',
			]
		);
		$current_id         = $this->person_with_roles(
			'Huidig commissielid',
			[
				$this->role( $this->commissie_id, 'Voorzitter', '' ),
				$this->role( $this->commissie_id, 'Secretaris', '' ),
			]
		);
		$former_id          = $this->person_with_roles(
			'Voormalig commissielid',
			[ $this->role( $this->commissie_id, 'Lid', '2020-01-01' ) ]
		);
		$other_id           = $this->person_with_roles(
			'Andere vrijwilliger',
			[ $this->role( $other_commissie_id, 'Lid', '' ) ]
		);

		$loaded_work_history = [];
		$observer            = static function ( $value, $object_id, $meta_key ) use ( &$loaded_work_history ) {
			if ( $meta_key === 'work_history' ) {
				$loaded_work_history[] = (int) $object_id;
			}
			return $value;
		};
		add_filter( 'get_post_metadata', $observer, 10, 3 );

		try {
			$response = $this->request( '/rondo/v1/commissies/' . $this->commissie_id . '/people' );
		} finally {
			remove_filter( 'get_post_metadata', $observer, 10 );
		}

		$data        = $response->get_data();
		$current_ids = array_map( 'intval', array_column( $data['current'], 'id' ) );
		$former_ids  = array_map( 'intval', array_column( $data['former'], 'id' ) );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $data ) );
		$this->assertSame( [ $current_id ], $current_ids );
		$this->assertSame( [ $former_id ], $former_ids );
		$this->assertNotContains( $other_id, $loaded_work_history );
	}

	public function test_member_count_is_not_computed_when_fields_exclude_it(): void {
		$controller = new Commissies();
		$request    = new WP_REST_Request( 'GET', '/wp/v2/commissies/' . $this->commissie_id );
		$request->set_param( '_fields', 'id,title,fields' );
		$response = new WP_REST_Response( [ 'id' => $this->commissie_id ] );

		$result = $controller->add_member_count_to_response(
			$response,
			get_post( $this->commissie_id ),
			$request
		);

		$this->assertSame( [ 'id' => $this->commissie_id ], $result->get_data() );
	}

	private function person_with_roles( string $title, array $roles ): int {
		$person_id = $this->createPerson( [ 'post_title' => $title ] );
		Fields::update_for_post( $person_id, 'work_history', $roles );
		return $person_id;
	}

	private function role( int $commissie_id, string $job_title, string $end_date ): array {
		return [
			'team'        => $commissie_id,
			'entity_type' => 'commissie',
			'job_title'   => $job_title,
			'start_date'  => '2019-01-01',
			'end_date'    => $end_date,
			'is_current'  => $end_date === '',
		];
	}
}
