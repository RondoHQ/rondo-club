<?php
/**
 * iCal Feed for Birthdays
 *
 * Generates an iCal feed of all birthday dates accessible to a user,
 * authenticated via a secret token URL.
 */

namespace Rondo\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICalFeed {

	/**
	 * User meta key for storing the iCal token
	 */
	const TOKEN_META_KEY = 'rondo_ical_token';

	/**
	 * Token length in bytes (will be hex encoded to double this)
	 */
	const TOKEN_LENGTH = 32;

	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_action( 'template_redirect', [ $this, 'handle_feed_request' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register rewrite rules for the calendar feed
	 */
	public function register_rewrite_rules() {
		// Personal calendar feed
		add_rewrite_rule(
			'^calendar/([a-f0-9]+)\.ics$',
			'index.php?rondo_ical_feed=1&rondo_ical_token=$matches[1]',
			'top'
		);

		// Workspace calendar feed
		add_rewrite_rule(
			'^workspace/([0-9]+)/calendar/([a-f0-9]+)\.ics$',
			'index.php?rondo_workspace_ical=1&rondo_workspace_id=$matches[1]&rondo_ical_token=$matches[2]',
			'top'
		);
	}

	/**
	 * Add custom query vars
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'rondo_ical_feed';
		$vars[] = 'rondo_ical_token';
		$vars[] = 'rondo_workspace_ical';
		$vars[] = 'rondo_workspace_id';
		return $vars;
	}

	/**
	 * Handle the feed request
	 */
	public function handle_feed_request() {
		// Handle workspace calendar feed first
		if ( get_query_var( 'rondo_workspace_ical' ) ) {
			$this->handle_workspace_feed();
			exit;
		}

		// Handle personal calendar feed
		if ( ! get_query_var( 'rondo_ical_feed' ) ) {
			return;
		}

		$token = get_query_var( 'rondo_ical_token' );

		if ( empty( $token ) ) {
			status_header( 400 );
			exit( 'Invalid request' );
		}

		// Find user by token
		$user_id = $this->get_user_by_token( $token );

		if ( ! $user_id ) {
			status_header( 404 );
			exit( 'Calendar not found' );
		}

		// Generate and output the feed
		$this->output_feed( $user_id );
		exit;
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Get iCal feed URL
		register_rest_route(
			'rondo/v1',
			'/user/ical-url',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_ical_url' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Regenerate iCal token
		register_rest_route(
			'rondo/v1',
			'/user/regenerate-ical-token',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'regenerate_token' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);
	}

	/**
	 * Get or create token for a user
	 */
	public function get_user_token( $user_id ) {
		$token = get_user_meta( $user_id, self::TOKEN_META_KEY, true );

		if ( empty( $token ) ) {
			$token = $this->generate_token();
			update_user_meta( $user_id, self::TOKEN_META_KEY, $token );
		}

		return $token;
	}

	/**
	 * Generate a secure random token
	 */
	private function generate_token() {
		return bin2hex( random_bytes( self::TOKEN_LENGTH ) );
	}

	/**
	 * Find user by their iCal token
	 */
	private function get_user_by_token( $token ) {
		global $wpdb;

		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = %s AND meta_value = %s 
             LIMIT 1",
				self::TOKEN_META_KEY,
				$token
			)
		);

		return $user_id ? (int) $user_id : null;
	}

	/**
	 * Get the iCal feed URL for the current user
	 */
	public function get_ical_url( $request ) {
		$user_id = get_current_user_id();
		$token   = $this->get_user_token( $user_id );

		$url = home_url( '/calendar/' . $token . '.ics' );

		return rest_ensure_response(
			[
				'url'        => $url,
				'webcal_url' => str_replace( [ 'https://', 'http://' ], 'webcal://', $url ),
				'token'      => $token,
			]
		);
	}

	/**
	 * Regenerate the iCal token for the current user
	 */
	public function regenerate_token( $request ) {
		$user_id = get_current_user_id();
		$token   = $this->generate_token();

		update_user_meta( $user_id, self::TOKEN_META_KEY, $token );

		$url = home_url( '/calendar/' . $token . '.ics' );

		return rest_ensure_response(
			[
				'success'    => true,
				'url'        => $url,
				'webcal_url' => str_replace( [ 'https://', 'http://' ], 'webcal://', $url ),
				'message'    => __( 'Your calendar URL has been regenerated. Update any calendar subscriptions with the new URL.', 'rondo' ),
			]
		);
	}

	/**
	 * Output the iCal feed
	 */
	private function output_feed( $user_id ) {
		// Get all birthday events accessible to this user
		$dates = $this->get_user_dates( $user_id );

		// Set headers
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="rondo.ics"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Generate iCal content
		$output = $this->generate_ical( $dates );

		echo $output;
	}

	/**
	 * Get all birthdate events accessible to a user
	 *
	 * Returns birthdays from person records as calendar events.
	 */
	private function get_user_dates( $user_id ) {
		// Temporarily set the current user for access control
		$old_user = wp_get_current_user();
		wp_set_current_user( $user_id );

		global $wpdb;

		// Query people with birthdate meta that the user can access
		$people_with_birthdays = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_modified_gmt, pm.meta_value as birthdate
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'person'
			AND p.post_status = 'publish'
			AND pm.meta_key = 'birthdate'
			AND pm.meta_value != ''"
		);

		$dates = [];
		$access_control = new \RONDO_Access_Control();

		foreach ( $people_with_birthdays as $person ) {
			// Check if user has access to this person
			if ( ! $access_control->user_can_access_post( $person->ID, $user_id ) ) {
				continue;
			}

			$dates[] = [
				'id'           => $person->ID,
				'title'        => html_entity_decode( $person->post_title, ENT_QUOTES, 'UTF-8' ) . ' - Verjaardag',
				'date_value'   => $person->birthdate,
				'is_recurring' => true, // Birthdays always recur
					'people'       => [
					[
						'id'   => $person->ID,
						'name' => html_entity_decode( $person->post_title, ENT_QUOTES, 'UTF-8' ),
					],
				],
				'modified'     => $person->post_modified_gmt,
			];
		}

		// Restore original user
		wp_set_current_user( $old_user->ID );

		return $dates;
	}

