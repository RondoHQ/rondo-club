<?php

namespace Tests\Wpunit;

use Rondo\Data\CredentialEncryption;
use Rondo\Identity\OidcAuthorizationService;
use Rondo\Identity\OidcClientRegistry;
use Rondo\Identity\OidcIdentity;
use Rondo\Identity\OidcKeyStore;
use Rondo\Identity\OidcProvider;
use Rondo\REST\Oidc as RestOidc;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

final class OidcProviderTest extends RondoTestCase {

	private const VERIFIER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	protected function setUp(): void {
		parent::setUp();
		delete_option( OidcClientRegistry::OPTION_CLIENTS );
		delete_option( OidcKeyStore::OPTION_KEYS );
	}

	public function test_client_secret_is_returned_once_and_rotation_invalidates_it(): void {
		$created = $this->createClient( 'https://support.example.test/subdir' );

		$this->assertSame( 'https://support.example.test/subdir', $created['freescout_base_url'] );
		$this->assertArrayHasKey( 'client_secret', $created );
		$this->assertArrayNotHasKey( 'client_secret_hash', $created );
		$this->assertArrayNotHasKey( 'client_secret', OidcClientRegistry::all()[0] );
		$this->assertTrue( OidcClientRegistry::verify_secret( OidcClientRegistry::find( $created['client_id'] ), $created['client_secret'] ) );

		$rotated = OidcClientRegistry::rotate_secret( $created['client_id'] );
		$this->assertFalse( OidcClientRegistry::verify_secret( OidcClientRegistry::find( $created['client_id'] ), $created['client_secret'] ) );
		$this->assertTrue( OidcClientRegistry::verify_secret( OidcClientRegistry::find( $created['client_id'] ), $rotated['client_secret'] ) );
	}

	public function test_authorization_code_flow_issues_verifiable_tokens_and_rejects_replay(): void {
		$user_id = $this->createEligibleUser( 'agent@example.test' );
		$this->assertTrue( OidcIdentity::mark_email_verified( $user_id, 'agent@example.test', 'activation' ) );
		$client = $this->createClient();

		$authorization = OidcAuthorizationService::prepare_authorization( $this->authorizationParams( $client ), $user_id );
		$this->assertSame( 'consent', $authorization['status'] );
		$redirect = OidcAuthorizationService::decide( $authorization['pending_token'], $user_id, true );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		$this->assertSame( 'state-value-123456', $query['state'] );

		$tokens = OidcAuthorizationService::exchange_code(
			[
				'grant_type'    => 'authorization_code',
				'code'          => $query['code'],
				'redirect_uri'  => $client['redirect_uris'][0],
				'code_verifier' => self::VERIFIER,
			],
			$this->basicHeader( $client )
		);
		$this->assertIsArray( $tokens );
		$this->assertSame( 'Bearer', $tokens['token_type'] );
		$this->assertSame( OidcAuthorizationService::ACCESS_TOKEN_TTL_SECONDS, $tokens['expires_in'] );

		[ $header, $claims ] = $this->verifyJwt( $tokens['id_token'] );
		$this->assertSame( 'RS256', $header['alg'] );
		$this->assertSame( OidcAuthorizationService::issuer(), $claims['iss'] );
		$this->assertSame( $client['client_id'], $claims['aud'] );
		$this->assertSame( 'nonce-value-123456', $claims['nonce'] );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]{43}$/', $claims['sub'] );
		$this->assertSame( 'agent@example.test', $claims['email'] );
		$this->assertTrue( $claims['email_verified'] );

		$userinfo = OidcAuthorizationService::userinfo( 'Bearer ' . $tokens['access_token'] );
		$this->assertSame( $claims['sub'], $userinfo['sub'] );
		$this->assertSame( 'agent@example.test', $userinfo['email'] );
		$this->assertArrayNotHasKey( 'iss', $userinfo );

