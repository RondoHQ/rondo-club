<?php

namespace Tests\Wpunit;

use Rondo\Users\ActivationService;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/**
 * Tests for the public self-service activation flow.
 *
 * This is an unauthenticated endpoint, so the tests that matter are the abuse ones:
 * a token must not activate someone on another address, must not be replayable, and
 * must not survive expiry. Keep literal email addresses out of docblocks in this suite
 * — Codeception reads anything after an at-sign as an annotation.
 */
class ActivationServiceTest extends RondoTestCase {

	private function person( string $name, ?string $email, bool $former = false ): int {
		$person_id        = $this->createPerson( [ 'post_title' => $name ] );
		[ $first, $last ] = array_pad( explode( ' ', $name, 2 ), 2, '' );
		update_field( 'first_name', $first, $person_id );
		update_field( 'last_name', $last, $person_id );
		if ( $email !== null ) {
			update_field( 'email_1', $email, $person_id );
		}
		if ( $former ) {
			update_post_meta( $person_id, 'former_member', '1' );
		}
		return $person_id;
	}

	protected function set_up(): void {
		parent::set_up();
		// Never send during tests.
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	// ------------------------------------------------------------- lookup

	public function test_an_address_finds_its_person(): void {
		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );

		$this->assertSame( [ $person_id ], ActivationService::persons_for_email( 'anne@example.com' ) );
	}

	public function test_a_family_address_finds_everyone_on_it(): void {
		$anne = $this->person( 'Anne Jansen', 'gezin@example.com' );
		$bram = $this->person( 'Bram Jansen', 'gezin@example.com' );

		$found = ActivationService::persons_for_email( 'gezin@example.com' );

		sort( $found );
		$expected = [ $anne, $bram ];
		sort( $expected );
		$this->assertSame( $expected, $found );
	}

	public function test_former_members_are_never_activatable(): void {
		$this->person( 'Oud Lid', 'oud@example.com', true );

		$this->assertSame( [], ActivationService::persons_for_email( 'oud@example.com' ) );
	}

	public function test_an_unknown_address_finds_nobody(): void {
		$this->person( 'Anne Jansen', 'anne@example.com' );

		$this->assertSame( [], ActivationService::persons_for_email( 'niemand@example.com' ) );
	}

	public function test_a_malformed_address_finds_nobody(): void {
		$this->assertSame( [], ActivationService::persons_for_email( 'not-an-email' ) );
	}

	// -------------------------------------------------------------- tokens

