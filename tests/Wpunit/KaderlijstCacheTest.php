<?php

namespace Tests\Wpunit;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\Fields\RestFields;
use Rondo\REST\Api;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Scope safety, invalidation, and response parity for the Kaderlijst cache.
 */
class KaderlijstCacheTest extends RondoTestCase {

	private Api $controller;

	protected function set_up(): void {
		parent::set_up();
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_kaderlijst_cache_generation' );
		delete_option( VolunteerStatus::OPTION_PLAYER_ROLES );
		$this->controller = new Api();
	}

	protected function tear_down(): void {
		remove_role( 'rondo_kader_scope_a' );
		remove_role( 'rondo_kader_scope_b' );
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_kaderlijst_cache_generation' );
		delete_option( VolunteerStatus::OPTION_PLAYER_ROLES );
		parent::tear_down();
	}

	public function test_response_is_cached_and_manual_refresh_rebuilds_it(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$person_id = $this->create_kader_person( 'Voor cache' );

		$first  = $this->people();
		$fields = $this->person_fields( $first, $person_id );
		$this->assertSame( 'Voor cache', $fields['first_name'] );
		$this->assertEquals(
			array_intersect_key(
				RestFields::for_post( 'person', $person_id ),
				array_flip(
					[
						'first_name',
						'infix',
						'last_name',
						'work_history',
						'email_1',
						'email_2',
						'mobile_1',
						'mobile_2',
						'telephone_1',
						'telephone_2',
					]
				)
			),
			$fields,
			'The optimized field reader must preserve the existing REST wire payload.'
		);

		// Bypass the domain field hook to prove the second request is a cache hit.
		update_post_meta( $person_id, 'first_name', 'Na cache' );
		$cached = $this->people();
		$this->assertSame( 'Voor cache', $this->person_fields( $cached, $person_id )['first_name'] );

		$refreshed = $this->people( true );
		$this->assertSame( 'Na cache', $this->person_fields( $refreshed, $person_id )['first_name'] );
	}

	public function test_native_field_write_invalidates_cached_response(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$person_id = $this->create_kader_person( 'Oud' );

		$this->assertSame( 'Oud', $this->person_fields( $this->people(), $person_id )['first_name'] );

		Fields::update_for_post( $person_id, 'first_name', 'Nieuw' );

		$this->assertSame( 'Nieuw', $this->person_fields( $this->people(), $person_id )['first_name'] );
	}

	public function test_coordinator_caches_do_not_cross_age_group_scopes(): void {
		add_role( 'rondo_kader_scope_a', 'Kader scope A', [ 'read' => true ] );
		add_role( 'rondo_kader_scope_b', 'Kader scope B', [ 'read' => true ] );
		update_option(
			'rondo_age_group_access',
			[
				'rondo_kader_scope_a' => [ 'Onder 10' ],
				'rondo_kader_scope_b' => [ 'Onder 12' ],
			]
		);

		$team_a  = $this->createOrganization( [ 'post_title' => 'JO10-1' ] );
		$team_b  = $this->createOrganization( [ 'post_title' => 'JO12-1' ] );
		$coach_a = $this->create_kader_person( 'Coach tien', $team_a );
		$coach_b = $this->create_kader_person( 'Coach twaalf', $team_b );
		$this->create_player( 'Speler tien', 'Onder 10', $team_a );
		$this->create_player( 'Speler twaalf', 'Onder 12', $team_b );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_kader_scope_a' ] ) );
		$this->assertSame( [ $coach_a ], $this->person_ids( $this->people() ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_kader_scope_b' ] ) );
		$this->assertSame( [ $coach_b ], $this->person_ids( $this->people() ) );
	}

	public function test_player_role_setting_is_part_of_cache_identity(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$person_id = $this->create_kader_person( 'Wordt speler' );

		$this->assertSame( [ $person_id ], $this->person_ids( $this->people() ) );

		$player_roles   = VolunteerStatus::get_player_roles();
		$player_roles[] = 'Trainer';
		update_option( VolunteerStatus::OPTION_PLAYER_ROLES, $player_roles );

		$this->assertSame( [], $this->person_ids( $this->people() ) );
	}

	private function create_kader_person( string $first_name, int $team_id = 0 ): int {
		return $this->createPerson(
			[ 'post_title' => $first_name ],
			[
				'first_name'   => $first_name,
				'last_name'    => 'Kaderlid',
				'email_1'      => 'kader@example.com',
				'work_history' => [
					[
						'team'       => $team_id,
						'job_title'  => 'Trainer',
						'start_date' => '2025-01-01',
						'end_date'   => '',
						'is_current' => true,
					],
				],
			]
		);
	}

	private function create_player( string $first_name, string $age_group, int $team_id ): int {
		return $this->createPerson(
			[ 'post_title' => $first_name ],
			[
				'first_name'     => $first_name,
				'last_name'      => 'Speler',
				'leeftijdsgroep' => $age_group,
				'work_history'   => [
					[
						'team'       => $team_id,
						'job_title'  => 'Speler',
						'start_date' => '2025-01-01',
						'end_date'   => '',
						'is_current' => true,
					],
				],
			]
		);
	}

	/** @return array<int, array{id:int, fields:array}> */
	private function people( bool $refresh = false ): array {
		$request = new WP_REST_Request( 'GET', '/rondo/v1/kaderlijst/people' );
		if ( $refresh ) {
			$request->set_param( 'refresh', true );
		}

		return $this->controller->get_kaderlijst_people( $request )->get_data()['people'] ?? [];
	}

	/** @param array<int, array{id:int, fields:array}> $people */
	private function person_ids( array $people ): array {
		$ids = array_map( static fn( array $person ): int => (int) $person['id'], $people );
		sort( $ids );
		return $ids;
	}

	/**
	 * @param array<int, array{id:int, fields:array}> $people
	 * @return array<string, mixed>
	 */
	private function person_fields( array $people, int $person_id ): array {
		foreach ( $people as $person ) {
			if ( (int) $person['id'] === $person_id ) {
				return $person['fields'];
			}
		}

		$this->fail( "Person {$person_id} not found in Kaderlijst response." );
	}
}
