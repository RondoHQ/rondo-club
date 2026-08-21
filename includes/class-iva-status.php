<?php
/**
 * IvaStatus
 *
 * Helper for proof of responsible alcohol service: either an IVA certificate
 * (Instructie Verantwoord Alcoholschenken) or a Social Hygiene diploma.
 * Neither proof expires after approval.
 *
 *   missing  : no datum-iva AND no certificaat uploaded
 *   pending  : something uploaded but not yet approved by bestuurslid kantine
 *   valid    : approved AND datum-iva is present
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IvaStatus {

	const STATUS_MISSING = 'missing';
	const STATUS_PENDING = 'pending';
	const STATUS_VALID   = 'valid';
	/** Legacy status value kept for consumers that still reference the constant. */
	const STATUS_EXPIRED = 'expired';

	/**
	 * Kept in the REST contract for backwards compatibility. A null term means
	 * that approved proof remains valid indefinitely.
	 */
	const VALIDITY_YEARS = null;

	/**
	 * Reminder window — when to send the verlengingsherinnering.
	 */
	const REMINDER_MONTHS_BEFORE_EXPIRY = 3;

	/**
	 * Resolve the IVA status for a person.
	 *
	 * @param int $person_id Person post ID.
	 * @return string One of self::STATUS_*.
	 */
	public static function status( int $person_id ): string {
		$datum    = self::datum_iva( $person_id );
		$cert     = \Rondo\Fields\Fields::get_for_post( $person_id, 'iva_certificaat' );
		$approved = self::is_approved( $person_id );

		if ( empty( $datum ) && empty( $cert ) ) {
			return self::STATUS_MISSING;
		}

		if ( ! $approved ) {
			return self::STATUS_PENDING;
		}

		if ( empty( $datum ) ) {
			// Approved without a datum is a data-quality anomaly — treat as pending
			// so an admin re-reviews instead of silently letting a stale approval ride.
			return self::STATUS_PENDING;
		}

		return self::STATUS_VALID;
	}

	/**
	 * Convenience: is the alcohol-service proof currently approved?
	 */
	public static function is_valid( int $person_id ): bool {
		return self::status( $person_id ) === self::STATUS_VALID;
	}

	/**
	 * Expiration date for the proof.
	 *
	 * IVA certificates and Social Hygiene diplomas do not expire. The method is
	 * retained so existing REST consumers keep receiving the same response shape.
	 */
	public static function expires_at( int $person_id ): ?string {
		unset( $person_id );
		return null;
	}

	/**
	 * Approved proof never needs an expiry reminder.
	 *
	 * Retained for backwards-compatible REST responses.
	 */
	public static function needs_renewal_reminder( int $person_id ): bool {
		unset( $person_id );
		return false;
	}

	/**
	 * Read the datum-iva, tolerating string / null / DateTime values.
	 */
	private static function datum_iva( int $person_id ): string {
		$raw = \Rondo\Fields\Fields::get_for_post( $person_id, 'datum_iva' );
		if ( is_string( $raw ) ) {
			return trim( $raw );
		}
		// Some native field return formats give back DateTime — coerce to Y-m-d.
		if ( $raw instanceof \DateTimeInterface ) {
			return $raw->format( 'Y-m-d' );
		}
		$post_meta = get_post_meta( $person_id, 'datum-iva', true );
		return is_string( $post_meta ) ? trim( $post_meta ) : '';
	}

	/**
	 * Read the iva-approved flag, tolerating native field/post_meta truthy inconsistency.
	 */
	public static function is_approved( int $person_id ): bool {
		$value = \Rondo\Fields\Fields::get_for_post( $person_id, 'iva_approved' );
		if ( $value === null ) {
			$value = get_post_meta( $person_id, 'iva-approved', true );
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value === 1;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), [ '1', 'true', 'yes' ], true );
		}
		return false;
	}
}
