<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Users\CapabilitySync;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/**
 * Tests role reconciliation across every capability-sync entry point.
 */
class CapabilitySyncTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		delete_option( 'rondo_functie_capability_map' );
		delete_option( 'rondo_commissie_capability_map' );
	}

	protected function tear_down(): void {
		delete_option( 'rondo_functie_capability_map' );
		delete_option( 'rondo_commissie_capability_map' );
		parent::tear_down();
	}

	/**
	 * Scheduled sync must combine supplied Sportlink functies with local commissie membership.
	 */
	public function test_knvb_sync_combines_sportlink_functies_with_commissie_roles(): void {
		$fixture = $this->create_linked_user_with_commissie( 'Commissielid' );

		update_option(
			'rondo_functie_capability_map',
			[
				'Sportlinkfunctie' => [ 'rondo_vog' => true ],
				'Commissielid'     => [ 'rondo_financieel' => true ],
			]
		);
		update_option(
			'rondo_commissie_capability_map',
			[ (string) $fixture['commissie_id'] => [ 'rondo_fairplay' => true ] ]
		);

		$result = ( new CapabilitySync() )->sync_user_by_knvb_id( $fixture['knvb_id'], [ 'Sportlinkfunctie' ] );
		$roles  = (array) get_userdata( $fixture['user_id'] )->roles;

		$this->assertIsArray( $result );
		$this->assertSame( 'synced', $result['status'] );
		$this->assertContains( 'rondo_vog', $roles );
		$this->assertContains( 'rondo_fairplay', $roles );
		$this->assertNotContains(
			'rondo_financieel',
			$roles,
			'The scheduled endpoint must keep using supplied Sportlink functies instead of deriving them locally.'
		);
	}

	/**
	 * Equivalent inputs must produce the same roles through all three sync paths.
	 */
	public function test_sync_entry_points_produce_the_same_roles(): void {
		$fixture = $this->create_linked_user_with_commissie( 'Sportlinkfunctie' );

		update_option(
			'rondo_functie_capability_map',
			[ 'Sportlinkfunctie' => [ 'rondo_vog' => true ] ]
		);
		update_option(
			'rondo_commissie_capability_map',
			[ (string) $fixture['commissie_id'] => [ 'rondo_fairplay' => true ] ]
		);

		$sync = new CapabilitySync();
		$sync->sync_user_by_knvb_id( $fixture['knvb_id'], [ 'Sportlinkfunctie' ] );
		$knvb_roles = $this->syncable_roles( $fixture['user_id'] );

		$this->remove_syncable_roles( $fixture['user_id'] );
		$sync->sync_user_by_person_id( $fixture['person_id'] );
		$person_roles = $this->syncable_roles( $fixture['user_id'] );

		$this->remove_syncable_roles( $fixture['user_id'] );
		$sync->sync_all();
		$all_roles = $this->syncable_roles( $fixture['user_id'] );

		$this->assertSame( [ 'rondo_fairplay', 'rondo_vog' ], $knvb_roles );
		$this->assertSame( $knvb_roles, $person_roles );
		$this->assertSame( $knvb_roles, $all_roles );
	}

	/**
	 * Ending commissie membership must still revoke its role on scheduled sync.
	 */
	public function test_knvb_sync_revokes_commissie_role_after_membership_ends(): void {
		$fixture = $this->create_linked_user_with_commissie( 'Sportlinkfunctie' );

		update_option(
			'rondo_commissie_capability_map',
			[ (string) $fixture['commissie_id'] => [ 'rondo_fairplay' => true ] ]
		);

		$sync = new CapabilitySync();
		$sync->sync_user_by_knvb_id( $fixture['knvb_id'], [] );
		$this->assertContains( 'rondo_fairplay', (array) get_userdata( $fixture['user_id'] )->roles );

		Fields::update_for_post(
			$fixture['person_id'],
			'work_history',
			[
				[
					'team'        => $fixture['commissie_id'],
					'entity_type' => 'commissie',
					'job_title'   => 'Sportlinkfunctie',
					'is_current'  => false,
				],
			]
		);

		$result = $sync->sync_user_by_knvb_id( $fixture['knvb_id'], [] );

		$this->assertContains( 'rondo_fairplay', $result['revoked'] );
		$this->assertNotContains( 'rondo_fairplay', (array) get_userdata( $fixture['user_id'] )->roles );
	}

	/**
	 * Existing manual revokes remain authoritative for commissie mappings.
	 */
	public function test_knvb_sync_respects_manual_commissie_role_revoke(): void {
		$fixture = $this->create_linked_user_with_commissie( 'Commissielid' );

		update_option(
			'rondo_commissie_capability_map',
			[ (string) $fixture['commissie_id'] => [ 'rondo_fairplay' => true ] ]
		);
		update_user_meta(
			$fixture['user_id'],
			CapabilitySync::META_MANUAL_REVOKES,
			wp_json_encode( [ 'rondo_fairplay' ] )
		);

		$result = ( new CapabilitySync() )->sync_user_by_knvb_id( $fixture['knvb_id'], [] );

		$this->assertSame( [], $result['granted'] );
		$this->assertNotContains( 'rondo_fairplay', (array) get_userdata( $fixture['user_id'] )->roles );
	}

	/**
	 * Create one provisioned user linked to a current commissie work-history row.
	 *
	 * @return array{user_id:int, person_id:int, commissie_id:int, knvb_id:string}
	 */
	private function create_linked_user_with_commissie( string $job_title ): array {
		$user_id      = $this->createRondoUser();
		$person_id    = $this->createPerson( [ 'post_title' => 'Capability Sync Person' ] );
		$commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Capability Sync Commissie',
			]
		);
		$knvb_id      = 'SYNC-' . $user_id;

		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		update_user_meta( $user_id, UserProvisioning::META_KNVB_ID, $knvb_id );
		update_post_meta( $person_id, UserProvisioning::META_USER_ID, $user_id );
		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'team'        => $commissie_id,
					'entity_type' => 'commissie',
					'job_title'   => $job_title,
					'is_current'  => true,
				],
			]
		);

		return compact( 'user_id', 'person_id', 'commissie_id', 'knvb_id' );
	}

	/**
	 * Return only automatically managed roles in deterministic order.
	 *
	 * @return string[]
	 */
	private function syncable_roles( int $user_id ): array {
		$roles = array_values( array_diff( (array) get_userdata( $user_id )->roles, [ 'rondo_user' ] ) );
		sort( $roles );
		return $roles;
	}

	/**
	 * Remove automatically managed roles so another entry point can be compared.
	 */
	private function remove_syncable_roles( int $user_id ): void {
		$user = new \WP_User( $user_id );
		foreach ( array_diff( (array) $user->roles, [ 'rondo_user' ] ) as $role ) {
			$user->remove_role( $role );
		}
	}
}
