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
		delete_option( 'rondo_player_stable_version' );
		delete_option( 'rondo_player_beta_version' );
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
				'code'           => $code,
				'title'          => 'Scherm kantine',
				'location'       => 'Kantine',
				'wake_time'      => '07:30',
				'sleep_time'     => '23:15',
				'timezone'       => 'Europe/Amsterdam',
				'update_channel' => 'stable',
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

		wp_set_current_user( $admin_id );
		$updated = $this->dispatch(
			'POST',
			"/rondo/v1/narrowcasting/displays/{$display_id}",
			[
				'title'          => 'Scherm clubhuis',
				'location'       => 'Bestuurskamer',
				'wake_time'      => '08:15',
				'sleep_time'     => '22:45',
				'timezone'       => 'Europe/Brussels',
				'update_channel' => 'stable',
			]
		);
		$this->assertSame( 200, $updated->get_status() );
		$this->assertSame( 'Scherm clubhuis', $updated->get_data()['name'] );
		$this->assertSame( 'Bestuurskamer', $updated->get_data()['location'] );
		$this->assertSame( 'Europe/Brussels', $updated->get_data()['display_timezone'] );

		wp_set_current_user( 0 );
		$config = $this->dispatch(
			'GET',
			'/rondo/v1/narrowcasting/devices/me/config',
			[],
			[ 'X-Rondo-Device-Token' => $token ]
		);
		$this->assertSame( 200, $config->get_status() );
		$this->assertSame( 'Scherm clubhuis', $config->get_data()['name'] );
		$this->assertSame( '08:15', $config->get_data()['wake_time'] );
		$this->assertSame( '22:45', $config->get_data()['sleep_time'] );
		$this->assertSame( 'Europe/Brussels', $config->get_data()['timezone'] );
		$this->assertSame( 'stable', $config->get_data()['update']['channel'] );
		$this->assertSame( '0.3.0', $config->get_data()['update']['target_version'] );
		$this->assertStringContainsString( 'no-store', $config->get_headers()['Cache-Control'] );
		$this->assertSame( 10, $config->get_data()['content_interval_seconds'] );

		$matchday = $this->dispatch(
			'GET',
			'/rondo/v1/narrowcasting/feeds/matchday',
			[],
			[ 'X-Rondo-Device-Token' => $token ]
		);
		$this->assertSame( 200, $matchday->get_status() );
		$this->assertArrayNotHasKey( 'client_id', $matchday->get_data() );
		$this->assertStringContainsString( 'no-store', $matchday->get_headers()['Cache-Control'] );

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
			[ 'command' => 'shutdown' ]
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
		$this->assertSame( 'shutdown', $poll->get_data()['command']['name'] );
		$this->assertStringContainsString( 'no-store', $poll->get_headers()['Cache-Control'] );

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
				'stable_version'     => '0.3.1',
				'beta_version'       => '0.4.0',
			]
		);
		$this->assertSame( 200, $settings->get_status() );
		$this->assertTrue( $settings->get_data()['client_id_configured'] );
		$this->assertSame( '••••••••', $settings->get_data()['client_id_masked'] );
		$this->assertStringNotContainsString( 'RouteSecret123', wp_json_encode( $settings->get_data() ) );
		$this->assertSame( '0.3.1', $settings->get_data()['player_updates']['stable_version'] );
		$this->assertSame( '0.4.0', $settings->get_data()['player_updates']['beta_version'] );

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
		$token = $claim->get_data()['token'];

		$config = $this->dispatch(
			'GET',
			'/rondo/v1/narrowcasting/devices/me/config',
			[],
			[ 'X-Rondo-Device-Token' => $token ]
		);
		$this->assertSame( 'stable', $config->get_data()['update']['channel'] );
		$this->assertSame( '0.3.1', $config->get_data()['update']['target_version'] );

		wp_set_current_user( $admin_id );
		$beta = $this->dispatch(
			'POST',
			"/rondo/v1/narrowcasting/displays/{$display_id}",
			[
				'title'          => 'Bestuurskamer',
				'update_channel' => 'beta',
			]
		);
		$this->assertSame( 200, $beta->get_status() );
		$this->assertSame( 'beta', $beta->get_data()['update_channel'] );
		$this->assertSame( '0.4.0', $beta->get_data()['update_target_version'] );

		$invalid_version = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/settings',
			[ 'stable_version' => 'latest' ]
		);
		$this->assertSame( 400, $invalid_version->get_status() );

		$invalid_command = $this->dispatch(
			'POST',
			"/rondo/v1/narrowcasting/displays/{$display_id}/commands",
			[ 'command' => 'run_arbitrary_shell' ]
		);
		$this->assertSame( 400, $invalid_command->get_status() );
	}

	public function test_browser_presentation_pairing_and_signaling_flow(): void {
		$device_token = 'presentation-device-token';
		$display_id   = self::factory()->post->create(
			[
				'post_type'   => 'rondo_display',
				'post_status' => 'publish',
				'post_title'  => 'Scherm bestuurskamer',
			]
		);
		Fields::update_many_for_post(
			$display_id,
			[
				'device_id'            => 'rondo-pi-presentation-001',
				'device_secret_hash'   => hash_hmac( 'sha256', $device_token, wp_salt( 'auth' ) ),
				'pairing_status'       => 'paired',
				'presentation_enabled' => false,
			]
		);

		$disabled = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/me/presentation/session',
			[],
			[ 'X-Rondo-Device-Token' => $device_token ]
		);
		$this->assertSame( 403, $disabled->get_status() );

		Fields::update_for_post( $display_id, 'presentation_enabled', true );
		$created = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/devices/me/presentation/session',
			[],
			[ 'X-Rondo-Device-Token' => $device_token ]
		);
		$this->assertSame( 200, $created->get_status() );
		$this->assertMatchesRegularExpression( '/^\d{6}$/', $created->get_data()['code'] );
		$this->assertTrue( wp_is_uuid( $created->get_data()['session_id'] ) );
		$this->assertStringContainsString( 'no-store', $created->get_headers()['Cache-Control'] );

		$unauthorized_join = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/presentation/join',
			[ 'code' => $created->get_data()['code'] ]
		);
		$this->assertSame( 401, $unauthorized_join->get_status() );

		wp_set_current_user( $this->createRondoUser() );
		$joined = $this->dispatch(
			'POST',
			'/rondo/v1/narrowcasting/presentation/join',
			[ 'code' => $created->get_data()['code'] ]
		);
		$this->assertSame( 200, $joined->get_status() );
		$this->assertSame( 'Scherm bestuurskamer', $joined->get_data()['display_name'] );
		$this->assertNotSame( $created->get_data()['token'], $joined->get_data()['token'] );

		$session_route = "/rondo/v1/narrowcasting/presentation/sessions/{$created->get_data()['session_id']}/signal";
		$offer         = [
			'description' => [
				'type' => 'offer',
				'sdp'  => "v=0\r\n",
			],
			'candidates'  => [],
			'hangup'      => false,
		];
		$stored_offer  = $this->dispatch_json(
			'POST',
			$session_route,
			$offer,
			[ 'X-Rondo-Presentation-Token' => $joined->get_data()['token'] ]
		);
		$this->assertSame( 200, $stored_offer->get_status() );

		$receiver_signal = $this->dispatch(
			'GET',
			$session_route,
			[],
			[ 'X-Rondo-Presentation-Token' => $created->get_data()['token'] ]
		);
		$this->assertSame( 'offer', $receiver_signal->get_data()['signal']['description']['type'] );

		$answer = [
			'description' => [
				'type' => 'answer',
				'sdp'  => "v=0\r\n",
			],
			'candidates'  => [],
			'hangup'      => false,
		];
		$this->assertSame(
			200,
			$this->dispatch_json(
				'POST',
				$session_route,
				$answer,
				[ 'X-Rondo-Presentation-Token' => $created->get_data()['token'] ]
			)->get_status()
		);

		$sender_signal = $this->dispatch(
			'GET',
			$session_route,
			[],
			[ 'X-Rondo-Presentation-Token' => $joined->get_data()['token'] ]
		);
		$this->assertSame( 'answer', $sender_signal->get_data()['signal']['description']['type'] );

		$wrong_token = $this->dispatch(
			'GET',
			$session_route,
			[],
			[ 'X-Rondo-Presentation-Token' => 'wrong-token' ]
		);
		$this->assertSame( 401, $wrong_token->get_status() );
	}

	private function dispatch( string $method, string $route, array $params = [], array $headers = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_body_params( $params );
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}

		return $this->server->dispatch( $request );
	}

	private function dispatch_json( string $method, string $route, array $params, array $headers = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}

		return $this->server->dispatch( $request );
	}
}
