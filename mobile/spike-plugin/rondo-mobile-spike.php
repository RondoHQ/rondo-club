<?php
/**
 * Plugin Name: Rondo Mobile Spike (development only)
 * Description: Opt-in native member login experiment. Never loaded by the theme.
 * Version: 0.8.0
 *
 * @package Rondo\MobileSpike
 */

namespace Rondo\MobileSpike;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/shared/class-gateway.php';

/** The original experiment remains unavailable on staging and production. */
final class Plugin extends \Rondo\Mobile\Gateway {
	public static function enabled(): bool {
		return defined( 'RONDO_MOBILE_SPIKE' ) && RONDO_MOBILE_SPIKE === true && in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
	}
}

new Plugin();
