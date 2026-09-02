<?php
/**
 * Credential Encryption Class
 *
 * Provides secure encryption/decryption for OAuth tokens and API credentials
 * using sodium encryption (available via WordPress/PHP).
 */

namespace Rondo\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CredentialEncryption {
	private const PREFIX = 'rondo:v2:';

	/**
	 * Get the encryption key derived from WordPress AUTH_KEY
	 *
	 * @return string 32-byte encryption key
	 */
	private static function get_key(): string {
		$source = defined( 'RONDO_ENCRYPTION_KEY' ) && trim( (string) RONDO_ENCRYPTION_KEY ) !== ''
			? (string) RONDO_ENCRYPTION_KEY
			: (string) AUTH_KEY;

		return hash( 'sha256', $source . '|rondo-credentials-v2', true );
	}

	/** Legacy key used before encrypted values were versioned. */
	private static function get_legacy_key(): string {
		return hash( 'sha256', AUTH_KEY . 'rondo_calendar', true );
	}

	/** Encrypt arbitrary bytes. */
	public static function encrypt_secret( string $plaintext ): string {
		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, self::get_key() );

		return self::PREFIX . base64_encode( $nonce . $ciphertext );
	}

	/** Decrypt arbitrary bytes from a versioned value. */
	public static function decrypt_secret( string $encrypted ): ?string {
		if ( ! str_starts_with( $encrypted, self::PREFIX ) ) {
			return null;
		}

		return self::decrypt_payload( substr( $encrypted, strlen( self::PREFIX ) ), self::get_key() );
	}

	/** Whether a value uses the current encrypted storage format. */
	public static function is_encrypted( string $value ): bool {
		return str_starts_with( $value, self::PREFIX );
	}

	/** Read a secret option without exposing its ciphertext to callers. */
	public static function get_secret_option( string $option, string $default = '' ): string {
		$value = get_option( $option, $default );
		if ( ! is_string( $value ) ) {
			return $default;
		}

		if ( ! self::is_encrypted( $value ) ) {
			return $value;
		}

		return self::decrypt_secret( $value ) ?? $default;
	}

	/** Store a secret option encrypted and without autoloading it. */
	public static function update_secret_option( string $option, string $value ): bool {
		$encrypted = $value === '' ? '' : self::encrypt_secret( $value );
		$current   = get_option( $option, null );

		if ( $current === $encrypted ) {
			return true;
		}

		return update_option( $option, $encrypted, false );
	}

	/** Encrypt an existing plaintext option in place. */
	public static function migrate_secret_option( string $option ): bool {
		$value = get_option( $option, '' );
		if ( ! is_string( $value ) || $value === '' || self::is_encrypted( $value ) ) {
			return true;
		}

		return self::update_secret_option( $option, $value );
	}

	/**
	 * Encrypt credentials array to base64 string
	 *
	 * @param array $data Credentials array to encrypt
	 * @return string Base64-encoded encrypted string (nonce + ciphertext)
	 */
	public static function encrypt( array $data ): string {
		return self::encrypt_secret( (string) wp_json_encode( $data ) );
	}

	/**
	 * Decrypt base64 string back to credentials array
	 *
	 * @param string $encrypted Base64-encoded encrypted string
	 * @return array|null Decrypted credentials array, or null on failure
	 */
	public static function decrypt( string $encrypted ): ?array {
		try {
			$plaintext = self::is_encrypted( $encrypted )
				? self::decrypt_secret( $encrypted )
				: self::decrypt_payload( $encrypted, self::get_legacy_key() );
			if ( $plaintext === null ) {
				return null;
			}

			$result = json_decode( $plaintext, true );

			if ( ! is_array( $result ) ) {
				return null;
			}

			return $result;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/** Decrypt one base64-encoded secretbox payload. */
	private static function decrypt_payload( string $payload, string $key ): ?string {
		try {
			$decoded = base64_decode( $payload, true );
			$minimum = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
			if ( $decoded === false || strlen( $decoded ) < $minimum ) {
				return null;
			}

			$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plaintext  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

			return $plaintext === false ? null : $plaintext;
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