	/**
	 * Generate iCal content
	 */
	private function generate_ical( $dates ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$domain    = wp_parse_url( $site_url, PHP_URL_HOST );

		$lines = [];

		// Calendar header
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//Rondo//' . $site_name . '//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'X-WR-CALNAME:' . $this->escape_ical_text( $site_name . ' - Important Dates' );
		$lines[] = 'X-WR-TIMEZONE:UTC';

		// Add events
		foreach ( $dates as $date ) {
			$event_lines = $this->generate_event( $date, $domain );
			$lines       = array_merge( $lines, $event_lines );
		}

		// Calendar footer
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Generate a single VEVENT
	 */
	private function generate_event( $date, $domain ) {
		$lines = [];

		// Parse the date
		$date_value = $date['date_value'];
		if ( empty( $date_value ) ) {
			return $lines;
		}

		// Format: YYYYMMDD for all-day events
		$dtstart = str_replace( '-', '', $date_value );

		// Calculate end date (next day for all-day events).
		$end_timestamp = strtotime( $date_value . ' +1 day' );
		$dtend         = gmdate( 'Ymd', $end_timestamp );

		// Generate UID
		$uid = 'date-' . $date['id'] . '@' . $domain;

		// Build summary with date type prefix if available
		$summary = $date['title'];

		// Build description with people names
		$description = '';
		if ( ! empty( $date['people'] ) ) {
			$names       = array_column( $date['people'], 'name' );
			$description = 'Related to: ' . implode( ', ', $names );
		}

		// Build URL to first related person (if any)
		$url = '';
		if ( ! empty( $date['people'] ) ) {
			$first_person_id = $date['people'][0]['id'];
			$url             = home_url( '/people/' . $first_person_id );
		}

		// Modified timestamp for DTSTAMP
		$dtstamp = gmdate( 'Ymd\THis\Z', strtotime( $date['modified'] ) );

		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . $uid;
		$lines[] = 'DTSTAMP:' . $dtstamp;
		$lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
		$lines[] = 'DTEND;VALUE=DATE:' . $dtend;
		$lines[] = 'SUMMARY:' . $this->escape_ical_text( $summary );

		if ( ! empty( $description ) ) {
			$lines[] = 'DESCRIPTION:' . $this->escape_ical_text( $description );
		}

		if ( ! empty( $url ) ) {
			$lines[] = 'URL:' . $url;
		}

		// Add recurrence rule for recurring dates
		if ( $date['is_recurring'] ) {
			$lines[] = 'RRULE:FREQ=YEARLY';
		}

		$lines[] = 'END:VEVENT';

		return $lines;
	}

	/**
	 * Handle workspace iCal feed request
	 */
	private function handle_workspace_feed() {
		$workspace_id = intval( get_query_var( 'rondo_workspace_id' ) );
		$token        = sanitize_text_field( get_query_var( 'rondo_ical_token' ) );

		// Verify token belongs to a valid user
		$user_id = $this->get_user_by_token( $token );
		if ( ! $user_id ) {
			status_header( 403 );
			echo 'Invalid token';
			exit;
		}

		// Verify workspace exists
		$workspace = get_post( $workspace_id );
		if ( ! $workspace || $workspace->post_type !== 'workspace' || $workspace->post_status !== 'publish' ) {
			status_header( 404 );
			echo 'Workspace not found';
			exit;
		}

		// Verify user is a member of the workspace
		if ( ! \RONDO_Workspace_Members::is_member( $workspace_id, $user_id ) ) {
			status_header( 403 );
			echo 'Access denied - not a workspace member';
			exit;
		}

		// Get all birthday dates for contacts in this workspace
		$dates = $this->get_workspace_birthdays( $workspace_id );

		// Generate iCal output with workspace-specific calendar name
		$this->output_workspace_ical( $dates, $workspace->post_title );
	}

	/**
	 * Get birthday dates for contacts in a workspace
	 *
	 * @param int $workspace_id The workspace post ID.
	 * @return array Array of date data for iCal generation.
	 */
	private function get_workspace_birthdays( $workspace_id ) {
		// Get term ID for this workspace
		$term = get_term_by( 'slug', 'workspace-' . $workspace_id, 'workspace_access' );
		if ( ! $term ) {
			return [];
		}

		global $wpdb;

		// Get all contacts (people) in this workspace with birthdate set
		$people_with_birthdays = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_modified_gmt, pm.meta_value as birthdate
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				WHERE p.post_type = 'person'
				AND p.post_status = 'publish'
				AND pm.meta_key = 'birthdate'
				AND pm.meta_value != ''
				AND tr.term_taxonomy_id = %d",
				$term->term_taxonomy_id
			)
		);

