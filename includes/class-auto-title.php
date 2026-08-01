<?php
/**
 * Auto-generate post titles from canonical fields
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoTitle {

	public function __construct() {
		add_action( 'rondo_fields_saved_post', [ $this, 'auto_generate_person_title' ], 20 );

		// Generate titles after a logical native field save.
		add_action( 'rest_after_insert_person', [ $this, 'auto_generate_person_title_rest' ], 20, 2 );

		add_filter( 'rondo_fields_validate_value', [ $this, 'normalize_native_value' ], 10, 4 );

		// Inject title into REST API requests for person creation (runs very early)
		add_filter( 'rest_pre_dispatch', [ $this, 'inject_title_for_person_creation' ], 10, 3 );
	}

	/**
	 * Inject required fields into REST API requests for person creation
	 *
	 * This runs very early in the REST API dispatch, before validation.
	 * It adds a temporary title to POST requests for people (replaced by auto_generate_person_title).
	 *
	 * @param mixed           $result  Response to replace the requested version with. Can be anything
	 *                                 a normal endpoint can return, or null to not hijack the request.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed Unchanged result.
	 */
	public function inject_title_for_person_creation( $result, $server, $request ) {
		$route  = $request->get_route();
		$method = $request->get_method();

		// Only handle person creation (POST to /wp/v2/people)
		if ( $method !== 'POST' || $route !== '/wp/v2/people' ) {
			return $result;
		}

		// Inject a temporary title if not set - will be replaced by auto_generate_person_title()
		$title = $request->get_param( 'title' );
		if ( empty( $title ) ) {
			$request->set_param( 'title', __( 'New Person', 'rondo' ) );
		}

		return $result;
	}

	/**
	 * Auto-generate Person post title from first_name + last_name
	 */
	public function auto_generate_person_title( $post_id ) {
		if ( ! $this->is_valid_person_save( $post_id ) ) {
			return;
		}

		$this->update_person_title( $post_id );
	}

	/** Normalize email values in scalar and contact-info fields. */
	public function normalize_native_value( $value, $post_id, array $definition, $old_value ) {
		if ( in_array( $definition['canonical_name'], [ 'email_1', 'email_2' ], true ) ) {
			return $this->maybe_lowercase_email( $value, $post_id, $definition, $old_value );
		}
		if ( $definition['canonical_name'] === 'contact_info' && is_array( $value ) ) {
			foreach ( $value as &$row ) {
				if ( is_array( $row ) && ( $row['contact_type'] ?? '' ) === 'email' && isset( $row['contact_value'] ) ) {
					$row['contact_value'] = strtolower( trim( (string) $row['contact_value'] ) );
				}
			}
			unset( $row );
		}
		return $value;
	}

	/**
	 * Auto-generate Person post title from REST API request
	 *
	 * @param WP_Post         $post    Inserted or updated post object.
	 * @param WP_REST_Request $request Request object.
	 */
	public function auto_generate_person_title_rest( $post, $request ) {
		$this->update_person_title( $post->ID );
	}

	/**
	 * Validate that this is a legitimate person post save operation
	 *
	 * @param int $post_id Post ID to validate.
	 * @return bool True if valid person save, false otherwise.
	 */
	private function is_valid_person_save( int $post_id ): bool {
		if ( get_post_type( $post_id ) !== 'person' ) {
			return false;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Build and save the person title from native field name fields.
	 *
	 * @param int $post_id Person post ID.
	 */
	private function update_person_title( int $post_id ): void {
		$full_name = implode(
			' ',
			array_filter(
				[
					\Rondo\Fields\Fields::get_for_post( $post_id, 'first_name' ),
					\Rondo\Fields\Fields::get_for_post( $post_id, 'infix' ),
					\Rondo\Fields\Fields::get_for_post( $post_id, 'last_name' ),
				]
			)
		);

		if ( empty( $full_name ) ) {
			$full_name = trim( (string) \Rondo\Fields\Fields::get_for_post( $post_id, 'company_name' ) );
		}

		if ( empty( $full_name ) ) {
			$full_name = __( 'Unnamed Person', 'rondo' );
		}

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => $full_name,
				'post_name'  => sanitize_title( $full_name . '-' . $post_id ),
			]
		);
	}

	/**
	 * Hide the title field for Person CPT (since it's auto-generated)
	 */
	public function hide_title_field( $field ) {
		global $post;

		if ( $post && $post->post_type === 'person' ) {
			return false;
		}

		return $field;
	}

	/**
	 * Lowercase email addresses when saving email fields
	 *
	 * @param mixed $value The value to save
	 * @param int $post_id The post ID
	 * @param array $field The field array
	 * @param mixed $original The original value
	 * @return mixed
	 */
	public function maybe_lowercase_email( $value, $post_id, $field, $original ) {
		// Only process string values
		if ( ! is_string( $value ) || empty( $value ) ) {
			return $value;
		}

		// Check if this looks like an email (using WordPress is_email function)
		if ( is_email( $value ) ) {
			return strtolower( $value );
		}

		return $value;
	}
}
