<?php
/**
 * Google Contacts API Import Class
 *
 * Imports contacts from Google Contacts via People API into Rondo.
 * Matches existing contacts by email only, fills gaps without overwriting.
 */

namespace Rondo\Import;

use Rondo\Calendar\GoogleOAuth;
use Rondo\Collaboration\CommentTypes;
use Rondo\Contacts\GoogleContactsConnection;
use Google\Service\PeopleService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleContactsAPI {

	/**
	 * WordPress user ID performing the import
	 */
	private int $user_id;

	/**
	 * Import statistics
	 */
	private array $stats = [
		'contacts_imported' => 0,
		'contacts_updated'  => 0,
		'contacts_skipped'  => 0,
		'contacts_no_email' => 0,
		'contacts_unlinked' => 0,
		'teams_created' => 0,
		'dates_created'     => 0,
		'photos_imported'   => 0,
		'errors'            => [],
	];

	/**
	 * Team name to ID mapping cache
	 */
	private array $team_map = [];

	/**
	 * Google People Service instance
	 */
	private ?PeopleService $service = null;

	/**
	 * Constructor
	 *
	 * @param int $user_id WordPress user ID performing the import.
	 */
	public function __construct( int $user_id ) {
		$this->user_id = $user_id;

		// Increase limits for large imports
		@set_time_limit( 600 );
		wp_raise_memory_limit( 'admin' );
	}

	/**
	 * Import all contacts from Google
	 *
	 * @return array Import statistics.
	 * @throws \Exception On API or authentication errors.
	 */
	public function import_all(): array {
		$page_token = null;
		$sync_token = null;

		do {
			$response    = $this->fetch_contacts( $page_token, true );
			$connections = $response->getConnections() ?: [];

			foreach ( $connections as $person ) {
				$this->process_contact( $person );
			}

			$page_token = $response->getNextPageToken();

			// Capture sync token from final response (only available on last page)
			if ( ! $page_token && $response->getNextSyncToken() ) {
				$sync_token = $response->getNextSyncToken();
			}
		} while ( $page_token );

		// Store sync token for future delta syncs
		$connection_updates = [
			'last_sync'     => current_time( 'c' ),
			'contact_count' => $this->stats['contacts_imported'] + $this->stats['contacts_updated'],
			'last_error'    => null,
		];

		if ( $sync_token ) {
			$connection_updates['sync_token'] = $sync_token;
		}

		GoogleContactsConnection::update_connection( $this->user_id, $connection_updates );

		// Clear pending import flag
		GoogleContactsConnection::set_pending_import( $this->user_id, false );

		return $this->stats;
	}

	/**
	 * Import only changed contacts from Google using syncToken
	 *
	 * Uses Google's incremental sync mechanism to only fetch contacts that have
	 * changed since the last sync. If no syncToken exists or token is expired,
	 * falls back to full import.
	 *
	 * @return array Import statistics with keys: contacts_imported, contacts_updated, contacts_unlinked, etc.
	 * @throws \Exception On API or authentication errors.
	 */
	public function import_delta(): array {
		// Get stored syncToken
		$connection = GoogleContactsConnection::get_connection( $this->user_id );
		$sync_token = $connection['sync_token'] ?? null;

		// No syncToken - fall back to full import
		if ( empty( $sync_token ) ) {
			return $this->import_all();
		}

		try {
			$new_sync_token = null;
			$page_token     = null;

			do {
				$response    = $this->fetch_contacts_delta( $sync_token, $page_token );
				$connections = $response->getConnections() ?: [];

				foreach ( $connections as $person ) {
					// Check if contact was deleted in Google
					$metadata = $person->getMetadata();
					if ( $metadata && $metadata->getDeleted() ) {
						$this->unlink_contact( $person->getResourceName() );
						continue;
					}

					// Process contact normally
					$this->process_contact( $person );
				}

				$page_token = $response->getNextPageToken();

				// Capture sync token from final response
				if ( ! $page_token && $response->getNextSyncToken() ) {
					$new_sync_token = $response->getNextSyncToken();
				}
			} while ( $page_token );

			// Update connection with new sync token
			$connection_updates = [
				'last_sync'  => current_time( 'c' ),
				'last_error' => null,
			];

			if ( $new_sync_token ) {
				$connection_updates['sync_token'] = $new_sync_token;
			}

			GoogleContactsConnection::update_connection( $this->user_id, $connection_updates );

			return $this->stats;

		} catch ( \Google\Service\Exception $e ) {
			// Handle expired syncToken (410 Gone error)
			if ( $e->getCode() === 410 ) {
				// Clear expired token
				GoogleContactsConnection::update_connection(
					$this->user_id,
					[ 'sync_token' => null ]
				);

				// Fall back to full sync
				return $this->import_all();
			}

			throw $e;
		}
	}

	/**
	 * Fetch contacts delta from Google API using syncToken
	 *
	 * @param string      $sync_token Sync token from previous sync.
	 * @param string|null $page_token Pagination token.
	 * @return object ListConnectionsResponse.
	 * @throws \Exception On API error.
	 */
	private function fetch_contacts_delta( string $sync_token, ?string $page_token = null ): object {
		$service = $this->get_people_service();

		$params = [
			'personFields' => 'names,emailAddresses,phoneNumbers,addresses,organizations,birthdays,photos,urls,metadata',
			'syncToken'    => $sync_token,
			'pageSize'     => 100,
		];

		if ( $page_token ) {
			$params['pageToken'] = $page_token;
		}

		return $service->people_connections->listPeopleConnections( 'people/me', $params );
	}

	/**
	 * Unlink a contact that was deleted in Google
	 *
	 * Removes Google metadata from the Rondo person but preserves all other data.
	 *
	 * @param string $resource_name Google resource name (people/c123...).
	 */
	private function unlink_contact( string $resource_name ): void {
		// Find person by Google contact ID
		$posts = get_posts(
			[
				'post_type'   => 'person',
				'author'      => $this->user_id,
				'meta_key'    => '_google_contact_id',
				'meta_value'  => $resource_name,
				'numberposts' => 1,
			]
		);

		if ( empty( $posts ) ) {
			return;
		}

		$post_id = $posts[0]->ID;

		// Remove Google-related meta but preserve Rondo data
		delete_post_meta( $post_id, '_google_contact_id' );
		delete_post_meta( $post_id, '_google_etag' );
		delete_post_meta( $post_id, '_google_last_import' );
		delete_post_meta( $post_id, '_google_last_export' );

		++$this->stats['contacts_unlinked'];

		// Log for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'RONDO_Google_Contacts: Unlinked contact %d (was %s) - deleted in Google',
					$post_id,
					$resource_name
				)
			);
		}
	}

	/**
	 * Get Google People Service instance
	 *
	 * Handles token refresh if needed.
	 *
	 * @return PeopleService Configured service instance.
	 * @throws \Exception On authentication failure.
	 */
	private function get_people_service(): PeopleService {
		if ( $this->service ) {
			return $this->service;
		}

		$credentials = GoogleContactsConnection::get_decrypted_credentials( $this->user_id );
		if ( ! $credentials ) {
			throw new \Exception( 'No Google Contacts credentials found' );
		}

		$client = GoogleOAuth::get_contacts_client( false, false );
		if ( ! $client ) {
			throw new \Exception( 'Google OAuth is not configured' );
		}

		$client->setAccessToken( $credentials );

		// Refresh token if expired
		if ( $client->isAccessTokenExpired() ) {
			$refresh_token = $client->getRefreshToken();
			if ( ! $refresh_token ) {
				throw new \Exception( 'Access token expired and no refresh token available' );
			}
			$client->fetchAccessTokenWithRefreshToken( $refresh_token );
			GoogleContactsConnection::update_credentials( $this->user_id, $client->getAccessToken() );
		}

		$this->service = new PeopleService( $client );
		return $this->service;
	}

	/**
	 * Fetch contacts from Google API
	 *
	 * @param string|null $page_token         Pagination token.
	 * @param bool        $request_sync_token Whether to request a sync token for future delta syncs.
	 * @return object ListConnectionsResponse.
	 * @throws \Exception On API error.
	 */
	private function fetch_contacts( ?string $page_token = null, bool $request_sync_token = false ): object {
		$service = $this->get_people_service();

		$params = [
			'personFields' => 'names,emailAddresses,phoneNumbers,addresses,organizations,birthdays,photos,urls,metadata',
			'pageSize'     => 100,
		];

		if ( $request_sync_token ) {
			$params['requestSyncToken'] = true;
		}

		if ( $page_token ) {
			$params['pageToken'] = $page_token;
		}

		return $service->people_connections->listPeopleConnections( 'people/me', $params );
	}

	/**
	 * Process a single contact
	 *
	 * @param object $person Google Person object.
	 */
	private function process_contact( object $person ): void {
		// Skip contacts without email
		if ( $this->should_skip_contact( $person ) ) {
			++$this->stats['contacts_no_email'];
			return;
		}

		$email       = $this->get_primary_email( $person );
		$existing_id = $this->find_by_email( $email );

		if ( $existing_id ) {
			// Detect and log conflicts before processing (for existing contacts).
			$conflicts = $this->detect_field_conflicts( $existing_id, $person );
			if ( ! empty( $conflicts ) ) {
				$this->log_conflict_resolution( $existing_id, $conflicts );
			}

			// Link and fill gaps for existing person
			$post_id = $existing_id;
			++$this->stats['contacts_updated'];
		} else {
			// Create new person
			$names      = $person->getNames() ?: [];
			$name       = $names[0] ?? null;
			$first_name = $name ? $name->getGivenName() : '';
			$infix      = $name ? ( $name->getMiddleName() ?: '' ) : '';
			$last_name  = $name ? $name->getFamilyName() : '';

			// Fallback to display name if given/family empty
			if ( empty( $first_name ) && empty( $last_name ) && $name ) {
				$display_name = $name->getDisplayName();
				if ( $display_name ) {
					$name_parts = explode( ' ', trim( $display_name ), 2 );
					$first_name = $name_parts[0] ?? '';
					$last_name  = $name_parts[1] ?? '';
				}
			}

			$post_id = wp_insert_post(
				[
					'post_type'   => 'person',
					'post_status' => 'publish',
					'post_title'  => implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) ),
					'post_author' => $this->user_id,
				]
			);

			if ( is_wp_error( $post_id ) ) {
				$this->stats['errors'][] = 'Failed to create: ' . ( $name ? $name->getDisplayName() : 'Unknown' );
				return;
			}
			++$this->stats['contacts_imported'];
		}

		// Store Google IDs
		$this->store_google_ids( $post_id, $person->getResourceName(), $person->getEtag() );

		// Import fields (fill gaps only)
		$this->import_names( $post_id, $person );
		$this->import_contact_info( $post_id, $person );
		$this->import_addresses( $post_id, $person );
		$this->import_work_history( $post_id, $person );

		$full_name = get_the_title( $post_id );
		$this->import_birthday( $post_id, $person, $full_name );
		$this->import_photo( $post_id, $person, $full_name );
	}

	/**
	 * Check if contact should be skipped
	 *
	 * Contacts without email addresses are skipped.
	 *
	 * @param object $person Google Person object.
	 * @return bool True if should skip.
	 */
	private function should_skip_contact( object $person ): bool {
		$emails = $person->getEmailAddresses() ?: [];
		return empty( $emails );
	}

	/**
	 * Get primary email address
	 *
	 * @param object $person Google Person object.
	 * @return string|null Primary email or null.
	 */
	private function get_primary_email( object $person ): ?string {
		$emails = $person->getEmailAddresses() ?: [];
		if ( empty( $emails ) ) {
			return null;
		}

		// Find primary email
		foreach ( $emails as $email ) {
			$metadata = $email->getMetadata();
			if ( $metadata && $metadata->getPrimary() ) {
				return strtolower( $email->getValue() );
			}
		}

		// Fallback to first email
		return strtolower( $emails[0]->getValue() );
	}

	/**
	 * Find existing person by email
	 *
	 * Searches ACF contact_info repeater for matching email.
	 *
	 * @param string $email Email to search for.
	 * @return int|null Post ID or null if not found.
	 */
	private function find_by_email( string $email ): ?int {
		global $wpdb;

		$accessible_ids = $this->get_user_accessible_person_ids();
		if ( empty( $accessible_ids ) ) {
			return null;
		}

		$id_list = implode( ',', array_map( 'intval', $accessible_ids ) );

		// ACF stores repeater data as: contact_info_0_contact_type, contact_info_0_contact_value
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list is sanitized via intval
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id
				INNER JOIN {$wpdb->postmeta} pm_value ON p.ID = pm_value.post_id
				WHERE p.post_type = 'person'
				AND p.post_status = 'publish'
				AND p.ID IN ({$id_list})
				AND pm_type.meta_key LIKE 'contact_info_%%_contact_type'
				AND pm_type.meta_value = 'email'
				AND pm_value.meta_key = REPLACE(pm_type.meta_key, '_contact_type', '_contact_value')
				AND LOWER(pm_value.meta_value) = LOWER(%s)
				LIMIT 1",
				$email
			)
		);

		return $result ? (int) $result : null;
	}

	/**
	 * Get person IDs accessible by the current user
	 *
	 * @return array Array of post IDs.
	 */
	private function get_user_accessible_person_ids(): array {
		global $wpdb;

		// Admins get all
		if ( user_can( $this->user_id, 'manage_options' ) ) {
			return $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'person'
				AND post_status = 'publish'"
			);
		}

		// Regular users get only their own
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'person'
				AND post_status = 'publish'
				AND post_author = %d",
				$this->user_id
			)
		);
	}

	/**
	 * Import name fields (fill gaps only)
	 *
	 * @param int    $post_id Post ID.
	 * @param object $person  Google Person object.
	 */
	private function import_names( int $post_id, object $person ): void {
		$names = $person->getNames() ?: [];
		$name  = $names[0] ?? null;
		if ( ! $name ) {
			return;
		}

		$existing_first = get_field( 'first_name', $post_id );
		$existing_infix = get_field( 'infix', $post_id );
		$existing_last  = get_field( 'last_name', $post_id );

		$given_name  = $name->getGivenName();
		$middle_name = $name->getMiddleName() ?: '';
		$family_name = $name->getFamilyName();

		// Fallback to display name if given/family empty
		if ( empty( $given_name ) && empty( $family_name ) ) {
			$display_name = $name->getDisplayName();
			if ( $display_name ) {
				$name_parts  = explode( ' ', trim( $display_name ), 2 );
				$given_name  = $name_parts[0] ?? '';
				$family_name = $name_parts[1] ?? '';
			}
		}

		if ( empty( $existing_first ) && $given_name ) {
			update_field( 'first_name', $given_name, $post_id );
		}
		if ( empty( $existing_infix ) && $middle_name ) {
			update_field( 'infix', $middle_name, $post_id );
		}
		if ( empty( $existing_last ) && $family_name ) {
			update_field( 'last_name', $family_name, $post_id );
		}
	}

	/**
	 * Import contact info (emails, phones, URLs) - fill gaps only
	 *
	 * @param int    $post_id Post ID.
	 * @param object $person  Google Person object.
	 */
	private function import_contact_info( int $post_id, object $person ): void {
		$existing = get_field( 'contact_info', $post_id ) ?: [];
		$added    = false;

		// Build set of existing values for quick lookup
		$existing_values = [];
		foreach ( $existing as $info ) {
			$existing_values[ strtolower( $info['contact_value'] ) ] = true;
		}

		// Import emails
		$emails = $person->getEmailAddresses() ?: [];
		foreach ( $emails as $email ) {
			$value = $email->getValue();
			if ( empty( $value ) || isset( $existing_values[ strtolower( $value ) ] ) ) {
				continue;
			}
			$existing[]                           = [
				'contact_type'  => 'email',
				'contact_label' => $this->format_label( $email->getType() ),
				'contact_value' => $value,
			];
			$existing_values[ strtolower( $value ) ] = true;
			$added                                = true;
		}

		// Import phones
		$phones = $person->getPhoneNumbers() ?: [];
		foreach ( $phones as $phone ) {
			$value = $phone->getCanonicalForm() ?: $phone->getValue();
			if ( empty( $value ) || isset( $existing_values[ strtolower( $value ) ] ) ) {
				continue;
			}
			$existing[]                           = [
				'contact_type'  => $this->map_phone_type( $phone->getType() ),
				'contact_label' => $this->format_label( $phone->getType() ),
				'contact_value' => $value,
			];
			$existing_values[ strtolower( $value ) ] = true;
			$added                                = true;
		}

		// Import URLs
		$urls = $person->getUrls() ?: [];
		foreach ( $urls as $url ) {
			$value = $url->getValue();
			if ( empty( $value ) || isset( $existing_values[ strtolower( $value ) ] ) ) {
				continue;
			}
			$existing[]                           = [
				'contact_type'  => $this->detect_url_type( $value ),
				'contact_label' => $this->format_label( $url->getType() ),
				'contact_value' => $value,
			];
			$existing_values[ strtolower( $value ) ] = true;
			$added                                = true;
		}

		if ( $added ) {
			update_field( 'contact_info', $existing, $post_id );
		}
	}

	/**
	 * Import addresses - fill gaps only
	 *
	 * @param int    $post_id Post ID.
	 * @param object $person  Google Person object.
	 */
	private function import_addresses( int $post_id, object $person ): void {
		$existing = get_field( 'addresses', $post_id ) ?: [];
		$added    = false;

		// Build set of existing streets for duplicate detection
		$existing_streets = [];
		foreach ( $existing as $addr ) {
			if ( ! empty( $addr['street'] ) ) {
				$existing_streets[ strtolower( $addr['street'] ) ] = true;
			}
		}

		$addresses = $person->getAddresses() ?: [];
		foreach ( $addresses as $address ) {
			// Combine street and extended address
			$street = trim( ( $address->getStreetAddress() ?? '' ) . ' ' . ( $address->getExtendedAddress() ?? '' ) );

			// Skip if street is already present
			if ( ! empty( $street ) && isset( $existing_streets[ strtolower( $street ) ] ) ) {
				continue;
			}

			// Skip completely empty addresses
			if ( empty( $street ) && empty( $address->getCity() ) && empty( $address->getRegion() ) &&
				empty( $address->getPostalCode() ) && empty( $address->getCountry() ) ) {
				continue;
			}

			$existing[] = [
				'address_label' => $this->format_label( $address->getType() ),
				'street'        => $street,
				'postal_code'   => $address->getPostalCode() ?? '',
				'city'          => $address->getCity() ?? '',
				'state'         => $address->getRegion() ?? '',
				'country'       => $address->getCountry() ?? '',
			];

			if ( ! empty( $street ) ) {
				$existing_streets[ strtolower( $street ) ] = true;
			}
			$added = true;
		}

		if ( $added ) {
			update_field( 'addresses', $existing, $post_id );
		}
	}

	/**
	 * Import work history - fill gaps only
	 *
	 * @param int    $post_id Post ID.
	 * @param object $person  Google Person object.
	 */
	private function import_work_history( int $post_id, object $person ): void {
		$existing = get_field( 'work_history', $post_id ) ?: [];
		$added    = false;

		// Build set of existing company IDs
		$existing_team_ids = [];
		foreach ( $existing as $job ) {
			if ( ! empty( $job['team'] ) ) {
				$team_id                          = is_object( $job['team'] ) ? $job['team']->ID : (int) $job['team'];
				$existing_team_ids[ $team_id ] = true;
			}
		}

		$organizations = $person->getOrganizations() ?: [];
		foreach ( $organizations as $org ) {
			$org_name = $org->getName();
			if ( empty( $org_name ) ) {
				continue;
			}

			$team_id = $this->get_or_create_company( $org_name );
			if ( ! $team_id || isset( $existing_team_ids[ $team_id ] ) ) {
				continue;
			}

			// Parse dates
			$start_date = '';
			$end_date   = '';
			$start      = $org->getStartDate();
			$end        = $org->getEndDate();

			if ( $start && $start->getYear() ) {
				$start_date = sprintf(
					'%04d-%02d-%02d',
					$start->getYear(),
					$start->getMonth() ?: 1,
					$start->getDay() ?: 1
				);
			}

			if ( $end && $end->getYear() ) {
				$end_date = sprintf(
					'%04d-%02d-%02d',
					$end->getYear(),
					$end->getMonth() ?: 1,
					$end->getDay() ?: 1
				);
			}

			$existing[] = [
				'team'    => $team_id,
				'job_title'  => $org->getTitle() ?? '',
				'is_current' => (bool) $org->getCurrent(),
				'start_date' => $start_date,
				'end_date'   => $end_date,
			];

			$existing_team_ids[ $team_id ] = true;
			$added                               = true;
		}

		if ( $added ) {
			update_field( 'work_history', $existing, $post_id );
		}
	}

	/**
	 * Import birthday directly on person record
	 *
	 * Stores birthdate in the person's ACF birthdate field.
	 * Skip if year is unknown (0 or not provided) since we only store full dates.
	 *
	 * @param int    $post_id   Post ID.
	 * @param object $person    Google Person object.
	 * @param string $full_name Person's full name (unused, kept for backward compatibility).
	 */
	private function import_birthday( int $post_id, object $person, string $full_name ): void {
		$birthdays = $person->getBirthdays() ?: [];
		if ( empty( $birthdays ) ) {
			return;
		}

		$birthday = $birthdays[0];
		$date     = $birthday->getDate();
		if ( ! $date ) {
			return;
		}

		$month = $date->getMonth();
		$day   = $date->getDay();
		$year  = $date->getYear();

		// Skip if no month/day or year is unknown (0 or null)
		if ( ! $month || ! $day || ! $year || $year === 0 ) {
			return;
		}

		// Skip if person already has a birthdate
		$existing_birthdate = get_field( 'birthdate', $post_id );
		if ( ! empty( $existing_birthdate ) ) {
			return;
		}

		// Format date and store directly on person
		$date_formatted = sprintf( '%04d-%02d-%02d', $year, $month, $day );
		update_field( 'birthdate', $date_formatted, $post_id );

		++$this->stats['dates_created'];
	}

	/**
	 * Import photo as featured image
	 *
	 * @param int    $post_id   Post ID.
	 * @param object $person    Google Person object.
	 * @param string $full_name Person's full name for filename.
	 */
	private function import_photo( int $post_id, object $person, string $full_name ): void {
		// Skip if person already has featured image
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		$photos = $person->getPhotos() ?: [];
		if ( empty( $photos ) ) {
			return;
		}

		// Find primary photo or use first
		$photo = null;
		foreach ( $photos as $p ) {
			$metadata = $p->getMetadata();
			if ( $metadata && $metadata->getPrimary() ) {
				$photo = $p;
				break;
			}
		}
		if ( ! $photo ) {
			$photo = $photos[0];
		}

		// Skip default/placeholder photos
		if ( $photo->getDefault() ) {
			return;
		}

		$url = $photo->getUrl();
		if ( empty( $url ) ) {
			return;
		}

		// Append size parameter for better quality
		$url = preg_replace( '/[?&]sz=\d+/', '', $url );
		$url .= ( strpos( $url, '?' ) !== false ? '&' : '?' ) . 'sz=400';

		$attachment_id = $this->sideload_image( $url, $post_id, $full_name );
		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
			++$this->stats['photos_imported'];
		}
	}

	/**
	 * Sideload image from URL
	 *
	 * @param string $url         Image URL.
	 * @param int    $post_id     Post ID to attach to.
	 * @param string $description Image description.
	 * @return int|null Attachment ID or null on failure.
	 */
	private function sideload_image( string $url, int $post_id, string $description ): ?int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return null;
		}

		$filename   = sanitize_title( strtolower( $description ) ) . '.jpg';
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, $post_id, $description );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return null;
		}

		return $attachment_id;
	}

	/**
	 * Get or create a team by name
	 *
	 * @param string $name Team name.
	 * @return int Team post ID.
	 */
	private function get_or_create_company( string $name ): int {
		if ( isset( $this->team_map[ $name ] ) ) {
			return $this->team_map[ $name ];
		}

		// Check if team exists
		$existing = get_page_by_title( $name, OBJECT, 'team' );
		if ( $existing ) {
			$this->team_map[ $name ] = $existing->ID;
			return $existing->ID;
		}

		// Create new company
		$post_id = wp_insert_post(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => $name,
				'post_author' => $this->user_id,
			]
		);

		if ( ! is_wp_error( $post_id ) ) {
			$this->team_map[ $name ] = $post_id;
			++$this->stats['teams_created'];
			return $post_id;
		}

		return 0;
	}

	/**
	 * Store Google contact IDs as post meta
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $resource_name Google resource name (people/c123...).
	 * @param string $etag          Google etag for change detection.
	 */
	private function store_google_ids( int $post_id, string $resource_name, string $etag ): void {
		update_post_meta( $post_id, '_google_contact_id', $resource_name );
		update_post_meta( $post_id, '_google_etag', $etag );
		update_post_meta( $post_id, '_google_last_import', current_time( 'c' ) );

		// Store field snapshot for future conflict detection.
		$this->store_field_snapshot( $post_id );
	}

	/**
	 * Get current field values for conflict detection snapshot
	 *
	 * @param int $post_id Post ID.
	 * @return array Field values keyed by field name.
	 */
	private function get_field_snapshot( int $post_id ): array {
		$snapshot = [
			'first_name'   => get_field( 'first_name', $post_id ) ?: '',
			'last_name'    => get_field( 'last_name', $post_id ) ?: '',
			'email'        => '',
			'phone'        => '',
			'organization' => '',
		];

		// Get primary email from contact_info repeater (first email type entry).
		$contact_info = get_field( 'contact_info', $post_id ) ?: [];
		foreach ( $contact_info as $info ) {
			if ( 'email' === ( $info['contact_type'] ?? '' ) && ! empty( $info['contact_value'] ) ) {
				$snapshot['email'] = strtolower( $info['contact_value'] );
				break;
			}
		}

		// Get primary phone from contact_info repeater (first phone/mobile type entry).
		foreach ( $contact_info as $info ) {
			$type = $info['contact_type'] ?? '';
			if ( in_array( $type, [ 'phone', 'mobile' ], true ) && ! empty( $info['contact_value'] ) ) {
				$snapshot['phone'] = $info['contact_value'];
				break;
			}
		}

		// Get organization from work_history (first entry with is_current=true or first entry).
		$work_history = get_field( 'work_history', $post_id ) ?: [];
		foreach ( $work_history as $job ) {
			if ( ! empty( $job['is_current'] ) && ! empty( $job['team'] ) ) {
				$team_id             = is_object( $job['team'] ) ? $job['team']->ID : (int) $job['team'];
				$snapshot['organization'] = get_the_title( $team_id );
				break;
			}
		}
		// Fallback to first entry if no current job found.
		if ( empty( $snapshot['organization'] ) && ! empty( $work_history[0]['team'] ) ) {
			$team_id             = is_object( $work_history[0]['team'] ) ? $work_history[0]['team']->ID : (int) $work_history[0]['team'];
			$snapshot['organization'] = get_the_title( $team_id );
		}

		return $snapshot;
	}

	/**
	 * Store field snapshot for future conflict detection
	 *
	 * @param int $post_id Post ID.
	 */
	private function store_field_snapshot( int $post_id ): void {
		$snapshot              = $this->get_field_snapshot( $post_id );
		$snapshot['synced_at'] = current_time( 'c' );
		update_post_meta( $post_id, '_google_synced_fields', $snapshot );
	}

	/**
	 * Detect field-level conflicts between Google and Rondo
	 *
	 * Compares Google values, Rondo values, and last-synced snapshot to determine
	 * which fields were modified in both systems since last sync.
	 *
	 * @param int    $post_id      Post ID.
	 * @param object $google_person Google Person object.
	 * @return array Array of conflicts, each with field, google_value, rondo_value, kept_value.
	 */
	private function detect_field_conflicts( int $post_id, object $google_person ): array {
		$conflicts = [];

		// Get stored snapshot from last sync.
		$snapshot = get_post_meta( $post_id, '_google_synced_fields', true ) ?: [];

		// No snapshot means this is first sync, no conflicts possible.
		if ( empty( $snapshot ) ) {
			return $conflicts;
		}

		// Get current Rondo values.
		$rondo_values = $this->get_field_snapshot( $post_id );

		// Extract Google values from person object.
		$google_values = $this->extract_google_field_values( $google_person );

		// Fields to check for conflicts.
		$fields = [ 'first_name', 'last_name', 'email', 'phone', 'organization' ];

		foreach ( $fields as $field ) {
			$snapshot_value = $snapshot[ $field ] ?? '';
			$rondo_value   = $rondo_values[ $field ] ?? '';
			$google_value   = $google_values[ $field ] ?? '';

			// Conflict: both systems changed this field since last sync.
			// (Google value differs from snapshot AND Rondo value differs from snapshot).
			if ( $google_value !== $snapshot_value && $rondo_value !== $snapshot_value ) {
				// Only log if values are actually different (not just both changed to same value).
				if ( $google_value !== $rondo_value ) {
					$conflicts[] = [
						'field'        => $field,
						'google_value' => $google_value,
						'rondo_value' => $rondo_value,
						'kept_value'   => $rondo_value, // Rondo wins.
					];
				}
			}
		}

		return $conflicts;
	}

	/**
	 * Extract field values from Google Person object
	 *
	 * @param object $google_person Google Person object.
	 * @return array Field values keyed by field name.
	 */
	private function extract_google_field_values( object $google_person ): array {
		$values = [
			'first_name'   => '',
			'last_name'    => '',
			'email'        => '',
			'phone'        => '',
			'organization' => '',
		];

		// Names.
		$names = $google_person->getNames() ?: [];
		$name  = $names[0] ?? null;
		if ( $name ) {
			$values['first_name'] = $name->getGivenName() ?: '';
			$values['infix']      = $name->getMiddleName() ?: '';
			$values['last_name']  = $name->getFamilyName() ?: '';
		}

		// Primary email.
		$emails = $google_person->getEmailAddresses() ?: [];
		foreach ( $emails as $email ) {
			$metadata = $email->getMetadata();
			if ( $metadata && $metadata->getPrimary() ) {
				$values['email'] = strtolower( $email->getValue() );
				break;
			}
		}
		if ( empty( $values['email'] ) && ! empty( $emails ) ) {
			$values['email'] = strtolower( $emails[0]->getValue() );
		}

		// Primary phone.
		$phones = $google_person->getPhoneNumbers() ?: [];
		foreach ( $phones as $phone ) {
			$metadata = $phone->getMetadata();
			if ( $metadata && $metadata->getPrimary() ) {
				$values['phone'] = $phone->getCanonicalForm() ?: $phone->getValue();
				break;
			}
		}
		if ( empty( $values['phone'] ) && ! empty( $phones ) ) {
			$values['phone'] = $phones[0]->getCanonicalForm() ?: $phones[0]->getValue();
		}

		// Organization (first one).
		$organizations = $google_person->getOrganizations() ?: [];
		foreach ( $organizations as $org ) {
			if ( $org->getName() ) {
				$values['organization'] = $org->getName();
				break;
			}
		}

		return $values;
	}

	/**
	 * Log conflict resolution as activity entry
	 *
	 * Creates an activity entry on the person showing which fields had conflicts
	 * and that Rondo values were kept.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $conflicts Array of conflicts from detect_field_conflicts().
	 */
	private function log_conflict_resolution( int $post_id, array $conflicts ): void {
		if ( empty( $conflicts ) ) {
			return;
		}

		// Format conflict details as bullet list.
		$lines = [ 'Sync conflict resolved (Rondo wins):' ];
		foreach ( $conflicts as $conflict ) {
			$field_label  = ucfirst( str_replace( '_', ' ', $conflict['field'] ) );
			$google_value = $conflict['google_value'] ?: '(empty)';
			$rondo_value = $conflict['rondo_value'] ?: '(empty)';
			$lines[]      = sprintf( '- %s: Google had "%s", kept "%s"', $field_label, $google_value, $rondo_value );
		}
		$content = implode( "\n", $lines );

		// Create activity comment.
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $post_id,
				'comment_content'  => $content,
				'comment_type'     => CommentTypes::TYPE_ACTIVITY,
				'user_id'          => $this->user_id,
				'comment_approved' => 1,
			]
		);

		if ( $comment_id ) {
			update_comment_meta( $comment_id, 'activity_type', 'sync_conflict' );
			update_comment_meta( $comment_id, 'activity_date', current_time( 'Y-m-d' ) );
		}
	}

	/**
	 * Map Google phone type to Rondo contact type
	 *
	 * @param string|null $google_type Google phone type.
	 * @return string 'mobile' or 'phone'.
	 */
	private function map_phone_type( ?string $google_type ): string {
		$mobile_types = [ 'mobile', 'workmobile' ];
		return in_array( strtolower( $google_type ?? '' ), $mobile_types, true ) ? 'mobile' : 'phone';
	}

	/**
	 * Detect URL type from URL content
	 *
	 * @param string $url URL to analyze.
	 * @return string Contact type (linkedin, twitter, facebook, instagram, website).
	 */
	private function detect_url_type( string $url ): string {
		$url_lower = strtolower( $url );

		if ( strpos( $url_lower, 'linkedin.com' ) !== false ) {
			return 'linkedin';
		}
		if ( strpos( $url_lower, 'twitter.com' ) !== false || strpos( $url_lower, 'x.com' ) !== false ) {
			return 'twitter';
		}
		if ( strpos( $url_lower, 'facebook.com' ) !== false ) {
			return 'facebook';
		}
		if ( strpos( $url_lower, 'instagram.com' ) !== false ) {
			return 'instagram';
		}

		return 'website';
	}

	/**
	 * Format label for display
	 *
	 * @param string|null $type Type string from Google.
	 * @return string Formatted label.
	 */
	private function format_label( ?string $type ): string {
		if ( empty( $type ) ) {
			return '';
		}

		$type = strtolower( trim( $type ) );

		// Remove common prefixes
		$type = preg_replace( '/^\*\s*/', '', $type );
		$type = str_replace( [ ':: ', '::: ' ], '', $type );

		// Skip generic labels
		if ( in_array( $type, [ '', 'other', 'custom' ], true ) ) {
			return '';
		}

		return ucfirst( $type );
	}
}
