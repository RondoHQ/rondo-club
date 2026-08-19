<?php

namespace Tests\Wpunit;

use Rondo\REST\People;
use Tests\Support\RondoTestCase;

/**
 * Covers overlapping People characteristics and relationship-derived parents.
 */
class PeopleCharacteristicsFilterTest extends RondoTestCase {

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

	/**
	 * @param array<string,string> $filters Filter parameters.
	 * @return array<string,mixed>
	 */
	private function filtered_data( array $filters = [] ): array {
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'ownership', 'all' );
		$request->set_param( 'orderby', 'first_name' );
		$request->set_param( 'order', 'asc' );
		foreach ( $filters as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->controller->get_filtered_people( $request )->get_data();
	}

	private function add_current_child( int $parent_id, bool $former_child = false ): int {
		$child_id = $this->createPerson(
			[ 'post_title' => 'Child' ],
			[ 'former_member' => $former_child ]
		);
		\Rondo\Fields\Fields::update_for_post(
			$parent_id,
			'relationships',
			[
				[
					'related_person'    => $child_id,
					'relationship_type' => $this->child_relationship_type,
				],
			]
		);

		return $child_id;
	}

	public function test_overlapping_characteristics_combine_with_and(): void {
		$overlap_id = $this->createPerson(
			[ 'post_title' => 'Overlapping roles' ],
			[
				'first_name'          => 'Overlap',
				'spelactiviteit'      => 'Veldvoetbal',
				'knvb_id'             => 'KNVB123',
				'huidig_vrijwilliger' => true,
				'is_sponsor'          => true,
			]
		);
		$this->add_current_child( $overlap_id );

		$sponsor_only_id = $this->createPerson(
			[ 'post_title' => 'Sponsor only' ],
			[
				'first_name' => 'Sponsor',
				'is_sponsor' => true,
			]
		);

		$data = $this->filtered_data(
			[
				'spelend_lid'         => '1',
				'knvb_bekend'         => '1',
				'is_parent'           => '1',
				'huidig_vrijwilliger' => '1',
				'is_sponsor'          => '1',
			]
		);
		$ids  = array_column( $data['people'], 'id' );

		$this->assertContains( $overlap_id, $ids );
		$this->assertNotContains( $sponsor_only_id, $ids );

		$overlap = current( array_filter( $data['people'], static fn( $person ) => $person['id'] === $overlap_id ) );
		$this->assertSame(
			[
				'playing_member' => true,
				'knvb_known'     => true,
				'parent'         => true,
				'volunteer'      => true,
				'sponsor'        => true,
				'contact'        => false,
			],
			$overlap['characteristics']
		);
	}

	public function test_parent_filter_ignores_relationships_to_former_children(): void {
		$current_parent_id = $this->createPerson( [ 'post_title' => 'Current parent' ] );
		$former_parent_id  = $this->createPerson( [ 'post_title' => 'Former child parent' ] );
		$this->add_current_child( $current_parent_id );
		$this->add_current_child( $former_parent_id, true );

		$ids = array_column( $this->filtered_data( [ 'is_parent' => '1' ] )['people'], 'id' );

		$this->assertContains( $current_parent_id, $ids );
		$this->assertNotContains( $former_parent_id, $ids );
	}

	public function test_knvb_filter_supports_known_and_not_known(): void {
		$known_id   = $this->createPerson(
			[ 'post_title' => 'Known by KNVB' ],
			[ 'knvb_id' => 'KNOWN123' ]
		);
		$unknown_id = $this->createPerson( [ 'post_title' => 'Unknown by KNVB' ] );

		$known_ids   = array_column( $this->filtered_data( [ 'knvb_bekend' => '1' ] )['people'], 'id' );
		$unknown_ids = array_column( $this->filtered_data( [ 'knvb_bekend' => '0' ] )['people'], 'id' );

		$this->assertContains( $known_id, $known_ids );
		$this->assertNotContains( $unknown_id, $known_ids );
		$this->assertContains( $unknown_id, $unknown_ids );
		$this->assertNotContains( $known_id, $unknown_ids );
	}

	public function test_sponsor_filters_follow_company_relationships(): void {
		$person_id  = $this->createPerson( [ 'post_title' => 'Nieuw sponsorcontact' ] );
		$sponsor_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_sponsor',
				'post_status' => 'publish',
				'post_title'  => 'Relatie BV',
			]
		);
		\Rondo\Fields\Fields::update_for_post( $sponsor_id, 'sponsor_role', 'businessclub' );
		\Rondo\Sponsors\Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'receives_pass' => true,
				],
			]
			);

		$sponsor_data      = $this->filtered_data( [ 'is_sponsor' => '1' ] );
		$businessclub_data = $this->filtered_data( [ 'is_businessclub_member' => '1' ] );

		$this->assertContains( $person_id, array_column( $sponsor_data['people'], 'id' ) );
		$this->assertContains( $person_id, array_column( $businessclub_data['people'], 'id' ) );
		$person = current( array_filter( $sponsor_data['people'], static fn( array $item ): bool => $item['id'] === $person_id ) );
		$this->assertTrue( $person['characteristics']['sponsor'] );
	}
}
