<?php
/**
 * Canonical `fields` REST compatibility provider.
 *
 * @package Rondo\Fields
 */

namespace Rondo\Fields;

use InvalidArgumentException;
use Rondo\Core\AccessControl;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes canonical fields beside ACF while persistence remains ACF-backed.
 */
final class RestFields {

	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'register' ], 20 );
		// Run after ACF's request initializer. That callback historically returns
		// null instead of preserving an earlier short-circuit response.
		add_filter( 'rest_pre_dispatch', [ $this, 'guard_payload' ], 99, 3 );
	}

	/** Serialize a post for hand-built Rondo endpoints. */
	public static function for_post( string $context, int $post_id ): array {
		$serializer = new self( false );
		return $serializer->read( $context, $post_id, $post_id );
	}

	/** Serialize a taxonomy term for hand-built Rondo endpoints. */
	public static function for_term( string $taxonomy, int $term_id ): array {
		$serializer = new self( false );
		return $serializer->read( $taxonomy, $taxonomy . '_' . $term_id, $term_id );
	}

	/** Register one `fields` attribute per REST-enabled object context. */
	public function register(): void {
		foreach ( Registry::contexts() as $context ) {
			register_rest_field(
				$context,
				'fields',
				[
					'get_callback'    => Registry::context_kind( $context ) === 'term'
						? fn( $object ) => $this->read_term( $context, $object )
						: fn( $object ) => $this->read_post( $context, $object ),
					'update_callback' => Registry::context_kind( $context ) === 'term'
						? fn( $value, $object ) => $this->write_term( $context, $value, $object )
						: fn( $value, $object ) => $this->write_post( $context, $value, $object ),
					'schema'          => $this->schema_for( $context ),
				]
			);
		}
	}

	/**
	 * Return a request's domain payload using legacy storage names.
	 *
	 * Compatibility-stage domain validators call this once so `acf` and
	 * `fields` traverse the same permission and business-rule code paths.
	 * Invalid canonical values are left for the provider's field-specific 400.
	 *
	 * @return array<string,mixed>
	 */
	public static function request_payload( WP_REST_Request $request, string $context ): array {
		$acf = $request->get_param( 'acf' );
		if ( is_array( $acf ) ) {
			return $acf;
		}

		$fields = $request->get_param( 'fields' );
		if ( ! is_array( $fields ) ) {
			return [];
		}

		try {
			return Registry::to_storage( $context, Formatter::for_storage( $context, $fields ) );
		} catch ( InvalidArgumentException $error ) {
			return $fields;
		}
	}

	/**
	 * Reject ambiguous writes and record deprecated legacy writes without values.
	 *
	 * @param mixed $result Pre-dispatch result.
	 * @return mixed
	 */
	public function guard_payload( $result, $server, WP_REST_Request $request ) {
		if ( in_array( $request->get_method(), [ 'GET', 'HEAD', 'OPTIONS' ], true ) ) {
			return $result;
		}

		$acf    = $request->get_param( 'acf' );
		$fields = $request->get_param( 'fields' );
		if ( is_array( $acf ) && is_array( $fields ) ) {
			self::log_contract_event( 'ambiguous_field_payload', $request );
			return $server->error_to_response(
				new WP_Error(
					'ambiguous_field_payload',
					__( 'Send either "acf" or "fields", never both.', 'rondo' ),
					[ 'status' => 400 ]
				)
			);
		}

		if ( is_array( $acf ) ) {
			self::log_contract_event( 'deprecated_acf_write', $request );
		}

		return $result;
	}

	/** @param array<string,mixed>|object $object */
	private function read_post( string $context, $object ): array {
		$post_id = $this->object_id( $object );
		return $this->read( $context, $post_id, $post_id );
	}

	/** @param array<string,mixed>|object $object */
	private function read_term( string $context, $object ): array {
		$term_id = $this->object_id( $object );
		return $this->read( $context, $context . '_' . $term_id, $term_id );
	}

	/**
	 * Read every registered field through ACF and format the canonical response.
	 *
	 * @param int|string $target ACF object identifier.
	 */
	private function read( string $context, $target, int $object_id ): array {
		$legacy = [];
		foreach ( Registry::fields_for( $context ) as $definition ) {
			if ( $definition['storage_name'] === null ) {
				continue;
			}
			$identifier                            = $definition['key'] ?? $definition['storage_name'];
			$legacy[ $definition['storage_name'] ] = ( $definition['backend'] ?? 'acf' ) === 'meta'
				? get_post_meta( $object_id, $definition['storage_name'], true )
				: get_field( $identifier, $target );
		}

		if ( $context === 'person' ) {
			if ( AccessControl::is_scoped_member() ) {
				$legacy = AccessControl::filter_member_visible_acf( $legacy );
			}
			$legacy = AccessControl::filter_sensitive_acf( $legacy );
		}

		try {
			$fields = Formatter::for_wire( $context, Registry::canonicalize( $context, $legacy ) );
		} catch ( InvalidArgumentException $error ) {
			self::log_resolution_failure( $context, $error->getMessage() );
			return [];
		}

		if ( $context === 'person' && isset( $fields['relationships'] ) ) {
			$fields['relationships'] = $this->enrich_relationships( $fields['relationships'], $object_id );
		}

		return $fields;
	}

	/** @param array<string,mixed>|object $object */
	private function write_post( string $context, $value, $object ) {
		$post_id = $this->object_id( $object );
		return $this->write( $context, $value, $post_id, $post_id );
	}

	/** @param array<string,mixed>|object $object */
	private function write_term( string $context, $value, $object ) {
		$term_id = $this->object_id( $object );
		return $this->write( $context, $value, $context . '_' . $term_id, $term_id );
	}

	/**
	 * Apply a partial canonical write through existing ACF update hooks.
	 *
	 * @param mixed      $value Payload.
	 * @param int|string $target ACF target.
	 * @return true|WP_Error
	 */
	private function write( string $context, $value, $target, int $object_id ) {
		if ( ! is_array( $value ) ) {
			return $this->invalid_field_error( 'fields', 'The fields payload must be an object.' );
		}

		try {
			$normalized = Formatter::for_storage( $context, $value );
			$storage    = Registry::to_storage( $context, $normalized );
		} catch ( InvalidArgumentException $error ) {
			self::log_resolution_failure( $context, $error->getMessage() );
			$field = preg_match( '/^(fields(?:\.[A-Za-z0-9_-]+)*)/', $error->getMessage(), $matches )
				? $matches[1]
				: 'fields';
			return $this->invalid_field_error( $field, $error->getMessage() );
		}

		foreach ( $storage as $storage_name => $field_value ) {
			$definition = Registry::resolve( $context, $storage_name );
			$identifier = $definition['key'] ?? $storage_name;
			if ( ( $definition['backend'] ?? 'acf' ) === 'meta' && Registry::context_kind( $context ) === 'post' ) {
				if ( $field_value === '' || $field_value === null ) {
					delete_post_meta( $object_id, $storage_name );
				} else {
					update_post_meta( $object_id, $storage_name, $field_value );
				}
			} else {
				update_field( $identifier, $field_value, $target );
			}
		}

		// update_field() runs update-value filters. The logical REST update also
		// needs the post-save domain services (titles, inverse relationships,
		// volunteer status and cache invalidation) exactly once per payload.
		if ( Registry::context_kind( $context ) === 'post' && $object_id > 0 ) {
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Third-party ACF hook name.
			do_action( 'acf/save_post', $object_id );
		}

		return true;
	}

	/**
	 * Add the pinned relationship read-only properties after visibility checks.
	 *
	 * @param mixed $relationships Relationship rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function enrich_relationships( $relationships, int $source_person_id ): array {
		if ( ! is_array( $relationships ) ) {
			return [];
		}

		$enriched = [];
		foreach ( $relationships as $relationship ) {
			if ( ! is_array( $relationship ) ) {
				continue;
			}
			$person_id = absint( $relationship['related_person_id'] ?? 0 );
			if ( ! $person_id || $person_id === $source_person_id || ! AccessControl::can_view_person( $person_id ) ) {
				continue;
			}

			$type_id                           = absint( $relationship['relationship_type_id'] ?? 0 );
			$term                              = $type_id ? get_term( $type_id, 'relationship_type' ) : null;
			$relationship['person_name']       = get_the_title( $person_id );
			$relationship['person_thumbnail']  = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: '';
			$relationship['relationship_name'] = $term && ! is_wp_error( $term ) ? $term->name : '';
			$relationship['relationship_slug'] = $term && ! is_wp_error( $term ) ? $term->slug : '';
			$enriched[]                        = $relationship;
		}
		return $enriched;
	}

	/** @return array<string,mixed> */
	private function schema_for( string $context ): array {
		$properties = [];
		foreach ( Registry::fields_for( $context ) as $name => $definition ) {
			$type                = $this->json_type( $definition['type'] );
			$properties[ $name ] = [
				'description' => $definition['instructions'] ?? $definition['label'] ?? $name,
				'type'        => empty( $definition['required'] ) && ! in_array( $type, [ 'array', 'boolean' ], true )
					? [ $type, 'null' ]
					: $type,
				'readonly'    => ! empty( $definition['read_only'] ),
			];
		}
		return [
			'description' => 'Canonical Rondo domain fields.',
			'type'        => 'object',
			'context'     => [ 'view', 'edit' ],
			'properties'  => $properties,
		];
	}

	private function json_type( string $type ): string {
		if ( in_array( $type, [ 'repeater', 'relationship', 'gallery', 'checkbox' ], true ) ) {
			return 'array';
		}
		if ( $type === 'true_false' ) {
			return 'boolean';
		}
		if ( $type === 'number' ) {
			return 'number';
		}
		if ( in_array( $type, [ 'post_object', 'taxonomy' ], true ) ) {
			return 'integer';
		}
		return 'string';
	}

	private function invalid_field_error( string $field, string $message ): WP_Error {
		return new WP_Error(
			'rondo_invalid_field',
			__( 'Invalid field value.', 'rondo' ),
			[
				'status' => 400,
				'field'  => $field,
				'detail' => $message,
			]
		);
	}

	/** @param array<string,mixed>|object $object */
	private function object_id( $object ): int {
		if ( is_object( $object ) ) {
			return absint( $object->ID ?? $object->term_id ?? 0 );
		}
		return absint( $object['id'] ?? $object['ID'] ?? $object['term_id'] ?? 0 );
	}

	private static function log_contract_event( string $event, WP_REST_Request $request ): void {
		$user = wp_get_current_user();
		error_log(
			sprintf(
				'Rondo fields contract: event=%s method=%s route=%s user_id=%d user_login=%s',
				$event,
				$request->get_method(),
				$request->get_route(),
				(int) $user->ID,
				$user->user_login ?: 'anonymous'
			)
		);
	}

	private static function log_resolution_failure( string $context, string $message ): void {
		error_log( sprintf( 'Rondo field registry failure: context=%s error=%s', $context, $message ) );
	}
}
