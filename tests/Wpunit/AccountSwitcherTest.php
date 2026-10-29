<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Users\AccountSwitcher;
use Rondo\Users\ActivationService;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/** Mailbox possession authorizes an exact address across authenticated sessions. */
class AccountSwitcherTest extends RondoTestCase {

	private $old_cookie;
	private string $issued_cookie = '';

	protected function set_up(): void {
		parent::set_up();
		$this->old_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
		add_filter( 'pre_wp_mail', '__return_true' );
		add_filter( 'send_auth_cookies', '__return_false' );
		add_action(
			'set_logged_in_cookie',
			function ( $cookie ) {
				$this->issued_cookie = $cookie;
			}
			);
	}

	protected function tear_down(): void {
		if ( $this->old_cookie === null ) {
			unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		} else {
			$_COOKIE[ LOGGED_IN_COOKIE ] = $this->old_cookie;
		}
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function person( string $name, string $email, bool $account = true ): array {
		$person_id = $this->createPerson(
			[ 'post_title' => $name ],
			[
				'first_name' => $name,
				'email_1'    => $email,
			]
			);
		$user_id   = 0;
		if ( $account ) {
			$result = ( new UserProvisioning() )->provision( $person_id, false );
			$this->assertIsArray( $result );
			$user_id = (int) $result['user_id'];
		}
		return [ $person_id, $user_id ];
	}

	private function login( int $user_id, string $verified_email = '' ): string {
		$manager = \WP_Session_Tokens::get_instance( $user_id );
		$token   = $manager->create( time() + HOUR_IN_SECONDS );
		wp_set_current_user( $user_id );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, time() + HOUR_IN_SECONDS, 'logged_in', $token );
		if ( $verified_email !== '' ) {
			$raw = wp_generate_password( 40, false );
			AccountSwitcher::bind_login_link(
				add_query_arg(
				[
					'user_id' => $user_id,
					'token'   => $raw,
				],
				wp_login_url()
				),
				$user_id,
				$verified_email
				);
			do_action( 'set_logged_in_cookie', $_COOKIE[ LOGGED_IN_COOKIE ], 0, time() + HOUR_IN_SECONDS, $user_id, 'logged_in', $token );
			do_action( 'magic_login_logged_in', get_userdata( $user_id ), [ 'token' => hash_hmac( 'sha256', $raw, wp_salt() ) ] );
		}
		return $token;
	}

	public function test_password_session_cannot_read_names_switch_or_activate(): void {
		[ , $user ]   = $this->person( 'Adult', 'shared@example.com' );
		[ $other ]    = $this->person( 'Unrelated', 'shared@example.com' );
		[ $inactive ] = $this->person( 'New', 'shared@example.com', false );
		$this->login( $user );
		update_user_meta( $user, 'rondo_oidc_verified_email', 'shared@example.com' );
		$this->assertTrue( AccountSwitcher::status()['available'] );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
		$this->assertSame( [], AccountSwitcher::status()['accounts'] );
		$this->assertWPError( AccountSwitcher::switch_account( $other ) );
		$this->assertWPError( AccountSwitcher::activation_url( $inactive ) );
	}

	public function test_activation_confirmation_survives_logout_and_password_login(): void {
		[ $person ] = $this->person( 'First', 'shared@example.com', false );
		$this->person( 'Second', 'shared@example.com' );
		$result = ActivationService::activate( ActivationService::create_token( 'shared@example.com' ), $person );
		$this->assertIsString( $result );
		$user = (int) get_post_meta( $person, UserProvisioning::META_USER_ID, true );
		$this->login( $user );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		wp_destroy_current_session();
		$this->login( $user );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		$this->assertCount( 2, AccountSwitcher::status()['accounts'] );
	}

	public function test_recorded_activation_is_reused_without_another_email(): void {
		[ , $user ] = $this->person( 'First', 'shared@example.com' );
		$this->person( 'Second', 'shared@example.com' );
		update_user_meta( $user, 'rondo_oidc_verified_email', 'shared@example.com' );
		update_user_meta( $user, 'rondo_oidc_verified_email_method', 'activation' );
		update_user_meta( $user, 'rondo_oidc_verified_email_at', gmdate( DATE_ATOM ) );
		$this->login( $user );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		$this->assertCount( 2, AccountSwitcher::status()['accounts'] );
	}

