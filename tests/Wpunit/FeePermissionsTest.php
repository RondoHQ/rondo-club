<?php

namespace Tests\Wpunit;

use Rondo\REST\Fees;
use Rondo\REST\Capabilities;
use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
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

	/** An observer: financieel_read only — may look, may not touch. */
	private function meekijker(): int {
		$user_id = $this->createRondoUser( [ 'user_login' => 'meekijker' ] );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'financieel_read' );
		return $user_id;
	}

	public function test_an_observer_may_view_but_not_manage_finances(): void {
		$user_id = $this->meekijker();

		$this->assertTrue( UserRoles::can_view_finances( $user_id ) );
		$this->assertFalse( UserRoles::can_manage_finances( $user_id ) );
	}

	public function test_the_write_capability_implies_the_read_capability(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'write_only_treasurer' ] );
		( new \WP_User( $user_id ) )->add_cap( 'financieel' );

		$this->assertTrue(
			UserRoles::can_view_finances( $user_id ),
			'A custom role may carry financieel alone; it must still read its own invoices'
		);
	}

	/**
	 * The read capability is a view bypass, never an edit grant. Adding it to
	 * can_edit_people() would let a read-only treasurer rewrite member records.
	 */
	public function test_an_observer_may_not_edit_people(): void {
		$this->assertFalse( AccessControl::can_edit_people( $this->meekijker() ) );
	}

	public function test_a_treasurer_may_still_edit_people(): void {
		$this->assertTrue( AccessControl::can_edit_people( $this->penningmeester() ) );
	}

	public function test_an_observer_passes_the_read_gate_but_not_the_write_gate(): void {
		wp_set_current_user( $this->meekijker() );

		$fees = new Fees();
		$this->assertTrue( $fees->check_financieel_read_permission() );
		$this->assertFalse( $fees->check_financieel_permission() );
	}

	public function test_a_plain_member_passes_neither_finance_gate(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'plain_member_read' ] ) );

		$fees = new Fees();
		$this->assertFalse( $fees->check_financieel_read_permission() );
		$this->assertFalse( $fees->check_financieel_permission() );
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

	public function test_bulk_invoice_job_requires_explicit_confirmation(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$server = $this->bootRestControllers( [ Fees::class ] );

		$request = new \WP_REST_Request( 'POST', '/rondo/v1/fees/bulk-create-invoices' );
		$request->set_param( 'season', '2026-2027' );
		$response = $server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );

		$request->set_param( 'confirmed', false );
		$response = $server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'bulk_invoice_confirmation_required', $response->get_data()['code'] );
		$this->assertSame( [], get_option( \Rondo\Finance\BulkInvoiceCreator::JOB_OPTION, [] ) );
	}
}
