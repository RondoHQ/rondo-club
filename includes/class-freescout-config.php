<?php
/**
 * Installation-level FreeScout integration configuration.
 *
 * @package Rondo\Integrations\FreeScout
 */

namespace Rondo\Integrations\FreeScout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve the audit policy without exposing signing material. */
final class Config {

	public const RETENTION_OPTION    = 'freescout_audit_retention_days';
	public const RETENTION_DEFAULT   = 365;
	public const RETENTION_MIN       = 90;
	public const RETENTION_MAX       = 730;
	public const PROVISIONING_OPTION = 'freescout_realtime_provisioning';

	/** Whether Rondo should push access changes to registered FreeScout clients. */
	public static function provisioning_events_enabled(): bool {
		$environment = self::environment_boolean( 'RONDO_FREESCOUT_PROVISIONING_EVENTS' );
		if ( $environment !== null ) {
			return $environment;
		}

		return (bool) get_option( self::PROVISIONING_OPTION, false );
	}

	/** Return client-safe state for the realtime provisioning switch. */
	public static function provisioning_status(): array {
		$environment = self::environment_boolean( 'RONDO_FREESCOUT_PROVISIONING_EVENTS' );

		return [
			'enabled' => $environment ?? self::provisioning_events_enabled(),
			'source'  => $environment === null ? 'rondo_setting' : 'environment',
			'locked'  => $environment !== null,
		];
	}

	/** Update the Rondo-managed provisioning switch. */
	public static function update_provisioning_events( bool $enabled ): bool {
		if ( self::environment_boolean( 'RONDO_FREESCOUT_PROVISIONING_EVENTS' ) !== null ) {
			return false;
		}

		return (bool) get_option( self::PROVISIONING_OPTION, false ) === $enabled
			|| update_option( self::PROVISIONING_OPTION, $enabled, false );
	}

	/** Return configured current and previous HMAC keys, current first. */
	public static function signing_keys(): array {
		$keys = [];
		foreach ( [ 'RONDO_FREESCOUT_SIGNING_KEY', 'RONDO_FREESCOUT_SIGNING_KEY_PREVIOUS' ] as $name ) {
			$value = defined( $name ) ? constant( $name ) : getenv( $name );
			if ( is_string( $value ) && trim( $value ) !== '' ) {
				$keys[] = trim( $value );
			}
		}

		/**
		 * Filter signing keys for tests and managed hosting.
		 *
		 * @param string[] $keys Current and previous keys.
		 */
		$keys = (array) apply_filters( 'rondo_freescout_signing_keys', $keys );

		return array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $keys ),
					static fn( string $key ): bool => strlen( $key ) >= 32
				)
			)
		);
	}

	/** @return array{retention_days:int,source:string}|\WP_Error */
	public static function retention_policy() {
		$environment = self::environment_retention();
		if ( $environment !== null ) {
			if ( ! self::valid_retention( $environment ) ) {
				return new \WP_Error(
					'rondo_freescout_retention_invalid',
					'RONDO_AUDIT_RETENTION_DAYS moet tussen 90 en 730 liggen.',
					[ 'status' => 503 ]
				);
			}
			return [
				'retention_days' => (int) $environment,
				'source'         => 'environment',
			];
		}

		$setting = get_option( self::RETENTION_OPTION, null );
		if ( $setting !== null && self::valid_retention( $setting ) ) {
			return [
				'retention_days' => (int) $setting,
				'source'         => 'rondo_setting',
			];
		}

		return [
			'retention_days' => self::RETENTION_DEFAULT,
			'source'         => 'default',
		];
	}

	/** Return client-safe settings-screen state. */
	public static function retention_status(): array {
		$policy = self::retention_policy();
		if ( is_wp_error( $policy ) ) {
			return [
				'retention_days' => self::RETENTION_DEFAULT,
				'source'         => 'environment',
				'locked'         => true,
				'valid'          => false,
				'message'        => $policy->get_error_message(),
			];
		}

		return [
			'retention_days' => $policy['retention_days'],
			'source'         => $policy['source'],
			'locked'         => $policy['source'] === 'environment',
			'valid'          => true,
			'message'        => '',
		];
	}

	public static function update_retention( int $days ): bool {
		if ( self::environment_retention() !== null || ! self::valid_retention( $days ) ) {
			return false;
		}

		return (int) get_option( self::RETENTION_OPTION, 0 ) === $days
			|| update_option( self::RETENTION_OPTION, $days, false );
	}

	private static function environment_retention(): ?string {
		$value = defined( 'RONDO_AUDIT_RETENTION_DAYS' ) ? constant( 'RONDO_AUDIT_RETENTION_DAYS' ) : getenv( 'RONDO_AUDIT_RETENTION_DAYS' );
		if ( $value === false || $value === null || trim( (string) $value ) === '' ) {
			return null;
		}

		return trim( (string) $value );
	}

	private static function valid_retention( $days ): bool {
		return ctype_digit( (string) $days ) && (int) $days >= self::RETENTION_MIN && (int) $days <= self::RETENTION_MAX;
	}

	private static function environment_boolean( string $name ): ?bool {
		$value = defined( $name ) ? constant( $name ) : getenv( $name );
		if ( $value === false || $value === null || trim( (string) $value ) === '' ) {
			return null;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}
}
