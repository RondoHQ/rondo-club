<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Tests\Support\RondoTestCase;

/**
 * Tests for the field-sensitivity axis of person access control.
 *
 * Person access has two independent axes. `AgeGroupAccessTest` covers "which
 * people"; this file covers "which fields". Before AccessControl gained
 * SENSITIVE_FIELD_GROUPS the second axis only ever applied to plain
 * members, so every management role read the club's finance flags whether or
 * not finance was any of their business.
 *
 * Every payload here is built from a fixture that actually carries the
 * sensitive values — asserting absence against a person who never had the
 * field set would pass for the wrong reason.
 */
class PersonFieldSensitivityTest extends RondoTestCase {

	/** Person field payload carrying one value from every sensitive group. */
	private function sensitive_payload(): array {
		return [
			'first_name'                => 'Jan',
			'last_name'                 => 'Jansen',
			'email_1'                   => 'jan@example.com',
			'wacht_op_overschrijving'   => true,
			'financiele-blokkade'       => true,
			'nikki-contributie-status'  => 'openstaand',
			'_nikki_2025_saldo'         => 42,
			'freescout-id'              => 1234,
			'onboarding-email-lid-sent' => '2026-01-01 10:00:00',
			'sponsit_contact_id'        => 'abc-123',
		];
	}

	private function filter_as( int $user_id ): array {
		return AccessControl::filter_sensitive_fields( $this->sensitive_payload(), $user_id );
	}

	public function test_administrator_reads_every_sensitive_group(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$fields = $this->filter_as( $admin_id );

		$this->assertArrayHasKey( 'financiele-blokkade', $fields );
		$this->assertArrayHasKey( '_nikki_2025_saldo', $fields );
		$this->assertArrayHasKey( 'freescout-id', $fields );
		$this->assertArrayHasKey( 'sponsit_contact_id', $fields );
	}

	public function test_volunteer_coordinator_loses_every_sensitive_group(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_vrijwilligers' ] );

		$fields = $this->filter_as( $user_id );

		$this->assertArrayNotHasKey( 'financiele-blokkade', $fields );
		$this->assertArrayNotHasKey( 'nikki-contributie-status', $fields );
		$this->assertArrayNotHasKey( '_nikki_2025_saldo', $fields );
		$this->assertArrayNotHasKey( 'freescout-id', $fields );
		$this->assertArrayNotHasKey( 'onboarding-email-lid-sent', $fields );
		$this->assertArrayNotHasKey( 'sponsit_contact_id', $fields );

		// Ordinary fields are untouched.
		$this->assertSame( 'Jan', $fields['first_name'] );
		$this->assertSame( 'jan@example.com', $fields['email_1'] );
	}

	/**
	 * The narrowing is deliberate and reaches roles that could read these fields
	 * before. Asserted so nobody "fixes" it back.
	 */
	public function test_fairplay_and_vog_lose_the_finance_group(): void {
		foreach ( [ 'rondo_fairplay', 'rondo_vog' ] as $role ) {
			$user_id = self::factory()->user->create( [ 'role' => $role ] );

			$fields = $this->filter_as( $user_id );

			$this->assertArrayNotHasKey( 'financiele-blokkade', $fields, $role . ' must not read finance flags' );
			$this->assertArrayNotHasKey( '_nikki_2025_saldo', $fields, $role . ' must not read nikki balances' );
		}
	}

	public function test_finance_read_role_keeps_the_finance_group(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_financieel_lezen' ] );

		$fields = $this->filter_as( $user_id );

		$this->assertArrayHasKey( 'financiele-blokkade', $fields );
		$this->assertArrayHasKey( '_nikki_2025_saldo', $fields );
		// Finance is not support: FreeScout stays hidden.
		$this->assertArrayNotHasKey( 'freescout-id', $fields );
	}

