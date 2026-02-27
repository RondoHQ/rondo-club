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
	 * Option key for Lettermint project API token.
	 */
	const OPTION_LETTERMINT_API_TOKEN = \Rondo\Notifications\LettermintConfig::OPTION_API_TOKEN;

	/**
	 * Option key for Lettermint team API token.
	 */
	const OPTION_LETTERMINT_TEAM_API_TOKEN = \Rondo\Notifications\LettermintConfig::OPTION_TEAM_API_TOKEN;

	/**
	 * Option key for Lettermint route ID.
	 */
	const OPTION_LETTERMINT_ROUTE_ID = \Rondo\Notifications\LettermintConfig::OPTION_ROUTE_ID;

	/**
	 * Option key for Lettermint webhook secret.
	 */
	const OPTION_LETTERMINT_WEBHOOK_SECRET = \Rondo\Notifications\LettermintConfig::OPTION_WEBHOOK_SECRET;

	/**
	 * Option key for the last created Lettermint webhook ID.
	 */
	const OPTION_LETTERMINT_WEBHOOK_ID = 'rondo_lettermint_webhook_id';

	/**
	 * Default configuration values
	 *
	 * @var array<string, string>
	 */
	const DEFAULTS = [
		'club_name'     => '',
		'freescout_url' => '',
		'freescout_api_key' => '',
		'lettermint_api_token' => '',
		'lettermint_team_api_token' => '',
		'lettermint_route_id' => '',
		'lettermint_webhook_secret' => '',
		'lettermint_webhook_id' => '',
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
		return trim( self::get_freescout_api_key() ) !== '';
	}

	/**
	 * Get Lettermint route ID.
	 *
	 * @return string
	 */
	public static function get_lettermint_route_id(): string {
		return get_option( self::OPTION_LETTERMINT_ROUTE_ID, self::DEFAULTS['lettermint_route_id'] );
	}

	/**
	 * Get Lettermint webhook ID.
	 *
	 * @return string
	 */
	public static function get_lettermint_webhook_id(): string {
		return get_option( self::OPTION_LETTERMINT_WEBHOOK_ID, self::DEFAULTS['lettermint_webhook_id'] );
	}

	/**
	 * Check whether a Lettermint project API token is configured.
	 *
	 * @return bool
	 */
	public static function has_lettermint_api_token(): bool {
		return trim( (string) get_option( self::OPTION_LETTERMINT_API_TOKEN, self::DEFAULTS['lettermint_api_token'] ) ) !== '';
	}

	/**
	 * Check whether a Lettermint team API token is configured.
	 *
	 * @return bool
	 */
	public static function has_lettermint_team_api_token(): bool {
		return trim( (string) get_option( self::OPTION_LETTERMINT_TEAM_API_TOKEN, self::DEFAULTS['lettermint_team_api_token'] ) ) !== '';
	}

	/**
	 * Check whether a Lettermint webhook secret is configured.
	 *
	 * @return bool
	 */
	public static function has_lettermint_webhook_secret(): bool {
		return trim( (string) get_option( self::OPTION_LETTERMINT_WEBHOOK_SECRET, self::DEFAULTS['lettermint_webhook_secret'] ) ) !== '';
	}

	/**
	 * Get all configuration settings
	 *
	 * @return array<string, string|bool> Array of all configuration settings
	 */
	public static function get_all_settings(): array {
		$webhook_path = 'rondo/v1/lettermint/webhook';

		return [
			'club_name'     => self::get_club_name(),
			'freescout_url' => self::get_freescout_url(),
			'freescout_has_api_key' => self::has_freescout_api_key(),
			'lettermint_route_id' => self::get_lettermint_route_id(),
			'lettermint_webhook_id' => self::get_lettermint_webhook_id(),
			'lettermint_has_api_token' => self::has_lettermint_api_token(),
			'lettermint_has_team_api_token' => self::has_lettermint_team_api_token(),
			'lettermint_has_webhook_secret' => self::has_lettermint_webhook_secret(),
			'lettermint_webhook_url' => rest_url( $webhook_path ),
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

	/**
	 * Update Lettermint project API token.
	 *
	 * @param string $api_token Token.
	 * @return bool
	 */
	public static function update_lettermint_api_token( string $api_token ): bool {
		$sanitized = sanitize_text_field( $api_token );
		return update_option( self::OPTION_LETTERMINT_API_TOKEN, $sanitized );
	}

	/**
	 * Update Lettermint team API token.
	 *
	 * @param string $api_token Token.
	 * @return bool
	 */
	public static function update_lettermint_team_api_token( string $api_token ): bool {
		$sanitized = sanitize_text_field( $api_token );
		return update_option( self::OPTION_LETTERMINT_TEAM_API_TOKEN, $sanitized );
	}

	/**
	 * Update Lettermint route ID.
	 *
	 * @param string $route_id Route ID.
	 * @return bool
	 */
	public static function update_lettermint_route_id( string $route_id ): bool {
		$sanitized = sanitize_text_field( $route_id );
		return update_option( self::OPTION_LETTERMINT_ROUTE_ID, $sanitized );
	}

	/**
	 * Update Lettermint webhook secret.
	 *
	 * @param string $secret Secret.
	 * @return bool
	 */
	public static function update_lettermint_webhook_secret( string $secret ): bool {
		$sanitized = sanitize_text_field( $secret );
		return update_option( self::OPTION_LETTERMINT_WEBHOOK_SECRET, $sanitized );
	}

	/**
	 * Update Lettermint webhook ID.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @return bool
	 */
	public static function update_lettermint_webhook_id( string $webhook_id ): bool {
		$sanitized = sanitize_text_field( $webhook_id );
		return update_option( self::OPTION_LETTERMINT_WEBHOOK_ID, $sanitized );
	}
}
