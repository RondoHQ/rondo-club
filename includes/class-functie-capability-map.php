<?php
/**
 * Functie-to-Role Capability Map
 *
 * Stores and retrieves the admin-configured mapping from Sportlink Functies (job titles)
 * to Rondo WordPress roles. Used by Phase 206 (Capability Sync) to automatically grant
 * or revoke roles during rondo-sync runs.
 *
 * Data structure stored in wp_options:
 *   rondo_functie_capability_map => [
 *     'Trainer'          => [ 'rondo_user' => true, 'rondo_fairplay' => false, ... ],
 *     'Penningmeester'   => [ 'rondo_user' => true, 'rondo_bestuur'  => true, ... ],
 *   ]
 *
 * @package Rondo\Config
 */

namespace Rondo\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static config class for the Functie-to-Role mapping.
 *
 * No hooks or constructor needed — pure data access class.
 * PSR-4 autoloaded, used statically from the REST API class.
 */
class FunctieCapabilityMap {

	/**
	 * WordPress option key for the mapping.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'rondo_functie_capability_map';

	/**
	 * Get the saved Functie-to-Role mapping.
	 *
	 * @return array Map of functie => [ role_slug => bool ]. Empty array if nothing saved yet.
	 */
	public static function get_map(): array {
		$map = get_option( self::OPTION_KEY, null );
		return is_array( $map ) ? $map : [];
	}

	/**
	 * Persist the Functie-to-Role mapping.
	 *
	 * @param array $map Map of functie => [ role_slug => bool ].
	 * @return bool True if option was updated, false if unchanged or failed.
	 */
	public static function update_map( array $map ): bool {
		return update_option( self::OPTION_KEY, $map );
	}

	/**
	 * Get the role slugs that a given Functie grants.
	 *
	 * Returns only slugs where the mapped value is truthy (true/1).
	 * Returns an empty array if the Functie has no mapping.
	 *
	 * @param string $functie The Sportlink Functie (job title) to look up.
	 * @return string[] Array of WordPress role slugs granted by this Functie.
	 */
	public static function get_roles_for_functie( string $functie ): array {
		$map   = self::get_map();
		$entry = $map[ $functie ] ?? [];
		return array_keys( array_filter( $entry ) );
	}
}
