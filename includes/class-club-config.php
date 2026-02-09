<?php
/**
 * Club Configuration Service
 *
 * Handles club-wide configuration settings storage and retrieval using the WordPress Options API.
 *
 * @package Rondo\Config
 */

namespace Rondo\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Club Configuration service class
 */
class ClubConfig {

	/**
	 * Option key for club name
	 */
	const OPTION_CLUB_NAME = 'rondo_club_name';

	/**
	 * Option key for FreeScout URL
	 */
	const OPTION_FREESCOUT_URL = 'rondo_freescout_url';

	/**
	 * Default configuration values
	 *
	 * @var array<string, string>
	 */
	const DEFAULTS = [
		'club_name'     => '',
		'freescout_url' => '',
	];

	/**
	 * Get club name
	 *
	 * @return string The club name (empty string if not configured)
	 */
	public function get_club_name(): string {
		return get_option( self::OPTION_CLUB_NAME, self::DEFAULTS['club_name'] );
	}

	/**
	 * Get FreeScout URL
	 *
	 * @return string The FreeScout URL (empty string if not configured)
	 */
	public function get_freescout_url(): string {
		return get_option( self::OPTION_FREESCOUT_URL, self::DEFAULTS['freescout_url'] );
	}

	/**
	 * Get all configuration settings
	 *
	 * @return array<string, string> Array of all configuration settings
	 */
	public function get_all_settings(): array {
		return [
			'club_name'     => $this->get_club_name(),
			'freescout_url' => $this->get_freescout_url(),
		];
	}

	/**
	 * Update club name
	 *
	 * @param string $name The club name to set.
	 * @return bool True on success, false on failure
	 */
	public function update_club_name( string $name ): bool {
		$sanitized = sanitize_text_field( $name );
		return update_option( self::OPTION_CLUB_NAME, $sanitized );
	}

	/**
	 * Update FreeScout URL
	 *
	 * @param string $url The FreeScout URL to set.
	 * @return bool True on success, false on failure
	 */
	public function update_freescout_url( string $url ): bool {
		$sanitized = esc_url_raw( $url );
		return update_option( self::OPTION_FREESCOUT_URL, $sanitized );
	}
}
