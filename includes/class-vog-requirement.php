<?php
/**
 * Resolve which current volunteers require a VOG for at least one active role.
 *
 * @package Rondo\VOG
 */

namespace Rondo\VOG;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Role-aware VOG requirement resolver.
 */
class VOGRequirement {

	/** Cached list of people for the filtered VOG overviews. */
	private const CACHE_KEY = 'rondo_vog_required_people_v1';

	/** Request-local copy of the cached person IDs. */
	private static ?array $required_person_ids = null;

	/**
	 * Return all current volunteers who need a VOG for at least one active role.
	 *
	 * @return int[] Person post IDs.
	 */
	public static function get_required_person_ids(): array {
		if ( self::$required_person_ids !== null ) {
			return self::$required_person_ids;
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			self::$required_person_ids = array_values( array_map( 'intval', $cached ) );
			return self::$required_person_ids;
		}

		$person_ids = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'huidig-vrijwilliger',
				'meta_value'     => '1',
			]
		);

		$required = [];
		foreach ( $person_ids as $person_id ) {
			if ( self::is_required( (int) $person_id ) ) {
				$required[] = (int) $person_id;
			}
		}

		self::$required_person_ids = $required;
		set_transient( self::CACHE_KEY, $required, HOUR_IN_SECONDS );

		return self::$required_person_ids;
	}

	/**
	 * Check whether one person needs a VOG for an active volunteer role.
	 */
	public static function is_required( int $person_id ): bool {
		$work_history = Fields::get_for_post( $person_id, 'work_history' );
		if ( ! is_array( $work_history ) || empty( $work_history ) ) {
			return false;
		}

		$exempt_roles      = array_map( 'strval', VOGEmail::get_exempt_roles() );
		$exempt_commissies = array_map( 'intval', ( new VOGEmail() )->get_exempt_commissies() );
		$excluded_roles    = array_map( 'strval', VolunteerStatus::get_excluded_roles() );
		$player_roles      = array_map( 'strval', VolunteerStatus::get_player_roles() );

		foreach ( $work_history as $position ) {
			if ( ! is_array( $position ) || ! VolunteerStatus::is_position_current( $position ) ) {
				continue;
			}

			$job_title = trim( (string) ( $position['job_title'] ?? '' ) );
			if ( in_array( $job_title, $excluded_roles, true ) || in_array( $job_title, $exempt_roles, true ) ) {
				continue;
			}

			$team_id     = (int) ( $position['team'] ?? 0 );
			$entity_type = self::resolve_entity_type( $position, $team_id );

			if ( $entity_type === 'commissie' ) {
				if ( $team_id > 0 && in_array( $team_id, $exempt_commissies, true ) ) {
					continue;
				}

				return true;
			}

			if ( $entity_type === 'team' && $job_title !== '' && ! in_array( $job_title, $player_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove cached requirement results after person or VOG-setting changes.
	 */
	public static function invalidate_cache(): void {
		self::$required_person_ids = null;
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Resolve legacy work-history rows whose entity type is still empty.
	 *
	 * @param array $position Work-history row.
	 * @param int   $team_id  Linked team or commissie post ID.
	 */
	private static function resolve_entity_type( array $position, int $team_id ): string {
		$entity_type = (string) ( $position['entity_type'] ?? '' );
		if ( in_array( $entity_type, [ 'team', 'commissie' ], true ) ) {
			return $entity_type;
		}

		if ( $team_id <= 0 ) {
			return '';
		}

		$post_type = get_post_type( $team_id );
		return in_array( $post_type, [ 'team', 'commissie' ], true ) ? $post_type : '';
	}
}
