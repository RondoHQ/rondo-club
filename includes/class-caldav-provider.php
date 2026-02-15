<?php
/**
 * CalDAV Calendar Provider Class
 *
 * Handles syncing events from CalDAV servers (iCloud, Fastmail, Nextcloud, etc.)
 * to the local calendar_event CPT. Uses Sabre\DAV for WebDAV operations and
 * Sabre\VObject for iCalendar parsing.
 *
 * Provider-specific URL patterns:
 * - iCloud: https://caldav.icloud.com (requires app-specific password)
 *   Generate at: https://appleid.apple.com -> Security -> App-Specific Passwords
 * - Fastmail: https://caldav.fastmail.com/dav/calendars/user/{email}/
 * - Nextcloud: https://yourserver.com/remote.php/dav/calendars/{user}/
 * - Generic: User provides full calendar URL
 */

namespace Rondo\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Sabre\DAV\Client;
use Sabre\VObject\Reader;

class CalDAVProvider {

	/**
	 * Known meeting URL domains
	 */
	private const MEETING_DOMAINS = [
		'zoom.us',
		'teams.microsoft.com',
		'meet.google.com',
		'webex.com',
	];

	/**
	 * HTTP error messages for common status codes
	 * Keys are error codes, values are already-translated message keys
	 */
	private const HTTP_ERROR_MESSAGES = [
		'401'          => 'auth_failed',
		'Unauthorized' => 'auth_failed',
		'404'          => 'not_found',
	];

	/**
	 * Test CalDAV connection credentials
	 *
	 * Validates that the provided credentials work by checking for CalDAV support
	 * and discovering available calendars.
	 *
	 * @param string $url      CalDAV server URL
	 * @param string $username Username (email for most providers)
	 * @param string $password Password (app-specific password for iCloud)
	 * @return array ['success' => bool, 'calendars' => [...], 'error' => string]
	 */
	public static function test_connection( string $url, string $username, string $password ): array {
		try {
			// Create DAV client with basic auth
			$client = self::create_dav_client( $url, $username, $password );

			// Check for CalDAV support via OPTIONS request
			$supported = self::check_caldav_support( $client, $url );
			if ( ! $supported ) {
				return [
					'success' => false,
					'error'   => __( 'Server does not support CalDAV. Please check the URL.', 'rondo' ),
				];
			}

			// Discover available calendars
			$calendars = self::discover_calendars( $url, $username, $password );

			if ( empty( $calendars ) ) {
				return [
					'success'   => true,
					'calendars' => [],
					'message'   => __( 'Connected successfully but no calendars found.', 'rondo' ),
				];
			}

			return [
				'success'   => true,
				'calendars' => $calendars,
			];

		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();

			// Check for common HTTP error codes and provide user-friendly messages
			foreach ( self::HTTP_ERROR_MESSAGES as $error_code => $message_key ) {
				if ( strpos( $error_message, $error_code ) !== false ) {
					$friendly_message = match ( $message_key ) {
						'auth_failed' => __( 'Authentication failed. Check username and password. For iCloud, use an app-specific password.', 'rondo' ),
						'not_found'   => __( 'Calendar URL not found. Please verify the CalDAV server address.', 'rondo' ),
						default       => __( 'Connection failed', 'rondo' ),
					};

					return [
						'success' => false,
						'error'   => $friendly_message,
					];
				}
			}

			return [
				'success' => false,
				'error'   => sprintf( __( 'Connection failed: %s', 'rondo' ), $error_message ),
			];
		}
	}

	/**
	 * Check if server supports CalDAV
	 *
	 * @param Client $client Sabre DAV client
	 * @param string $url    Server URL
	 * @return bool True if CalDAV is supported
	 */
	private static function check_caldav_support( Client $client, string $url ): bool {
		try {
			$response = $client->options();

			// Check for calendar-access capability in DAV header
			if ( isset( $response['dav'] ) ) {
				$dav_header = is_array( $response['dav'] ) ? implode( ', ', $response['dav'] ) : $response['dav'];
				return strpos( $dav_header, 'calendar-access' ) !== false;
			}

			// Also check if we can access the calendar home
			return true; // Assume support if we got a response
		} catch ( \Exception $e ) {
			// If OPTIONS fails, try to proceed anyway (some servers don't support it)
			return true;
		}
	}

