<?php
/**
 * Auto-generate post titles from ACF fields
 */

namespace Caelis\Core;

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
		add_action( 'rest_after_insert_person', [ $this, 'trigger_calendar_rematch_rest' ], 25 );

		// Hide title field in admin for person CPT
		add_filter( 'acf/prepare_field/name=_post_title', [ $this, 'hide_title_field' ] );

		// Lowercase email addresses on save
		add_filter( 'acf/update_value/key=field_contact_value', [ $this, 'maybe_lowercase_email' ], 10, 4 );
		add_filter( 'acf/update_value/key=field_company_contact_value', [ $this, 'maybe_lowercase_email' ], 10, 4 );
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
			$full_name = __( 'Unnamed Person', 'caelis' );
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
		$type_label = ! empty( $date_types ) ? $date_types[0] : __( 'Date', 'caelis' );

		// Get related people
		$people = get_field( 'related_people', $post_id ) ?: [];

		if ( empty( $people ) ) {
			return sprintf( __( 'Unnamed %s', 'caelis' ), $type_label );
		}

		// Get full names of related people
		$names = [];
		foreach ( $people as $person ) {
			$person_id = is_object( $person ) ? $person->ID : $person;
			$full_name = html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' );
			if ( $full_name && $full_name !== __( 'Unnamed Person', 'caelis' ) ) {
				$names[] = $full_name;
			}
		}

		if ( empty( $names ) ) {
			return sprintf( __( 'Unnamed %s', 'caelis' ), $type_label );
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
					__( 'Wedding of %1$s & %2$s', 'caelis' ),
					$names[0],
					$names[1]
				);
			} elseif ( $count === 1 ) {
				// "Wedding of Person1" (fallback if only one person)
				return sprintf( __( 'Wedding of %s', 'caelis' ), $names[0] );
			}
		}

		if ( $count === 1 ) {
			// "Sarah's Birthday"
			return sprintf( __( "%1\$s's %2\$s", 'caelis' ), $names[0], $type_label );
		} elseif ( $count === 2 ) {
			// "Tom & Lisa's Birthday"
			return sprintf(
				__( "%1\$s & %2\$s's %3\$s", 'caelis' ),
				$names[0],
				$names[1],
				$type_label
			);
		} else {
			// "Mike, Sarah +2 Birthday"
			$first_two = implode( ', ', array_slice( $names, 0, 2 ) );
			$remaining = $count - 2;
			return sprintf(
				__( '%1$s +%2$d %3$s', 'caelis' ),
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

		// Trigger calendar re-matching
		\Caelis\Calendar\Matcher::on_person_saved( $post_id );
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
		// Trigger calendar re-matching
		\Caelis\Calendar\Matcher::on_person_saved( $post->ID );
	}
}
