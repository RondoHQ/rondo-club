<?php
/** Process-scoped serialization on Rondo's single-host WordPress deployment. */
namespace Rondo\Matches;

final class CompensationLock {

	public static function acquire() {
		$handle = fopen( get_temp_dir() . 'rondo-match-' . md5( ABSPATH ) . '.lock', 'c' );
		if ( ! $handle || ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			if ( $handle ) {
				fclose( $handle ); }
			return new \WP_Error( 'rondo_match_busy', 'Een andere wijziging wordt verwerkt. Probeer opnieuw.', [ 'status' => 409 ] );
		}
		return $handle;
	}
	/** The OS releases flock after process failure; durable receipts recover responses. */
	public static function run( callable $operation ) {
		$handle = self::acquire();
		if ( is_wp_error( $handle ) ) {
			return $handle; }
		try {
			return $operation();
		} finally {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}
}