	public function test_a_token_round_trips_to_its_address(): void {
		$token = ActivationService::create_token( 'anne@example.com' );

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $token );
		$this->assertSame( 'anne@example.com', ActivationService::email_for_token( $token ) );
	}

	public function test_the_raw_token_is_never_stored(): void {
		$token = ActivationService::create_token( 'anne@example.com' );

		$this->assertFalse(
			get_transient( ActivationService::TOKEN_TRANSIENT_PREFIX . $token ),
			'Only the hash may be stored, so a database read cannot be replayed as a link'
		);
		$this->assertSame(
			'anne@example.com',
			get_transient( ActivationService::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ) )
		);
	}

	public function test_activation_email_uses_the_membership_administration_sender(): void {
		$mail = null;
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$mail ) {
				$mail = $atts;
				return true;
			},
			5,
			2
		);

		$this->assertTrue( ActivationService::send_activation_email( 'anne@example.com', str_repeat( 'a', 64 ) ) );
		$this->assertIsArray( $mail );
		$from_headers = array_values(
			array_filter(
				$mail['headers'],
				fn( string $header ): bool => str_starts_with( $header, 'From: ' )
			)
		);
		$this->assertCount( 1, $from_headers );
		$this->assertStringContainsString( '<' . ActivationService::ACTIVATION_FROM_EMAIL . '>', $from_headers[0] );
	}

	public function test_a_garbage_token_resolves_to_nothing(): void {
		$this->assertNull( ActivationService::email_for_token( 'nope' ) );
		$this->assertNull( ActivationService::email_for_token( str_repeat( 'a', 64 ) ) );
	}

	public function test_a_consumed_token_is_dead(): void {
		$token = ActivationService::create_token( 'anne@example.com' );
		ActivationService::consume_token( $token );

		$this->assertNull( ActivationService::email_for_token( $token ) );
	}

	// ---------------------------------------------------------- activation

	public function test_activating_creates_an_account_and_returns_a_password_url(): void {
		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );
		$token     = ActivationService::create_token( 'anne@example.com' );

		$url = ActivationService::activate( $token, $person_id );

		$this->assertIsString( $url );
		$this->assertStringContainsString( 'action=rp', $url );
		$this->assertTrue( ActivationService::has_account( $person_id ) );
	}

	/**
	 * The attack this endpoint exists to resist: hold a token for your own address,
	 * then post someone else's person_id.
	 */
	public function test_a_token_cannot_activate_a_person_on_another_address(): void {
		$mine     = $this->person( 'Anne Jansen', 'anne@example.com' );
		$stranger = $this->person( 'Onbekende', 'iemand.anders@example.com' );
		$token    = ActivationService::create_token( 'anne@example.com' );

		$result = ActivationService::activate( $token, $stranger );

		$this->assertWPError( $result );
		$this->assertSame( 'person_mismatch', $result->get_error_code() );
		$this->assertFalse( ActivationService::has_account( $stranger ) );
		$this->assertFalse( ActivationService::has_account( $mine ) );
	}

	public function test_a_token_is_burned_after_use_and_cannot_be_replayed(): void {
		$anne  = $this->person( 'Anne Jansen', 'gezin@example.com' );
		$bram  = $this->person( 'Bram Jansen', 'gezin@example.com' );
		$token = ActivationService::create_token( 'gezin@example.com' );

		$this->assertIsString( ActivationService::activate( $token, $anne ) );

		$second = ActivationService::activate( $token, $bram );
		$this->assertWPError( $second );
		$this->assertSame( 'invalid_token', $second->get_error_code() );
		$this->assertFalse( ActivationService::has_account( $bram ) );
	}

	public function test_an_expired_token_cannot_activate(): void {
		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );
		$token     = ActivationService::create_token( 'anne@example.com' );
		delete_transient( ActivationService::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ) );

		$result = ActivationService::activate( $token, $person_id );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	public function test_activating_twice_is_refused(): void {
		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );

		ActivationService::activate( ActivationService::create_token( 'anne@example.com' ), $person_id );
		$second = ActivationService::activate( ActivationService::create_token( 'anne@example.com' ), $person_id );

		$this->assertWPError( $second );
		$this->assertSame( 'already_active', $second->get_error_code() );
	}

	public function test_a_former_member_cannot_be_activated_even_with_a_valid_token(): void {
		$person_id = $this->person( 'Oud Lid', 'oud@example.com', true );
		$token     = ActivationService::create_token( 'oud@example.com' );

		$result = ActivationService::activate( $token, $person_id );

		$this->assertWPError( $result );
		$this->assertSame( 'person_mismatch', $result->get_error_code() );
	}

	/**
	 * Activation must not send the welcome mail — the member already came from their
	 * inbox and is sent straight on to set a password.
	 */
	public function test_activation_sends_no_welcome_email(): void {
		$sent = [];
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$sent ) {
				$sent[] = $atts['subject'];
				return true;
			},
			5,
			2
		);

		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );
		ActivationService::activate( ActivationService::create_token( 'anne@example.com' ), $person_id );

		$this->assertSame( [], $sent, 'No mail may be sent when activating from a link' );
	}

	public function test_the_second_household_member_gets_a_synthetic_wp_email(): void {
		$anne = $this->person( 'Anne Jansen', 'gezin@example.com' );
		$bram = $this->person( 'Bram Jansen', 'gezin@example.com' );

		ActivationService::activate( ActivationService::create_token( 'gezin@example.com' ), $anne );
		ActivationService::activate( ActivationService::create_token( 'gezin@example.com' ), $bram );

		$bram_user = get_userdata( (int) get_post_meta( $bram, UserProvisioning::META_USER_ID, true ) );

		$this->assertTrue( UserProvisioning::is_synthetic_email( $bram_user->user_email ) );
		$this->assertSame( 'gezin@example.com', UserProvisioning::contact_email( $bram_user->ID ) );
	}

	// ------------------------------------------------------------ rate limit

	public function test_an_address_is_rate_limited_after_three_requests(): void {
		$ip = '203.0.113.9';

		for ( $i = 0; $i < ActivationService::RATE_EMAIL_MAX; $i++ ) {
			$this->assertFalse( ActivationService::is_rate_limited( 'anne@example.com', $ip ) );
			ActivationService::record_attempt( 'anne@example.com', $ip );
		}

		$this->assertTrue( ActivationService::is_rate_limited( 'anne@example.com', $ip ) );
	}

	public function test_an_ip_is_rate_limited_across_different_addresses(): void {
		$ip = '203.0.113.10';

		for ( $i = 0; $i < ActivationService::RATE_IP_MAX; $i++ ) {
			ActivationService::record_attempt( "member{$i}@example.com", $ip );
		}

		$this->assertTrue(
			ActivationService::is_rate_limited( 'nog-iemand@example.com', $ip ),
			'Enumeration across many addresses from one IP must be throttled'
		);
	}

	public function test_a_different_ip_is_unaffected(): void {
		for ( $i = 0; $i < ActivationService::RATE_IP_MAX; $i++ ) {
			ActivationService::record_attempt( "member{$i}@example.com", '203.0.113.11' );
		}

		$this->assertFalse( ActivationService::is_rate_limited( 'anne@example.com', '203.0.113.12' ) );
	}
}
