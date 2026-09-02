<?php
/**
 * One-time migration of legacy plaintext credentials.
 *
 * @package Rondo\Data
 */

namespace Rondo\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecretMigration {
	private const VERSION_OPTION = 'rondo_secret_storage_version';
	private const VERSION        = 2;

	/** Encrypt legacy secret options and credential files. */
	public static function run(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		$options = [
			'rondo_freescout_api_key',
			'rondo_lettermint_api_token',
			'rondo_lettermint_team_api_token',
			'rondo_lettermint_webhook_secret',
			'rondo_membership_pass_apple_cert_password',
			'rondo_membership_pass_jwt_secret',
			'rondo_access_fingerprint_secret',
			'rondo_narrowcasting_sportlink_client_id',
		];

		foreach ( $options as $option ) {
			if ( ! CredentialEncryption::migrate_secret_option( $option ) ) {
				return;
			}
		}

		$files = [
			PrivateCredentialStorage::APPLE  => self::legacy_path( 'rondo_membership_pass_apple_cert_path', 'rondo_membership_pass_apple_cert_attachment_id' ),
			PrivateCredentialStorage::GOOGLE => self::legacy_path( 'rondo_membership_pass_google_service_account_path', 'rondo_membership_pass_google_service_account_attachment_id' ),
		];

		foreach ( $files as $type => $path ) {
			if ( ! PrivateCredentialStorage::migrate_file( $type, $path ) ) {
				return;
			}
		}

		delete_option( 'rondo_membership_pass_apple_cert_path' );
		delete_option( 'rondo_membership_pass_google_service_account_path' );
		update_option( 'rondo_membership_pass_apple_cert_attachment_id', 0, false );
		update_option( 'rondo_membership_pass_google_service_account_attachment_id', 0, false );
		if ( ! self::lettermint_plugin_is_active() ) {
			delete_option( 'lettermint_api_token' );
		}
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/** Resolve a legacy direct path or media attachment path. */
	private static function legacy_path( string $path_option, string $attachment_option ): string {
		$path = (string) get_option( $path_option, '' );
		if ( $path !== '' ) {
			return $path;
		}

		$attachment_id = (int) get_option( $attachment_option, 0 );
		return $attachment_id > 0 ? (string) get_attached_file( $attachment_id ) : '';
	}

	/** Whether the retired third-party Lettermint plugin is still active. */
	private static function lettermint_plugin_is_active(): bool {
		foreach ( (array) get_option( 'active_plugins', [] ) as $plugin ) {
			if ( stripos( (string) $plugin, 'lettermint' ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
