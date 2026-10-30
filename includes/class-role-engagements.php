<?php
/**
 * Local classification of individual Sportlink roles, independent of sync payloads.
 *
 * @package Rondo\People
 */

namespace Rondo\People;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RoleEngagements {
	public const LABELS = [
		'unknown'     => 'Nog niet ingedeeld',
		'employee'    => 'Personeel in loondienst',
		'compensated' => 'Vrijwilliger met vergoeding',
		'volunteer'   => 'Onbetaalde vrijwilliger',
	];

	public static function can_view(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'ledenadministratie' ) || current_user_can( 'vrijwilligers' ) || \Rondo\Core\UserRoles::can_view_finances();
	}

	/** A role's identity survives reordering; a changed function or start date requires review. */
	public static function role_key( array $role ): string {
		return hash(
			'sha256',
			wp_json_encode(
			[
				(int) ( $role['team_id'] ?? $role['team'] ?? 0 ),
				mb_strtolower( trim( preg_replace( '/\s+/u', ' ', (string) ( $role['job_title'] ?? '' ) ) ) ),
				preg_replace( '/[^0-9]/', '', (string) ( $role['start_date'] ?? '' ) ),
			]
			)
			);
	}

	/** These are staff roles, including committees exempt from VOG requirements. */
	private static function eligible( array $role ): bool {
		$entity_id = (int) ( $role['team_id'] ?? $role['team'] ?? 0 );
		$type      = get_post_type( $entity_id );
		$title     = trim( (string) ( $role['job_title'] ?? '' ) );
		return in_array( $type, [ 'team', 'commissie' ], true ) && $title !== ''
			&& ! in_array( $title, VolunteerStatus::get_excluded_roles(), true )
			&& ( $type !== 'team' || ! in_array( $title, VolunteerStatus::get_player_roles(), true ) );
	}

	public static function for_person( int $person_id ): array {
		$history = Fields::get_for_post( $person_id, 'work_history' ) ?: [];
		$stored  = Fields::get_for_post( $person_id, 'role_engagements' ) ?: [];
		$types   = array_column( $stored, 'engagement_type', 'role_key' );
		$keys    = array_count_values( array_map( [ self::class, 'role_key' ], $history ) );
		$roles   = [];
		$summary = array_fill_keys( array_keys( self::LABELS ), 0 );
		foreach ( $history as $index => $role ) {
			if ( ! self::eligible( $role ) ) {
				continue;
			}
			$key     = self::role_key( $role );
			$unique  = $keys[ $key ] === 1;
			$type    = $unique && isset( self::LABELS[ $types[ $key ] ?? '' ] ) ? $types[ $key ] : 'unknown';
			$current = VolunteerStatus::is_position_current( $role );
			if ( $current ) {
				++$summary[ $type ];
			}
			$roles[] = [
				'index'           => $index,
				'role_key'        => $key,
				'engagement_type' => $type,
				'is_current'      => $current,
				'can_classify'    => $unique,
				'entity_id'       => (int) ( $role['team_id'] ?? $role['team'] ?? 0 ),
			];
		}
		return [
			'roles'   => $roles,
			'summary' => $summary,
			'version' => hash( 'sha256', wp_json_encode( [ $history, $stored ] ) ),
		];
	}

	/** Only this endpoint writes the read-only registry field; source roles remain intact. */
	public static function update( int $person_id, string $role_key, string $type, string $version ) {
		if ( ! isset( self::LABELS[ $type ] ) ) {
			return new \WP_Error( 'rondo_engagement_type_invalid', 'Kies een geldig functietype.', [ 'status' => 400 ] );
		}
		$lock      = 'rondo_role_engagement_lock_' . $person_id;
		$locked_at = (int) get_option( $lock, 0 );
		if ( $locked_at && $locked_at < time() - 60 ) {
			delete_option( $lock );
		}
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'rondo_engagement_busy', 'Deze indeling wordt bijgewerkt. Probeer het opnieuw.', [ 'status' => 409 ] );
		}
		try {
			$current = self::for_person( $person_id );
			if ( ! hash_equals( $current['version'], $version ) ) {
				return new \WP_Error( 'rondo_engagement_stale', 'De functies of indeling zijn gewijzigd. Vernieuw de pagina en controleer de functie opnieuw.', [ 'status' => 409 ] );
			}
			$matches = array_values( array_filter( $current['roles'], static fn( $role ) => $role['role_key'] === $role_key && $role['can_classify'] ) );
			if ( count( $matches ) !== 1 ) {
				return new \WP_Error( 'rondo_engagement_role_invalid', 'Deze functie ontbreekt of komt dubbel voor. Controleer de functiegegevens.', [ 'status' => 409 ] );
			}
			$before = $matches[0]['engagement_type'];
			if ( $before === $type ) {
				return $current;
			}
			$stored = array_values( array_filter( Fields::get_for_post( $person_id, 'role_engagements' ) ?: [], static fn( $row ) => $row['role_key'] !== $role_key ) );
			if ( $type !== 'unknown' ) {
				$stored[] = [
					'role_key'        => $role_key,
					'engagement_type' => $type,
				];
			}
			$result = Fields::update_many_for_post( $person_id, [ 'role_engagements' => $stored ] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			add_post_meta(
				$person_id,
				'_role_engagement_change',
				[
					'role_key'   => $role_key,
					'before'     => $before,
					'after'      => $type,
					'actor_id'   => get_current_user_id(),
					'changed_at' => current_time( 'mysql' ),
				]
				);
			return self::for_person( $person_id );
		} finally {
			delete_option( $lock );
		}
	}

	/** Current, permission-filtered counts; one person may occur in multiple categories. */
	public static function statistics(): array {
		$ids = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			]
			);
		update_meta_cache( 'post', $ids );
		$totals   = [];
		$entities = [];
		foreach ( $ids as $id ) {
			if ( CommunicationPolicy::is_deceased( (int) $id ) ) {
				continue;
			}
			foreach ( self::for_person( (int) $id )['roles'] as $role ) {
				if ( ! $role['is_current'] ) {
					continue;
				}
				$type                             = $role['engagement_type'];
				$entity                           = $role['entity_id'];
				$totals[ $type ]['people'][ $id ] = true;
				$totals[ $type ]['roles']         = ( $totals[ $type ]['roles'] ?? 0 ) + 1;
				$entities[ $entity ][ $type ]['people'][ $id ] = true;
				$entities[ $entity ][ $type ]['roles']         = ( $entities[ $entity ][ $type ]['roles'] ?? 0 ) + 1;
			}
		}
		$by_entity = [];
		foreach ( $entities as $id => $counts ) {
			$by_entity[] = [
				'id'     => $id,
				'name'   => get_the_title( $id ),
				'type'   => get_post_type( $id ),
				'counts' => self::counts( $counts ),
			];
		}
		usort( $by_entity, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
		return [
			'as_of'     => current_datetime()->format( DATE_ATOM ),
			'totals'    => self::counts( $totals ),
			'by_entity' => $by_entity,
		];
	}

	private static function counts( array $rows ): array {
		$result = [];
		foreach ( self::LABELS as $type => $label ) {
			$result[ $type ] = [
				'people' => count( $rows[ $type ]['people'] ?? [] ),
				'roles'  => $rows[ $type ]['roles'] ?? 0,
			];
		}
		return $result;
	}
}
