<?php
/**
 * Plugin Name: Rondo Error Monitoring
 * Description: Loads GlitchTip before regular plugins and the Rondo theme.
 *
 * @package Rondo
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'RONDO_GLITCHTIP_DSN' ) || ! RONDO_GLITCHTIP_DSN ) {
	return;
}

$rondo_monitoring_theme = defined( 'RONDO_REQUEST_THEME_DIR' ) ? RONDO_REQUEST_THEME_DIR : realpath( WP_CONTENT_DIR . '/themes/rondo-club' );
if ( is_readable( $rondo_monitoring_theme . '/vendor/autoload.php' ) && is_readable( $rondo_monitoring_theme . '/includes/class-error-monitoring.php' ) ) {
	require_once $rondo_monitoring_theme . '/vendor/autoload.php';
	require_once $rondo_monitoring_theme . '/includes/class-error-monitoring.php';
	\Rondo\ErrorMonitoring::boot();
}
unset( $rondo_monitoring_theme );
