<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Current REST CRUD contract for Rondo CPTs.
 *
 * Administrators and specialist roles may mutate their domains. Plain members
 * can read only the household/club-structure surfaces and must never inherit
 * generic WordPress post CRUD permissions.
 */
class CptCrudTest extends RondoTestCase {

	private WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$this->server   = rest_get_server();
		$wp_rest_server = $this->server;

		AccessControl::flush_visible_person_ids_cache();
		delete_option( 'rondo_age_group_access' );
	}

	private function user( string $role = UserRoles::ROLE_NAME ): int {
		$user_id = self::factory()->user->create(
			[
				'role'       => $role,
				'user_login' => uniqid( $role . '_', true ),
			]
		);
		UserRoles::sync_role_capabilities( $role );
		return $user_id;
	}

	private function request( string $method, string $route, array $params = [] ) {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	private function assertDenied( $response, string $message = '' ): void {
		$this->assertContains( $response->get_status(), [ 401, 403, 404 ], $message ?: 'Request should be denied.' );
	}

	public function test_administrator_can_crud_person_with_acf(): void {
		$admin_id = $this->user( 'administrator' );
		wp_set_current_user( $admin_id );

		$create = $this->request(
			'POST',
			'/wp/v2/people',
			[
				'title'  => 'CRUD Person',
				'status' => 'publish',
				'acf'    => [
					'first_name' => 'CRUD',
					'last_name'  => 'Person',
				],
			]
		);
		$this->assertSame( 201, $create->get_status() );
		$person_id = (int) $create->get_data()['id'];

		$read = $this->request( 'GET', '/wp/v2/people/' . $person_id );
		$this->assertSame( 200, $read->get_status() );
		$this->assertSame( 'CRUD', $read->get_data()['acf']['first_name'] );

		$update = $this->request(
			'PATCH',
			'/wp/v2/people/' . $person_id,
			[ 'acf' => [ 'first_name' => 'Updated' ] ]
		);
		$this->assertSame( 200, $update->get_status() );
		$this->assertSame( 'Updated', get_field( 'first_name', $person_id ) );

		$delete = $this->request( 'DELETE', '/wp/v2/people/' . $person_id, [ 'force' => true ] );
		$this->assertSame( 200, $delete->get_status() );
		$this->assertNull( get_post( $person_id ) );
	}

	public function test_plain_member_reads_only_linked_household(): void {
		$me       = $this->createPerson( [ 'post_title' => 'Me' ] );
		$stranger = $this->createPerson( [ 'post_title' => 'Stranger' ] );
		$user_id  = $this->user();
		update_user_meta( $user_id, 'rondo_linked_person_id', $me );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$this->assertSame( 200, $this->request( 'GET', '/wp/v2/people/' . $me )->get_status() );
		$this->assertDenied( $this->request( 'GET', '/wp/v2/people/' . $stranger ) );

		$list = $this->request( 'GET', '/wp/v2/people', [ 'per_page' => 100 ] );
		$this->assertSame( 200, $list->get_status() );
		$this->assertSame( [ $me ], array_map( 'intval', array_column( $list->get_data(), 'id' ) ) );
	}

	public function test_plain_member_cannot_create_update_or_delete_people(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Protected Person' ] );
		$user_id   = $this->user();
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$this->assertDenied(
			$this->request(
				'POST',
				'/wp/v2/people',
				[
					'title'  => 'Forged',
					'status' => 'publish',
				]
				)
		);
		$this->assertDenied(
			$this->request( 'PATCH', '/wp/v2/people/' . $person_id, [ 'title' => 'Changed' ] )
		);
		$this->assertDenied( $this->request( 'DELETE', '/wp/v2/people/' . $person_id ) );
		$this->assertSame( 'Protected Person', get_post( $person_id )->post_title );
	}

	public function test_plain_member_can_read_but_not_mutate_teams_and_commissies(): void {
		$admin_id     = $this->user( 'administrator' );
		$team_id      = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_author' => $admin_id,
			]
			);
		$commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_author' => $admin_id,
			]
			);
		$user_id      = $this->user();
		wp_set_current_user( $user_id );

		$this->assertSame( 200, $this->request( 'GET', '/wp/v2/teams/' . $team_id )->get_status() );
		$this->assertSame( 200, $this->request( 'GET', '/wp/v2/commissies/' . $commissie_id )->get_status() );
		$this->assertDenied(
			$this->request(
			'POST',
			'/wp/v2/teams',
			[
				'title'  => 'Forged team',
				'status' => 'publish',
			]
			)
			);
		$this->assertDenied( $this->request( 'PATCH', '/wp/v2/teams/' . $team_id, [ 'title' => 'Changed' ] ) );
		$this->assertDenied( $this->request( 'DELETE', '/wp/v2/commissies/' . $commissie_id ) );
	}

	/** @dataProvider sensitiveCoreRoutes */
	public function test_plain_member_cannot_read_or_create_sensitive_core_cpts( string $post_type, string $rest_base ): void {
		$admin_id = $this->user( 'administrator' );
		$post_id  = self::factory()->post->create(
			[
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_author' => $admin_id,
				'post_title'  => 'Sensitive record',
			]
		);

		$user_id = $this->user();
		wp_set_current_user( $user_id );

		$this->assertFalse(
			current_user_can( 'read_post', $post_id ),
			'Core read meta-cap unexpectedly allowed for ' . $post_type . ': ' . wp_json_encode( get_post_type_object( $post_type )->cap )
		);
		$this->assertDenied( $this->request( 'GET', '/wp/v2/' . $rest_base . '/' . $post_id ) );
		$this->assertDenied(
			$this->request(
				'POST',
				'/wp/v2/' . $rest_base,
				[
					'title'  => 'Forged record',
					'status' => 'publish',
				]
				)
		);
	}

	public static function sensitiveCoreRoutes(): array {
		return [
			'invoices'         => [ 'rondo_invoice', 'invoices' ],
			'discipline cases' => [ 'discipline_case', 'discipline-cases' ],
			'clothing items'   => [ 'rondo_clothing_item', 'clothing-items' ],
			'dienst types'     => [ 'dienst_type', 'dienst-types' ],
			'shift templates'  => [ 'shift_template', 'shift-templates' ],
			'dienst shifts'    => [ 'dienst_shift', 'dienst-shifts' ],
			'taakuitleg items' => [ 'taakuitleg', 'taakuitleg' ],
		];
	}

	public function test_finance_role_can_read_invoices_but_plain_member_cannot(): void {
		$admin_id   = $this->user( 'administrator' );
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'publish',
				'post_author' => $admin_id,
			]
		);

		$finance_id = $this->user( 'rondo_financieel_lezen' );
		wp_set_current_user( $finance_id );
		$this->assertSame( 200, $this->request( 'GET', '/wp/v2/invoices/' . $invoice_id )->get_status() );

		$member_id = $this->user();
		wp_set_current_user( $member_id );
		$this->assertDenied( $this->request( 'GET', '/wp/v2/invoices/' . $invoice_id ) );
	}

	public function test_volunteer_manager_can_crud_shift_configuration(): void {
		$user_id = $this->user( 'rondo_vrijwilligers' );
		wp_set_current_user( $user_id );

		$create = $this->request(
			'POST',
			'/wp/v2/dienst-types',
			[
				'title'  => 'Kantinedienst',
				'status' => 'publish',
			]
		);
		$this->assertSame( 201, $create->get_status() );
		$post_id = (int) $create->get_data()['id'];

		$this->assertSame( 200, $this->request( 'PATCH', '/wp/v2/dienst-types/' . $post_id, [ 'title' => 'Bardienst' ] )->get_status() );
		$this->assertSame( 200, $this->request( 'DELETE', '/wp/v2/dienst-types/' . $post_id, [ 'force' => true ] )->get_status() );
	}

	public function test_membership_administrator_can_edit_people(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Member record' ] );
		$user_id   = $this->user( 'rondo_ledenadministratie' );
		wp_set_current_user( $user_id );

		$response = $this->request( 'PATCH', '/wp/v2/people/' . $person_id, [ 'title' => 'Updated member record' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated member record', get_post( $person_id )->post_title );
	}

	public function test_household_endpoint_stays_personal_for_administrators(): void {
		$self     = $this->createPerson( [ 'post_title' => 'Administrator self' ] );
		$stranger = $this->createPerson( [ 'post_title' => 'Other member' ] );
		$admin_id = $this->user( 'administrator' );
		update_user_meta( $admin_id, 'rondo_linked_person_id', $self );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $admin_id );

		$response = ( new People() )->get_household();
		$ids      = array_map( 'intval', array_column( $response->get_data(), 'id' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( $self, $ids );
		$this->assertNotContains( $stranger, $ids );
	}
}
