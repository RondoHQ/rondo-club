<?php
/** Dated training attendance, separate from the recurring schedule. */
namespace Rondo\Training;

use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\Teams\MyTeam;

final class Occurrences {

	public static function date( $value ): ?\DateTimeImmutable {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d' ) === $value ? $date : null;
	}

	/** Latest state per occurrence; the native repeater retains the complete audit trail. */
	public static function states( int $schedule_id ): array {
		$result = [];
		foreach ( Fields::get_for_post( $schedule_id, 'cancellations' ) ?: [] as $event ) {
			$key            = str_replace( '-', '', $event['date'] ) . ':' . $event['block_id'] . ':' . $event['team_id'];
			$result[ $key ] = [
				'cancelled' => (bool) $event['cancelled'],
				'revision'  => (int) $event['revision'],
			];
		}
		return $result;
	}

	public static function state( array $states, string $date, string $block, int $team ): array {
		return $states[ str_replace( '-', '', $date ) . ':' . $block . ':' . $team ] ?? [
			'cancelled' => false,
			'revision'  => 0,
		];
	}

	public static function manageable_teams(): array {
		return UserRoles::is_entree() ? [] : array_column( array_filter( MyTeam::teams_for_user(), static fn( $team ) => $team['can_view_contacts'] ), 'id' );
	}

	/** Public schedule information plus permissions for the authenticated viewer, without audit identities. */
	public static function week( $value = null ) {
		$date = self::date( $value ?? current_datetime()->format( 'Y-m-d' ) );
		if ( ! $date ) {
			return Schedules::error( 'Kies een geldige datum (JJJJ-MM-DD).' );
		}
		$monday   = $date->modify( '-' . ( (int) $date->format( 'N' ) - 1 ) . ' days' );
		$id       = (int) get_option( Schedules::ACTIVE, 0 );
		$schedule = $id && Schedules::exists( $id ) ? Schedules::schedule( $id ) : null;
		$states   = $schedule ? self::states( $id ) : [];
		$teams    = self::manageable_teams();
		$manager  = ! UserRoles::is_entree() && UserRoles::can_manage_training();
		if ( $schedule ) {
			foreach ( $schedule['blocks'] as &$block ) {
				$day                 = $monday->modify( '+' . ( $block['day'] - 1 ) . ' days' );
				$block['date']       = $day->format( 'Y-m-d' );
				$future              = new \DateTimeImmutable( $block['date'] . ' ' . $block['start'], wp_timezone() ) > current_datetime();
				$block['attendance'] = [];
				foreach ( $block['team_ids'] as $index => $team_id ) {
					$block['attendance'][] = self::state( $states, $block['date'], $block['block_id'], $team_id ) + [
						'team_id'    => $team_id,
						'name'       => $block['team_names'][ $index ],
						'can_change' => $future && get_post_status( $team_id ) === 'publish' && ( $manager || in_array( $team_id, $teams, true ) ),
					];
				}
				$block['cancelled'] = count( $block['attendance'] ) > 0 && count( array_filter( $block['attendance'], static fn( $item ) => ! $item['cancelled'] ) ) === 0;
			}
			unset( $block );
		}
		return [
			'week_start' => $monday->format( 'Y-m-d' ),
			'week_end'   => $monday->modify( '+6 days' )->format( 'Y-m-d' ),
			'schedule'   => $schedule,
			'pitches'    => Schedules::settings()['pitches'],
			'timezone'   => wp_timezone_string(),
		];
	}

	/** Called under the same lock as schedule edits and activation. */
	public static function change( array $data ) {
		$keys = [ 'schedule_id', 'schedule_revision', 'block_id', 'date', 'team_id', 'cancelled', 'revision' ];
		if ( array_diff( array_keys( $data ), $keys ) || array_diff( $keys, array_keys( $data ) ) || ! is_int( $data['schedule_id'] ) || ! is_int( $data['schedule_revision'] ) || ! is_int( $data['team_id'] ) || ! is_int( $data['revision'] ) || ! is_bool( $data['cancelled'] ) || ! is_string( $data['block_id'] ) || ! self::date( $data['date'] ) ) {
			return Schedules::error( 'Controleer de training, datum en afmelding.' );
		}
		$id = $data['schedule_id'];
		if ( $id !== (int) get_option( Schedules::ACTIVE, 0 ) || ! Schedules::exists( $id ) ) {
			return Schedules::error( 'Het actieve schema is gewijzigd. Vernieuw het weekoverzicht.', 409 );
		}
		$schedule = Schedules::schedule( $id );
		if ( $data['schedule_revision'] !== $schedule['revision'] ) {
			return Schedules::error( 'De trainingstijden zijn gewijzigd. Vernieuw het weekoverzicht.', 409 );
		}
		$blocks = array_column( $schedule['blocks'], null, 'block_id' );
		$block  = $blocks[ $data['block_id'] ] ?? null;
		if ( ! $block || ! in_array( $data['team_id'], $block['team_ids'], true ) || get_post_status( $data['team_id'] ) !== 'publish' || (int) self::date( $data['date'] )->format( 'N' ) !== (int) $block['day'] ) {
			return Schedules::error( 'Deze training staat niet op deze datum voor dit team gepland.', 404 );
		}
		if ( ! UserRoles::can_manage_training() && ! in_array( $data['team_id'], self::manageable_teams(), true ) ) {
			return Schedules::error( 'Alleen de trainer of leider van dit team kan deze training afmelden.', 403 );
		}
		if ( new \DateTimeImmutable( $data['date'] . ' ' . $block['start'], wp_timezone() ) <= current_datetime() ) {
			return Schedules::error( 'Deze training is al begonnen. De afmelding kan niet meer worden gewijzigd.', 409 );
		}
		$state = self::state( self::states( $id ), $data['date'], $data['block_id'], $data['team_id'] );
		if ( $state['revision'] !== $data['revision'] ) {
			return Schedules::error( 'De afmelding is intussen gewijzigd. Vernieuw het weekoverzicht.', 409 );
		}
		if ( $state['cancelled'] !== $data['cancelled'] ) {
			$events   = Fields::get_for_post( $id, 'cancellations' ) ?: [];
			$events[] = [
				'date'       => $data['date'],
				'block_id'   => $data['block_id'],
				'team_id'    => $data['team_id'],
				'cancelled'  => $data['cancelled'],
				'revision'   => $state['revision'] + 1,
				'changed_by' => get_current_user_id(),
				'changed_at' => current_datetime()->format( DATE_ATOM ),
			];
			$saved    = Fields::update_many_for_post( $id, [ 'cancellations' => $events ] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		return self::week( $data['date'] );
	}
}
