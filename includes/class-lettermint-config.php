<?php
/**
 * Lettermint configuration helper.
 *
 * Reads Lettermint settings from constants, environment variables, or
 * WordPress options (in that priority order).
 *
 * @package Rondo\Notifications
 */

namespace Rondo\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LettermintConfig {

	/**
	 * Option key for Lettermint API token.
	 */
	const OPTION_API_TOKEN = 'rondo_lettermint_api_token';

	/**
	 * Option key for Lettermint team API token.
	 */
	const OPTION_TEAM_API_TOKEN = 'rondo_lettermint_team_api_token';

	/**
	 * Option key for Lettermint route ID.
	 */
	const OPTION_ROUTE_ID = 'rondo_lettermint_route_id';

	/**
	 * Option key for Lettermint webhook secret.
	 */
	const OPTION_WEBHOOK_SECRET = 'rondo_lettermint_webhook_secret';

	/**
	 * Option key for Lettermint webhook tolerance in seconds.
	 */
	const OPTION_WEBHOOK_TOLERANCE = 'rondo_lettermint_webhook_tolerance';

	/**
	 * Get Lettermint API token.
	 *
	 * Priority:
	 * 1. RONDO_LETTERMINT_API_TOKEN constant
	 * 2. LETTERMINT_API_TOKEN environment variable
	 * 3. rondo_lettermint_api_token option
	 *
	 * @return string
	 */
	public static function get_api_token(): string {
		if ( defined( 'RONDO_LETTERMINT_API_TOKEN' ) ) {
			return trim( (string) RONDO_LETTERMINT_API_TOKEN );
		}

		$env = getenv( 'LETTERMINT_API_TOKEN' );
		if ( is_string( $env ) && trim( $env ) !== '' ) {
			return trim( $env );
		}

		$option = get_option( self::OPTION_API_TOKEN, '' );
		return is_string( $option ) ? trim( $option ) : '';
	}

	/**
	 * Get Lettermint team API token used for Team API actions.
	 *
	 * Priority:
	 * 1. RONDO_LETTERMINT_TEAM_API_TOKEN constant
	 * 2. LETTERMINT_TEAM_API_TOKEN environment variable
	 * 3. rondo_lettermint_team_api_token option
	 * 4. Fallback to project API token (for backwards compatibility)
	 *
	 * @return string
	 */
	public static function get_team_api_token(): string {
		if ( defined( 'RONDO_LETTERMINT_TEAM_API_TOKEN' ) ) {
			return trim( (string) RONDO_LETTERMINT_TEAM_API_TOKEN );
		}

		$env = getenv( 'LETTERMINT_TEAM_API_TOKEN' );
		if ( is_string( $env ) && trim( $env ) !== '' ) {
			return trim( $env );
		}

		$option = get_option( self::OPTION_TEAM_API_TOKEN, '' );
		if ( is_string( $option ) && trim( $option ) !== '' ) {
			return trim( $option );
		}

		return self::get_api_token();
	}

	/**
	 * Get optional Lettermint route ID.
	 *
	 * Priority:
	 * 1. RONDO_LETTERMINT_ROUTE_ID constant
	 * 2. LETTERMINT_ROUTE_ID environment variable
	 * 3. rondo_lettermint_route_id option
	 *
	 * @return string
	 */
	public static function get_route_id(): string {
		if ( defined( 'RONDO_LETTERMINT_ROUTE_ID' ) ) {
			return trim( (string) RONDO_LETTERMINT_ROUTE_ID );
		}

		$env = getenv( 'LETTERMINT_ROUTE_ID' );
		if ( is_string( $env ) && trim( $env ) !== '' ) {
			return trim( $env );
		}

		$option = get_option( self::OPTION_ROUTE_ID, '' );
		return is_string( $option ) ? trim( $option ) : '';
	}

	/**
	 * Get Lettermint webhook signing secret.
	 *
	 * Priority:
	 * 1. RONDO_LETTERMINT_WEBHOOK_SECRET constant
	 * 2. LETTERMINT_WEBHOOK_SECRET environment variable
	 * 3. rondo_lettermint_webhook_secret option
	 *
	 * @return string
	 */
	public static function get_webhook_secret(): string {
		if ( defined( 'RONDO_LETTERMINT_WEBHOOK_SECRET' ) ) {
			return trim( (string) RONDO_LETTERMINT_WEBHOOK_SECRET );
		}

		$env = getenv( 'LETTERMINT_WEBHOOK_SECRET' );
		if ( is_string( $env ) && trim( $env ) !== '' ) {
			return trim( $env );
		}

		$option = get_option( self::OPTION_WEBHOOK_SECRET, '' );
		return is_string( $option ) ? trim( $option ) : '';
	}

	/**
	 * Get Lettermint webhook timestamp tolerance (seconds).
	 *
	 * Priority:
	 * 1. RONDO_LETTERMINT_WEBHOOK_TOLERANCE constant
	 * 2. LETTERMINT_WEBHOOK_TOLERANCE environment variable
	 * 3. rondo_lettermint_webhook_tolerance option
	 *
	 * @return int
	 */
	public static function get_webhook_tolerance(): int {
		if ( defined( 'RONDO_LETTERMINT_WEBHOOK_TOLERANCE' ) ) {
			return max( 30, (int) RONDO_LETTERMINT_WEBHOOK_TOLERANCE );
		}

		$env = getenv( 'LETTERMINT_WEBHOOK_TOLERANCE' );
		if ( is_string( $env ) && trim( $env ) !== '' ) {
			return max( 30, (int) $env );
		}

		$option = (int) get_option( self::OPTION_WEBHOOK_TOLERANCE, 300 );
		return max( 30, $option );
	}
}
