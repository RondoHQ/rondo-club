<?php

namespace Tests\Wpunit;

use Rondo\MobilePilot\Plugin;
use Rondo\REST\UserSettings;
use Rondo\REST\People;
use Rondo\REST\MembershipPasses;
use Tests\Support\RondoTestCase;

/** Real policy with synthetic users and a local MySQL database; never contacts AWC. */
final class MobilePilotTest extends RondoTestCase {
	private const VERIFIER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private int $pilot_user;
	private int $person;

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'RONDO_MOBILE_PILOT' ) ) {
			define( 'RONDO_MOBILE_PILOT', true );
		}
		require_once dirname( __DIR__, 2 ) . '/mobile/pilot-plugin/rondo-mobile-pilot.php';
		update_option( 'home', 'https://rondo.svawc.nl' );
		$this->pilot_user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->person     = self::factory()->post->create(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
			]
			);
		update_user_meta( $this->pilot_user, 'rondo_linked_person_id', $this->person );
		update_option(
			'rondo_mobile_pilot',
			[
				'enabled' => true,
				'epoch'   => str_repeat( 'e', 32 ),
				'ends_at' => time() + DAY_IN_SECONDS,
				'testers' => [
					[
						'user_id'   => $this->pilot_user,
						'person_id' => $this->person,
					],
				],
			],
			false
			);
		$this->bootRestControllers( [ UserSettings::class, People::class, MembershipPasses::class, Plugin::class ] );
	}

	private function params(): array {
		return [
			'client_id'             => Plugin::CLIENT,
			'redirect_uri'          => Plugin::CALLBACK,
			'scope'                 => Plugin::SCOPE,
			'response_type'         => 'code',
			'code_challenge_method' => 'S256',
			'code_challenge'        => rtrim( strtr( base64_encode( hash( 'sha256', self::VERIFIER, true ) ), '+/', '-_' ), '=' ),
			'state'                 => str_repeat( 's', 43 ),
		];
	}

	private function post( string $route, array $data, string $token = '' ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		if ( $token !== '' ) {
			$request->set_header( 'Authorization', 'Bearer ' . $token );
		}
		$request->set_body( wp_json_encode( $data ) );
		return rest_do_request( $request );
	}

	private function exchange( string $code, array $extra = [] ): \WP_REST_Response {
		return $this->post(
			'token',
			array_merge(
			[
				'grant_type'    => 'authorization_code',
				'client_id'     => Plugin::CLIENT,
				'redirect_uri'  => Plugin::CALLBACK,
				'code'          => $code,
				'code_verifier' => self::VERIFIER,
			],
			$extra
			)
			);
	}

	private function pair(): array {
		$code = Plugin::issue( $this->params(), $this->pilot_user );
		$this->assertIsString( $code );
		$response = $this->exchange( $code );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	private function read( string $token, array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'GET', '/' . Plugin::NS . '/read' );
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		$request->set_query_params( array_merge( [ 'resource' => 'me' ], $params ) );
		return rest_do_request( $request );
	}

	public function test_only_pinned_testers_can_authorize_read_scope_and_https_callback(): void {
		$outsider = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->assertWPError( Plugin::issue( $this->params(), $outsider ) );
		foreach ( [ [ 'scope' => Plugin::PROFILE_SCOPE ], [ 'scope' => Plugin::MEMBER_SCOPE ], [ 'redirect_uri' => 'club.rondo.spike://oauth/callback' ], [ 'redirect_uri' => Plugin::CALLBACK . '/extra' ], [ 'client_id' => 'rondo-mobile-spike' ] ] as $extra ) {
			$this->assertWPError( Plugin::issue( array_merge( $this->params(), $extra ), $this->pilot_user ) );
		}
		$pair = $this->pair();
		$this->assertSame( Plugin::SCOPE, $pair['scope'] );
		$this->assertLessThanOrEqual( time() + 7 * DAY_IN_SECONDS, $pair['refresh_expires_at'] );
		$this->assertSame( 200, $this->read( $pair['access_token'] )->get_status() );
		$this->assertSame( 'no-store', $this->read( $pair['access_token'] )->get_headers()['Cache-Control'] );
	}

	public function test_disabling_expiry_removal_and_relinking_block_existing_sessions(): void {
		$config = Plugin::settings();
		foreach ( [ 'disabled', 'deadline', 'removed', 'relinked', 'epoch', 'password' ] as $change ) {
			update_option( 'rondo_mobile_pilot', $config, false );
			update_user_meta( $this->pilot_user, 'rondo_linked_person_id', $this->person );
			$pair    = $this->pair();
			$changed = $config;
			if ( $change === 'disabled' ) {
				$changed['enabled'] = false;
			}
			if ( $change === 'deadline' ) {
				$changed['ends_at'] = time() - 1;
			}
			if ( $change === 'removed' ) {
				$changed['testers'] = [];
			}
			if ( $change === 'epoch' ) {
				$changed['epoch'] = str_repeat( 'n', 32 );
			}
			if ( $change === 'relinked' ) {
				update_user_meta( $this->pilot_user, 'rondo_linked_person_id', $this->person + 999 );
			}
			if ( $change === 'password' ) {
				wp_set_password( 'new-pass', $this->pilot_user );
			}
			update_option( 'rondo_mobile_pilot', $changed, false );
			$this->assertNotSame( 200, $this->read( $pair['access_token'] )->get_status(), $change );
			$this->assertNotSame(
				200,
				$this->post(
				'token',
				[
					'grant_type'    => 'refresh_token',
					'client_id'     => Plugin::CLIENT,
					'refresh_token' => $pair['refresh_token'],
				]
				)->get_status(),
				$change
				);
		}
	}

	public function test_writes_and_foreign_passes_are_blocked_even_with_valid_session(): void {
		$pair = $this->pair();
		foreach ( [ 'profile', 'shift' ] as $route ) {
			$response = $this->post(
				$route,
				[
					'action'   => 'signup',
					'shift_id' => 1,
				],
				$pair['access_token']
				);
			$this->assertSame( 403, $response->get_status() );
			$this->assertSame( 'read_only_pilot', $response->get_data()['code'] );
		}
		$foreign = self::factory()->post->create(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
			]
			);
		$this->assertSame(
			403,
			$this->read(
			$pair['access_token'],
			[
				'resource'  => 'pass',
				'person_id' => $foreign,
			]
			)->get_status()
			);
		$this->assertSame(
			403,
			$this->post(
			'wallet',
			[
				'person_id' => $foreign,
				'provider'  => 'apple',
			],
			$pair['access_token']
			)->get_status()
			);
		$this->assertSame( 400, $this->read( $pair['access_token'], [ 'resource' => '/wp/v2/users' ] )->get_status() );
		$this->assertSame(
			401,
			$this->post(
			'wallet',
			[
				'person_id' => $this->person,
				'provider'  => 'apple',
			]
			)->get_status()
			);
	}

	public function test_wrong_verifier_replay_and_spike_client_cannot_exchange(): void {
		$code = Plugin::issue( $this->params(), $this->pilot_user );
		$this->assertSame( 400, $this->exchange( $code, [ 'code_verifier' => str_repeat( 'z', 43 ) ] )->get_status() );
		$this->assertSame( 400, $this->exchange( $code, [ 'client_id' => 'rondo-mobile-spike' ] )->get_status() );
		$this->assertSame( 200, $this->exchange( $code )->get_status() );
		$this->assertSame( 400, $this->exchange( $code )->get_status() );
	}

	public function test_refresh_reuse_revokes_pilot_family(): void {
		$first  = $this->pair();
		$params = [
			'grant_type'    => 'refresh_token',
			'client_id'     => Plugin::CLIENT,
			'refresh_token' => $first['refresh_token'],
		];
		$second = $this->post( 'token', $params );
		$this->assertSame( 200, $second->get_status() );
		$this->assertSame( 400, $this->post( 'token', $params )->get_status() );
		$this->assertSame( 401, $this->read( $second->get_data()['access_token'] )->get_status() );
	}

	public function test_pilot_is_disabled_on_other_origins_or_missing_configuration(): void {
		update_option( 'home', 'https://other.example.test' );
		$this->assertFalse( Plugin::enabled() );
		update_option( 'home', 'https://rondo.svawc.nl' );
		delete_option( 'rondo_mobile_pilot' );
		$this->assertFalse( Plugin::enabled() );
	}

	public function test_rate_limit_is_atomic_and_does_not_trust_forwarded_ip(): void {
		$_SERVER['REMOTE_ADDR'] = '192.0.2.41';
		$request                = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/token' );
		$plugin                 = new Plugin();
		for ( $i = 0; $i < 60; ++$i ) {
			$this->assertTrue( $plugin->route_permission( $request ) );
		}
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.99';
		$this->assertWPError( $plugin->route_permission( $request ) );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
	}
}
