<?php
/**
 * Aggregated volunteer statistics for the coordinator dashboard.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Core\PostTitle;
use Rondo\Fees\SeasonKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds privacy-safe season statistics from shifts and obligation units.
 */
final class VolunteerStatistics {
	private const SHORTAGE_WINDOW_DAYS = 30;
	private const SHORTAGE_LIMIT       = 12;

	/**
	 * Build the complete statistics payload for one sports season.
	 *
	 * @param string|null $season Sports season in YYYY-YYYY format.
	 * @return array<string, mixed>
	 */
	public function for_season( ?string $season = null ): array {
		$season = $this->normalize_season( $season );
		$now    = current_datetime();
		$shifts = $this->shift_ids_for_season( $season );

		update_meta_cache( 'post', $shifts );

		$summary               = [
			'total_shifts'          => 0,
			'total_capacity'        => 0,
			'total_assignments'     => 0,
			'completed_assignments' => 0,
			'upcoming_assignments'  => 0,
			'other_assignments'     => 0,
		];
		$type_rows             = [];
		$people                = [];
		$assignments_by_person = [];
		$daily_signups         = [];
		$undated_signups       = 0;
		$shortages             = [];

		foreach ( $shifts as $shift_id ) {
			$shift_id = (int) $shift_id;
			$status   = (string) get_post_meta( $shift_id, 'status', true );
			$status   = $status !== '' ? $status : 'open';
			if ( $status === 'geannuleerd' ) {
				continue;
			}

			$type_id  = (int) get_post_meta( $shift_id, 'dienst_type_id', true );
			$capacity = max( 1, (int) get_post_meta( $shift_id, 'capacity', true ) );
			$assigned = $this->valid_person_ids( ShiftAssignments::person_ids( $shift_id ) );
			$start    = $this->parse_datetime( (string) get_post_meta( $shift_id, 'start_datetime', true ) );

			if ( ! isset( $type_rows[ $type_id ] ) ) {
				$type_rows[ $type_id ] = $this->empty_type_row( $type_id );
			}

			++$summary['total_shifts'];
			$summary['total_capacity'] += $capacity;
			++$type_rows[ $type_id ]['shift_count'];
			$type_rows[ $type_id ]['capacity'] += $capacity;

			foreach ( $assigned as $person_id ) {
				$person_id = (int) $person_id;
				if ( $person_id <= 0 ) {
					continue;
				}

				++$summary['total_assignments'];
				++$type_rows[ $type_id ]['assignments'];
				$type_rows[ $type_id ]['people'][ $person_id ] = true;
				$people[ $person_id ]                          = true;
				$assignments_by_person[ $person_id ]           = ( $assignments_by_person[ $person_id ] ?? 0 ) + 1;

				if ( $status === 'voltooid' ) {
					++$summary['completed_assignments'];
				} elseif ( $start && $start >= $now ) {
					++$summary['upcoming_assignments'];
				} else {
					++$summary['other_assignments'];
				}

				$signup_timestamp = $this->assignment_timestamp( $shift_id, $person_id );
				if ( $signup_timestamp > 0 && $signup_timestamp <= $now->getTimestamp() ) {
					$date                   = wp_date( 'Y-m-d', $signup_timestamp );
					$daily_signups[ $date ] = ( $daily_signups[ $date ] ?? 0 ) + 1;
				} else {
					++$undated_signups;
				}
			}

			$shortage = $this->shortage_row( $shift_id, $type_rows[ $type_id ]['name'], $status, $start, $capacity, count( $assigned ), $now );
			if ( $shortage !== null ) {
				$shortages[] = $shortage;
			}
		}

		$unique_volunteers                            = count( $people );
		$summary['unique_volunteers']                 = $unique_volunteers;
		$summary['fill_rate']                         = $this->percentage( $summary['total_assignments'], $summary['total_capacity'] );
		$summary['average_assignments_per_volunteer'] = $unique_volunteers > 0
			? round( $summary['total_assignments'] / $unique_volunteers, 2 )
			: 0.0;

		$types = $this->finalize_type_rows( $type_rows, $summary['total_assignments'] );
		$trend = $this->build_trend( $daily_signups );
		usort( $shortages, static fn( array $a, array $b ): int => strcmp( $a['start_datetime'], $b['start_datetime'] ) );

		return [
			'season'                   => $season,
			'available_seasons'        => $this->available_seasons( $season ),
			'generated_at'             => $now->format( DATE_ATOM ),
			'summary'                  => $summary,
			'by_task_type'             => $types,
			'signup_trend'             => $trend,
			'undated_assignments'      => $undated_signups,
			'assignment_distribution'  => $this->assignment_distribution( $assignments_by_person ),
			'obligation_progress'      => $this->obligation_progress( $season ),
			'upcoming_shortages'       => array_slice( $shortages, 0, self::SHORTAGE_LIMIT ),
			'upcoming_shortages_total' => count( $shortages ),
			'shortage_window_days'     => self::SHORTAGE_WINDOW_DAYS,
		];
	}

