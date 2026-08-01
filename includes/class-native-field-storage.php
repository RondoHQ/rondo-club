<?php
/**
 * Native WordPress metadata storage for registered Rondo fields.
 *
 * @package Rondo\Fields
 */

namespace Rondo\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the existing numbered metadata layout.
 *
 * Keeping the field-key reference rows makes the migration reversible and lets
 * existing databases move in place without a bulk value rewrite.
 */
final class NativeFieldStorage {

	/** @return mixed */
	public static function read_post( int $post_id, array $definition ) {
		return self::read( 'post', $post_id, $definition );
	}

	/** @return mixed */
	public static function read_term( int $term_id, array $definition ) {
		return self::read( 'term', $term_id, $definition );
	}

	/** @param mixed $value */
	public static function write_post( int $post_id, array $definition, $value ): bool {
		return self::write( 'post', $post_id, $definition, $value );
	}

	/** @param mixed $value */
	public static function write_term( int $term_id, array $definition, $value ): bool {
		return self::write( 'term', $term_id, $definition, $value );
	}

	public static function delete_post( int $post_id, array $definition ): bool {
		return self::delete( 'post', $post_id, $definition );
	}

	public static function delete_term( int $term_id, array $definition ): bool {
		return self::delete( 'term', $term_id, $definition );
	}

	/** Normalize a candidate value to the same typed shape returned by reads. */
	public static function normalize_for_comparison( array $definition, $value ) {
		if ( $definition['type'] === 'repeater' && is_array( $value ) ) {
			$rows = [];
			foreach ( $value as $row ) {
				if ( ! is_array( $row ) ) {
					$rows[] = $row;
					continue;
				}
				$normalized_row = [];
				foreach ( $row as $name => $child_value ) {
					try {
						$child = Registry::resolve_sub_field( $definition, (string) $name );
					} catch ( \InvalidArgumentException $error ) {
						$normalized_row[ $name ] = $child_value;
						continue;
					}
					$normalized_row[ $name ] = self::normalize_for_comparison( $child, $child_value );
				}
				$rows[] = $normalized_row;
			}
			return $rows;
		}

		return self::normalize_read_value( $definition, $value );
	}

	/** Normalize internal API values to the legacy ACF-compatible storage shape. */
	public static function normalize_for_storage( array $definition, $value ) {
		if ( $definition['type'] === 'repeater' && is_array( $value ) ) {
			$rows = [];
			foreach ( $value as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$normalized_row = [];
				foreach ( $row as $name => $child_value ) {
					try {
						$child = Registry::resolve_sub_field( $definition, (string) $name );
					} catch ( \InvalidArgumentException $error ) {
						continue;
					}
					if ( $child['storage_name'] === null ) {
						continue;
					}
					$normalized_row[ $child['storage_name'] ] = self::normalize_for_storage( $child, $child_value );
				}
				$rows[] = $normalized_row;
			}
			return $rows;
		}

		if ( $value === null ) {
			return in_array( $definition['type'], [ 'repeater', 'relationship', 'gallery', 'checkbox' ], true ) ? [] : '';
		}

		switch ( $definition['type'] ) {
			case 'date_picker':
				if ( is_string( $value ) && preg_match( '/^(\d{4})-?(\d{2})-?(\d{2})$/', $value, $matches ) ) {
					return $matches[1] . $matches[2] . $matches[3];
				}
				return $value;
			case 'true_false':
				return $value ? 1 : 0;
			case 'number':
				if ( $value === '' || ! is_numeric( $value ) ) {
					return $value;
				}
				$number = (float) $value;
				return $number === (float) (int) $number ? (int) $number : $number;
			default:
				return $value;
		}
	}

