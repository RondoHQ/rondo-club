<?php

namespace Tests\Wpunit;

use Rondo\MobileSpike\Plugin;
use Rondo\REST\People;
use Rondo\REST\MemberShifts;
use Rondo\REST\MemberProfile;
use Rondo\Fields\Fields;
use Rondo\Users\MemberProfileService;
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
		$this->bootRestControllers( [ UserSettings::class, People::class, MemberShifts::class, MemberProfile::class, MembershipPasses::class, Plugin::class ] );
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

	public function test_magic_email_keeps_only_the_validated_mobile_return_and_provider_token(): void {
		$plugin      = new Plugin();
		$user        = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		$return      = add_query_arg( array_merge( $this->params(), [ 'action' => 'rondo_mobile_spike_authorize' ] ), admin_url( 'admin-post.php' ) );
		$link        = add_query_arg(
			[
				'magic-login' => '1',
				'token'       => 'fixture-token',
				'redirect_to' => rawurlencode( home_url( '/' ) ),
			],
			wp_login_url()
			);
		$old_post    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Save test fixture globals.
		$old_request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Save test fixture globals.
		try {
			$_POST['redirect_to'] = $return;
			$result               = $plugin->magic_login_link( $link, $user, 'email' );
			parse_str( wp_parse_url( $result, PHP_URL_QUERY ), $params );
			$this->assertSame( 'fixture-token', $params['token'] );
			$this->assertSame( $return, $params['redirect_to'] );
			$_REQUEST['redirect_to'] = $return;
			$this->assertSame( $return, $plugin->magic_login_redirect( home_url( '/profile' ), $user ) );
			$this->assertSame( $link, $plugin->magic_login_link( $link, $user, 'sms' ) );
			$this->assertSame( 'https://other.test/login', $plugin->magic_login_link( 'https://other.test/login', $user, 'email' ) );
			foreach ( [ home_url( '/' ), 'https://other.test/', $return . '#fragment', add_query_arg( 'redirect_uri', 'https://other.test/', $return ), [ $return ] ] as $invalid ) {
				$_POST['redirect_to']    = $invalid;
				$_REQUEST['redirect_to'] = $invalid;
				$this->assertSame( $link, $plugin->magic_login_link( $link, $user, 'email' ) );
				$this->assertSame( home_url( '/profile' ), $plugin->magic_login_redirect( home_url( '/profile' ), $user ) );
			}
		} finally {
			$_POST    = $old_post;
			$_REQUEST = $old_request;
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

	private function change_shift( string $token, array $params ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/shift' );
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		return rest_do_request( $request );
	}

	private function mobile_shift( array $assigned = [] ): int {
		$shift = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Mobiele testdienst',
			]
			);
		$start = current_datetime()->modify( '+2 days' );
		foreach ( [
			'start_datetime'   => $start->format( 'Y-m-d H:i:s' ),
			'end_datetime'     => $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
			'status'           => 'open',
			'capacity'         => 2,
			'assigned_persons' => $assigned,
		] as $key => $value ) {
			update_post_meta( $shift, $key, $value );
		}
		return $shift;
	}

	public function test_member_writes_require_explicit_consent_and_ignore_scope_upgrade_requests(): void {
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$pair = $this->exchange( Plugin::issue( $this->params(), $user ) )->get_data();
		$this->assertSame( Plugin::SCOPE, $pair['scope'] );
		$refreshed = $this->refresh_token( $pair['refresh_token'], [ 'scope' => Plugin::MEMBER_SCOPE ] )->get_data();
		$this->assertSame( Plugin::SCOPE, $refreshed['scope'] );
		$response = $this->change_shift(
			$refreshed['access_token'],
			[
				'shift_id' => 1,
				'action'   => 'signup',
			]
			);
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'consent_required', $response->get_data()['code'] );
		$this->assertSame(
			401,
			$this->change_shift(
			'',
			[
				'shift_id' => 1,
				'action'   => 'signup',
			]
			)->get_status()
			);
	}

	public function test_member_signup_cancel_reuses_capacity_deadline_overlap_and_current_person_rules(): void {
		$user   = $this->createRondoUser();
		$person = $this->createPerson();
		$other  = $this->createPerson();
		update_user_meta( $user, 'rondo_linked_person_id', $person );
		$params = array_merge( $this->params(), [ 'scope' => Plugin::MEMBER_SCOPE ] );
		$pair   = $this->exchange( Plugin::issue( $params, $user ) )->get_data();
		$this->assertSame( Plugin::MEMBER_SCOPE, $pair['scope'] );
		$pair = $this->refresh_token( $pair['refresh_token'] )->get_data();
		$this->assertSame( Plugin::MEMBER_SCOPE, $pair['scope'] );
		$token   = $pair['access_token'];
		$shift   = $this->mobile_shift( [ $other ] );
		$guarded = $this->mobile_shift();
		$type    = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
			]
			);
		update_post_meta( $guarded, 'dienst_type_id', $type );
		foreach ( [ 'vog_required', 'iva_required', 'required_pool' ] as $requirement ) {
			update_post_meta( $type, $requirement, $requirement === 'required_pool' ? 999999 : true );
			$denied = $this->change_shift(
				$token,
				[
					'shift_id'      => $guarded,
					'action'        => 'signup',
					'force_overlap' => true,
				]
				);
			$this->assertSame( 403, $denied->get_status() );
			$this->assertSame( $requirement === 'required_pool' ? 'pool_membership_required' : $requirement, $denied->get_data()['code'] );
			$this->assertSame( [], get_post_meta( $guarded, 'assigned_persons', true ) );
			delete_post_meta( $type, $requirement );
		}

		wp_set_current_user( 0 );
		$body = [
			'shift_id' => $shift,
			'action'   => 'signup',
		];
		foreach ( [ [ 'person_id' => $other ], [ 'action' => 'cancellation' ], [ 'force_overlap' => 'true' ], [ 'shift_id' => '../1' ] ] as $extra ) {
			$this->assertSame( 400, $this->change_shift( $token, array_merge( $body, $extra ) )->get_status() );
		}
		update_post_meta( $shift, 'capacity', 1 );
		$this->assertSame( 'shift_full', $this->change_shift( $token, $body )->get_data()['code'] );
		update_post_meta( $shift, 'capacity', 2 );
		$this->assertTrue( $this->change_shift( $token, $body )->get_data()['signed_up'] );
		$this->assertSame( [ $other, $person ], get_post_meta( $shift, 'assigned_persons', true ) );
		$this->assertSame( 'vol', get_post_meta( $shift, 'status', true ) );
		$this->assertTrue( $this->change_shift( $token, $body )->get_data()['already_signed_up'] );
		$overlap      = $this->mobile_shift();
		$overlap_body = [
			'shift_id' => $overlap,
			'action'   => 'signup',
		];
		$this->assertSame( 'overlap_warning', $this->change_shift( $token, $overlap_body )->get_data()['code'] );
		$this->assertTrue( $this->change_shift( $token, $overlap_body + [ 'force_overlap' => true ] )->get_data()['signed_up'] );
		$this->assertTrue(
			$this->change_shift(
			$token,
			[
				'shift_id' => $overlap,
				'action'   => 'cancel',
			]
			)->get_data()['cancelled']
			);
		update_post_meta( $shift, '_shift_signup_at_' . $person, time() - HOUR_IN_SECONDS );
		$cancel = [
			'shift_id' => $shift,
			'action'   => 'cancel',
		];
		$this->assertSame( 'shift_cancel_deadline_passed', $this->change_shift( $token, $cancel )->get_data()['code'] );
		$this->assertSame( [ $other, $person ], get_post_meta( $shift, 'assigned_persons', true ) );
		update_post_meta( $shift, '_shift_signup_at_' . $person, time() );
		$response = $this->change_shift( $token, $cancel );
		$this->assertTrue( $response->get_data()['cancelled'] );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( [ $other ], get_post_meta( $shift, 'assigned_persons', true ) );
		$this->assertSame( 'open', get_post_meta( $shift, 'status', true ) );
		$this->assertSame( 0, get_current_user_id() );
	}

	private function change_profile( string $token, string $action, array $values = [], array $extra = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/profile' );
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
			array_merge(
			[
				'action' => $action,
				'values' => $values,
			],
			$extra
			)
			)
			);
		return rest_do_request( $request );
	}

	public function test_profile_scope_is_explicit_and_old_families_cannot_gain_it_by_refresh(): void {
		$user = $this->createRondoUser();
		foreach ( [ Plugin::SCOPE, Plugin::MEMBER_SCOPE ] as $scope ) {
			$pair = $this->exchange( Plugin::issue( array_merge( $this->params(), [ 'scope' => $scope ] ), $user ) )->get_data();
			$pair = $this->refresh_token( $pair['refresh_token'], [ 'scope' => Plugin::PROFILE_SCOPE ] )->get_data();
			$this->assertSame( $scope, $pair['scope'] );
			$response = $this->change_profile( $pair['access_token'], 'email_cancel' );
			$this->assertSame( 403, $response->get_status() );
			$this->assertSame( 'consent_required', $response->get_data()['code'] );
		}
		$this->assertSame( 401, $this->change_profile( '', 'email_cancel' )->get_status() );
	}

	public function test_profile_phones_are_self_only_validated_and_readonly_when_membership_changes(): void {
		$user  = $this->createRondoUser();
		$self  = $this->createPerson( [], [ 'mobile_2' => '+31622222222' ] );
		$other = $this->createPerson( [], [ 'mobile_1' => '+31633333333' ] );
		update_user_meta( $user, 'rondo_linked_person_id', $self );
		\Rondo\Core\AccessControl::flush_visible_person_ids_cache();
		$pair = $this->exchange( Plugin::issue( array_merge( $this->params(), [ 'scope' => Plugin::PROFILE_SCOPE ] ), $user ) )->get_data();
		$pair = $this->refresh_token( $pair['refresh_token'] )->get_data();
		$this->assertSame( Plugin::PROFILE_SCOPE, $pair['scope'] );
		$token  = $pair['access_token'];
		$values = [
			'mobile_1'    => '06 12345678',
			'mobile_2'    => '+31622222222',
			'telephone_1' => '',
			'telephone_2' => '',
		];
		wp_set_current_user( 0 );
		foreach ( [ [ 'mobile_1' => '+31612345678' ], array_merge( $values, [ 'person_id' => $other ] ), array_merge( $values, [ 'mobile_1' => [] ] ) ] as $invalid ) {
			$this->assertSame( 400, $this->change_profile( $token, 'phones', $invalid )->get_status() );
		}
		$this->assertSame( 400, $this->change_profile( $token, 'phones', $values, [ 'person_id' => $other ] )->get_status() );
		$this->assertSame( 400, $this->change_profile( $token, '../profile-change-log', [] )->get_status() );
		$this->assertSame( 'rondo_invalid_phone', $this->change_profile( $token, 'phones', array_merge( $values, [ 'mobile_1' => '123' ] ) )->get_data()['code'] );
		$result = $this->change_profile( $token, 'phones', $values );
		$this->assertSame( 200, $result->get_status() );
		$this->assertTrue( $result->get_data()['success'] );
		$this->assertSame( [ $self ], $result->get_data()['affected'] );
		$this->assertSame( 'no-store', $result->get_headers()['Cache-Control'] );
		$this->assertSame( '+31612345678', Fields::get_for_post( $self, 'mobile_1' ) );
		$this->assertSame( '+31622222222', Fields::get_for_post( $self, 'mobile_2' ) );
		$this->assertSame( '+31633333333', Fields::get_for_post( $other, 'mobile_1' ) );
		$profile = $this->read( $token, 'profile', 'GET', [ 'person_id' => $other ] )->get_data();
		$this->assertSame( $self, $profile['person']['id'] );
		$this->assertTrue( $profile['can_edit'] );
		$this->assertSame( 0, get_current_user_id() );
		Fields::update_for_post( $self, 'former_member', true );
		$this->assertFalse( $this->read( $token, 'profile' )->get_data()['can_edit'] );
		$this->assertSame( 'rondo_former_member_readonly', $this->change_profile( $token, 'phones', $values )->get_data()['code'] );
		$this->assertSame( 403, $this->change_profile( $token, 'email_cancel' )->get_status() );
		$this->assertSame( '+31612345678', Fields::get_for_post( $self, 'mobile_1' ) );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_profile_address_reuses_household_rules_and_preserves_other_addresses(): void {
		$user  = $this->createRondoUser();
		$work  = [
			'address_label' => 'Work',
			'street_name'   => 'Kantoorstraat',
			'house_number'  => '2',
			'postal_code'   => '1234 AB',
			'city'          => 'Teststad',
			'country'       => 'Nederland',
			'country_code'  => 'NL',
		];
		$self  = $this->createPerson( [], [ 'addresses' => [ $work ] ] );
		$child = $this->createPerson(
			[],
			[
				'birthdate'     => gmdate( 'Y-m-d', strtotime( '-10 years' ) ),
				'relationships' => [
					[
						'related_person'    => $self,
						'relationship_type' => \Rondo\Data\InverseRelationships::TYPE_PARENT,
					],
				],
			]
			);
		$other = $this->createPerson( [], [ 'addresses' => [ $work ] ] );
		Fields::update_for_post(
			$self,
			'relationships',
			[
				[
					'related_person'    => $child,
					'relationship_type' => \Rondo\Data\InverseRelationships::TYPE_CHILD,
				],
			]
			);
		update_user_meta( $user, 'rondo_linked_person_id', $self );
		\Rondo\Core\AccessControl::flush_visible_person_ids_cache();
		$pair    = $this->exchange( Plugin::issue( array_merge( $this->params(), [ 'scope' => Plugin::PROFILE_SCOPE ] ), $user ) )->get_data();
		$address = [
			'street_name'           => 'Proefstraat',
			'house_number'          => '10',
			'house_number_addition' => 'A',
			'postal_code'           => '1234ab',
			'city'                  => 'Teststad',
			'state'                 => 'Gelderland',
			'country'               => 'Nederland',
			'country_code'          => 'NL',
		];
		wp_set_current_user( 0 );
		$this->assertSame( 'rondo_invalid_postal_code', $this->change_profile( $pair['access_token'], 'address', array_merge( $address, [ 'postal_code' => 'bad' ] ) )->get_data()['code'] );
		$result = $this->change_profile( $pair['access_token'], 'address', $address );
		$this->assertSame( 200, $result->get_status() );
		$this->assertEqualsCanonicalizing( [ $self, $child ], $result->get_data()['affected'] );
		$this->assertSame( '1234 AB', Fields::get_for_post( $child, 'addresses' )[0]['postal_code'] );
		$this->assertSame( 'Kantoorstraat', Fields::get_for_post( $self, 'addresses' )[1]['street_name'] );
		$this->assertSame( 'Kantoorstraat', Fields::get_for_post( $other, 'addresses' )[0]['street_name'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_profile_email_stays_pending_until_verified_and_cancelled_links_cannot_apply(): void {
		$user = $this->createRondoUser();
		$self = $this->createPerson( [], [ 'email_1' => 'old@example.test' ] );
		update_user_meta( $user, 'rondo_linked_person_id', $self );
		\Rondo\Core\AccessControl::flush_visible_person_ids_cache();
		$pair       = $this->exchange( Plugin::issue( array_merge( $this->params(), [ 'scope' => Plugin::PROFILE_SCOPE ] ), $user ) )->get_data();
		$token      = $pair['access_token'];
		$mail_token = '';
		$capture    = static function ( $return, $atts ) use ( &$mail_token ) {
			if ( preg_match( '#email-wijzigen/([a-f0-9]{64})#', (string) $atts['message'], $matches ) ) {
				$mail_token = $matches[1];
			}
			return true;
		};
		add_filter( 'pre_wp_mail', $capture, 10, 2 );
		try {
			wp_set_current_user( 0 );
			$this->assertSame(
				200,
				$this->change_profile(
				$token,
				'email_request',
				[
					'slot'  => 'primary',
					'email' => 'new@example.test',
				]
				)->get_status()
				);
			$this->assertSame( 'old@example.test', Fields::get_for_post( $self, 'email_1' ) );
			$pending = $this->read( $token, 'profile' )->get_data()['pending_email'];
			$this->assertSame( 'new@example.test', $pending['email'] );
			$this->assertArrayNotHasKey( 'token_hash', $pending );
			$this->assertSame( 200, $this->change_profile( $token, 'email_cancel' )->get_status() );
			$this->assertNull( $this->read( $token, 'profile' )->get_data()['pending_email'] );
			$this->assertWPError( MemberProfileService::verify_email_token( $mail_token ) );
			$this->assertSame(
				200,
				$this->change_profile(
				$token,
				'email_request',
				[
					'slot'  => 'secondary',
					'email' => 'second@example.test',
				]
				)->get_status()
				);
			$this->assertIsArray( MemberProfileService::verify_email_token( $mail_token ) );
			$this->assertSame( 'second@example.test', $this->read( $token, 'profile' )->get_data()['person']['fields']['email_2'] );
			$this->assertNull( $this->read( $token, 'profile' )->get_data()['pending_email'] );
			$this->assertSame( 200, $this->change_profile( $token, 'email_remove' )->get_status() );
			$this->assertSame( '', Fields::get_for_post( $self, 'email_2' ) );
			$this->assertSame( 'old@example.test', Fields::get_for_post( $self, 'email_1' ) );
			$this->assertSame( 0, get_current_user_id() );
		} finally {
			remove_filter( 'pre_wp_mail', $capture, 10 );
		}
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
