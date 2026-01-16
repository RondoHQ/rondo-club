<?php
/**
 * iCal Feed for Important Dates
 *
 * Generates an iCal feed of all important dates accessible to a user,
 * authenticated via a secret token URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_ICal_Feed {

	/**
	 * User meta key for storing the iCal token
	 */
	const TOKEN_META_KEY = 'prm_ical_token';

	/**
	 * Token length in bytes (will be hex encoded to double this)
	 */
	const TOKEN_LENGTH = 32;

	public function __construct() {
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_feed_request' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register rewrite rules for the calendar feed
	 */
	public function register_rewrite_rules() {
		// Personal calendar feed
		add_rewrite_rule(
			'^calendar/([a-f0-9]+)\.ics$',
			'index.php?prm_ical_feed=1&prm_ical_token=$matches[1]',
			'top'
		);

		// Workspace calendar feed
		add_rewrite_rule(
			'^workspace/([0-9]+)/calendar/([a-f0-9]+)\.ics$',
			'index.php?prm_workspace_ical=1&prm_workspace_id=$matches[1]&prm_ical_token=$matches[2]',
			'top'
		);
	}

	/**
	 * Add custom query vars
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'prm_ical_feed';
		$vars[] = 'prm_ical_token';
		$vars[] = 'prm_workspace_ical';
		$vars[] = 'prm_workspace_id';
		return $vars;
	}

	/**
	 * Handle the feed request
	 */
	public function handle_feed_request() {
		// Handle workspace calendar feed first
		if ( get_query_var( 'prm_workspace_ical' ) ) {
			$this->handle_workspace_feed();
			exit;
		}

		// Handle personal calendar feed
		if ( ! get_query_var( 'prm_ical_feed' ) ) {
			return;
		}

		$token = get_query_var( 'prm_ical_token' );

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
			'prm/v1',
			'/user/ical-url',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ical_url' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Regenerate iCal token
		register_rest_route(
			'prm/v1',
			'/user/regenerate-ical-token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'regenerate_token' ),
				'permission_callback' => 'is_user_logged_in',
			)
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
			array(
				'url'        => $url,
				'webcal_url' => str_replace( array( 'https://', 'http://' ), 'webcal://', $url ),
				'token'      => $token,
			)
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
			array(
				'success'    => true,
				'url'        => $url,
				'webcal_url' => str_replace( array( 'https://', 'http://' ), 'webcal://', $url ),
				'message'    => __( 'Your calendar URL has been regenerated. Update any calendar subscriptions with the new URL.', 'personal-crm' ),
			)
		);
	}

	/**
	 * Output the iCal feed
	 */
	private function output_feed( $user_id ) {
		// Get all important dates accessible to this user
		$dates = $this->get_user_dates( $user_id );

		// Set headers
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="caelis.ics"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Generate iCal content
		$output = $this->generate_ical( $dates );

		echo $output;
	}

	/**
	 * Get all important dates accessible to a user
	 */
	private function get_user_dates( $user_id ) {
		// Temporarily set the current user for access control
		$old_user = wp_get_current_user();
		wp_set_current_user( $user_id );

		$args = array(
			'post_type'      => 'important_date',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		// Access control filters will automatically apply
		$posts = get_posts( $args );

		$dates = array();
		foreach ( $posts as $post ) {
			$related_people = get_field( 'related_people', $post->ID ) ?: array();
			$people_data    = array();

			foreach ( $related_people as $person ) {
				$person_id     = is_object( $person ) ? $person->ID : $person;
				$people_data[] = array(
					'id'   => $person_id,
					'name' => html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' ),
				);
			}

			$date_types = wp_get_post_terms( $post->ID, 'date_type', array( 'fields' => 'names' ) );

			$dates[] = array(
				'id'           => $post->ID,
				'title'        => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
				'date_value'   => get_field( 'date_value', $post->ID ),
				'is_recurring' => (bool) get_field( 'is_recurring', $post->ID ),
				'date_type'    => ! empty( $date_types ) ? $date_types[0] : '',
				'people'       => $people_data,
				'modified'     => $post->post_modified_gmt,
			);
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

		$lines = array();

		// Calendar header
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//Caelis//' . $site_name . '//EN';
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
		$lines = array();

		// Parse the date
		$date_value = $date['date_value'];
		if ( empty( $date_value ) ) {
			return $lines;
		}

		// Format: YYYYMMDD for all-day events
		$dtstart = str_replace( '-', '', $date_value );

		// Calculate end date (next day for all-day events)
		$end_timestamp = strtotime( $date_value . ' +1 day' );
		$dtend         = date( 'Ymd', $end_timestamp );

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

		// Add categories if date type exists
		if ( ! empty( $date['date_type'] ) ) {
			$lines[] = 'CATEGORIES:' . $this->escape_ical_text( $date['date_type'] );
		}

		$lines[] = 'END:VEVENT';

		return $lines;
	}

	/**
	 * Handle workspace iCal feed request
	 */
	private function handle_workspace_feed() {
		$workspace_id = intval( get_query_var( 'prm_workspace_id' ) );
		$token        = sanitize_text_field( get_query_var( 'prm_ical_token' ) );

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
		if ( ! PRM_Workspace_Members::is_member( $workspace_id, $user_id ) ) {
			status_header( 403 );
			echo 'Access denied - not a workspace member';
			exit;
		}

		// Get all important dates for contacts in this workspace
		$dates = $this->get_workspace_important_dates( $workspace_id );

		// Generate iCal output with workspace-specific calendar name
		$this->output_workspace_ical( $dates, $workspace->post_title );
	}

	/**
	 * Get important dates for contacts in a workspace
	 *
	 * @param int $workspace_id The workspace post ID.
	 * @return array Array of date data for iCal generation.
	 */
	private function get_workspace_important_dates( $workspace_id ) {
		// Get term ID for this workspace
		$term = get_term_by( 'slug', 'workspace-' . $workspace_id, 'workspace_access' );
		if ( ! $term ) {
			return array();
		}

		// Get all contacts (people) in this workspace
		$people = get_posts(
			array(
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'tax_query'      => array(
					array(
						'taxonomy' => 'workspace_access',
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
				'fields'         => 'ids',
			)
		);

		if ( empty( $people ) ) {
			return array();
		}

		// Get important dates linked to these people
		$date_posts = get_posts(
			array(
				'post_type'      => 'important_date',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => 'related_people',
						'value'   => $people,
						'compare' => 'IN',
					),
				),
			)
		);

		// If no results with standard meta query, try ACF relationship approach
		if ( empty( $date_posts ) ) {
			// ACF stores relationship fields differently, so we need to query each person
			$date_ids = array();
			foreach ( $people as $person_id ) {
				$person_dates = get_posts(
					array(
						'post_type'      => 'important_date',
						'posts_per_page' => -1,
						'post_status'    => 'publish',
						'meta_query'     => array(
							array(
								'key'     => 'related_people',
								'value'   => '"' . $person_id . '"',
								'compare' => 'LIKE',
							),
						),
						'fields'         => 'ids',
					)
				);
				$date_ids     = array_merge( $date_ids, $person_dates );
			}

			if ( ! empty( $date_ids ) ) {
				$date_posts = get_posts(
					array(
						'post_type'      => 'important_date',
						'posts_per_page' => -1,
						'post_status'    => 'publish',
						'post__in'       => array_unique( $date_ids ),
					)
				);
			}
		}

		// Format dates for iCal generation (same format as get_user_dates)
		$dates = array();
		foreach ( $date_posts as $post ) {
			$related_people = get_field( 'related_people', $post->ID ) ?: array();
			$people_data    = array();

			foreach ( $related_people as $person ) {
				$person_id     = is_object( $person ) ? $person->ID : $person;
				$people_data[] = array(
					'id'   => $person_id,
					'name' => html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' ),
				);
			}

			$date_types = wp_get_post_terms( $post->ID, 'date_type', array( 'fields' => 'names' ) );

			$dates[] = array(
				'id'           => $post->ID,
				'title'        => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
				'date_value'   => get_field( 'date_value', $post->ID ),
				'is_recurring' => (bool) get_field( 'is_recurring', $post->ID ),
				'date_type'    => ! empty( $date_types ) ? $date_types[0] : '',
				'people'       => $people_data,
				'modified'     => $post->post_modified_gmt,
			);
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
		header( 'Content-Disposition: attachment; filename="caelis-workspace.ics"' );
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

		$lines = array();

		// Calendar header
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//Caelis//' . $workspace_name . '//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'X-WR-CALNAME:' . $this->escape_ical_text( 'Caelis - ' . $workspace_name );
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
