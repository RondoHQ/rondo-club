<?php
/**
 * Custom Fields Manager
 *
 * Provides programmatic CRUD operations for ACF custom field definitions.
 * Uses ACF's database persistence API to store field groups and fields.
 *
 * @package Stadion\CustomFields
 */

namespace Rondo\CustomFields;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manager class for custom field definitions.
 *
 * Creates, reads, updates, and deactivates custom fields for People and Organizations
 * using ACF-native storage patterns. Fields are stored in dedicated field groups
 * per post type.
 */
class Manager {

	/**
	 * Supported post types for custom fields.
	 */
	const SUPPORTED_POST_TYPES = array( 'person', 'team', 'commissie' );

	/**
	 * Map Stadion field types to ACF field types.
	 *
	 * Stadion uses simplified type names for the frontend UI,
	 * while ACF uses different internal type names.
	 *
	 * @var array
	 */
	private const TYPE_MAP = array(
		'date' => 'date_picker',
	);

	/**
	 * Properties that can be updated on existing fields.
	 * Key, type, and parent are immutable once created.
	 *
	 * @var array
	 */
	private const UPDATABLE_PROPERTIES = array(
		// Core properties.
		'label',
		'name',
		'instructions',
		'required',
		'choices',
		'default_value',
		'placeholder',
		// Number field options.
		'min',
		'max',
		'step',
		'prepend',
		'append',
		// Date field options.
		'display_format',
		'return_format',
		'first_day',
		// Select field options.
		'allow_null',
		'multiple',
		'ui',
		// Checkbox field options.
		'layout',
		'toggle',
		'allow_custom',
		'save_custom',
		// Text/Textarea options.
		'maxlength',
		// True/False options.
		'ui_on_text',
		'ui_off_text',
		// Image field options.
		'preview_size',
		'library',
		'min_width',
		'max_width',
		'min_height',
		'max_height',
		'min_size',
		'max_size',
		'mime_types',
		// Color picker field options.
		'enable_opacity',
		// Relationship field options.
		'post_type',
		'filters',
		// Field ordering.
		'menu_order',
		// Unique validation.
		'unique',
		// UI visibility.
		'editable_in_ui',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// No hooks needed - stateless class.
	}

