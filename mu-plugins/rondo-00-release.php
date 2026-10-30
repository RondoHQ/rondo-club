<?php
/**
 * Plugin Name: Rondo Release Pinning
 * Description: Keeps each PHP request on one complete theme release.
 *
 * @package Rondo
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// PHP workers can retain a symlink's old realpath between requests.
clearstatcache( true );
$rondo_release_path = realpath( WP_CONTENT_DIR . '/themes/rondo-club' );
if ( $rondo_release_path && is_readable( $rondo_release_path . '/functions.php' ) ) {
	define( 'RONDO_REQUEST_THEME_DIR', $rondo_release_path );
	foreach ( [ 'template_directory', 'stylesheet_directory' ] as $rondo_directory_filter ) {
		add_filter(
			$rondo_directory_filter,
			static function ( $directory, $theme ) {
				return $theme === 'rondo-club' ? RONDO_REQUEST_THEME_DIR : $directory;
			},
			PHP_INT_MAX,
			2
		);
	}
}
unset( $rondo_release_path, $rondo_directory_filter );
