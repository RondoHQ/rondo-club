<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\REST\Commissies;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/** Regression coverage for the Rondo-local commissie information endpoint. */
class CommissieInfoTest extends RondoTestCase {

	private WP_REST_Server $server;
	private int $commissie_id;

	protected function set_up(): void {
		parent::set_up();

		$this->server       = $this->bootRestControllers( [ Commissies::class, UserSettings::class ] );
		$this->commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Commissie Sportiviteit',
				'post_author' => 1,
			]
		);
	}

	private function request( string $method, string $route, array $params = [] ) {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	private function user( string $role ): int {
		return self::factory()->user->create(
			[
				'role'       => $role,
				'user_login' => uniqid( $role . '_', true ),
			]
		);
	}

	public function test_board_member_can_update_local_commissie_information(): void {
		$board_id = $this->user( 'rondo_bestuur' );
		wp_set_current_user( $board_id );

		$response = $this->request(
			'POST',
			'/rondo/v1/commissies/' . $this->commissie_id . '/info',
			[
				'fields' => [
					'lange_omschrijving' => 'Stimuleert sportief gedrag.',
					'uren_aantal'        => 2.5,
					'uren_periode'       => 'week',
				],
			]
		);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'Stimuleert sportief gedrag.', Fields::get_for_post( $this->commissie_id, 'lange_omschrijving' ) );
		$this->assertSame( 2.5, Fields::get_for_post( $this->commissie_id, 'uren_aantal' ) );
		$this->assertSame( 'week', $response->get_data()['fields']['uren_periode'] );
		$this->assertTrue( UserRoles::can_manage_commissie_info( $board_id ) );
		$this->assertTrue( $this->request( 'GET', '/rondo/v1/user/me' )->get_data()['can_edit_commissie_info'] );
	}

	public function test_administrator_can_update_local_commissie_information(): void {
		$admin_id = $this->user( 'administrator' );
		wp_set_current_user( $admin_id );

		$response = $this->request(
			'POST',
			'/rondo/v1/commissies/' . $this->commissie_id . '/info',
			[ 'fields' => [ 'taakomschrijving' => 'Ondersteunt commissieleden.' ] ]
		);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'Ondersteunt commissieleden.', Fields::get_for_post( $this->commissie_id, 'taakomschrijving' ) );
	}

	public function test_non_board_user_cannot_update_local_commissie_information(): void {
		$user_id = $this->user( 'rondo_fairplay' );
		wp_set_current_user( $user_id );

		$response = $this->request(
			'POST',
			'/rondo/v1/commissies/' . $this->commissie_id . '/info',
			[ 'fields' => [ 'lange_omschrijving' => 'Niet toegestaan' ] ]
		);

		$this->assertSame( 403, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( '', Fields::get_for_post( $this->commissie_id, 'lange_omschrijving' ) );
		$this->assertFalse( UserRoles::can_manage_commissie_info( $user_id ) );
	}

	public function test_board_member_cannot_change_non_local_commissie_data(): void {
		$board_id = $this->user( 'rondo_bestuur' );
		wp_set_current_user( $board_id );

		$unknown_field = $this->request(
			'POST',
			'/rondo/v1/commissies/' . $this->commissie_id . '/info',
			[ 'fields' => [ 'website' => 'https://example.com' ] ]
		);
		$core_update   = $this->request(
			'POST',
			'/wp/v2/commissies/' . $this->commissie_id,
			[ 'title' => 'Gewijzigde naam' ]
		);

		$this->assertSame( 400, $unknown_field->get_status(), wp_json_encode( $unknown_field->get_data() ) );
		$this->assertSame( 'fields.website', $unknown_field->get_data()['data']['field'] );
		$this->assertSame( 403, $core_update->get_status(), wp_json_encode( $core_update->get_data() ) );
		$this->assertSame( 'Commissie Sportiviteit', get_the_title( $this->commissie_id ) );
	}

	public function test_invalid_local_value_is_rejected_without_writing(): void {
		wp_set_current_user( $this->user( 'rondo_bestuur' ) );

		$response = $this->request(
			'POST',
			'/rondo/v1/commissies/' . $this->commissie_id . '/info',
			[ 'fields' => [ 'uren_periode' => 'jaar' ] ]
		);

		$this->assertSame( 400, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( '', Fields::get_for_post( $this->commissie_id, 'uren_periode' ) );
	}
}