	/**
	 * Ensure a field group exists for the given post type.
	 *
	 * Creates the field group if it doesn't exist, returns the existing one if it does.
	 *
	 * @param string $post_type The post type to create/get field group for.
	 * @return array|WP_Error Field group array on success, WP_Error on failure.
	 */
	public function ensure_field_group( string $post_type ) {
		// Validate post type.
		if ( ! $this->is_valid_post_type( $post_type ) ) {
			return new WP_Error(
				'invalid_post_type',
				sprintf( 'Post type "%s" is not supported. Supported types: %s', $post_type, implode( ', ', self::SUPPORTED_POST_TYPES ) )
			);
		}

		$group_key = $this->get_group_key( $post_type );

		// Check if group already exists in database.
		// We must get the post ID directly because acf_get_field_group() returns ID=0
		// when the group is also loaded from JSON (which takes precedence).
		$group_post = get_page_by_path( $group_key, OBJECT, 'acf-field-group' );
		if ( $group_post ) {
			$existing = acf_get_field_group( $group_post->ID );
			if ( $existing ) {
				// Ensure we have the database ID, not 0.
				$existing['ID'] = $group_post->ID;
				return $existing;
			}
		}

		// Create new field group.
		$field_group = array(
			'key'                   => $group_key,
			'title'                 => 'Custom Fields',
			'fields'                => array(),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => $post_type,
					),
				),
			),
			'menu_order'            => 100, // After built-in groups.
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'show_in_rest'          => 1,
		);

		$result = acf_import_field_group( $field_group );

		// Validate the result has an ID (required for field parent).
		if ( ! $result || ! is_array( $result ) || ! isset( $result['ID'] ) ) {
			return new WP_Error(
				'field_group_create_failed',
				'Failed to create field group in database.'
			);
		}

		return $result;
	}

	/**
	 * Generate a unique field key from a label.
	 *
	 * @param string $label     The field label.
	 * @param string $post_type The target post type.
	 * @return string The generated field key.
	 */
	public function generate_field_key( string $label, string $post_type ): string {
		// Sanitize label to slug.
		$slug = sanitize_title( $label );

		// Create base key namespaced by post type.
		$base_key = 'field_custom_' . $post_type . '_' . $slug;

		// If key already exists, append unique suffix.
		if ( acf_get_field( $base_key ) ) {
			$base_key .= '_' . substr( uniqid(), -6 );
		}

		return $base_key;
	}

	/**
	 * Create a new custom field.
	 *
	 * @param string $post_type    The target post type.
	 * @param array  $field_config Field configuration array.
	 *                             Required keys: label, type.
	 *                             Optional keys: name, instructions, required, choices, default_value, placeholder.
	 * @return array|WP_Error Created field array on success, WP_Error on failure.
	 */
	public function create_field( string $post_type, array $field_config ) {
		// Validate post type.
		if ( ! $this->is_valid_post_type( $post_type ) ) {
			return new WP_Error(
				'invalid_post_type',
				sprintf( 'Post type "%s" is not supported.', $post_type )
			);
		}

		// Validate required config - label.
		if ( empty( $field_config['label'] ) ) {
			return new WP_Error(
				'missing_required',
				'Field label is required.'
			);
		}

		// Validate required config - type.
		if ( empty( $field_config['type'] ) ) {
			return new WP_Error(
				'missing_required',
				'Field type is required.'
			);
		}

		// Ensure field group exists.
		$group = $this->ensure_field_group( $post_type );
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		// Generate field key.
		$field_key = $this->generate_field_key( $field_config['label'], $post_type );

		// Generate field name from label if not provided.
		$field_name = ! empty( $field_config['name'] )
			? sanitize_title( $field_config['name'] )
			: sanitize_title( $field_config['label'] );

		// Map Stadion type to ACF type.
		$acf_type = $this->map_type_to_acf( $field_config['type'] );

		// Build field array.
		$field = array(
			'key'          => $field_key,
			'label'        => $field_config['label'],
			'name'         => $field_name,
			'type'         => $acf_type,
			'parent'       => $group['ID'], // Must be post ID, not key.
			'instructions' => $field_config['instructions'] ?? '',
			'required'     => $field_config['required'] ?? 0,
		);

		// Add optional properties from UPDATABLE_PROPERTIES.
		// These include type-specific settings (min, max, choices, etc.)
		// that ACF handles based on the field type.
		foreach ( self::UPDATABLE_PROPERTIES as $prop ) {
			// Skip properties already set in core array.
			if ( in_array( $prop, array( 'label', 'name', 'instructions', 'required' ), true ) ) {
				continue;
			}
			if ( isset( $field_config[ $prop ] ) ) {
				$field[ $prop ] = $field_config[ $prop ];
			}
		}

		// Enforce Y-m-d format for date fields (ensures consistent sorting and JS parsing).
		if ( 'date' === $acf_type ) {
			$field['return_format']  = 'Y-m-d';
			$field['display_format'] = 'Y-m-d';
		}

		// Persist to database.
		$result = acf_update_field( $field );

		if ( ! $result ) {
			return new WP_Error(
				'create_failed',
				'Failed to create field in database.'
			);
		}

		return $result;
	}

	/**
	 * Update an existing field.
	 *
	 * Only properties in UPDATABLE_PROPERTIES can be updated.
	 * The key, type, and parent are immutable once created.
	 *
	 * @param string $field_key The field key to update.
	 * @param array  $updates   Array of properties to update.
	 * @return array|WP_Error Updated field array on success, WP_Error on failure.
	 */
	public function update_field( string $field_key, array $updates ) {
		// Get existing field.
		$field = acf_get_field( $field_key );

		if ( ! $field ) {
			return new WP_Error(
				'field_not_found',
				sprintf( 'Field with key "%s" not found.', $field_key )
			);
		}

		// Apply allowed updates only.
		foreach ( self::UPDATABLE_PROPERTIES as $prop ) {
			if ( isset( $updates[ $prop ] ) ) {
				$field[ $prop ] = $updates[ $prop ];
			}
		}

		// Enforce Y-m-d format for date fields (ensures consistent sorting and JS parsing).
		if ( 'date' === $field['type'] ) {
			$field['return_format']  = 'Y-m-d';
			$field['display_format'] = 'Y-m-d';
		}

		// Persist to database.
		$result = acf_update_field( $field );

		if ( ! $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update field in database.'
			);
		}

		return $result;
	}

	/**
	 * Deactivate a field (soft delete).
	 *
	 * Sets the field's active flag to 0. ACF will not render inactive fields,
	 * but stored values in wp_postmeta are preserved.
	 *
	 * @param string $field_key The field key to deactivate.
	 * @return array|WP_Error Updated field array on success, WP_Error on failure.
	 */
	public function deactivate_field( string $field_key ) {
		// Get existing field.
		$field = acf_get_field( $field_key );

		if ( ! $field ) {
			return new WP_Error(
				'field_not_found',
				sprintf( 'Field with key "%s" not found.', $field_key )
			);
		}

		// Mark as inactive.
		$field['active'] = 0;

		// Persist to database.
		$result = acf_update_field( $field );

		if ( ! $result ) {
			return new WP_Error(
				'deactivate_failed',
				'Failed to deactivate field in database.'
			);
		}

		return $result;
	}

	/**
	 * Reactivate a previously deactivated field.
	 *
	 * Sets the field's active flag to 1, making it visible again.
	 *
	 * @param string $field_key The field key to reactivate.
	 * @return array|WP_Error Updated field array on success, WP_Error on failure.
	 */
	public function reactivate_field( string $field_key ) {
		// Get existing field.
		$field = acf_get_field( $field_key );

		if ( ! $field ) {
			return new WP_Error(
				'field_not_found',
				sprintf( 'Field with key "%s" not found.', $field_key )
			);
		}

		// Mark as active.
		$field['active'] = 1;

		// Persist to database.
		$result = acf_update_field( $field );

		if ( ! $result ) {
			return new WP_Error(
				'reactivate_failed',
				'Failed to reactivate field in database.'
			);
		}

		return $result;
	}

	/**
	 * Get all custom fields for a post type.
	 *
	 * @param string $post_type        The post type to get fields for.
	 * @param bool   $include_inactive Whether to include inactive fields.
	 * @return array Array of field arrays, empty array if none.
	 */
	public function get_fields( string $post_type, bool $include_inactive = false ): array {
		// Validate post type.
		if ( ! $this->is_valid_post_type( $post_type ) ) {
			return array();
		}

		$group_key = $this->get_group_key( $post_type );

		// Get field group post ID directly from database.
		// We can't use acf_get_field_group() because it returns ID=0 when
		// the group is also loaded from JSON (which takes precedence).
		$group_post = get_page_by_path( $group_key, OBJECT, 'acf-field-group' );
		if ( ! $group_post ) {
			return array();
		}

		// Get all fields in the group by post ID.
		$fields = acf_get_fields( $group_post->ID );

		// Handle false return (no fields).
		if ( ! $fields ) {
			return array();
		}

		// Filter out inactive fields unless requested.
		if ( ! $include_inactive ) {
			$fields = array_filter(
				$fields,
				function ( $field ) {
					// Active is 1 or true, inactive is 0 or false.
					return ! isset( $field['active'] ) || $field['active'];
				}
			);
			// Re-index array.
			$fields = array_values( $fields );
		}

		// Map ACF types back to Stadion types for API responses.
		$fields = array_map(
			function ( $field ) {
				$field['type'] = $this->map_type_from_acf( $field['type'] );
				return $field;
			},
			$fields
		);

		return $fields;
	}

	/**
	 * Get a single field by key.
	 *
	 * @param string $field_key The field key.
	 * @return array|false Field array on success, false if not found.
	 */
	public function get_field( string $field_key ) {
		$field = acf_get_field( $field_key );
		if ( $field ) {
			// Map ACF type back to Stadion type for API response.
			$field['type'] = $this->map_type_from_acf( $field['type'] );
		}
		return $field;
	}

	/**
	 * Reorder fields by setting menu_order.
	 *
	 * @param string $post_type  The post type.
	 * @param array  $field_keys Array of field keys in desired order.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function reorder_fields( string $post_type, array $field_keys ) {
		if ( ! $this->is_valid_post_type( $post_type ) ) {
			return new WP_Error( 'invalid_post_type', 'Invalid post type.' );
		}

		foreach ( $field_keys as $menu_order => $field_key ) {
			$field = acf_get_field( $field_key );
			if ( $field ) {
				$field['menu_order'] = $menu_order + 1; // Start at 1, not 0.
				acf_update_field( $field );
			}
		}

		return true;
	}

	/**
	 * Check if a post type is supported for custom fields.
	 *
	 * @param string $post_type The post type to check.
	 * @return bool True if supported, false otherwise.
	 */
	private function is_valid_post_type( string $post_type ): bool {
		return in_array( $post_type, self::SUPPORTED_POST_TYPES, true );
	}

	/**
	 * Get the field group key for a post type.
	 *
	 * @param string $post_type The post type.
	 * @return string The field group key.
	 */
	private function get_group_key( string $post_type ): string {
		return 'group_custom_fields_' . $post_type;
	}

	/**
	 * Map a Stadion field type to the corresponding ACF field type.
	 *
	 * @param string $type The Stadion field type.
	 * @return string The ACF field type.
	 */
	private function map_type_to_acf( string $type ): string {
		return self::TYPE_MAP[ $type ] ?? $type;
	}

	/**
	 * Map an ACF field type back to Stadion field type for API responses.
	 *
	 * @param string $acf_type The ACF field type.
	 * @return string The Stadion field type.
	 */
	private function map_type_from_acf( string $acf_type ): string {
		$reverse_map = array_flip( self::TYPE_MAP );
		return $reverse_map[ $acf_type ] ?? $acf_type;
	}
}
