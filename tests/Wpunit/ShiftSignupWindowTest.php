<?php

namespace Tests\Wpunit;

use Rondo\Config\ClubConfig;
use Rondo\Volunteer\ShiftSignupWindow;
use Tests\Support\RondoTestCase;

/**
 * The two-halves signup window.
 *
 * A season is planned in full but opened in halves, so the autumn does not fill
 * up with people who wanted a quiet spring dienst. July–December is open from
 * the season's start; January–June opens on a configured month-day, 1 November
 * by default.
 */
class ShiftSignupWindowTest extends RondoTestCase {

	protected function tear_down(): void {
		delete_option( ClubConfig::OPTION_VOLUNTEER_SECOND_HALF_OPENS );
		parent::tear_down();
	}

	private function create_shift( string $start ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $shift_id, 'start_datetime', $start );

		return $shift_id;
	}

	private function at( string $moment ): \DateTimeImmutable {
		return new \DateTimeImmutable( $moment, wp_timezone() );
	}

	public function test_first_half_is_open_from_the_start_of_the_season(): void {
		$shift = $this->create_shift( '2026-10-03 10:00:00' );

		$this->assertTrue( ShiftSignupWindow::is_open( $shift, $this->at( '2026-09-01 09:00:00' ) ) );
		$this->assertNull( ShiftSignupWindow::opens_at( $shift, $this->at( '2026-09-01 09:00:00' ) ) );
	}

	public function test_second_half_is_closed_until_the_opening_date(): void {
		$shift = $this->create_shift( '2027-03-06 10:00:00' );

		$this->assertFalse( ShiftSignupWindow::is_open( $shift, $this->at( '2026-10-31 23:59:00' ) ) );
		$this->assertSame(
			'2026-11-01',
			ShiftSignupWindow::opens_at( $shift, $this->at( '2026-10-31 23:59:00' ) )->format( 'Y-m-d' )
		);
	}

	/** The boundary is the club's midnight, not UTC's. */
	public function test_second_half_opens_exactly_at_midnight_local_time(): void {
		$shift = $this->create_shift( '2027-03-06 10:00:00' );

		$this->assertTrue( ShiftSignupWindow::is_open( $shift, $this->at( '2026-11-01 00:00:00' ) ) );
		$this->assertTrue( ShiftSignupWindow::is_open( $shift, $this->at( '2026-11-01 00:00:01' ) ) );
	}

	public function test_configured_date_moves_the_opening(): void {
		update_option( ClubConfig::OPTION_VOLUNTEER_SECOND_HALF_OPENS, '10-15' );
		$shift = $this->create_shift( '2027-02-06 10:00:00' );

		$this->assertFalse( ShiftSignupWindow::is_open( $shift, $this->at( '2026-10-14 12:00:00' ) ) );
		$this->assertTrue( ShiftSignupWindow::is_open( $shift, $this->at( '2026-10-15 00:00:00' ) ) );
	}

	public function test_malformed_configuration_falls_back_to_the_default(): void {
		update_option( ClubConfig::OPTION_VOLUNTEER_SECOND_HALF_OPENS, 'nonsense' );
		$shift = $this->create_shift( '2027-03-06 10:00:00' );

		$this->assertSame(
			'2026-11-01',
			ShiftSignupWindow::opens_at( $shift, $this->at( '2026-09-01 12:00:00' ) )->format( 'Y-m-d' )
		);
	}

	public function test_month_day_validation(): void {
		$this->assertSame( '11-01', ShiftSignupWindow::sanitize_month_day( '11-01' ) );
		$this->assertSame( '02-29', ShiftSignupWindow::sanitize_month_day( '02-29' ), 'leap day is a valid choice' );
		$this->assertNull( ShiftSignupWindow::sanitize_month_day( '13-01' ) );
		$this->assertNull( ShiftSignupWindow::sanitize_month_day( '02-30' ) );
		$this->assertNull( ShiftSignupWindow::sanitize_month_day( '1-1' ) );
		$this->assertNull( ShiftSignupWindow::sanitize_month_day( '' ) );
	}

	/**
	 * A June rollout of next season must not quietly open its autumn diensten,
	 * which would otherwise look open because July–December always is.
	 */
	public function test_a_future_seasons_first_half_is_still_closed(): void {
		$shift = $this->create_shift( '2027-09-04 10:00:00' ); // season 2027-2028

		$now = $this->at( '2027-06-01 12:00:00' ); // still season 2026-2027
		$this->assertFalse( ShiftSignupWindow::is_open( $shift, $now ) );
		$this->assertSame( '2027-07-01', ShiftSignupWindow::opens_at( $shift, $now )->format( 'Y-m-d' ) );
	}

	public function test_a_future_seasons_second_half_waits_for_its_own_opening_date(): void {
		$shift = $this->create_shift( '2028-02-05 10:00:00' ); // season 2027-2028

		$now = $this->at( '2027-06-01 12:00:00' );
		$this->assertSame( '2027-11-01', ShiftSignupWindow::opens_at( $shift, $now )->format( 'Y-m-d' ) );
	}

	public function test_past_seasons_are_not_reported_as_pending(): void {
		$shift = $this->create_shift( '2025-03-01 10:00:00' );

		$this->assertNull( ShiftSignupWindow::opens_at( $shift, $this->at( '2026-09-01 12:00:00' ) ) );
	}

	/** A dienst spanning New Year takes its start date, so it is first-half. */
	public function test_new_years_eve_dienst_counts_as_first_half(): void {
		$shift = $this->create_shift( '2026-12-31 20:00:00' );

		$this->assertTrue( ShiftSignupWindow::is_open( $shift, $this->at( '2026-09-01 12:00:00' ) ) );
	}

	public function test_shift_without_a_start_is_never_considered_pending(): void {
		$shift = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
			]
		);

		$this->assertNull( ShiftSignupWindow::opens_at( $shift ) );
	}
}
