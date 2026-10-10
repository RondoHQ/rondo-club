<?php
/** Personal committee rosters, with contacts limited to current chairpersons. */

namespace Rondo\Commissies;

use Rondo\Fields\Fields;
use Rondo\People\RosterPerson;

final class MyCommittees {

	/** Resolve only the signed-in person's own current committee assignments. */
	public static function committees_for_user( ?int $user_id = null ): array {
		$user_id   = $user_id ?? get_current_user_id();
		$person_id = $user_id > 0 ? (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) : 0;
		if ( ! RosterPerson::is_published_person( $person_id ) || Fields::get_for_post( $person_id, 'former_member' ) ) {
			return [];
		}

		$committees = [];
		foreach ( Fields::get_for_post( $person_id, 'work_history' ) ?: [] as $position ) {
			$id   = (int) ( $position['team'] ?? 0 );
			$role = trim( (string) ( $position['job_title'] ?? '' ) );
			if ( $role === '' || ! RosterPerson::is_current_position( $position ) || get_post_type( $id ) !== 'commissie' || get_post_status( $id ) !== 'publish' ) {
				continue;
			}
			$committees[ $id ] = [
				'id'                => $id,
				'name'              => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'can_view_contacts' => strtolower( $role ) === 'voorzitter' || ( $committees[ $id ]['can_view_contacts'] ?? false ),
			];
		}
		usort( $committees, static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );
		return $committees;
	}

	/** Narrow roster projection; this never grants access to general person records. */
	public static function rosters(): array {
		$committees = self::committees_for_user();
		if ( ! $committees ) {
			return [];
		}
		$by_committee = [];
		foreach ( $committees as $committee ) {
			$by_committee[ $committee['id'] ] = $committee + [ 'members' => [] ];
		}
		// The caller's memberships are verified before this scoped internal query.
		$people = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'         => '^work_history_[0-9]+_team$',
						'compare_key' => 'REGEXP',
						'value'       => array_keys( $by_committee ),
						'compare'     => 'IN',
						'type'        => 'NUMERIC',
					],
				],
			]
		);
		foreach ( $people as $person ) {
			if ( Fields::get_for_post( $person->ID, 'former_member' ) ) {
				continue;
			}
			foreach ( Fields::get_for_post( $person->ID, 'work_history' ) ?: [] as $position ) {
				$id   = (int) ( $position['team'] ?? 0 );
				$role = trim( (string) ( $position['job_title'] ?? '' ) );
				if ( ! isset( $by_committee[ $id ] ) || $role === '' || ! RosterPerson::is_current_position( $position ) ) {
					continue;
				}
				if ( ! isset( $by_committee[ $id ]['members'][ $person->ID ] ) ) {
					$member                                        = $by_committee[ $id ]['can_view_contacts'] ? RosterPerson::contact( $person->ID ) : RosterPerson::identity( $person->ID );
					$by_committee[ $id ]['members'][ $person->ID ] = $member + [
						'thumbnail' => get_the_post_thumbnail_url( $person->ID, 'thumbnail' ) ?: null,
						'roles'     => [],
					];
				}
				$roles   = $by_committee[ $id ]['members'][ $person->ID ]['roles'];
				$roles[] = $role;
				$by_committee[ $id ]['members'][ $person->ID ]['roles'] = array_values( array_unique( $roles ) );
			}
		}
		foreach ( $by_committee as &$committee ) {
			usort( $committee['members'], static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );
		}
		unset( $committee );
		return array_values( $by_committee );
	}
}
