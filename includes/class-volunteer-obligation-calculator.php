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
 *                AND person was not marked no-show
 *   no_show   → shift.status = 'voltooid' AND a `_no_show_{person_id}` meta exists
 *   pending   → shift.start_datetime ≥ now() AND person is in assigned_persons
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

		self::flush_cache_for_person( $person_id );
		return true;
	}

	/**
	 * Reverse a previously-recorded no-show marker.
	 */
	public static function unmark_no_show( int $shift_id, int $person_id ): bool {
		$deleted = delete_post_meta( $shift_id, self::NO_SHOW_META_PREFIX . $person_id );
		if ( $deleted ) {
			self::flush_cache_for_person( $person_id );
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
			// Wipe ALL caches — cheap enough at our scale and a missed flush would silently lie.
			global $wpdb;
			$wpdb->query(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE '\\_transient\\_rondo_vobligation_%'
				    OR option_name LIKE '\\_transient\\_timeout\\_rondo_vobligation_%'"
			);
		}

		return $count;
	}

	/**
	 * Wipe all obligation-cache transients touching this person.
	 * Pragmatic: full-table delete is cheaper than indexing per-unit reverse maps.
	 */
	private static function flush_cache_for_person( int $person_id ): void {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\\_transient\\_rondo_vobligation_%'
			    OR option_name LIKE '\\_transient\\_timeout\\_rondo_vobligation_%'"
		);
	}

	/**
	 * Heart of the calculator — actually walks `dienst_shift` posts and counts.
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

		$completed = 0;
		$pending   = 0;
		$no_show   = 0;
		$now_ts    = time();

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
				$marked_no_show = (bool) get_post_meta( $shift_id, self::NO_SHOW_META_PREFIX . $pid, true );

				if ( $status === 'voltooid' ) {
					if ( $marked_no_show ) {
						++$no_show;
					} else {
						++$completed;
					}
					continue;
				}

				if ( $status === 'geannuleerd' ) {
					continue;
				}

				if ( $start !== '' && strtotime( $start ) >= $now_ts ) {
					++$pending;
				}
			}
		}

		return [
			'completed_count' => $completed,
			'pending_count'   => $pending,
			'no_show_count'   => $no_show,
		];
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