	/**
	 * The finance group is keyed on can_view_finances(), not on the raw
	 * `financieel_read` capability, so a role carrying only the write capability
	 * is not locked out of the fields it may change.
	 */
	public function test_write_only_finance_role_still_reads_the_finance_group(): void {
		$role_slug = 'rondo_test_financieel_write_only';
		add_role(
			$role_slug,
			'Test Financieel Write Only',
			[
				'read'       => true,
				'financieel' => true,
			]
			);
		$user_id = self::factory()->user->create( [ 'role' => $role_slug ] );

		$fields = $this->filter_as( $user_id );

		remove_role( $role_slug );

		$this->assertArrayHasKey(
			'financiele-blokkade',
			$fields,
			'financieel implies read; can_view_finances() must be the predicate'
		);
	}

	/**
	 * `rondo_bestuur` does not hold manage_options, and the membership desk
	 * chases onboarding mails — so ledenadministratie keeps the support group.
	 */
	public function test_ledenadministratie_keeps_the_support_group(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );

		$fields = $this->filter_as( $user_id );

		$this->assertArrayHasKey( 'freescout-id', $fields );
		$this->assertArrayHasKey( 'onboarding-email-lid-sent', $fields );
		// Membership administration is not finance.
		$this->assertArrayNotHasKey( 'financiele-blokkade', $fields );
	}

	public function test_sponsor_manager_keeps_only_the_sponsor_group(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_sponsorbeheerder' ] );

		$fields = $this->filter_as( $user_id );

		$this->assertArrayHasKey( 'sponsit_contact_id', $fields );
		$this->assertArrayNotHasKey( 'financiele-blokkade', $fields );
		$this->assertArrayNotHasKey( 'freescout-id', $fields );
	}

	/**
	 * `wacht_op_overschrijving` reads like a finance field but is a KNVB
	 * transfer flag — membership administration. It is deliberately in no group.
	 */
	public function test_transfer_flag_is_not_treated_as_a_finance_field(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_vrijwilligers' ] );

		$fields = $this->filter_as( $user_id );

		$this->assertArrayHasKey( 'wacht_op_overschrijving', $fields );
	}

	public function test_hidden_fields_cannot_be_used_as_filters_or_sorts(): void {
		$coordinator_id = self::factory()->user->create( [ 'role' => 'rondo_vrijwilligers' ] );
		$treasurer_id   = self::factory()->user->create( [ 'role' => 'rondo_financieel' ] );

		// Query parameters use canonical underscores; compatible storage uses hyphens.
		$this->assertTrue( AccessControl::field_is_hidden( 'financiele_blokkade', $coordinator_id ) );
		$this->assertTrue( AccessControl::field_is_hidden( 'financiele-blokkade', $coordinator_id ) );
		$this->assertFalse( AccessControl::field_is_hidden( 'financiele_blokkade', $treasurer_id ) );

		// Prefix groups match too, so sorting on a nikki column is refused.
		$this->assertTrue( AccessControl::field_is_hidden( '_nikki_2025_saldo', $coordinator_id ) );

		// Ordinary fields stay sortable and filterable for everyone.
		$this->assertFalse( AccessControl::field_is_hidden( 'leeftijdsgroep', $coordinator_id ) );
		$this->assertFalse( AccessControl::field_is_hidden( 'wacht_op_overschrijving', $coordinator_id ) );
	}

	/**
	 * The member allowlist and the sensitivity filter compose: a scoped member
	 * gets the allowlist, and the allowlist already excludes every sensitive
	 * group, so nothing sensitive can survive both.
	 */
	public function test_member_allowlist_and_sensitivity_filter_compose(): void {
		$member_id = $this->createRondoUser();

		$fields = AccessControl::filter_sensitive_fields(
			AccessControl::filter_member_visible_fields( $this->sensitive_payload() ),
			$member_id
		);

		$this->assertArrayHasKey( 'first_name', $fields );
		$this->assertArrayNotHasKey( 'financiele-blokkade', $fields );
		$this->assertArrayNotHasKey( 'freescout-id', $fields );
		$this->assertArrayNotHasKey( 'wacht_op_overschrijving', $fields );
	}
}
