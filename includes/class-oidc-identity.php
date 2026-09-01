<?php
/**
 * OpenID Connect identity eligibility and durable email proof.
 *
 * @package Rondo\Identity
 */

namespace Rondo\Identity;

use Rondo\Fields\Fields;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve the narrow identity Rondo may expose to first-party OIDC clients. */
final class OidcIdentity {

	public const META_SUBJECT         = 'rondo_oidc_subject';
	public const META_VERIFIED_EMAIL  = 'rondo_oidc_verified_email';
	public const META_VERIFIED_AT     = 'rondo_oidc_verified_email_at';
	public const META_VERIFIED_METHOD = 'rondo_oidc_verified_email_method';
	public const META_AUTH_TIME       = 'rondo_oidc_last_auth_time';

	private const METHODS = [ 'activation', 'magic_login', 'profile_email_change', 'oidc_email' ];

	public function __construct() {
		add_action( 'magic_login_logged_in', [ self::class, 'record_magic_login' ], 10, 2 );
		add_action( 'wp_login', [ self::class, 'record_auth_time' ], 10, 2 );
	}

	/** Store the time of a real WordPress authentication event. */
	public static function record_auth_time( string $user_login, \WP_User $user ): void {
		unset( $user_login );
		update_user_meta( $user->ID, self::META_AUTH_TIME, time() );
	}

	/** A successfully consumed Magic Login link proves the current exact address. */
	public static function record_magic_login( \WP_User $user, $token = null ): void {
		unset( $token );
		$email = self::external_email( $user->ID );
		if ( $email !== null ) {
			self::mark_email_verified( $user->ID, $email, 'magic_login' );
		}
	}

	/** Record an approved emailed proof for the exact currently resolved address. */
	public static function mark_email_verified( int $user_id, string $email, string $method ) {
		$email   = self::normalize_email( $email );
		$current = self::external_email( $user_id );
		if ( $email === '' || $current === null || ! hash_equals( $current, $email ) ) {
			return new \WP_Error( 'rondo_oidc_email_not_current', 'Het bevestigde e-mailadres is niet het huidige accountadres.' );
		}
		if ( ! in_array( $method, self::METHODS, true ) ) {
			return new \WP_Error( 'rondo_oidc_verification_method_invalid', 'Deze verificatiemethode is niet toegestaan.' );
		}
		if ( ! self::email_is_unique( $user_id, $email ) ) {
			return new \WP_Error( 'rondo_oidc_email_ambiguous', 'Dit e-mailadres hoort bij meerdere FreeScout-gerechtigde accounts.' );
		}

		update_user_meta( $user_id, self::META_VERIFIED_EMAIL, $email );
		update_user_meta( $user_id, self::META_VERIFIED_AT, gmdate( DATE_ATOM ) );
		update_user_meta( $user_id, self::META_VERIFIED_METHOD, $method );

		return true;
	}

	/** Return an eligible identity, optionally requiring durable exact-email proof. */
	public static function resolve( int $user_id, bool $require_verified = true ) {
		$base = self::base_eligibility( $user_id );
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$email = self::external_email( $user_id );
		if ( $email === null ) {
			return new \WP_Error( 'rondo_oidc_email_unavailable', 'Dit account heeft geen bruikbaar extern e-mailadres.' );
		}
		if ( ! self::email_is_unique( $user_id, $email ) ) {
			return new \WP_Error( 'rondo_oidc_email_ambiguous', 'Dit e-mailadres hoort bij meerdere FreeScout-gerechtigde accounts.' );
		}

		$verified_email = self::normalize_email( (string) get_user_meta( $user_id, self::META_VERIFIED_EMAIL, true ) );
		$is_verified    = $verified_email !== '' && hash_equals( $email, $verified_email );
		if ( $require_verified && ! $is_verified ) {
			return new \WP_Error( 'rondo_oidc_email_verification_required', 'Bevestig eerst dit e-mailadres voor FreeScout.' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error( 'rondo_oidc_user_unavailable', 'Dit Rondo-account is niet beschikbaar.' );
		}
		$subject = self::subject( $user_id );
		if ( $subject === '' ) {
			return new \WP_Error( 'rondo_oidc_subject_unavailable', 'De vaste Rondo-identiteit kon niet worden aangemaakt.' );
		}

		return [
			'user_id'        => $user_id,
			'sub'            => $subject,
			'email'          => $email,
			'email_verified' => $is_verified,
			'name'           => $user->display_name,
			'given_name'     => (string) $user->first_name,
			'family_name'    => (string) $user->last_name,
			'auth_time'      => (int) get_user_meta( $user_id, self::META_AUTH_TIME, true ) ?: time(),
		];
	}

	/** Return UserInfo claims restricted by the granted scopes. */
	public static function claims( array $identity, array $scopes ): array {
		$claims = [ 'sub' => $identity['sub'] ];
		if ( in_array( 'email', $scopes, true ) ) {
			$claims['email']          = $identity['email'];
			$claims['email_verified'] = ! empty( $identity['email_verified'] );
		}
		if ( in_array( 'profile', $scopes, true ) ) {
			$claims['name']        = $identity['name'];
			$claims['given_name']  = $identity['given_name'];
			$claims['family_name'] = $identity['family_name'];

			$picture = (string) apply_filters( 'rondo_oidc_picture_url', '', $identity['user_id'] );
			if ( self::is_same_origin_url( $picture ) ) {
				$claims['picture'] = $picture;
			}
		}

		return $claims;
	}

	/** Resolve the exact external address used for identity claims. */
	public static function external_email( int $user_id ): ?string {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return null;
		}

		$contact = self::normalize_email( (string) get_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, true ) );
		$email   = $contact !== '' ? $contact : self::normalize_email( (string) $user->user_email );
		if ( $email === '' || UserProvisioning::is_synthetic_email( $email ) ) {
			return null;
		}

		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		if ( $person_id > 0 ) {
			$person_emails = array_filter(
				[
					self::normalize_email( (string) Fields::try_get_for_post( $person_id, 'email_1' ) ),
					self::normalize_email( (string) Fields::try_get_for_post( $person_id, 'email_2' ) ),
				]
			);
			if ( ! in_array( $email, $person_emails, true ) ) {
				return null;
			}
		}