	public function test_existing_browser_confirmation_is_saved_for_future_logins(): void {
		[ , $user ]                    = $this->person( 'First', 'shared@example.com' );
		$token                         = $this->login( $user );
		$manager                       = \WP_Session_Tokens::get_instance( $user );
		$session                       = $manager->get( $token );
		$session['rondo_switch_email'] = 'shared@example.com';
		$manager->update( $token, $session );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		wp_destroy_current_session();
		$token = $this->login( $user );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		$session               = $manager->get( $token );
		$session['expiration'] = time() - 1;
		$manager->update( $token, $session );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
	}

	public function test_inferred_legacy_magic_login_address_is_not_imported(): void {
		[ , $user ] = $this->person( 'First', 'shared@example.com' );
		update_user_meta( $user, 'rondo_oidc_verified_email', 'shared@example.com' );
		update_user_meta( $user, 'rondo_oidc_verified_email_method', 'magic_login' );
		update_user_meta( $user, 'rondo_oidc_verified_email_at', gmdate( DATE_ATOM ) );
		$this->login( $user );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
	}

	public function test_address_change_and_restore_revokes_all_sessions_and_legacy_confirmation(): void {
		[ $person, $user ] = $this->person( 'First', 'shared@example.com' );
		update_user_meta( $user, 'rondo_oidc_verified_email', 'shared@example.com' );
		update_user_meta( $user, 'rondo_oidc_verified_email_method', 'activation' );
		update_user_meta( $user, 'rondo_oidc_verified_email_at', gmdate( DATE_ATOM ) );
		$this->login( $user, 'shared@example.com' );
		$first_cookie = $_COOKIE[ LOGGED_IN_COOKIE ];
		$this->login( $user, 'shared@example.com' );
		Fields::update_for_post( $person, 'email_1', 'changed@example.com' );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
		Fields::update_for_post( $person, 'email_1', 'shared@example.com' );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
		$_COOKIE[ LOGGED_IN_COOKIE ] = $first_cookie;
		$this->assertFalse( AccountSwitcher::status()['verified'] );
		$this->login( $user );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
		$this->login( $user, 'shared@example.com' );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
	}

	public function test_deleting_address_or_changing_person_link_revokes_confirmation(): void {
		[ $person, $user ] = $this->person( 'First', 'shared@example.com' );
		$this->login( $user, 'shared@example.com' );
		delete_post_meta( $person, 'email_1' );
		Fields::update_for_post( $person, 'email_1', 'shared@example.com' );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
		$this->login( $user, 'shared@example.com' );
		update_user_meta( $user, 'rondo_linked_person_id', 999999 );
		update_user_meta( $user, 'rondo_linked_person_id', $person );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
	}

	public function test_unchanged_verified_address_survives_other_contact_changes(): void {
		[ $person, $user ] = $this->person( 'First', 'shared@example.com' );
		$this->login( $user, 'shared@example.com' );
		Fields::update_for_post( $person, 'email_2', 'other@example.com' );
		$this->login( $user );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		$this->assertSame( 'shared@example.com', AccountSwitcher::status()['email'] );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
	}

