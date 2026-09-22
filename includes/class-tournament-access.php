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

		return self::is_coordinator( (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
	}

	/** Whether a person has a current tournament coordinator role. */
	public static function is_coordinator( int $person_id ): bool {
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

		if ( get_post_status( $entry_id ) === 'trash' ) {
			return false;
		}
		$person_id = self::linked_person( $user_id );
		foreach ( Fields::get_for_post( $entry_id, 'assignment_snapshot' ) ?: [] as $row ) {
			$assigned_person = (int) ( $row['person_id'] ?? 0 );
			if ( $assigned_person > 0 ? $assigned_person === $person_id : (int) ( $row['user_id'] ?? 0 ) === $user_id ) {
				return true;
			}
		}
		return false;
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
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => self::assignment_query( $user_id ),
			]
		);

		foreach ( $ids as $entry_id ) {
			if ( self::is_assigned( (int) $entry_id, $user_id ) ) {
				return true;
			}
		}
		return false;
	}

	/** Include pending invitations once the normal account flow links this person. */
	public static function assignment_query( int $user_id ): array {
		$query     = [
			'relation' => 'OR',
			[
				'key'     => '_tournament_assigned_user_' . $user_id,
				'compare' => 'EXISTS',
			],
		];
		$person_id = self::linked_person( $user_id );
		if ( $person_id > 0 ) {
			$query[] = [
				'key'     => '_tournament_assigned_person_' . $person_id,
				'compare' => 'EXISTS',
			];
		}
		return $query;
	}

	private static function linked_person( int $user_id ): int {
		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		return get_post_type( $person_id ) === 'person' && get_post_status( $person_id ) === 'publish' && ! Fields::get_for_post( $person_id, 'former_member' ) ? $person_id : 0;
	}

	private static function normalize_role( string $role ): string {
		return strtolower( remove_accents( trim( $role ) ) );
	}
}
