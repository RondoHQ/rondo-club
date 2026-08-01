<?php
/**
 * Native custom field definition store.
 *
 * @package Rondo\CustomFields
 */

namespace Rondo\CustomFields;

use Rondo\Fields\Registry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores dynamic definitions in a schema-versioned WordPress option. */
class Manager {
	public const SUPPORTED_POST_TYPES = [ 'person', 'team', 'commissie' ];
	public const OPTION_NAME          = 'rondo_dynamic_field_definitions';
	public const SCHEMA_VERSION       = 1;
	private const SUPPORTED_TYPES     = [ 'text', 'textarea', 'number', 'url', 'email', 'select', 'checkbox', 'radio', 'true_false', 'date' ];

	private const UPDATABLE_PROPERTIES = [
		'label',
		'instructions',
		'required',
		'choices',
		'default_value',
		'placeholder',
		'min',
		'max',
		'step',
		'prepend',
		'append',
		'display_format',
		'return_format',
		'first_day',
		'allow_null',
		'multiple',
		'ui',
		'layout',
		'toggle',
		'allow_custom',
		'save_custom',
		'maxlength',
		'ui_on_text',
		'ui_off_text',
		'preview_size',
		'library',
		'min_width',
		'max_width',
		'min_height',
		'max_height',
		'min_size',
		'max_size',
		'mime_types',
		'post_type',
		'filters',
		'menu_order',
		'unique',
		'editable_in_ui',
	];

	/** Return a synthetic group descriptor kept for API compatibility. */
	public function ensure_field_group( string $post_type ) {
		if ( ! $this->is_valid_post_type( $post_type ) ) {
			return new WP_Error( 'invalid_post_type', sprintf( 'Post type "%s" is not supported.', $post_type ) );
		}
		return [
			'ID'        => 0,
			'key'       => 'group_custom_fields_' . $post_type,
			'title'     => 'Custom Fields',
			'post_type' => $post_type,
			'native'    => true,
		];
	}

	public function generate_field_key( string $label, string $post_type ): string {
		$base = 'field_custom_' . $post_type . '_' . sanitize_title( $label );
		$key  = $base;
		while ( $this->get_field( $key ) ) {
			$key = $base . '_' . strtolower( wp_generate_password( 6, false, false ) );
		}
		return $key;
	}

	/** @return array<string,mixed>|WP_Error */
	public function create_field( string $post_type, array $field_config ) {
		if ( ! $this->is_valid_post_type( $post_type ) ) {
			return new WP_Error( 'invalid_post_type', sprintf( 'Post type "%s" is not supported.', $post_type ) );
		}
		if ( empty( $field_config['label'] ) || empty( $field_config['type'] ) ) {
			return new WP_Error( 'missing_required', 'Field label and type are required.' );
		}
		$field_type = sanitize_key( (string) $field_config['type'] );
		if ( ! in_array( $field_type, self::SUPPORTED_TYPES, true ) ) {
			return new WP_Error( 'invalid_field_type', 'The field type is not supported.' );
		}

		$storage_name = ! empty( $field_config['name'] ) ? sanitize_title( $field_config['name'] ) : sanitize_title( $field_config['label'] );
		$canonical    = ! empty( $field_config['canonical_name'] )
			? sanitize_key( $field_config['canonical_name'] )
			: sanitize_key( str_replace( '-', '_', $storage_name ) );
		$collision    = $this->collision( $post_type, $canonical, $storage_name );
		if ( $collision ) {
			return $collision;
		}

		$fields       = $this->get_fields( $post_type, true );
		$field        = [
			'id'             => $this->generate_field_key( (string) $field_config['label'], $post_type ),
			'key'            => '',
			'label'          => sanitize_text_field( (string) $field_config['label'] ),
			'name'           => $storage_name,
			'storage_key'    => $storage_name,
			'canonical_name' => $canonical,
			'type'           => $field_type,
			'active'         => true,
			'menu_order'     => count( $fields ) + 1,
			'instructions'   => '',
			'required'       => 0,
			'editable_in_ui' => true,
		];
		$field['key'] = $field['id'];
		foreach ( self::UPDATABLE_PROPERTIES as $property ) {
			if ( array_key_exists( $property, $field_config ) ) {
				$field[ $property ] = $this->sanitize_property( $property, $field_config[ $property ] );
			}
		}
		if ( $field['type'] === 'date' ) {
			$field['display_format'] = 'Y-m-d';
			$field['return_format']  = 'Y-m-d';
		}

		$fields[] = $field;
		$this->put_context( $post_type, $fields );
		return $field;
	}