	/**
	 * Discover available calendars from CalDAV server
	 *
	 * Uses PROPFIND with Depth: 1 to find calendar collections.
	 *
	 * @param string $url      CalDAV server URL
	 * @param string $username Username
	 * @param string $password Password
	 * @return array Array of ['id' => href, 'name' => displayname, 'color' => calendar-color]
	 */
	public static function discover_calendars( string $url, string $username, string $password ): array {
		$client = self::create_dav_client( $url, $username, $password );

		// Build PROPFIND request to find calendar collections
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:cs="http://calendarserver.org/ns/" xmlns:ic="http://apple.com/ns/ical/">
    <d:prop>
        <d:resourcetype/>
        <d:displayname/>
        <ic:calendar-color/>
        <cs:getctag/>
    </d:prop>
</d:propfind>';

		try {
			$response = $client->request(
				'PROPFIND',
				'',
				$body,
				[
					'Depth'        => '1',
					'Content-Type' => 'application/xml; charset=utf-8',
				]
			);

			if ( ! self::is_http_success( $response['statusCode'] ) ) {
				return [];
			}

			return self::parse_calendar_response( $response['body'], $url );
		} catch ( \Exception $e ) {
			error_log( 'RONDO_CalDAV_Provider: Failed to discover calendars: ' . $e->getMessage() );
			return [];
		}
	}

	/**
	 * Parse PROPFIND response to extract calendar information
	 *
	 * @param string $xml_body Response XML
	 * @param string $base_url Base URL for resolving relative hrefs
	 * @return array Array of calendar objects
	 */
	private static function parse_calendar_response( string $xml_body, string $base_url ): array {
		$calendars = [];

		try {
			$xml = new \SimpleXMLElement( $xml_body );
			self::register_caldav_namespaces( $xml );

			// Find all responses
			$responses = $xml->xpath( '//d:response' );

			foreach ( $responses as $response ) {
				// Get href (calendar ID)
				$href_elements = $response->xpath( 'd:href' );
				if ( empty( $href_elements ) ) {
					continue;
				}

				// Check if this is a calendar resource
				$resourcetype = $response->xpath( 'd:propstat/d:prop/d:resourcetype/c:calendar' );
				if ( empty( $resourcetype ) ) {
					// Also check without namespace in case server uses different format
					$resourcetype = $response->xpath( './/d:resourcetype/*[local-name()="calendar"]' );
					if ( empty( $resourcetype ) ) {
						continue;
					}
				}

				$href = (string) $href_elements[0];

				// Get display name
				$displayname_elements = $response->xpath( 'd:propstat/d:prop/d:displayname' );
				if ( ! empty( $displayname_elements ) ) {
					$displayname = (string) $displayname_elements[0];
				} else {
					$displayname = basename( $href );
				}

				// Get calendar color (Apple extension)
				$color_elements = $response->xpath( 'd:propstat/d:prop/ic:calendar-color' );
				$color          = ! empty( $color_elements ) ? (string) $color_elements[0] : '#0000FF';

				// Clean up color format (some servers include alpha channel)
				if ( strlen( $color ) === 9 && $color[0] === '#' ) {
					$color = substr( $color, 0, 7 ); // Remove alpha channel
				}

				$calendars[] = [
					'id'    => $href,
					'name'  => $displayname,
					'color' => $color,
				];
			}
		} catch ( \Exception $e ) {
			error_log( 'RONDO_CalDAV_Provider: Failed to parse calendar response: ' . $e->getMessage() );
		}

		return $calendars;
	}

