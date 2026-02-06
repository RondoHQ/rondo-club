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

		// Trigger calendar re-matching after person save (priority 25 = after title generation)
		// Hook both acf/save_post (admin) and rest_after_insert_person (REST API)
		add_action( 'acf/save_post', [ $this, 'trigger_calendar_rematch' ], 25 );
		add_action( 'rest_after_insert_person', [ $this, 'trigger_calendar_rematch_rest' ], 25, 2 );

		// Handle async calendar rematch cron job
		add_action( 'rondo_async_calendar_rematch', [ $this, 'handle_async_calendar_rematch' ] );

		// Hide title field in admin for person CPT
		add_filter( 'acf/prepare_field/name=_post_title', [ $this, 'hide_title_field' ] );

		// Lowercase email addresses on save
		add_filter( 'acf/update_value/key=field_contact_value', [ $this, 'maybe_lowercase_email' ], 10, 4 );
		add_filter( 'acf/update_value/key=field_company_contact_value', [ $this, 'maybe_lowercase_email' ], 10, 4 );

		// Inject title into REST API requests for person creation (runs very early)
		add_filter( 'rest_pre_dispatch', [ $this, 'inject_title_for_person_creation' ], 10, 3 );

		// Debug logging for REST API person requests
		add_filter( 'rest_pre_dispatch', [ $this, 'debug_log_person_request' ], 1, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'debug_log_person_response' ], 999, 3 );

		// Make title not required in REST API schema (backup)
		add_filter( 'rest_person_item_schema', [ $this, 'make_title_optional_in_schema' ] );

		// Set temporary title for REST API person creation
		add_filter( 'rest_pre_insert_person', [ $this, 'set_temporary_title_rest' ], 5, 2 );
	}

	/**
	 * Debug log incoming REST API requests for person endpoint
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed Unchanged result.
	 */
	public function debug_log_person_request( $result, $server, $request ) {
		$route = $request->get_route();
		if ( strpos( $route, '/wp/v2/people' ) !== 0 ) {
			return $result;
		}

		$log_data = [
			'time'        => gmdate( 'Y-m-d H:i:s' ),
			'route'       => $route,
			'method'      => $request->get_method(),
			'params'      => $request->get_params(),
			'headers'     => $request->get_headers(),
			'user_id'     => get_current_user_id(),
			'auth_header' => isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? 'present' : 'missing',
		];

		error_log( 'RONDO REST DEBUG [REQUEST]: ' . wp_json_encode( $log_data ) );

		return $result;
	}

	/**
	 * Debug log REST API responses for person endpoint
	 *
	 * @param WP_REST_Response|WP_Error $response Result to send to the client.
	 * @param array                     $handler  Route handler used for the request.
	 * @param WP_REST_Request           $request  Request used to generate the response.
	 * @return WP_REST_Response|WP_Error Unchanged response.
	 */
	public function debug_log_person_response( $response, $handler, $request ) {
		$route = $request->get_route();
		if ( strpos( $route, '/wp/v2/people' ) !== 0 ) {
			return $response;
		}

		if ( is_wp_error( $response ) ) {
			$log_data = [
				'time'    => gmdate( 'Y-m-d H:i:s' ),
				'route'   => $route,
				'method'  => $request->get_method(),
				'error'   => true,
				'code'    => $response->get_error_code(),
				'message' => $response->get_error_message(),
				'data'    => $response->get_error_data(),
			];
		} else {
			$log_data = [
				'time'   => gmdate( 'Y-m-d H:i:s' ),
				'route'  => $route,
				'method' => $request->get_method(),
				'status' => $response->get_status(),
			];
		}

		error_log( 'RONDO REST DEBUG [RESPONSE]: ' . wp_json_encode( $log_data ) );

		return $response;
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
	 * Make title field optional in REST API schema for person post type
	 *
	 * By default, WordPress marks 'title' as required if the post type supports it.
	 * Since we auto-generate titles from ACF fields, we need to allow creation without title.
	 *
	 * @param array $schema The REST API schema for person post type.
	 * @return array Modified schema with title not required.
	 */
	public function make_title_optional_in_schema( $schema ) {
		if ( isset( $schema['properties']['title']['required'] ) ) {
			$schema['properties']['title']['required'] = false;
		}

		// Also remove 'title' from the top-level 'required' array if present
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			$schema['required'] = array_values( array_diff( $schema['required'], [ 'title' ] ) );
		}

		return $schema;
	}

	/**
	 * Set temporary title for person during REST API creation
	 *
	 * WordPress requires a title when creating posts via REST API.
	 * This filter runs before validation to set a temporary title,
	 * which will be replaced by auto_generate_person_title() after ACF fields are saved.
	 *
	 * @param stdClass        $prepared_post The prepared post data.
	 * @param WP_REST_Request $request       The request object.
	 * @return stdClass The prepared post with temporary title.
	 */
	public function set_temporary_title_rest( $prepared_post, $request ) {
		// Only set temporary title if no title was provided
		if ( empty( $prepared_post->post_title ) ) {
			$prepared_post->post_title = __( 'New Person', 'rondo' );
		}

		return $prepared_post;
	}

	/**
	 * Auto-generate Person post title from first_name + last_name
	 */
	public function auto_generate_person_title( $post_id ) {
		// Skip if not a person post type
		if ( get_post_type( $post_id ) !== 'person' ) {
			return;
		}

		// Skip autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$first_name = get_field( 'first_name', $post_id ) ?: '';
		$infix      = get_field( 'infix', $post_id ) ?: '';
		$last_name  = get_field( 'last_name', $post_id ) ?: '';

		$full_name = implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) );

		if ( empty( $full_name ) ) {
			$full_name = __( 'Unnamed Person', 'rondo' );
		}

		// Unhook to prevent infinite loop
		remove_action( 'acf/save_post', [ $this, 'auto_generate_person_title' ], 20 );

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => $full_name,
				'post_name'  => sanitize_title( $full_name . '-' . $post_id ),
			]
		);

		// Re-hook
		add_action( 'acf/save_post', [ $this, 'auto_generate_person_title' ], 20 );
	}

	/**
	 * Auto-generate Person post title from REST API request
	 *
	 * Called via rest_after_insert_person hook when a person is created/updated via REST API.
	 * ACF fields are already saved at this point, so we can read them and generate the title.
	 *
	 * @param WP_Post         $post    Inserted or updated post object.
	 * @param WP_REST_Request $request Request object.
	 */
	public function auto_generate_person_title_rest( $post, $request ) {
		$post_id = $post->ID;

		$first_name = get_field( 'first_name', $post_id ) ?: '';
		$infix      = get_field( 'infix', $post_id ) ?: '';
		$last_name  = get_field( 'last_name', $post_id ) ?: '';

		$full_name = implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) );

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
	 * Lowercase email addresses when saving contact_info repeater
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

	/**
	 * Trigger calendar event re-matching when a person is saved
	 *
	 * This ensures that when email addresses are added/changed on a person,
	 * existing calendar events with those emails are now matched to the person.
	 *
	 * @param int $post_id Post ID being saved
	 */
	public function trigger_calendar_rematch( $post_id ) {
		// Skip if not a person post type
		if ( get_post_type( $post_id ) !== 'person' ) {
			return;
		}

		// Skip autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Schedule async rematch - don't block the save
		$this->schedule_calendar_rematch( $post_id );
	}

	/**
	 * Trigger calendar re-matching from REST API insert
	 *
	 * Called via rest_after_insert_person hook when a person is created/updated via REST API.
	 *
	 * @param WP_Post         $post    Inserted or updated post object.
	 * @param WP_REST_Request $request Request object.
	 */
	public function trigger_calendar_rematch_rest( $post, $request ) {
		// Schedule async rematch - don't block the API response
		$this->schedule_calendar_rematch( $post->ID );
	}

	/**
	 * Schedule calendar rematch to run asynchronously
	 *
	 * Uses a static flag to prevent duplicate scheduling within the same request
	 * (since both acf/save_post and rest_after_insert_person can fire).
	 *
	 * @param int $post_id Person post ID.
	 */
	private function schedule_calendar_rematch( int $post_id ): void {
		static $scheduled = [];

		// Prevent scheduling multiple times for the same person in one request
		if ( isset( $scheduled[ $post_id ] ) ) {
			return;
		}
		$scheduled[ $post_id ] = true;

		// Clear any existing scheduled event for this person
		$timestamp = wp_next_scheduled( 'rondo_async_calendar_rematch', [ $post_id ] );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'rondo_async_calendar_rematch', [ $post_id ] );
		}

		// Schedule to run immediately (next cron tick)
		wp_schedule_single_event( time(), 'rondo_async_calendar_rematch', [ $post_id ] );

		// Trigger cron to run soon (non-blocking)
		spawn_cron();
	}

	/**
	 * Handle async calendar rematch cron job
	 *
	 * @param int $post_id Person post ID.
	 */
	public function handle_async_calendar_rematch( int $post_id ): void {
		\Rondo\Calendar\Matcher::on_person_saved( $post_id );
	}

}