	/** @return array<string,mixed>|WP_Error */
	public function update_field( string $field_key, array $updates ) {
		$location = $this->locate( $field_key );
		if ( ! $location ) {
			return new WP_Error( 'field_not_found', sprintf( 'Field with key "%s" not found.', $field_key ) );
		}
		[ $post_type, $index, $fields ] = $location;
		foreach ( self::UPDATABLE_PROPERTIES as $property ) {
			if ( array_key_exists( $property, $updates ) ) {
				$fields[ $index ][ $property ] = $this->sanitize_property( $property, $updates[ $property ] );
			}
		}
		if ( $fields[ $index ]['type'] === 'date' ) {
			$fields[ $index ]['display_format'] = 'Y-m-d';
			$fields[ $index ]['return_format']  = 'Y-m-d';
		}
		$this->put_context( $post_type, $fields );
		return $fields[ $index ];
	}

	/** @return array<string,mixed>|WP_Error */
	public function deactivate_field( string $field_key ) {
		return $this->set_active( $field_key, false );
	}

	/** @return array<string,mixed>|WP_Error */
	public function reactivate_field( string $field_key ) {
		return $this->set_active( $field_key, true );
	}

	/** @return array<int,array<string,mixed>> */
	public function get_fields( string $post_type, bool $include_inactive = false ): array {
		if ( ! $this->is_valid_post_type( $post_type ) ) {
			return [];
		}
		$store  = $this->store();
		$fields = array_values( $store['contexts'][ $post_type ] ?? [] );
		if ( ! $include_inactive ) {
			$fields = array_values( array_filter( $fields, static fn( $field ) => ! empty( $field['active'] ) ) );
		}
		usort( $fields, static fn( $a, $b ) => (int) ( $a['menu_order'] ?? 0 ) <=> (int) ( $b['menu_order'] ?? 0 ) );
		return $fields;
	}

	/** @return array<string,mixed>|false */
	public function get_field( string $field_key ) {
		$location = $this->locate( $field_key );
		return $location ? $location[2][ $location[1] ] : false;
	}

	/** @return true|WP_Error */
	public function reorder_fields( string $post_type, array $field_keys ) {
		if ( ! $this->is_valid_post_type( $post_type ) ) {
			return new WP_Error( 'invalid_post_type', 'Invalid post type.' );
		}
		$fields = $this->get_fields( $post_type, true );
		$known  = array_column( $fields, null, 'key' );
		foreach ( $field_keys as $index => $key ) {
			if ( ! isset( $known[ $key ] ) ) {
				return new WP_Error( 'field_not_found', sprintf( 'Field with key "%s" not found.', $key ) );
			}
			$known[ $key ]['menu_order'] = $index + 1;
		}
		$this->put_context( $post_type, array_values( $known ) );
		return true;
	}

	/** Return a portable backup document. */
	public function export_store(): array {
		return $this->store();
	}

