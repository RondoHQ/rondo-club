<?php
/**
 * Person communication policy.
 *
 * @package Rondo\People
 */

namespace Rondo\People;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central source of truth for whether person-directed communication is allowed.
 */
final class CommunicationPolicy {

	/** Whether Sportlink has recorded a date of death for this person. */
	public static function is_deceased( int $person_id ): bool {
		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return false;
		}

		return trim( (string) Fields::get_for_post( $person_id, 'datum_overlijden' ) ) !== '';
	}

	/** Whether automated person-directed communication may be sent. */
	public static function may_contact( int $person_id ): bool {
		return ! self::is_deceased( $person_id );
	}

	/**
	 * Return the person's valid email addresses unless communication is blocked.
	 *
	 * @return string[]
	 */
	public static function email_addresses( int $person_id ): array {
		if ( ! self::may_contact( $person_id ) ) {
			return [];
		}

		$emails = [];
		foreach ( [ 'email_1', 'email_2' ] as $field_name ) {
			$email = sanitize_email( trim( (string) Fields::get_for_post( $person_id, $field_name ) ) );
			if ( is_email( $email ) ) {
				$emails[] = strtolower( $email );
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/** Return the first contactable email address, if any. */
	public static function primary_email( int $person_id ): ?string {
		$emails = self::email_addresses( $person_id );
		return $emails[0] ?? null;
	}
}
