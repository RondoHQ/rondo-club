<?php

namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;
use Rondo\Collaboration\Reminders;

/**
 * Tests for birthday reminders across both stored date shapes.
 *
 * Birthdates live in the database in two shapes: the canonical compact 'Ymd' that the field
 * registry writes, and legacy dashed 'Y-m-d' rows that predate it. Both must surface on the
 * dashboard and in the weekly digest. A previous SQL filter accepted only the dashed shape and
 * silently dropped every canonically stored person.
 */
class BirthdayRemindersTest extends RondoTestCase {

	/**
	 * Reminders instance under test.
	 *
	 * @var Reminders
	 */
	private Reminders $reminders;

	/**
	 * User the digest is generated for.
	 *
	 * The weekly digest is visibility-scoped, and a plain rondo_user sees only their own household.
	 * These tests are about date parsing, not access control, so they use a management-level user
	 * that can reach every person.
	 *
	 * @var int
	 */
	private int $user_id;

	protected function set_up(): void {
		parent::set_up();

		$this->reminders = new Reminders();
		$this->user_id   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->user_id );
	}

	/**
	 * Build a birthdate falling on the given offset from today, in the requested storage shape.
	 *
	 * @param int    $days_from_today Offset in days.
	 * @param string $format          Storage format ('Ymd' or 'Y-m-d').
	 * @param int    $years_ago       How many years back to place the birth year.
	 * @return string
	 */
	private function birthdateFor( int $days_from_today, string $format, int $years_ago = 30 ): string {
		$date = ( new \DateTime( 'today', wp_timezone() ) )
			->modify( "+{$days_from_today} days" )
			->modify( "-{$years_ago} years" );

		return $date->format( $format );
	}

	/**
	 * Create a person whose birthdate meta is written verbatim, bypassing field normalization.
	 *
	 * The Fields layer rewrites every date to the canonical compact shape, so legacy dashed rows
	 * can only be reproduced by writing post meta directly.
	 *
	 * @param string $title     Person name.
	 * @param string $birthdate Raw meta value.
	 * @return int
	 */
	private function createPersonWithRawBirthdate( string $title, string $birthdate ): int {
		$person_id = $this->createPerson( [ 'post_title' => $title ] );
		update_post_meta( $person_id, 'birthdate', $birthdate );

		return $person_id;
	}

	/**
	 * Extract person IDs from a reminder list.
	 *
	 * @param array $reminders Reminder rows.
	 * @return int[]
	 */
	private function idsIn( array $reminders ): array {
		return array_map( 'intval', wp_list_pluck( $reminders, 'id' ) );
	}

	/**
	 * Both stored shapes must appear on the dashboard when the birthday is today.
	 */
	public function test_upcoming_reminders_includes_both_stored_date_shapes(): void {
		$compact_id = $this->createPersonWithRawBirthdate( 'Compact Person', $this->birthdateFor( 0, 'Ymd' ) );
		$dashed_id  = $this->createPersonWithRawBirthdate( 'Dashed Person', $this->birthdateFor( 0, 'Y-m-d' ) );

		$ids = $this->idsIn( $this->reminders->get_upcoming_reminders( 14 ) );

		$this->assertContains(
			$compact_id,
			$ids,
			'A birthdate stored in the canonical compact Ymd shape must surface on the dashboard'
		);
		$this->assertContains(
			$dashed_id,
			$ids,
			'A legacy dashed Y-m-d birthdate must keep surfacing on the dashboard'
		);
	}

	/**
	 * The computed next occurrence must be today for a birthday that falls today.
	 */
	public function test_upcoming_reminders_resolves_next_occurrence_for_compact_dates(): void {
		$person_id = $this->createPersonWithRawBirthdate( 'Compact Person', $this->birthdateFor( 0, 'Ymd' ) );

		$reminders = $this->reminders->get_upcoming_reminders( 14 );
		$match     = null;
		foreach ( $reminders as $reminder ) {
			if ( (int) $reminder['id'] === $person_id ) {
				$match = $reminder;
				break;
			}
		}

		$this->assertNotNull( $match, 'The compact-format person should be present' );
		$this->assertSame(
			( new \DateTime( 'today', wp_timezone() ) )->format( 'Y-m-d' ),
			$match['next_occurrence'],
			'A birthday falling today should resolve to today, not next year'
		);
		$this->assertSame( 0, $match['days_until'] );
		$this->assertSame(
			$this->birthdateFor( 0, 'Y-m-d' ),
			$match['date_value'],
			'date_value is a REST-facing date and must be canonical Y-m-d even when stored compact — new Date("20110802") crashes the dashboard'
		);
	}

	/**
	 * Birthdays beyond the requested window stay out of the results.
	 */
	public function test_upcoming_reminders_respects_window(): void {
		$inside_id  = $this->createPersonWithRawBirthdate( 'Inside Window', $this->birthdateFor( 3, 'Ymd' ) );
		$outside_id = $this->createPersonWithRawBirthdate( 'Outside Window', $this->birthdateFor( 40, 'Ymd' ) );

		$ids = $this->idsIn( $this->reminders->get_upcoming_reminders( 14 ) );

		$this->assertContains( $inside_id, $ids );
		$this->assertNotContains( $outside_id, $ids, 'A birthday 40 days out must not appear in a 14-day window' );
	}

	/**
	 * Former members are excluded regardless of stored date shape.
	 */
	public function test_upcoming_reminders_excludes_former_members(): void {
		$person_id = $this->createPersonWithRawBirthdate( 'Former Member', $this->birthdateFor( 0, 'Ymd' ) );
		update_post_meta( $person_id, 'former_member', '1' );

		$ids = $this->idsIn( $this->reminders->get_upcoming_reminders( 14 ) );

		$this->assertNotContains( $person_id, $ids, 'Former members must stay out of birthday reminders' );
	}

	/**
	 * Unparseable birthdate values must not blow up or leak into the results.
	 *
	 * The impossible calendar dates matter specifically: 29 February falls back to 1 March, and
	 * that fallback must not swallow other invalid month/day pairs and turn them into birthdays.
	 */
	public function test_upcoming_reminders_ignores_malformed_birthdates(): void {
		$ignored = [
			'No Birthdate'      => '',
			'Garbage Birthdate' => 'not-a-date',
			'Thirtieth Of Feb'  => '20080230',
			'Thirteenth Month'  => '20081332',
		];

		$expected_absent = [];
		foreach ( $ignored as $title => $birthdate ) {
			$expected_absent[ $title ] = $this->createPersonWithRawBirthdate( $title, $birthdate );
		}

		$ids = $this->idsIn( $this->reminders->get_upcoming_reminders( 400 ) );

		foreach ( $expected_absent as $title => $person_id ) {
			$this->assertNotContains( $person_id, $ids, "{$title} must not appear as a birthday" );
		}
	}

	/**
	 * The next occurrence of a 29 February birthday, resolved independently of the query.
	 *
	 * Leap years keep 29 February; common years fall back to 1 March. The first of this year and
	 * next year that has not yet passed is the next occurrence.
	 *
	 * @return string
	 */
	private function expectedLeapDayOccurrence(): string {
		$today        = new \DateTime( 'today', wp_timezone() );
		$current_year = (int) $today->format( 'Y' );

		foreach ( [ $current_year, $current_year + 1 ] as $year ) {
			$is_leap    = (bool) ( new \DateTime( "{$year}-01-01", wp_timezone() ) )->format( 'L' );
			$occurrence = new \DateTime( $is_leap ? "{$year}-02-29" : "{$year}-03-01", wp_timezone() );

			if ( $occurrence >= $today ) {
				return $occurrence->format( 'Y-m-d' );
			}
		}

		$this->fail( 'A 29 February birthday must occur within the next two years' );
	}

	/**
	 * A 29 February birthday must surface, falling back to 1 March in common years.
	 *
	 * Previously it resolved to NULL in every common year and vanished from the dashboard
	 * entirely, three years out of four.
	 */
	public function test_upcoming_reminders_resolves_leap_day_birthdays(): void {
		$person_id = $this->createPersonWithRawBirthdate( 'Leap Day Person', '20080229' );

		// A 400-day window always contains the next occurrence, whichever year it lands in.
		$reminders = $this->reminders->get_upcoming_reminders( 400 );
		$match     = null;
		foreach ( $reminders as $reminder ) {
			if ( (int) $reminder['id'] === $person_id ) {
				$match = $reminder;
				break;
			}
		}

		$this->assertNotNull( $match, 'A 29 February birthday must appear in the reminder list' );
		$this->assertSame(
			$this->expectedLeapDayOccurrence(),
			$match['next_occurrence'],
			'A 29 February birthday resolves to 29 February in leap years and 1 March otherwise'
		);
	}

	/**
	 * The PHP occurrence helper, which backs the weekly digest, agrees with the SQL query.
	 *
	 * Both surfaces must place a leap-day birthday on the same date; a disagreement would show the
	 * person on the dashboard and in the digest on different days.
	 */
	public function test_calculate_next_occurrence_matches_query_for_leap_day(): void {
		$next = $this->reminders->calculate_next_occurrence( '20080229', true );

		$this->assertNotNull( $next, 'A 29 February birthdate must resolve to a concrete date' );
		$this->assertSame( $this->expectedLeapDayOccurrence(), $next->format( 'Y-m-d' ) );
	}

	/**
	 * Rolling a passed birthday into next year must keep the original month and day.
	 *
	 * Uses yesterday's month/day, so the "already passed this year" branch is exercised on every
	 * day of the year except when yesterday fell in the previous calendar year.
	 */
	public function test_calculate_next_occurrence_preserves_month_and_day_on_rollover(): void {
		$today     = new \DateTime( 'today', wp_timezone() );
		$yesterday = ( clone $today )->modify( '-1 day' );

		$next = $this->reminders->calculate_next_occurrence( $yesterday->format( 'Ymd' ), true );

		$this->assertNotNull( $next );
		$this->assertSame(
			$yesterday->format( 'm-d' ),
			$next->format( 'm-d' ),
			'Rolling over to next year must not shift the month or day'
		);
		$this->assertGreaterThanOrEqual(
			$today->format( 'Y-m-d' ),
			$next->format( 'Y-m-d' ),
			'The next occurrence must never be in the past'
		);
	}

	/**
	 * The weekly digest buckets both stored shapes into "today".
	 */
	public function test_weekly_digest_includes_both_stored_date_shapes_today(): void {
		$compact_id = $this->createPersonWithRawBirthdate( 'Compact Person', $this->birthdateFor( 0, 'Ymd' ) );
		$dashed_id  = $this->createPersonWithRawBirthdate( 'Dashed Person', $this->birthdateFor( 0, 'Y-m-d' ) );

		$digest = $this->reminders->get_weekly_digest( $this->user_id );
		$ids    = $this->idsIn( $digest['today'] );

		$this->assertContains(
			$compact_id,
			$ids,
			'A canonically stored birthdate must reach the weekly digest'
		);
		$this->assertContains( $dashed_id, $ids, 'A legacy dashed birthdate must keep reaching the weekly digest' );

		foreach ( $digest['today'] as $item ) {
			$this->assertSame(
				$this->birthdateFor( 0, 'Y-m-d' ),
				$item['date_value'],
				'Digest date_value must be canonical Y-m-d regardless of the stored shape'
			);
		}
	}

	/**
	 * The weekly digest still separates tomorrow from today.
	 */
	public function test_weekly_digest_buckets_tomorrow_separately(): void {
		$today_id    = $this->createPersonWithRawBirthdate( 'Today Person', $this->birthdateFor( 0, 'Ymd' ) );
		$tomorrow_id = $this->createPersonWithRawBirthdate( 'Tomorrow Person', $this->birthdateFor( 1, 'Ymd' ) );

		$digest = $this->reminders->get_weekly_digest( $this->user_id );

		$this->assertContains( $today_id, $this->idsIn( $digest['today'] ) );
		$this->assertContains( $tomorrow_id, $this->idsIn( $digest['tomorrow'] ) );
		$this->assertNotContains( $tomorrow_id, $this->idsIn( $digest['today'] ) );
	}
}
