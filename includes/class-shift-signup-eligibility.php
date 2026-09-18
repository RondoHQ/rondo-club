<?php
/** Shared certificate and pool rules for signup, assignment and recruitment mail. */
namespace Rondo\Volunteer;

use Rondo\Core\VolunteerStatus;

final class ShiftSignupEligibility {
	public static function block_reason( int $shift_id, int $person_id, ?array $blocks = null ): ?string {
		$blocks         = $blocks ?? self::signup_blocks( $person_id );
		$dienst_type_id = (int) get_post_meta( $shift_id, 'dienst_type_id', true );
		if ( $dienst_type_id <= 0 ) {
			return null;
		}

		if ( (bool) get_post_meta( $dienst_type_id, 'vog_required', true ) && in_array( 'vog', $blocks, true ) ) {
			return 'vog';
		}

		$requires_iva = (bool) get_post_meta( $dienst_type_id, 'iva_required', true );
		if ( $requires_iva && ! (bool) get_post_meta( $shift_id, 'iva_waived', true ) && in_array( 'iva', $blocks, true ) ) {
			return 'iva';
		}

		$required_pool = (int) get_post_meta( $dienst_type_id, 'required_pool', true );
		if ( $required_pool > 0 && ! self::person_is_pool_member( $person_id, $required_pool ) ) {
			return 'pool';
		}

		return null;
	}
	public static function signup_blocks( int $person_id ): array {
		$blocks = [];

		$datum_vog = (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'datum_vog' );
		// VOG validity = 3 years (existing convention from class-rest-vog.php).
		if ( $datum_vog === '' || strtotime( $datum_vog . ' +3 years' ) < time() ) {
			$blocks[] = 'vog';
		}

		if ( ! IvaStatus::is_valid( $person_id ) ) {
			$blocks[] = 'iva';
		}

		return $blocks;
	}
	private static function person_is_pool_member( int $person_id, int $commissie_id ): bool {
		$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' );
		if ( ! is_array( $work_history ) ) {
			return false;
		}

		foreach ( $work_history as $position ) {
			$team_id = (int) ( $position['team'] ?? 0 );
			if ( $team_id !== $commissie_id ) {
				continue;
			}
			if ( VolunteerStatus::is_position_current( $position ) ) {
				return true;
			}
		}
		return false;
	}
}
