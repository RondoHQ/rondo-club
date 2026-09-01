<?php
/**
 * OpenID Connect RS256 signing-key storage and rotation.
 *
 * @package Rondo\Identity
 */

namespace Rondo\Identity;

use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep one active private key and public overlap keys in a non-autoloaded option. */
final class OidcKeyStore {

	public const OPTION_KEYS     = 'rondo_oidc_signing_keys';
	public const OVERLAP_SECONDS = DAY_IN_SECONDS;

	/** Return the public JSON Web Key Set. */
	public static function jwks(): array {
		$store = self::store();
		if ( is_wp_error( $store ) ) {
			return [ 'keys' => [] ];
		}

		$keys = [ $store['current']['jwk'] ];
		foreach ( (array) ( $store['previous'] ?? [] ) as $previous ) {
			if ( (int) ( $previous['retire_at'] ?? 0 ) >= time() && is_array( $previous['jwk'] ?? null ) ) {
				$keys[] = $previous['jwk'];
			}
		}

		return [ 'keys' => $keys ];
	}

	/** Sign an ID-token claim set with the current key. */
	public static function sign( array $claims ) {
		$store = self::store();
		if ( is_wp_error( $store ) ) {
			return $store;
		}

		$private = CredentialEncryption::decrypt( (string) $store['current']['private_key'] );
		$pem     = is_array( $private ) ? (string) ( $private['pem'] ?? '' ) : '';
		$key     = $pem !== '' ? openssl_pkey_get_private( $pem ) : false;
		if ( $key === false ) {
			return new \WP_Error( 'rondo_oidc_signing_key_unavailable', 'De OpenID Connect-ondertekeningssleutel is niet beschikbaar.' );
		}

		$header  = self::base64url(
			(string) wp_json_encode(
			[
				'alg' => 'RS256',
				'kid' => $store['current']['kid'],
				'typ' => 'JWT',
			]
			)
			);
		$payload = self::base64url( (string) wp_json_encode( $claims ) );
		$input   = $header . '.' . $payload;
		if ( ! openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			return new \WP_Error( 'rondo_oidc_signing_failed', 'Het ID-token kon niet worden ondertekend.' );
		}

		return $input . '.' . self::base64url( $signature );
	}

	/** Rotate immediately and retain the old public key for the overlap window. */
	public static function rotate() {
		$store = self::store();
		if ( is_wp_error( $store ) ) {
			return $store;
		}

		$previous   = self::active_previous( (array) ( $store['previous'] ?? [] ) );
		$previous[] = [
			'kid'       => $store['current']['kid'],
			'jwk'       => $store['current']['jwk'],
			'retire_at' => time() + self::OVERLAP_SECONDS,
		];
		$new        = self::generate_key();
		if ( is_wp_error( $new ) ) {
			return $new;
		}

		$updated = [
			'current'    => $new,
			'previous'   => $previous,
			'rotated_at' => gmdate( DATE_ATOM ),
		];
		update_option( self::OPTION_KEYS, $updated, false );

		return [
			'kid'              => $new['kid'],
			'created_at'       => $new['created_at'],
			'previous_key_ids' => array_values( wp_list_pluck( $previous, 'kid' ) ),
			'overlap_until'    => gmdate( DATE_ATOM, time() + self::OVERLAP_SECONDS ),
		];
	}

	/** Return public status without exposing encrypted private material. */
	public static function status(): array {
		$store = self::store();
		if ( is_wp_error( $store ) ) {
			return [ 'configured' => false ];
		}

		$previous = self::active_previous( (array) ( $store['previous'] ?? [] ) );

		return [
			'configured'         => true,
			'current_key_id'     => $store['current']['kid'],
			'current_created_at' => $store['current']['created_at'],
			'previous_key_ids'   => array_values( wp_list_pluck( $previous, 'kid' ) ),
			'rotated_at'         => $store['rotated_at'] ?? null,
		];
	}

	/** Load or lazily create the first signing key. */
	private static function store() {
		$store = get_option( self::OPTION_KEYS, [] );
		if ( is_array( $store ) && ! empty( $store['current']['private_key'] ) && ! empty( $store['current']['jwk'] ) ) {
			$active = self::active_previous( (array) ( $store['previous'] ?? [] ) );
			if ( $active !== (array) ( $store['previous'] ?? [] ) ) {
				$store['previous'] = $active;
				update_option( self::OPTION_KEYS, $store, false );
			}
			return $store;
		}

		$key = self::generate_key();
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$store = [
			'current'    => $key,
			'previous'   => [],
			'rotated_at' => null,
		];
		if ( get_option( self::OPTION_KEYS, false ) === false && ! add_option( self::OPTION_KEYS, $store, '', false ) ) {
			$winner = get_option( self::OPTION_KEYS, [] );
			if ( is_array( $winner ) && ! empty( $winner['current']['private_key'] ) && ! empty( $winner['current']['jwk'] ) ) {
				return $winner;
			}
		}
		update_option( self::OPTION_KEYS, $store, false );

		return $store;
	}

	/** Generate and encrypt one RSA private key. */
	private static function generate_key() {
		$key = openssl_pkey_new(
			[
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			]
		);
		if ( $key === false || ! openssl_pkey_export( $key, $private_pem ) ) {
			return new \WP_Error( 'rondo_oidc_key_generation_failed', 'De OpenID Connect-sleutel kon niet worden gegenereerd.' );
		}

		$details = openssl_pkey_get_details( $key );
		if ( ! is_array( $details ) || empty( $details['rsa']['n'] ) || empty( $details['rsa']['e'] ) ) {
			return new \WP_Error( 'rondo_oidc_key_details_failed', 'De publieke OpenID Connect-sleutel kon niet worden gelezen.' );
		}

		$kid = self::random_value( 16 );

		return [
			'kid'         => $kid,
			'created_at'  => gmdate( DATE_ATOM ),
			'private_key' => CredentialEncryption::encrypt( [ 'pem' => $private_pem ] ),
			'jwk'         => [
				'kty' => 'RSA',
				'use' => 'sig',
				'alg' => 'RS256',
				'kid' => $kid,
				'n'   => self::base64url( $details['rsa']['n'] ),
				'e'   => self::base64url( $details['rsa']['e'] ),
			],
		];
	}

	private static function active_previous( array $keys ): array {
		return array_values(
			array_filter(
				$keys,
				static fn( $key ): bool => is_array( $key ) && (int) ( $key['retire_at'] ?? 0 ) >= time()
			)
		);
	}

	private static function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	private static function random_value( int $bytes ): string {
		return self::base64url( random_bytes( $bytes ) );
	}
}
