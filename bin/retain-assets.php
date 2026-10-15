<?php
/**
 * Keep retired build assets for seven days after their first obsolete deployment.
 *
 * Usage: php bin/retain-assets.php inventory|prune /absolute/path/to/dist
 * Runs outside WordPress, on the release builder and the deployment host.
 *
 * @package Rondo
 */

// CLI diagnostics and native file operations are intentional here.
// phpcs:disable WordPress.Security.EscapeOutput

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

/**
 * Read and validate a JSON file before any deletions.
 *
 * @param string $path JSON file path.
 * @return array Decoded data.
 */
function rondo_read_asset_json( $path ) {
	$data = json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	if ( ! is_array( $data ) ) {
		throw new RuntimeException( 'Invalid asset inventory or retention state.' );
	}
	return $data;
}

try {
	$mode = $argv[1] ?? '';
	$dist = realpath( $argv[2] ?? '' );
	if ( ! in_array( $mode, [ 'inventory', 'prune' ], true ) || ! $dist || ! is_dir( "$dist/assets" ) || is_link( "$dist/assets" ) ) {
		throw new RuntimeException( 'Expected inventory|prune and a dist directory containing assets.' );
	}

	$assets = [];
	$files  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$dist/assets", FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		if ( $file->isLink() ) {
			throw new RuntimeException( 'Symlinks are not allowed in build assets.' );
		}
		if ( $file->isFile() ) {
			$assets[] = substr( $file->getPathname(), strlen( "$dist/assets/" ) );
		}
	}
	sort( $assets );
	if ( ! $assets ) {
		throw new RuntimeException( 'Refusing an empty asset inventory.' );
	}

	$output = "$dist/.asset-inventory.json";
	$data   = $assets;
	if ( $mode === 'prune' ) {
		$current = rondo_read_asset_json( $output );
		if ( ! $current || array_diff( $current, $assets ) ) {
			throw new RuntimeException( 'Release assets are missing; refusing to prune.' );
		}
		$output = "$dist/.asset-retention.json";
		$state  = file_exists( $output ) ? rondo_read_asset_json( $output ) : [];
		foreach ( $state as $retired_at ) {
			if ( ! is_int( $retired_at ) || $retired_at <= 0 ) {
				throw new RuntimeException( 'Invalid retirement timestamp; refusing to prune.' );
			}
		}
		$data = [];
		$now  = time();
		foreach ( array_diff( $assets, $current ) as $asset ) {
			$retired_at = $state[ $asset ] ?? $now;
			if ( $now - $retired_at >= 7 * 24 * 60 * 60 ) {
				if ( ! unlink( "$dist/assets/$asset" ) ) {
					throw new RuntimeException( 'Unable to remove an expired asset.' );
				}
			} else {
				$data[ $asset ] = $retired_at;
			}
		}
	}

	// A failed write must not leave partially readable retention state.
	$temporary = "$output.tmp";
	if ( file_put_contents( $temporary, json_encode( $data, JSON_THROW_ON_ERROR ) ) === false || ! rename( $temporary, $output ) ) {
		throw new RuntimeException( 'Unable to save asset inventory or retention state.' );
	}
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}
