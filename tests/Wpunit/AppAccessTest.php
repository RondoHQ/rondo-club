<?php

namespace Tests\Wpunit;

use Rondo\Data\CredentialEncryption;
use Rondo\REST\AppAccess;
use Rondo\REST\UserSettings;
use Rondo\Security\AppAccess as AppAccessService;
use Tests\Support\RondoTestCase;

class AppAccessTest extends RondoTestCase {
	// Public dummy fixture, never a real account secret.
	private const URI = 'otpauth://totp/Laposta%3Atest-account?secret=JBSWY3DPEHPK3PXP&issuer=Laposta';
	private int $admin_id;
	private int $board_id;

	protected function set_up(): void {
		parent::set_up();
		$this->admin_id = $this->createRondoUser( [ 'role' => 'administrator' ] );
		$this->board_id = $this->createRondoUser( [ 'role' => 'rondo_bestuur' ] );
		$this->bootRestControllers( [ AppAccess::class, UserSettings::class ] );
		wp_set_current_user( $this->admin_id );
	}

	protected function tear_down(): void {
		delete_option( AppAccessService::OPTION );
		parent::tear_down();
	}

	private function request( string $method = 'GET', array $body = [], string $suffix = '' ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rondo/v1/app-access/laposta' . $suffix );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		// serve_request applies this filter after dispatch; internal rest_do_request does not.
		return apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request );
	}

	public function test_admin_saves_encrypted_secret_without_echoing_it(): void {
		$response = $this->request( 'PUT', [ 'uri' => self::URI ] );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'configured' => true,
				'account'    => 'Laposta:test-account',
			],
			$response->get_data()
			);
		$stored = get_option( AppAccessService::OPTION );
		$this->assertTrue( CredentialEncryption::is_encrypted( $stored ) );
		$this->assertStringNotContainsString( 'JBSWY3DPEHPK3PXP', $stored );
		$this->assertArrayNotHasKey( AppAccessService::OPTION, wp_load_alloptions() );
		$this->assertSame( self::URI, CredentialEncryption::decrypt_secret( $stored ) );
		$this->assertStringContainsString( 'no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( $response->get_data(), $this->request()->get_data() );
		$this->assertSame( 200, $this->request( 'PUT', [ 'uri' => self::URI ] )->get_status() );
	}

	public function test_board_can_reveal_but_cannot_change_or_delete(): void {
		$this->request( 'PUT', [ 'uri' => self::URI ] );
		wp_set_current_user( $this->board_id );
		$this->assertSame( 200, $this->request()->get_status() );
		$response = $this->request( 'POST', [], '/reveal' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'uri' => self::URI ], $response->get_data() );
		$this->assertStringContainsString( 'no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( 403, $this->request( 'PUT', [ 'uri' => self::URI ] )->get_status() );
		$this->assertSame( 403, $this->request( 'DELETE' )->get_status() );
		$this->assertTrue( ( new UserSettings() )->get_current_user_data( $this->board_id )['can_access_app_access'] );
	}

	public function test_other_roles_and_anonymous_users_cannot_read_or_write(): void {
		$this->request( 'PUT', [ 'uri' => self::URI ] );
		foreach ( [ 'rondo_user', 'rondo_financieel', 'rondo_ledenadministratie', 'subscriber' ] as $role ) {
			$user_id = $this->createRondoUser( [ 'role' => $role ] );
			wp_set_current_user( $user_id );
			foreach ( [ [ 'GET', '' ], [ 'PUT', '' ], [ 'DELETE', '' ], [ 'POST', '/reveal' ] ] as [ $method, $suffix ] ) {
				$response = $this->request( $method, [ 'uri' => self::URI ], $suffix );
				$this->assertSame( 403, $response->get_status(), $role . ' ' . $method );
				$this->assertStringNotContainsString( 'JBSWY3DPEHPK3PXP', wp_json_encode( $response->get_data() ) );
				$this->assertStringContainsString( 'no-store', $response->get_headers()['Cache-Control'] );
			}
			$this->assertFalse( ( new UserSettings() )->get_current_user_data( $user_id )['can_access_app_access'] );
		}
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'POST', [], '/reveal' )->get_status() );
	}

	public function test_access_is_revoked_when_board_role_is_removed(): void {
		$this->request( 'PUT', [ 'uri' => self::URI ] );
		wp_set_current_user( $this->board_id );
		$this->assertSame( 200, $this->request( 'POST', [], '/reveal' )->get_status() );
		$user = new \WP_User( $this->board_id );
		$user->set_role( 'rondo_user' );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->board_id );
		$this->assertSame( 403, $this->request( 'POST', [], '/reveal' )->get_status() );
	}

	public function test_invalid_uris_do_not_replace_existing_secret_or_leak_input(): void {
		$this->request( 'PUT', [ 'uri' => self::URI ] );
		$invalid = [
			null,
			[],
			'',
			'op://vault/item/otp',
			str_replace( 'totp', 'hotp', self::URI ),
			str_replace( 'otpauth:', 'https:', self::URI ),
			str_replace( 'JBSWY3DPEHPK3PXP', '<secret>', self::URI ),
			self::URI . '&secret=JBSWY3DPEHPK3PXP',
			self::URI . '&digits=5',
			self::URI . '&period=0',
			self::URI . '&algorithm=MD5',
			self::URI . '#fragment',
			self::URI . '&image=https://example.com',
			str_replace( 'issuer=Laposta', 'issuer=Other', self::URI ),
			str_replace( 'totp/', 'user@totp/', self::URI ),
			self::URI . '&secret[]=value',
			str_repeat( 'a', 1025 ),
		];
		foreach ( $invalid as $uri ) {
			$response = $this->request( 'PUT', [ 'uri' => $uri ] );
			$this->assertSame( 400, $response->get_status() );
			$this->assertStringNotContainsString( 'JBSWY3DPEHPK3PXP', wp_json_encode( $response->get_data() ) );
			$this->assertSame( self::URI, AppAccessService::uri() );
		}
	}

	public function test_nondefault_parameters_are_preserved_and_query_secrets_are_rejected(): void {
		$uri = self::URI . '&algorithm=SHA256&digits=8&period=60';
		$this->assertSame( 200, $this->request( 'PUT', [ 'uri' => $uri ] )->get_status() );
		$this->assertSame( $uri, $this->request( 'POST', [], '/reveal' )->get_data()['uri'] );
		$request = new \WP_REST_Request( 'PUT', '/rondo/v1/app-access/laposta' );
		$request->set_query_params( [ 'uri' => self::URI ] );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		$this->assertSame( $uri, AppAccessService::uri() );
	}

	public function test_method_overrides_cannot_reveal_secrets_through_get_caches(): void {
		$this->request( 'PUT', [ 'uri' => self::URI ] );
		foreach ( [ 'query', 'header' ] as $override ) {
			// serve_request has already changed the effective method to POST at dispatch.
			$request = new \WP_REST_Request( 'POST', '/rondo/v1/app-access/laposta/reveal' );
			if ( $override === 'query' ) {
				$request->set_query_params( [ '_method' => 'POST' ] );
			} else {
				$request->set_header( 'X-HTTP-Method-Override', 'POST' );
			}
			$response = rest_do_request( $request );
			$this->assertSame( 405, $response->get_status() );
			$this->assertStringNotContainsString( 'JBSWY3DPEHPK3PXP', wp_json_encode( $response->get_data() ) );
		}
	}

	public function test_missing_removed_plaintext_and_corrupt_settings_fail_closed(): void {
		$this->assertFalse( $this->request()->get_data()['configured'] );
		$this->assertSame( 404, $this->request( 'POST', [], '/reveal' )->get_status() );
		$this->request( 'PUT', [ 'uri' => self::URI ] );
		$this->assertSame( 200, $this->request( 'DELETE' )->get_status() );
		$this->assertSame( 404, $this->request( 'POST', [], '/reveal' )->get_status() );
		foreach ( [ self::URI, 'rondo:v2:invalid' ] as $stored ) {
			update_option( AppAccessService::OPTION, $stored, false );
			$this->assertFalse( $this->request()->get_data()['configured'] );
			$this->assertSame( 404, $this->request( 'POST', [], '/reveal' )->get_status() );
		}
		$this->assertSame( 404, $this->request( 'GET', [], '/reveal' )->get_status() );
	}
}
