<?php
/** Shared work-history status rules. */

namespace Rondo\Core;

class WorkHistory {

	/**
	 * An explicitly inactive role can have an unknown end date.
	 *
	 * Missing status keeps the legacy date-based behavior. Dated roles retain
	 * each consumer's existing date boundary rules.
	 *
	 * @param array $position Work-history row, in storage or canonical format.
	 * @return bool
	 */
	public static function is_inactive_without_end_date( array $position ): bool {
		return isset( $position['is_current'] )
			&& in_array( $position['is_current'], [ false, 0, '0' ], true )
			&& trim( (string) ( $position['end_date'] ?? '' ) ) === '';
	}
}
