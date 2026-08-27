<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\REST\Api;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Regression coverage for the isolated Kaderlijst capability. */
class KaderlijstAccessTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();

		if ( ! get_role( UserRoles::KADERLIJST_ROLE ) ) {
			add_role(
				UserRoles::KADERLIJST_ROLE,
				'Rondo Kaderlijst',
				[ 'read' => true ]
			);
		}
		get_role( UserRoles::KADERLIJST_ROLE )->add_cap( UserRoles::KADERLIJST_CAPABILITY );
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_kaderlijst_cache_generation' );
	}

	protected function tear_down(): void {
		remove_role( 'rondo_test_general_staff' );
		delete_option( 'rondo_age_group_access' );
		delete_option( 'rondo_kaderlijst_cache_generation' );
		parent::tear_down();
	}

	public function test_dedicated_role_opens_only_the_kaderlijst_surface(): void {
		$user_id = $this->createRondoUser();
		( new \WP_User( $user_id ) )->add_role( UserRoles::KADERLIJST_ROLE );
		wp_set_current_user( $user_id );

		$user_data = ( new UserSettings() )->get_current_user_data( $user_id );

		$this->assertFalse( $user_data['is_kader'] );
		$this->assertFalse( $user_data['has_extra_roles'] );
		$this->assertTrue( $user_data['can_access_kaderlijst'] );
	}

	public function test_existing_extra_roles_remain_general_kader_roles(): void {
		add_role( 'rondo_test_general_staff', 'Algemeen kader', [ 'read' => true ] );
		$user_id = $this->createRondoUser();
		( new \WP_User( $user_id ) )->add_role( 'rondo_test_general_staff' );
		wp_set_current_user( $user_id );

		$user_data = ( new UserSettings() )->get_current_user_data( $user_id );

		$this->assertTrue( $user_data['is_kader'] );
		$this->assertTrue( $user_data['has_extra_roles'] );
		$this->assertTrue( $user_data['can_access_kaderlijst'] );
	}

	public function test_dedicated_role_never_widens_general_person_visibility(): void {
		$user_id = $this->createRondoUser();
		( new \WP_User( $user_id ) )->add_role( UserRoles::KADERLIJST_ROLE );
		$other_person = $this->createPerson( [ 'post_title' => 'Niet zichtbaar als algemeen lid' ] );

		update_option(
			'rondo_age_group_access',
			[ UserRoles::KADERLIJST_ROLE => [ 'Onder 10', 'Senioren' ] ]
		);
		wp_set_current_user( $user_id );

		$this->assertSame( [], AccessControl::get_permitted_age_groups() );
		$this->assertFalse( AccessControl::can_view_person( $other_person ) );
	}

	public function test_dedicated_role_receives_the_full_kaderlijst_payload(): void {
		$user_id = $this->createRondoUser();
		( new \WP_User( $user_id ) )->add_role( UserRoles::KADERLIJST_ROLE );
		wp_set_current_user( $user_id );

		$team_id   = $this->createOrganization( [ 'post_title' => 'JO10-1' ] );
		$person_id = $this->createPerson(
			[ 'post_title' => 'Trainer Toernooi' ],
			[
				'first_name'   => 'Trainer',
				'last_name'    => 'Toernooi',
				'email_1'      => 'trainer@example.com',
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

		$response = ( new Api() )->get_kaderlijst_people( new WP_REST_Request( 'GET', '/rondo/v1/kaderlijst/people' ) );
		$people   = $response->get_data()['people'];
		$person   = current( array_filter( $people, static fn( array $item ): bool => (int) $item['id'] === $person_id ) );

		$this->assertIsArray( $person );
		$this->assertSame( 'trainer@example.com', $person['fields']['email_1'] );
		$this->assertNotEmpty( $person['fields']['work_history'] );
	}

	public function test_endpoint_rejects_plain_members_and_accepts_dedicated_role(): void {
		$server = $this->bootRestControllers( [ Api::class ] );

		$plain_user_id = $this->createRondoUser();
		wp_set_current_user( $plain_user_id );
		$forbidden = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/kaderlijst/people' ) );

		$this->assertSame( 403, $forbidden->get_status() );

		$kaderlijst_user_id = $this->createRondoUser();
		( new \WP_User( $kaderlijst_user_id ) )->add_role( UserRoles::KADERLIJST_ROLE );
		wp_set_current_user( $kaderlijst_user_id );
		$allowed = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/kaderlijst/people' ) );

		$this->assertSame( 200, $allowed->get_status() );
	}
}
