<?php
/**
 * Normalize phone numbers to E.164 format on save
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PhoneNormalizer {

	/**
	 * Phone fields to normalize.
	 *
	 * @var string[]
	 */
	private const PHONE_FIELDS = [
		'mobile_1',
		'mobile_2',
		'telephone_1',
		'telephone_2',
	];

	public function normalize_native_field( $value, $post_id, array $field, $old_value ) {
		return in_array( $field['canonical_name'], self::PHONE_FIELDS, true )
			? self::normalize( $value )
			: $value;
	}

	/** Normalize a standalone value for forms that do not write through core REST. */
	public static function normalize( $value ) {
		return ( new self( false ) )->normalize_phone_number( $value, 0, [], null );
	}

	/** Optionally skip hook registration for standalone normalization. */
	public function __construct( bool $register_hook = true ) {
		if ( $register_hook ) {
			add_filter( 'rondo_fields_validate_value', [ $this, 'normalize_native_field' ], 10, 4 );
		}
	}

	/**
	 * Normalize a phone number to E.164 format.
	 *
	 * Handles Dutch local numbers (06-xxx, 020-xxx), international (+xx),
	 * and 00xx-prefixed international numbers.
	 *
	 * @param mixed $value    The value to save.
	 * @param int   $post_id  The post ID.
	 * @param array $field    The field array.
	 * @param mixed $original The original value.
	 * @return mixed
	 */
	public function normalize_phone_number( $value, $post_id, $field, $original ) {
		// Only process string values.
		if ( ! is_string( $value ) || empty( $value ) ) {
			return $value;
		}

		// Strip Unicode invisible characters.
		$value = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', $value );

		// Trim whitespace.
		$value = trim( $value );

		// If empty after cleaning, return empty string.
		if ( $value === '' ) {
			return '';
		}

		// Already international format: starts with +.
		if ( $value[0] === '+' ) {
			// Keep leading +, strip everything except digits from the rest.
			return '+' . preg_replace( '/[^0-9]/', '', substr( $value, 1 ) );
		}

		// International with 00 prefix (e.g., 0031612345678).
		if ( str_starts_with( $value, '00' ) ) {
			$digits = preg_replace( '/[^0-9]/', '', substr( $value, 2 ) );
			return '+' . $digits;
		}

		// Dutch local number starting with 0 (e.g., 06-12345678, 020-1234567).
		if ( $value[0] === '0' ) {
			$digits = preg_replace( '/[^0-9]/', '', substr( $value, 1 ) );
			return '+31' . $digits;
		}

		// Edge case: digits only without leading 0 or +.
		return preg_replace( '/[^0-9]/', '', $value );
	}
}
