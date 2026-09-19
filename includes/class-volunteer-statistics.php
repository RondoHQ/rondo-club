<?php
/**
 * Aggregated volunteer statistics for the coordinator dashboard.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Core\PostTitle;
use Rondo\Core\WorkHistory;
use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\Users\UserProvisioning;

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
		$partition = VolunteerExemptionResolver::partition_units( ( new VolunteerEligibilityService() )->get_eligible_units( $season ), $season );

		return [
			'season'                   => $season,
			'available_seasons'        => $this->available_seasons( $season ),
			'generated_at'             => $now->format( DATE_ATOM ),
			'summary'                  => $summary,
			'by_task_type'             => $types,
			'signup_trend'             => $trend,
			'account_trend'            => $this->account_trend( $now ),
			'undated_assignments'      => $undated_signups,
			'assignment_distribution'  => $this->assignment_distribution( $assignments_by_person ),
			'obligation_progress'      => $this->obligation_progress( $season, $partition ),
			'by_team'                  => $this->by_team( $partition, $assignments_by_person ),
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
	 * Build the all-time account creation trend from existing site users.
	 *
	 * WordPress stores user_registered in UTC; group days in the site timezone.
	 * Only aggregate counts leave this service, never account identifiers.
	 *
	 * @return array<int, array{date:string,count:int,cumulative:int}>
	 */
	private function account_trend( \DateTimeImmutable $now ): array {
		$daily = [];
		$users = get_users( [ 'fields' => [ 'user_registered' ] ] );
		$utc   = new \DateTimeZone( 'UTC' );

		foreach ( $users as $user ) {
			$value      = (string) $user->user_registered;
			$registered = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, $utc );
			if ( ! $registered || $registered->format( 'Y-m-d H:i:s' ) !== $value || $value === '0000-00-00 00:00:00' || $registered > $now ) {
				continue;
			}

			$date           = wp_date( 'Y-m-d', $registered->getTimestamp() );
			$daily[ $date ] = ( $daily[ $date ] ?? 0 ) + 1;
		}

		return $this->build_trend( $daily );
	}

	/**
	 * Build cumulative trend points.
	 *
	 * @param array<string, int> $daily Daily counts.
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
	private function obligation_progress( string $season, array $partition ): array {
		$active = $partition['active'];
		$exempt = count( $partition['exempt'] );

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
	 * Current team members, account coverage and season duties, counted per member.
	 *
	 * Family requirements and assignments intentionally recur for every triggering
	 * child. These rows must not be added together as unique club-wide totals.
	 *
	 * @param array $partition Active and exempt obligation units.
	 * @param array $assignments_by_person Non-cancelled season assignments by person.
	 * @return array<int, array<string, int|string>>
	 */
	private function by_team( array $partition, array $assignments_by_person ): array {
		$required = [];
		$assigned = [];
		foreach ( [ 'active', 'exempt' ] as $group ) {
			foreach ( $partition[ $group ] as $unit ) {
				$count = array_sum( array_intersect_key( $assignments_by_person, array_flip( $unit['person_ids'] ) ) );
				foreach ( $unit['trigger_person_ids'] as $person_id ) {
					$required[ $person_id ] = ( $required[ $person_id ] ?? 0 ) + ( $group === 'active' ? (int) $unit['required_count'] : 0 );
					$assigned[ $person_id ] = ( $assigned[ $person_id ] ?? 0 ) + $count;
				}
			}
		}

		// Accept both supported account-link directions, but never a deleted user.
		$user_ids = array_map( 'intval', get_users( [ 'fields' => 'ID' ] ) );
		update_meta_cache( 'user', $user_ids );
		$users          = array_fill_keys( $user_ids, true );
		$account_people = [];
		foreach ( $user_ids as $user_id ) {
			$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
			if ( $person_id > 0 ) {
				$account_people[ $person_id ][ $user_id ] = true;
			}
		}

		$teams = [];
		foreach ( get_posts(
			[
				'post_type'        => 'team',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'suppress_filters' => true,
			]
			) as $team ) {
			$teams[ $team->ID ] = [
				'id'               => $team->ID,
				'name'             => PostTitle::plain( $team->ID ),
				'activiteit'       => (string) Fields::get_for_post( $team->ID, 'activiteit' ),
				'people_count'     => 0,
				'account_count'    => 0,
				'required_count'   => 0,
				'assignment_count' => 0,
			];
		}
		$people      = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => 'work_history',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					],
				],
			]
		);
		$eligibility = new VolunteerEligibilityService();
		$today       = current_datetime()->format( 'Ymd' );
		$team_people = [];
		foreach ( $people as $person_id ) {
			if ( ! VolunteerEligibilityService::is_active_member( $person_id ) ) {
				continue;
			}
			$member_teams = [];
			foreach ( Fields::get_for_post( $person_id, 'work_history' ) ?: [] as $position ) {
				$team_id = (int) ( $position['team'] ?? 0 );
				$start   = str_replace( '-', '', (string) ( $position['start_date'] ?? '' ) );
				$end     = str_replace( '-', '', (string) ( $position['end_date'] ?? '' ) );
				if ( ! isset( $teams[ $team_id ] ) || WorkHistory::is_inactive_without_end_date( $position )
					|| ( $start !== '' && $start > $today ) || ( $end !== '' && $end < $today ) ) {
					continue;
				}
				$member_teams[ $team_id ] = true;
			}
			if ( empty( $member_teams ) ) {
				continue;
			}

			$family_ids = $this->valid_person_ids( array_merge( [ $person_id ], $eligibility->find_parents( $person_id ) ) );
			foreach ( $member_teams as $team_id => $_ ) {
				foreach ( $family_ids as $family_id ) {
					$team_people[ $team_id ][ $family_id ] = true;
				}
				$teams[ $team_id ]['required_count']   += $required[ $person_id ] ?? 0;
				$teams[ $team_id ]['assignment_count'] += $assigned[ $person_id ] ?? $assignments_by_person[ $person_id ] ?? 0;
			}
		}
		foreach ( $team_people as $team_id => $members ) {
			$team_accounts = [];
			foreach ( $members as $person_id => $_ ) {
				$team_accounts += $account_people[ $person_id ] ?? [];
				$user_id        = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
				if ( isset( $users[ $user_id ] ) ) {
					$team_accounts[ $user_id ] = true;
				}
			}
			$teams[ $team_id ]['people_count']  = count( $members );
			$teams[ $team_id ]['account_count'] = count( $team_accounts );
		}
		usort( $teams, static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );
		return $teams;
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
