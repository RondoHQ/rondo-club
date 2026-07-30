<?php
/**
 * ShiftSignupWindow
 *
 * Decides when a dienst becomes claimable by members themselves.
 *
 * The club plans a whole season at once, but opening all of it for signup in
 * August means the autumn fills with people who wanted a quiet spring dienst.
 * So the season is opened in two halves: the first half (July–December) is open
 * from the start, the second half (January–June) opens on a configured date —
 * 1 November by default.
 *
 * The split is not configurable and deliberately so: `SeasonKey` already defines
 * a season as 1 July – 30 June, which puts the boundary at 1 January by
 * construction. A second, separately configured notion of "season half" could
 * silently disagree with the obligation calculator that shares the same season.
 *
 * This gates **self-service signup only**. A coordinator assigning someone
 * (REST\MemberShifts::add_assignee) is planning, not racing for a slot, and is
 * never blocked by the window.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Config\ClubConfig;
use Rondo\Fees\SeasonKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ShiftSignupWindow {

	/** Fallback opening moment when the club has not configured one. */
	public const DEFAULT_OPENS = '11-01';

	/**
	 * May this shift be claimed by a member right now?
	 */
	public static function is_open( int $shift_id, ?\DateTimeImmutable $now = null ): bool {
		$opens_at = self::opens_at( $shift_id, $now );

		return $opens_at === null;
	}

	/**
	 * When does this shift open for self-service signup?
	 *
	 * @return \DateTimeImmutable|null Null when it is already open, or when the
	 *                                 shift has no usable start date to judge.
	 */
	public static function opens_at( int $shift_id, ?\DateTimeImmutable $now = null ): ?\DateTimeImmutable {
		$start = self::shift_start( $shift_id );
		if ( $start === null ) {
			return null;
		}

		$now          = $now ?: current_datetime();
		$shift_season = SeasonKey::current( $start->format( 'Y-m-d' ) );

		// Two independent gates, whichever falls later:
		//
		// 1. The shift's own season must have started. This is what keeps a June
		//    rollout of next season out of reach — including its first half,
		//    which would otherwise look open because July–December always is.
		// 2. Second-half diensten additionally wait for the configured date.
		$opens_at = self::season_start( $shift_season );

		if ( (int) $start->format( 'n' ) < 7 ) {
			$second_half_opens = self::second_half_opens( $shift_season );
			if ( $second_half_opens > $opens_at ) {
				$opens_at = $second_half_opens;
			}
		}

		return $now < $opens_at ? $opens_at : null;
	}

	/** 1 July of the season's first calendar year. */
	private static function season_start( string $season ): \DateTimeImmutable {
		return new \DateTimeImmutable(
			self::season_start_year( $season ) . '-07-01 00:00:00',
			wp_timezone()
		);
	}

	/**
	 * The configured moment the second half of a season opens.
	 *
	 * Stored as a month-day so it applies every season without an annual edit;
	 * resolved here against the season's first calendar year, because the
	 * opening always falls in the autumn half.
	 *
	 * @param string $season Season key, "YYYY-YYYY".
	 */
	public static function second_half_opens( string $season ): \DateTimeImmutable {
		$month_day = self::configured_month_day();
		$year      = self::season_start_year( $season );

		$opens_at = \DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$year . '-' . $month_day,
			wp_timezone()
		);

		if ( ! $opens_at ) {
			$opens_at = \DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				$year . '-' . self::DEFAULT_OPENS,
				wp_timezone()
			);
		}

		return $opens_at;
	}

	/**
	 * Validate a month-day setting. Returns null when it is not a real date.
	 */
	public static function sanitize_month_day( string $value ): ?string {
		$value = trim( $value );

		if ( ! preg_match( '/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value ) ) {
			return null;
		}

		// A leap year, so 02-29 is accepted as a legitimate choice.
		return checkdate(
			(int) substr( $value, 0, 2 ),
			(int) substr( $value, 3, 2 ),
			2024
		) ? $value : null;
	}

	/** The club's configured month-day, falling back to the default. */
	private static function configured_month_day(): string {
		return self::sanitize_month_day( ClubConfig::get_volunteer_second_half_opens() )
			?? self::DEFAULT_OPENS;
	}

	private static function season_start_year( string $season ): int {
		return (int) substr( $season, 0, 4 );
	}

	private static function shift_start( int $shift_id ): ?\DateTimeImmutable {
		$start = (string) get_post_meta( $shift_id, 'start_datetime', true );
		if ( $start === '' ) {
			return null;
		}

		try {
			return new \DateTimeImmutable( $start, wp_timezone() );
		} catch ( \Exception $exception ) {
			return null;
		}
	}
}
