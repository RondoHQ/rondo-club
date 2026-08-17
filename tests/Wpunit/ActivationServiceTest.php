<?php

namespace Tests\Wpunit;

use Rondo\Users\ActivationService;
use Rondo\Users\GuardianAccountService;
use Rondo\Users\MagicLoginActivation;
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
		\Rondo\Fields\Fields::update_for_post( $person_id, 'first_name', $first );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'last_name', $last );
		if ( $email !== null ) {
			\Rondo\Fields\Fields::update_for_post( $person_id, 'email_1', $email );
		}
		if ( $former ) {
			update_post_meta( $person_id, 'former_member', '1' );
		}
		return $person_id;
	}

	private function parent_relationship_type(): int {
		$term = get_term_by( 'slug', 'parent', 'relationship_type' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$created = wp_insert_term( 'Ouder', 'relationship_type', [ 'slug' => 'parent' ] );
		$this->assertIsArray( $created );
		return (int) $created['term_id'];
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

	public function test_existing_account_receives_a_magic_login_link(): void {
		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );
		$user_id   = self::factory()->user->create(
			[
				'user_login' => 'anne',
				'user_email' => 'anne@example.com',
			]
		);
		update_post_meta( $person_id, UserProvisioning::META_USER_ID, $user_id );

		add_filter(
			'rondo_activation_magic_login_url',
			fn() => 'https://example.com/magic-login-token'
		);

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

		$this->assertTrue( ActivationService::send_magic_login_email( 'anne@example.com', [ $person_id ] ) );
		$this->assertSame( [ 'anne@example.com' ], $mail['to'] );
		$this->assertStringContainsString( 'https://example.com/magic-login-token', $mail['message'] );
		$this->assertStringContainsString( 'Direct inloggen', $mail['message'] );
	}

	public function test_household_receives_a_named_magic_link_for_each_account(): void {
		$anne = $this->person( 'Anne Jansen', 'gezin@example.com' );
		$bram = $this->person( 'Bram Jansen', 'gezin@example.com' );

		foreach (
			[
				$anne => 'anne',
				$bram => 'bram',
			] as $person_id => $login
		) {
			$user_id = self::factory()->user->create(
				[
					'user_login' => $login,
					'user_email' => $login . '@example.com',
				]
			);
			update_post_meta( $person_id, UserProvisioning::META_USER_ID, $user_id );
		}

		add_filter(
			'rondo_activation_magic_login_url',
			fn( $url, $user ) => 'https://example.com/magic-' . $user->user_login,
			10,
			2
		);

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

		$this->assertTrue( ActivationService::send_magic_login_email( 'gezin@example.com', [ $anne, $bram ] ) );
		$this->assertStringContainsString( 'Inloggen als Anne Jansen', $mail['message'] );
		$this->assertStringContainsString( 'Inloggen als Bram Jansen', $mail['message'] );
		$this->assertStringContainsString( 'https://example.com/magic-anne', $mail['message'] );
		$this->assertStringContainsString( 'https://example.com/magic-bram', $mail['message'] );
	}

	public function test_magic_login_request_provisions_one_unambiguous_adult(): void {
		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );
		add_filter( 'rondo_activation_magic_login_url', fn() => 'https://example.com/new-account-login' );

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

		ActivationService::send_for_magic_login_request( 'anne@example.com' );

		$this->assertTrue( ActivationService::has_account( $person_id ) );
		$this->assertIsArray( $mail );
		$this->assertStringContainsString( 'https://example.com/new-account-login', $mail['message'] );
	}

	public function test_magic_login_request_keeps_youth_on_the_guardian_picker(): void {
		$person_id = $this->person( 'Rens van Haren', 'bas@example.com' );
		update_post_meta( $person_id, 'leeftijdsgroep', 'Onder 12' );

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

		ActivationService::send_for_magic_login_request( 'bas@example.com' );

		$this->assertFalse( ActivationService::has_account( $person_id ) );
		$this->assertIsArray( $mail );
		$this->assertStringStartsWith( 'Activeer je account bij ', $mail['subject'] );
		$this->assertStringContainsString( '/activeren/', $mail['message'] );
	}

	public function test_magic_login_request_keeps_a_household_on_the_identity_picker(): void {
		$anne = $this->person( 'Anne Jansen', 'gezin@example.com' );
		$bram = $this->person( 'Bram Jansen', 'gezin@example.com' );

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

		ActivationService::send_for_magic_login_request( 'gezin@example.com' );

		$this->assertFalse( ActivationService::has_account( $anne ) );
		$this->assertFalse( ActivationService::has_account( $bram ) );
		$this->assertIsArray( $mail );
		$this->assertStringStartsWith( 'Activeer je account bij ', $mail['subject'] );
	}

	public function test_magic_login_request_combines_existing_login_and_household_activation(): void {
		$anne    = $this->person( 'Anne Jansen', 'gezin@example.com' );
		$bram    = $this->person( 'Bram Jansen', 'gezin@example.com' );
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'anne',
				'user_email' => 'gezin@example.com',
			]
		);
		update_post_meta( $anne, UserProvisioning::META_USER_ID, $user_id );
		add_filter( 'rondo_activation_magic_login_url', fn() => 'https://example.com/magic-anne' );

		$sent = [];
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$sent ) {
				$sent[] = $atts;
				return true;
			},
			5,
			2
		);

		ActivationService::send_for_magic_login_request( 'gezin@example.com' );

		$this->assertCount( 1, $sent );
		$this->assertFalse( ActivationService::has_account( $bram ) );
		$this->assertStringContainsString( 'https://example.com/magic-anne', $sent[0]['message'] );
		$this->assertStringContainsString( 'Inloggen als Anne Jansen', $sent[0]['message'] );
		$this->assertStringContainsString( '/activeren/', $sent[0]['message'] );
		$this->assertStringContainsString( 'Account activeren', $sent[0]['message'] );
	}

	public function test_magic_login_request_sends_nothing_for_an_unknown_address(): void {
		$sent = [];
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$sent ) {
				$sent[] = $atts;
				return true;
			},
			5,
			2
		);

		ActivationService::send_for_magic_login_request( 'niemand@example.com' );

		$this->assertSame( [], $sent );
	}

	public function test_magic_login_bridge_hides_account_state_and_dispatches_afterward(): void {
		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );
		add_filter( 'rondo_activation_magic_login_url', fn() => 'https://example.com/deferred-login' );

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

		$bridge       = new MagicLoginActivation();
		$_POST['log'] = 'anne@example.com';
		$this->assertTrue( $bridge->intercept_send( null, false ) );

		$response = $bridge->normalize_result(
			[
				'errors'    => new \WP_Error( 'missing_user', 'Account bestaat niet.' ),
				'info'      => '',
				'show_form' => true,
			],
			[]
		);
		unset( $_POST['log'] );

		$this->assertInstanceOf( \WP_Error::class, $response['errors'] );
		$this->assertFalse( $response['errors']->has_errors() );
		$this->assertFalse( $response['show_form'] );
		$this->assertStringContainsString( 'Als er een account bestaat', $response['info'] );
		$this->assertFalse( ActivationService::has_account( $person_id ), 'Provisioning must wait until after the response' );

		$bridge->dispatch_queued_request( false );

		$this->assertTrue( ActivationService::has_account( $person_id ) );
		$this->assertIsArray( $mail );
		$this->assertStringContainsString( 'https://example.com/deferred-login', $mail['message'] );
	}

	public function test_magic_login_bridge_preserves_an_earlier_plugin_failure(): void {
		$bridge       = new MagicLoginActivation();
		$blocked      = new \WP_Error( 'captcha_failed', 'Controle mislukt.' );
		$_POST['log'] = 'anne@example.com';

		$this->assertSame( $blocked, $bridge->intercept_send( $blocked, false ) );
		$response = [
			'errors'    => $blocked,
			'info'      => '',
			'show_form' => true,
		];
		$this->assertSame( $response, $bridge->normalize_result( $response, [] ) );

		unset( $_POST['log'] );
	}

	public function test_magic_login_bridge_silently_throttles_activation_requests(): void {
		$person_id = $this->person( 'Anne Jansen', 'anne@example.com' );
		$ip        = '203.0.113.77';
		for ( $attempt = 0; $attempt < ActivationService::RATE_EMAIL_MAX; $attempt++ ) {
			ActivationService::record_attempt( 'anne@example.com', $ip );
		}

		$sent = [];
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$sent ) {
				$sent[] = $atts;
				return true;
			},
			5,
			2
		);

		$previous_ip            = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR'] = $ip;
		$_POST['log']           = 'anne@example.com';
		$bridge                 = new MagicLoginActivation();
		$this->assertTrue( $bridge->intercept_send( null, false ) );
		unset( $_POST['log'] );
		if ( $previous_ip === null ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $previous_ip;
		}

		$bridge->dispatch_queued_request( false );

		$this->assertFalse( ActivationService::has_account( $person_id ) );
		$this->assertSame( [], $sent );
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

	public function test_a_parent_can_activate_through_a_youth_person(): void {
		$child_id = $this->person( 'Rens van Haren', 'bas@example.com' );
		update_post_meta( $child_id, 'leeftijdsgroep', 'Onder 12' );
		$token = ActivationService::create_token( 'bas@example.com' );

		$url = ActivationService::activate_guardian( $token, $child_id, 'Bas van Haren' );

		$user_id = (int) get_post_meta( $child_id, UserProvisioning::META_USER_ID, true );
		$this->assertIsString( $url );
		$this->assertGreaterThan( 0, $user_id );
		$this->assertSame( 'Bas van Haren', GuardianAccountService::pending_for_user( $user_id )['name'] );
		$this->assertSame( $child_id, (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
	}

	public function test_a_matching_parent_record_receives_the_account_immediately(): void {
		$parent_type = $this->parent_relationship_type();
		$child_id    = $this->person( 'Rens van Haren', 'bas@example.com' );
		$parent_id   = $this->person( 'Bas van Haren', 'bas@example.com' );
		update_post_meta( $child_id, 'leeftijdsgroep', 'Onder 12' );
		\Rondo\Fields\Fields::update_for_post(
			$child_id,
			'relationships',
			[
				[
					'related_person'    => $parent_id,
					'relationship_type' => $parent_type,
				],
			]
		);

		$url = ActivationService::activate_guardian(
			ActivationService::create_token( 'bas@example.com' ),
			$child_id,
			'Bas van Haren'
		);

		$user_id = (int) get_post_meta( $parent_id, UserProvisioning::META_USER_ID, true );
		$this->assertIsString( $url );
		$this->assertStringContainsString( 'action=rp', $url );
		$this->assertGreaterThan( 0, $user_id );
		$this->assertFalse( ActivationService::has_account( $child_id ) );
		$this->assertSame( $parent_id, (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
		$this->assertNull( GuardianAccountService::pending_for_user( $user_id ) );
	}

	public function test_a_matching_unlinked_parent_is_linked_before_activation(): void {
		$parent_type = $this->parent_relationship_type();
		$child_id    = $this->person( 'Rens van Haren', 'bas@example.com' );
		$parent_id   = $this->person( 'Bas van Haren', 'bas@example.com' );
		update_post_meta( $child_id, 'leeftijdsgroep', 'Onder 12' );
		\Rondo\Fields\Fields::update_for_post( $child_id, 'knvb_id', 'TEST-ACTIVATION-0' );

		$url = ActivationService::activate_guardian(
			ActivationService::create_token( 'bas@example.com' ),
			$child_id,
			'  BAS   VAN HAREN '
		);

		$relationships = \Rondo\Fields\Fields::get_for_post( $child_id, 'relationships' );
		$this->assertIsString( $url );
		$this->assertCount( 1, $relationships );
		$this->assertSame( $parent_id, (int) $relationships[0]['related_person'] );
		$this->assertSame( $parent_type, (int) $relationships[0]['relationship_type'] );
		$this->assertTrue( ActivationService::has_account( $parent_id ) );
		$this->assertFalse( ActivationService::has_account( $child_id ) );
	}

	public function test_an_unmatched_parent_is_created_linked_and_given_the_account(): void {
		$parent_type = $this->parent_relationship_type();
		$child_id    = $this->person( 'Rens van Haren', 'bas@example.com' );
		update_post_meta( $child_id, 'leeftijdsgroep', 'Onder 12' );
		\Rondo\Fields\Fields::update_for_post( $child_id, 'knvb_id', 'TEST-ACTIVATION-1' );

		$url = ActivationService::activate_guardian(
			ActivationService::create_token( 'bas@example.com' ),
			$child_id,
			'Bas van Haren'
		);

		$relationships = \Rondo\Fields\Fields::get_for_post( $child_id, 'relationships' );
		$this->assertIsString( $url );
		$this->assertCount( 1, $relationships );
		$this->assertSame( $parent_type, (int) $relationships[0]['relationship_type'] );
		$parent_id = (int) $relationships[0]['related_person'];
		$user_id   = (int) get_post_meta( $parent_id, UserProvisioning::META_USER_ID, true );
		$this->assertSame( 'Bas van Haren', get_the_title( $parent_id ) );
		$this->assertSame( 'bas@example.com', \Rondo\Fields\Fields::get_for_post( $parent_id, 'email_1' ) );
		$this->assertGreaterThan( 0, $user_id );
		$this->assertFalse( ActivationService::has_account( $child_id ) );
		$this->assertSame( $parent_id, (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
		$this->assertNull( GuardianAccountService::pending_for_user( $user_id ) );
	}

	public function test_an_existing_parent_account_is_opened_with_magic_login(): void {
		$parent_type = $this->parent_relationship_type();
		$child_id    = $this->person( 'Rens van Haren', 'bas@example.com' );
		$parent_id   = $this->person( 'Bas van Haren', 'bas@example.com' );
		update_post_meta( $child_id, 'leeftijdsgroep', 'Onder 12' );
		\Rondo\Fields\Fields::update_for_post(
			$child_id,
			'relationships',
			[
				[
					'related_person'    => $parent_id,
					'relationship_type' => $parent_type,
				],
			]
		);
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'bas-ouder',
				'user_email' => 'bas@example.com',
			]
		);
		update_post_meta( $parent_id, UserProvisioning::META_USER_ID, $user_id );
		update_user_meta( $user_id, 'rondo_linked_person_id', $parent_id );
		add_filter( 'rondo_activation_magic_login_url', fn() => 'https://example.com/magic-parent' );
		$token = ActivationService::create_token( 'bas@example.com' );

		$url = ActivationService::activate_guardian( $token, $child_id, 'Bas van Haren' );

		$this->assertSame( 'https://example.com/magic-parent', $url );
		$this->assertNull( ActivationService::email_for_token( $token ) );
		$this->assertFalse( ActivationService::has_account( $child_id ) );
	}

	public function test_a_full_parent_household_uses_the_temporary_child_fallback(): void {
		$parent_type   = $this->parent_relationship_type();
		$child_id      = $this->person( 'Rens van Haren', 'nieuwe.ouder@example.com' );
		$relationships = [];
		update_post_meta( $child_id, 'leeftijdsgroep', 'Onder 12' );
		\Rondo\Fields\Fields::update_for_post( $child_id, 'knvb_id', 'TEST-ACTIVATION-2' );
		foreach ( [ 'Eerste Ouder', 'Tweede Ouder' ] as $index => $name ) {
			$parent_id       = $this->person( $name, 'ouder' . $index . '@example.com' );
			$relationships[] = [
				'related_person'    => $parent_id,
				'relationship_type' => $parent_type,
			];
		}
		\Rondo\Fields\Fields::update_for_post( $child_id, 'relationships', $relationships );

		$url = ActivationService::activate_guardian(
			ActivationService::create_token( 'nieuwe.ouder@example.com' ),
			$child_id,
			'Derde Ouder'
		);

		$user_id = (int) get_post_meta( $child_id, UserProvisioning::META_USER_ID, true );
		$this->assertIsString( $url );
		$this->assertGreaterThan( 0, $user_id );
		$this->assertSame( 'Derde Ouder', GuardianAccountService::pending_for_user( $user_id )['name'] );
		$this->assertCount( 2, \Rondo\Fields\Fields::get_for_post( $child_id, 'relationships' ) );
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
