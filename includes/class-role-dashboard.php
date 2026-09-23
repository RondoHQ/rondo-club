<?php
/** Role-scoped dashboard data; dashboard access never grants record access. */

namespace Rondo\Dashboard;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\Training\Schedules;

final class RoleDashboard {

	/** Resolve the union of roles on every request, including after revocation. */
	public static function context( ?int $user_id = null ): array {
		$user_id     = $user_id ?? get_current_user_id();
		$coordinator = $user_id > 0 && AccessControl::has_coordinator_team_scope( $user_id );
		$secretary   = UserRoles::can_access_section( 'wedstrijdzaken', $user_id );
		$user        = $user_id ? get_userdata( $user_id ) : false;
		$board       = $user && in_array( 'rondo_bestuur', (array) $user->roles, true );
		return [
			'board'       => $board,
			'coordinator' => $coordinator,
			'secretary'   => $secretary,
			'enabled'     => $board || $coordinator || $secretary,
			'can_access'  => $board || $coordinator || $secretary || UserRoles::is_kader( $user_id ),
		];
	}

	/** Only explicitly assigned coordinator teams, not the user's children's teams. */
	public static function team_ids(): array {
		if ( ! self::context()['coordinator'] ) {
			return [];
		}
		return AccessControl::get_permitted_team_ids();
	}

	/** Minimal team summaries and birthdays, with a separate record permission check. */
	public static function overview(): array {
		$context = self::context();
		$ids     = self::team_ids();
		$teams   = [];
		foreach ( $ids as $id ) {
			$teams[ $id ] = [
				'id'           => $id,
				'name'         => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'player_count' => 0,
			];
		}
		$memberships = [];
		if ( $ids ) {
			$people       = get_posts(
				[
					'post_type'        => 'person',
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'suppress_filters' => false,
					'no_found_rows'    => true,
					'meta_query'       => [
						[
							'key'         => '^work_history_[0-9]+_team$',
							'compare_key' => 'REGEXP',
							'value'       => $ids,
							'compare'     => 'IN',
							'type'        => 'NUMERIC',
						],
					],
				]
				);
			$player_roles = array_map( 'strtolower', VolunteerStatus::get_player_roles() );
			foreach ( $people as $person ) {
				if ( Fields::get_for_post( $person->ID, 'former_member' ) || ! AccessControl::can_view_person( $person->ID ) ) {
					continue;
				}
				$player_teams = [];
				foreach ( Fields::get_for_post( $person->ID, 'work_history' ) ?: [] as $job ) {
					$id = (int) ( $job['team'] ?? 0 );
					if ( ! isset( $teams[ $id ] ) || ! VolunteerStatus::is_position_current( $job ) ) {
						continue;
					}
					$memberships[ $person->ID ][ $id ] = $id;
					if ( in_array( strtolower( trim( $job['job_title'] ?? '' ) ), $player_roles, true ) ) {
						$player_teams[ $id ] = $id;
					}
				}
				foreach ( $player_teams as $id ) {
					++$teams[ $id ]['player_count'];
				}
			}
		}
		$birthdays = [];
		if ( $context['coordinator'] || $context['board'] ) {
			foreach ( ( new \RONDO_Reminders() )->get_upcoming_reminders( 6 ) as $birthday ) {
				$id = (int) $birthday['id'];
				if ( ! AccessControl::can_view_person( $id ) ) {
					continue;
				}
				$birthday['team_ids'] = array_values( $memberships[ $id ] ?? [] );
				$birthdays[]          = $birthday;
			}
		}
		usort( $teams, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
		$active_id = (int) get_option( Schedules::ACTIVE, 0 );
		$schedule  = $context['coordinator'] && $active_id && Schedules::exists( $active_id ) ? Schedules::schedule( $active_id ) : null;
		$training  = [];
		if ( is_array( $schedule ) ) {
			foreach ( $schedule['blocks'] as $block ) {
				$visible_ids = array_values( array_intersect( $block['team_ids'], $ids ) );
				if ( ! $visible_ids ) {
					continue;
				}
				$training[] = array_intersect_key( $block, array_flip( [ 'block_id', 'day', 'start', 'duration', 'pitch_id', 'size', 'offset' ] ) ) + [ 'team_ids' => $visible_ids ];
			}
		}
		return BoardDashboard::overview() + [
			'context'               => $context,
			'teams'                 => array_values( $teams ),
			'birthdays'             => $birthdays,
			'training'              => $training,
			'has_training_schedule' => is_array( $schedule ),
			'pitches'               => $context['coordinator'] ? Schedules::settings()['pitches'] : [],
			'today'                 => current_datetime()->format( 'Y-m-d' ),
			'day'                   => (int) current_datetime()->format( 'N' ),
			'end_date'              => current_datetime()->modify( '+6 days' )->format( 'Y-m-d' ),
		];
	}

	/** Blocks are composed once, with each existing section permission rechecked. */
	public static function available_blocks(): array {
		$context = self::context();
		$blocks  = $context['board'] ? [ 'birthdays' ] : [ 'attention' ];
		if ( $context['board'] ) {
			if ( UserRoles::can_access_section( 'jubilarissen' ) ) {
				$blocks[] = 'anniversaries';
			}
			$blocks[] = 'attention';
			foreach ( [
				'membership' => 'ledenadministratie',
				'volunteers' => 'vrijwilligers',
				'vog'        => 'vog',
			] as $block => $capability ) {
				if ( current_user_can( $capability ) ) {
					$blocks[] = $block;
				}
			}
		} elseif ( $context['coordinator'] ) {
			$blocks[] = 'birthdays';
		}
		if ( $context['coordinator'] || $context['secretary'] ) {
			$blocks[] = 'matches';
		}
		if ( $context['coordinator'] ) {
			$blocks[] = 'teams';
		}
		return $blocks;
	}

	/** A separate preference namespace preserves the existing dashboard layout. */
	public static function settings(): array {
		$available = self::available_blocks();
		$saved     = get_user_meta( get_current_user_id(), 'rondo_role_dashboard_layout', true );
		$order     = is_array( $saved ) ? array_values( array_intersect( $saved['order'] ?? [], $available ) ) : [];
		$order     = array_values( array_unique( array_merge( $order, $available ) ) );
		$hidden    = is_array( $saved ) ? $saved['hidden'] ?? [] : [];
		return [
			'defaults' => $available,
			'order'    => $order,
			'hidden'   => array_values( array_intersect( $hidden, $available ) ),
		];
	}
}
