<?php

namespace Tests\Wpunit;

use Rondo\Data\CredentialEncryption;
use Rondo\Data\PrivateCredentialStorage;
use Tests\Support\RondoTestCase;

class CredentialEncryptionTest extends RondoTestCase {
	private const SECRET_OPTION = 'rondo_test_encrypted_secret';

	protected function tear_down(): void {
		delete_option( self::SECRET_OPTION );
		delete_option( 'rondo_membership_pass_google_service_account_encrypted' );
		delete_option( 'rondo_membership_pass_google_service_account_filename' );
		parent::tear_down();
	}

	public function test_secret_options_are_encrypted_at_rest(): void {
		$this->assertTrue( CredentialEncryption::update_secret_option( self::SECRET_OPTION, 'top-secret' ) );

		$stored = (string) get_option( self::SECRET_OPTION, '' );
		$this->assertTrue( CredentialEncryption::is_encrypted( $stored ) );
		$this->assertStringNotContainsString( 'top-secret', $stored );
		$this->assertSame( 'top-secret', CredentialEncryption::get_secret_option( self::SECRET_OPTION ) );
	}

	public function test_plaintext_option_can_be_migrated_in_place(): void {
		update_option( self::SECRET_OPTION, 'legacy-secret', false );

		$this->assertTrue( CredentialEncryption::migrate_secret_option( self::SECRET_OPTION ) );
		$this->assertTrue( CredentialEncryption::is_encrypted( (string) get_option( self::SECRET_OPTION, '' ) ) );
		$this->assertSame( 'legacy-secret', CredentialEncryption::get_secret_option( self::SECRET_OPTION ) );
	}

	public function test_existing_array_ciphertext_remains_readable(): void {
		$data      = [ 'access_token' => 'legacy-token' ];
		$json      = wp_json_encode( $data );
		$key       = hash( 'sha256', AUTH_KEY . 'rondo_calendar', true );
		$nonce     = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$encrypted = base64_encode( $nonce . sodium_crypto_secretbox( $json, $nonce, $key ) );

		$this->assertSame( $data, CredentialEncryption::decrypt( $encrypted ) );
	}

	public function test_google_credential_file_is_encrypted_and_materialized_privately(): void {
		$json = wp_json_encode(
			[
				'client_email'   => 'wallet@example.test',
				'private_key_id' => 'key-id',
				'private_key'    => "-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----\n",
			]
		);

		$this->assertTrue( PrivateCredentialStorage::store( PrivateCredentialStorage::GOOGLE, $json, 'wallet.json' ) );
		$stored = (string) get_option( 'rondo_membership_pass_google_service_account_encrypted', '' );
		$this->assertTrue( CredentialEncryption::is_encrypted( $stored ) );
		$this->assertStringNotContainsString( 'PRIVATE KEY', $stored );
		$this->assertSame( 'wallet.json', PrivateCredentialStorage::filename( PrivateCredentialStorage::GOOGLE ) );

		$path = PrivateCredentialStorage::temporary_path( PrivateCredentialStorage::GOOGLE );
		$this->assertFileExists( $path );
		$this->assertSame( $json, file_get_contents( $path ) );
		$this->assertSame( '0600', substr( sprintf( '%o', fileperms( $path ) ), -4 ) );
		wp_delete_file( $path );
	}
}
