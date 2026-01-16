<?php
/**
 * Credential Encryption Class
 *
 * Provides secure encryption/decryption for OAuth tokens and CalDAV credentials
 * using sodium encryption (available via WordPress/PHP).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Credential_Encryption {

	/**
	 * Get the encryption key derived from WordPress AUTH_KEY
	 *
	 * @return string 32-byte encryption key
	 */
	private static function get_key(): string {
		// Use WordPress AUTH_KEY as basis, hash to proper length for sodium
		return hash( 'sha256', AUTH_KEY . 'prm_calendar', true );
	}

	/**
	 * Encrypt credentials array to base64 string
	 *
	 * @param array $data Credentials array to encrypt
	 * @return string Base64-encoded encrypted string (nonce + ciphertext)
	 */
	public static function encrypt( array $data ): string {
		$json       = wp_json_encode( $data );
		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $json, $nonce, self::get_key() );

		return base64_encode( $nonce . $ciphertext );
	}

	/**
	 * Decrypt base64 string back to credentials array
	 *
	 * @param string $encrypted Base64-encoded encrypted string
	 * @return array|null Decrypted credentials array, or null on failure
	 */
	public static function decrypt( string $encrypted ): ?array {
		try {
			$decoded = base64_decode( $encrypted, true );

			if ( $decoded === false ) {
				return null;
			}

			// Minimum length: nonce (24 bytes) + MAC (16 bytes) = 40 bytes
			$min_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

			if ( strlen( $decoded ) < $min_length ) {
				return null;
			}

			$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

			$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, self::get_key() );

			if ( $plaintext === false ) {
				return null;
			}

			$result = json_decode( $plaintext, true );

			if ( ! is_array( $result ) ) {
				return null;
			}

			return $result;
		} catch ( Exception $e ) {
			return null;
		}
	}
}
