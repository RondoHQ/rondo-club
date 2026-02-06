<?php
/**
 * Custom Fields Validation
 *
 * Provides validation hooks for custom field values including unique constraint.
 *
 * @package Stadion\CustomFields
 */

namespace Rondo\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation class for custom fields.
 *
 * Handles unique validation via ACF's validate_value hook.
 */
class Validation {

	/**
	 * Constructor.
	 *
	 * Registers ACF validation hooks.
	 */
	public function __construct() {
		add_filter( 'acf/validate_value', array( $this, 'validate_unique' ), 10, 4 );
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
	public function validate_unique( $valid, $value, $field, $input_name ) {
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
		$post_id = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF handles nonce verification.
		if ( isset( $_POST['post_ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_id = (int) $_POST['post_ID'];
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_POST['post_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_id = (int) $_POST['post_id'];
		}

		// Determine post type from field key.
		// Field keys are like: field_custom_person_xxx or field_custom_company_xxx.
		$post_type = null;
		if ( strpos( $field['key'], 'field_custom_person_' ) === 0 ) {
			$post_type = 'person';
		} elseif ( strpos( $field['key'], 'field_custom_company_' ) === 0 ) {
			$post_type = 'team';
		}

		if ( ! $post_type ) {
			return $valid;
		}

		// Query for existing posts with same value (same user, same post type).
		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'author'         => get_current_user_id(),
			'meta_query'     => array(
				array(
					'key'     => $field['name'],
					'value'   => $value,
					'compare' => '=',
				),
			),
		);

		// Exclude current post if editing.
		if ( $post_id ) {
			$query_args['post__not_in'] = array( $post_id );
		}

		$existing = get_posts( $query_args );

		if ( ! empty( $existing ) ) {
			return sprintf(
				/* translators: %s is the field label. */
				__( '%s must be unique. This value is already in use.', 'stadion' ),
				$field['label']
			);
		}

		return $valid;
	}
}