	/** @return mixed */
	private static function read( string $kind, int $object_id, array $definition ) {
		$name = (string) $definition['storage_name'];
		if ( $definition['type'] !== 'repeater' ) {
			if ( ! self::exists( $kind, $object_id, $name ) ) {
				return self::default_value( $definition );
			}
			return self::normalize_read_value( $definition, self::get( $kind, $object_id, $name ) );
		}

		$count = self::get( $kind, $object_id, $name );
		// Support fixtures or transitional writes that stored complete rows.
		if ( is_array( $count ) ) {
			return $count;
		}
		$count = max( 0, (int) $count );
		$rows  = [];
		for ( $index = 0; $index < $count; $index++ ) {
			$row = [];
			foreach ( $definition['sub_fields'] ?? [] as $child ) {
				if ( $child['storage_name'] === null ) {
					continue;
				}
				$child_name                    = $name . '_' . $index . '_' . $child['storage_name'];
				$row[ $child['storage_name'] ] = self::exists( $kind, $object_id, $child_name )
					? self::normalize_read_value( $child, self::get( $kind, $object_id, $child_name ) )
					: self::default_value( $child );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** @param mixed $value */
	private static function write( string $kind, int $object_id, array $definition, $value ): bool {
		$name = (string) $definition['storage_name'];
		if ( $definition['type'] !== 'repeater' ) {
			self::set( $kind, $object_id, $name, $value );
			self::write_reference( $kind, $object_id, $name, $definition );
			return true;
		}

		$rows      = is_array( $value ) ? array_values( $value ) : [];
		$old_count = max( 0, (int) self::get( $kind, $object_id, $name ) );
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $definition['sub_fields'] ?? [] as $child ) {
				if ( $child['storage_name'] === null ) {
					continue;
				}
				$child_name  = $name . '_' . $index . '_' . $child['storage_name'];
				$child_value = array_key_exists( $child['storage_name'], $row )
					? $row[ $child['storage_name'] ]
					: self::default_value( $child );
				self::set( $kind, $object_id, $child_name, $child_value );
				self::write_reference( $kind, $object_id, $child_name, $child );
			}
		}

		// Explicitly remove numbered rows beyond the new logical length.
		for ( $index = count( $rows ); $index < $old_count; $index++ ) {
			foreach ( $definition['sub_fields'] ?? [] as $child ) {
				if ( $child['storage_name'] === null ) {
					continue;
				}
				$child_name = $name . '_' . $index . '_' . $child['storage_name'];
				self::remove( $kind, $object_id, $child_name );
				self::remove( $kind, $object_id, '_' . $child_name );
			}
		}

		if ( $rows === [] ) {
			self::remove( $kind, $object_id, $name );
			self::remove( $kind, $object_id, '_' . $name );
		} else {
			self::set( $kind, $object_id, $name, count( $rows ) );
			self::write_reference( $kind, $object_id, $name, $definition );
		}
		return true;
	}

	private static function delete( string $kind, int $object_id, array $definition ): bool {
		$name = (string) $definition['storage_name'];
		if ( $definition['type'] === 'repeater' ) {
			$count = max( 0, (int) self::get( $kind, $object_id, $name ) );
			for ( $index = 0; $index < $count; $index++ ) {
				foreach ( $definition['sub_fields'] ?? [] as $child ) {
					if ( $child['storage_name'] === null ) {
						continue;
					}
					$child_name = $name . '_' . $index . '_' . $child['storage_name'];
					self::remove( $kind, $object_id, $child_name );
					self::remove( $kind, $object_id, '_' . $child_name );
				}
			}
		}
		$deleted = self::remove( $kind, $object_id, $name );
		self::remove( $kind, $object_id, '_' . $name );
		return $deleted;
	}

	/** @return mixed */
	private static function default_value( array $definition ) {
		if ( array_key_exists( 'default_value', $definition ) ) {
			return $definition['default_value'];
		}
		return in_array( $definition['type'], [ 'repeater', 'relationship', 'gallery', 'checkbox' ], true ) ? [] : '';
	}

	/** @param mixed $value @return mixed */
	private static function normalize_read_value( array $definition, $value ) {
		if ( ! empty( $definition['multiple'] ) && $definition['type'] === 'select' ) {
			return is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : [];
		}
		switch ( $definition['type'] ) {
			case 'true_false':
				return (bool) $value;
			case 'number':
				if ( $value === '' || ! is_numeric( $value ) ) {
					return $value;
				}
				return (float) $value === (float) (int) $value ? (int) $value : (float) $value;
			case 'post_object':
			case 'taxonomy':
				if ( ! empty( $definition['multiple'] ) ) {
					return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : [];
				}
				return $value === '' ? null : self::object_id( $value );
			case 'relationship':
				return is_array( $value ) ? array_values( array_map( 'absint', $value ) ) : [];
			default:
				return $value;
		}
	}

	private static function write_reference( string $kind, int $object_id, string $name, array $definition ): void {
		if ( ! empty( $definition['key'] ) ) {
			self::set( $kind, $object_id, '_' . $name, $definition['key'] );
		}
	}

	/** @param mixed $value */
	private static function object_id( $value ): int {
		if ( is_object( $value ) ) {
			return absint( $value->ID ?? $value->term_id ?? 0 );
		}
		if ( is_array( $value ) ) {
			if ( array_is_list( $value ) ) {
				return isset( $value[0] ) ? self::object_id( $value[0] ) : 0;
			}
			return absint( $value['ID'] ?? $value['id'] ?? $value['term_id'] ?? 0 );
		}
		return absint( $value );
	}

	/** @return mixed */
	private static function get( string $kind, int $object_id, string $key ) {
		return $kind === 'term' ? get_term_meta( $object_id, $key, true ) : get_post_meta( $object_id, $key, true );
	}

	/** @param mixed $value */
	private static function set( string $kind, int $object_id, string $key, $value ): void {
		if ( $kind === 'term' ) {
			update_term_meta( $object_id, $key, $value );
		} else {
			update_post_meta( $object_id, $key, $value );
		}
	}

	private static function remove( string $kind, int $object_id, string $key ): bool {
		return $kind === 'term' ? delete_term_meta( $object_id, $key ) : delete_post_meta( $object_id, $key );
	}

	private static function exists( string $kind, int $object_id, string $key ): bool {
		return metadata_exists( $kind, $object_id, $key );
	}
}
