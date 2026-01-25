<?php
/**
 * Auto-generate post titles from ACF fields
 */

namespace Stadion\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoTitle {

	public function __construct() {
		add_action( 'acf/save_post', [ $this, 'auto_generate_person_title' ], 20 );
		add_action( 'acf/save_post', [ $this, 'auto_generate_date_title' ], 20 );

		// Trigger calendar re-matching after person save (priority 25 = after title generation)
		// Hook both acf/save_post (admin) and rest_after_insert_person (REST API)
		add_action( 'acf/save_post', [ $this, 'trigger_calendar_rematch' ], 25 );
		add_action( 'rest_after_insert_person', [ $this, 'trigger_calendar_rematch_rest' ], 25, 2 );

		// Handle async calendar rematch cron job
		add_action( 'stadion_async_calendar_rematch', [ $this, 'handle_async_calendar_rematch' ] );

		// Hide title field in admin for person CPT
		add_filter( 'acf/prepare_field/name=_post_title', [ $this, 'hide_title_field' ] );

		// Lowercase email addresses on save
		add_filter( 'acf/update_value/key=field_contact_value', [ $this, 'maybe_lowercase_email' ], 10, 4 );
		add_filter( 'acf/update_value/key=field_company_contact_value', [ $this, 'maybe_lowercase_email' ], 10, 4 );

		// Validate duplicate email addresses before save (REST API)
		add_filter( 'rest_pre_insert_person', [ $this, 'validate_unique_emails_rest' ], 10, 2 );
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
		$last_name  = get_field( 'last_name', $post_id ) ?: '';

		$full_name = trim( $first_name . ' ' . $last_name );

		if ( empty( $full_name ) ) {
			$full_name = __( 'Unnamed Person', 'stadion' );
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
	 * Auto-generate Important Date post title from type + related people
	 */
	public function auto_generate_date_title( $post_id ) {
		// Skip if not an important_date post type
		if ( get_post_type( $post_id ) !== 'important_date' ) {
			return;
		}

		// Skip autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check if user has set a custom title (different from what we'd auto-generate)
		// This preserves user edits even when custom_label field is not explicitly set
		$current_title = get_the_title( $post_id );
		$auto_title    = $this->generate_date_title_from_fields( $post_id );
		$custom_label  = get_field( 'custom_label', $post_id );

		// If current title differs from auto-generated, user has customized it
		// Set custom_label so it persists and skip auto-generation
		if ( ! empty( $current_title ) && $current_title !== $auto_title && empty( $custom_label ) ) {
			update_field( 'custom_label', $current_title, $post_id );
			return; // Respect user's custom title
		}

		// Check for custom label first (existing logic)

		if ( ! empty( $custom_label ) ) {
			$title = $custom_label;
		} else {
			$title = $this->generate_date_title_from_fields( $post_id );
		}

		// Unhook to prevent infinite loop
		remove_action( 'acf/save_post', [ $this, 'auto_generate_date_title' ], 20 );

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => $title,
				'post_name'  => sanitize_title( $title . '-' . $post_id ),
			]
		);

		// Re-hook
		add_action( 'acf/save_post', [ $this, 'auto_generate_date_title' ], 20 );
	}

