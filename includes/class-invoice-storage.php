<?php
/**
 * Private storage for generated invoice files.
 *
 * Invoice PDFs contain member names, billing addresses and amounts, and the
 * QR images encode the invoice payment link. They must never sit in the
 * public uploads directory under enumerable sequential names, so new files
 * are written to a private directory next to the WordPress root, following
 * the pattern already used for VOG and IVA files, and are only ever served
 * through the permission-gated REST download routes.
 *
 * Stored field values stay relative paths of the form
 * 'invoices/factuur-<number>.pdf', so existing records keep working:
 * resolution checks the private directory first and falls back to the
 * legacy uploads location for files generated before this change, until
 * they are moved server-side.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InvoiceStorage {

	/** Content-ID used for the QR image embedded in invoice emails. */
	public const QR_CID = 'rondo-invoice-qr';

	/**
	 * Directory new invoice files are written to (outside the web root).
	 */
	public static function directory(): string {
		$directory = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'rondo-private/invoices';
		if ( ! file_exists( $directory ) ) {
			wp_mkdir_p( $directory );
		}
		self::protect_legacy_directory();
		return $directory;
	}

	/**
	 * Drop a deny-all .htaccess into the legacy public invoices directory
	 * when it still exists, mirroring how communication attachments protect
	 * their uploads folder on Apache. The nginx template in ops/nginx
	 * carries the equivalent rule; this covers files that were generated
	 * before the move and have not been relocated server-side yet.
	 */
	private static function protect_legacy_directory(): void {
		$legacy = self::legacy_directory();
		if ( ! is_dir( $legacy ) ) {
			return;
		}
		$deny_file = $legacy . '/.htaccess';
		if ( ! file_exists( $deny_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $deny_file, "Require all denied\nDeny from all\n" );
		}
	}

	/**
	 * Legacy public directory, read only as a fallback for existing files.
	 */
	private static function legacy_directory(): string {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/invoices';
	}

	/**
	 * Absolute path a new file with the given name should be written to.
	 *
	 * @param string $filename Bare file name, for example 'factuur-2026F0001.pdf'.
	 */
	public static function path_for_write( string $filename ): string {
		return self::directory() . '/' . basename( $filename );
	}

	/**
	 * Resolve a stored relative path to an absolute path for reading or
	 * deleting, with strict containment.
	 *
	 * Only the bare file name of the stored value is ever used, and the
	 * resolved real path must stay inside the invoice directory it was
	 * found in, so values containing traversal segments can never reach
	 * files outside the invoice storage. Returns null when the stored
	 * value is not an invoice file name or no file exists.
	 *
	 * @param string $relative_path Stored value, for example 'invoices/factuur-2026F0001.pdf'.
	 */
	public static function resolve( string $relative_path ): ?string {
		$filename = self::stored_filename( $relative_path );
		if ( $filename === null ) {
			return null;
		}

		foreach ( [ self::directory(), self::legacy_directory() ] as $directory ) {
			$candidate = $directory . '/' . $filename;
			if ( ! file_exists( $candidate ) ) {
				continue;
			}
			$real_file = realpath( $candidate );
			$real_dir  = realpath( $directory );
			if ( $real_file !== false && $real_dir !== false && str_starts_with( $real_file, $real_dir . DIRECTORY_SEPARATOR ) ) {
				return $real_file;
			}
		}

		return null;
	}

	/**
	 * Whether a file exists for a stored relative path.
	 *
	 * @param string $relative_path Stored value.
	 */
	public static function exists( string $relative_path ): bool {
		return self::resolve( $relative_path ) !== null;
	}

	/**
	 * Extract the bare file name from a stored relative path.
	 *
	 * Returns null for anything that is not a plain file name under the
	 * invoices prefix, including any value with traversal segments.
	 *
	 * @param string $relative_path Stored value.
	 */
	private static function stored_filename( string $relative_path ): ?string {
		$relative_path = trim( str_replace( '\\', '/', $relative_path ) );
		if ( ! str_starts_with( $relative_path, 'invoices/' ) ) {
			return null;
		}

		$filename = substr( $relative_path, strlen( 'invoices/' ) );
		if ( $filename === '' || $filename !== basename( $filename ) ) {
			return null;
		}

		return $filename;
	}
}
