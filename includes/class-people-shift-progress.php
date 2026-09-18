<?php
/**
 * Season obligation summaries for the restricted People list.
 */

namespace Rondo\Volunteer;

use Rondo\Fees\SeasonKey;

class PeopleShiftProgress {

	/** Only the two explicitly authorized roles may use the list or its filters. */
	public static function can_view(): bool {
		return (bool) array_intersect( [ 'rondo_bestuur', 'rondo_vrijwilligers' ], (array) wp_get_current_user()->roles );
	}

	/** Filter values shared with REST validation. */
	public const STATUSES = [ 'not_started', 'insufficient', 'planned', 'completed', 'exempt' ];

	/** Build all summaries in one batch, reusing the existing attribution rules. */
	public function for_season( ?string $season = null ): array {
		$season = $season ?: SeasonKey::current();
		$units  = ( new VolunteerEligibilityService() )->get_eligible_units( $season );
		$units  = ( new VolunteerObligationCalculator() )->decorate_units( $units, $season );
		$people = [];

		foreach ( $units as $unit ) {
			$exempt = VolunteerExemptionResolver::resolve_unit( $unit, $season ) !== null;
			$ids    = array_map( 'intval', $unit['person_ids'] );
			if ( $unit['kind'] === 'gezin' ) {
				// The parents owe this duty; the children themselves do not.
				$ids = array_diff( $ids, array_map( 'intval', $unit['trigger_person_ids'] ) );
			}
			foreach ( array_unique( $ids ) as $person_id ) {
				$people[ $person_id ][] = array_merge( $unit, [ 'is_exempt' => $exempt ] );
			}
		}

		return array_map( [ $this, 'summarize' ], $people );
	}

	/** Summarize without letting excess work on one duty hide another open duty. */
	public function summarize( array $units ): array {
		$result    = [
			'required'  => 0,
			'completed' => 0,
			'planned'   => 0,
			'family'    => false,
			'status'    => 'exempt',
		];
		$remaining = 0;
		$unplanned = 0;
		foreach ( $units as $unit ) {
			$result['family'] = $result['family'] || $unit['kind'] === 'gezin';
			if ( $unit['is_exempt'] ) {
				continue;
			}
			$required             = (int) $unit['required_count'];
			$completed            = (int) $unit['completed_count'];
			$planned              = (int) $unit['pending_count'];
			$result['required']  += $required;
			$result['completed'] += $completed;
			$result['planned']   += $planned;
			$remaining           += max( 0, $required - $completed );
			$unplanned           += max( 0, $required - $completed - $planned );
		}

		if ( $result['required'] > 0 ) {
			if ( $remaining === 0 ) {
				$result['status'] = 'completed';
			} elseif ( $unplanned === 0 ) {
				$result['status'] = 'planned';
			} elseif ( $result['planned'] + $result['completed'] === 0 ) {
				$result['status'] = 'not_started';
			} else {
				$result['status'] = 'insufficient';
			}
		}

		return $result;
	}
}
