<?php
/**
 * Commissie-to-Role Capability Map
 *
 * Stores and retrieves the admin-configured mapping from Commissies (committees)
 * to Rondo WordPress roles. Used by CapabilitySync to automatically grant roles
 * to users who are active members of a commissie, regardless of their specific functie.
 *
 * Data structure stored in wp_options:
 *   rondo_commissie_capability_map => [
 *     '2668' => [ 'rondo_fairplay' => true, 'rondo_vog' => false, ... ],
 *     '2669' => [ 'rondo_bestuur'  => true, ... ],
 *   ]
 *
 * @package Rondo\Config
 */

namespace Rondo\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static config class for the Commissie-to-Role mapping.
 */
class CommissieCapabilityMap {

	const OPTION_KEY = 'rondo_commissie_capability_map';

	/**
	 * Get the saved Commissie-to-Role mapping.
	 *
	 * @return array Map of commissie_id => [ role_slug => bool ].
	 */
	public static function get_map(): array {
		$map = get_option( self::OPTION_KEY, null );
		return is_array( $map ) ? $map : [];
	}

	/**
	 * Persist the Commissie-to-Role mapping.
	 *
	 * @param array $map Map of commissie_id => [ role_slug => bool ].
	 * @return bool True if option was updated.
	 */
	public static function update_map( array $map ): bool {
		return update_option( self::OPTION_KEY, $map );
	}

	/**
	 * Get the role slugs that membership in a commissie grants.
	 *
	 * @param int $commissie_id The commissie post ID.
	 * @return string[] Array of WordPress role slugs.
	 */
	public static function get_roles_for_commissie( int $commissie_id ): array {
		$map   = self::get_map();
		$entry = $map[ (string) $commissie_id ] ?? [];
		return array_keys( array_filter( $entry ) );
	}
}
