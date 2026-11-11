<?php

namespace Tests\Wpunit;

use InvalidArgumentException;
use Rondo\Fields\Registry;
use Rondo\Finance\InvoiceStorage;
use Tests\Support\RondoTestCase;

/**
 * Invoice files live in private storage and stored path values must never
 * resolve outside the invoice directory.
 */
class InvoiceStorageTest extends RondoTestCase {

	public function test_write_path_is_outside_uploads(): void {
		$path = InvoiceStorage::path_for_write( 'factuur-2026F0001.pdf' );

		$this->assertStringEndsWith( 'rondo-private/invoices/factuur-2026F0001.pdf', $path );
		$this->assertStringNotContainsString( 'uploads', $path );
	}

	public function test_resolve_finds_private_and_legacy_files(): void {
		$private_path = InvoiceStorage::path_for_write( 'factuur-2099F0001.pdf' );
		file_put_contents( $private_path, '%PDF-test' );
		$legacy_dir = wp_upload_dir()['basedir'] . '/invoices';
		wp_mkdir_p( $legacy_dir );
		file_put_contents( $legacy_dir . '/factuur-2099F0002.pdf', '%PDF-legacy' );

		try {
			$this->assertSame( realpath( $private_path ), InvoiceStorage::resolve( 'invoices/factuur-2099F0001.pdf' ) );
			$this->assertSame( realpath( $legacy_dir . '/factuur-2099F0002.pdf' ), InvoiceStorage::resolve( 'invoices/factuur-2099F0002.pdf' ), 'files generated before the move stay downloadable through the gated route' );
		} finally {
			unlink( $private_path );
			unlink( $legacy_dir . '/factuur-2099F0002.pdf' );
		}
	}

	public function test_resolve_rejects_traversal_and_foreign_paths(): void {
		$this->assertNull( InvoiceStorage::resolve( 'invoices/../../wp-config.php' ) );
		$this->assertNull( InvoiceStorage::resolve( '../wp-config.php' ) );
		$this->assertNull( InvoiceStorage::resolve( '/etc/passwd' ) );
		$this->assertNull( InvoiceStorage::resolve( 'invoices/sub/factuur-2026F0001.pdf' ) );
		$this->assertNull( InvoiceStorage::resolve( 'invoices/factuur-2099F9999.pdf' ), 'a well-formed name without a file resolves to null, not a path' );
	}

	public function test_path_fields_are_read_only_for_api_writes(): void {
		foreach ( [ 'pdf_path', 'qr_code_path' ] as $field ) {
			$this->assertNotEmpty( Registry::resolve( 'rondo_invoice', $field )['read_only'] ?? null, $field );
			try {
				Registry::to_storage( 'rondo_invoice', [ $field => 'invoices/../../wp-config.php' ] );
				$this->fail( "Writing {$field} should have been rejected as read-only." );
			} catch ( InvalidArgumentException $error ) {
				$this->assertStringContainsString( 'is read-only', $error->getMessage() );
			}
		}
	}
}
