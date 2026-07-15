<?php
/**
 * IvaStatus
 *
 * Helper for the IVA (Instructie Verantwoord Alcoholschenken) certificate
 * lifecycle. Bestuursbesluit 2026-05-26: IVA certificates are valid for
 * 5 jaar after the `datum-iva` date.
 *
 *   missing  : no datum-iva AND no certificaat uploaded
 *   pending  : something uploaded but not yet approved by bestuurslid kantine
 *   valid    : approved AND datum-iva within the 5-year window
 *   expired  : approved BUT datum-iva older than 5 jaar
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
	const STATUS_EXPIRED = 'expired';

	/**
	 * Validity term per the 2026-05-26 bestuursbesluit.
	 */
	const VALIDITY_YEARS = 5;

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
		$cert     = get_field( 'iva-certificaat', $person_id );
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

		$expires_at = self::expires_at( $person_id );
		if ( $expires_at === null ) {
			return self::STATUS_VALID;
		}

		return $expires_at >= gmdate( 'Y-m-d' ) ? self::STATUS_VALID : self::STATUS_EXPIRED;
	}

	/**
	 * Convenience: is the IVA currently valid (approved + not expired)?
	 */
	public static function is_valid( int $person_id ): bool {
		return self::status( $person_id ) === self::STATUS_VALID;
	}

	/**
	 * Expiration date for the IVA certificate, or null when datum-iva is missing.
	 * Formula: datum-iva + VALIDITY_YEARS.
	 */
	public static function expires_at( int $person_id ): ?string {
		$datum = self::datum_iva( $person_id );
		if ( empty( $datum ) ) {
			return null;
		}

		$timestamp = strtotime( $datum . ' +' . self::VALIDITY_YEARS . ' years' );
		if ( $timestamp === false ) {
			return null;
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Is the certificate within the reminder window (expires within REMINDER_MONTHS_BEFORE_EXPIRY)?
	 */
	public static function needs_renewal_reminder( int $person_id ): bool {
		$expires_at = self::expires_at( $person_id );
		if ( $expires_at === null ) {
			return false;
		}

		$now    = strtotime( gmdate( 'Y-m-d' ) );
		$expiry = strtotime( $expires_at );
		if ( $now === false || $expiry === false || $expiry < $now ) {
			return false;
		}

		$diff_days = (int) floor( ( $expiry - $now ) / DAY_IN_SECONDS );
		$threshold = self::REMINDER_MONTHS_BEFORE_EXPIRY * 30;

		return $diff_days <= $threshold;
	}

	/**
	 * Read the datum-iva, tolerating string / null / DateTime values.
	 */
	private static function datum_iva( int $person_id ): string {
		$raw = get_field( 'datum-iva', $person_id );
		if ( is_string( $raw ) ) {
			return trim( $raw );
		}
		// Some ACF return formats give back DateTime — coerce to Y-m-d.
		if ( $raw instanceof \DateTimeInterface ) {
			return $raw->format( 'Y-m-d' );
		}
		$post_meta = get_post_meta( $person_id, 'datum-iva', true );
		return is_string( $post_meta ) ? trim( $post_meta ) : '';
	}

	/**
	 * Read the iva-approved flag, tolerating ACF/post_meta truthy inconsistency.
	 */
	public static function is_approved( int $person_id ): bool {
		$value = get_field( 'iva-approved', $person_id );
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
