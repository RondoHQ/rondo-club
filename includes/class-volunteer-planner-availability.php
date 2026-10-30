<?php
/** Availability shared by proposal generation, editing and publication. */
namespace Rondo\Volunteer;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\Teams\TeamMatches;
use Rondo\Training\Schedules;
final class PlannerAvailability {
	private array $teams               = [];
	private array $feeds               = [];
	private ?array $training           = null;
	private array $training_attendance = [];
	public static function interval( int $id ): ?array {
		try {
			$start = new \DateTimeImmutable( (string) get_post_meta( $id, 'start_datetime', true ), wp_timezone() );
			$end   = new \DateTimeImmutable( (string) get_post_meta( $id, 'end_datetime', true ), wp_timezone() );
			return get_post_meta( $id, 'start_datetime', true ) && get_post_meta( $id, 'end_datetime', true ) && $end > $start ? [ $start, $end ] : null;
		}
		catch ( \Exception $e ) {
			return null;
		}
	}
	public static function overlaps( array $a, array $b ): bool {
		return $a[0] < $b[1] && $a[1] > $b[0];
	}
	private function player_teams( int $person ): array {
		if ( ! isset( $this->teams[ $person ] ) ) {
			$teams = [];
			foreach ( Fields::get_for_post( $person, 'work_history' ) ?: [] as $role ) {
				if ( VolunteerStatus::is_position_current( $role ) && in_array( mb_strtolower( trim( $role['job_title'] ?? '' ) ), array_map( 'mb_strtolower', VolunteerStatus::get_player_roles() ), true ) && get_post_type( (int) ( $role['team'] ?? 0 ) ) === 'team' ) {
					$teams[] = (int) $role['team'];
				}
			}
			$this->teams[ $person ] = array_unique( $teams );
		}
		return $this->teams[ $person ];
	}
	/** Unknown fixtures reserve the day; an unavailable source never means free. */
	public function reason( int $person, array $interval, array $busy ): ?string {
		foreach ( $busy[ $person ] ?? [] as $other ) {
			if ( self::overlaps( $interval, $other ) ) {
				return 'Overlapt met een andere dienst of een ander voorstel';
			}
		}
		$teams = $this->player_teams( $person );
		if ( ! $teams ) {
			$age = ( new VolunteerEligibilityService() )->age_group_number( $person );
			return $age !== null && $age >= 17 ? 'Actuele teamindeling ontbreekt' : null;
		}
		if ( $this->training === null ) {
			$id                        = (int) get_option( Schedules::ACTIVE, 0 );
			$training                  = $id ? Schedules::schedule( $id ) : [];
			$this->training            = is_wp_error( $training ) ? [] : $training;
			$this->training_attendance = $id && ! is_wp_error( $training ) ? \Rondo\Training\Occurrences::states( $id ) : [];
		}
		$season        = \Rondo\Fees\SeasonKey::current( $interval[0]->format( 'Y-m-d' ) );
		$year          = (int) substr( $season, 0, 4 );
		$valid_seasons = [ $season, $year . '/' . substr( (string) ( $year + 1 ), -2 ), $year . '/' . ( $year + 1 ) ];
		if ( empty( $this->training['blocks'] ) || ! in_array( $this->training['season'] ?? '', $valid_seasons, true ) ) {
			return 'Actueel trainingsschema ontbreekt';
		}
		foreach ( $teams as $team ) {
			$has_training = false;
			foreach ( $this->training['blocks'] as $block ) {
				if ( ! in_array( $team, $block['team_ids'] ?? [], true ) ) {
					continue;
				}
				$has_training = true;
				for ( $day = $interval[0]->setTime( 0, 0 ); $day < $interval[1]; $day = $day->modify( '+1 day' ) ) {
					if ( (int) $day->format( 'N' ) !== (int) $block['day'] || \Rondo\Training\Occurrences::state( $this->training_attendance, $day->format( 'Y-m-d' ), $block['block_id'], $team )['cancelled'] ) {
						continue;
					}
					$start = new \DateTimeImmutable( $day->format( 'Y-m-d' ) . ' ' . $block['start'], wp_timezone() );
					if ( self::overlaps( $interval, [ $start->modify( '-30 minutes' ), $start->modify( '+' . ( (int) $block['duration'] + 30 ) . ' minutes' ) ] ) ) {
						return 'Eigen training, inclusief 30 minuten marge';
					}
				}
			}
			if ( ! $has_training ) {
				return 'Trainingstijden van het team ontbreken';
			}
			if ( ! array_key_exists( $team, $this->feeds ) ) {
				$this->feeds[ $team ] = ( new TeamMatches() )->get_feed( $team );
			}
			$feed = $this->feeds[ $team ];
			if ( is_wp_error( $feed ) || empty( $feed['matched'] ) || ! empty( $feed['stale'] ) || ( $feed['season'] ?? '' ) !== $season ) {
				return 'Actueel wedstrijdprogramma ontbreekt';
			}
			foreach ( $feed['matches'] ?? [] as $match ) {
				if ( ! empty( $match['cancelled'] ) ) {
					continue;
				}
				$start = new \DateTimeImmutable( $match['starts_at'], wp_timezone() );
				if ( empty( $match['time_known'] ) || empty( $match['duration_minutes'] ) ) {
					$span = [ $start->setTime( 0, 0 ), $start->setTime( 0, 0 )->modify( '+1 day' ) ];
				}
				else {
					$span = [ $start->modify( '-2 hours' ), $start->modify( '+' . ( (int) $match['duration_minutes'] + 120 ) . ' minutes' ) ];
				}
				if ( self::overlaps( $interval, $span ) ) {
					return 'Eigen wedstrijd, inclusief twee uur marge';
				}
			}
			foreach ( $feed['matchdays'] ?? [] as $day ) {
				if ( empty( $day['cancelled'] ) && self::overlaps( $interval, [ new \DateTimeImmutable( $day['date'], wp_timezone() ), new \DateTimeImmutable( $day['end_date'], wp_timezone() ) ] ) ) {
					return 'Voorlopige wedstrijddag; aanvangstijd nog onbekend';
				}
			}
		}
		return null;
	}
}
