<?php
/**
 * Site-wide feature toggles.
 *
 * @package Rondo\Config
 */

namespace Rondo\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and evaluates the site's three-state feature toggles.
 */
final class FeatureToggles {

	public const OPTION_NAME = 'rondo_feature_toggles';
	public const ON          = 'on';
	public const OFF         = 'off';
	public const ADMIN_ONLY  = 'admin_only';

	/**
	 * Feature definitions and safe defaults.
	 *
	 * Kleding and Club TV predate toggles, so they default to on. Ruimtes keeps
	 * its existing opt-in behaviour and defaults to off.
	 *
	 * @return array<string, array{label:string,description:string,default:string}>
	 */
	public static function definitions(): array {
		return [
			'rooms'         => [
				'label'       => __( 'Ruimtes', 'rondo' ),
				'description' => __( 'Ruimtereserveringen en reserveringsgestuurde presentaties.', 'rondo' ),
				'default'     => self::OFF,
			],
			'clothing'      => [
				'label'       => __( 'Kleding', 'rondo' ),
				'description' => __( 'Kledingvoorraad, uitgiftes en kledingprofielen.', 'rondo' ),
				'default'     => self::ON,
			],
			'narrowcasting' => [
				'label'       => __( 'Club TV', 'rondo' ),
				'description' => __( 'Content, afspeellijsten, schermbeheer en browserpresentaties.', 'rondo' ),
				'default'     => self::ON,
			],
		];
	}

	/** @return string[] */
	public static function states(): array {
		return [ self::ON, self::OFF, self::ADMIN_ONLY ];
	}

	/** Return one effective state, including the legacy rooms fallback. */
	public static function get_state( string $feature ): string {
		$definitions = self::definitions();
		if ( ! isset( $definitions[ $feature ] ) ) {
			return self::OFF;
		}

		$stored = get_option( self::OPTION_NAME, [] );
		if ( is_array( $stored ) && isset( $stored[ $feature ] ) && in_array( $stored[ $feature ], self::states(), true ) ) {
			return $stored[ $feature ];
		}

		if ( $feature === 'rooms' ) {
			$legacy = get_option( 'rondo_rooms_enabled', null );
			if ( $legacy !== null ) {
				return rest_sanitize_boolean( $legacy ) ? self::ON : self::OFF;
			}
		}

		return $definitions[ $feature ]['default'];
	}

	/** @return array<string, string> */
	public static function get_all(): array {
		$states = [];
		foreach ( array_keys( self::definitions() ) as $feature ) {
			$states[ $feature ] = self::get_state( $feature );
		}
		return $states;
	}

	/** Whether routes and runtime hooks for a feature should be registered. */
	public static function is_available( string $feature ): bool {
		return self::get_state( $feature ) !== self::OFF;
	}

	/** Whether a specific user may use a feature in its current state. */
	public static function can_access( string $feature, ?int $user_id = null ): bool {
		$state = self::get_state( $feature );
		if ( $state === self::ON ) {
			return true;
		}
		if ( $state !== self::ADMIN_ONLY ) {
			return false;
		}

		return $user_id === null
			? current_user_can( 'manage_options' )
			: user_can( $user_id, 'manage_options' );
	}

	/**
	 * Validate and persist a complete or partial state map.
	 *
	 * @param array<string, mixed> $updates Requested states.
	 * @return array<string, string>|\WP_Error
	 */
	public static function update( array $updates ) {
		$definitions = self::definitions();
		foreach ( $updates as $feature => $state ) {
			if ( ! isset( $definitions[ $feature ] ) || ! is_string( $state ) || ! in_array( $state, self::states(), true ) ) {
				return new \WP_Error(
					'rondo_feature_toggle_invalid',
					__( 'Een feature toggle bevat een ongeldige waarde.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}
		}

		$states = array_merge( self::get_all(), $updates );
		update_option( self::OPTION_NAME, $states, false );

		return $states;
	}
}
