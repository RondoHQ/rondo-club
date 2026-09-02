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

	public const RETENTION_OPTION  = 'freescout_audit_retention_days';
	public const RETENTION_DEFAULT = 365;
	public const RETENTION_MIN     = 90;
	public const RETENTION_MAX     = 730;

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
}