		$replay = OidcAuthorizationService::exchange_code(
			[
				'grant_type'    => 'authorization_code',
				'code'          => $query['code'],
				'redirect_uri'  => $client['redirect_uris'][0],
				'code_verifier' => self::VERIFIER,
			],
			$this->basicHeader( $client )
		);
		$this->assertWPError( $replay );
		$this->assertSame( 'invalid_grant', $replay->get_error_data()['oauth_error'] );
	}

	public function test_authorization_rejects_redirect_scope_and_pkce_mismatches(): void {
		$user_id = $this->createEligibleUser( 'guard@example.test' );
		$client  = $this->createClient();

		$params                 = $this->authorizationParams( $client );
		$params['redirect_uri'] = 'https://attacker.example.test/callback';
		$error                  = OidcAuthorizationService::prepare_authorization( $params, $user_id );
		$this->assertWPError( $error );
		$this->assertSame( '', $error->get_error_data()['redirect_uri'] );

		$params          = $this->authorizationParams( $client );
		$params['scope'] = 'openid email offline_access';
		$error           = OidcAuthorizationService::prepare_authorization( $params, $user_id );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_scope', $error->get_error_data()['oauth_error'] );

		$params                          = $this->authorizationParams( $client );
		$params['code_challenge_method'] = 'plain';
		$error                           = OidcAuthorizationService::prepare_authorization( $params, $user_id );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_request', $error->get_error_data()['oauth_error'] );
	}

	public function test_token_exchange_rejects_bad_client_secret_and_verifier(): void {
		$user_id = $this->createEligibleUser( 'exchange@example.test' );
		OidcIdentity::mark_email_verified( $user_id, 'exchange@example.test', 'activation' );
		$client = $this->createClient();
		$code   = $this->authorizeCode( $client, $user_id );
		$input  = [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $client['redirect_uris'][0],
			'code_verifier' => self::VERIFIER,
		];

		$bad_client                  = $client;
		$bad_client['client_secret'] = 'definitely-not-the-secret';
		$rejected                    = OidcAuthorizationService::exchange_code( $input, $this->basicHeader( $bad_client ) );
		$this->assertWPError( $rejected );
		$this->assertSame( 'invalid_client', $rejected->get_error_data()['oauth_error'] );
		$this->assertIsArray( OidcAuthorizationService::exchange_code( $input, $this->basicHeader( $client ) ) );

		$input['code']          = $this->authorizeCode( $client, $user_id );
		$input['code_verifier'] = str_repeat( 'b', 43 );
		$rejected               = OidcAuthorizationService::exchange_code( $input, $this->basicHeader( $client ) );
		$this->assertWPError( $rejected );
		$this->assertSame( 'invalid_grant', $rejected->get_error_data()['oauth_error'] );
		$input['code_verifier'] = self::VERIFIER;
		$this->assertWPError( OidcAuthorizationService::exchange_code( $input, $this->basicHeader( $client ) ) );
	}

	public function test_user_without_a_mapped_capability_is_denied(): void {
		$user_id = $this->createEligibleUser( 'no-access@example.test' );
		get_userdata( $user_id )->set_role( 'subscriber' );
		OidcIdentity::mark_email_verified( $user_id, 'no-access@example.test', 'activation' );
		$error = OidcAuthorizationService::prepare_authorization( $this->authorizationParams( $this->createClient() ), $user_id );

		$this->assertWPError( $error );
		$this->assertSame( 'access_denied', $error->get_error_data()['oauth_error'] );
	}

	public function test_finance_user_is_eligible_but_read_only_finance_user_is_denied(): void {
		$finance_id   = $this->createEligibleUser( 'finance@example.test' );
		$finance_user = get_userdata( $finance_id );
		$finance_user->set_role( 'subscriber' );
		$finance_user->add_cap( 'financieel' );

		$identity = OidcIdentity::resolve( $finance_id, false );

		$this->assertIsArray( $identity );
		$this->assertSame( $finance_id, $identity['user_id'] );

		$read_id   = $this->createEligibleUser( 'finance-read@example.test' );
		$read_user = get_userdata( $read_id );
		$read_user->set_role( 'subscriber' );
		$read_user->add_cap( 'financieel_read' );

		$this->assertWPError( OidcIdentity::resolve( $read_id, false ) );
	}

	public function test_dedicated_email_link_proves_exact_address_once_without_oauth_parameters(): void {
		$user_id = $this->createEligibleUser( 'verify@example.test' );
		$client  = $this->createClient();
		$context = OidcAuthorizationService::prepare_authorization( $this->authorizationParams( $client ), $user_id );
		$this->assertSame( 'verification_required', $context['status'] );

		$mail = null;
		add_filter(
			'pre_wp_mail',
			static function ( $return, array $attributes ) use ( &$mail ) {
				unset( $return );
				$mail = $attributes;
				return true;
			},
			10,
			2
		);
		$result = OidcAuthorizationService::send_verification( $context['pending_token'], $user_id, '192.0.2.10' );
		$this->assertTrue( $result['sent'] );
		$this->assertIsArray( $mail );
		$this->assertStringNotContainsString( 'client_id=', $mail['message'] );
		$this->assertStringNotContainsString( 'state=', $mail['message'] );
		$this->assertMatchesRegularExpression( '#/oauth/verify-email/([A-Za-z0-9_-]{43})#', $mail['message'] );
		preg_match( '#/oauth/verify-email/([A-Za-z0-9_-]{43})#', $mail['message'], $matches );

		$wrong_user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->assertWPError( OidcAuthorizationService::consume_verification( $matches[1], $wrong_user ) );
		$consent = OidcAuthorizationService::consume_verification( $matches[1], $user_id );
		$this->assertIsArray( $consent );
		$this->assertSame( 'verify@example.test', get_user_meta( $user_id, OidcIdentity::META_VERIFIED_EMAIL, true ) );
		$this->assertSame( 'oidc_email', get_user_meta( $user_id, OidcIdentity::META_VERIFIED_METHOD, true ) );
		$this->assertWPError( OidcAuthorizationService::consume_verification( $matches[1], $user_id ) );
	}

	public function test_changed_or_shared_email_is_not_eligible(): void {
		$user_id = $this->createEligibleUser( 'shared@example.test' );
		$this->assertTrue( OidcIdentity::mark_email_verified( $user_id, 'shared@example.test', 'activation' ) );
		update_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, 'changed@example.test' );
		\Rondo\Fields\Fields::update_for_post( (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ), 'email_1', 'changed@example.test' );
		$this->assertWPError( OidcIdentity::resolve( $user_id, true ) );

		$other_id = $this->createEligibleUser( 'changed@example.test' );
		$this->assertWPError( OidcIdentity::resolve( $user_id, false ) );
		$this->assertWPError( OidcIdentity::resolve( $other_id, false ) );
	}

	public function test_successful_magic_login_marks_only_the_current_external_email(): void {
		$user_id = $this->createEligibleUser( 'magic@example.test' );
		$user    = get_userdata( $user_id );

		OidcIdentity::record_magic_login( $user, 'ignored-secret-token' );

		$this->assertSame( 'magic@example.test', get_user_meta( $user_id, OidcIdentity::META_VERIFIED_EMAIL, true ) );
		$this->assertSame( 'magic_login', get_user_meta( $user_id, OidcIdentity::META_VERIFIED_METHOD, true ) );
	}

	public function test_key_rotation_keeps_old_public_key_during_overlap(): void {
		$first = OidcKeyStore::jwks();
		$this->assertCount( 1, $first['keys'] );
		$status = OidcKeyStore::rotate();
		$keys   = OidcKeyStore::jwks();

		$this->assertCount( 2, $keys['keys'] );
		$this->assertNotSame( $first['keys'][0]['kid'], $status['kid'] );
		$this->assertContains( $first['keys'][0]['kid'], array_column( $keys['keys'], 'kid' ) );
	}

	public function test_metadata_uses_a_path_issuer_without_duplicating_oauth_in_endpoints(): void {
		$metadata = OidcAuthorizationService::metadata();

		$this->assertSame( untrailingslashit( home_url( '/oauth' ) ), $metadata['issuer'] );
		$this->assertSame( untrailingslashit( home_url( '/oauth/authorize' ) ), $metadata['authorization_endpoint'] );
		$this->assertSame( untrailingslashit( home_url( '/oauth/token' ) ), $metadata['token_endpoint'] );
		$this->assertSame( untrailingslashit( home_url( '/oauth/userinfo' ) ), $metadata['userinfo_endpoint'] );
		$this->assertSame( untrailingslashit( home_url( '/oauth/jwks' ) ), $metadata['jwks_uri'] );

		global $wp_rewrite;
		$provider = new OidcProvider();
		$provider->register_rewrite_rules();
		$this->assertSame(
			'index.php?rondo_oidc_endpoint=discovery',
			$wp_rewrite->extra_rules_top['^oauth/\.well-known/openid-configuration/?$']
		);
	}

	public function test_admin_rest_api_never_lists_secret_hashes(): void {
		$server  = $this->bootRestControllers( [ RestOidc::class ] );
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$created = $this->createClient();
		wp_set_current_user( $admin );

		$response = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/oidc/clients' ) );
		$this->assertSame( 200, $response->get_status() );
		$client = $response->get_data()['clients'][0];
		$this->assertSame( $created['client_id'], $client['client_id'] );
		$this->assertArrayNotHasKey( 'client_secret', $client );
		$this->assertArrayNotHasKey( 'client_secret_hash', $client );

		wp_set_current_user( 0 );
		$denied = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/oidc/clients' ) );
		$this->assertSame( 401, $denied->get_status() );
	}

	private function createEligibleUser( string $email ): int {
		$user_id   = self::factory()->user->create(
			[
				'role'         => 'rondo_ledenadministratie',
				'user_email'   => $email,
				'display_name' => 'Rondo Agent',
				'first_name'   => 'Rondo',
				'last_name'    => 'Agent',
			]
		);
		$person_id = $this->createPerson( [ 'post_title' => 'Rondo Agent' ], [ 'email_1' => $email ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		update_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, $email );

		return $user_id;
	}

	private function createClient( string $base_url = 'https://support.example.test' ): array {
		$client = OidcClientRegistry::create(
			[
				'label'              => 'FreeScout',
				'redirect_uris'      => [ $base_url . '/rondo-login/callback' ],
				'freescout_base_url' => $base_url,
			]
		);
		$this->assertIsArray( $client );

		return $client;
	}

	private function authorizationParams( array $client ): array {
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', self::VERIFIER, true ) ), '+/', '-_' ), '=' );

		return [
			'client_id'             => $client['client_id'],
			'redirect_uri'          => $client['redirect_uris'][0],
			'response_type'         => 'code',
			'scope'                 => 'openid email profile',
			'state'                 => 'state-value-123456',
			'nonce'                 => 'nonce-value-123456',
			'code_challenge'        => $challenge,
			'code_challenge_method' => 'S256',
		];
	}

	private function basicHeader( array $client ): string {
		return 'Basic ' . base64_encode( rawurlencode( $client['client_id'] ) . ':' . rawurlencode( $client['client_secret'] ) );
	}

	private function authorizeCode( array $client, int $user_id ): string {
		$authorization = OidcAuthorizationService::prepare_authorization( $this->authorizationParams( $client ), $user_id );
		$redirect      = OidcAuthorizationService::decide( $authorization['pending_token'], $user_id, true );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );

		return $query['code'];
	}

	private function verifyJwt( string $jwt ): array {
		$segments = explode( '.', $jwt );
		$this->assertCount( 3, $segments );
		$header = json_decode( $this->base64urlDecode( $segments[0] ), true );
		$claims = json_decode( $this->base64urlDecode( $segments[1] ), true );
		$store  = get_option( OidcKeyStore::OPTION_KEYS );
		$secret = CredentialEncryption::decrypt( $store['current']['private_key'] );
		$key    = openssl_pkey_get_private( $secret['pem'] );
		$public = openssl_pkey_get_details( $key )['key'];

		$this->assertSame( 1, openssl_verify( $segments[0] . '.' . $segments[1], $this->base64urlDecode( $segments[2] ), $public, OPENSSL_ALGO_SHA256 ) );

		return [ $header, $claims ];
	}

	private function base64urlDecode( string $value ): string {
		$padding = strlen( $value ) % 4;
		if ( $padding > 0 ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		return (string) base64_decode( strtr( $value, '-_', '+/' ), true );
	}
}
