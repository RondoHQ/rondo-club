<?php

namespace Tests\Wpunit;

use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/**
 * Tests for LoginResolver — signing in with a KNVB-ID or a real contact address.
 *
 * The dangerous case is the shared family mailbox: two members carry the same
 * `rondo_contact_email`, and the resolver must refuse to guess which one is logging in.
 */
class LoginResolverTest extends RondoTestCase {

	private const PASSWORD = 'correct-horse-battery-staple';

	/**
	 * A user with a generated username, a KNVB-ID and a contact address.
	 */
	private function member( string $login, string $wp_email, ?string $knvb_id, ?string $contact ): int {
		$user_id = self::factory()->user->create(
			[
				'user_login' => $login,
				'user_email' => $wp_email,
				'user_pass'  => self::PASSWORD,
				'role'       => 'rondo_user',
			]
		);
		if ( $knvb_id !== null ) {
			update_user_meta( $user_id, UserProvisioning::META_KNVB_ID, $knvb_id );
		}
		if ( $contact !== null ) {
			update_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, $contact );
		}
		return $user_id;
	}

	/**
	 * Drive the real login surface. The `wp_authenticate` action fires inside
	 * wp_signon(), which is what wp-login.php calls — not inside wp_authenticate().
	 * Suppress the auth cookie so nothing tries to write headers.
	 */
	private function login( string $identifier, string $password = self::PASSWORD ) {
		add_filter( 'send_auth_cookies', '__return_false' );
		$user = wp_signon(
			[
				'user_login'    => $identifier,
				'user_password' => $password,
			]
		);
		remove_filter( 'send_auth_cookies', '__return_false' );

		return $user;
	}

	public function test_a_username_still_works(): void {
		$user_id = $this->member( 'anne.jansen', 'anne@example.com', 'KNVB123', 'anne@example.com' );

		$user = $this->login( 'anne.jansen' );

		$this->assertNotWPError( $user );
		$this->assertSame( $user_id, $user->ID );
	}

	public function test_an_email_still_works(): void {
		$user_id = $this->member( 'anne.jansen', 'anne@example.com', 'KNVB123', 'anne@example.com' );

		$user = $this->login( 'anne@example.com' );

		$this->assertNotWPError( $user );
		$this->assertSame( $user_id, $user->ID );
	}

	public function test_a_member_can_sign_in_with_their_knvb_id(): void {
		$user_id = $this->member( 'anne.jansen', 'anne@example.com', 'KNVB123', 'anne@example.com' );

		$user = $this->login( 'KNVB123' );

		$this->assertNotWPError( $user );
		$this->assertSame( $user_id, $user->ID );
	}

	/**
	 * The household member whose WordPress address is an undeliverable placeholder:
	 * the KNVB-ID is the only identifier that reaches their account.
	 *
	 * Keep literal email addresses out of docblocks in this suite: Codeception reads
	 * anything after an at-sign as an annotation, and an example.com address silently
	 * registers a bogus data provider that stops the whole file from loading.
	 */
	public function test_a_household_member_signs_in_with_their_knvb_id(): void {
		$this->member( 'anne.jansen', 'gezin@example.com', 'KNVB111', 'gezin@example.com' );
		$bram = $this->member( 'bram.jansen', 'person-42@members.rondo.invalid', 'KNVB222', 'gezin@example.com' );

		$user = $this->login( 'KNVB222' );

		$this->assertNotWPError( $user );
		$this->assertSame( $bram, $user->ID );
	}

	public function test_a_unique_contact_address_resolves(): void {
		// user_email differs from the contact address, so core cannot resolve it alone.
		$user_id = $this->member( 'cato.jansen', 'person-7@members.rondo.invalid', null, 'cato@example.com' );

		$user = $this->login( 'cato@example.com' );

		$this->assertNotWPError( $user );
		$this->assertSame( $user_id, $user->ID );
	}

	/**
	 * Two members share the family address. Anne owns it as her WordPress address, so
	 * core resolves it to Anne — it must never resolve to Bram, and the resolver must
	 * not pick one of them arbitrarily.
	 */
	public function test_a_shared_mailbox_never_resolves_to_the_wrong_member(): void {
		$anne = $this->member( 'anne.jansen', 'gezin@example.com', 'KNVB111', 'gezin@example.com' );
		$bram = $this->member( 'bram.jansen', 'person-42@members.rondo.invalid', 'KNVB222', 'gezin@example.com' );

		$user = $this->login( 'gezin@example.com' );

		$this->assertNotWPError( $user, 'The address is Anne\'s user_email, so core resolves it' );
		$this->assertSame( $anne, $user->ID );
		$this->assertNotSame( $bram, $user->ID, 'Bram must never be reached via the shared address' );
	}

	/**
	 * Neither member owns the shared address as their WordPress address — ambiguous, so
	 * the login must fail rather than guess.
	 */
	public function test_an_ambiguous_contact_address_fails_rather_than_guessing(): void {
		$this->member( 'anne.jansen', 'person-41@members.rondo.invalid', 'KNVB111', 'gezin@example.com' );
		$this->member( 'bram.jansen', 'person-42@members.rondo.invalid', 'KNVB222', 'gezin@example.com' );

		$user = $this->login( 'gezin@example.com' );

		$this->assertWPError( $user, 'Two members share this address; picking one would be impersonation' );
	}

	public function test_a_wrong_password_still_fails_when_resolving_by_knvb_id(): void {
		$this->member( 'anne.jansen', 'anne@example.com', 'KNVB123', 'anne@example.com' );

		$user = $this->login( 'KNVB123', 'wrong-password' );

		$this->assertWPError( $user );
	}

	public function test_an_unknown_identifier_fails(): void {
		$this->member( 'anne.jansen', 'anne@example.com', 'KNVB123', 'anne@example.com' );

		$this->assertWPError( $this->login( 'KNVB999' ) );
		$this->assertWPError( $this->login( 'niemand@example.com' ) );
	}

	public function test_an_empty_identifier_fails(): void {
		$this->assertWPError( $this->login( '' ) );
	}
}