	/**
	 * Normalize an optional season value.
	 */
	private function normalize_season( ?string $season ): string {
		$season = trim( (string) $season );
		if ( preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) && (int) $matches[2] === (int) $matches[1] + 1 ) {
			return $season;
		}

		return SeasonKey::current();
	}

	/**
	 * Return current and previous seasons, plus a requested historical season.
	 *
	 * @return string[]
	 */
	private function available_seasons( string $selected ): array {
		$current = SeasonKey::current();
		$seasons = [ $current, SeasonKey::previous( $current ) ];
		if ( ! in_array( $selected, $seasons, true ) ) {
			$seasons[] = $selected;
		}

		return array_values( array_unique( $seasons ) );
	}

	/**
	 * Query every published shift in a sports season.
	 *
	 * @return int[]
	 */
	private function shift_ids_for_season( string $season ): array {
		$start_year   = (int) substr( $season, 0, 4 );
		$season_start = sprintf( '%04d-07-01 00:00:00', $start_year );
		$season_end   = sprintf( '%04d-06-30 23:59:59', $start_year + 1 );

		return array_map(
			'intval',
			get_posts(
				[
					'post_type'        => 'dienst_shift',
					'post_status'      => [ 'publish' ],
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'meta_query'       => [
						[
							'key'     => 'start_datetime',
							'value'   => [ $season_start, $season_end ],
							'compare' => 'BETWEEN',
							'type'    => 'DATETIME',
						],
					],
				]
			)
		);
	}

	/**
	 * Empty aggregate row for one task type.
	 *
	 * @return array<string, mixed>
	 */
	private function empty_type_row( int $type_id ): array {
		$color = $type_id > 0 ? sanitize_hex_color( (string) get_post_meta( $type_id, 'color', true ) ) : null;

		return [
			'id'          => $type_id,
			'name'        => PostTitle::plain( $type_id, 'Onbekende inschrijftaak' ),
			'color'       => $color ?: '',
			'shift_count' => 0,
			'capacity'    => 0,
			'assignments' => 0,
			'people'      => [],
		];
	}

	/**
	 * Convert internal task-type rows to their REST shape.
	 *
	 * @param array<int, array<string, mixed>> $rows Task-type aggregates.
	 * @return array<int, array<string, mixed>>
	 */
	private function finalize_type_rows( array $rows, int $total_assignments ): array {
		$result = [];
		foreach ( $rows as $row ) {
			$row['unique_volunteers'] = count( $row['people'] );
			$row['fill_rate']         = $this->percentage( $row['assignments'], $row['capacity'] );
			$row['share']             = $this->percentage( $row['assignments'], $total_assignments );
			unset( $row['people'] );
			$result[] = $row;
		}

		usort(
			$result,
			static fn( array $a, array $b ): int => ( $b['assignments'] <=> $a['assignments'] ) ?: strcasecmp( $a['name'], $b['name'] )
		);

		return $result;
	}

	/**
	 * Prefer the member signup timestamp and fall back to coordinator planning.
	 */
	private function assignment_timestamp( int $shift_id, int $person_id ): int {
		$timestamp = (int) get_post_meta( $shift_id, '_shift_signup_at_' . $person_id, true );
		if ( $timestamp > 0 ) {
			return $timestamp;
		}

		return (int) get_post_meta( $shift_id, '_shift_assigned_at_' . $person_id, true );
	}

	/**
	 * Build cumulative signup trend points.
	 *
	 * @param array<string, int> $daily Daily current-signup counts.
	 * @return array<int, array{date:string,count:int,cumulative:int}>
	 */
	private function build_trend( array $daily ): array {
		ksort( $daily );
		$cumulative = 0;
		$result     = [];
		foreach ( $daily as $date => $count ) {
			$cumulative += $count;
			$result[]    = [
				'date'       => $date,
				'count'      => $count,
				'cumulative' => $cumulative,
			];
		}

		return $result;
	}

	/**
	 * Group volunteers by how many current assignments they hold.
	 *
	 * @param array<int, int> $counts Assignment count per person.
	 * @return array{one:int,two:int,three_plus:int}
	 */
	private function assignment_distribution( array $counts ): array {
		$result = [
			'one'        => 0,
			'two'        => 0,
			'three_plus' => 0,
		];
		foreach ( $counts as $count ) {
			if ( $count <= 1 ) {
				++$result['one'];
			} elseif ( $count === 2 ) {
				++$result['two'];
			} else {
				++$result['three_plus'];
			}
		}

		return $result;
	}

	/**
	 * Summarize obligation-unit progress while keeping exemptions separate.
	 *
	 * @return array<string, int>
	 */
	private function obligation_progress( string $season ): array {
		$units  = ( new VolunteerEligibilityService() )->get_eligible_units( $season );
		$active = [];
		$exempt = 0;
		foreach ( $units as $unit ) {
			$unit = $this->sanitize_unit_person_ids( $unit );
			if ( $unit === null ) {
				continue;
			}

			if ( VolunteerExemptionResolver::resolve_unit( $unit, $season ) !== null ) {
				++$exempt;
				continue;
			}
			$active[] = $unit;
		}

		$calculator = new VolunteerObligationCalculator();
		$aggregate  = $calculator->aggregate( $calculator->decorate_units( $active, $season ) );

		return [
			'total_units'     => count( $active ) + $exempt,
			'exempt'          => $exempt,
			'completed'       => (int) $aggregate['units_voldaan'],
			'fully_scheduled' => (int) $aggregate['units_op_weg'],
			'partial'         => (int) $aggregate['units_geen_actie'],
			'not_started'     => (int) $aggregate['units_risico'],
			'total_required'  => (int) $aggregate['total_required'],
			'total_pending'   => (int) $aggregate['total_pending'],
			'total_completed' => (int) $aggregate['total_completed'],
			'total_no_show'   => (int) $aggregate['total_no_show'],
		];
	}

	/**
	 * Keep only existing person posts in a list of relationship IDs.
	 *
	 * Eligibility and assignment transients can briefly retain a deleted person
	 * until their five-minute cache expires. Those IDs are not current links and
	 * must not break or inflate the statistics response.
	 *
	 * @param int[] $person_ids Candidate person IDs.
	 * @return int[]
	 */
	private function valid_person_ids( array $person_ids ): array {
		return array_values(
			array_filter(
				array_map( 'intval', $person_ids ),
				static fn( int $person_id ): bool => $person_id > 0 && get_post_type( $person_id ) === 'person' && get_post_status( $person_id ) !== 'trash'
			)
		);
	}

	/**
	 * Remove stale person references from an eligibility unit.
	 *
	 * @param array<string, mixed> $unit Eligibility unit.
	 * @return array<string, mixed>|null
	 */
	private function sanitize_unit_person_ids( array $unit ): ?array {
		$person_ids = $this->valid_person_ids( (array) ( $unit['person_ids'] ?? [] ) );
		if ( empty( $person_ids ) ) {
			return null;
		}

		$unit['person_ids']         = $person_ids;
		$unit['trigger_person_ids'] = array_values(
			array_intersect(
				$person_ids,
				array_map( 'intval', (array) ( $unit['trigger_person_ids'] ?? [] ) )
			)
		);

		return $unit;
	}

	/**
	 * Return a shortage row for an incompletely staffed shift in the next month.
	 *
	 * @return array<string, mixed>|null
	 */
	private function shortage_row( int $shift_id, string $type_name, string $status, ?\DateTimeImmutable $start, int $capacity, int $assigned, \DateTimeImmutable $now ): ?array {
		if ( ! $start || ! in_array( $status, [ 'open', 'vol' ], true ) || $start < $now || $start > $now->modify( '+' . self::SHORTAGE_WINDOW_DAYS . ' days' ) ) {
			return null;
		}

		$remaining = max( 0, $capacity - $assigned );
		if ( $remaining === 0 ) {
			return null;
		}

		return [
			'id'              => $shift_id,
			'title'           => PostTitle::plain( $shift_id, $type_name ),
			'task_type'       => $type_name,
			'start_datetime'  => $start->format( 'Y-m-d H:i:s' ),
			'capacity'        => $capacity,
			'assigned_count'  => $assigned,
			'spots_remaining' => $remaining,
			'fill_rate'       => $this->percentage( $assigned, $capacity ),
			'days_until'      => max( 0, (int) ceil( ( $start->getTimestamp() - $now->getTimestamp() ) / DAY_IN_SECONDS ) ),
		];
	}

	/**
	 * Parse a WordPress-local stored datetime.
	 */
	private function parse_datetime( string $value ): ?\DateTimeImmutable {
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, wp_timezone() );
		return $date instanceof \DateTimeImmutable ? $date : null;
	}

	/**
	 * Calculate a one-decimal percentage without dividing by zero.
	 */
	private function percentage( int $part, int $whole ): float {
		return $whole > 0 ? round( 100 * $part / $whole, 1 ) : 0.0;
	}
}
