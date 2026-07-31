<?php
/**
 * Canonical Rondo field registry.
 *
 * @package Rondo\Fields
 */

namespace Rondo\Fields;

use InvalidArgumentException;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves canonical API names, legacy storage names and immutable field keys.
 */
final class Registry {

	/** @var array<string,mixed>|null */
	private static ?array $config = null;

	/** @var bool */
	private static bool $validated = false;

	/**
	 * Return the complete compiled registry.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( self::$config === null ) {
			$config = require RONDO_THEME_DIR . '/includes/config/field-registry.php';
			if ( ! is_array( $config ) ) {
				throw new RuntimeException( 'The Rondo field registry is invalid.' );
			}
			self::$config = $config;
		}

		if ( ! self::$validated ) {
			self::validate( self::$config );
			self::$validated = true;
		}

		return self::$config;
	}

	/**
	 * Clear memoized configuration. Intended for tests and option migrations.
	 */
	public static function reset(): void {
		self::$config    = null;
		self::$validated = false;
	}

	/**
	 * Registry schema version.
	 */
	public static function version(): int {
		return (int) self::all()['version'];
	}

	/**
	 * Return all registered context names.
	 *
	 * @return string[]
	 */
	public static function contexts(): array {
		return array_keys( self::all()['contexts'] );
	}

	/**
	 * Whether a context belongs to posts or terms.
	 */
	public static function context_kind( string $context ): string {
		$contexts = self::all()['contexts'];
		if ( ! isset( $contexts[ $context ] ) ) {
			throw new InvalidArgumentException( "Unknown field context: {$context}" );
		}
		return (string) $contexts[ $context ]['kind'];
	}

	/**
	 * Return definitions keyed by canonical name.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields_for( string $context ): array {
		$contexts = self::all()['contexts'];
		if ( ! isset( $contexts[ $context ] ) ) {
			throw new InvalidArgumentException( "Unknown field context: {$context}" );
		}
		return $contexts[ $context ]['fields'];
	}

	/**
	 * Resolve a canonical name, legacy storage name or immutable field key.
	 *
	 * @return array<string,mixed>
	 */
	public static function resolve( string $context, string $identifier ): array {
		$matches = [];
		foreach ( self::fields_for( $context ) as $definition ) {
			if (
				$definition['canonical_name'] === $identifier
				|| $definition['storage_name'] === $identifier
				|| ( isset( $definition['key'] ) && $definition['key'] === $identifier )
			) {
				$matches[] = $definition;
			}
		}

		if ( count( $matches ) !== 1 ) {
			$reason = count( $matches ) === 0 ? 'unresolved' : 'ambiguous';
			throw new InvalidArgumentException( "Field {$context}.{$identifier} is {$reason}." );
		}

		return $matches[0];
	}

	/**
	 * Resolve a nested field identifier within a repeater.
	 *
	 * @param array<string,mixed> $parent Parent definition.
	 * @return array<string,mixed>
	 */
	public static function resolve_sub_field( array $parent, string $identifier ): array {
		$matches = [];
		foreach ( $parent['sub_fields'] ?? [] as $definition ) {
			if (
				$definition['canonical_name'] === $identifier
				|| $definition['storage_name'] === $identifier
				|| ( isset( $definition['key'] ) && $definition['key'] === $identifier )
			) {
				$matches[] = $definition;
			}
		}

		if ( count( $matches ) !== 1 ) {
			$reason = count( $matches ) === 0 ? 'unresolved' : 'ambiguous';
			throw new InvalidArgumentException( "Nested field {$identifier} is {$reason}." );
		}

		return $matches[0];
	}

	/**
	 * Convert an ACF/storage-shaped payload to canonical names.
	 *
	 * @param array<string,mixed> $payload Legacy payload.
	 * @return array<string,mixed>
	 */
	public static function canonicalize( string $context, array $payload ): array {
		return self::map_payload( $context, $payload, true, false );
	}

	/**
	 * Convert a canonical write payload to storage names.
	 *
	 * Read-only and unknown fields are rejected with a canonical field path.
	 *
	 * @param array<string,mixed> $payload Canonical payload.
	 * @return array<string,mixed>
	 */
	public static function to_storage( string $context, array $payload ): array {
		return self::map_payload( $context, $payload, false, true );
	}

