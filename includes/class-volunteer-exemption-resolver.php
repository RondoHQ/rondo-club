<?php
/**
 * VolunteerExemptionResolver
 *
 * Single source of truth for whether a person is exempt from the
 * 2-diensten-per-jaar volunteer obligation. Consumed by the eligibility
 * derivation (#1) and the obligation counter (#6) so we never duplicate
 * the rules.
 *
 * Four auto-routes + one manual route:
 *   1. Active commissie member          (any commissie counts)
 *   2. Active staff role (trainer etc.) (configurable list, OPTION_STAFF_ROLES)
 *   3. Betaalde vrijwilliger             (ACF betaalde_vrijwilliger flag)
 *   4. Handmatige vrijstelling           (ACF vrijgesteld_handmatig + optional seizoen)
 *
 * The Sportlink `huidig-vrijwilliger` flag is intentionally NOT consulted —
 * we trust the explicit sources (commissie, work_history, ACF flags) because
 * the Sportlink semantics are ambiguous.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Core\VolunteerStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VolunteerExemptionResolver {

	const REASON_COMMISSIE = 'commissie';
	const REASON_STAFF     = 'staff';
	const REASON_PAID      = 'betaald';
	const REASON_MANUAL    = 'handmatig';

	/**
	 * Determine whether a person is exempt from the 2-diensten-plicht for a given season.
	 *
	 * @param int    $person_id Person post ID.
	 * @param string $season    KNVB season string ("2026-2027"). Used for handmatige seizoen-specific vrijstellingen.
	 * @return bool True if exempt.
	 */
	public static function is_exempt( int $person_id, string $season ): bool {
		return self::resolve( $person_id, $season ) !== null;
	}

	/**
	 * Resolve exemption to one of the REASON_* constants, or null if not exempt.
	 *
	 * Routes are evaluated in priority order; the first matching reason wins.
	 *
	 * @param int    $person_id Person post ID.
	 * @param string $season    KNVB season string ("2026-2027").
	 * @return string|null One of self::REASON_* or null when the person owes diensten.
	 */
	public static function resolve( int $person_id, string $season ): ?string {
		if ( $person_id <= 0 ) {
			return null;
		}

		if ( self::has_active_commissie( $person_id ) ) {
			return self::REASON_COMMISSIE;
		}

		if ( self::has_active_staff_role( $person_id ) ) {
			return self::REASON_STAFF;
		}

		if ( self::is_paid_volunteer( $person_id ) ) {
			return self::REASON_PAID;
		}

		if ( self::has_manual_exemption( $person_id, $season ) ) {
			return self::REASON_MANUAL;
		}

		return null;
	}

	/**
	 * Resolve the exemption that applies to an obligation unit.
	 *
	 * A gezin obligation belongs to the responsible adults together. If one of
	 * those adults is exempt, the shared gezin obligation is exempt as well. The
	 * triggering children are only considered for orphan units, where no adult
	 * could be resolved from relationships or the address fallback.
	 *
	 * @param array  $unit   Eligibility unit from VolunteerEligibilityService.
	 * @param string $season KNVB season string ("2026-2027").
	 * @return array{person_id:int,reason:string}|null Matching person and reason.
	 */
	public static function resolve_unit( array $unit, string $season ): ?array {
		$person_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $unit['person_ids'] ?? [] ) ) ) ) );
		if ( empty( $person_ids ) ) {
			return null;
		}

		if ( ( $unit['kind'] ?? '' ) === VolunteerEligibilityService::UNIT_KIND_GEZIN ) {
			$trigger_ids = array_map( 'intval', (array) ( $unit['trigger_person_ids'] ?? [] ) );
			$adults      = array_values( array_diff( $person_ids, $trigger_ids ) );
			if ( ! empty( $adults ) ) {
				$person_ids = $adults;
			}
		}

		foreach ( $person_ids as $person_id ) {
			$reason = self::resolve( $person_id, $season );
			if ( $reason !== null ) {
				return [
					'person_id' => $person_id,
					'reason'    => $reason,
				];
			}
		}

		return null;
	}

	/**
	 * Build a human-readable label for an exemption reason.
	 *
	 * @param string $reason One of self::REASON_*.
	 * @return string Dutch label suitable for UI.
	 */
	public static function reason_label( string $reason ): string {
		switch ( $reason ) {
			case self::REASON_COMMISSIE:
				return 'Actief commissielid';
			case self::REASON_STAFF:
				return 'Actieve staf-rol (trainer/leider/teammanager)';
			case self::REASON_PAID:
				return 'Betaalde vrijwilliger';
			case self::REASON_MANUAL:
				return 'Handmatige vrijstelling';
			default:
				return $reason;
		}
	}

	/**
	 * Route 1: is the person currently active in any commissie?
	 *
	 * Mirrors VolunteerStatus::is_volunteer_position() logic but only for
	 * commissie-typed work_history entries. Exempt commissies (rondo_vog_exempt_commissies)
	 * are intentionally still counted here — for the 2-diensten-plicht we care about
	 * actual commissie participation, not VOG scope.
	 */
	public static function has_active_commissie( int $person_id ): bool {
		$work_history = get_field( 'work_history', $person_id );
		if ( empty( $work_history ) || ! is_array( $work_history ) ) {
			return false;
		}

		$today = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		foreach ( $work_history as $position ) {
			if ( ! self::is_position_current( $position, $today ) ) {
				continue;
			}

			$entity_type = $position['entity_type'] ?? '';
			if ( $entity_type === 'commissie' ) {
				return true;
			}

			if ( ! empty( $position['team'] ) ) {
				$post_type = get_post_type( $position['team'] );
				if ( $post_type === 'commissie' ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Route 2: is the person currently in a staff role (trainer, leider, teammanager, …)?
	 *
	 * The list is configurable via VolunteerStatus::OPTION_STAFF_ROLES so the
	 * board can refine it without code changes.
	 */
	public static function has_active_staff_role( int $person_id ): bool {
		$work_history = get_field( 'work_history', $person_id );
		if ( empty( $work_history ) || ! is_array( $work_history ) ) {
			return false;
		}

		$staff_roles = VolunteerStatus::get_staff_roles();
		if ( empty( $staff_roles ) ) {
			return false;
		}

		$today = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

		foreach ( $work_history as $position ) {
			if ( ! self::is_position_current( $position, $today ) ) {
				continue;
			}

			$job_title = trim( (string) ( $position['job_title'] ?? '' ) );
			if ( $job_title === '' ) {
				continue;
			}

			foreach ( $staff_roles as $staff_role ) {
				if ( strcasecmp( $job_title, (string) $staff_role ) === 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Route 3: is the person flagged as a paid volunteer (and still within the optional end-date)?
	 */
	public static function is_paid_volunteer( int $person_id ): bool {
		$paid = get_post_meta( $person_id, 'betaalde_vrijwilliger', true );
		if ( ! self::truthy( $paid ) ) {
			return false;
		}

		$tot = trim( (string) get_post_meta( $person_id, 'vergoeding_tot', true ) );
		if ( $tot === '' ) {
			return true; // doorlopend
		}

		$today = gmdate( 'Y-m-d' );
		return $tot >= $today;
	}

	/**
	 * Route 4: is the person manually exempt for this season (or doorlopend)?
	 */
	public static function has_manual_exemption( int $person_id, string $season ): bool {
		$manual = get_post_meta( $person_id, 'vrijgesteld_handmatig', true );
		if ( ! self::truthy( $manual ) ) {
			return false;
		}

		$stored = trim( (string) get_post_meta( $person_id, 'vrijstelling_seizoen', true ) );

		// Empty seizoen = doorlopend.
		if ( $stored === '' ) {
			return true;
		}

		return $stored === $season;
	}

	/**
	 * Mirror of VolunteerStatus::is_position_current() — duplicated locally so
	 * this resolver has no friend-method dependency. Kept in sync by convention.
	 */
	private static function is_position_current( array $position, string $today ): bool {
		if ( ! empty( $position['is_current'] ) ) {
			return true;
		}

		$end_date = (string) ( $position['end_date'] ?? '' );

		if ( $end_date === '' ) {
			return ! empty( $position['start_date'] ) || ! empty( $position['team'] );
		}

		return $end_date >= $today;
	}

	/**
	 * ACF/post_meta stores booleans inconsistently (0/1, '0'/'1', true/false, ''/'1').
	 */
	private static function truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value === 1;
		}
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return in_array( $value, [ '1', 'true', 'yes', 'ja' ], true );
		}
		return false;
	}
}