	/** @return true|WP_Error */
	public function import_store( array $document, bool $replace = false, bool $apply = true ) {
		if ( (int) ( $document['schema_version'] ?? 0 ) !== self::SCHEMA_VERSION || ! is_array( $document['contexts'] ?? null ) ) {
			return new WP_Error( 'invalid_definition_backup', 'Unsupported dynamic-field definition backup.' );
		}
		$next     = $replace ? $this->empty_store() : $this->store();
		$seen_ids = [];
		foreach ( self::SUPPORTED_POST_TYPES as $post_type ) {
			foreach ( $next['contexts'][ $post_type ] as $field ) {
				$seen_ids[ (string) $field['key'] ] = $post_type;
			}
		}
		foreach ( self::SUPPORTED_POST_TYPES as $post_type ) {
			$static = Registry::all()['contexts'][ $post_type ]['fields'] ?? [];
			$known  = array_column( $next['contexts'][ $post_type ], null, 'key' );
			foreach ( $document['contexts'][ $post_type ] ?? [] as $field ) {
				if (
					! is_array( $field )
					|| empty( $field['id'] )
					|| empty( $field['key'] )
					|| $field['id'] !== $field['key']
					|| empty( $field['storage_key'] )
					|| empty( $field['canonical_name'] )
					|| ! in_array( $field['type'] ?? '', self::SUPPORTED_TYPES, true )
				) {
					return new WP_Error( 'invalid_definition_backup', "Invalid {$post_type} field definition." );
				}
				$key       = (string) $field['key'];
				$canonical = (string) $field['canonical_name'];
				$storage   = (string) $field['storage_key'];
				if ( isset( $seen_ids[ $key ] ) && $seen_ids[ $key ] !== $post_type ) {
					return new WP_Error( 'field_name_collision', 'A field identity is already used in another context.' );
				}
				foreach ( $static as $definition ) {
					if ( $definition['canonical_name'] === $canonical || $definition['storage_name'] === $storage ) {
						return new WP_Error( 'field_name_collision', "Imported field {$post_type}.{$canonical} collides with a static field." );
					}
				}
				foreach ( $known as $known_key => $definition ) {
					if ( $known_key !== $key && ( $definition['canonical_name'] === $canonical || $definition['storage_key'] === $storage ) ) {
						return new WP_Error( 'field_name_collision', "Imported field {$post_type}.{$canonical} collides with another dynamic field." );
					}
				}
				$field['name']    = $storage;
				$field['active']  = ! empty( $field['active'] );
				$known[ $key ]    = $field;
				$seen_ids[ $key ] = $post_type;
			}
			$next['contexts'][ $post_type ] = array_values( $known );
		}
		if ( $apply ) {
			update_option( self::OPTION_NAME, $next, false );
			Registry::reset();
		}
		return true;
	}

	/** @return array<string,mixed>|WP_Error */
	private function set_active( string $field_key, bool $active ) {
		$location = $this->locate( $field_key );
		if ( ! $location ) {
			return new WP_Error( 'field_not_found', sprintf( 'Field with key "%s" not found.', $field_key ) );
		}
		[ $post_type, $index, $fields ] = $location;
		$fields[ $index ]['active']     = $active;
		$this->put_context( $post_type, $fields );
		return $fields[ $index ];
	}

	/** @return array{0:string,1:int,2:array}|null */
	private function locate( string $field_key ): ?array {
		foreach ( self::SUPPORTED_POST_TYPES as $post_type ) {
			$fields = $this->get_fields( $post_type, true );
			foreach ( $fields as $index => $field ) {
				if ( ( $field['key'] ?? '' ) === $field_key || ( $field['id'] ?? '' ) === $field_key ) {
					return [ $post_type, $index, $fields ];
				}
			}
		}
		return null;
	}

	private function collision( string $post_type, string $canonical, string $storage_name ): ?WP_Error {
		$static = Registry::all()['contexts'][ $post_type ]['fields'] ?? [];
		foreach ( $static as $definition ) {
			if ( $definition['canonical_name'] === $canonical || $definition['storage_name'] === $storage_name ) {
				return new WP_Error( 'field_name_collision', 'The canonical or storage field name is already owned by a static field.' );
			}
		}
		foreach ( $this->get_fields( $post_type, true ) as $definition ) {
			if ( $definition['canonical_name'] === $canonical || $definition['storage_key'] === $storage_name ) {
				return new WP_Error( 'field_name_collision', 'The canonical or storage field name is already owned by another custom field.' );
			}
		}
		return null;
	}

