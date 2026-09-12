<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\Training\Schedules;
use Tests\Support\RondoTestCase;

class TrainingSchedulesTest extends RondoTestCase {
	private int $team_id;
	private int $admin_id;
	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		delete_option( Schedules::SETTINGS );
		delete_option( Schedules::ACTIVE );
		delete_option( 'rondo_feature_toggles' );
		delete_option( 'rondo_training_write_lock' );
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
		$this->team_id = $this->createOrganization( [ 'post_title' => 'O13-1' ] );
		$this->server  = $this->bootRestControllers( [ \Rondo\REST\Training::class ] );
		$this->assertSame(
			200,
			$this->request(
			'PUT',
			'/settings',
			[
				'revision'   => 0,
				'pitches'    => [
					[
						'id'   => 'veld2',
						'name' => 'Veld 2',
					],
					[
						'id'   => 'veld6',
						'name' => 'Veld 6',
					],
				],
				'age_groups' => [],
				'teams'      => [],
			]
			)->get_status()
			);
	}

	private function request( string $method, string $route, ?array $data = null ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rondo/v1/training' . $route );
		if ( $data !== null ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $data ) );
		}
		return $this->server->dispatch( $request );
	}

	private function block( array $changes = [] ): array {
		return array_merge(
			[
				'block_id' => wp_generate_uuid4(),
				'label'    => '',
				'team_ids' => [ $this->team_id ],
				'pitch_id' => 'veld2',
				'day'      => 1,
				'start'    => '18:00',
				'duration' => 60,
				'size'     => 2,
				'offset'   => 0,
			],
			$changes
			);
	}

	private function create( array $blocks = [] ): array {
		$response = $this->request(
			'POST',
			'/schedules',
			[
				'name'     => 'Regulier',
				'season'   => '2026/27',
				'revision' => 0,
				'blocks'   => $blocks,
			]
			);
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		return $response->get_data();
	}

	public function test_reads_are_public_in_every_flag_state_and_management_remains_protected(): void {
		$schedule   = $this->create( [ $this->block() ] );
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		foreach ( [ 'admin_only', 'off', 'on' ] as $state ) {
			update_option( 'rondo_feature_toggles', [ 'training' => $state ] );
			$controller = new \Rondo\REST\Training();
			$this->assertNotFalse( has_action( 'rest_api_init', [ $controller, 'register_routes' ] ) );
			$this->server = $this->bootRestControllers( [ \Rondo\REST\Training::class ] );
			foreach ( [ 0, $subscriber, $this->admin_id ] as $user_id ) {
				wp_set_current_user( $user_id );
				foreach ( [ '/schedules', '/schedules/' . $schedule['id'], '/active' ] as $route ) {
					$this->assertSame( 200, $this->request( 'GET', $route )->get_status(), $state . ': ' . $route );
				}
				if ( $user_id !== $this->admin_id ) {
					foreach ( [ [ 'GET', '/settings' ], [ 'PUT', '/settings' ], [ 'POST', '/schedules' ], [ 'PUT', '/schedules/' . $schedule['id'] ], [ 'DELETE', '/schedules/' . $schedule['id'] ], [ 'POST', '/schedules/' . $schedule['id'] . '/copy' ], [ 'POST', '/schedules/' . $schedule['id'] . '/activate' ] ] as [ $method, $route ] ) {
						$this->assertContains( $this->request( $method, $route, [] )->get_status(), [ 401, 403 ], $state . ': ' . $method . ' ' . $route );
					}
				} else {
					$this->assertSame( 200, $this->request( 'GET', '/settings' )->get_status() );
				}
			}
		}
	}

	public function test_training_capability_allows_complete_management_and_revocation_blocks_writes(): void {
		$user_id = $this->createRondoUser();
		$user    = new \WP_User( $user_id );
		$user->add_cap( UserRoles::TRAINING_CAPABILITY );
		wp_set_current_user( $user_id );
		update_option( 'rondo_feature_toggles', [ 'training' => 'off' ] );
		$data = ( new \Rondo\REST\UserSettings() )->get_current_user_data( $user_id );
		$this->assertTrue( $data['can_manage_training'] );
		$this->assertFalse( $data['is_admin'] );
		$this->assertFalse( $data['is_kader'] );
		$this->assertFalse( current_user_can( 'manage_options' ) );
		$settings = $this->request( 'GET', '/settings' )->get_data();
		$this->assertContains( $this->team_id, array_column( $settings['teams'], 'id' ) );
		$this->assertSame( 200, $this->request( 'PUT', '/settings', $settings['settings'] )->get_status() );
		$schedule         = $this->create( [ $this->block() ] );
		$schedule['name'] = 'Avondtraining';
		$this->assertSame(
			200,
			$this->request(
			'PUT',
			'/schedules/' . $schedule['id'],
			[
				'name'     => 'Avondtraining',
				'season'   => $schedule['season'],
				'revision' => 1,
				'blocks'   => [ $this->block() ],
			]
			)->get_status()
			);
		$copy = $this->request( 'POST', '/schedules/' . $schedule['id'] . '/copy', [ 'name' => 'Slecht weer' ] );
		$this->assertSame( 200, $copy->get_status() );
		$this->assertSame( 200, $this->request( 'POST', '/schedules/' . $copy->get_data()['id'] . '/activate', [ 'revision' => 1 ] )->get_status() );
		$this->assertSame( 200, $this->request( 'DELETE', '/schedules/' . $schedule['id'], [ 'revision' => 2 ] )->get_status() );
		$user->remove_cap( UserRoles::TRAINING_CAPABILITY );
		$this->assertFalse( ( new \Rondo\REST\UserSettings() )->get_current_user_data( $user_id )['can_manage_training'] );
		$this->assertSame( 403, $this->request( 'GET', '/settings' )->get_status() );
		$this->assertSame( 403, $this->request( 'POST', '/schedules', [] )->get_status() );
		$this->assertSame( 200, $this->request( 'GET', '/active' )->get_status() );
	}

	public function test_training_capability_is_assignable_through_the_role_matrix(): void {
		$controller = new \Rondo\REST\Capabilities();
		$matrix     = $controller->get_capability_matrix()->get_data();
		$this->assertSame( 'Trainingsschema beheren', $matrix['capability_labels'][ UserRoles::TRAINING_CAPABILITY ] );
		$this->assertNotContains( UserRoles::TRAINING_CAPABILITY, $matrix['management_capabilities'] );
		$role     = get_role( UserRoles::ROLE_NAME );
		$original = $role->has_cap( UserRoles::TRAINING_CAPABILITY );
		$user_id  = $this->createRondoUser();
		try {
			foreach ( [ true, false ] as $enabled ) {
				$request = new \WP_REST_Request();
				$request->set_param( 'roles', [ UserRoles::ROLE_NAME => [ 'capabilities' => [ UserRoles::TRAINING_CAPABILITY => $enabled ] ] ] );
				$this->assertSame( 200, $controller->update_capability_matrix( $request )->get_status() );
				$this->assertSame( $enabled, UserRoles::can_manage_training( $user_id ) );
			}
		} finally {
			$role->add_cap( UserRoles::TRAINING_CAPABILITY, $original );
		}
	}

	public function test_role_upgrade_grants_training_only_to_admins_and_preserves_assignments(): void {
		$admin = get_role( 'administrator' );
		$admin->remove_cap( UserRoles::TRAINING_CAPABILITY );
		update_option( UserRoles::ROLES_VERSION_OPTION, 12 );
		$roles = new UserRoles();
		$roles->maybe_upgrade_roles();
		$this->assertTrue( $admin->has_cap( UserRoles::TRAINING_CAPABILITY ) );
		foreach ( UserRoles::get_role_slugs() as $slug ) {
			$this->assertFalse( get_role( $slug )->has_cap( UserRoles::TRAINING_CAPABILITY ), $slug );
		}
		$admin->remove_cap( UserRoles::TRAINING_CAPABILITY );
		try {
			$roles->maybe_upgrade_roles();
			$this->assertFalse( $admin->has_cap( UserRoles::TRAINING_CAPABILITY ) );
		} finally {
			$admin->add_cap( UserRoles::TRAINING_CAPABILITY );
		}
	}

	public function test_all_versions_have_stable_ids_and_are_public_without_authentication(): void {
		$one = $this->create( [ $this->block() ] );
		$this->assertSame( 'Regulier', $one['name'] );
		$two = $this->request( 'POST', '/schedules/' . $one['id'] . '/copy', [ 'name' => 'Slecht weer' ] )->get_data();
		$this->assertNotSame( $one['id'], $two['id'] );
		$this->assertSame( $one['blocks'], $two['blocks'] );
		$this->assertSame( 200, $this->request( 'POST', '/schedules/' . $two['id'] . '/activate', [ 'revision' => 1 ] )->get_status() );
		wp_set_current_user( 0 );
		$feed = $this->request( 'GET', '/schedules' );
		$this->assertCount( 2, $feed->get_data()['schedules'] );
		$this->assertSame( $two['id'], $feed->get_data()['active_id'] );
		$this->assertSame( $two['id'], $this->request( 'GET', '/active' )->get_data()['schedule']['id'] );
		$this->assertSame( $one['id'], $this->request( 'GET', '/schedules/' . $one['id'] )->get_data()['schedule']['id'] );
		$this->assertSame( 'no-store, private', $feed->get_headers()['Cache-Control'] );
		$this->assertSame( 401, $this->request( 'GET', '/settings' )->get_status() );
		$this->assertSame( 401, $this->request( 'POST', '/schedules/' . $one['id'] . '/activate', [ 'revision' => 1 ] )->get_status() );
		$this->assertSame( 401, $this->request( 'PUT', '/schedules/' . $one['id'], [] )->get_status() );
		$this->assertSame( 401, $this->request( 'DELETE', '/schedules/' . $one['id'], [] )->get_status() );
	}

	public function test_copy_is_independent_and_rename_preserves_identifier(): void {
		$one   = $this->create( [ $this->block() ] );
		$copy  = $this->request( 'POST', '/schedules/' . $one['id'] . '/copy', [ 'name' => 'Slecht weer' ] )->get_data();
		$saved = $this->request(
			'PUT',
			'/schedules/' . $copy['id'],
			[
				'name'     => 'Kunstgras',
				'season'   => '2027/28',
				'revision' => 1,
				'blocks'   => [],
			]
			);
		$this->assertSame( $copy['id'], $saved->get_data()['id'] );
		$this->assertCount( 1, Schedules::schedule( $one['id'] )['blocks'] );
		$this->assertSame( 'Regulier', Schedules::schedule( $one['id'] )['name'] );
	}

	public function test_conflicts_reject_whole_write_but_adjacent_slots_and_shared_blocks_work(): void {
		$first    = $this->block();
		$schedule = $this->create( [ $first ] );
		foreach ( [
			$this->block(
				[
					'label'    => 'Keepers',
					'team_ids' => [],
					'offset'   => 1,
					'size'     => 1,
				]
				),
			$this->block( [ 'pitch_id' => 'veld6' ] ),
		] as $conflict ) {
			$response = $this->request(
				'PUT',
				'/schedules/' . $schedule['id'],
				[
					'name'     => 'Conflict',
					'season'   => '2026/27',
					'revision' => 1,
					'blocks'   => [ $first, $conflict ],
				]
				);
			$this->assertSame( 409, $response->get_status() );
			$this->assertSame( 'rondo_training_conflict', $response->get_data()['code'] );
			$this->assertSame( 'Regulier', Schedules::schedule( $schedule['id'] )['name'] );
			$this->assertCount( 1, Fields::get_for_post( $schedule['id'], 'blocks' ) );
		}
		$other_team = $this->createOrganization( [ 'post_title' => 'O13-2' ] );
		$this->create(
			[
				$this->block( [ 'team_ids' => [ $this->team_id, $other_team ] ] ),
				$this->block(
					[
						'label'    => 'Keepers',
						'team_ids' => [],
						'offset'   => 2,
					]
					),
				$this->block( [ 'start' => '19:00' ] ),
			]
			);
	}

	public function test_storage_uses_registered_numbered_rows_and_removes_stale_rows(): void {
		$schedule = $this->create( [ $this->block(), $this->block( [ 'day' => 2 ] ) ] );
		$id       = $schedule['id'];
		$this->assertEquals( 2, get_post_meta( $id, 'blocks', true ) );
		$this->assertSame( 'field_training_blocks', get_post_meta( $id, '_blocks', true ) );
		$this->assertEquals( [ $this->team_id ], get_post_meta( $id, 'blocks_1_team_ids', true ) );
		$this->assertSame( '18:00', $schedule['blocks'][0]['start'] );
		$this->assertSame(
			200,
			$this->request(
			'PUT',
			'/schedules/' . $id,
			[
				'name'     => 'Regulier',
				'season'   => '2026/27',
				'revision' => 1,
				'blocks'   => [],
			]
			)->get_status()
			);
		$this->assertSame( '', get_post_meta( $id, 'blocks_1_team_ids', true ) );
		$this->assertSame( '', get_post_meta( $id, '_blocks_1_team_ids', true ) );
		$this->assertSame( [], Fields::get_for_post( $id, 'blocks' ) );
	}

	public function test_revisions_prevent_stale_save_activation_and_deletion(): void {
		$schedule = $this->create();
		$id       = $schedule['id'];
		$payload  = [
			'name'     => 'Nieuwe naam',
			'season'   => '2026/27',
			'revision' => 1,
			'blocks'   => [],
		];
		$this->assertSame( 200, $this->request( 'PUT', '/schedules/' . $id, $payload )->get_status() );
		$this->assertSame( 409, $this->request( 'PUT', '/schedules/' . $id, $payload )->get_status() );
		$this->assertSame( 409, $this->request( 'POST', '/schedules/' . $id . '/activate', [ 'revision' => 1 ] )->get_status() );
		$this->assertSame( 409, $this->request( 'DELETE', '/schedules/' . $id, [ 'revision' => 1 ] )->get_status() );
		$this->assertSame( 2, Schedules::schedule( $id )['revision'] );
	}

	public function test_active_schedule_cannot_be_deleted_and_trash_is_not_exposed(): void {
		$schedule = $this->create();
		$id       = $schedule['id'];
		$this->assertNull( $this->request( 'GET', '/active' )->get_data()['schedule'] );
		$this->request( 'POST', '/schedules/' . $id . '/activate', [ 'revision' => 1 ] );
		$this->assertSame( 409, $this->request( 'DELETE', '/schedules/' . $id, [ 'revision' => 1 ] )->get_status() );
		$other = $this->create();
		$this->assertSame( 200, $this->request( 'DELETE', '/schedules/' . $other['id'], [ 'revision' => 1 ] )->get_status() );
		$this->assertSame( 'trash', get_post_status( $other['id'] ) );
		$this->assertSame( 404, $this->request( 'GET', '/schedules/' . $other['id'] )->get_status() );
		$this->assertSame( 404, $this->request( 'GET', '/schedules/' . $this->team_id )->get_status() );
		$this->assertCount( 1, $this->request( 'GET', '/schedules' )->get_data()['schedules'] );
	}

	public function test_settings_protect_referenced_pitches_and_validate_team_overrides(): void {
		$this->create( [ $this->block() ] );
		$config            = Schedules::settings();
		$config['pitches'] = [
			[
				'id'   => 'veld6',
				'name' => 'Veld 6',
			],
		];
		$this->assertSame( 409, $this->request( 'PUT', '/settings', $config )->get_status() );
		$config               = Schedules::settings();
		$config['age_groups'] = [
			[
				'id'       => 'o13',
				'name'     => 'O13',
				'duration' => 75,
				'size'     => 2,
			],
		];
		$config['teams']      = [
			[
				'team_id'      => $this->team_id,
				'age_group_id' => 'o13',
				'duration'     => 90,
				'size'         => null,
			],
		];
		$this->assertSame( 200, $this->request( 'PUT', '/settings', $config )->get_status() );
		$this->assertSame( 409, $this->request( 'PUT', '/settings', $config )->get_status() );
		$config                             = Schedules::settings();
		$config['teams'][0]['age_group_id'] = 'missing';
		$this->assertSame( 400, $this->request( 'PUT', '/settings', $config )->get_status() );
	}

	public function test_eighths_and_three_quarters_round_trip_and_check_collisions(): void {
		$blocks = [];
		for ( $i = 0; $i < 8; ++$i ) {
			$blocks[] = $this->block(
				[
					'size'     => 0.5,
					'offset'   => $i / 2,
					'team_ids' => [],
					'label'    => 'Keepers ' . $i,
				]
				);
		}
		$schedule = $this->create( $blocks );
		$this->assertCount( 8, $schedule['blocks'] );
		$this->assertSame( 0.5, $schedule['blocks'][1]['size'] );
		$this->assertSame( 0.5, $schedule['blocks'][1]['offset'] );
		$this->assertEquals( 0.5, get_post_meta( $schedule['id'], 'blocks_1_offset', true ) );
		$blocks[1]['offset'] = 0;
		$this->assertSame( 'rondo_training_conflict', Schedules::validate_blocks( $blocks )->get_error_code() );
		foreach ( [ 0, 1 ] as $offset ) {
			$this->assertIsArray(
				Schedules::validate_blocks(
				[
					$this->block(
					[
						'size'   => 3,
						'offset' => $offset,
					]
					),
					$this->block(
					[
						'size'     => 1,
						'offset'   => $offset ? 0 : 3,
						'label'    => 'Keepers',
						'team_ids' => [],
					]
						),
				]
				)
				);
		}
		$copy = $this->request( 'POST', '/schedules/' . $schedule['id'] . '/copy', [ 'name' => 'Kopie' ] );
		$this->assertSame( 200, $copy->get_status() );
		$this->assertSame( $schedule['blocks'], $copy->get_data()['blocks'] );
		$this->assertSame( 200, $this->request( 'POST', '/schedules/' . $schedule['id'] . '/activate', [ 'revision' => 1 ] )->get_status() );
	}

	public function test_colors_inherit_from_teams_allow_standalone_groups_and_update_all_versions(): void {
		$config               = Schedules::settings();
		$config['age_groups'] = [
			[
				'id'       => 'o8',
				'name'     => 'O8',
				'duration' => 75,
				'size'     => 0.5,
				'color'    => '#FFFFB3',
			],
			[
				'id'       => 'keepers',
				'name'     => 'Keepers',
				'duration' => 60,
				'size'     => 3,
				'color'    => '#123456',
			],
		];
		$config['teams']      = [
			[
				'team_id'      => $this->team_id,
				'age_group_id' => 'o8',
				'duration'     => null,
				'size'         => 0.5,
			],
		];
		$this->assertSame( 200, $this->request( 'PUT', '/settings', $config )->get_status() );
		$one = $this->create(
			[
				$this->block(),
				$this->block(
				[
					'team_ids'     => [],
					'label'        => 'Keeperstraining',
					'day'          => 2,
					'age_group_id' => 'keepers',
				]
				),
			]
			);
		$this->assertSame( '#ffffb3', $one['blocks'][0]['color'] );
		$this->assertSame( '#123456', $one['blocks'][1]['color'] );
		$this->assertSame( 'keepers', get_post_meta( $one['id'], 'blocks_1_age_group_id', true ) );
		$this->assertSame( 'field_training_age_group_id', get_post_meta( $one['id'], '_blocks_1_age_group_id', true ) );
		$two                              = $this->request( 'POST', '/schedules/' . $one['id'] . '/copy', [ 'name' => 'Ander weer' ] )->get_data();
		$config                           = Schedules::settings();
		$config['age_groups'][0]['color'] = '#b3de69';
		$this->assertSame( 200, $this->request( 'PUT', '/settings', $config )->get_status() );
		wp_set_current_user( 0 );
		foreach ( [ $one['id'], $two['id'] ] as $id ) {
			$this->assertSame( '#b3de69', $this->request( 'GET', '/schedules/' . $id )->get_data()['schedule']['blocks'][0]['color'] );
		}
		wp_set_current_user( $this->admin_id );
		$config                           = Schedules::settings();
		$config['age_groups'][0]['color'] = 'red; background: url(https://example.org)';
		$this->assertSame( 400, $this->request( 'PUT', '/settings', $config )->get_status() );
		$config = Schedules::settings();
		array_pop( $config['age_groups'] );
		$this->assertSame( 409, $this->request( 'PUT', '/settings', $config )->get_status() );
	}

	public function test_invalid_fields_and_times_are_rejected(): void {
		$changes = [
			[ 'start' => '24:00' ],
			[ 'start' => '18:05' ],
			[ 'duration' => -15 ],
			[ 'duration' => '60' ],
			[ 'day' => 8 ],
			[ 'offset' => 1 ],
			[ 'size' => 1.5 ],
			[
				'size'   => 0.5,
				'offset' => 0.25,
			],
			[
				'size'   => 0.5,
				'offset' => '0.5',
			],
			[
				'size'   => 3,
				'offset' => 2,
			],
			[ 'age_group_id' => 'unknown' ],
			[ 'pitch_id' => 'unknown' ],
			[
				'label'    => '',
				'team_ids' => [],
			],
			[ 'team_ids' => [ $this->admin_id ] ],
			[ 'unexpected' => 'field' ],
			[ 'start' => '23:30' ],
		];
		foreach ( $changes as $change ) {
			$result = Schedules::validate_blocks( [ $this->block( $change ) ] );
			$this->assertWPError( $result, wp_json_encode( $change ) );
		}
		$this->assertIsArray( Schedules::validate_blocks( [ $this->block( [ 'start' => '23:00' ] ) ] ) );
	}
	public function test_scalar_json_and_busy_writes_return_controlled_errors(): void {
		foreach ( [ [ 'POST', '/schedules' ], [ 'PUT', '/settings' ] ] as [ $method, $route ] ) {
			$request = new \WP_REST_Request( $method, '/rondo/v1/training' . $route );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( '"invalid"' );
			$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
		}
		update_option(
			'rondo_training_write_lock',
			[
				'token' => 'another-request',
				'time'  => time(),
			],
			false
			);
		$response = $this->request(
			'POST',
			'/schedules',
			[
				'name'     => 'Busy',
				'season'   => '2026/27',
				'revision' => 0,
				'blocks'   => [],
			]
			);
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'another-request', get_option( 'rondo_training_write_lock' )['token'] );
		$this->assertSame( [], Schedules::posts() );
		delete_option( 'rondo_training_write_lock' );
	}
}
