<?php
/**
 * Private authenticator enrollment for shared external applications.
 */

namespace Rondo\Security;

use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AppAccess {
	public const OPTION = 'rondo_laposta_authenticator_encrypted';

	/** The exact board role grants access; overlapping staff capabilities do not. */
	public static function can_access( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		$user    = get_userdata( $user_id );
		return $user && ( user_can( $user, 'manage_options' ) || in_array( 'rondo_bestuur', (array) $user->roles, true ) );
	}

	/** Read only the encrypted format, failing closed if the key or storage is damaged. */
	public static function uri(): ?string {
		$stored = get_option( self::OPTION, '' );
		return is_string( $stored ) && $stored !== '' ? CredentialEncryption::decrypt_secret( $stored ) : null;
	}

	/** Validate the provisioning URI without echoing any submitted secret in errors. */
	public static function validate_uri( $value ) {
		$error = new \WP_Error( 'rondo_invalid_authenticator', 'Voer een geldige otpauth://totp/ URL voor Laposta in, inclusief de geheime sleutel.', [ 'status' => 400 ] );
		if ( ! is_string( $value ) || strlen( $value ) > 1024 ) {
			return $error;
		}
		$value = trim( $value );
		if ( preg_match( '/[\x00-\x20\x7f]/', $value ) || preg_match( '/%(?![0-9a-f]{2})/i', $value ) ) {
			return $error;
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'otpauth' || ( $parts['host'] ?? '' ) !== 'totp'
			|| array_intersect( [ 'user', 'pass', 'port', 'fragment' ], array_keys( $parts ) ) ) {
			return $error;
		}
		$label = rawurldecode( ltrim( $parts['path'] ?? '', '/' ) );
		if ( ! str_starts_with( $label, 'Laposta:' ) || trim( substr( $label, 8 ) ) === '' || preg_match( '/[\x00-\x1f\x7f]/', $label ) ) {
			return $error;
		}
		$params = [];
		foreach ( explode( '&', $parts['query'] ?? '' ) as $pair ) {
			$pair = explode( '=', $pair, 2 );
			$key  = rawurldecode( $pair[0] );
			if ( count( $pair ) !== 2 || isset( $params[ $key ] ) || ! in_array( $key, [ 'secret', 'issuer', 'algorithm', 'digits', 'period' ], true ) ) {
				return $error;
			}
			$params[ $key ] = rawurldecode( $pair[1] );
		}
		$secret = $params['secret'] ?? '';
		if ( ! preg_match( '/^[A-Z2-7]{16,256}$/i', $secret ) || ! in_array( strlen( $secret ) % 8, [ 0, 2, 4, 5, 7 ], true )
			|| ( $params['issuer'] ?? '' ) !== 'Laposta'
			|| ! in_array( $params['algorithm'] ?? 'SHA1', [ 'SHA1', 'SHA256', 'SHA512' ], true )
			|| ! in_array( $params['digits'] ?? '6', [ '6', '8' ], true )
			|| ! preg_match( '/^[1-9][0-9]{0,2}$/', $params['period'] ?? '30' )
			|| (int) ( $params['period'] ?? 30 ) > 300 ) {
			return $error;
		}
		return $value;
	}

	/** Non-secret page state; setup material is returned only by the reveal endpoint. */
	public static function status(): array {
		$uri        = self::uri();
		$configured = $uri !== null && ! is_wp_error( self::validate_uri( $uri ) );
		return [
			'configured' => $configured,
			'account'    => $configured ? rawurldecode( ltrim( wp_parse_url( $uri, PHP_URL_PATH ) ?? '', '/' ) ) : null,
		];
	}
}