	private function put_context( string $post_type, array $fields ): void {
		$store                           = $this->store();
		$store['contexts'][ $post_type ] = array_values( $fields );
		update_option( self::OPTION_NAME, $store, false );
		Registry::reset();
	}

	/** @return array<string,mixed> */
	private function store(): array {
		$value = get_option( self::OPTION_NAME, null );
		if ( $value === null ) {
			$value = $this->import_legacy_database_definitions();
			update_option( self::OPTION_NAME, $value, false );
		}
		if ( ! is_array( $value ) || (int) ( $value['schema_version'] ?? 0 ) !== self::SCHEMA_VERSION ) {
			return $this->empty_store();
		}
		return $value;
	}

	/** Import legacy acf-field posts without loading or calling the plugin. */
	private function import_legacy_database_definitions(): array {
		$store = $this->empty_store();
		foreach ( self::SUPPORTED_POST_TYPES as $post_type ) {
			$group = get_page_by_path( 'group_custom_fields_' . $post_type, OBJECT, 'acf-field-group' );
			if ( ! $group ) {
				continue;
			}
			$posts = get_posts(
				[
					'post_type'      => 'acf-field',
					'post_parent'    => $group->ID,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'orderby'        => 'menu_order ID',
					'order'          => 'ASC',
				]
			);
			foreach ( $posts as $post ) {
				$config    = maybe_unserialize( $post->post_content );
				$config    = is_array( $config ) ? $config : [];
				$storage   = (string) ( $config['rondo_storage_key'] ?? $post->post_excerpt );
				$canonical = (string) ( $config['rondo_canonical_name'] ?? sanitize_key( str_replace( '-', '_', $storage ) ) );
				$field     = array_merge(
					$config,
					[
						'id'             => $post->post_name,
						'key'            => $post->post_name,
						'label'          => $post->post_title,
						'name'           => $storage,
						'storage_key'    => $storage,
						'canonical_name' => $canonical,
						'type'           => ( $config['type'] ?? 'text' ) === 'date_picker' ? 'date' : ( $config['type'] ?? 'text' ),
						'active'         => ! isset( $config['active'] ) || (bool) $config['active'],
						'menu_order'     => (int) $post->menu_order,
					]
				);
				unset( $field['rondo_storage_key'], $field['rondo_canonical_name'], $field['parent'], $field['ID'] );
				$store['contexts'][ $post_type ][] = $field;
			}
		}
		return $store;
	}

	private function empty_store(): array {
		return [
			'schema_version' => self::SCHEMA_VERSION,
			'contexts'       => array_fill_keys( self::SUPPORTED_POST_TYPES, [] ),
		];
	}

	/** @param mixed $value @return mixed */
	private function sanitize_property( string $property, $value ) {
		if ( in_array( $property, [ 'required', 'allow_null', 'multiple', 'ui', 'toggle', 'allow_custom', 'save_custom', 'unique', 'editable_in_ui' ], true ) ) {
			return (bool) $value;
		}
		if ( in_array( $property, [ 'menu_order', 'maxlength', 'first_day' ], true ) ) {
			return (int) $value;
		}
		if ( in_array( $property, [ 'min', 'max', 'step', 'min_width', 'max_width', 'min_height', 'max_height', 'min_size', 'max_size' ], true ) ) {
			return is_numeric( $value ) ? (float) $value : '';
		}
		if ( in_array( $property, [ 'choices', 'post_type', 'filters' ], true ) ) {
			return is_array( $value ) ? $value : [];
		}
		return is_string( $value ) ? sanitize_text_field( $value ) : $value;
	}

	private function is_valid_post_type( string $post_type ): bool {
		return in_array( $post_type, self::SUPPORTED_POST_TYPES, true );
	}
}
