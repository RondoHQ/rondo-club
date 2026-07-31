<?php
/**
 * Shared sponsor-role helpers.
 *
 * A person can be both a Sportlink member and an active sponsor. Person type
 * describes the base record (`member` or `contact`); sponsorship is always an
 * independent role stored in the is_sponsor flag.
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SponsorStatus {

	/**
	 * Determine whether a person currently has the sponsor role.
	 *
	 * @param int $person_id Person post ID.
	 * @return bool
	 */
	public static function is_sponsor( int $person_id ): bool {
		return self::value_is_true( get_post_meta( $person_id, 'is_sponsor', true ) );
	}

	/**
	 * Resolve sponsor status from already-loaded field values.
	 *
	 * @param mixed $value Stored sponsor flag.
	 * @return bool
	 */
	public static function value_is_true( $value ): bool {
		return in_array( $value, [ true, 1, '1', 'yes', 'true' ], true );
	}

	/**
	 * Determine whether a sponsor is also a member/parent record.
	 *
	 * @param int $person_id Person post ID.
	 * @return bool
	 */
	public static function is_dual_role( int $person_id ): bool {
		return self::is_sponsor( $person_id )
			&& sanitize_key( (string) get_post_meta( $person_id, 'person_type', true ) ) !== 'contact';
	}
}