		// Format dates for iCal generation
		$dates = [];
		foreach ( $people_with_birthdays as $person ) {
			$dates[] = [
				'id'           => $person->ID,
				'title'        => html_entity_decode( $person->post_title, ENT_QUOTES, 'UTF-8' ) . ' - Verjaardag',
				'date_value'   => $person->birthdate,
				'is_recurring' => true, // Birthdays always recur
				'people'       => [
					[
						'id'   => $person->ID,
						'name' => html_entity_decode( $person->post_title, ENT_QUOTES, 'UTF-8' ),
					],
				],
				'modified'     => $person->post_modified_gmt,
			];
		}

		return $dates;
	}

	/**
	 * Output iCal feed for workspace
	 *
	 * @param array  $dates          Array of date data.
	 * @param string $workspace_name The workspace name for calendar title.
	 */
	private function output_workspace_ical( $dates, $workspace_name ) {
		// Set headers
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="rondo-workspace.ics"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Generate iCal content with workspace-specific name
		$output = $this->generate_workspace_ical( $dates, $workspace_name );

		echo $output;
	}

	/**
	 * Generate iCal content for workspace
	 *
	 * @param array  $dates          Array of date data.
	 * @param string $workspace_name The workspace name.
	 * @return string iCal formatted content.
	 */
	private function generate_workspace_ical( $dates, $workspace_name ) {
		$site_url = home_url();
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );

		$lines = [];

		// Calendar header
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//Rondo//' . $workspace_name . '//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'X-WR-CALNAME:' . $this->escape_ical_text( 'Rondo - ' . $workspace_name );
		$lines[] = 'X-WR-TIMEZONE:UTC';

		// Add events
		foreach ( $dates as $date ) {
			$event_lines = $this->generate_event( $date, $domain );
			$lines       = array_merge( $lines, $event_lines );
		}

		// Calendar footer
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Escape text for iCal format
	 */
	private function escape_ical_text( $text ) {
		// Replace newlines, commas, semicolons, and backslashes
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( ',', '\,', $text );
		$text = str_replace( ';', '\;', $text );
		$text = str_replace( "\r\n", '\n', $text );
		$text = str_replace( "\r", '\n', $text );
		$text = str_replace( "\n", '\n', $text );

		return $text;
	}
}
