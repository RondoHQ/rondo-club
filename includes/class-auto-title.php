<?php
/**
 * Auto-generate post titles from ACF fields
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoTitle {

	/**
	 * Person IDs whose matching email fields changed during this request.
	 *
	 * @var array<int, bool>
	 */
	private array $calendar_rematch_person_ids = [];

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
		add_action( 'rondo_async_calendar_rematch_user', [ $this, 'handle_async_calendar_rematch_for_user' ] );

		// Hide title field in admin for person CPT
		add_filter( 'acf/prepare_field/name=_post_title', [ $this, 'hide_title_field' ] );

		// Lowercase email addresses on save (fixed fields + legacy repeater)
		add_filter( 'acf/update_value/name=email_1', [ $this, 'track_calendar_email_change' ], 5, 4 );
		add_filter( 'acf/update_value/name=email_2', [ $this, 'track_calendar_email_change' ], 5, 4 );
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

	/**
	 * Remember when a fixed person email changes during the current request.
	 *
	 * Calendar attendee matching only reads email_1 and email_2, so unrelated
	 * person updates do not need to schedule a full calendar rematch.
	 *
	 * @param mixed $value    New field value.
	 * @param mixed $post_id  Person post ID.
	 * @param array $field    ACF field definition.
	 * @param mixed $original Previously stored field value.
	 * @return mixed Unchanged field value.
	 */
	public function track_calendar_email_change( $value, $post_id, $field, $original ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || get_post_type( $post_id ) !== 'person' ) {
			return $value;
		}

		$normalized_value    = strtolower( trim( (string) $value ) );
		$normalized_original = strtolower( trim( (string) $original ) );

		if ( $normalized_value !== $normalized_original ) {
			$this->calendar_rematch_person_ids[ $post_id ] = true;
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
		$post_id = (int) $post_id;
		if ( ! $this->is_valid_person_save( $post_id ) || ! isset( $this->calendar_rematch_person_ids[ $post_id ] ) ) {
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
		if ( ! isset( $this->calendar_rematch_person_ids[ $post->ID ] ) ) {
			return;
		}

		// Schedule async rematch - don't block the API response
		$this->schedule_calendar_rematch( $post->ID );
	}

	/**
	 * Schedule calendar rematch to run asynchronously
	 *
	 * Jobs are keyed by owner and delayed briefly so bulk imports coalesce into
	 * one rematch instead of creating one cron job per changed person.
	 *
	 * @param int $post_id Person post ID.
	 */
	private function schedule_calendar_rematch( int $post_id ): void {
		$user_id = (int) get_post_field( 'post_author', $post_id );
		if ( $user_id <= 0 ) {
			return;
		}

		$args = [ $user_id ];
		if ( wp_next_scheduled( 'rondo_async_calendar_rematch_user', $args ) ) {
			return;
		}

		// Debounce bulk imports: all changed people for one owner share one job.
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'rondo_async_calendar_rematch_user', $args );
	}

	/**
	 * Handle async calendar rematch cron job
	 *
	 * @param int $post_id Person post ID.
	 */
	public function handle_async_calendar_rematch( int $post_id ): void {
		\Rondo\Calendar\Matcher::on_person_saved( $post_id );
	}

	/**
	 * Handle a coalesced calendar rematch for all changed people of one owner.
	 *
	 * @param int $user_id WordPress user ID that owns the people and events.
	 */
	public function handle_async_calendar_rematch_for_user( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		\Rondo\Calendar\Matcher::invalidate_cache( $user_id );
		\Rondo\Calendar\Matcher::rematch_events_for_user( $user_id );
	}
}
