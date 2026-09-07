<?php
/**
 * Contact-only rosters for a user's current coaching assignments.
 *
 * @package Rondo\Teams
 */

namespace Rondo\Teams;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\Volunteer\VolunteerEligibilityService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MyTeam {

	/** Coaching roles only; the volunteer-exemption list also contains unrelated roles. */
	private const STAFF_ROLES = [
		'trainer',
		'coach',
		'trainer/coach',
		'hoofdtrainer',
		'assistent-trainer',
		'assistent-coach',
		'assistent-trainer/coach',
		'leider',
		'teamleider',
		'teammanager',
	];

	/** Resolve access from the linked person, never from a requested user or team ID. */
	public static function teams_for_user( ?int $user_id = null ): array {
		$user_id   = $user_id ?? get_current_user_id();
		$person_id = $user_id > 0 ? (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) : 0;
		if ( ! self::is_published_person( $person_id ) || Fields::get_for_post( $person_id, 'former_member' ) ) {
			return [];
		}

		$teams = [];
		foreach ( Fields::get_for_post( $person_id, 'work_history' ) ?: [] as $position ) {
			if ( ! self::is_current( $position ) || ! in_array( self::normalize_role( $position['job_title'] ?? '' ), self::STAFF_ROLES, true ) ) {
				continue;
			}
			$team_id = (int) ( $position['team'] ?? 0 );
			$team    = get_post( $team_id );
			if ( ! $team || $team->post_type !== 'team' || $team->post_status !== 'publish' ) {
				continue;
			}
			$teams[ $team_id ] = [
				'id'   => $team_id,
				'name' => html_entity_decode( get_the_title( $team_id ), ENT_QUOTES, 'UTF-8' ),
			];
		}
		usort( $teams, static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );
		return $teams;
	}

	/**
	 * Return only player/parent contacts in the current user's assigned teams.
	 *
	 * This grants no general person access. The internal roster scan bypasses the
	 * household query filter only after resolving coaching assignments; every row
	 * is checked against those teams before the contact allowlist is applied.
	 */
	public static function rosters(): array {
		$teams = self::teams_for_user();
		if ( ! $teams ) {
			return [];
		}
		$by_team = [];
		foreach ( $teams as $team ) {
			$by_team[ $team['id'] ] = $team + [ 'players' => [] ];
		}
		$people       = get_posts(
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
						'value'       => array_keys( $by_team ),
						'compare'     => 'IN',
						'type'        => 'NUMERIC',
					],
				],
			]
		);
		$player_roles = array_map( [ self::class, 'normalize_role' ], VolunteerStatus::get_player_roles() );
		$parents      = new VolunteerEligibilityService();
		foreach ( $people as $person ) {
			if ( Fields::get_for_post( $person->ID, 'former_member' ) ) {
				continue;
			}
			$player = null;
			foreach ( Fields::get_for_post( $person->ID, 'work_history' ) ?: [] as $position ) {
				$team_id = (int) ( $position['team'] ?? 0 );
				if ( ! isset( $by_team[ $team_id ] ) || ! self::is_current( $position ) || ! in_array( self::normalize_role( $position['job_title'] ?? '' ), $player_roles, true ) ) {
					continue;
				}
				if ( $player === null ) {
					$player            = self::contact( $person->ID );
					$player['parents'] = [];
					foreach ( $parents->find_parents( $person->ID ) as $parent_id ) {
						if ( self::is_published_person( $parent_id ) ) {
							$player['parents'][] = self::contact( $parent_id );
						}
					}
				}
				$by_team[ $team_id ]['players'][ $person->ID ] = $player;
			}
		}
		foreach ( $by_team as &$team ) {
			usort( $team['players'], static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );
		}
		unset( $team );
		return array_values( $by_team );
	}

	/** Explicit dates take precedence over stale flags; the end date is inclusive. */
	private static function is_current( array $position ): bool {
		$today = current_datetime()->format( 'Ymd' );
		foreach ( [ 'start_date', 'end_date' ] as $key ) {
			$value = str_replace( '-', '', trim( (string) ( $position[ $key ] ?? '' ) ) );
			if ( $value === '' ) {
				continue;
			}
			$date = \DateTimeImmutable::createFromFormat( '!Ymd', $value, wp_timezone() );
			if ( ! $date || $date->format( 'Ymd' ) !== $value || ( $key === 'start_date' ? $value > $today : $value < $today ) ) {
				return false;
			}
		}
		return ! empty( $position['team'] );
	}

	private static function normalize_role( string $role ): string {
		return strtolower( trim( $role ) );
	}

	private static function is_published_person( int $person_id ): bool {
		return $person_id > 0 && get_post_type( $person_id ) === 'person' && get_post_status( $person_id ) === 'publish';
	}

	/** An explicit allowlist; never serialize a general person or user response. */
	private static function contact( int $person_id ): array {
		$parts = [];
		foreach ( [ 'first_name', 'infix', 'last_name' ] as $field ) {
			$parts[] = trim( (string) Fields::get_for_post( $person_id, $field ) );
		}
		$name    = implode( ' ', array_filter( $parts ) );
		$contact = [
			'id'     => $person_id,
			'name'   => $name ?: html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' ),
			'emails' => [],
			'phones' => [],
		];
		foreach ( [ 'email_1', 'email_2', 'mobile_1', 'mobile_2', 'telephone_1', 'telephone_2' ] as $field ) {
			$value = trim( (string) Fields::get_for_post( $person_id, $field ) );
			if ( $value === '' ) {
				continue;
			}
			$is_email                     = str_starts_with( $field, 'email' );
			$key                          = $is_email ? 'emails' : 'phones';
			$identity                     = $is_email ? strtolower( $value ) : preg_replace( '/[^+0-9]/', '', $value );
			$contact[ $key ][ $identity ] = $value;
		}
		$contact['emails'] = array_values( $contact['emails'] );
		$contact['phones'] = array_values( $contact['phones'] );
		return $contact;
	}
}
