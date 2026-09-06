<?php
/**
 * Add the member demonstration journeys without replacing existing demo data.
 *
 * Usage: wp eval-file bin/demo-feature-fixtures.php plan|seed
 *
 * @package Rondo\Demo
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}
require_once dirname( __DIR__ ) . '/includes/class-demo-feature-fixtures.php';
try {
	$action = $args[0] ?? 'plan';
	if ( ! in_array( $action, [ 'plan', 'seed' ], true ) ) {
		throw new RuntimeException( 'Use plan or seed.' );
	}
	// Seeding never sends mail, even if an installed plugin reacts to a record save.
	add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
	$result = $action === 'seed' ? \Rondo\Demo\FeatureFixtures::seed() : \Rondo\Demo\FeatureFixtures::plan();
	WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
} catch ( Throwable $error ) {
	WP_CLI::error( $error->getMessage() );
}
