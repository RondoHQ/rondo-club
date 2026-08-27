<?php
/**
 * Tournament authorization helpers.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentAccess {

	public const MANAGER_ROLE = 'Coördinator toernooien';

	/** Whether a user may manage all tournaments. */
	public static function can_manage( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		if ( get_post_type( $person_id ) !== 'person' ) {
			return false;
		}

		foreach ( Fields::get_for_post( $person_id, 'work_history' ) ?: [] as $position ) {
			if ( ! is_array( $position ) || ! VolunteerStatus::is_position_current( $position ) ) {
				continue;
			}
			if ( self::normalize_role( (string) ( $position['job_title'] ?? '' ) ) === self::normalize_role( self::MANAGER_ROLE ) ) {
				return true;
			}
		}

		return false;
	}

	/** Whether a user is assigned to one tournament entry. */
	public static function is_assigned( int $entry_id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		if ( $user_id <= 0 || get_post_type( $entry_id ) !== TournamentService::ENTRY_POST_TYPE ) {
			return false;
		}

		$assignments = Fields::get_for_post( $entry_id, 'assignment_snapshot' ) ?: [];
		$assigned    = array_map( static fn( array $row ): int => (int) ( $row['user_id'] ?? 0 ), $assignments );
		return in_array( $user_id, $assigned, true );
	}

	/** Whether a user may read one tournament entry. */
	public static function can_read_entry( int $entry_id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return self::can_manage( $user_id ) || self::is_assigned( $entry_id, $user_id );
	}

	/** Whether a user has at least one assigned tournament entry. */
	public static function has_assignments( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		$ids = get_posts(
			[
				'post_type'        => TournamentService::ENTRY_POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => '_tournament_assigned_user_' . $user_id,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		return ! empty( $ids );
	}

	private static function normalize_role( string $role ): string {
		return strtolower( remove_accents( trim( $role ) ) );
	}
}