	/**
	 * Map a complete payload in one direction.
	 *
	 * @param array<string,mixed> $payload Input payload.
	 * @return array<string,mixed>
	 */
	private static function map_payload( string $context, array $payload, bool $to_canonical, bool $for_write ): array {
		$mapped = [];
		foreach ( $payload as $identifier => $value ) {
			try {
				$definition = self::resolve( $context, (string) $identifier );
			} catch ( InvalidArgumentException $error ) {
				throw new InvalidArgumentException( 'fields.' . $identifier . ': ' . $error->getMessage(), 0, $error );
			}

			if ( $for_write && ! empty( $definition['read_only'] ) ) {
				throw new InvalidArgumentException( 'fields.' . $definition['canonical_name'] . ' is read-only.' );
			}

			$output_name = $to_canonical ? $definition['canonical_name'] : $definition['storage_name'];
			if ( $output_name === null ) {
				continue;
			}
			$mapped[ $output_name ] = self::map_nested_value( $definition, $value, $to_canonical, $for_write, 'fields.' . $definition['canonical_name'] );
		}
		return $mapped;
	}

	/**
	 * Map nested repeater rows.
	 *
	 * @param array<string,mixed> $definition Field definition.
	 * @param mixed               $value Value to map.
	 * @return mixed
	 */
	private static function map_nested_value( array $definition, $value, bool $to_canonical, bool $for_write, string $path ) {
		if ( empty( $definition['sub_fields'] ) || ! is_array( $value ) ) {
			return $value;
		}

		$rows = [];
		foreach ( $value as $row_index => $row ) {
			if ( ! is_array( $row ) ) {
				throw new InvalidArgumentException( "{$path}.{$row_index} must be an object." );
			}
			$mapped_row = [];
			foreach ( $row as $identifier => $child_value ) {
				try {
					$child = self::resolve_sub_field( $definition, (string) $identifier );
				} catch ( InvalidArgumentException $error ) {
					throw new InvalidArgumentException( "{$path}.{$row_index}.{$identifier}: " . $error->getMessage(), 0, $error );
				}
				$child_path = "{$path}.{$row_index}.{$child['canonical_name']}";
				if ( $for_write && ! empty( $child['read_only'] ) ) {
					throw new InvalidArgumentException( "{$child_path} is read-only." );
				}
				$output_name = $to_canonical ? $child['canonical_name'] : $child['storage_name'];
				if ( $output_name !== null ) {
					$mapped_row[ $output_name ] = $child_value;
				}
			}
			$rows[] = $mapped_row;
		}
		return $rows;
	}

	/**
	 * Validate collisions and reserved-name ownership.
	 *
	 * @param array<string,mixed> $config Registry configuration.
	 */
	private static function validate( array $config ): void {
		foreach ( $config['contexts'] as $context => $context_config ) {
			$canonical_seen = [];
			$storage_seen   = [];
			$key_seen       = [];
			$computed       = $config['computed_top_level'][ $context ] ?? [];
			$exceptions     = $config['reserved_exceptions'][ $context ] ?? [];

			foreach ( $context_config['fields'] as $canonical_name => $definition ) {
				if ( $canonical_name !== $definition['canonical_name'] ) {
					throw new RuntimeException( "Registry key mismatch for {$context}.{$canonical_name}." );
				}
				if ( isset( $canonical_seen[ $canonical_name ] ) ) {
					throw new RuntimeException( "Duplicate canonical field {$context}.{$canonical_name}." );
				}
				if ( in_array( $canonical_name, $computed, true ) ) {
					throw new RuntimeException( "Field {$context}.{$canonical_name} collides with a computed response property." );
				}
				if ( in_array( $canonical_name, $config['reserved_top_level'], true ) && ! in_array( $canonical_name, $exceptions, true ) ) {
					throw new RuntimeException( "Field {$context}.{$canonical_name} uses a reserved WordPress property name." );
				}

				$storage_name = $definition['storage_name'];
				if ( $storage_name !== null && isset( $storage_seen[ $storage_name ] ) ) {
					throw new RuntimeException( "Duplicate storage field {$context}.{$storage_name}." );
				}
				if ( isset( $definition['key'] ) && isset( $key_seen[ $definition['key'] ] ) ) {
					throw new RuntimeException( "Duplicate field key {$context}.{$definition['key']}." );
				}

				$canonical_seen[ $canonical_name ] = true;
				if ( $storage_name !== null ) {
					$storage_seen[ $storage_name ] = true;
				}
				if ( isset( $definition['key'] ) ) {
					$key_seen[ $definition['key'] ] = true;
				}
			}
		}
	}
}
