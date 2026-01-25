<?php
/**
 * Abstract Base REST API Class
 *
 * Provides shared infrastructure for domain-specific REST API classes.
 * Contains common permission checks and response formatting methods.
 */

namespace Stadion\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Base {

	/**
	 * Constructor
	 *
	 * Child classes should call parent::__construct() and register their routes.
	 */
	public function __construct() {
		// Base constructor is intentionally empty.
		// Child classes register their own routes via rest_api_init hook.
	}

	/**
	 * Check if user is logged in and approved
	 *
	 * Permission callback for endpoints requiring an approved user.
	 * Admins are always considered approved.
	 *
	 * @return bool True if user is logged in and approved, false otherwise.
	 */
	public function check_user_approved() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();

		// Admins are always approved
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Check if user is approved
		return \STADION_User_Roles::is_user_approved( $user_id );
	}

	/**
	 * Check if user is admin
	 *
	 * Permission callback for admin-only endpoints.
	 *
	 * @return bool True if user has manage_options capability.
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can access a person
	 *
	 * Permission callback for person-specific endpoints.
	 * Verifies user is approved and owns the person post.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can access the person, false otherwise.
	 */
	public function check_person_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check approval first
		if ( ! $this->check_user_approved() ) {
			return false;
		}

		$person_id      = $request->get_param( 'person_id' );
		$access_control = new \STADION_Access_Control();

		return $access_control->user_can_access_post( $person_id );
	}

	/**
	 * Check if user can edit a person
	 *
	 * Permission callback for person edit endpoints.
	 * Verifies user is approved and has edit capability for the person post.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can edit the person, false otherwise.
	 */
	public function check_person_edit_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check approval first
		if ( ! $this->check_user_approved() ) {
			return false;
		}

		$person_id = $request->get_param( 'person_id' );
		$person    = get_post( $person_id );

		if ( ! $person || $person->post_type !== 'person' ) {
			return false;
		}

		// Check if user can edit this person
		return current_user_can( 'edit_post', $person_id );
	}

	/**
	 * Check if user can edit a team
	 *
	 * Permission callback for team edit endpoints.
	 * Verifies user is approved and has edit capability for the team post.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can edit the team, false otherwise.
	 */
	public function check_company_edit_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check approval first
		if ( ! $this->check_user_approved() ) {
			return false;
		}

		$team_id = $request->get_param( 'team_id' );
		$team    = get_post( $team_id );

		if ( ! $team || $team->post_type !== 'team' ) {
			return false;
		}

		// Check if user can edit this team
		return current_user_can( 'edit_post', $team_id );
	}

	/**
	 * Sanitize text for JSON API output
	 *
	 * Decodes HTML entities stored by WordPress into plain text.
	 * React automatically escapes text when rendering JSX, so XSS protection
	 * is handled client-side. We just need to return clean, decoded text.
	 *
	 * Note: Do NOT use esc_html() here - it's for PHP HTML templates, not JSON APIs.
	 * Using it causes double-encoding (& becomes &amp; which displays literally).
	 *
	 * @param string|null $text The text to decode.
	 * @return string Decoded text for JSON response.
	 */
	protected function sanitize_text( $text ) {
		if ( empty( $text ) ) {
			return '';
		}
		return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Sanitize rich content allowing safe HTML
	 *
	 * Allows safe HTML tags (bold, italic, links, lists) but strips dangerous tags.
	 * Use for content fields that may contain user-formatted text.
	 *
	 * @param string|null $content The content to sanitize.
	 * @return string Sanitized content with safe HTML preserved.
	 */
	protected function sanitize_rich_content( $content ) {
		if ( empty( $content ) ) {
			return '';
		}
		return wp_kses_post( $content );
	}

	/**
	 * Sanitize URL for safe output
	 *
	 * Validates and escapes URL for safe output.
	 * Use for URL fields like website links and thumbnail URLs.
	 *
	 * @param string|null $url The URL to sanitize.
	 * @return string Sanitized URL or empty string if invalid/empty.
	 */
	protected function sanitize_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		return esc_url( $url );
	}

	/**
	 * Format person for summary response
	 *
	 * Returns a minimal representation of a person for list views and relationships.
	 *
	 * @param WP_Post $post The person post object.
	 * @return array Formatted person data.
	 */
	protected function format_person_summary( $post ) {
		return [
			'id'          => $post->ID,
			'name'        => $this->sanitize_text( $post->post_title ),
			'first_name'  => $this->sanitize_text( get_field( 'first_name', $post->ID ) ),
			'last_name'   => $this->sanitize_text( get_field( 'last_name', $post->ID ) ),
			'thumbnail'   => $this->sanitize_url( get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ),
			'is_favorite' => (bool) get_field( 'is_favorite', $post->ID ),
			'labels'      => wp_get_post_terms( $post->ID, 'person_label', [ 'fields' => 'names' ] ),
		];
	}

	/**
	 * Format team for summary response
	 *
	 * Returns a minimal representation of a team for list views and relationships.
	 *
	 * @param WP_Post $post The team post object.
	 * @return array Formatted company data.
	 */
	protected function format_company_summary( $post ) {
		return [
			'id'        => $post->ID,
			'name'      => $this->sanitize_text( $post->post_title ),
			'thumbnail' => $this->sanitize_url( get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ),
			'website'   => $this->sanitize_url( get_field( 'website', $post->ID ) ),
			'labels'    => wp_get_post_terms( $post->ID, 'team_label', [ 'fields' => 'names' ] ),
		];
	}

	/**
	 * Format date for response
	 *
	 * Returns a representation of an important_date post including related people.
	 *
	 * @param WP_Post $post The important_date post object.
	 * @return array Formatted date data.
	 */
	protected function format_date( $post ) {
		$related_people = get_field( 'related_people', $post->ID ) ?: [];
		$people_names   = [];

		foreach ( $related_people as $person ) {
			$person_id      = is_object( $person ) ? $person->ID : $person;
			$people_names[] = [
				'id'   => $person_id,
				'name' => $this->sanitize_text( get_the_title( $person_id ) ),
			];
		}

		return [
			'id'             => $post->ID,
			'title'          => $this->sanitize_text( $post->post_title ),
			'custom_label'   => $this->sanitize_text( get_field( 'custom_label', $post->ID ) ),
			'date_value'     => get_field( 'date_value', $post->ID ),
			'is_recurring'   => (bool) get_field( 'is_recurring', $post->ID ),
			'year_unknown'   => (bool) get_field( 'year_unknown', $post->ID ),
			'date_type'      => wp_get_post_terms( $post->ID, 'date_type', [ 'fields' => 'names' ] ),
			'related_people' => $people_names,
		];
	}
}
