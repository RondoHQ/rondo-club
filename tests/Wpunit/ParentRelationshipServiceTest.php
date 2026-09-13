<?php

namespace Tests\Wpunit;

use Rondo\Data\InverseRelationships;
use Rondo\Fields\Fields;
use Rondo\People\ParentRelationshipService;
use Tests\Support\RondoTestCase;

class ParentRelationshipServiceTest extends RondoTestCase {

	private int $parent_type_id;
	private int $child_type_id;

	protected function set_up(): void {
		parent::set_up();
		new InverseRelationships();
		$this->parent_type_id = $this->relationship_type( 'parent', 'Ouder' );
		$this->child_type_id  = $this->relationship_type( 'child', 'Kind' );
		Fields::update_for_term( 'relationship_type', $this->parent_type_id, 'inverse_relationship_type', $this->child_type_id );
		Fields::update_for_term( 'relationship_type', $this->child_type_id, 'inverse_relationship_type', $this->parent_type_id );
	}

	private function observation_fixture(): array {
		$parent = $this->createPerson(
			[],
			[
				'first_name' => 'Noor',
				'last_name'  => 'van Dijk',
				'email_1'    => 'noor@example.org',
			]
			);
		$child  = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'OBS123',
			]
			);
		Fields::update_for_post(
			$child,
			'relationships',
			[
				[
					'related_person'    => $parent,
					'relationship_type' => $this->parent_type_id,
				],
			]
			);
		return [
			$child,
			$parent,
			[
				[
					'slot'  => 1,
					'email' => 'NOOR@example.org',
					'name'  => 'Noor van Dijk',
				],
				[
					'slot'  => 2,
					'email' => '',
					'name'  => '',
				],
			],
		];
	}

	public function test_import_labels_existing_parents_without_modifying_people_or_callback_state(): void {
		[ $child, $parent, $slots ] = $this->observation_fixture();
		$before                     = get_post( $child )->post_modified_gmt;
		$service                    = new ParentRelationshipService();
		$result                     = $service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots );
		$this->assertSame( 1, $result['matched'] );
		$status = $service->get_sync_statuses( $child )[0];
		$this->assertSame( $parent, $status['parent_id'] );
		$this->assertSame( 1, $status['slot'] );
		$this->assertSame( 'sportlink_import', $status['source'] );
		$this->assertSame( '', get_post_meta( $child, '_rondo_parent_sync_statuses', true ) );
		$this->assertSame( $before, get_post( $child )->post_modified_gmt );
		$this->assertSame( 'noor@example.org', Fields::get_for_post( $parent, 'email_1' ) );
		$this->assertSame( $result, $service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots ) );
	}

	public function test_import_preserves_pending_errors_and_more_recent_confirmations(): void {
		[ $child, $parent, $slots ] = $this->observation_fixture();
		$service                    = new ParentRelationshipService();
		foreach ( [ 'pending', 'error', 'synced' ] as $state ) {
			$service->set_sync_status( $child, $parent, $state, 2, 'Keep this status' );
			$before = get_post_meta( $child, '_rondo_parent_sync_statuses', true );
			$service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots );
			$this->assertSame( array_values( $before ), $service->get_sync_statuses( $child ) );
			$this->assertSame( $before, get_post_meta( $child, '_rondo_parent_sync_statuses', true ) );
		}
	}

	public function test_new_observation_refreshes_and_clears_slots_but_stale_snapshot_cannot_restore_them(): void {
		[ $child, $parent, $slots ] = $this->observation_fixture();
		$service                    = new ParentRelationshipService();
		$service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots );
		$swapped            = [ $slots[1], $slots[0] ];
		$swapped[0]['slot'] = 1;
		$swapped[1]['slot'] = 2;
		$service->observe_parent_slots( $child, 'OBS123', '2020-01-02T12:00:00Z', $swapped );
		$this->assertSame( 2, $service->get_sync_statuses( $child )[0]['slot'] );
		$empty = [
			[
				'slot'  => 1,
				'email' => '',
				'name'  => '',
			],
			[
				'slot'  => 2,
				'email' => '',
				'name'  => '',
			],
		];
		$service->observe_parent_slots( $child, 'OBS123', '2020-01-03T12:00:00Z', $empty );
		$this->assertSame( [], $service->get_sync_statuses( $child ) );
		$this->assertSame( 'stale', $service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots )['reason'] );
		$this->assertSame( [], $service->get_sync_statuses( $child ) );
	}

	public function test_import_rejects_wrong_identity_and_future_dates_and_ignores_ambiguous_slots(): void {
		[ $child, $parent, $slots ] = $this->observation_fixture();
		$service                    = new ParentRelationshipService();
		$this->assertWPError( $service->observe_parent_slots( $child, 'WRONG123', '2020-01-01T12:00:00Z', $slots ) );
		$this->assertWPError( $service->observe_parent_slots( $child, 'OBS123', '2999-01-01T12:00:00Z', $slots ) );
		$this->assertSame( '', get_post_meta( $child, '_rondo_parent_slot_observation', true ) );
		$slots[1] = array_merge( $slots[0], [ 'slot' => 2 ] );
		$this->assertSame( 0, $service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots )['matched'] );
		$this->assertSame( [], $service->get_sync_statuses( $child ) );
	}

	public function test_import_requires_matching_name_and_email_of_a_current_linked_parent(): void {
		[ $child, $parent, $slots ] = $this->observation_fixture();
		$service                    = new ParentRelationshipService();
		$slots[0]['name']           = 'Different Person';
		$this->assertSame( 0, $service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots )['matched'] );
		$slots[0]['name'] = 'Noor van Dijk';
		$service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots );
		Fields::update_for_post( $child, 'relationships', [] );
		$this->assertSame( [], $service->get_sync_statuses( $child ) );
	}

	public function test_shared_email_requires_unique_name_and_secondary_email_is_supported(): void {
		[ $child, $parent, $slots ] = $this->observation_fixture();
		$other                      = $this->createPerson(
			[],
			[
				'first_name' => 'Robin',
				'email_1'    => 'robin@example.org',
				'email_2'    => 'noor@example.org',
			]
			);
		Fields::update_for_post(
			$child,
			'relationships',
			[
				[
					'related_person'    => $parent,
					'relationship_type' => $this->parent_type_id,
				],
				[
					'related_person'    => $other,
					'relationship_type' => $this->parent_type_id,
				],
			]
			);
		$service          = new ParentRelationshipService();
		$slots[0]['name'] = '';
		$this->assertSame( 0, $service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots )['matched'] );
		$slots[0]['name'] = 'Robin';
		$this->assertSame( 1, $service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots )['matched'] );
		$this->assertSame( $other, $service->get_sync_statuses( $child )[0]['parent_id'] );
		Fields::update_for_post( $child, 'former_member', true );
		$this->assertSame( 'inactive', $service->observe_parent_slots( $child, 'OBS123', '2020-01-02T12:00:00Z', $slots )['reason'] );
	}

	public function test_new_snapshot_supersedes_old_success_but_never_pending_or_error(): void {
		[ $child, $parent, $slots ] = $this->observation_fixture();
		$service                    = new ParentRelationshipService();
		foreach ( [ 'synced', 'pending', 'error' ] as $state ) {
			update_post_meta(
				$child,
				'_rondo_parent_sync_statuses',
				[
					$parent => [
						'parent_id'  => $parent,
						'state'      => $state,
						'slot'       => 2,
						'updated_at' => '2019-01-01T12:00:00Z',
						'message'    => '',
					],
				]
				);
			$service->observe_parent_slots( $child, 'OBS123', '2020-01-01T12:00:00Z', $slots );
			$status = $service->get_sync_statuses( $child )[0];
			$this->assertSame( $state, $status['state'] );
			$this->assertSame( $state === 'synced' ? 1 : 2, $status['slot'] );
		}
	}

	public function test_new_parent_is_created_linked_and_marked_pending(): void {
		$child   = $this->createPerson(
			[ 'post_title' => 'Kind' ],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'TEST123',
			]
			);
		$service = new ParentRelationshipService();
		$result  = $service->add_parent(
			$child,
			[
				'mode'  => 'new',
				'name'  => 'Noor van Dijk',
				'email' => 'NOOR@EXAMPLE.ORG',
				'phone' => '06 12345678',
			]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['created'] );
		$parent = (int) $result['parent_id'];
		$this->assertSame( 'Noor van Dijk', Fields::get_for_post( $parent, 'first_name' ) );
		$this->assertSame( 'noor@example.org', Fields::get_for_post( $parent, 'email_1' ) );
		$this->assertSame( '06 12345678', Fields::get_for_post( $parent, 'telephone_1' ) );

		$relationships = Fields::get_for_post( $child, 'relationships' );
		$this->assertCount( 1, $relationships );
		$this->assertSame( $parent, $relationships[0]['related_person'] );
		$this->assertSame( $this->parent_type_id, $relationships[0]['relationship_type'] );

		$inverse = Fields::get_for_post( $parent, 'relationships' );
		$this->assertCount( 1, $inverse );
		$this->assertSame( $child, $inverse[0]['related_person'] );
		$this->assertSame( $this->child_type_id, $inverse[0]['relationship_type'] );
		$this->assertSame( 'pending', $service->get_sync_statuses( $child )[0]['state'] );
	}

	public function test_existing_parent_requires_name_and_email(): void {
		$child  = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'TEST124',
			]
			);
		$parent = $this->createPerson( [], [ 'first_name' => 'Zonder mail' ] );
		$result = ( new ParentRelationshipService() )->add_parent(
			$child,
			[
				'mode'      => 'existing',
				'parent_id' => $parent,
			]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_parent_contact_required', $result->get_error_code() );
	}

	public function test_new_parent_reuses_neither_existing_email_nor_person(): void {
		$this->createPerson(
			[],
			[
				'first_name' => 'Bestaand',
				'email_1'    => 'ouder@example.org',
			]
			);
		$child  = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'TEST125',
			]
			);
		$result = ( new ParentRelationshipService() )->add_parent(
			$child,
			[
				'mode'  => 'new',
				'name'  => 'Dubbel',
				'email' => 'ouder@example.org',
			]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_parent_email_exists', $result->get_error_code() );
	}

	public function test_third_parent_is_blocked_before_creation(): void {
		$child = $this->createPerson(
			[],
			[
				'first_name' => 'Kind',
				'knvb_id'    => 'TEST126',
			]
			);
		$rows  = [];
		foreach ( [ 1, 2 ] as $index ) {
			$parent = $this->createPerson(
				[],
				[
					'first_name' => 'Ouder ' . $index,
					'email_1'    => "ouder{$index}@example.org",
				]
				);
			$rows[] = [
				'related_person'    => $parent,
				'relationship_type' => $this->parent_type_id,
			];
		}
		Fields::update_for_post( $child, 'relationships', $rows );

		$result = ( new ParentRelationshipService() )->add_parent(
			$child,
			[
				'mode'  => 'new',
				'name'  => 'Derde ouder',
				'email' => 'derde@example.org',
			]
		);
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_parent_slots_full', $result->get_error_code() );
	}

	private function relationship_type( string $slug, string $name ): int {
		$term = get_term_by( 'slug', $slug, 'relationship_type' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $name, 'relationship_type', [ 'slug' => $slug ] );
		$this->assertIsArray( $created );
		return (int) $created['term_id'];
	}
}
