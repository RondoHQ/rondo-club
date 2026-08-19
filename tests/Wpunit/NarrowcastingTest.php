<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Narrowcasting;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * End-to-end contract tests for player pairing and control.
 */
class NarrowcastingTest extends RondoTestCase {

	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		$_SERVER['REMOTE_ADDR'] = '192.0.2.' . random_int( 1, 250 );
		$this->server           = $this->bootRestControllers( [ Narrowcasting::class ] );
	}

	protected function tear_down(): void {
		delete_option( 'rondo_narrowcasting_sportlink_client_id' );
		delete_option( 'rondo_narrowcasting_sportlink_club_code' );
		delete_option( 'rondo_narrowcasting_matchday_cache' );
		delete_option( 'rondo_narrowcasting_default_playlist_id' );
		delete_option( 'rondo_finance_accent_color' );
		delete_option( 'rondo_finance_accent_background_color' );
		delete_transient( 'rondo_narrowcasting_matchday_refresh_lock' );
		delete_transient( 'rondo_narrowcasting_manual_refresh_lock' );
		parent::tear_down();
	}

	public function test_pairing_heartbeat_command_and_revocation_flow(): void {
		$device_id = 'rondo-pi-test-001';
		$register  = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/register',
			[ 'device_id' => $device_id ]
		);

		$this->assertSame( 200, $register->get_status() );
		$code = $register->get_data()['code'];
		$this->assertMatchesRegularExpression( '/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code );
		$this->assertFalse( $register->get_data()['approved'] );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$approve = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/displays/claim',
			[
				'code'       => $code,
				'title'      => 'Scherm kantine',
				'location'   => 'Kantine',
				'wake_time'  => '07:30',
				'sleep_time' => '23:15',
				'timezone'   => 'Europe/Amsterdam',
			]
		);

		$this->assertSame( 200, $approve->get_status() );
		$display_id = $approve->get_data()['id'];
		$this->assertSame( 'approved', Fields::get_for_post( $display_id, 'pairing_status' ) );

		wp_set_current_user( 0 );
		$claim = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/claim',
			[
				'device_id' => $device_id,
				'code'      => $code,
			]
		);

		$this->assertSame( 200, $claim->get_status() );
		$token = $claim->get_data()['token'];
		$this->assertGreaterThan( 40, strlen( $token ) );
		$this->assertSame( 'paired', Fields::get_for_post( $display_id, 'pairing_status' ) );
		$this->assertNotSame( $token, Fields::get_for_post( $display_id, 'device_secret_hash' ) );

		$config = $this->dispatch(
			'GET',
			'/rondo/v1/narrowcasting/devices/me/config',
			[],
			[ 'X-Rondo-Device-Token' => $token ]
		);
		$this->assertSame( 200, $config->get_status() );
		$this->assertSame( 'Scherm kantine', $config->get_data()['name'] );
		$this->assertSame( '07:30', $config->get_data()['wake_time'] );

		$matchday = $this->dispatch(
			'GET',
			'/rondo/v1/narrowcasting/feeds/matchday',
			[],
			[ 'X-Rondo-Device-Token' => $token ]
		);
		$this->assertSame( 200, $matchday->get_status() );
		$this->assertArrayNotHasKey( 'client_id', $matchday->get_data() );

		$heartbeat = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/me/heartbeat',
			[
				'state'   => 'playing',
				'version' => '0.1.0',
			],
			[ 'X-Rondo-Device-Token' => $token ]
		);
		$this->assertSame( 200, $heartbeat->get_status() );

		wp_set_current_user( $admin_id );
		$list = $this->dispatch( 'GET', '/rondo/v1/narrowcasting/displays' );
		$this->assertSame( 200, $list->get_status() );
		$this->assertTrue( $list->get_data()[0]['online'] );
		$this->assertSame( '0.1.0', $list->get_data()[0]['player_version'] );
		$this->assertArrayNotHasKey( 'device_secret_hash', $list->get_data()[0] );

		$queued = $this->dispatch(
			'POST',
			"/rondo/v1/narrowcasting/displays/{$display_id}/commands",
			[ 'command' => 'wake_tv' ]
		);
		$this->assertSame( 200, $queued->get_status() );
		$command_id = $queued->get_data()['command']['id'];

		wp_set_current_user( 0 );
		$poll = $this->dispatch(
			'GET',
			'/rondo/v1/narrowcasting/devices/me/commands',
			[],
			[ 'Authorization' => "Bearer {$token}" ]
		);
		$this->assertSame( 'wake_tv', $poll->get_data()['command']['name'] );

		$ack = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/me/commands/ack',
			[
				'command_id' => $command_id,
				'status'     => 'completed',
			],
			[ 'X-Rondo-Device-Token' => $token ]
		);
		$this->assertSame( 200, $ack->get_status() );
		$this->assertSame( '', Fields::get_for_post( $display_id, 'pending_command' ) );

		wp_set_current_user( $admin_id );
		$revoke = $this->dispatch( 'POST', "/rondo/v1/narrowcasting/displays/{$display_id}/revoke" );
		$this->assertSame( 200, $revoke->get_status() );

		wp_set_current_user( 0 );
		$rejected = $this->dispatch(
			'GET',
			'/rondo/v1/narrowcasting/devices/me/config',
			[],
			[ 'X-Rondo-Device-Token' => $token ]
		);
		$this->assertSame( 401, $rejected->get_status() );
	}

	public function test_admin_routes_and_device_identity_are_protected(): void {
		$unauthorized = $this->dispatch( 'GET', '/rondo/v1/narrowcasting/displays' );
		$this->assertSame( 401, $unauthorized->get_status() );
		$unauthorized_preview = $this->dispatch( 'GET', '/rondo/v1/narrowcasting/preview' );
		$this->assertSame( 401, $unauthorized_preview->get_status() );
		$unauthorized_matchday = $this->dispatch( 'GET', '/rondo/v1/narrowcasting/feeds/matchday' );
		$this->assertSame( 401, $unauthorized_matchday->get_status() );

		$register = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/register',
			[ 'device_id' => 'rondo-pi-owner-001' ]
		);
		$code     = $register->get_data()['code'];

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		update_option( 'rondo_finance_accent_color', '#c8102e' );
		update_option( 'rondo_finance_accent_background_color', '#fff7f7' );
		$preview = $this->dispatch( 'GET', '/rondo/v1/narrowcasting/preview' );
		$this->assertSame( 200, $preview->get_status() );
		$this->assertTrue( $preview->get_data()['preview'] );
		$this->assertSame( 'Voorbeeldscherm', $preview->get_data()['name'] );
		$this->assertSame( '#c8102e', $preview->get_data()['branding']['accent_color'] );
		$this->assertSame( '#fff7f7', $preview->get_data()['branding']['background_color'] );
		$this->assertArrayHasKey( 'logo_url', $preview->get_data()['branding'] );
		$this->assertArrayNotHasKey( 'device_secret_hash', $preview->get_data() );
		$preview_request = new WP_REST_Request( 'GET', '/rondo/v1/narrowcasting/feeds/matchday' );
		$preview_request->set_query_params( [ 'preview' => '1' ] );
		$preview_matchday = $this->server->dispatch( $preview_request );
		$this->assertSame( 200, $preview_matchday->get_status() );
		$this->assertSame( '6', wp_date( 'N', strtotime( $preview_matchday->get_data()['target_date'] ), wp_timezone() ) );

		$settings = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/settings',
			[
				'client_id'          => 'RouteSecret123',
				'club_relation_code' => 'BBKX38Z',
			]
		);
		$this->assertSame( 200, $settings->get_status() );
		$this->assertTrue( $settings->get_data()['client_id_configured'] );
		$this->assertSame( '••••••••', $settings->get_data()['client_id_masked'] );
		$this->assertStringNotContainsString( 'RouteSecret123', wp_json_encode( $settings->get_data() ) );

		$approve    = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/displays/claim',
			[
				'code'  => $code,
				'title' => 'Bestuurskamer',
			]
		);
		$display_id = $approve->get_data()['id'];

		wp_set_current_user( 0 );
		$mismatch = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/claim',
			[
				'device_id' => 'rondo-pi-attacker-001',
				'code'      => $code,
			]
		);
		$this->assertSame( 403, $mismatch->get_status() );

		$claim = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/claim',
			[
				'device_id' => 'rondo-pi-owner-001',
				'code'      => $code,
			]
		);
		$this->assertSame( 200, $claim->get_status() );

		wp_set_current_user( $admin_id );
		$invalid_command = $this->dispatch(
			'POST',
			"/rondo/v1/narrowcasting/displays/{$display_id}/commands",
			[ 'command' => 'run_arbitrary_shell' ]
		);
		$this->assertSame( 400, $invalid_command->get_status() );
	}

	private function dispatch( string $method, string $route, array $params = [], array $headers = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_body_params( $params );
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}

		return $this->server->dispatch( $request );
	}
}
