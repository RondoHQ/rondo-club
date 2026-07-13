<?php
/**
 * VolunteerObligationCalculator
 *
 * Per-unit counter that turns the eligibility view (#1) and the exemption
 * resolver (#16) into a concrete (`required` / `completed` / `pending` /
 * `no_show`) tally per season.
 *
 * Source of truth: `dienst_shift` posts with `assigned_persons` post_meta
 * containing one or more person IDs from the unit.
 *
 *   completed → shift.status = 'voltooid' AND person is in assigned_persons
 *                AND person was not marked no-show; or a cancelled shift whose
 *                cancellation was recorded less than 48 hours before start
 *   no_show   → shift.status = 'voltooid' AND a `_no_show_{person_id}` meta exists
 *   pending   → shift.start_datetime ≥ now() AND person is in assigned_persons
 *
 * Each shift is attributed to exactly ONE unit. A person who carries both a
 * spelersplicht and a gezinsplicht fills the speler duty first; only the surplus
 * counts toward the gezin. A player with no youth children has nowhere to spill,
 * so their speler unit keeps every shift and extra work is never dropped from the
 * club's totals. No unit reference is stored on the shift — the order is fixed and
 * the speler duty is per-person, so the split is derivable.
 *
 * The `required_count` field is the value already computed by
 * VolunteerEligibilityService (it already applies the multi-child rule); we
 * don't recompute it here, we just enrich every unit with progress numbers.
 *
 * Pro-rato regel (bestuursbesluit 2026-05-26): lid sinds vóór 1 januari =
 * volledige plicht, lid sinds 1 januari of later = halve plicht (afgerond
 * naar boven: 1 dienst). Applied per individual member; for gezin units the
 * highest-required child determines the gezin's prorata factor.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Fees\SeasonKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VolunteerObligationCalculator {

	const NO_SHOW_META_PREFIX  = '_no_show_';
	const NO_SHOW_WINDOW_HOURS = 72;
	const CACHE_TTL_SECONDS    = 5 * MINUTE_IN_SECONDS;

	/**
	 * Shared eligibility service, used to resolve each person's own speler duty
	 * when attributing shifts. Lazily created; see eligibility().
	 *
	 * @var VolunteerEligibilityService|null
	 */
	private ?VolunteerEligibilityService $eligibility = null;

	/**
	 * Enrich a list of eligible units with progress numbers.
	 *
	 * @param array  $units  Units from VolunteerEligibilityService::get_eligible_units().
	 * @param string $season KNVB season key (e.g. "2026-2027").
	 * @return array Same units, each augmented with completed_count, pending_count, no_show_count.
	 */
	public function decorate_units( array $units, string $season ): array {
		foreach ( $units as &$unit ) {
			$progress                = $this->progress_for_unit( $unit, $season );
			$unit['completed_count'] = $progress['completed_count'];
			$unit['pending_count']   = $progress['pending_count'];
			$unit['no_show_count']   = $progress['no_show_count'];
			$unit['remaining']       = max( 0, $unit['required_count'] - $progress['completed_count'] );
			$unit['status']          = $this->unit_status( $unit );
		}
		unset( $unit );
		return $units;
	}

	/**
	 * Compute aggregate dashboard stats over a list of decorated units.
	 *
	 * @param array $units Decorated units from decorate_units().
	 * @return array{
	 *   total_units: int,
	 *   total_required: int,
	 *   total_completed: int,
	 *   total_no_show: int,
	 *   total_pending: int,
	 *   units_voldaan: int,
	 *   units_op_weg: int,
	 *   units_geen_actie: int,
	 *   units_risico: int,
	 * }
	 */
	public function aggregate( array $units ): array {
		$stats = [
			'total_units'      => count( $units ),
			'total_required'   => 0,
			'total_completed'  => 0,
			'total_no_show'    => 0,
			'total_pending'    => 0,
			'units_voldaan'    => 0,
			'units_op_weg'     => 0,
			'units_geen_actie' => 0,
			'units_risico'     => 0,
		];

		foreach ( $units as $unit ) {
			$stats['total_required']  += $unit['required_count'] ?? 0;
			$stats['total_completed'] += $unit['completed_count'] ?? 0;
			$stats['total_no_show']   += $unit['no_show_count'] ?? 0;
			$stats['total_pending']   += $unit['pending_count'] ?? 0;

			switch ( $unit['status'] ?? 'geen-actie' ) {
				case 'voldaan':
					++$stats['units_voldaan'];
					break;
				case 'op-weg':
					++$stats['units_op_weg'];
					break;
				case 'risico':
					++$stats['units_risico'];
					break;
				default:
					++$stats['units_geen_actie'];
			}
		}

		return $stats;
	}

	/**
	 * Compute progress numbers for a single unit, using transient cache.
	 *
	 * @return array{completed_count: int, pending_count: int, no_show_count: int}
	 */
	public function progress_for_unit( array $unit, string $season ): array {
		$cache_key = 'rondo_vobligation_' . md5( $unit['unit_id'] . '|' . $season );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->compute_progress( $unit, $season );
		set_transient( $cache_key, $result, self::CACHE_TTL_SECONDS );
		return $result;
	}

	/**
	 * Mark a single person as a no-show on a specific shift.
	 *
	 * Only valid within {@see NO_SHOW_WINDOW_HOURS} after the shift's end_datetime.
	 *
	 * @return true|\WP_Error
	 */
	public static function mark_no_show( int $shift_id, int $person_id, ?int $marked_by_user = null ) {
		$shift = get_post( $shift_id );
		if ( ! $shift || $shift->post_type !== 'dienst_shift' ) {
			return new \WP_Error( 'invalid_shift', 'Invalid shift ID.', [ 'status' => 404 ] );
		}

		$end_datetime = get_post_meta( $shift_id, 'end_datetime', true );
		if ( ! $end_datetime ) {
			return new \WP_Error( 'no_end_datetime', 'Shift has no end_datetime.', [ 'status' => 400 ] );
		}

		$end_ts = strtotime( $end_datetime );
		$cutoff = $end_ts + ( self::NO_SHOW_WINDOW_HOURS * HOUR_IN_SECONDS );
		$now    = time();
		if ( $end_ts === false || $now > $cutoff ) {
			return new \WP_Error(
				'window_expired',
				sprintf( 'No-show window closed (>%dh past shift end).', self::NO_SHOW_WINDOW_HOURS ),
				[ 'status' => 410 ]
			);
		}

		$assigned = get_post_meta( $shift_id, 'assigned_persons', true );
		if ( ! is_array( $assigned ) || ! in_array( $person_id, array_map( 'intval', $assigned ), true ) ) {
			return new \WP_Error(
				'person_not_assigned',
				'Person is not assigned to this shift.',
				[ 'status' => 400 ]
			);
		}

		update_post_meta(
			$shift_id,
			self::NO_SHOW_META_PREFIX . $person_id,
			[
				'marked_at'      => gmdate( 'c' ),
				'marked_by_user' => $marked_by_user ?: get_current_user_id(),
			]
		);

		self::invalidate_cache();
		return true;
	}

	/**
	 * Reverse a previously-recorded no-show marker.
	 */
	public static function unmark_no_show( int $shift_id, int $person_id ): bool {
		$deleted = delete_post_meta( $shift_id, self::NO_SHOW_META_PREFIX . $person_id );
		if ( $deleted ) {
			self::invalidate_cache();
		}
		return (bool) $deleted;
	}

	/**
	 * Auto-complete shifts whose end_datetime is at least one hour in the past.
	 * Idempotent. Returns the number of shifts transitioned.
	 */
	public static function auto_complete_shifts(): int {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'     => 'end_datetime',
						'value'   => $cutoff,
						'compare' => '<',
						'type'    => 'DATETIME',
					],
					[
						'key'     => 'status',
						'value'   => [ 'open', 'vol' ],
						'compare' => 'IN',
					],
				],
			]
		);

		$count = 0;
		foreach ( $query->posts as $shift_id ) {
			update_post_meta( $shift_id, 'status', 'voltooid' );
			++$count;
		}

		if ( $count > 0 ) {
			self::invalidate_cache();
		}

		return $count;
	}

	/**
	 * Wipe all obligation-cache transients.
	 * Pragmatic: full-table delete is cheaper than indexing per-unit reverse maps.
	 */
	public static function invalidate_cache(): void {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\\_transient\\_rondo_vobligation_%'
			    OR option_name LIKE '\\_transient\\_timeout\\_rondo_vobligation_%'"
		);
	}

	/**
	 * Heart of the calculator — actually walks `dienst_shift` posts and counts.
	 *
	 * Every shift is attributed to exactly ONE unit. A person who owes both a
	 * spelersplicht and a gezinsplicht fills the speler duty first; the surplus
	 * spills into the gezin. Without this a playing parent who owes 2 + 3 = 5
	 * would be done after 3 shifts, because each shift counted twice.
	 */
	private function compute_progress( array $unit, string $season ): array {
		$person_ids = array_map( 'intval', $unit['person_ids'] ?? [] );
		if ( empty( $person_ids ) ) {
			return [
				'completed_count' => 0,
				'pending_count'   => 0,
				'no_show_count'   => 0,
			];
		}

		$tallies = $this->tally_per_person( $person_ids, $season );

		if ( ( $unit['kind'] ?? '' ) === VolunteerEligibilityService::UNIT_KIND_SPELER ) {
			return $this->speler_share( $person_ids[0], $tallies[ $person_ids[0] ], (int) ( $unit['required_count'] ?? 0 ) );
		}

		return $this->gezin_share( $person_ids, $tallies );
	}

	/**
	 * Count each person's own completed / pending / no-show shifts for the season.
	 *
	 * @param int[]  $person_ids Persons to tally.
	 * @param string $season     KNVB season key.
	 * @return array<int, array{completed: int, pending: int, no_show: int}> Keyed by person ID.
	 */
	private function tally_per_person( array $person_ids, string $season ): array {
		$tallies = [];
		foreach ( $person_ids as $pid ) {
			$tallies[ $pid ] = [
				'completed' => 0,
				'pending'   => 0,
				'no_show'   => 0,
			];
		}

		[ $season_start, $season_end ] = self::season_range( $season );

		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
				'meta_query'       => [
					[
						'key'     => 'start_datetime',
						'value'   => [ $season_start, $season_end ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
				],
			]
		);

		$now_ts = time();

		foreach ( $query->posts as $shift_id ) {
			$assigned = (array) get_post_meta( $shift_id, 'assigned_persons', true );
			$assigned = array_map( 'intval', $assigned );

			$overlap = array_intersect( $assigned, $person_ids );
			if ( empty( $overlap ) ) {
				continue;
			}

			$status = (string) get_post_meta( $shift_id, 'status', true );
			$start  = (string) get_post_meta( $shift_id, 'start_datetime', true );

			foreach ( $overlap as $pid ) {
				if ( $status === 'voltooid' ) {
					$marked_no_show = (bool) get_post_meta( $shift_id, self::NO_SHOW_META_PREFIX . $pid, true );
					if ( $marked_no_show ) {
						++$tallies[ $pid ]['no_show'];
					} else {
						++$tallies[ $pid ]['completed'];
					}
					continue;
				}

				if ( $status === 'geannuleerd' ) {
					if ( (bool) get_post_meta( $shift_id, ShiftCancellationService::META_CREDIT, true ) ) {
						++$tallies[ $pid ]['completed'];
					}
					continue;
				}

				if ( $start !== '' && strtotime( $start ) >= $now_ts ) {
					++$tallies[ $pid ]['pending'];
				}
			}
		}

		return $tallies;
	}

	/**
	 * The share of a person's shifts that discharges their own spelersplicht.
	 *
	 * Capped at `required` only when the person also carries a gezinsplicht for the
	 * surplus to spill into. A player without youth children keeps every shift here,
	 * so extra work is never silently dropped from the club's totals.
	 *
	 * @param array{completed: int, pending: int, no_show: int} $tally The person's own shifts.
	 */
	private function speler_share( int $person_id, array $tally, int $required ): array {
		if ( ! $this->has_gezin_duty( $person_id ) ) {
			return [
				'completed_count' => $tally['completed'],
				'pending_count'   => $tally['pending'],
				'no_show_count'   => $tally['no_show'],
			];
		}

		$completed = min( $tally['completed'], $required );
		$pending   = min( $tally['pending'], max( 0, $required - $completed ) );

		return [
			'completed_count' => $completed,
			'pending_count'   => $pending,
			// A no-show is a personal failure, counted once, on the person's own duty.
			'no_show_count'   => $tally['no_show'],
		];
	}

	/**
	 * The gezin's progress: whatever each member did beyond their own spelersplicht.
	 *
	 * Children and non-playing parents owe no speler duty, so every shift they do
	 * lands here.
	 *
	 * @param int[]                                                     $person_ids Parents plus children.
	 * @param array<int, array{completed: int, pending: int, no_show: int}> $tallies    Per-person shifts.
	 */
	private function gezin_share( array $person_ids, array $tallies ): array {
		$completed = 0;
		$pending   = 0;
		$no_show   = 0;

		foreach ( $person_ids as $pid ) {
			$tally         = $tallies[ $pid ];
			$speler_needs  = $this->speler_required_for( $pid );
			$speler_took_c = min( $tally['completed'], $speler_needs );
			$speler_took_p = min( $tally['pending'], max( 0, $speler_needs - $speler_took_c ) );

			$completed += $tally['completed'] - $speler_took_c;
			$pending   += $tally['pending'] - $speler_took_p;

			// Members with a speler unit already report their no-shows there.
			if ( $speler_needs === 0 ) {
				$no_show += $tally['no_show'];
			}
		}

		return [
			'completed_count' => $completed,
			'pending_count'   => $pending,
			'no_show_count'   => $no_show,
		];
	}

	/**
	 * How many shifts this person's own spelersplicht consumes before any spill.
	 * Zero for children and for adults who do not play.
	 */
	private function speler_required_for( int $person_id ): int {
		$age = $this->eligibility()->age_group_number( $person_id );

		return ( $age !== null && $age >= VolunteerEligibilityService::ADULT_MIN_AGE )
			? VolunteerEligibilityService::BASE_OBLIGATION
			: 0;
	}

	/**
	 * Does this person also carry a gezinsplicht that surplus shifts can spill into?
	 */
	private function has_gezin_duty( int $person_id ): bool {
		return ! empty( $this->eligibility()->find_youth_children( $person_id ) );
	}

	/**
	 * Lazily instantiated eligibility service, shared across one calculator run.
	 */
	private function eligibility(): VolunteerEligibilityService {
		if ( $this->eligibility === null ) {
			$this->eligibility = new VolunteerEligibilityService();
		}
		return $this->eligibility;
	}

	/**
	 * Bucket a decorated unit into one of: voldaan / op-weg / risico / geen-actie.
	 */
	private function unit_status( array $unit ): string {
		$required = (int) ( $unit['required_count'] ?? 0 );
		$done     = (int) ( $unit['completed_count'] ?? 0 );
		$pending  = (int) ( $unit['pending_count'] ?? 0 );

		if ( $required <= 0 ) {
			return 'voldaan';
		}

		if ( $done >= $required ) {
			return 'voldaan';
		}

		if ( $done + $pending >= $required ) {
			return 'op-weg';
		}

		if ( $done === 0 && $pending === 0 ) {
			return 'risico';
		}

		return 'geen-actie';
	}

	/**
	 * Translate a season key like "2026-2027" into ['2026-07-01 00:00:00', '2027-06-30 23:59:59'].
	 */
	private static function season_range( string $season ): array {
		if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $m ) ) {
			$current = SeasonKey::current();
			preg_match( '/^(\d{4})-(\d{4})$/', $current, $m );
		}
		$start = $m[1] . '-07-01 00:00:00';
		$end   = $m[2] . '-06-30 23:59:59';
		return [ $start, $end ];
	}
}
