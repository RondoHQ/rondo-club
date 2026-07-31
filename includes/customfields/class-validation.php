<?php
/**
 * Custom Fields Validation
 *
 * Provides validation hooks for custom field values including unique constraint.
 *
 * @package Rondo\CustomFields
 */

namespace Rondo\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation class for custom fields.
 *
 * Handles unique validation via native field's validate_value hook.
 */
class Validation {

	/**
	 * Constructor.
	 *
	 * Registers native field validation hooks.
	 */
	public function __construct() {
		add_filter( 'rondo_fields_validate_value', [ $this, 'validate_native_unique' ], 10, 4 );
	}

	public function validate_native_unique( $value, int $post_id, array $field, $old_value ) {
		if ( empty( $field['dynamic'] ) || empty( $field['unique'] ) || $value === '' || $value === null ) {
			return $value;
		}
		$result = $this->validate_unique( true, $value, $field, '', $post_id );
		return $result === true ? $value : new \WP_Error( 'rondo_duplicate_field', (string) $result, [ 'status' => 400 ] );
	}

	/**
	 * Validate unique constraint for custom fields.
	 *
	 * Checks if a field marked as unique has a duplicate value in another post
	 * of the same type owned by the same user.
	 *
	 * @param bool|string $valid      True if valid, error message string if invalid.
	 * @param mixed       $value      The field value being validated.
	 * @param array       $field      The field configuration.
	 * @param string      $input_name The input name (for error targeting).
	 * @return bool|string True if valid, error message if invalid.
	 */
	public function validate_unique( $valid, $value, $field, $input_name, ?int $native_post_id = null ) {
		// Bail early if already invalid.
		if ( $valid !== true ) {
			return $valid;
		}

		// Only check our custom fields (key prefix check).
		if ( strpos( $field['key'], 'field_custom_' ) !== 0 ) {
			return $valid;
		}

		// Only check if field is marked unique and has a value.
		if ( empty( $field['unique'] ) || $value === '' || $value === null ) {
			return $valid;
		}

		// Get current post ID from the $_POST data.
		$post_id = $native_post_id ?? 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- native field handles nonce verification.
		if ( $post_id === 0 && isset( $_POST['post_ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_id = (int) $_POST['post_ID'];
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( $post_id === 0 && isset( $_POST['post_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_id = (int) $_POST['post_id'];
		}

		// Determine post type from field key.
		// Field keys are like: field_custom_person_xxx or field_custom_company_xxx.
		$post_type = $field['context'] ?? null;

		if ( ! $post_type ) {
			return $valid;
		}

		// Query for existing posts with same value (same user, same post type).
		$query_args = [
			'post_type'      => $post_type,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'author'         => get_current_user_id(),
			'meta_query'     => [
				[
					'key'     => $field['name'],
					'value'   => $value,
					'compare' => '=',
				],
			],
		];

		// Exclude current post if editing.
		if ( $post_id ) {
			$query_args['post__not_in'] = [ $post_id ];
		}

		$existing = get_posts( $query_args );

		if ( ! empty( $existing ) ) {
			return sprintf(
				/* translators: %s is the field label. */
				__( '%s must be unique. This value is already in use.', 'rondo' ),
				$field['label']
			);
		}

		return $valid;
	}
}
