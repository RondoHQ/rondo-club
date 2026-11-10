<?php
/** Auditable, date-based duty credits for administrative corrections. */
namespace Rondo\Volunteer;

use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;

final class VolunteerDutyCredits {

	/** Each distinct reference represents one credited duty, never a worked shift. */
	public static function for_season( int $person_id, string $season ): array {
		$rows    = Fields::get_for_post( $person_id, 'volunteer_duty_credits' ) ?: [];
		$rows    = Formatter::for_wire( 'person', [ 'volunteer_duty_credits' => $rows ] )['volunteer_duty_credits'];
		$credits = [];
		foreach ( $rows as $row ) {
			$reference = trim( (string) ( $row['reference'] ?? '' ) );
			$date      = $row['date'] ?? null;
			if ( $reference === '' || ! $date || trim( (string) ( $row['reason'] ?? '' ) ) === '' || SeasonKey::current( $date ) !== $season ) {
				continue;
			}
			$credits[ $reference ] = $row;
		}
		return array_values( $credits );
	}
}
