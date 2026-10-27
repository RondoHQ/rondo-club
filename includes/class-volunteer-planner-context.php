<?php
/** A current, shared quota and availability ledger for volunteer proposals. */
namespace Rondo\Volunteer;

use Rondo\Core\AccessControl;
use Rondo\Core\PostTitle;
use Rondo\Fees\SeasonKey;
use Rondo\People\CommunicationPolicy;
final class PlannerContext {
	public array $units          = [];
	public array $slots          = [];
	private array $busy          = [];
	private array $blocks        = [];
	private array $load          = [];
	private array $own_units     = [];
	private array $team_sources  = [];
	private array $pairing_teams = [];
	private array $families      = [];
	private array $companions    = [];
	private PlannerAvailability $availability;
	private \DateTimeImmutable $now;
	public array $reasons = [];
	public function __construct( \DateTimeImmutable $now, int $exclude_plan = 0, ?array $exclude_row = null, ?string $season = null ) {
		$this->now          = $now;
		$this->availability = new PlannerAvailability();
		$season             = $season ?? SeasonKey::current( $now->format( 'Y-m-d' ) );
		$eligibility        = new VolunteerEligibilityService();
		$units              = ( new VolunteerObligationCalculator() )->decorate_units( $eligibility->get_eligible_units( $season ), $season );
		foreach ( $units as $unit ) {
			if ( get_current_user_id() > 0 && array_filter( $unit['person_ids'], static fn( $id ) => ! AccessControl::can_view_person( (int) $id ) ) ) {
				continue;
			}
			// Keep family links for existing assignees too, even after their quota is filled.
			if ( $unit['kind'] === 'gezin' ) {
				foreach ( array_diff( $unit['person_ids'], $unit['trigger_person_ids'] ) as $parent ) {
					$this->team_sources[ $parent ] = array_unique( array_merge( $this->team_sources[ $parent ] ?? [], $unit['trigger_person_ids'] ) );
					$this->families[ $parent ][]   = $unit['unit_id'];
				}
			}
			if ( VolunteerExemptionResolver::resolve_unit( $unit, $season ) !== null ) {
				continue;
			}
			$key                 = hash_hmac( 'sha256', $season . '|' . $unit['unit_id'], wp_salt( 'auth' ) );
			$unit['remaining']   = max( 0, $unit['required_count'] - $unit['completed_count'] - $unit['pending_count'] );
			$unit['half']        = [ 0, 0 ];
			$unit['candidates']  = $unit['kind'] === 'gezin' ? array_values( array_diff( $unit['person_ids'], $unit['trigger_person_ids'] ) ) : $unit['person_ids'];
			$unit['candidates']  = array_values( array_filter( $unit['candidates'], static fn( $id ) => $eligibility->may_volunteer( (int) $id ) && CommunicationPolicy::primary_email( (int) $id ) ) );
			$this->units[ $key ] = $unit;
			if ( $unit['kind'] === 'speler' ) {
				$this->own_units[ (int) $unit['person_ids'][0] ] = $key;
			}
		}
		$year   = (int) substr( $season, 0, 4 );
		$shifts = get_posts(
		[
			'post_type'        => 'dienst_shift',
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'suppress_filters' => true,
			'meta_query'       => [
				[
					'key'     => 'start_datetime',
					'value'   => [ "$year-07-01 00:00:00", ( $year + 1 ) . '-06-30 23:59:59' ],
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				],
			],
		]
		);
		$tokens = [];
		foreach ( $shifts as $shift ) {
			$interval = PlannerAvailability::interval( $shift->ID );
			if ( ! $interval ) {
				continue;
			}
			$status    = get_post_meta( $shift->ID, 'status', true );
			$completed = $status === 'voltooid' || ( $status === 'geannuleerd' && get_post_meta( $shift->ID, ShiftCancellationService::META_CREDIT, true ) );
			$pending   = in_array( $status, [ 'open', 'vol', '' ], true ) && $interval[0] >= $now;
			foreach ( ShiftAssignments::person_ids( $shift->ID ) as $person ) {
				if ( $pending ) {
					$this->busy[ $person ][] = $interval;
				}
				if ( ( $completed && ! get_post_meta( $shift->ID, '_no_show_' . $person, true ) ) || $pending ) {
					$tokens[ $person ][]   = [
						'completed' => $completed,
						'start'     => $interval[0],
						'half'      => (int) $interval[0]->format( 'n' ) >= 7 ? 0 : 1,
					];
					$this->load[ $person ] = ( $this->load[ $person ] ?? 0 ) + 1;
				}
			}
		}
		foreach ( $tokens as $person => $items ) {
			usort( $items, static fn( $a, $b ) => (int) $b['completed'] <=> (int) $a['completed'] ?: $a['start'] <=> $b['start'] );
			$own       = $this->own_units[ $person ] ?? null;
			$own_count = $own ? $this->units[ $own ]['required_count'] : 0;
			foreach ( $items as $index => $item ) {
				if ( $own && $index < $own_count ) {
					++$this->units[ $own ]['half'][ $item['half'] ];
				}
				foreach ( $this->units as $key => $unit ) {
					if ( $unit['kind'] === 'gezin' && in_array( $person, $unit['person_ids'], true ) && $index >= $own_count ) {
						++$this->units[ $key ]['half'][ $item['half'] ];
					}
				}
			}
		}
		foreach ( VolunteerPlanner::plans( true ) as $plan ) {
			if ( $plan->ID === $exclude_plan ) {
				continue;
			}
			$data = get_post_meta( $plan->ID, '_planner_data', true );
			foreach ( $data['rows'] ?? [] as $index => $row ) {
				if ( $exclude_row === [ $plan->ID, $index ] || ( $row['state'] ?? '' ) !== 'pending' || empty( $row['person_id'] ) ) {
					continue;
				}
				$span = PlannerAvailability::interval( (int) $row['shift_id'] );
				if ( in_array( (int) $row['person_id'], ShiftAssignments::person_ids( (int) $row['shift_id'] ), true ) ) {
					continue;
				}
				if ( $span && $span[0] > $now ) {
					$this->reserve( $row, $span );
				}
			}
		}
	}
	public function reserve( array $row, array $interval ): void {
		$key    = $row['unit'];
		$person = (int) $row['person_id'];
		if ( isset( $this->units[ $key ] ) ) {
			--$this->units[ $key ]['remaining'];
			++$this->units[ $key ]['half'][ (int) $interval[0]->format( 'n' ) >= 7 ? 0 : 1 ];
		}
		$this->slots[ $row['shift_id'] ]        = ( $this->slots[ $row['shift_id'] ] ?? 0 ) + 1;
		$this->busy[ $person ][]                = $interval;
		$this->load[ $person ]                  = ( $this->load[ $person ] ?? 0 ) + 1;
		$this->companions[ $row['shift_id'] ][] = $person;
	}

