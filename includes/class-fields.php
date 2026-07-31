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
 * Stable internal API whose backend can move from ACF to native meta per context.
 */
final class Fields {

	/** @return mixed */
	public static function get_for_post( int $post_id, string $canonical_name ) {
		$context    = self::post_context( $post_id );
		$definition = Registry::resolve( $context, $canonical_name );
		if ( ( $definition['backend'] ?? 'acf' ) === 'meta' ) {
			return get_post_meta( $post_id, $definition['storage_name'], true );
		}
		return get_field( $definition['key'] ?? $definition['storage_name'], $post_id );
	}

	/** @param mixed $value */
	public static function update_for_post( int $post_id, string $canonical_name, $value ): bool {
		$context    = self::post_context( $post_id );
		$definition = Registry::resolve( $context, $canonical_name );
		if ( ( $definition['backend'] ?? 'acf' ) === 'meta' ) {
			return update_post_meta( $post_id, $definition['storage_name'], $value ) !== false;
		}
		return (bool) update_field( $definition['key'] ?? $definition['storage_name'], $value, $post_id );
	}

	public static function delete_for_post( int $post_id, string $canonical_name ): bool {
		$context    = self::post_context( $post_id );
		$definition = Registry::resolve( $context, $canonical_name );
		if ( ( $definition['backend'] ?? 'acf' ) === 'meta' ) {
			return delete_post_meta( $post_id, $definition['storage_name'] );
		}
		return (bool) delete_field( $definition['key'] ?? $definition['storage_name'], $post_id );
	}

	/** @return mixed */
	public static function get_for_term( string $taxonomy, int $term_id, string $canonical_name ) {
		self::assert_term_context( $taxonomy );
		$definition = Registry::resolve( $taxonomy, $canonical_name );
		return get_field( $definition['key'] ?? $definition['storage_name'], $taxonomy . '_' . $term_id );
	}

	/** @param mixed $value */
	public static function update_for_term( string $taxonomy, int $term_id, string $canonical_name, $value ): bool {
		self::assert_term_context( $taxonomy );
		$definition = Registry::resolve( $taxonomy, $canonical_name );
		return (bool) update_field( $definition['key'] ?? $definition['storage_name'], $value, $taxonomy . '_' . $term_id );
	}

	public static function delete_for_term( string $taxonomy, int $term_id, string $canonical_name ): bool {
		self::assert_term_context( $taxonomy );
		$definition = Registry::resolve( $taxonomy, $canonical_name );
		return (bool) delete_field( $definition['key'] ?? $definition['storage_name'], $taxonomy . '_' . $term_id );
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
