<?php
/**
 * Canonical `fields` REST provider.
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
 * Exposes canonical fields backed by native WordPress metadata.
 */
final class RestFields {

	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'register' ], 20 );
		add_filter( 'rest_pre_dispatch', [ $this, 'guard_payload' ], 99, 3 );
	}

	/** Serialize a post for hand-built Rondo endpoints. */
	public static function for_post( string $context, int $post_id ): array {
		$serializer = new self( false );
		return $serializer->read( $context, $post_id );
	}

	/** Serialize a taxonomy term for hand-built Rondo endpoints. */
	public static function for_term( string $taxonomy, int $term_id ): array {
		$serializer = new self( false );
		return $serializer->read( $taxonomy, $term_id );
	}

	/** Register one `fields` attribute per REST-enabled object context. */
	public function register(): void {
		global $wp_rest_additional_fields;

		foreach ( Registry::contexts() as $context ) {
			unset( $wp_rest_additional_fields[ $context ]['acf'] );
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
			add_filter( 'rest_prepare_' . $context, [ $this, 'remove_legacy_attribute' ], PHP_INT_MAX );
		}
	}

	/** Remove the retired response attribute at the final prepare stage. */
	public function remove_legacy_attribute( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = $response->get_data();
		unset( $data['acf'] );
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Return a request's normalized canonical domain payload.
	 *
	 * Domain validators call this once so permission and business-rule code uses
	 * the same names as the public contract. Invalid values are left for the
	 * provider's field-specific 400 response.
	 *
	 * @return array<string,mixed>
	 */
	public static function request_payload( WP_REST_Request $request, string $context ): array {
		$fields = $request->get_param( 'fields' );
		if ( ! is_array( $fields ) ) {
			return [];
		}

		try {
			return Formatter::for_storage( $context, $fields );
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

		$legacy_payload = $request->get_param( 'acf' );
		if ( $legacy_payload !== null ) {
			self::log_contract_event( 'removed_acf_payload', $request );
			return $server->error_to_response(
				new WP_Error(
					'removed_acf_payload',
					__( 'The "acf" request attribute was removed. Send canonical values under "fields".', 'rondo' ),
					[ 'status' => 400 ]
				)
			);
		}

		return $result;
	}

	/** @param array<string,mixed>|object $object */
	private function read_post( string $context, $object ): array {
		$post_id = $this->object_id( $object );
		return $this->read( $context, $post_id );
	}

	/** @param array<string,mixed>|object $object */
	private function read_term( string $context, $object ): array {
		$term_id = $this->object_id( $object );
		return $this->read( $context, $term_id );
	}

	/**
	 * Read every registered field and format the canonical response.
	 */
	private function read( string $context, int $object_id ): array {
		$legacy = [];
		foreach ( Registry::fields_for( $context ) as $definition ) {
			if ( $definition['storage_name'] === null ) {
				continue;
			}
			$legacy[ $definition['storage_name'] ] = Registry::context_kind( $context ) === 'term'
				? NativeFieldStorage::read_term( $object_id, $definition )
				: NativeFieldStorage::read_post( $object_id, $definition );
		}

		if ( $context === 'person' ) {
			if ( AccessControl::is_scoped_member() ) {
				$legacy = AccessControl::filter_member_visible_fields( $legacy );
			}
			$legacy = AccessControl::filter_sensitive_fields( $legacy );
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
		return $this->write( $context, $value, $post_id );
	}

	/** @param array<string,mixed>|object $object */
	private function write_term( string $context, $value, $object ) {
		$term_id = $this->object_id( $object );
		return $this->write( $context, $value, $term_id );
	}

	/**
	 * Apply a partial canonical write through native metadata.
	 *
	 * @param mixed $value Payload.
	 * @return true|WP_Error
	 */
	private function write( string $context, $value, int $object_id ) {
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

		if ( Registry::context_kind( $context ) === 'term' ) {
			foreach ( $storage as $storage_name => $field_value ) {
				NativeFieldStorage::write_term( $object_id, Registry::resolve( $context, $storage_name ), $field_value );
			}
		} else {
			$result = Fields::update_many_for_post( $object_id, $storage );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
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
			$type                = $this->json_type( $definition );
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

	private function json_type( array $definition ): string {
		$type = $definition['type'];
		if ( in_array( $type, [ 'repeater', 'relationship', 'gallery', 'checkbox' ], true ) || ! empty( $definition['multiple'] ) ) {
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
		if ( in_array( $type, [ 'file', 'image' ], true ) ) {
			$return_format = $definition['return_format'] ?? 'array';
			return $return_format === 'array' ? 'object' : ( $return_format === 'id' ? 'integer' : 'string' );
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
