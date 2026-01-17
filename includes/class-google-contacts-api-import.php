<?php
/**
 * Google Contacts API Import Class
 *
 * Imports contacts from Google Contacts via People API into Caelis.
 * Matches existing contacts by email only, fills gaps without overwriting.
 */

namespace Caelis\Import;

use Caelis\Calendar\GoogleOAuth;
use Caelis\Contacts\GoogleContactsConnection;
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
		'companies_created' => 0,
		'dates_created'     => 0,
		'photos_imported'   => 0,
		'errors'            => [],
	];

	/**
	 * Company name to ID mapping cache
	 */
	private array $company_map = [];

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

		do {
			$response    = $this->fetch_contacts( $page_token );
			$connections = $response->getConnections() ?: [];

			foreach ( $connections as $person ) {
				$this->process_contact( $person );
			}

			$page_token = $response->getNextPageToken();
		} while ( $page_token );

		// Update connection stats
		GoogleContactsConnection::update_connection(
			$this->user_id,
			[
				'last_sync'     => current_time( 'c' ),
				'contact_count' => $this->stats['contacts_imported'] + $this->stats['contacts_updated'],
				'last_error'    => null,
			]
		);

		// Clear pending import flag
		GoogleContactsConnection::set_pending_import( $this->user_id, false );

		return $this->stats;
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
	 * @param string|null $page_token Pagination token.
	 * @return object ListConnectionsResponse.
	 * @throws \Exception On API error.
	 */
	private function fetch_contacts( ?string $page_token = null ): object {
		$service = $this->get_people_service();

		$params = [
			'personFields' => 'names,emailAddresses,phoneNumbers,addresses,organizations,birthdays,photos,urls,metadata',
			'pageSize'     => 100,
		];

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
			// Link and fill gaps for existing person
			$post_id = $existing_id;
			++$this->stats['contacts_updated'];
		} else {
			// Create new person
			$names      = $person->getNames() ?: [];
			$name       = $names[0] ?? null;
			$first_name = $name ? $name->getGivenName() : '';
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
					'post_title'  => trim( $first_name . ' ' . $last_name ),
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
		$existing_last  = get_field( 'last_name', $post_id );

		$given_name  = $name->getGivenName();
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
		$existing_company_ids = [];
		foreach ( $existing as $job ) {
			if ( ! empty( $job['company'] ) ) {
				$company_id                          = is_object( $job['company'] ) ? $job['company']->ID : (int) $job['company'];
				$existing_company_ids[ $company_id ] = true;
			}
		}

		$organizations = $person->getOrganizations() ?: [];
		foreach ( $organizations as $org ) {
			$org_name = $org->getName();
			if ( empty( $org_name ) ) {
				continue;
			}

			$company_id = $this->get_or_create_company( $org_name );
			if ( ! $company_id || isset( $existing_company_ids[ $company_id ] ) ) {
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
				'company'    => $company_id,
				'job_title'  => $org->getTitle() ?? '',
				'is_current' => (bool) $org->getCurrent(),
				'start_date' => $start_date,
				'end_date'   => $end_date,
			];

			$existing_company_ids[ $company_id ] = true;
			$added                               = true;
		}

		if ( $added ) {
			update_field( 'work_history', $existing, $post_id );
		}
	}

	/**
	 * Import birthday as important_date
	 *
	 * @param int    $post_id   Post ID.
	 * @param object $person    Google Person object.
	 * @param string $full_name Person's full name for title.
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
		if ( ! $month || ! $day ) {
			return;
		}

		// Check if birthday already exists
		$existing = get_posts(
			[
				'post_type'      => 'important_date',
				'posts_per_page' => 1,
				'post_author'    => $this->user_id,
				'meta_query'     => [
					[
						'key'     => 'related_people',
						'value'   => '"' . $post_id . '"',
						'compare' => 'LIKE',
					],
				],
				'tax_query'      => [
					[
						'taxonomy' => 'date_type',
						'field'    => 'slug',
						'terms'    => 'birthday',
					],
				],
			]
		);

		if ( ! empty( $existing ) ) {
			return;
		}

		// Format date - use 0000 if year is unknown
		$year           = $date->getYear() ?: 0;
		$date_formatted = sprintf( '%04d-%02d-%02d', $year, $month, $day );

		// translators: %s is the person's name
		$title = sprintf( __( "%s's Birthday", 'caelis' ), $full_name );

		$date_post_id = wp_insert_post(
			[
				'post_type'   => 'important_date',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $this->user_id,
			]
		);

		if ( is_wp_error( $date_post_id ) ) {
			return;
		}

		update_field( 'date_value', $date_formatted, $date_post_id );
		update_field( 'is_recurring', true, $date_post_id );
		update_field( 'related_people', [ $post_id ], $date_post_id );

		// Set year_unknown if year is 0
		if ( 0 === $year ) {
			update_field( 'year_unknown', true, $date_post_id );
		}

		// Ensure the birthday term exists and assign it
		$term = term_exists( 'birthday', 'date_type' );
		if ( ! $term ) {
			$term = wp_insert_term( 'Birthday', 'date_type', [ 'slug' => 'birthday' ] );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			$term_id = is_array( $term ) ? $term['term_id'] : $term;
			wp_set_post_terms( $date_post_id, [ (int) $term_id ], 'date_type' );
		}

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
	 * Get or create a company by name
	 *
	 * @param string $name Company name.
	 * @return int Company post ID.
	 */
	private function get_or_create_company( string $name ): int {
		if ( isset( $this->company_map[ $name ] ) ) {
			return $this->company_map[ $name ];
		}

		// Check if company exists
		$existing = get_page_by_title( $name, OBJECT, 'company' );
		if ( $existing ) {
			$this->company_map[ $name ] = $existing->ID;
			return $existing->ID;
		}

		// Create new company
		$post_id = wp_insert_post(
			[
				'post_type'   => 'company',
				'post_status' => 'publish',
				'post_title'  => $name,
				'post_author' => $this->user_id,
			]
		);

		if ( ! is_wp_error( $post_id ) ) {
			$this->company_map[ $name ] = $post_id;
			++$this->stats['companies_created'];
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
	}

	/**
	 * Map Google phone type to Caelis contact type
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
