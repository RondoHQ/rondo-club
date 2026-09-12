<?php
/**
 * Household team rosters with staff contacts and coaching-scoped player contacts.
 *
 * @package Rondo\Teams
 */

namespace Rondo\Teams;

use Rondo\Core\AccessControl;
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
		if ( ! self::is_published_person( $person_id ) ) {
			return [];
		}

		$teams        = [];
		$player_roles = array_map( [ self::class, 'normalize_role' ], VolunteerStatus::get_player_roles() );
		// Reuse the personal household boundary: self and linked minor children only.
		foreach ( AccessControl::get_visible_person_ids( $user_id ) as $member_id ) {
			if ( ! self::is_published_person( $member_id ) || Fields::get_for_post( $member_id, 'former_member' ) ) {
				continue;
			}
			foreach ( Fields::get_for_post( $member_id, 'work_history' ) ?: [] as $position ) {
				$role     = self::normalize_role( $position['job_title'] ?? '' );
				$is_staff = $member_id === $person_id && in_array( $role, self::STAFF_ROLES, true );
				if ( ! self::is_current( $position ) || ( ! $is_staff && ! in_array( $role, $player_roles, true ) ) ) {
					continue;
				}
				$team_id = (int) ( $position['team'] ?? 0 );
				$team    = get_post( $team_id );
				if ( ! $team || $team->post_type !== 'team' || $team->post_status !== 'publish' ) {
					continue;
				}
				$teams[ $team_id ] = [
					'id'                => $team_id,
					'name'              => html_entity_decode( get_the_title( $team_id ), ENT_QUOTES, 'UTF-8' ),
					'can_view_contacts' => $is_staff || ( $teams[ $team_id ]['can_view_contacts'] ?? false ),
				];
			}
		}
		usort( $teams, static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );
		return $teams;
	}

	/**
	 * Return player identities and staff contacts, adding player contacts for coaches.
	 *
	 * This grants no general person access. The internal roster scan bypasses the
	 * household query filter only after resolving current team assignments. Both
	 * player membership and contact access are checked separately for each team.
	 */
	public static function rosters(): array {
		$teams = self::teams_for_user();
		if ( ! $teams ) {
			return [];
		}
		$by_team = [];
		foreach ( $teams as $team ) {
			$by_team[ $team['id'] ] = $team + [
				'players' => [],
				'staff'   => [],
			];
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
			$player         = null;
			$player_contact = null;
			$staff_contact  = null;
			foreach ( Fields::get_for_post( $person->ID, 'work_history' ) ?: [] as $position ) {
				$team_id = (int) ( $position['team'] ?? 0 );
				$role    = trim( $position['job_title'] ?? '' );
				if ( ! isset( $by_team[ $team_id ] ) || ! self::is_current( $position ) || $role === '' ) {
					continue;
				}
				// Staff contacts are available to every viewer of this team, never their parents' contacts.
				if ( ! in_array( self::normalize_role( $role ), $player_roles, true ) ) {
					if ( $staff_contact === null ) {
						$staff_contact              = self::contact( $person->ID );
						$staff_contact['thumbnail'] = get_the_post_thumbnail_url( $person->ID, 'thumbnail' ) ?: null;
					}
					$roles                                       = $by_team[ $team_id ]['staff'][ $person->ID ]['roles'] ?? [];
					$roles[]                                     = $role;
					$by_team[ $team_id ]['staff'][ $person->ID ] = $staff_contact + [ 'roles' => array_values( array_unique( $roles ) ) ];
					continue;
				}
				if ( $player === null ) {
					$player              = self::identity( $person->ID );
					$player['thumbnail'] = get_the_post_thumbnail_url( $person->ID, 'thumbnail' ) ?: null;
				}
				if ( ! $by_team[ $team_id ]['can_view_contacts'] ) {
					$by_team[ $team_id ]['players'][ $person->ID ] = $player;
					continue;
				}
				if ( $player_contact === null ) {
					$player_contact              = self::contact( $person->ID );
					$player_contact['thumbnail'] = $player['thumbnail'];
					$player_contact['parents']   = [];
					foreach ( $parents->find_parents( $person->ID ) as $parent_id ) {
						if ( self::is_published_person( $parent_id ) ) {
							$player_contact['parents'][] = self::contact( $parent_id );
						}
					}
				}
				$by_team[ $team_id ]['players'][ $person->ID ] = $player_contact;
			}
		}
		foreach ( $by_team as &$team ) {
			usort( $team['players'], static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );
			usort( $team['staff'], static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );
		}
		unset( $team );
		return array_values( $by_team );
	}

	/** Explicit dates take precedence over stale flags; the end date is inclusive. */
	private static function is_current( array $position ): bool {
		if ( \Rondo\Core\WorkHistory::is_inactive_without_end_date( $position ) ) {
			return false;
		}

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

	/** Minimal roster identity only; never read contact fields for player-only access. */
	private static function identity( int $person_id ): array {
		$parts = [];
		foreach ( [ 'first_name', 'infix', 'last_name' ] as $field ) {
			$parts[] = trim( (string) Fields::get_for_post( $person_id, $field ) );
		}
		$name = implode( ' ', array_filter( $parts ) );
		return [
			'id'   => $person_id,
			'name' => $name ?: html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' ),
		];
	}

	/** An explicit allowlist; never serialize a general person or user response. */
	private static function contact( int $person_id ): array {
		$contact = self::identity( $person_id ) + [
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
