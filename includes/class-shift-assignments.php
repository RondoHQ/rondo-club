<?php
/**
 * Canonical read of a shift's assigned persons.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads `assigned_persons` meta the way the field registry defines it.
 *
 * The raw meta row can be missing (get_post_meta returns ''), a scalar, or an
 * array containing zeros or duplicates. Casting with `(array)` turns '' into
 * [''], which made an empty shift count one phantom assignee and present
 * itself as filled. Every reader must go through this helper instead of
 * casting the meta value.
 */
class ShiftAssignments {

	/**
	 * Person IDs assigned to a shift: positive, unique, in signup order.
	 *
	 * @return int[]
	 */
	public static function person_ids( int $shift_id ): array {
		$raw = get_post_meta( $shift_id, 'assigned_persons', true );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$ids = [];
		foreach ( $raw as $value ) {
			$id = (int) $value;
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}
}