	public function test_verified_mailbox_switches_unrelated_accounts_with_target_permissions_and_same_expiry(): void {
		[ , $first ]                = $this->person( 'First', 'shared@example.com' );
		[ $second_person, $second ] = $this->person( 'Second', 'shared@example.com' );
		get_userdata( $first )->add_cap( 'manage_options' );
		$token  = $this->login( $first, 'shared@example.com' );
		$expiry = \WP_Session_Tokens::get_instance( $first )->get( $token )['expiration'];
		$nonce  = wp_create_nonce( 'wp_rest' );
		$this->assertCount( 2, AccountSwitcher::status()['accounts'] );
		$this->assertTrue( current_user_can( 'manage_options' ) );
		$result = AccountSwitcher::switch_account( $second_person );
		$this->assertIsArray( $result );
		$this->assertSame( $second, get_current_user_id() );
		$this->assertFalse( current_user_can( 'manage_options' ) );
		$this->assertFalse( \WP_Session_Tokens::get_instance( $first )->verify( $token ) );
		$_COOKIE[ LOGGED_IN_COOKIE ] = $this->issued_cookie;
		$this->assertFalse( wp_verify_nonce( $nonce, 'wp_rest' ) );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		$this->assertSame( $expiry, \WP_Session_Tokens::get_instance( $second )->get( wp_get_session_token() )['expiration'] );
	}

	public function test_proof_survives_another_session_but_does_not_grant_another_mailbox(): void {
		[ $first_person, $first ]   = $this->person( 'First', 'shared@example.com' );
		[ $second_person, $second ] = $this->person( 'Second', 'shared@example.com' );
		[ $third_person ]           = $this->person( 'Third', 'other@example.com' );
		Fields::update_for_post( $second_person, 'email_2', 'other@example.com' );
		$this->login( $first, 'shared@example.com' );
		$this->assertIsArray( AccountSwitcher::switch_account( $second_person ) );
		$_COOKIE[ LOGGED_IN_COOKIE ] = $this->issued_cookie;
		$this->assertWPError( AccountSwitcher::switch_account( $third_person ) );
		$this->login( $second );
		$this->assertWPError( AccountSwitcher::switch_account( $third_person ) );
		$this->assertIsArray( AccountSwitcher::switch_account( $first_person ) );
	}

	public function test_secondary_mailbox_is_proved_exactly_and_does_not_grant_primary_mailbox(): void {
		[ $first_person, $first ] = $this->person( 'First', 'primary@example.com' );
		Fields::update_for_post( $first_person, 'email_2', 'secondary@example.com' );
		[ $second ] = $this->person( 'Secondary', 'secondary@example.com' );
		[ $third ]  = $this->person( 'Primary', 'primary@example.com' );
		$this->login( $first, 'secondary@example.com' );
		$this->assertSame( 'secondary@example.com', AccountSwitcher::status()['email'] );
		$this->assertWPError( AccountSwitcher::switch_account( $third ) );
		$this->assertIsArray( AccountSwitcher::switch_account( $second ) );
	}

	public function test_source_or_target_address_removal_revokes_switching(): void {
		[ $first_person, $first ] = $this->person( 'First', 'shared@example.com' );
		[ $second ]               = $this->person( 'Second', 'shared@example.com' );
		$this->login( $first, 'shared@example.com' );
		Fields::update_for_post( $second, 'email_1', 'changed@example.com' );
		$this->assertWPError( AccountSwitcher::switch_account( $second ) );
		Fields::update_for_post( $second, 'email_1', 'shared@example.com' );
		Fields::update_for_post( $first_person, 'email_1', 'changed@example.com' );
		$this->assertWPError( AccountSwitcher::switch_account( $second ) );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
	}

	public function test_invalid_links_and_former_members_cannot_be_opened(): void {
		[ , $first ]       = $this->person( 'First', 'shared@example.com' );
		[ $second, $user ] = $this->person( 'Second', 'shared@example.com' );
		$this->login( $first, 'shared@example.com' );
		update_user_meta( $user, 'rondo_linked_person_id', 999999 );
		$this->assertWPError( AccountSwitcher::switch_account( $second ) );
		update_user_meta( $user, 'rondo_linked_person_id', $second );
		Fields::update_for_post( $second, 'former_member', true );
		$this->assertWPError( AccountSwitcher::switch_account( $second ) );
	}

	public function test_plugin_login_restrictions_are_preserved(): void {
		[ , $first ] = $this->person( 'First', 'shared@example.com' );
		[ $second ]  = $this->person( 'Second', 'shared@example.com' );
		$this->login( $first, 'shared@example.com' );
		add_filter( 'magic_login_pre_send_login_link', static fn() => new \WP_Error( 'blocked' ) );
		$this->assertWPError( AccountSwitcher::switch_account( $second ) );
		$this->assertSame( $first, get_current_user_id() );
	}

