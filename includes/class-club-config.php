<?php
/**
 * Club Configuration Service
 *
 * Handles club-wide configuration settings storage and retrieval using the WordPress Options API.
 *
 * @package Stadion\Config
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
	const OPTION_CLUB_NAME = 'stadion_club_name';

	/**
	 * Option key for accent color
	 */
	const OPTION_ACCENT_COLOR = 'stadion_accent_color';

	/**
	 * Option key for FreeScout URL
	 */
	const OPTION_FREESCOUT_URL = 'stadion_freescout_url';

	/**
	 * Default configuration values
	 *
	 * @var array<string, string>
	 */
	const DEFAULTS = [
		'club_name'     => '',
		'accent_color'  => '#006935',
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
	 * Get accent color
	 *
	 * @return string The accent color in hex format (default: #006935)
	 */
	public function get_accent_color(): string {
		$color = get_option( self::OPTION_ACCENT_COLOR, self::DEFAULTS['accent_color'] );

		// Validate the color
		$sanitized = sanitize_hex_color( $color );
		if ( ! $sanitized ) {
			return self::DEFAULTS['accent_color'];
		}

		return $sanitized;
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
			'accent_color'  => $this->get_accent_color(),
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
	 * Update accent color
	 *
	 * @param string $color The accent color in hex format.
	 * @return bool True on success, false on failure (invalid color)
	 */
	public function update_accent_color( string $color ): bool {
		$sanitized = sanitize_hex_color( $color );
		if ( ! $sanitized ) {
			return false;
		}
		return update_option( self::OPTION_ACCENT_COLOR, $sanitized );
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
