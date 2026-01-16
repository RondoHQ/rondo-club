<?php
/**
 * Google Calendar Provider Class
 *
 * Handles syncing events from Google Calendar to the local calendar_event CPT.
 * Uses the Google Calendar API to fetch events and upserts them locally.
 */

namespace Caelis\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleProvider {

	/**
	 * Sync events from Google Calendar for a connection
	 *
	 * @param int   $user_id    WordPress user ID
	 * @param array $connection Connection data from user meta
	 * @return array Summary of sync results (created, updated, total)
	 * @throws Exception On API errors
	 */
	public static function sync( int $user_id, array $connection ): array {
		// Get valid access token
		$access_token = PRM_Google_OAuth::get_access_token( $connection );
		if ( ! $access_token ) {
			throw new Exception( 'Authentication required. Please reconnect your Google Calendar.' );
		}

		// Create Google Calendar service
		$service = self::get_service( $connection );
		if ( ! $service ) {
			throw new Exception( 'Failed to initialize Google Calendar service.' );
		}

		// Calculate date range
		$sync_from_days = isset( $connection['sync_from_days'] ) ? absint( $connection['sync_from_days'] ) : 90;
		$start_date     = new DateTime();
		$start_date->modify( "-{$sync_from_days} days" );

		$end_date = new DateTime();
		$end_date->modify( '+30 days' );

		// Determine which calendar to sync
		$calendar_id = ! empty( $connection['calendar_id'] ) ? $connection['calendar_id'] : 'primary';

		// Fetch events from Google Calendar
		$created    = 0;
		$updated    = 0;
		$total      = 0;
		$page_token = null;

		do {
			try {
				$params = [
					'timeMin'      => $start_date->format( 'c' ),
					'timeMax'      => $end_date->format( 'c' ),
					'singleEvents' => true,
					'orderBy'      => 'startTime',
					'maxResults'   => 250,
				];

				if ( $page_token ) {
					$params['pageToken'] = $page_token;
				}

				$events = $service->events->listEvents( $calendar_id, $params );

				foreach ( $events->getItems() as $event ) {
					try {
						$result = self::upsert_event( $user_id, $connection, $event );
						++$total;

						if ( $result['action'] === 'created' ) {
							++$created;
						} else {
							++$updated;
						}
					} catch ( Exception $e ) {
						// Log error but continue with other events
						error_log( 'PRM_Google_Calendar_Provider: Failed to upsert event ' . $event->getId() . ': ' . $e->getMessage() );
					}
				}

				$page_token = $events->getNextPageToken();
			} catch ( Google\Service\Exception $e ) {
				throw new Exception( 'Google Calendar API error: ' . $e->getMessage() );
			}
		} while ( $page_token );

		return [
			'created' => $created,
			'updated' => $updated,
			'total'   => $total,
		];
	}

	/**
	 * Get authenticated Google Calendar service
	 *
	 * @param array $connection Connection with credentials
	 * @return Google\Service\Calendar|null Service or null on failure
	 */
	private static function get_service( array $connection ): ?Google\Service\Calendar {
		$access_token = PRM_Google_OAuth::get_access_token( $connection );
		if ( ! $access_token ) {
			return null;
		}

		$client = PRM_Google_OAuth::get_client();
		if ( ! $client ) {
			return null;
		}

		$client->setAccessToken( $access_token );

		return new Google\Service\Calendar( $client );
	}

	/**
	 * Upsert a single event into calendar_event CPT
	 *
	 * @param int    $user_id    WordPress user ID
	 * @param array  $connection Connection data
	 * @param object $event      Google Calendar event object
	 * @return array Result with post_id and action (created/updated)
	 */
	private static function upsert_event( int $user_id, array $connection, $event ): array {
		$event_uid     = $event->getId();
		$connection_id = $connection['id'] ?? '';

		// Check for existing event
		$existing = get_posts(
			[
				'post_type'      => 'calendar_event',
				'author'         => $user_id,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => '_event_uid',
						'value' => $event_uid,
					],
					[
						'key'   => '_connection_id',
						'value' => $connection_id,
					],
				],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		$existing_id = ! empty( $existing ) ? $existing[0] : null;

		// Parse event times
		$start_time = self::parse_event_time( $event->getStart() );
		$end_time   = self::parse_event_time( $event->getEnd() );
		$is_all_day = self::is_all_day( $event );

		// Extract meeting URL and attendees
		$meeting_url = self::extract_meeting_url( $event );
		$attendees   = self::extract_attendees( $event );

		// Get organizer email
		$organizer       = $event->getOrganizer();
		$organizer_email = $organizer ? $organizer->getEmail() : '';

		// Build post data
		$post_data = [
			'post_type'    => 'calendar_event',
			'post_title'   => sanitize_text_field( $event->getSummary() ?: '(No title)' ),
			'post_content' => sanitize_textarea_field( $event->getDescription() ?: '' ),
			'post_author'  => $user_id,
			'post_status'  => 'publish',
			'post_date'    => $start_time,
		];

		$action = 'updated';
		if ( $existing_id ) {
			$post_data['ID'] = $existing_id;
			wp_update_post( $post_data );
			$post_id = $existing_id;
		} else {
			$post_id = wp_insert_post( $post_data );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( 'Failed to save event: ' . $post_id->get_error_message() );
		}

		// Update post meta
		update_post_meta( $post_id, '_connection_id', $connection_id );
		update_post_meta( $post_id, '_event_uid', $event_uid );
		update_post_meta( $post_id, '_calendar_id', $connection['calendar_id'] ?? 'primary' );
		update_post_meta( $post_id, '_start_time', $start_time );
		update_post_meta( $post_id, '_end_time', $end_time );
		update_post_meta( $post_id, '_all_day', $is_all_day ? '1' : '0' );
		update_post_meta( $post_id, '_location', sanitize_text_field( $event->getLocation() ?: '' ) );
		update_post_meta( $post_id, '_meeting_url', esc_url_raw( $meeting_url ) );
		update_post_meta( $post_id, '_organizer_email', sanitize_email( $organizer_email ) );
		update_post_meta( $post_id, '_attendees', wp_json_encode( $attendees ) );
		update_post_meta( $post_id, '_raw_data', wp_json_encode( $event->toSimpleObject() ) );

		// Run contact matching
		$matches = PRM_Calendar_Matcher::match_attendees( $user_id, $attendees );
		update_post_meta( $post_id, '_matched_people', wp_json_encode( $matches ) );

		return [
			'post_id' => $post_id,
			'action'  => $action,
		];
	}

