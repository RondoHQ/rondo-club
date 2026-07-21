<?php
/**
 * Auto-generate post titles from ACF fields
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoTitle {

	public function __construct() {
		add_action( 'acf/save_post', [ $this, 'auto_generate_person_title' ], 20 );

		// Generate title for REST API person creation/update (priority 20 = same as acf/save_post)
		add_action( 'rest_after_insert_person', [ $this, 'auto_generate_person_title_rest' ], 20, 2 );

		// Hide title field in admin for person CPT
		add_filter( 'acf/prepare_field/name=_post_title', [ $this, 'hide_title_field' ] );

		// Lowercase email addresses on save (fixed fields + legacy repeater)
		add_filter( 'acf/update_value/name=email_1', [ $this, 'maybe_lowercase_email' ], 10, 4 );
		add_filter( 'acf/update_value/name=email_2', [ $this, 'maybe_lowercase_email' ], 10, 4 );
		add_filter( 'acf/update_value/key=field_contact_value', [ $this, 'maybe_lowercase_email' ], 10, 4 );
		add_filter( 'acf/update_value/key=field_company_contact_value', [ $this, 'maybe_lowercase_email' ], 10, 4 );

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

		// Unhook to prevent infinite loop
		remove_action( 'acf/save_post', [ $this, 'auto_generate_person_title' ], 20 );

		$this->update_person_title( $post_id );

		// Re-hook
		add_action( 'acf/save_post', [ $this, 'auto_generate_person_title' ], 20 );
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
	 * Build and save the person title from ACF name fields.
	 *
	 * @param int $post_id Person post ID.
	 */
	private function update_person_title( int $post_id ): void {
		$full_name = implode(
			' ',
			array_filter(
				[
					get_field( 'first_name', $post_id ),
					get_field( 'infix', $post_id ),
					get_field( 'last_name', $post_id ),
				]
			)
		);

		if ( empty( $full_name ) ) {
			$full_name = trim( (string) get_field( 'company_name', $post_id ) );
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
