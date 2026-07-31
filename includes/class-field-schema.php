<?php
/**
 * Native WordPress metadata registration for Rondo scalar fields.
 *
 * @package Rondo\Fields
 */

namespace Rondo\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FieldSchema {
	public function __construct() {
		add_action( 'init', [ $this, 'register' ], 20 );
	}

	public function register(): void {
		foreach ( Registry::contexts() as $context ) {
			if ( Registry::context_kind( $context ) !== 'post' || ! post_type_exists( $context ) ) {
				continue;
			}
			foreach ( Registry::fields_for( $context ) as $definition ) {
				if ( $definition['storage_name'] === null || $definition['type'] === 'repeater' ) {
					continue;
				}
				register_post_meta(
					$context,
					$definition['storage_name'],
					[
						'single'        => true,
						'type'          => $this->meta_type( $definition ),
						'show_in_rest'  => false,
						'auth_callback' => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
					]
				);
			}
		}
	}

	private function meta_type( array $definition ): string {
		if ( in_array( $definition['type'], [ 'relationship', 'gallery', 'checkbox' ], true ) || ! empty( $definition['multiple'] ) ) {
			return 'array';
		}
		if ( $definition['type'] === 'true_false' ) {
			return 'boolean';
		}
		if ( $definition['type'] === 'number' ) {
			return 'number';
		}
		return 'string';
	}
}