	public function test_activation_uses_existing_picker_and_rechecks_membership(): void {
		[ , $first ] = $this->person( 'First', 'shared@example.com' );
		[ $new ]     = $this->person( 'New', 'shared@example.com', false );
		[ $outside ] = $this->person( 'Outside', 'outside@example.com', false );
		$this->login( $first, 'shared@example.com' );
		$result = AccountSwitcher::activation_url( $new );
		$this->assertIsArray( $result );
		$token = basename( $result['redirect_url'] );
		$this->assertSame( 'shared@example.com', ActivationService::email_for_token( $token ) );
		$this->assertWPError( AccountSwitcher::activation_url( $outside ) );
	}

	public function test_expired_session_and_unknown_magic_token_cannot_authorize_switching(): void {
		[ , $first ] = $this->person( 'First', 'shared@example.com' );
		[ $second ]  = $this->person( 'Second', 'shared@example.com' );
		$token       = $this->login( $first );
		do_action( 'magic_login_logged_in', get_userdata( $first ), [ 'token' => str_repeat( 'a', 64 ) ] );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
		$manager               = \WP_Session_Tokens::get_instance( $first );
		$session               = $manager->get( $token );
		$session['expiration'] = time() - 1;
		$manager->update( $token, $session );
		$this->assertWPError( AccountSwitcher::switch_account( $second ) );
	}

	public function test_routes_reject_logged_out_requests_and_validate_inputs(): void {
		$server = $this->bootRestControllers( [ \Rondo\REST\AccountSwitcher::class ] );
		wp_set_current_user( 0 );
		foreach ( [
			''          => 'GET',
			'/verify'   => 'POST',
			'/switch'   => 'POST',
			'/activate' => 'POST',
		] as $suffix => $method ) {
			$request = new \WP_REST_Request( $method, '/rondo/v1/user/account-switcher' . $suffix );
			$request->set_param( 'email', 'shared@example.com' );
			$request->set_param( 'person_id', 1 );
			$this->assertSame( 401, $server->dispatch( $request )->get_status() );
		}
		[ , $user ] = $this->person( 'First', 'shared@example.com' );
		$this->login( $user );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/user/account-switcher/switch' );
		$request->set_param( 'person_id', 0 );
		$this->assertSame( 400, $server->dispatch( $request )->get_status() );
		$this->assertWPError( AccountSwitcher::request_verification( 'attacker@example.com' ) );
	}

	public function test_verification_email_binds_generated_link_and_rate_limits_requests(): void {
		[ , $user ] = $this->person( 'First', 'shared@example.com' );
		$this->login( $user );
		add_filter(
			'rondo_activation_magic_login_url',
			static fn( $url, $target ) => add_query_arg(
			[
				'user_id' => $target->ID,
				'token'   => 'test-mail-token',
			],
			wp_login_url()
			),
			10,
			2
			);
		$this->assertSame( [ 'sent' => true ], AccountSwitcher::request_verification( 'shared@example.com' ) );
		$token = wp_get_session_token();
		do_action( 'set_logged_in_cookie', $_COOKIE[ LOGGED_IN_COOKIE ], 0, time() + HOUR_IN_SECONDS, $user, 'logged_in', $token );
		do_action( 'magic_login_logged_in', get_userdata( $user ), [ 'token' => hash_hmac( 'sha256', 'test-mail-token', wp_salt() ) ] );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		for ( $i = 0; $i < 20; $i++ ) {
			ActivationService::record_attempt( 'shared@example.com', ActivationService::client_ip() );
		}
		$this->assertSame( 'rondo_switch_rate_limit', AccountSwitcher::request_verification( 'shared@example.com' )->get_error_code() );
	}

