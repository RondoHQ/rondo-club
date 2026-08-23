<?php
/**
 * Resolve the commissies and year groups for which one person may reserve a room.
 *
 * @package Rondo\Rooms
 */

namespace Rondo\Rooms;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BookingEligibility {

	/** Return eligible contexts for a WordPress user. */
	public static function for_user( int $user_id ): array {
		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		return $person_id > 0 ? self::for_person( $person_id ) : [];
	}

	/** Return eligible contexts for a linked person. */
	public static function for_person( int $person_id ): array {
		if ( get_post_type( $person_id ) !== 'person' ) {
			return [];
		}

		$contexts     = [];
		$work_history = Fields::get_for_post( $person_id, 'work_history' ) ?: [];
		foreach ( $work_history as $position ) {
			if (
				! is_array( $position )
				|| ! VolunteerStatus::is_position_current( $position )
				|| ! VolunteerStatus::is_volunteer_position( $position )
			) {
				continue;
			}

			$entity_id   = (int) ( $position['team'] ?? $position['team_id'] ?? 0 );
			$entity_type = (string) ( $position['entity_type'] ?? get_post_type( $entity_id ) );
			if ( $entity_id <= 0 ) {
				continue;
			}

			if ( $entity_type === 'commissie' && get_post_type( $entity_id ) === 'commissie' ) {
				$key              = 'commissie:' . $entity_id;
				$contexts[ $key ] = [
					'type'                => 'commissie',
					'commissie_id'        => $entity_id,
					'age_group_key'       => null,
					'eligibility_team_id' => null,
					'label'               => get_the_title( $entity_id ),
				];
				continue;
			}

			if ( $entity_type !== 'team' || get_post_type( $entity_id ) !== 'team' ) {
				continue;
			}

			foreach ( self::year_groups_for_team( $entity_id ) as $year_group ) {
				$key = 'age_group:' . $year_group['key'];
				if ( isset( $contexts[ $key ] ) ) {
					$contexts[ $key ]['team_ids'][] = $entity_id;
					$contexts[ $key ]['team_ids']   = array_values( array_unique( $contexts[ $key ]['team_ids'] ) );
					continue;
				}
				$contexts[ $key ] = [
					'type'                => 'age_group',
					'commissie_id'        => null,
					'age_group_key'       => $year_group['key'],
					'eligibility_team_id' => $entity_id,
					'team_ids'            => [ $entity_id ],
					'label'               => $year_group['label'],
				];
			}
		}

		$contexts = array_values( $contexts );
		usort( $contexts, static fn( array $left, array $right ): int => strnatcasecmp( $left['label'], $right['label'] ) );
		return $contexts;
	}

	/** Resolve one submitted context against the server-derived list. */
	public static function match( int $user_id, string $type, int $commissie_id, string $age_group_key ): ?array {
		$age_group_key = self::normalize_age_group( $age_group_key );
		foreach ( self::for_user( $user_id ) as $context ) {
			if ( $type === 'commissie' && $context['type'] === 'commissie' && (int) $context['commissie_id'] === $commissie_id ) {
				return $context;
			}
			if ( $type === 'age_group' && $context['type'] === 'age_group' && $context['age_group_key'] === $age_group_key ) {
				return $context;
			}
		}
		return null;
	}

	/** Normalize Sportlink age labels without parsing team names. */
	public static function normalize_age_group( string $value ): string {
		$value = trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/(?:onder|[jmo])\s*-?\s*(\d{1,2})/iu', $value, $matches ) === 1 ) {
			return 'O' . (int) $matches[1];
		}
		return strtoupper( sanitize_title( $value ) );
	}

	/** Human-readable label for one normalized year-group key. */
	public static function age_group_label( string $key ): string {
		return preg_match( '/^O(\d{1,2})$/', $key, $matches ) === 1
			? 'O' . (int) $matches[1] . ' jaarlaagoverleg'
			: ucwords( str_replace( '-', ' ', strtolower( $key ) ) );
	}

	/** Derive normalized player year groups from one current team roster. */
	private static function year_groups_for_team( int $team_id ): array {
		$people = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => 'work_history',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					],
				],
			]
		);

		$groups       = [];
		$player_roles = VolunteerStatus::get_player_roles();
		foreach ( $people as $person_id ) {
			$plays_for_team = false;
			foreach ( Fields::get_for_post( (int) $person_id, 'work_history' ) ?: [] as $position ) {
				if (
					is_array( $position )
					&& (int) ( $position['team'] ?? $position['team_id'] ?? 0 ) === $team_id
					&& VolunteerStatus::is_position_current( $position )
					&& in_array( (string) ( $position['job_title'] ?? '' ), $player_roles, true )
				) {
					$plays_for_team = true;
					break;
				}
			}
			if ( ! $plays_for_team ) {
				continue;
			}

			$key = self::normalize_age_group( (string) Fields::get_for_post( (int) $person_id, 'leeftijdsgroep' ) );
			if ( $key !== '' ) {
				$groups[ $key ] = [
					'key'   => $key,
					'label' => self::age_group_label( $key ),
				];
			}
		}

		return array_values( $groups );
	}
}
