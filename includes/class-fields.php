<?php
/**
 * Context-aware PHP field API.
 *
 * @package Rondo\Fields
 */

namespace Rondo\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable internal API backed by native WordPress metadata.
 */
final class Fields {
	/** Return all registered values keyed by canonical name. */
	public static function all_for_post( int $post_id ): array {
		$context = self::post_context( $post_id );
		$storage = [];
		foreach ( Registry::fields_for( $context ) as $definition ) {
			if ( $definition['storage_name'] !== null ) {
				$storage[ $definition['storage_name'] ] = NativeFieldStorage::read_post( $post_id, $definition );
			}
		}
		return Registry::canonicalize( $context, $storage );
	}

	/** @return mixed */
	public static function get_for_post( int $post_id, string $canonical_name ) {
		$context    = self::post_context( $post_id );
		$definition = Registry::resolve( $context, $canonical_name );
		return NativeFieldStorage::read_post( $post_id, $definition );
	}

	/** Return null for a deleted or unsupported post instead of throwing. */
	public static function try_get_for_post( int $post_id, string $canonical_name ) {
		try {
			return self::get_for_post( $post_id, $canonical_name );
		} catch ( \InvalidArgumentException $error ) {
			return null;
		}
	}

	/** @param mixed $value */
	public static function update_for_post( int $post_id, string $canonical_name, $value ): bool {
		return self::update_many_for_post( $post_id, [ $canonical_name => $value ] ) === true;
	}

	/**
	 * Atomically apply a logical field update and fire domain services once.
	 *
	 * @return true|\WP_Error
	 */
	public static function update_many_for_post( int $post_id, array $values ) {
		$context = self::post_context( $post_id );
		$changes = [];
		foreach ( $values as $identifier => $value ) {
			$definition            = Registry::resolve( $context, (string) $identifier );
			$definition['context'] = $context;
			$old_value             = NativeFieldStorage::read_post( $post_id, $definition );
			$new_value             = apply_filters( 'rondo_fields_validate_value', $value, $post_id, $definition, $old_value );
			if ( is_wp_error( $new_value ) ) {
				return $new_value;
			}
			$new_value      = NativeFieldStorage::normalize_for_storage( $definition, $new_value );
			$comparable_old = NativeFieldStorage::normalize_for_comparison( $definition, $old_value );
			$comparable_new = NativeFieldStorage::normalize_for_comparison( $definition, $new_value );
			if ( maybe_serialize( $comparable_old ) === maybe_serialize( $comparable_new ) ) {
				continue;
			}
			$changes[] = [ $definition, $old_value, $new_value ];
		}

		foreach ( $changes as [ $definition, $old_value, $new_value ] ) {
			NativeFieldStorage::write_post( $post_id, $definition, $new_value );
			do_action( 'rondo_fields_updated', $post_id, $definition['canonical_name'], $new_value, $old_value, $definition );
		}
		if ( $changes ) {
			do_action( 'rondo_fields_saved_post', $post_id, $changes );
		}
		return true;
	}

	public static function delete_for_post( int $post_id, string $canonical_name ): bool {
		$context    = self::post_context( $post_id );
		$definition = Registry::resolve( $context, $canonical_name );
		return NativeFieldStorage::delete_post( $post_id, $definition );
	}

	/** @return mixed */
	public static function get_for_term( string $taxonomy, int $term_id, string $canonical_name ) {
		self::assert_term_context( $taxonomy );
		$definition = Registry::resolve( $taxonomy, $canonical_name );
		return NativeFieldStorage::read_term( $term_id, $definition );
	}

	/** @param mixed $value */
	public static function update_for_term( string $taxonomy, int $term_id, string $canonical_name, $value ): bool {
		self::assert_term_context( $taxonomy );
		$definition = Registry::resolve( $taxonomy, $canonical_name );
		return NativeFieldStorage::write_term( $term_id, $definition, $value );
	}

	public static function delete_for_term( string $taxonomy, int $term_id, string $canonical_name ): bool {
		self::assert_term_context( $taxonomy );
		$definition = Registry::resolve( $taxonomy, $canonical_name );
		return NativeFieldStorage::delete_term( $term_id, $definition );
	}

	private static function post_context( int $post_id ): string {
		$context = get_post_type( $post_id );
		if ( ! is_string( $context ) || Registry::context_kind( $context ) !== 'post' ) {
			throw new \InvalidArgumentException( "Post {$post_id} has no registered field context." );
		}
		return $context;
	}

	private static function assert_term_context( string $taxonomy ): void {
		if ( Registry::context_kind( $taxonomy ) !== 'term' ) {
			throw new \InvalidArgumentException( "{$taxonomy} is not a registered term field context." );
		}
	}
}
