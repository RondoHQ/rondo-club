<?php
/**
 * Encrypted storage for credential files.
 *
 * @package Rondo\Data
 */

namespace Rondo\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PrivateCredentialStorage {
	public const APPLE  = 'apple';
	public const GOOGLE = 'google';

	private const OPTIONS = [
		self::APPLE  => 'rondo_membership_pass_apple_cert_encrypted',
		self::GOOGLE => 'rondo_membership_pass_google_service_account_encrypted',
	];

	private const NAME_OPTIONS = [
		self::APPLE  => 'rondo_membership_pass_apple_cert_filename',
		self::GOOGLE => 'rondo_membership_pass_google_service_account_filename',
	];

	/** Store and verify one credential file. */
	public static function store( string $type, string $contents, string $filename ): bool {
		if ( ! self::is_valid( $type, $contents ) || strlen( $contents ) > 2 * MB_IN_BYTES ) {
			return false;
		}

		$option    = self::option_for( $type );
		$encrypted = CredentialEncryption::encrypt_secret( $contents );
		if ( ! update_option( $option, $encrypted, false ) && get_option( $option, '' ) !== $encrypted ) {
			return false;
		}

		if ( ! hash_equals( $contents, self::get( $type ) ?? '' ) ) {
			return false;
		}

		update_option( self::NAME_OPTIONS[ $type ], sanitize_file_name( $filename ), false );
		return true;
	}

	/** Read decrypted credential bytes for internal use. */
	public static function get( string $type ): ?string {
		$encrypted = get_option( self::option_for( $type ), '' );
		if ( ! is_string( $encrypted ) || $encrypted === '' ) {
			return null;
		}

		return CredentialEncryption::decrypt_secret( $encrypted );
	}

	/** Whether encrypted credential bytes are configured. */
	public static function has( string $type ): bool {
		return self::get( $type ) !== null;
	}

	/** Return the original safe filename only. */
	public static function filename( string $type ): string {
		return sanitize_file_name( (string) get_option( self::NAME_OPTIONS[ $type ] ?? '', '' ) );
	}

	/** Materialize credential bytes in a mode-0600 temporary file for SDKs. */
	public static function temporary_path( string $type ): string {
		$contents = self::get( $type );
		if ( $contents === null ) {
			return '';
		}

		$path = tempnam( get_temp_dir(), 'rondo-credential-' );
		if ( $path === false ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $path, $contents, LOCK_EX ) === false ) {
			wp_delete_file( $path );
			return '';
		}
		chmod( $path, 0600 );
		register_shutdown_function( static fn() => wp_delete_file( $path ) );

		return $path;
	}

	/** Encrypt a legacy file and remove it only after verified storage. */
	public static function migrate_file( string $type, string $path ): bool {
		if ( self::has( $type ) || $path === '' || ! is_readable( $path ) ) {
			return self::has( $type ) || $path === '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $path );
		if ( $contents === false || ! self::store( $type, $contents, basename( $path ) ) ) {
			return false;
		}

		wp_delete_file( $path );
		return true;
	}

	/** Validate file contents before storing them. */
	private static function is_valid( string $type, string $contents ): bool {
		if ( $type === self::APPLE ) {
			return $contents !== '';
		}

		if ( $type !== self::GOOGLE ) {
			return false;
		}

		$data = json_decode( $contents, true );
		return is_array( $data )
			&& ! empty( $data['client_email'] )
			&& ! empty( $data['private_key'] )
			&& ! empty( $data['private_key_id'] );
	}

	private static function option_for( string $type ): string {
		if ( ! isset( self::OPTIONS[ $type ] ) ) {
			throw new \InvalidArgumentException( 'Unsupported credential type.' );
		}

		return self::OPTIONS[ $type ];
	}
}
