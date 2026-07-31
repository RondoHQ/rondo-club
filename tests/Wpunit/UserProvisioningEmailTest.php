<?php

namespace Tests\Wpunit;

use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/**
 * Tests for household email handling in UserProvisioning.
 *
 * Members of one household share a mailbox, but WordPress insists on a unique
 * `user_email`. The first claimant keeps the real address; later members get an
 * undeliverable `.invalid` placeholder, and the real address lives in user meta.
 * Mail must never be addressed to the placeholder.
 */
class UserProvisioningEmailTest extends RondoTestCase {

	private UserProvisioning $provisioner;

	/** @var string[] Recipients captured from wp_mail(). */
	private array $sent_to = [];

	protected function set_up(): void {
		parent::set_up();
		$this->provisioner = new UserProvisioning();
		$this->sent_to     = [];

		// Short-circuit wp_mail: capture the recipient, send nothing.
		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$this->sent_to[] = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'];
				return true;
			},
			10,
			2
		);
	}

	private function person( string $first, string $last, ?string $email ): int {
		$person_id = $this->createPerson( [ 'post_title' => "$first $last" ] );
		update_field( 'first_name', $first, $person_id );
		update_field( 'last_name', $last, $person_id );
		if ( $email !== null ) {
			update_field( 'email_1', $email, $person_id );
		}
		return $person_id;
	}

	public function test_first_claimant_of_an_address_keeps_it_as_their_wp_email(): void {
		$person_id = $this->person( 'Anne', 'Jansen', 'gezin@example.com' );

		$result = $this->provisioner->provision( $person_id );

		$this->assertSame( 'created', $result['status'] );
		$user = get_userdata( $result['user_id'] );
		$this->assertSame( 'gezin@example.com', $user->user_email );
		$this->assertSame( 'gezin@example.com', UserProvisioning::contact_email( $result['user_id'] ) );
		$this->assertFalse( UserProvisioning::is_synthetic_email( $user->user_email ) );
	}

	public function test_second_member_of_a_household_gets_a_synthetic_wp_email(): void {
		$first  = $this->person( 'Anne', 'Jansen', 'gezin@example.com' );
		$second = $this->person( 'Bram', 'Jansen', 'gezin@example.com' );

		$this->provisioner->provision( $first );
		$result = $this->provisioner->provision( $second );

		$this->assertSame( 'created', $result['status'], 'A shared address must not block provisioning' );

		$user = get_userdata( $result['user_id'] );
		$this->assertSame( "person-{$second}@members.rondo.invalid", $user->user_email );
		$this->assertTrue( UserProvisioning::is_synthetic_email( $user->user_email ) );
		$this->assertSame(
			'gezin@example.com',
			UserProvisioning::contact_email( $result['user_id'] ),
			'The real address must survive in user meta'
		);
	}

	/**
	 * The whole point of the placeholder: mail still reaches the family mailbox.
	 */
	public function test_the_welcome_mail_goes_to_the_real_address_not_the_placeholder(): void {
		$first  = $this->person( 'Anne', 'Jansen', 'gezin@example.com' );
		$second = $this->person( 'Bram', 'Jansen', 'gezin@example.com' );

		$this->provisioner->provision( $first );
		$this->sent_to = [];
		$this->provisioner->provision( $second );

		$this->assertSame( [ 'gezin@example.com' ], $this->sent_to );
	}

	public function test_a_third_household_member_also_works(): void {
		$ids = [
			$this->person( 'Anne', 'Jansen', 'gezin@example.com' ),
			$this->person( 'Bram', 'Jansen', 'gezin@example.com' ),
			$this->person( 'Cato', 'Jansen', 'gezin@example.com' ),
		];

		foreach ( $ids as $person_id ) {
			$this->assertSame( 'created', $this->provisioner->provision( $person_id )['status'] );
		}

		$this->assertCount( 3, $this->sent_to );
		$this->assertSame( [ 'gezin@example.com', 'gezin@example.com', 'gezin@example.com' ], $this->sent_to );
	}

	public function test_provisioning_is_idempotent(): void {
		$person_id = $this->person( 'Anne', 'Jansen', 'gezin@example.com' );

		$first  = $this->provisioner->provision( $person_id );
		$second = $this->provisioner->provision( $person_id );

		$this->assertSame( 'created', $first['status'] );
		$this->assertSame( 'already_exists', $second['status'] );
		$this->assertSame( $first['user_id'], $second['user_id'] );
	}

	/**
	 * A user linked to the person but missing the person→user meta must be adopted,
	 * not duplicated into a second account.
	 */
	public function test_an_orphaned_user_link_is_adopted_rather_than_duplicated(): void {
		$person_id = $this->person( 'Anne', 'Jansen', 'gezin@example.com' );
		$result    = $this->provisioner->provision( $person_id );

		// Simulate the person→user link going missing.
		delete_post_meta( $person_id, UserProvisioning::META_USER_ID );

		$again = $this->provisioner->provision( $person_id );

		$this->assertSame( 'already_exists', $again['status'] );
		$this->assertSame( $result['user_id'], $again['user_id'] );
		$this->assertSame( 1, (int) count_users()['avail_roles']['rondo_user'] ?? 0 );
	}

	public function test_a_person_without_an_email_cannot_be_provisioned(): void {
		$person_id = $this->person( 'Anne', 'Jansen', null );

		$result = $this->provisioner->provision( $person_id );

		$this->assertWPError( $result );
		$this->assertSame( 'no_email', $result->get_error_code() );
	}

	public function test_contact_email_falls_back_to_user_email_for_legacy_accounts(): void {
		$user_id = self::factory()->user->create( [ 'user_email' => 'oud@example.com' ] );

		$this->assertSame( 'oud@example.com', UserProvisioning::contact_email( $user_id ) );
	}

	public function test_contact_email_is_null_when_only_a_placeholder_remains(): void {
		$user_id = self::factory()->user->create( [ 'user_email' => 'person-99@members.rondo.invalid' ] );

		$this->assertNull(
			UserProvisioning::contact_email( $user_id ),
			'A placeholder must never be handed back as a deliverable address'
		);
	}

	// ----------------------------------------------------- ContactEmailRouter

	/**
	 * Core addresses the password-reset mail to `user_email`. For a household member
	 * that is a `.invalid` placeholder, so without the router the account would be
	 * unrecoverable. This is the test that matters most.
	 */
	public function test_a_password_reset_reaches_the_household_mailbox(): void {
		$first  = $this->person( 'Anne', 'Jansen', 'gezin@example.com' );
		$second = $this->person( 'Bram', 'Jansen', 'gezin@example.com' );

		$this->provisioner->provision( $first );
		$result = $this->provisioner->provision( $second );

		$user = get_userdata( $result['user_id'] );
		$this->assertTrue( UserProvisioning::is_synthetic_email( $user->user_email ) );

		$this->sent_to = [];
		$this->assertTrue( retrieve_password( $user->user_login ) );

		$this->assertSame(
			[ 'gezin@example.com' ],
			$this->sent_to,
			'The reset link must be rerouted to the real address, not the .invalid placeholder'
		);
	}

	public function test_mail_to_an_unknown_placeholder_is_dropped_not_delivered(): void {
		$atts = \Rondo\Users\ContactEmailRouter::reroute_synthetic_recipients(
			[
				'to'      => 'person-4242@members.rondo.invalid',
				'subject' => 'x',
			]
		);

		$this->assertSame( [], $atts['to'], 'Fail closed: an unresolvable placeholder must not be delivered' );
	}

	public function test_ordinary_recipients_pass_through_untouched(): void {
		$atts = \Rondo\Users\ContactEmailRouter::reroute_synthetic_recipients(
			[
				'to'      => [ 'iemand@example.com', 'ander@example.com' ],
				'subject' => 'x',
			]
		);

		$this->assertSame( [ 'iemand@example.com', 'ander@example.com' ], $atts['to'] );
	}
}
