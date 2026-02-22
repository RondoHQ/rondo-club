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
 * Club Configuration static utility class
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
	 * Option key for FreeScout API key
	 */
	const OPTION_FREESCOUT_API_KEY = 'rondo_freescout_api_key';

	/**
	 * Default configuration values
	 *
	 * @var array<string, string>
	 */
	const DEFAULTS = [
		'club_name'     => '',
		'freescout_url' => '',
		'freescout_api_key' => '',
	];

	/**
	 * Get club name
	 *
	 * @return string The club name (empty string if not configured)
	 */
	public static function get_club_name(): string {
		return get_option( self::OPTION_CLUB_NAME, self::DEFAULTS['club_name'] );
	}

	/**
	 * Get FreeScout URL
	 *
	 * @return string The FreeScout URL (empty string if not configured)
	 */
	public static function get_freescout_url(): string {
		return get_option( self::OPTION_FREESCOUT_URL, self::DEFAULTS['freescout_url'] );
	}

	/**
	 * Get FreeScout API key.
	 *
	 * @return string The FreeScout API key (empty string if not configured)
	 */
	public static function get_freescout_api_key(): string {
		return get_option( self::OPTION_FREESCOUT_API_KEY, self::DEFAULTS['freescout_api_key'] );
	}

	/**
	 * Check whether a FreeScout API key is configured.
	 *
	 * @return bool True when a key exists, false otherwise.
	 */
	public static function has_freescout_api_key(): bool {
		return '' !== trim( self::get_freescout_api_key() );
	}

	/**
	 * Get all configuration settings
	 *
	 * @return array<string, string|bool> Array of all configuration settings
	 */
	public static function get_all_settings(): array {
		return [
			'club_name'     => self::get_club_name(),
			'freescout_url' => self::get_freescout_url(),
			'freescout_has_api_key' => self::has_freescout_api_key(),
		];
	}

	/**
	 * Update club name
	 *
	 * @param string $name The club name to set.
	 * @return bool True on success, false on failure
	 */
	public static function update_club_name( string $name ): bool {
		$sanitized = sanitize_text_field( $name );
		return update_option( self::OPTION_CLUB_NAME, $sanitized );
	}

	/**
	 * Update FreeScout URL
	 *
	 * @param string $url The FreeScout URL to set.
	 * @return bool True on success, false on failure
	 */
	public static function update_freescout_url( string $url ): bool {
		$sanitized = esc_url_raw( $url );
		return update_option( self::OPTION_FREESCOUT_URL, $sanitized );
	}

	/**
	 * Update FreeScout API key.
	 *
	 * @param string $api_key The FreeScout API key to set.
	 * @return bool True on success, false on failure
	 */
	public static function update_freescout_api_key( string $api_key ): bool {
		$sanitized = sanitize_text_field( $api_key );
		return update_option( self::OPTION_FREESCOUT_API_KEY, $sanitized );
	}
}
