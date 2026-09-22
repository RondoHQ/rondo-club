<?php
/**
 * Person-based tournament invitations and account resolution.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Fields\Fields;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentAssignees {

	/** Resolve accounts only through their trusted person link, never by email. */
	public static function users_by_person( array $person_ids ): array {
		$person_ids = array_values( array_unique( array_filter( array_map( 'intval', $person_ids ) ) ) );
		if ( empty( $person_ids ) ) {
			return [];
		}
		$users     = get_users(
			[
				'fields'     => 'all',
				'number'     => -1,
				'orderby'    => 'ID',
				'order'      => 'ASC',
				'meta_query' => [
					[
						'key'     => 'rondo_linked_person_id',
						'value'   => $person_ids,
						'compare' => 'IN',
					],
				],
			]
		);
		$by_person = [];
		foreach ( $users as $user ) {
			$person_id                 = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
			$by_person[ $person_id ] ??= $user;
		}
		return $by_person;
	}

	/** Refresh account and delivery details without rewriting invitation history. */
	public static function resolve( array $assignments ): array {
		$users = self::users_by_person( array_column( $assignments, 'person_id' ) );
		foreach ( $assignments as &$assignment ) {
			$person_id = (int) ( $assignment['person_id'] ?? 0 );
			if ( $person_id <= 0 ) {
				$user_id             = (int) ( $assignment['user_id'] ?? 0 );
				$assignment['email'] = $user_id > 0 ? (string) ( UserProvisioning::contact_email( $user_id ) ?? '' ) : '';
				continue; // Legacy assignments still resolve their current account address.
			}
			$user                  = $users[ $person_id ] ?? null;
			$assignment['user_id'] = $user ? (int) $user->ID : 0;
			$assignment['email']   = self::email( $person_id, $assignment['user_id'] );
		}
		unset( $assignment );
		return $assignments;
	}

	public static function email( int $person_id, int $user_id = 0 ): string {
		if ( get_post_type( $person_id ) !== 'person' || get_post_status( $person_id ) !== 'publish' ) {
			return '';
		}
		$email = $user_id > 0 ? (string) ( UserProvisioning::contact_email( $user_id ) ?? '' ) : '';
		foreach ( [ $email, Fields::get_for_post( $person_id, 'email_1' ), Fields::get_for_post( $person_id, 'email_2' ) ] as $candidate ) {
			$candidate = sanitize_email( (string) $candidate );
			if ( is_email( $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/** New invitation receipts survive account creation; legacy receipts still count. */
	public static function receipt_key( array $assignee, string $kind ): string {
		$person_id = (int) ( $assignee['person_id'] ?? 0 );
		return '_tournament_' . $kind . '_email_sent_' . ( $person_id > 0 ? 'person_' . $person_id : (int) ( $assignee['user_id'] ?? 0 ) );
	}

	public static function was_sent( int $entry_id, array $assignee, string $kind ): bool {
		$user_id = (int) ( $assignee['user_id'] ?? 0 );
		return (bool) get_post_meta( $entry_id, self::receipt_key( $assignee, $kind ), true )
			|| ( $user_id > 0 && (bool) get_post_meta( $entry_id, '_tournament_' . $kind . '_email_sent_' . $user_id, true ) );
	}
}
