<?php

namespace Tests\Wpunit;

use Rondo\Config\FeatureToggles;
use Rondo\REST\Api;
use Rondo\REST\Clothing;
use Rondo\REST\Narrowcasting;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Feature toggle defaults, permissions, persistence, and REST management. */
class FeatureTogglesTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		delete_option( FeatureToggles::OPTION_NAME );
		delete_option( 'rondo_rooms_enabled' );
	}

	protected function tear_down(): void {
		delete_option( FeatureToggles::OPTION_NAME );
		delete_option( 'rondo_rooms_enabled' );
		parent::tear_down();
	}

	public function test_defaults_preserve_existing_features_and_keep_rooms_off(): void {
		$this->assertSame(
			[
				'rooms'         => FeatureToggles::OFF,
				'clothing'      => FeatureToggles::ON,
				'narrowcasting' => FeatureToggles::ON,
			],
			FeatureToggles::get_all()
		);
	}

	public function test_legacy_rooms_option_remains_compatible(): void {
		update_option( 'rondo_rooms_enabled', true );
		$this->assertSame( FeatureToggles::ON, FeatureToggles::get_state( 'rooms' ) );
		$this->assertTrue( \rondo_rooms_enabled() );
	}

	public function test_admin_only_allows_only_administrators(): void {
		FeatureToggles::update( [ 'clothing' => FeatureToggles::ADMIN_ONLY ] );
		$member_id = $this->createRondoUser();
		$admin_id  = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$this->assertFalse( FeatureToggles::can_access( 'clothing', $member_id ) );
		$this->assertTrue( FeatureToggles::can_access( 'clothing', $admin_id ) );
		$this->assertTrue( FeatureToggles::is_available( 'clothing' ) );
	}

	public function test_invalid_state_is_rejected_without_changing_values(): void {
		$result = FeatureToggles::update( [ 'rooms' => 'pilot' ] );

		$this->assertWPError( $result );
		$this->assertSame( FeatureToggles::OFF, FeatureToggles::get_state( 'rooms' ) );
	}

	public function test_admin_only_gates_clothing_and_club_tv_rest_access(): void {
		FeatureToggles::update(
			[
				'clothing'      => FeatureToggles::ADMIN_ONLY,
				'narrowcasting' => FeatureToggles::ADMIN_ONLY,
			]
		);
		$server    = $this->bootRestControllers( [ Clothing::class, Narrowcasting::class ] );
		$member_id = $this->createRondoUser();
		$member    = get_user_by( 'id', $member_id );
		$member->add_cap( 'manage_clothing' );
		$member->add_cap( 'narrowcasting' );
		wp_set_current_user( $member_id );

		$this->assertSame( 403, $this->dispatch( $server, 'GET', '/rondo/v1/clothing/items' )->get_status() );
		$this->assertSame( 403, $this->dispatch( $server, 'GET', '/rondo/v1/narrowcasting/items' )->get_status() );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$this->assertSame( 200, $this->dispatch( $server, 'GET', '/rondo/v1/clothing/items' )->get_status() );
		$this->assertSame( 200, $this->dispatch( $server, 'GET', '/rondo/v1/narrowcasting/items' )->get_status() );
	}

	public function test_rest_management_is_admin_only_and_persists_states(): void {
		$server    = $this->bootRestControllers( [ Api::class ] );
		$member_id = $this->createRondoUser();
		wp_set_current_user( $member_id );
		$this->assertSame( 403, $this->dispatch( $server, 'GET', '/rondo/v1/settings/feature-toggles' )->get_status() );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$response = $this->dispatch(
			$server,
			'PUT',
			'/rondo/v1/settings/feature-toggles',
			[ 'states' => [ 'narrowcasting' => FeatureToggles::ADMIN_ONLY ] ]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( FeatureToggles::ADMIN_ONLY, $response->get_data()['states']['narrowcasting'] );
		$this->assertSame( FeatureToggles::ADMIN_ONLY, FeatureToggles::get_state( 'narrowcasting' ) );
	}

	private function dispatch( \WP_REST_Server $server, string $method, string $route, array $params = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( $method === 'GET' ) {
			$request->set_query_params( $params );
		} else {
			$request->set_body_params( $params );
		}
		return $server->dispatch( $request );
	}
}
