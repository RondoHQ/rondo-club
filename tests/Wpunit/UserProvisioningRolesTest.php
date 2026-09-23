<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\Users\ActivationService;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/**
 * New accounts receive current committee access before their first sign-in.
 */
class UserProvisioningRolesTest extends RondoTestCase {

	private string $role_slug;

	protected function set_up(): void {
		parent::set_up();
		add_filter( 'pre_wp_mail', '__return_true' );
		$this->role_slug = UserRoles::add_custom_role( 'Jubilarissen test' );
		get_role( $this->role_slug )->add_cap( 'jubilarissen' );
		// The board also grants jubilarissen; this marker isolates committee-derived access.
		get_role( $this->role_slug )->add_cap( 'test_committee_access' );
		update_option( 'rondo_functie_capability_map', [ 'Bestuurslid test' => [ 'rondo_bestuur' => true ] ] );
	}

	protected function tear_down(): void {
		UserRoles::remove_custom_role( $this->role_slug );
		delete_option( 'rondo_functie_capability_map' );
		delete_option( 'rondo_commissie_capability_map' );
		parent::tear_down();
	}

	private function person( array $membership ): int {
		$commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
			]
			);
		update_option( 'rondo_commissie_capability_map', [ $commissie_id => [ $this->role_slug => true ] ] );
		$person_id = $this->createPerson(
			[],
			[
				'first_name'   => 'Committee',
				'last_name'    => 'Member',
				'work_history' => [
					array_merge(
						[
							'team'        => $commissie_id,
							'entity_type' => 'commissie',
						],
						$membership
						),
					[
						'job_title'  => 'Bestuurslid test',
						'is_current' => true,
					],
				],
			]
		);
		Fields::update_for_post( $person_id, 'email_1', "committee-{$person_id}@example.com" );
		return $person_id;
	}

	public function test_provisioning_grants_committee_and_function_roles_immediately(): void {
		$person_id = $this->person( [ 'is_current' => true ] );
		$result    = ( new UserProvisioning() )->provision( $person_id, false );
		$this->assertIsArray( $result );
		$user = get_userdata( $result['user_id'] );
		$this->assertContains( 'rondo_user', $user->roles );
		$this->assertContains( 'rondo_bestuur', $user->roles );
		$this->assertContains( $this->role_slug, $user->roles );
		$this->assertTrue( user_can( $user, 'test_committee_access' ) );
	}

	public function test_self_service_activation_grants_committee_access(): void {
		$person_id = $this->person( [ 'is_current' => true ] );
		$token     = ActivationService::create_token( "committee-{$person_id}@example.com" );
		$url       = ActivationService::activate( $token, $person_id );
		$this->assertIsString( $url );
		$user_id = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
		$this->assertGreaterThan( 0, $user_id );
		$this->assertTrue( user_can( $user_id, 'test_committee_access' ) );
	}

	public function test_inactive_expired_and_future_memberships_do_not_grant_access(): void {
		foreach ( [
			[ 'is_current' => false ],
			[
				'is_current' => true,
				'end_date'   => '2000-01-01',
			],
			[
				'is_current' => true,
				'start_date' => '2099-01-01',
			],
		] as $membership ) {
			$result = ( new UserProvisioning() )->provision( $this->person( $membership ), false );
			$this->assertIsArray( $result );
			$user = get_userdata( $result['user_id'] );
			$this->assertNotContains( $this->role_slug, $user->roles );
			$this->assertFalse( user_can( $user, 'test_committee_access' ) );
			$this->assertContains( 'rondo_bestuur', $user->roles );
		}
	}
}
