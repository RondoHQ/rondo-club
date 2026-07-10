<?php

namespace Tests\Wpunit;

use Rondo\REST\Fees;
use Rondo\REST\Capabilities;
use Tests\Support\RondoTestCase;

/**
 * The Financiën section of the UI is gated on the `financieel` capability, so the
 * endpoints behind it must be too. They used to require `manage_options`, which left
 * the penningmeester looking at a contributie page with no seasons and no categories.
 */
class FeePermissionsTest extends RondoTestCase {

	/** A treasurer: financieel, but not an administrator. */
	private function penningmeester(): int {
		$user_id = $this->createRondoUser( [ 'user_login' => 'penningmeester' ] );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'financieel' );
		return $user_id;
	}

	public function test_the_administrator_role_carries_the_financieel_capability(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$this->assertTrue(
			user_can( $admin_id, 'financieel' ),
			'UserRoles grants it on activation; the financieel-only gate relies on this'
		);
	}

	public function test_a_treasurer_may_read_the_contributie_settings(): void {
		wp_set_current_user( $this->penningmeester() );

		$this->assertTrue( ( new Fees() )->check_financieel_permission() );
	}

	public function test_an_administrator_may_read_the_contributie_settings(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertTrue( ( new Fees() )->check_financieel_permission() );
	}

	public function test_a_plain_member_may_not(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'plain_member_fees' ] ) );

		$this->assertFalse( ( new Fees() )->check_financieel_permission() );
	}

	public function test_a_treasurer_may_read_available_werkfuncties(): void {
		wp_set_current_user( $this->penningmeester() );

		$this->assertTrue( ( new Capabilities() )->check_admin_or_financieel_permission() );
	}

	public function test_a_plain_member_may_not_read_available_werkfuncties(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'plain_member_werk' ] ) );

		$this->assertFalse( ( new Capabilities() )->check_admin_or_financieel_permission() );
	}

	/**
	 * Every route on the contributie and facturen screens must be reachable by a
	 * treasurer. This walks the registered routes rather than trusting a list.
	 */
	public function test_no_fee_route_is_locked_behind_manage_options(): void {
		wp_set_current_user( $this->penningmeester() );

		$fees = new Fees();
		$this->assertTrue( method_exists( $fees, 'check_financieel_permission' ) );

		$source = file_get_contents( get_template_directory() . '/includes/class-rest-fees.php' );

		$this->assertStringNotContainsString(
			'check_admin_permission',
			$source,
			'A fee endpoint gated on manage_options locks out the penningmeester'
		);
		$this->assertStringNotContainsString(
			"current_user_can( 'manage_options' )",
			$source,
			'Same, via an inline closure'
		);
	}
}
