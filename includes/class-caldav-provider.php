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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Sabre\DAV\Client;
use Sabre\VObject\Reader;

class PRM_CalDAV_Provider {

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
			$client = new Client(
				[
					'baseUri'  => $url,
					'userName' => $username,
					'password' => $password,
				]
			);

			// Check for CalDAV support via OPTIONS request
			$supported = self::check_caldav_support( $client, $url );
			if ( ! $supported ) {
				return [
					'success' => false,
					'error'   => __( 'Server does not support CalDAV. Please check the URL.', 'caelis' ),
				];
			}

			// Discover available calendars
			$calendars = self::discover_calendars( $url, $username, $password );

			if ( empty( $calendars ) ) {
				return [
					'success'   => true,
					'calendars' => [],
					'message'   => __( 'Connected successfully but no calendars found.', 'caelis' ),
				];
			}

			return [
				'success'   => true,
				'calendars' => $calendars,
			];

		} catch ( Exception $e ) {
			$error_message = $e->getMessage();

			// Provide user-friendly error messages for common issues
			if ( strpos( $error_message, '401' ) !== false || strpos( $error_message, 'Unauthorized' ) !== false ) {
				return [
					'success' => false,
					'error'   => __( 'Authentication failed. Check username and password. For iCloud, use an app-specific password.', 'caelis' ),
				];
			}

			if ( strpos( $error_message, '404' ) !== false ) {
				return [
					'success' => false,
					'error'   => __( 'Calendar URL not found. Please verify the CalDAV server address.', 'caelis' ),
				];
			}

			return [
				'success' => false,
				'error'   => sprintf( __( 'Connection failed: %s', 'caelis' ), $error_message ),
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
		} catch ( Exception $e ) {
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
		$client = new Client(
			[
				'baseUri'  => $url,
				'userName' => $username,
				'password' => $password,
			]
		);

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

			if ( $response['statusCode'] < 200 || $response['statusCode'] >= 300 ) {
				return [];
			}

			return self::parse_calendar_response( $response['body'], $url );
		} catch ( Exception $e ) {
			error_log( 'PRM_CalDAV_Provider: Failed to discover calendars: ' . $e->getMessage() );
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
			$xml = new SimpleXMLElement( $xml_body );
			$xml->registerXPathNamespace( 'd', 'DAV:' );
			$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );
			$xml->registerXPathNamespace( 'ic', 'http://apple.com/ns/ical/' );

			// Find all responses
			$responses = $xml->xpath( '//d:response' );

			foreach ( $responses as $response ) {
				// Get href (calendar ID)
				$href_elements = $response->xpath( 'd:href' );
				if ( empty( $href_elements ) ) {
					continue;
				}
				$href = (string) $href_elements[0];

				// Check if this is a calendar resource
				$resourcetype = $response->xpath( 'd:propstat/d:prop/d:resourcetype/c:calendar' );
				if ( empty( $resourcetype ) ) {
					// Also check without namespace in case server uses different format
					$resourcetype = $response->xpath( './/d:resourcetype/*[local-name()="calendar"]' );
					if ( empty( $resourcetype ) ) {
						continue;
					}
				}

				// Get display name
				$displayname_elements = $response->xpath( 'd:propstat/d:prop/d:displayname' );
				$displayname          = ! empty( $displayname_elements ) ? (string) $displayname_elements[0] : basename( $href );

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
		} catch ( Exception $e ) {
			error_log( 'PRM_CalDAV_Provider: Failed to parse calendar response: ' . $e->getMessage() );
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
		// Decrypt credentials
		$credentials = PRM_Credential_Encryption::decrypt( $connection['credentials'] );
		if ( ! $credentials || empty( $credentials['url'] ) || empty( $credentials['username'] ) || empty( $credentials['password'] ) ) {
			throw new Exception( __( 'Invalid CalDAV credentials. Please reconnect your calendar.', 'caelis' ) );
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

		$client = new Client(
			[
				'baseUri'  => $calendar_url,
				'userName' => $username,
				'password' => $password,
			]
		);

		// Calculate date range
		$sync_from_days = isset( $connection['sync_from_days'] ) ? absint( $connection['sync_from_days'] ) : 90;
		$start_date     = new DateTime();
		$start_date->modify( "-{$sync_from_days} days" );

		$end_date = new DateTime();
		$end_date->modify( '+30 days' );

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

			if ( $response['statusCode'] < 200 || $response['statusCode'] >= 300 ) {
				throw new Exception( sprintf( __( 'CalDAV server returned error: %d', 'caelis' ), $response['statusCode'] ) );
			}

			// Parse response and upsert events
			return self::process_calendar_report( $user_id, $connection, $response['body'] );

		} catch ( Exception $e ) {
			error_log( 'PRM_CalDAV_Provider: Sync failed: ' . $e->getMessage() );
			throw new Exception( sprintf( __( 'Sync failed: %s', 'caelis' ), $e->getMessage() ) );
		}
	}

	/**
	 * Process calendar REPORT response and upsert events
	 *
	 * @param int    $user_id    WordPress user ID
	 * @param array  $connection Connection data
	 * @param string $xml_body   Response XML containing calendar data
	 * @return array ['created' => int, 'updated' => int, 'total' => int]
	 */
	private static function process_calendar_report( int $user_id, array $connection, string $xml_body ): array {
		$created = 0;
		$updated = 0;
		$total   = 0;

		try {
			$xml = new SimpleXMLElement( $xml_body );
			$xml->registerXPathNamespace( 'd', 'DAV:' );
			$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );

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
						try {
							$result = self::upsert_event( $user_id, $connection, $vevent );
							++$total;

							if ( $result['action'] === 'created' ) {
								++$created;
							} else {
								++$updated;
							}
						} catch ( Exception $e ) {
							error_log( 'PRM_CalDAV_Provider: Failed to upsert event: ' . $e->getMessage() );
						}
					}
				} catch ( Exception $e ) {
					error_log( 'PRM_CalDAV_Provider: Failed to parse iCalendar: ' . $e->getMessage() );
				}
			}
		} catch ( Exception $e ) {
			error_log( 'PRM_CalDAV_Provider: Failed to parse REPORT response: ' . $e->getMessage() );
		}

		return [
			'created' => $created,
			'updated' => $updated,
			'total'   => $total,
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
			throw new Exception( 'Event has no UID' );
		}

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
			$organizer = (string) $vevent->ORGANIZER;
			// Strip mailto: prefix
			if ( strpos( $organizer, 'mailto:' ) === 0 ) {
				$organizer_email = substr( $organizer, 7 );
			} else {
				$organizer_email = $organizer;
			}
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
		$matches = PRM_Calendar_Matcher::match_attendees( $user_id, $attendees );
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
			$email = (string) $attendee;

			// Strip mailto: prefix
			if ( strpos( $email, 'mailto:' ) === 0 ) {
				$email = substr( $email, 7 );
			}

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

		// Zoom URL pattern
		if ( preg_match( '/https:\/\/[\w.-]*zoom\.us\/j\/[\w\d\-\?=&]+/i', $text_to_search, $matches ) ) {
			return $matches[0];
		}

		// Microsoft Teams URL pattern
		if ( preg_match( '/https:\/\/teams\.microsoft\.com\/l\/meetup-join\/[\w\d\-\%\/\?=&]+/i', $text_to_search, $matches ) ) {
			return $matches[0];
		}

		// Google Meet URL pattern
		if ( preg_match( '/https:\/\/meet\.google\.com\/[\w\-]+/i', $text_to_search, $matches ) ) {
			return $matches[0];
		}

		// Webex URL pattern
		if ( preg_match( '/https:\/\/[\w.-]*webex\.com\/[\w\d\-\/\?=&]+/i', $text_to_search, $matches ) ) {
			return $matches[0];
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
		$meeting_domains = [
			'zoom.us',
			'teams.microsoft.com',
			'meet.google.com',
			'webex.com',
		];

		foreach ( $meeting_domains as $domain ) {
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
		} catch ( Exception $e ) {
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
}
