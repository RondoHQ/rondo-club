<?php
/**
 * Shared person-name formatting.
 *
 * @package Rondo\People
 */

namespace Rondo\People;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Formats names in the same order as the frontend formatPersonName helper. */
class PersonName {

	/**
	 * Format personal name fields, omitting empty parts.
	 *
	 * @param string $first_name First name.
	 * @param string $infix      Surname prefix.
	 * @param string $last_name  Last name.
	 * @return string Full personal name.
	 */
	public static function format( string $first_name, string $infix, string $last_name ): string {
		return implode( ' ', array_filter( array_map( 'trim', [ $first_name, $infix, $last_name ] ) ) );
	}

	/**
	 * Read and format the current canonical name fields.
	 *
	 * @param int $person_id Person post ID.
	 * @return string Full personal name, or empty when no name fields exist.
	 */
	public static function get( int $person_id ): string {
		return self::format(
			(string) Fields::get_for_post( $person_id, 'first_name' ),
			(string) Fields::get_for_post( $person_id, 'infix' ),
			(string) Fields::get_for_post( $person_id, 'last_name' )
		);
	}
}
