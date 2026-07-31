<?php
/**
 * Role Finder helper.
 *
 * Finds WordPress users whose linked person currently holds a specific job title
 * (e.g. "secretaris", "penningmeester") by inspecting native field work_history fields.
 * Falls back to administrator user IDs when no matching users are found.
 *
 * @package Rondo\Core
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RoleFinder {

	/**
	 * Get user IDs for users whose linked person currently holds a role
	 * matching the given keyword.
	 *
	 * Searches all users with a `rondo_linked_person_id` user meta value,
	 * then checks whether their linked person has a current work_history
	 * entry whose job_title contains the keyword (case-sensitive).
	 *
	 * Falls back to administrator user IDs when no matching users are found.
	 *
	 * @param string $keyword Job title keyword to match, case-sensitive (e.g. "Secretaris", "Penningmeester").
	 * @return int[] User IDs.
	 */
	public static function get_user_ids_by_role( string $keyword ): array {
		$user_ids = get_users(
			[
				'fields'   => 'ids',
				'meta_key' => 'rondo_linked_person_id',
			]
		);

		$owners = [];
		foreach ( $user_ids as $user_id ) {
			$person_id = (int) get_user_meta( (int) $user_id, 'rondo_linked_person_id', true );
			if ( $person_id <= 0 ) {
				continue;
			}
			if ( self::person_has_current_role( $person_id, $keyword ) ) {
				$owners[] = (int) $user_id;
			}
		}

		$owners = array_values( array_unique( $owners ) );
		if ( ! empty( $owners ) ) {
			return $owners;
		}

		// Fallback: return administrator user IDs.
		$admins = get_users(
			[
				'role'   => 'administrator',
				'fields' => 'ids',
			]
		);

		return array_values( array_unique( array_map( 'intval', $admins ) ) );
	}

	/**
	 * Check whether a person currently holds a role matching the keyword.
	 *
	 * @param int    $person_id Person post ID.
	 * @param string $keyword   Job title keyword to match.
	 * @return bool
	 */
	private static function person_has_current_role( int $person_id, string $keyword ): bool {
		$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' );
		if ( ! is_array( $work_history ) ) {
			return false;
		}

		foreach ( $work_history as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$job_title = trim( (string) ( $entry['job_title'] ?? '' ) );
			if ( $job_title === '' || strpos( $job_title, $keyword ) === false ) {
				continue;
			}

			if ( self::is_current_work_history_entry( $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a work_history row is currently active.
	 *
	 * A row is considered current if:
	 * - The `is_current` flag is truthy, OR
	 * - The start_date is in the past (or empty) AND the end_date is in the
	 *   future (or empty).
	 *
	 * @param array $entry Work history row.
	 * @return bool
	 */
	private static function is_current_work_history_entry( array $entry ): bool {
		if ( ! empty( $entry['is_current'] ) ) {
			return true;
		}

		$today_ts = strtotime( current_time( 'Y-m-d' ) );
		if ( $today_ts === false ) {
			return false;
		}

		$start_raw = trim( (string) ( $entry['start_date'] ?? '' ) );
		$end_raw   = trim( (string) ( $entry['end_date'] ?? '' ) );

		$start_ts = $start_raw !== '' ? strtotime( $start_raw ) : false;
		$end_ts   = $end_raw !== '' ? strtotime( $end_raw ) : false;

		if ( $start_ts !== false && $start_ts > $today_ts ) {
			return false;
		}
		if ( $end_ts !== false && $end_ts < $today_ts ) {
			return false;
		}

		return true;
	}
}