	/**
	 * Extract attendees from Google event
	 *
	 * @param object $event Google Calendar event
	 * @return array Array of attendee data [{email, name, status}]
	 */
	private static function extract_attendees( $event ): array {
		$attendees = $event->getAttendees();
		if ( ! $attendees || ! is_array( $attendees ) ) {
			return [];
		}

		$result = [];
		foreach ( $attendees as $attendee ) {
			$email = $attendee->getEmail();
			if ( empty( $email ) ) {
				continue;
			}

			$result[] = [
				'email'  => sanitize_email( $email ),
				'name'   => sanitize_text_field( $attendee->getDisplayName() ?: '' ),
				'status' => sanitize_text_field( $attendee->getResponseStatus() ?: 'needsAction' ),
			];
		}

		return $result;
	}

	/**
	 * Extract meeting URL from Google event (Meet, Zoom, Teams links)
	 *
	 * @param object $event Google Calendar event
	 * @return string Meeting URL or empty string
	 */
	private static function extract_meeting_url( $event ): string {
		// First check for Google Meet hangout link
		$hangout_link = $event->getHangoutLink();
		if ( ! empty( $hangout_link ) ) {
			return $hangout_link;
		}

		// Check conferenceData for video entry points
		$conference_data = $event->getConferenceData();
		if ( $conference_data ) {
			$entry_points = $conference_data->getEntryPoints();
			if ( $entry_points && is_array( $entry_points ) ) {
				foreach ( $entry_points as $entry_point ) {
					if ( $entry_point->getEntryPointType() === 'video' ) {
						$uri = $entry_point->getUri();
						if ( ! empty( $uri ) ) {
							return $uri;
						}
					}
				}
			}
		}

		// Parse description and location for Zoom/Teams URLs
		$text_to_search = ( $event->getDescription() ?: '' ) . ' ' . ( $event->getLocation() ?: '' );

		// Zoom URL pattern
		if ( preg_match( '/https:\/\/[\w.-]*zoom\.us\/j\/[\w\d\-\?=&]+/i', $text_to_search, $matches ) ) {
			return $matches[0];
		}

		// Microsoft Teams URL pattern
		if ( preg_match( '/https:\/\/teams\.microsoft\.com\/l\/meetup-join\/[\w\d\-\%\/\?=&]+/i', $text_to_search, $matches ) ) {
			return $matches[0];
		}

		// Webex URL pattern
		if ( preg_match( '/https:\/\/[\w.-]*webex\.com\/[\w\d\-\/\?=&]+/i', $text_to_search, $matches ) ) {
			return $matches[0];
		}

		return '';
	}

	/**
	 * Parse event time (handles all-day vs timed events)
	 *
	 * @param object $event_time Google EventDateTime object
	 * @return string ISO 8601 datetime string formatted for MySQL
	 */
	private static function parse_event_time( $event_time ): string {
		if ( ! $event_time ) {
			return current_time( 'mysql' );
		}

		// Timed event - has dateTime
		$date_time = $event_time->getDateTime();
		if ( $date_time ) {
			// Parse and convert to site timezone
			$dt = new DateTime( $date_time );
			$dt->setTimezone( wp_timezone() );
			return $dt->format( 'Y-m-d H:i:s' );
		}

		// All-day event - has date only
		$date = $event_time->getDate();
		if ( $date ) {
			return $date . ' 00:00:00';
		}

		return current_time( 'mysql' );
	}

	/**
	 * Check if event is all-day
	 *
	 * @param object $event Google Calendar event
	 * @return bool True if all-day event
	 */
	private static function is_all_day( $event ): bool {
		$start = $event->getStart();
		if ( ! $start ) {
			return false;
		}

		// All-day events have date but not dateTime
		return $start->getDate() !== null && $start->getDateTime() === null;
	}
}