	/**
	 * Sync events from CalDAV calendar
	 *
	 * Uses calendar-query REPORT to fetch events in date range.
	 *
	 * @param int   $user_id    WordPress user ID
	 * @param array $connection Connection from user meta (includes encrypted credentials)
	 * @return array ['created' => int, 'updated' => int, 'total' => int]
	 * @throws Exception On sync errors
	 */
	public static function sync( int $user_id, array $connection ): array {
		$connection_id = $connection['id'] ?? '';

		// Prevent concurrent syncs for same connection (race condition fix)
		$lock_key = 'rondo_sync_lock_' . $user_id . '_' . $connection_id;
		if ( get_transient( $lock_key ) ) {
			// Another sync is in progress, skip this one
			return [
				'created' => 0,
				'updated' => 0,
				'total'   => 0,
				'skipped' => true,
			];
		}
		// Set lock for 5 minutes (sync should complete well within this)
		set_transient( $lock_key, true, 5 * MINUTE_IN_SECONDS );

		try {
			return self::do_sync( $user_id, $connection );
		} finally {
			// Always release lock when done
			delete_transient( $lock_key );
		}
	}

	/**
	 * Perform the actual sync (called by sync() with lock protection)
	 *
	 * @param int   $user_id    WordPress user ID
	 * @param array $connection Connection from user meta (includes encrypted credentials)
	 * @return array ['created' => int, 'updated' => int, 'total' => int]
	 * @throws \Exception On sync errors
	 */
	private static function do_sync( int $user_id, array $connection ): array {
		// Decrypt credentials
		$credentials = \Rondo\Data\CredentialEncryption::decrypt( $connection['credentials'] );
		if ( ! $credentials || empty( $credentials['url'] ) || empty( $credentials['username'] ) || empty( $credentials['password'] ) ) {
			throw new \Exception( __( 'Invalid CalDAV credentials. Please reconnect your calendar.', 'rondo' ) );
		}

		$url      = $credentials['url'];
		$username = $credentials['username'];
		$password = $credentials['password'];

		// Get the specific calendar to sync
		$calendar_id = ! empty( $connection['calendar_id'] ) ? $connection['calendar_id'] : '';

		// Build calendar URL
		$calendar_url = $url;
		if ( ! empty( $calendar_id ) && $calendar_id !== '/' ) {
			// If calendar_id is relative, append it to base URL
			if ( strpos( $calendar_id, 'http' ) !== 0 ) {
				$calendar_url = rtrim( $url, '/' ) . '/' . ltrim( $calendar_id, '/' );
			} else {
				$calendar_url = $calendar_id;
			}
		}

		$client = self::create_dav_client( $calendar_url, $username, $password );

		// Calculate date range
		$sync_from_days = isset( $connection['sync_from_days'] ) ? absint( $connection['sync_from_days'] ) : 90;
		$sync_to_days   = isset( $connection['sync_to_days'] ) ? absint( $connection['sync_to_days'] ) : 30;

		$start_date = new \DateTime();
		$start_date->modify( "-{$sync_from_days} days" );

		$end_date = new \DateTime();
		$end_date->modify( "+{$sync_to_days} days" );

		// Format dates for CalDAV (UTC format required)
		$start_utc = $start_date->format( 'Ymd\THis\Z' );
		$end_utc   = $end_date->format( 'Ymd\THis\Z' );

		// Build calendar-query REPORT request
		$body = '<?xml version="1.0" encoding="UTF-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
    <d:prop>
        <d:getetag/>
        <c:calendar-data/>
    </d:prop>
    <c:filter>
        <c:comp-filter name="VCALENDAR">
            <c:comp-filter name="VEVENT">
                <c:time-range start="' . $start_utc . '" end="' . $end_utc . '"/>
            </c:comp-filter>
        </c:comp-filter>
    </c:filter>
</c:calendar-query>';

		try {
			$response = $client->request(
				'REPORT',
				'',
				$body,
				[
					'Depth'        => '1',
					'Content-Type' => 'application/xml; charset=utf-8',
				]
			);

			if ( ! self::is_http_success( $response['statusCode'] ) ) {
				throw new \Exception( sprintf( __( 'CalDAV server returned error: %d', 'rondo' ), $response['statusCode'] ) );
			}

			// Parse response and upsert events
			$result = self::process_calendar_report( $user_id, $connection, $response['body'] );

			// Delete local events that no longer exist in the source calendar
			$deleted = self::delete_removed_events(
				$user_id,
				$connection['id'] ?? '',
				$connection['calendar_id'] ?? '',
				$result['seen_uids'],
				$start_date,
				$end_date
			);

			return [
				'created' => $result['created'],
				'updated' => $result['updated'],
				'deleted' => $deleted,
				'total'   => $result['total'],
			];

		} catch ( \Exception $e ) {
			error_log( 'RONDO_CalDAV_Provider: Sync failed: ' . $e->getMessage() );
			throw new \Exception( sprintf( __( 'Sync failed: %s', 'rondo' ), $e->getMessage() ) );
		}
	}