	/** Own teams and, for family duties, the current teams of the children. */
	private function pairing_teams( int $person ): array {
		if ( ! isset( $this->pairing_teams[ $person ] ) ) {
			$teams = [];
			foreach ( array_merge( [ $person ], $this->team_sources[ $person ] ?? [] ) as $source ) {
				if ( get_current_user_id() === 0 || AccessControl::can_view_person( (int) $source ) ) {
					$teams = array_merge( $teams, $this->availability->player_teams( (int) $source ) );
				}
			}
			$this->pairing_teams[ $person ] = array_unique( $teams );
		}
		return $this->pairing_teams[ $person ];
	}

	/** A pair is two different people from different family obligations. */
	private function can_pair( int $a, int $b ): bool {
		return $a !== $b
			&& ! array_intersect( $this->families[ $a ] ?? [], $this->families[ $b ] ?? [] )
			&& (bool) array_intersect( $this->pairing_teams( $a ), $this->pairing_teams( $b ) );
	}

	/** Prefer pairs only within the best fairness tier; never relax candidate rules. */
	public function choose_candidate( int $shift, array $options, int $free_slots ): ?array {
		if ( ! $options ) {
			return null;
		}
		$priority  = array_slice( $options[0]['score'], 0, 3 );
		$peers     = array_values( array_filter( $options, static fn( $option ) => array_slice( $option['score'], 0, 3 ) === $priority ) );
		$unpaired  = [];
		$occupants = array_unique( array_merge( ShiftAssignments::person_ids( $shift ), $this->companions[ $shift ] ?? [] ) );
		foreach ( $occupants as $person ) {
			foreach ( $unpaired as $index => $other ) {
				if ( $this->can_pair( (int) $person, $other ) ) {
					unset( $unpaired[ $index ] );
					continue 2;
				}
			}
			$unpaired[] = (int) $person;
		}
		foreach ( $peers as $option ) {
			foreach ( $unpaired as $other ) {
				if ( $this->can_pair( $option['person_id'], $other ) ) {
					return $option;
				}
			}
		}
		// Look ahead before filling an empty pair, so an unpaired first choice
		// does not prevent two equally eligible teammates from working together.
		if ( $free_slots >= 2 ) {
			$by_team = [];
			foreach ( $peers as $option ) {
				foreach ( $this->pairing_teams( $option['person_id'] ) as $team ) {
					$by_team[ $team ][] = $option['person_id'];
				}
			}
			foreach ( $peers as $option ) {
				foreach ( $this->pairing_teams( $option['person_id'] ) as $team ) {
					foreach ( $by_team[ $team ] as $other ) {
						if ( $this->can_pair( $option['person_id'], $other ) ) {
							return $option;
						}
					}
				}
			}
		}
		return $options[0];
	}
	/** The same candidate rules are enforced on generation, editing and publishing. */
	public function candidates( int $shift, ?int $type = null ): array {
		$this->reasons = [];
		$span          = PlannerAvailability::interval( $shift );
		if ( ! $span || $span[0] <= $this->now ) {
			return [];
		}
		$result = [];
		$half   = (int) $span[0]->format( 'n' ) >= 7 ? 0 : 1;
		foreach ( $this->units as $key => $unit ) {
			if ( $unit['remaining'] <= 0 ) {
				continue;
			}
			foreach ( $unit['candidates'] as $person ) {
				$person = (int) $person;
				$own    = $this->own_units[ $person ] ?? null;
				if ( $unit['kind'] === 'gezin' && $own && $this->units[ $own ]['remaining'] > 0 ) {
					continue;
				}
				if ( in_array( $person, ShiftAssignments::person_ids( $shift ), true ) || get_post_meta( $shift, '_shift_declined_' . $person, true ) ) {
					continue;
				}
				$this->blocks[ $person ] = $this->blocks[ $person ] ?? ShiftSignupEligibility::signup_blocks( $person );
				$reason                  = ShiftSignupEligibility::block_reason( $shift, $person, $this->blocks[ $person ], $type );
				$reason                  = $reason ?: $this->availability->reason( $person, $span, $this->busy );
				if ( $reason ) {
					$this->reasons[ $reason ] = ( $this->reasons[ $reason ] ?? 0 ) + 1;
					continue;
				}
				$goal     = $half === 0 ? (int) ceil( $unit['required_count'] / 2 ) : (int) floor( $unit['required_count'] / 2 );
				$result[] = [
					'person_id' => $person,
					'name'      => PostTitle::plain( $person ),
					'unit'      => $key,
					'remaining' => $unit['remaining'],
					'score'     => [ $unit['half'][ $half ] < $goal ? 0 : 1, ( $unit['required_count'] - $unit['remaining'] ) / max( 1, $unit['required_count'] ), $this->load[ $person ] ?? 0, hash( 'sha256', $this->now->format( 'o-W' ) . '|' . $person ) ],
				];
			}
		}
		usort( $result, static fn( $a, $b ) => $a['score'] <=> $b['score'] );
		return $result;
	}
}