	/**
	 * Generate date title from type and related people
	 */
	private function generate_date_title_from_fields( $post_id ) {
		// Get date type from taxonomy
		$date_types = wp_get_post_terms( $post_id, 'date_type', [ 'fields' => 'names' ] );
		$type_label = ! empty( $date_types ) ? $date_types[0] : __( 'Date', 'stadion' );

		// Get related people
		$people = get_field( 'related_people', $post_id ) ?: [];

		if ( empty( $people ) ) {
			return sprintf( __( 'Unnamed %s', 'stadion' ), $type_label );
		}

		// Get full names of related people
		$names = [];
		foreach ( $people as $person ) {
			$person_id = is_object( $person ) ? $person->ID : $person;
			$full_name = html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' );
			if ( $full_name && $full_name !== __( 'Unnamed Person', 'stadion' ) ) {
				$names[] = $full_name;
			}
		}

		if ( empty( $names ) ) {
			return sprintf( __( 'Unnamed %s', 'stadion' ), $type_label );
		}

		$count = count( $names );

		// Get date type slug to check for wedding
		$date_type_slugs = wp_get_post_terms( $post_id, 'date_type', [ 'fields' => 'slugs' ] );
		$type_slug       = ! empty( $date_type_slugs ) ? $date_type_slugs[0] : '';

		// Special handling for wedding type
		if ( $type_slug === 'wedding' ) {
			if ( $count >= 2 ) {
				// "Wedding of Person1 & Person2"
				return sprintf(
					__( 'Wedding of %1$s & %2$s', 'stadion' ),
					$names[0],
					$names[1]
				);
			} elseif ( $count === 1 ) {
				// "Wedding of Person1" (fallback if only one person)
				return sprintf( __( 'Wedding of %s', 'stadion' ), $names[0] );
			}
		}

		if ( $count === 1 ) {
			// "Sarah's Birthday"
			return sprintf( __( "%1\$s's %2\$s", 'stadion' ), $names[0], $type_label );
		} elseif ( $count === 2 ) {
			// "Tom & Lisa's Birthday"
			return sprintf(
				__( "%1\$s & %2\$s's %3\$s", 'stadion' ),
				$names[0],
				$names[1],
				$type_label
			);
		} else {
			// "Mike, Sarah +2 Birthday"
			$first_two = implode( ', ', array_slice( $names, 0, 2 ) );
			$remaining = $count - 2;
			return sprintf(
				__( '%1$s +%2$d %3$s', 'stadion' ),
				$first_two,
				$remaining,
				$type_label
			);
		}
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
		$timestamp = wp_next_scheduled( 'stadion_async_calendar_rematch', [ $post_id ] );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'stadion_async_calendar_rematch', [ $post_id ] );
		}

		// Schedule to run immediately (next cron tick)
		wp_schedule_single_event( time(), 'stadion_async_calendar_rematch', [ $post_id ] );

		// Trigger cron to run soon (non-blocking)
		spawn_cron();
	}

	/**
	 * Handle async calendar rematch cron job
	 *
	 * @param int $post_id Person post ID.
	 */
	public function handle_async_calendar_rematch( int $post_id ): void {
		\Stadion\Calendar\Matcher::on_person_saved( $post_id );
	}

	/**
	 * Validate that email addresses are unique across people (REST API)
	 *
	 * Prevents saving a person if any of their email addresses already exist
	 * on another person record belonging to the same user.
	 *
	 * @param stdClass        $prepared_post The prepared post data.
	 * @param WP_REST_Request $request       The request object.
	 * @return stdClass|WP_Error The prepared post or error if duplicate email found.
	 */
	public function validate_unique_emails_rest( $prepared_post, $request ) {
		// Get the acf field from the request
		$acf_fields = $request->get_param( 'acf' );

		if ( empty( $acf_fields ) || ! isset( $acf_fields['contact_info'] ) ) {
			return $prepared_post;
		}

		$contact_info = $acf_fields['contact_info'];
		if ( ! is_array( $contact_info ) ) {
			return $prepared_post;
		}

		// Extract email addresses from contact_info
		$emails = [];
		foreach ( $contact_info as $contact ) {
			if (
				isset( $contact['contact_type'] ) &&
				$contact['contact_type'] === 'email' &&
				! empty( $contact['contact_value'] )
			) {
				$emails[] = strtolower( trim( $contact['contact_value'] ) );
			}
		}

		if ( empty( $emails ) ) {
			return $prepared_post;
		}

		// Get current post ID (for updates) or 0 (for creates)
		// For PUT/PATCH requests, the ID is in the URL params, not the request body
		$url_params      = $request->get_url_params();
		$current_post_id = isset( $url_params['id'] ) ? (int) $url_params['id'] : 0;
		$user_id         = get_current_user_id();

		// Check for duplicates
		$duplicate = $this->find_duplicate_email( $emails, $current_post_id, $user_id );

		if ( $duplicate ) {
			return new \WP_Error(
				'duplicate_email',
				sprintf(
					/* translators: 1: email address, 2: person name */
					__( 'The email address "%1$s" is already used by "%2$s". Each email address can only belong to one person.', 'stadion' ),
					$duplicate['email'],
					$duplicate['person_name']
				),
				[ 'status' => 400 ]
			);
		}

		return $prepared_post;
	}

	/**
	 * Find if any email in the list already belongs to another person
	 *
	 * Uses direct SQL query for performance - avoids loading all people and their ACF fields.
	 *
	 * @param array $emails          Array of email addresses to check.
	 * @param int   $exclude_post_id Post ID to exclude (current person being edited).
	 * @param int   $user_id         User ID to scope the check to.
	 * @return array|null Array with 'email' and 'person_name' if duplicate found, null otherwise.
	 */
	private function find_duplicate_email( array $emails, int $exclude_post_id, int $user_id ): ?array {
		if ( empty( $emails ) ) {
			return null;
		}

		global $wpdb;

		// Build placeholders for the IN clause
		$placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );

		// Find people that have any of these email values in their contact_info meta
		// ACF stores repeater values as contact_info_0_contact_value, contact_info_1_contact_value, etc.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is safe
		$sql = $wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, pm.meta_value as email
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'person'
			 AND p.post_status = 'publish'
			 AND p.post_author = %d
			 AND p.ID != %d
			 AND pm.meta_key LIKE 'contact_info\_%%\_contact_value'
			 AND pm.meta_value IN ($placeholders)
			 LIMIT 10",
			array_merge( [ $user_id, $exclude_post_id ], $emails )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Already prepared above
		$potential_matches = $wpdb->get_results( $sql );

		if ( empty( $potential_matches ) ) {
			return null;
		}

		// Verify matches are actually email type (not some other contact type with same value)
		foreach ( $potential_matches as $match ) {
			$contact_info = get_field( 'contact_info', $match->ID );

			if ( ! is_array( $contact_info ) ) {
				continue;
			}

			foreach ( $contact_info as $contact ) {
				if (
					isset( $contact['contact_type'] ) &&
					$contact['contact_type'] === 'email' &&
					! empty( $contact['contact_value'] ) &&
					in_array( strtolower( trim( $contact['contact_value'] ) ), $emails, true )
				) {
					return [
						'email'       => strtolower( trim( $contact['contact_value'] ) ),
						'person_id'   => $match->ID,
						'person_name' => $match->post_title,
					];
				}
			}
		}

		return null;
	}
}