	/**
	 * Delete local events that no longer exist in the source calendar
	 *
	 * Finds all local events for this connection/calendar within the sync date range
	 * and deletes any whose UIDs are not in the list of seen UIDs from the API.
	 *
	 * @param int       $user_id         WordPress user ID
	 * @param string    $connection_id   Connection ID
	 * @param string    $calendar_id     Calendar ID
	 * @param array     $seen_event_uids Array of event UIDs that exist in source
	 * @param \DateTime $start_date      Start of sync window
	 * @param \DateTime $end_date        End of sync window
	 * @return int Number of events deleted
	 */
	private static function delete_removed_events(
		int $user_id,
		string $connection_id,
		string $calendar_id,
		array $seen_event_uids,
		\DateTime $start_date,
		\DateTime $end_date
	): int {
		if ( empty( $connection_id ) ) {
			return 0;
		}

		// Build meta query - calendar_id may be empty for single-calendar connections
		$meta_query = [
			'relation' => 'AND',
			[
				'key'   => '_connection_id',
				'value' => $connection_id,
			],
			[
				'key'     => '_start_time',
				'value'   => $start_date->format( 'Y-m-d H:i:s' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			],
			[
				'key'     => '_start_time',
				'value'   => $end_date->format( 'Y-m-d H:i:s' ),
				'compare' => '<=',
				'type'    => 'DATETIME',
			],
		];

		// Only filter by calendar_id if one is set
		if ( ! empty( $calendar_id ) ) {
			$meta_query[] = [
				'key'   => '_calendar_id',
				'value' => $calendar_id,
			];
		}

		// Query all local events for this connection/calendar within the sync date range
		$local_events = get_posts(
			[
				'post_type'      => 'calendar_event',
				'post_status'    => [ 'publish', 'future' ],
				'author'         => $user_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => $meta_query,
			]
		);

		$deleted = 0;

		foreach ( $local_events as $event_id ) {
			$event_uid = get_post_meta( $event_id, '_event_uid', true );

			// If this event's UID is not in the list of seen UIDs, it was deleted
			if ( ! in_array( $event_uid, $seen_event_uids, true ) ) {
				wp_delete_post( $event_id, true ); // Force delete (bypass trash)
				++$deleted;

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'RONDO_CalDAV_Provider: Deleted event %d (UID: %s) - no longer exists in source calendar',
							$event_id,
							$event_uid
						)
					);
				}
			}
		}

		return $deleted;
	}

	/**
	 * Process calendar REPORT response and upsert events
	 *
	 * @param int    $user_id    WordPress user ID
	 * @param array  $connection Connection data
	 * @param string $xml_body   Response XML containing calendar data
	 * @return array ['created' => int, 'updated' => int, 'deleted' => int, 'total' => int, 'seen_uids' => array]
	 */
	private static function process_calendar_report( int $user_id, array $connection, string $xml_body ): array {
		$created         = 0;
		$updated         = 0;
		$total           = 0;
		$seen_event_uids = []; // Track all UIDs seen from API

		try {
			$xml = new \SimpleXMLElement( $xml_body );
			self::register_caldav_namespaces( $xml );

			// Find all responses
			$responses = $xml->xpath( '//d:response' );

			foreach ( $responses as $response ) {
				// Get calendar-data (iCalendar content)
				$calendar_data = $response->xpath( 'd:propstat/d:prop/c:calendar-data' );
				if ( empty( $calendar_data ) ) {
					continue;
				}

				$ical_data = (string) $calendar_data[0];
				if ( empty( $ical_data ) ) {
					continue;
				}

				try {
					// Parse iCalendar data with Sabre VObject
					$vcalendar = Reader::read( $ical_data );

					// Process all VEVENTs in this calendar object
					foreach ( $vcalendar->VEVENT as $vevent ) {
						// Track this event UID as seen
						if ( isset( $vevent->UID ) ) {
							$seen_event_uids[] = (string) $vevent->UID;
						}

						try {
							$result = self::upsert_event( $user_id, $connection, $vevent );
							++$total;

							if ( $result['action'] === 'created' ) {
								++$created;
							} else {
								++$updated;
							}
						} catch ( \Exception $e ) {
							error_log( 'RONDO_CalDAV_Provider: Failed to upsert event: ' . $e->getMessage() );
						}
					}
				} catch ( \Exception $e ) {
					error_log( 'RONDO_CalDAV_Provider: Failed to parse iCalendar: ' . $e->getMessage() );
				}
			}
		} catch ( \Exception $e ) {
			error_log( 'RONDO_CalDAV_Provider: Failed to parse REPORT response: ' . $e->getMessage() );
		}

		return [
			'created'   => $created,
			'updated'   => $updated,
			'total'     => $total,
			'seen_uids' => $seen_event_uids,
		];
	}

	/**
	 * Upsert a single event into calendar_event CPT
	 *
	 * @param int                        $user_id    WordPress user ID
	 * @param array                      $connection Connection data
	 * @param \Sabre\VObject\Component   $vevent     VEVENT component from iCalendar
	 * @return array Result with post_id and action (created/updated)
	 */
	private static function upsert_event( int $user_id, array $connection, $vevent ): array {
		// Get unique event identifier
		$event_uid = isset( $vevent->UID ) ? (string) $vevent->UID : '';
		if ( empty( $event_uid ) ) {
			throw new \Exception( 'Event has no UID' );
		}

		$connection_id = $connection['id'] ?? '';

		// Check for existing event
		// Include 'any' post_status because future events have 'future' status
		$existing = get_posts(
			[
				'post_type'      => 'calendar_event',
				'post_status'    => 'any',
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
		$start_time = self::parse_event_time( $vevent->DTSTART );
		$end_time   = self::parse_event_time( $vevent->DTEND ?? $vevent->DTSTART );
		$is_all_day = self::is_all_day( $vevent );

		// Extract event data
		$summary     = isset( $vevent->SUMMARY ) ? (string) $vevent->SUMMARY : '(No title)';
		$description = isset( $vevent->DESCRIPTION ) ? (string) $vevent->DESCRIPTION : '';
		$location    = isset( $vevent->LOCATION ) ? (string) $vevent->LOCATION : '';

		// Extract meeting URL and attendees
		$meeting_url = self::extract_meeting_url( $vevent, $description );
		$attendees   = self::parse_attendees( $vevent );

		// Get organizer email
		$organizer_email = '';
		if ( isset( $vevent->ORGANIZER ) ) {
			$organizer       = (string) $vevent->ORGANIZER;
			$organizer_email = self::strip_mailto_prefix( $organizer );
		}

		// Build post data
		$post_data = [
			'post_type'    => 'calendar_event',
			'post_title'   => sanitize_text_field( $summary ),
			'post_content' => sanitize_textarea_field( $description ),
			'post_author'  => $user_id,
			'post_status'  => 'publish',
			'post_date'    => $start_time,
		];

		if ( $existing_id ) {
			$post_data['ID'] = $existing_id;
			wp_update_post( $post_data );
			$post_id = $existing_id;
			$action  = 'updated';
		} else {
			$post_id = wp_insert_post( $post_data );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( 'Failed to save event: ' . $post_id->get_error_message() );
		}

		// Update post meta (same fields as Google provider)
		update_post_meta( $post_id, '_connection_id', $connection_id );
		update_post_meta( $post_id, '_event_uid', $event_uid );
		update_post_meta( $post_id, '_calendar_id', $connection['calendar_id'] ?? '' );
		update_post_meta( $post_id, '_start_time', $start_time );
		update_post_meta( $post_id, '_end_time', $end_time );
		update_post_meta( $post_id, '_all_day', $is_all_day ? '1' : '0' );
		update_post_meta( $post_id, '_location', sanitize_text_field( $location ) );
		update_post_meta( $post_id, '_meeting_url', esc_url_raw( $meeting_url ) );
		update_post_meta( $post_id, '_organizer_email', sanitize_email( $organizer_email ) );
		update_post_meta( $post_id, '_attendees', wp_json_encode( $attendees ) );

		// Store raw iCalendar data for debugging
		$raw_data = [
			'uid'         => $event_uid,
			'summary'     => $summary,
			'description' => $description,
			'location'    => $location,
			'start'       => $start_time,
			'end'         => $end_time,
		];
		update_post_meta( $post_id, '_raw_data', wp_json_encode( $raw_data ) );

		// Run contact matching
		$matches = \RONDO_Calendar_Matcher::match_attendees( $user_id, $attendees );
		update_post_meta( $post_id, '_matched_people', wp_json_encode( $matches ) );

		return [
			'post_id' => $post_id,
			'action'  => $action,
		];
	}

	/**
	 * Parse VEVENT attendees
	 *
	 * @param \Sabre\VObject\Component $vevent VEVENT component
	 * @return array Array of ['email' => string, 'name' => string, 'status' => string]
	 */
	private static function parse_attendees( $vevent ): array {
		$attendees = [];

		if ( ! isset( $vevent->ATTENDEE ) ) {
			return $attendees;
		}

		foreach ( $vevent->ATTENDEE as $attendee ) {
			$email = self::strip_mailto_prefix( (string) $attendee );

			if ( empty( $email ) ) {
				continue;
			}

			// Get CN (common name) parameter
			$name = '';
			if ( isset( $attendee['CN'] ) ) {
				$name = (string) $attendee['CN'];
			}

			// Get PARTSTAT (participation status) parameter
			$status = 'needsAction';
			if ( isset( $attendee['PARTSTAT'] ) ) {
				$partstat = strtoupper( (string) $attendee['PARTSTAT'] );
				switch ( $partstat ) {
					case 'ACCEPTED':
						$status = 'accepted';
						break;
					case 'DECLINED':
						$status = 'declined';
						break;
					case 'TENTATIVE':
						$status = 'tentative';
						break;
					default:
						$status = 'needsAction';
				}
			}

			$attendees[] = [
				'email'  => sanitize_email( $email ),
				'name'   => sanitize_text_field( $name ),
				'status' => $status,
			];
		}

		return $attendees;
	}

	/**
	 * Extract meeting URL from VEVENT
	 *
	 * Looks in X-GOOGLE-CONFERENCE, URL property, and description text.
	 *
	 * @param \Sabre\VObject\Component $vevent      VEVENT component
	 * @param string                   $description Event description
	 * @return string Meeting URL or empty string
	 */
	private static function extract_meeting_url( $vevent, string $description ): string {
		// Check X-GOOGLE-CONFERENCE property (Google Meet links)
		if ( isset( $vevent->{'X-GOOGLE-CONFERENCE'} ) ) {
			return (string) $vevent->{'X-GOOGLE-CONFERENCE'};
		}

		// Check URL property
		if ( isset( $vevent->URL ) ) {
			$url = (string) $vevent->URL;
			if ( self::is_meeting_url( $url ) ) {
				return $url;
			}
		}

		// Check location for meeting URLs
		$location       = isset( $vevent->LOCATION ) ? (string) $vevent->LOCATION : '';
		$text_to_search = $description . ' ' . $location;

		// Try each meeting URL pattern
		$patterns = self::get_meeting_url_patterns();
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text_to_search, $matches ) ) {
				return $matches[0];
			}
		}

		return '';
	}

	/**
	 * Check if URL is a known meeting service URL
	 *
	 * @param string $url URL to check
	 * @return bool True if it's a meeting URL
	 */
	private static function is_meeting_url( string $url ): bool {
		foreach ( self::MEETING_DOMAINS as $domain ) {
			if ( strpos( $url, $domain ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse event time from DTSTART/DTEND property
	 *
	 * @param \Sabre\VObject\Property|null $dt Date/time property
	 * @return string MySQL datetime format (Y-m-d H:i:s)
	 */
	private static function parse_event_time( $dt ): string {
		if ( ! $dt ) {
			return current_time( 'mysql' );
		}

		try {
			// Get DateTime from VObject property
			$datetime = $dt->getDateTime();

			if ( $datetime ) {
				// Convert to site timezone
				$datetime->setTimezone( wp_timezone() );
				return $datetime->format( 'Y-m-d H:i:s' );
			}

			// For DATE (not DATETIME) values - all-day events
			$value = (string) $dt;
			if ( strlen( $value ) === 8 ) {
				// YYYYMMDD format
				return substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 ) . ' 00:00:00';
			}

			return current_time( 'mysql' );
		} catch ( \Exception $e ) {
			return current_time( 'mysql' );
		}
	}

	/**
	 * Check if event is all-day
	 *
	 * @param \Sabre\VObject\Component $vevent VEVENT component
	 * @return bool True if all-day event
	 */
	private static function is_all_day( $vevent ): bool {
		if ( ! isset( $vevent->DTSTART ) ) {
			return false;
		}

		// Check if DTSTART has VALUE=DATE parameter (indicates all-day)
		$dtstart = $vevent->DTSTART;

		if ( isset( $dtstart['VALUE'] ) && strtoupper( (string) $dtstart['VALUE'] ) === 'DATE' ) {
			return true;
		}

		// Also check if the value looks like a date-only value (8 characters)
		$value = (string) $dtstart;
		return strlen( $value ) === 8 && ctype_digit( $value );
	}

	/**
	 * Create a Sabre DAV client with standard configuration
	 *
	 * @param string $url      Base URL for the DAV server
	 * @param string $username Username for authentication
	 * @param string $password Password for authentication
	 * @return Client Configured DAV client instance
	 */
	private static function create_dav_client( string $url, string $username, string $password ): Client {
		return new Client(
			[
				'baseUri'  => $url,
				'userName' => $username,
				'password' => $password,
			]
		);
	}

	/**
	 * Register CalDAV namespaces on a SimpleXMLElement
	 *
	 * @param \SimpleXMLElement $xml XML element to register namespaces on
	 * @return void
	 */
	private static function register_caldav_namespaces( \SimpleXMLElement $xml ): void {
		$xml->registerXPathNamespace( 'd', 'DAV:' );
		$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );
		$xml->registerXPathNamespace( 'ic', 'http://apple.com/ns/ical/' );
	}

	/**
	 * Strip mailto: prefix from email address
	 *
	 * @param string $address Email address potentially with mailto: prefix
	 * @return string Email address without mailto: prefix
	 */
	private static function strip_mailto_prefix( string $address ): string {
		if ( strpos( $address, 'mailto:' ) === 0 ) {
			return substr( $address, 7 );
		}
		return $address;
	}

	/**
	 * Check if HTTP status code indicates success
	 *
	 * @param int $status_code HTTP status code
	 * @return bool True if status code is in the 2xx range
	 */
	private static function is_http_success( int $status_code ): bool {
		return $status_code >= 200 && $status_code < 300;
	}

	/**
	 * Get regex patterns for detecting meeting URLs
	 *
	 * Patterns are derived from MEETING_DOMAINS to keep a single source of truth
	 * for supported meeting providers.
	 *
	 * @return array Array of regex patterns
	 */
	private static function get_meeting_url_patterns(): array {
		$patterns = [];

		foreach ( self::MEETING_DOMAINS as $domain ) {
			$escaped_domain = preg_quote( $domain, '/' );

			// Match https URLs with optional subdomain, the meeting domain, and any
			// trailing path/query up to whitespace or a quote character.
			$patterns[] = '/https:\/\/[\w\.-]*' . $escaped_domain . '\/[^\s"]*/i';
		}

		return $patterns;
	}
}