		return $email;
	}

	/** Return or create the stable opaque subject stored in user meta. */
	public static function subject( int $user_id ): string {
		$subject = (string) get_user_meta( $user_id, self::META_SUBJECT, true );
		if ( preg_match( '/^[A-Za-z0-9_-]{43}$/', $subject ) ) {
			return $subject;
		}

		$subject = self::random_value( 32 );
		if ( add_user_meta( $user_id, self::META_SUBJECT, $subject, true ) ) {
			return $subject;
		}

		$stored = (string) get_user_meta( $user_id, self::META_SUBJECT, true );

		return preg_match( '/^[A-Za-z0-9_-]{43}$/', $stored ) ? $stored : '';
	}

	/** Eligibility excluding the address and its proof, used to detect shared mailboxes. */
	private static function base_eligibility( int $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || (int) $user->user_status !== 0 || ! empty( $user->spam ) || ! empty( $user->deleted ) ) {
			return new \WP_Error( 'rondo_oidc_user_unavailable', 'Dit Rondo-account is niet beschikbaar.' );
		}

		$is_admin     = user_can( $user_id, 'manage_options' );
		$capabilities = (array) apply_filters( 'rondo_oidc_freescout_capabilities', [ 'ledenadministratie' ] );
		$has_access   = $is_admin;
		foreach ( $capabilities as $capability ) {
			if ( is_string( $capability ) && $capability !== '' && user_can( $user_id, $capability ) ) {
				$has_access = true;
				break;
			}
		}
		if ( ! $has_access ) {
			return new \WP_Error( 'rondo_oidc_access_denied', 'Dit account heeft geen FreeScout-toegang.' );
		}

		if ( ! $is_admin ) {
			$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
			$person    = $person_id > 0 ? get_post( $person_id ) : null;
			if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
				return new \WP_Error( 'rondo_oidc_person_unavailable', 'Het gekoppelde ledenprofiel is niet beschikbaar.' );
			}
		}

		return true;
	}

	/** Whether no other currently eligible account resolves to this address. */
	private static function email_is_unique( int $user_id, string $email ): bool {
		$candidates = get_users(
			[
				'meta_key'   => UserProvisioning::META_CONTACT_EMAIL,
				'meta_value' => $email,
				'fields'     => 'ID',
				'number'     => -1,
			]
		);
		$wordpress  = get_user_by( 'email', $email );
		if ( $wordpress instanceof \WP_User ) {
			$candidates[] = $wordpress->ID;
		}

		foreach ( array_unique( array_map( 'intval', $candidates ) ) as $candidate_id ) {
			if ( $candidate_id === $user_id || is_wp_error( self::base_eligibility( $candidate_id ) ) ) {
				continue;
			}
			$candidate_email = self::external_email( $candidate_id );
			if ( $candidate_email !== null && hash_equals( $email, $candidate_email ) ) {
				return false;
			}
		}

		return true;
	}

	private static function normalize_email( string $email ): string {
		$email = strtolower( trim( sanitize_email( $email ) ) );

		return is_email( $email ) ? $email : '';
	}

	private static function is_same_origin_url( string $url ): bool {
		if ( $url === '' ) {
			return false;
		}
		$origin = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( $url );

		return is_array( $origin )
			&& is_array( $target )
			&& strtolower( (string) ( $origin['scheme'] ?? '' ) ) === strtolower( (string) ( $target['scheme'] ?? '' ) )
			&& strtolower( (string) ( $origin['host'] ?? '' ) ) === strtolower( (string) ( $target['host'] ?? '' ) )
			&& (int) ( $origin['port'] ?? 0 ) === (int) ( $target['port'] ?? 0 );
	}

	private static function random_value( int $bytes ): string {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}
}
