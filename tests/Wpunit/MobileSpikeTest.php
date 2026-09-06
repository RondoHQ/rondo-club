<?php

namespace Tests\Wpunit;

use Rondo\MobileSpike\Plugin;
use Rondo\REST\People;
use Rondo\REST\MemberShifts;
use Rondo\REST\MembershipPasses;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

/** Opt-in spike contract tests; production theme never loads this plugin. */
final class MobileSpikeTest extends RondoTestCase {
	private const VERIFIER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'RONDO_MOBILE_SPIKE' ) ) {
			define( 'RONDO_MOBILE_SPIKE', true );
		}
		require_once dirname( __DIR__, 2 ) . '/mobile/spike-plugin/rondo-mobile-spike.php';
		if ( ! Plugin::enabled() ) {
			$this->markTestSkipped( 'Use WP_ENVIRONMENT_TYPE=local for the development-only mobile spike tests.' );
		}
		$this->bootRestControllers( [ UserSettings::class, People::class, MemberShifts::class, MembershipPasses::class, Plugin::class ] );
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

	private function exchange( string $code, array $overrides = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/token' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
			array_merge(
			[
				'grant_type'    => 'authorization_code',
				'client_id'     => Plugin::CLIENT,
				'redirect_uri'  => Plugin::CALLBACK,
				'code'          => $code,
				'code_verifier' => self::VERIFIER,
			],
			$overrides
			)
			)
			);
		return rest_do_request( $request );
	}

	private function refresh_token( string $token, array $overrides = [] ): \WP_REST_Response {
		return $this->exchange(
			'',
			array_merge(
			[
				'grant_type'    => 'refresh_token',
				'refresh_token' => $token,
			],
			$overrides
			)
			);
	}

	public function test_refresh_rotation_and_reuse_revoke_the_device_family(): void {
		$user  = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$first = $this->exchange( Plugin::issue( $this->params(), $user ) )->get_data();
		$this->assertSame( 300, $first['expires_in'] );
		$second = $this->refresh_token( $first['refresh_token'] );
		$this->assertSame( 200, $second->get_status() );
		$pair = $second->get_data();
		$this->assertNotSame( $first['refresh_token'], $pair['refresh_token'] );
		$this->assertSame( $first['refresh_expires_at'], $pair['refresh_expires_at'] );
		$this->assertSame( 200, $this->read( $pair['access_token'] )->get_status() );
		$this->assertSame( 400, $this->refresh_token( $first['refresh_token'] )->get_status() );
		$this->assertSame( 401, $this->read( $pair['access_token'] )->get_status() );
		$this->assertSame( 400, $this->refresh_token( $pair['refresh_token'] )->get_status() );
	}

	public function test_refresh_revoke_works_without_a_live_access_token(): void {
		$user    = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$pair    = $this->exchange( Plugin::issue( $this->params(), $user ) )->get_data();
		$request = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/revoke' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'refresh_token' => $pair['refresh_token'] ] ) );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( 401, $this->read( $pair['access_token'] )->get_status() );
		$this->assertSame( 400, $this->refresh_token( $pair['refresh_token'] )->get_status() );
	}

	public function test_refresh_requires_client_password_permissions_audience_and_absolute_expiry(): void {
		foreach ( [ 'client', 'password', 'permission', 'audience', 'expiry' ] as $change ) {
			$user       = self::factory()->user->create( [ 'role' => 'subscriber' ] );
			$pair       = $this->exchange( Plugin::issue( $this->params(), $user ) )->get_data();
			$record     = get_option( 'rondo_mobile_refresh_' . hash( 'sha256', $pair['refresh_token'] ) );
			$family_key = 'rondo_mobile_family_' . $record['family'];
			if ( $change === 'password' ) {
				wp_set_password( 'changed-password', $user );
			} elseif ( $change === 'permission' ) {
				( new \WP_User( $user ) )->set_role( '' );
			} elseif ( $change === 'audience' || $change === 'expiry' ) {
				$family = get_option( $family_key );
				$family[ $change === 'audience' ? 'audience' : 'expires_at' ] = $change === 'audience' ? 'https://other.test' : time() - 1;
				update_option( $family_key, $family );
			}
			$this->assertSame( 400, $this->refresh_token( $pair['refresh_token'], $change === 'client' ? [ 'client_id' => 'other' ] : [] )->get_status(), $change );
		}
	}

	private function session( int $user_id ): string {
		$code = Plugin::issue( $this->params(), $user_id );
		$this->assertIsString( $code );
		$response = $this->exchange( $code );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		return $response->get_data()['access_token'];
	}

	private function read( string $token, string $resource = 'me', string $method = 'GET', array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/' . Plugin::NS . '/read' );
		$request->set_param( 'resource', $resource );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		return rest_do_request( $request );
	}

	public function test_exact_redirect_client_scope_and_pkce_are_required(): void {
		foreach ( [
			'redirect_uri'          => 'https://attacker.test',
			'client_id'             => 'freescout',
			'scope'                 => 'write',
			'code_challenge_method' => 'plain',
			'state'                 => 'short',
		] as $key => $value ) {
			$params         = $this->params();
			$params[ $key ] = $value;
			$this->assertWPError( Plugin::validate( $params ) );
		}
	}

	public function test_login_post_preserves_only_valid_local_authorization_redirect(): void {
		$user      = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		$params    = array_merge( $this->params(), [ 'action' => 'rondo_mobile_spike_authorize' ] );
		$requested = add_query_arg( $params, admin_url( 'admin-post.php' ) );
		$fallback  = home_url( '/' );
		$plugin    = new Plugin();
		$this->assertSame( $requested, apply_filters( 'login_redirect', $fallback, $requested, $user ) );
		foreach ( [
			add_query_arg( $params, 'https://attacker.test/wp-admin/admin-post.php' ),
			add_query_arg( $params, home_url( '/other.php' ) ),
			add_query_arg( 'action', 'other', $requested ),
			add_query_arg( 'code_challenge_method', 'plain', $requested ),
			add_query_arg( 'redirect_uri', 'https://attacker.test', $requested ),
			$requested . '#unexpected',
			$fallback,
		] as $invalid ) {
			$this->assertSame( $fallback, $plugin->login_redirect( $fallback, $invalid, $user ) );
		}
		$this->assertSame( $fallback, $plugin->login_redirect( $fallback, $requested, new \WP_Error( 'failed' ) ) );
	}

	public function test_member_calendar_forces_signup_view_and_one_valid_month(): void {
		$user   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$person = $this->createPerson();
		update_user_meta( $user, 'rondo_linked_person_id', $person );
		$token = $this->session( $user );
		$month = current_datetime()->format( 'Y-m' );
		wp_set_current_user( 0 );
		$result = $this->read(
			$token,
			'calendar',
			'GET',
			[
				'month' => $month,
				'view'  => 'manage',
				'from'  => '2000-01-01',
			]
			);
		$this->assertSame( 200, $result->get_status() );
		$this->assertSame( 'signup', $result->get_data()['view'] );
		$this->assertSame( $month . '-01', $result->get_data()['from'] );
		$this->assertSame( current_datetime()->format( 'Y-m-t' ), $result->get_data()['to'] );
		$this->assertSame( 0, get_current_user_id() );
		foreach ( [ '', '2026-13', '2026-2', '2026-01/../../', [ '2026-01' ] ] as $invalid ) {
			$this->assertSame( 400, $this->read( $token, 'calendar', 'GET', [ 'month' => $invalid ] )->get_status() );
		}
		$this->assertSame( 200, $this->read( $token, 'my-shifts' )->get_status() );
		$this->assertSame( $person, $this->read( $token, 'my-shifts' )->get_data()['person_id'] );
	}

	public function test_pass_read_is_limited_to_personal_household_even_for_an_admin(): void {
		$user     = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$person   = $this->createPerson( [], [ 'type-lid' => 'Bondslid' ] );
		$stranger = $this->createPerson( [], [ 'type-lid' => 'Bondslid' ] );
		update_user_meta( $user, 'rondo_linked_person_id', $person );
		$token = $this->session( $user );
		wp_set_current_user( 0 );
		$result = $this->read( $token, 'pass', 'GET', [ 'person_id' => $person ] );
		$this->assertSame( 200, $result->get_status() );
		$this->assertArrayHasKey( 'token', $result->get_data() );
		$this->assertSame( 'no-store', $result->get_headers()['Cache-Control'] );
		$this->assertSame( 403, $this->read( $token, 'pass', 'GET', [ 'person_id' => $stranger ] )->get_status() );
		$this->assertSame( 400, $this->read( $token, 'pass', 'GET', [ 'person_id' => '../1' ] )->get_status() );
		$this->assertSame(
			400,
			$this->read(
			$token,
			'pass',
			'GET',
			[
				'person_id' => $person,
				'role'      => [],
			]
			)->get_status()
			);
		\Rondo\Fields\Fields::update_for_post( $person, 'former_member', true );
		$this->assertSame( 403, $this->read( $token, 'pass', 'GET', [ 'person_id' => $person ] )->get_status() );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_exchange_rejects_wrong_verifier_and_replay(): void {
		$user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$code = Plugin::issue( $this->params(), $user );
		$this->assertSame( 400, $this->exchange( $code, [ 'code_verifier' => str_repeat( 'b', 43 ) ] )->get_status() );
		$this->assertSame( 400, $this->exchange( $code, [ 'redirect_uri' => 'club.other://oauth/callback' ] )->get_status() );
		$this->assertSame( 400, $this->exchange( $code, [ 'client_id' => 'other' ] )->get_status() );
		$this->assertSame( 200, $this->exchange( $code )->get_status() );
		$this->assertSame( 400, $this->exchange( $code )->get_status() );
	}

	public function test_existing_user_endpoint_runs_as_token_user_and_restores_caller(): void {
		$user   = self::factory()->user->create(
			[
				'role'         => 'subscriber',
				'display_name' => 'Test Member',
			]
			);
		$caller = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$token  = $this->session( $user );
		wp_set_current_user( $caller );
		$response = $this->read( $token );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $user, $response->get_data()['id'] );
		$this->assertFalse( $response->get_data()['is_admin'] );
		$this->assertSame( $caller, get_current_user_id() );
	}

	public function test_household_matches_existing_permissions_and_filters(): void {
		$user     = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$person   = $this->createPerson(
			[],
			[
				'first_name' => 'Eigen',
				'last_name'  => 'Lid',
			]
			);
		$stranger = $this->createPerson(
			[],
			[
				'first_name' => 'Andere',
				'last_name'  => 'Persoon',
			]
			);
		update_user_meta( $user, 'rondo_linked_person_id', $person );
		\Rondo\Core\AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user );
		$direct = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/people/household' ) );
		$token  = $this->session( $user );
		wp_set_current_user( 0 );
		$mobile = $this->read( $token, 'household' );
		$this->assertNotSame( 404, $direct->get_status(), 'The actual household endpoint must be registered.' );
		$this->assertSame( $direct->get_status(), $mobile->get_status() );
		$this->assertSame( $direct->get_data(), $mobile->get_data() );
		$ids = array_map( 'intval', array_column( $mobile->get_data(), 'id' ) );
		$this->assertContains( $person, $ids );
		$this->assertNotContains( $stranger, $ids );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_token_cannot_write_or_select_arbitrary_rest_routes(): void {
		$user  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$token = $this->session( $user );
		$this->assertSame( 400, $this->read( $token, '/wp/v2/users' )->get_status() );
		$this->assertSame( 404, $this->read( $token, 'me', 'POST' )->get_status() );
		wp_set_current_user( 0 );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/user/me' );
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
	}

	public function test_revocation_unknown_and_expired_tokens_fail_closed(): void {
		$user  = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$token = $this->session( $user );
		$this->assertSame( 401, $this->read( str_repeat( 'z', 43 ) )->get_status() );
		$request = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/revoke' );
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( 401, $this->read( $token )->get_status() );
		$token              = $this->session( $user );
		$key                = 'rondo_mobile_session_' . hash( 'sha256', $token );
		$data               = get_transient( $key );
		$data['expires_at'] = time() - 1;
		set_transient( $key, $data, 300 );
		$this->assertSame( 401, $this->read( $token )->get_status() );
	}

	public function test_tokens_and_codes_cannot_cross_club_audiences(): void {
		$user  = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$code  = Plugin::issue( $this->params(), $user );
		$token = $this->session( $user );
		$old   = get_option( 'home' );
		try {
			update_option( 'home', 'https://another-club.example.test' );
			$this->assertSame( 401, $this->read( $token )->get_status() );
			$this->assertSame( 400, $this->exchange( $code )->get_status() );
		} finally {
			update_option( 'home', $old );
		}
	}

	public function test_password_change_or_removed_read_access_invalidates_session(): void {
		$user  = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$token = $this->session( $user );
		wp_set_password( 'test-only-new-password', $user );
		$this->assertSame( 401, $this->read( $token )->get_status() );
		$token = $this->session( $user );
		get_userdata( $user )->set_role( '' );
		$this->assertSame( 401, $this->read( $token )->get_status() );
		$this->assertWPError( Plugin::issue( $this->params(), $user ) );
	}
}
