<?php

namespace Tests\Wpunit;

use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/**
 * Multiple age groups must combine without bypassing person access or pagination.
 */
class PeopleAgeGroupFilterTest extends RondoTestCase {

	private \WP_REST_Server $server;
	private array $people;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->server = $this->bootRestControllers( [ People::class ] );
		$this->people = [];
		foreach ( [ 'Onder 6', 'Onder 7', 'Onder 13', 'Senioren' ] as $group ) {
			$this->people[ $group ] = $this->createPerson(
				[ 'post_title' => $group ],
				[
					'first_name'     => $group,
					'leeftijdsgroep' => $group,
				]
			);
		}
	}

	private function filtered_data( string $groups, int $page = 1, int $per_page = 100 ): array {
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/people/filtered' );
		$request->set_param( 'leeftijdsgroep', $groups );
		$request->set_param( 'page', $page );
		$request->set_param( 'per_page', $per_page );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	public function test_multiple_groups_return_the_union_and_accept_existing_single_values(): void {
		$data = $this->filtered_data( 'Onder 6,Onder 7,Onder 13' );
		$this->assertEqualsCanonicalizing(
			[ $this->people['Onder 6'], $this->people['Onder 7'], $this->people['Onder 13'] ],
			array_column( $data['people'], 'id' )
		);
		$this->assertSame( [ $this->people['Onder 7'] ], array_column( $this->filtered_data( 'Onder 7' )['people'], 'id' ) );
	}

	public function test_pagination_counts_the_combined_unique_people(): void {
		$first  = $this->filtered_data( 'Onder 6,Onder 7,Onder 6', 1, 1 );
		$second = $this->filtered_data( 'Onder 6,Onder 7,Onder 6', 2, 1 );
		$this->assertSame( 2, $first['total'] );
		$this->assertSame( 2, $first['total_pages'] );
		$this->assertEqualsCanonicalizing(
			[ $this->people['Onder 6'], $this->people['Onder 7'] ],
			array_merge( array_column( $first['people'], 'id' ), array_column( $second['people'], 'id' ) )
		);
	}

	public function test_unknown_groups_do_not_broaden_the_selection(): void {
		$this->assertSame( [], $this->filtered_data( 'Unknown,Other' )['people'] );
		$this->assertSame( [ $this->people['Onder 6'] ], array_column( $this->filtered_data( 'Onder 6,Unknown' )['people'], 'id' ) );
	}

	public function test_multiple_groups_remain_limited_to_permitted_age_groups(): void {
		$user_id = $this->createRondoUser();
		update_option( 'rondo_age_group_access', [ 'rondo_user' => [ 'Onder 6' ] ] );
		wp_set_current_user( $user_id );
		try {
			$data = $this->filtered_data( 'Onder 6,Onder 7,Senioren' );
			$this->assertSame( [ $this->people['Onder 6'] ], array_column( $data['people'], 'id' ) );
			$this->assertSame( 1, $data['total'] );
		} finally {
			delete_option( 'rondo_age_group_access' );
		}
	}
}