	public function test_a_consumed_login_proof_cannot_be_replayed_in_a_new_session(): void {
		[ , $user ] = $this->person( 'First', 'shared@example.com' );
		$this->login( $user );
		$raw = 'one-use-mailbox-proof';
		AccountSwitcher::bind_login_link(
			add_query_arg(
			[
				'user_id' => $user,
				'token'   => $raw,
			],
			wp_login_url()
			),
			$user,
			'shared@example.com'
			);
		$token_data = [ 'token' => hash_hmac( 'sha256', $raw, wp_salt() ) ];
		do_action( 'set_logged_in_cookie', $_COOKIE[ LOGGED_IN_COOKIE ], 0, time() + HOUR_IN_SECONDS, $user, 'logged_in', wp_get_session_token() );
		do_action( 'magic_login_logged_in', get_userdata( $user ), $token_data );
		$this->assertTrue( AccountSwitcher::status()['verified'] );
		$this->assertFalse( get_transient( 'rondo_switch_link_' . $token_data['token'] ) );
		// Revoke the address and restore it: the old, consumed link cannot reauthorize it.
		$person = (int) get_user_meta( $user, 'rondo_linked_person_id', true );
		Fields::update_for_post( $person, 'email_1', 'changed@example.com' );
		Fields::update_for_post( $person, 'email_1', 'shared@example.com' );
		$this->login( $user );
		do_action( 'set_logged_in_cookie', $_COOKIE[ LOGGED_IN_COOKIE ], 0, time() + HOUR_IN_SECONDS, $user, 'logged_in', wp_get_session_token() );
		do_action( 'magic_login_logged_in', get_userdata( $user ), $token_data );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
	}

	public function test_a_link_sent_before_an_address_change_does_not_prove_the_new_address(): void {
		[ $person, $user ] = $this->person( 'First', 'old@example.com' );
		$this->login( $user );
		$raw = 'old-address-proof';
		AccountSwitcher::bind_login_link(
			add_query_arg(
			[
				'user_id' => $user,
				'token'   => $raw,
			],
			wp_login_url()
			),
			$user,
			'old@example.com'
			);
		Fields::update_for_post( $person, 'email_1', 'new@example.com' );
		do_action( 'set_logged_in_cookie', $_COOKIE[ LOGGED_IN_COOKIE ], 0, time() + HOUR_IN_SECONDS, $user, 'logged_in', wp_get_session_token() );
		do_action( 'magic_login_logged_in', get_userdata( $user ), [ 'token' => hash_hmac( 'sha256', $raw, wp_salt() ) ] );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
	}

	public function test_all_people_on_a_mailbox_are_listed_even_above_twenty(): void {
		[ , $user ] = $this->person( 'First', 'shared@example.com' );
		for ( $i = 0; $i < 21; $i++ ) {
			$this->person( 'Person ' . $i, 'shared@example.com', false );
		}
		$this->login( $user, 'shared@example.com' );
		$this->assertCount( 22, AccountSwitcher::status()['accounts'] );
	}

	public function test_login_identifier_is_only_a_hint_and_mail_failure_is_reported(): void {
		[ $person, $user ] = $this->person( 'First', 'primary@example.com' );
		Fields::update_for_post( $person, 'email_2', 'secondary@example.com' );
		$this->person( 'Primary', 'primary@example.com', false );
		$this->person( 'Secondary', 'secondary@example.com', false );
		$identifier = 'secondary@example.com';
		$password   = 'unused';
		do_action_ref_array( 'wp_authenticate', [ &$identifier, &$password ] );
		$this->login( $user );
		$this->assertSame( 'secondary@example.com', AccountSwitcher::status()['email'] );
		$this->assertFalse( AccountSwitcher::status()['verified'] );
		add_filter(
			'rondo_activation_magic_login_url',
			static fn( $url, $target ) => add_query_arg(
			[
				'user_id' => $target->ID,
				'token'   => 'mail-failure',
			],
			wp_login_url()
			),
			10,
			2
			);
		add_filter( 'pre_wp_mail', '__return_false', 99 );
		$this->assertSame( 'rondo_switch_mail_failed', AccountSwitcher::request_verification( 'secondary@example.com' )->get_error_code() );
	}
}
