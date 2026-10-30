<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\Fields\Registry;
use Rondo\Fields\RestFields;
use Rondo\People\RoleEngagements;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class RoleEngagementsTest extends RondoTestCase {
	private int $person;
	private int $team;
	private int $committee;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->team      = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'Testteam',
			]
			);
		$this->committee = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Testcommissie',
			]
			);
		$this->person    = $this->createPerson();
		Fields::update_for_post( $this->person, 'work_history', [ $this->role( $this->team, 'Trainer' ), $this->role( $this->committee, 'Penningmeester' ) ] );
	}

	public function test_unknown_is_explicit_and_person_can_be_employee_and_volunteer(): void {
		( new \Rondo\Core\VolunteerStatus() )->calculate_and_update_status( $this->person );
		$before  = Fields::get_for_post( $this->person, 'work_history' );
		$initial = RoleEngagements::for_person( $this->person );
		$this->assertSame( 2, $initial['summary']['unknown'] );
		$this->classify( 0, 'employee' );
		$result = $this->classify( 1, 'volunteer' );
		$this->assertSame( 1, $result['summary']['employee'] );
		$this->assertSame( 1, $result['summary']['volunteer'] );
		$this->assertSame( 0, $result['summary']['unknown'] );
		$this->assertSame( $before, Fields::get_for_post( $this->person, 'work_history' ) );
		$this->assertTrue( (bool) Fields::get_for_post( $this->person, 'huidig_vrijwilliger' ) );
		$this->assertFalse( (bool) Fields::get_for_post( $this->person, 'betaalde_vrijwilliger' ) );
		$this->assertCount( 2, get_post_meta( $this->person, '_role_engagement_change', false ) );
	}

	public function test_native_storage_is_separate_and_unknown_clears_stale_rows(): void {
		$this->classify( 0, 'compensated' );
		$this->assertEquals( 1, get_post_meta( $this->person, 'role_engagements', true ) );
		$this->assertSame( 'compensated', get_post_meta( $this->person, 'role_engagements_0_engagement_type', true ) );
		$this->assertSame( 'field_role_engagement_engagement_type', get_post_meta( $this->person, '_role_engagements_0_engagement_type', true ) );
		$this->classify( 0, 'unknown' );
		$this->assertSame( [], Fields::get_for_post( $this->person, 'role_engagements' ) );
		$this->assertFalse( metadata_exists( 'post', $this->person, 'role_engagements_0_engagement_type' ) );
		$this->assertTrue( Registry::resolve( 'person', 'role_engagements' )['read_only'] );
	}

	public function test_reordered_and_rebuilt_sync_rows_keep_their_individual_classification(): void {
		$this->classify( 0, 'employee' );
		$roles                     = Fields::get_for_post( $this->person, 'work_history' );
		$rebuilt                   = [ $this->role( $this->committee, 'Penningmeester' ), $this->role( $this->team, 'Trainer' ) ];
		$rebuilt[1]['description'] = 'Nieuwe omschrijving uit Sportlink';
		Fields::update_for_post( $this->person, 'work_history', $rebuilt );
		$result = RoleEngagements::for_person( $this->person );
		$this->assertSame( 'unknown', $result['roles'][0]['engagement_type'] );
		$this->assertSame( 'employee', $result['roles'][1]['engagement_type'] );
		$this->assertSame(
			RoleEngagements::role_key( $roles[0] ),
			RoleEngagements::role_key(
			[
				'team_id'    => $this->team,
				'job_title'  => ' Trainer ',
				'start_date' => '2020-01-01',
			]
			)
			);
	}

	public function test_changed_function_or_new_term_does_not_inherit_old_classification(): void {
		$this->classify( 0, 'employee' );
		foreach ( [ $this->role( $this->team, 'Leider' ), array_merge( $this->role( $this->team, 'Trainer' ), [ 'start_date' => '2021-01-01' ] ) ] as $role ) {
			Fields::update_for_post( $this->person, 'work_history', [ $role ] );
			$this->assertSame( 'unknown', RoleEngagements::for_person( $this->person )['roles'][0]['engagement_type'] );
		}
		$this->assertCount( 1, Fields::get_for_post( $this->person, 'role_engagements' ) );
	}

	public function test_stale_version_invalid_type_and_duplicate_function_cannot_write(): void {
		$initial = RoleEngagements::for_person( $this->person );
		$this->classify( 0, 'employee' );
		$this->assertSame( 'rondo_engagement_stale', RoleEngagements::update( $this->person, $initial['roles'][1]['role_key'], 'volunteer', $initial['version'] )->get_error_code() );
		$this->assertWPError( RoleEngagements::update( $this->person, 'invented', 'invalid', $initial['version'] ) );
		Fields::update_for_post( $this->person, 'work_history', [ $this->role( $this->team, 'Trainer' ), $this->role( $this->team, 'Trainer' ) ] );
		$duplicate = RoleEngagements::for_person( $this->person );
		$this->assertFalse( $duplicate['roles'][0]['can_classify'] );
		$this->assertSame( 'unknown', $duplicate['roles'][0]['engagement_type'] );
		$this->assertWPError( RoleEngagements::update( $this->person, $duplicate['roles'][0]['role_key'], 'employee', $duplicate['version'] ) );
	}

	public function test_source_change_invalidates_version_and_lock_prevents_lost_updates(): void {
		$initial = RoleEngagements::for_person( $this->person );
		Fields::update_for_post( $this->person, 'work_history', [ $this->role( $this->team, 'Trainer' ) ] );
		$this->assertWPError( RoleEngagements::update( $this->person, $initial['roles'][0]['role_key'], 'employee', $initial['version'] ) );
		$now = RoleEngagements::for_person( $this->person );
		add_option( 'rondo_role_engagement_lock_' . $this->person, time(), '', false );
		$this->assertSame( 'rondo_engagement_busy', RoleEngagements::update( $this->person, $now['roles'][0]['role_key'], 'employee', $now['version'] )->get_error_code() );
		$this->assertSame( [], Fields::get_for_post( $this->person, 'role_engagements' ) );
	}

	public function test_counts_deduplicate_people_within_categories_and_exclude_noncurrent_and_player_roles(): void {
		$history = [ $this->role( $this->team, 'Trainer' ), $this->role( $this->team, 'Leider' ), $this->role( $this->committee, 'Penningmeester' ), $this->role( $this->team, 'Teamspeler' ), array_merge( $this->role( $this->committee, 'Secretaris' ), [ 'is_current' => false ] ), array_merge( $this->role( $this->committee, 'Voorzitter' ), [ 'start_date' => '2099-01-01' ] ) ];
		Fields::update_for_post( $this->person, 'work_history', $history );
		$this->classify( 0, 'employee' );
		$this->classify( 1, 'employee' );
		$this->classify( 2, 'volunteer' );
		$stats = RoleEngagements::statistics();
		$this->assertSame(
			[
				'people' => 1,
				'roles'  => 2,
			],
			$stats['totals']['employee']
			);
		$this->assertSame(
			[
				'people' => 1,
				'roles'  => 1,
			],
			$stats['totals']['volunteer']
			);
		$this->assertSame(
			[
				'people' => 0,
				'roles'  => 0,
			],
			$stats['totals']['unknown']
			);
		$this->assertCount( 2, $stats['by_entity'] );
		$this->assertStringNotContainsString( 'person_id', wp_json_encode( $stats ) );
	}

	public function test_vog_exempt_committees_are_still_counted_as_staff_functions(): void {
		update_option( 'rondo_vog_exempt_commissies', [ $this->committee ] );
		$this->assertCount( 2, RoleEngagements::for_person( $this->person )['roles'] );
	}

	public function test_read_redaction_and_write_permissions_apply_to_real_rest_routes(): void {
		$this->bootRestControllers( [ RestFields::class, People::class ] );
		$this->classify( 0, 'employee' );
		$admin = get_current_user_id();
		$read  = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/people/' . $this->person ) );
		$this->assertSame( 200, $read->get_status() );
		$this->assertSame( 1, $read->get_data()['role_engagements']['summary']['employee'] );
		foreach ( [ 'rondo_vog', 'rondo_fairplay', 'rondo_vrijwilligers' ] as $role ) {
			$id = self::factory()->user->create( [ 'role' => $role ] );
			wp_set_current_user( $id );
			$this->assertSame( 403, $this->write_request( 'volunteer' )->get_status() );
			if ( $role !== 'rondo_vrijwilligers' ) {
				$this->assertTrue( AccessControl::field_is_hidden( 'role_engagements' ) );
				$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/people/' . $this->person ) );
				$this->assertArrayNotHasKey( 'role_engagements', $response->get_data() );
				$this->assertArrayNotHasKey( 'role_engagements', $response->get_data()['fields'] ?? [] );
			}
		}
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->write_request( 'volunteer' )->get_status() );
		wp_set_current_user( $admin );
		$this->assertSame( 200, $this->write_request( 'compensated' )->get_status() );
	}

	public function test_direct_field_writes_are_rejected_even_for_admin_and_former_members_stay_readonly(): void {
		$this->bootRestControllers( [ RestFields::class, People::class ] );
		$request = new WP_REST_Request( 'POST', '/wp/v2/people/' . $this->person );
		$request->set_body_params(
			[
				'fields' => [
					'role_engagements' => [
						[
							'role_key'        => 'fake',
							'engagement_type' => 'employee',
						],
					],
				],
			]
			);
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		Fields::update_for_post( $this->person, 'former_member', true );
		$id = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );
		wp_set_current_user( $id );
		$this->assertSame( 403, $this->write_request( 'employee' )->get_status() );
	}

	public function test_person_payment_flag_does_not_guess_role_type_and_deceased_people_are_not_counted(): void {
		Fields::update_for_post( $this->person, 'betaalde_vrijwilliger', true );
		$this->assertSame( 2, RoleEngagements::for_person( $this->person )['summary']['unknown'] );
		Fields::update_for_post( $this->person, 'datum_overlijden', '2026-01-01' );
		$this->assertSame( 0, RoleEngagements::statistics()['totals']['unknown']['people'] );
	}

	public function test_finance_and_membership_managers_can_classify_active_people(): void {
		$this->bootRestControllers( [ RestFields::class, People::class ] );
		foreach ( [ 'rondo_financieel', 'rondo_ledenadministratie' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			$this->assertSame( 200, $this->write_request( 'employee' )->get_status(), $role );
		}
	}

	private function role( int $entity, string $job ): array {
		return [
			'team'        => $entity,
			'entity_type' => get_post_type( $entity ),
			'job_title'   => $job,
			'start_date'  => '2020-01-01',
			'is_current'  => true,
		];
	}

	private function classify( int $index, string $type ): array {
		$data   = RoleEngagements::for_person( $this->person );
		$result = RoleEngagements::update( $this->person, $data['roles'][ $index ]['role_key'], $type, $data['version'] );
		$this->assertIsArray( $result );
		return $result;
	}

	private function write_request( string $type ) {
		$data    = RoleEngagements::for_person( $this->person );
		$request = new WP_REST_Request( 'PATCH', '/rondo/v1/people/' . $this->person . '/role-engagements' );
		$request->set_body_params(
			[
				'role_key'        => $data['roles'][0]['role_key'],
				'engagement_type' => $type,
				'version'         => $data['version'],
			]
			);
		return rest_do_request( $request );
	}
}
