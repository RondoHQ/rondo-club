<?php
/**
 * Google Contacts Export Class
 *
 * Exports Caelis contacts to Google Contacts via People API.
 * Handles field mapping, contact creation, updates, and photo uploads.
 *
 * @package Caelis
 */

namespace Caelis\Export;

use Caelis\Calendar\GoogleOAuth;
use Caelis\Contacts\GoogleContactsConnection;
use Google\Service\PeopleService;
use Google\Service\PeopleService\Person;
use Google\Service\PeopleService\Name;
use Google\Service\PeopleService\EmailAddress;
use Google\Service\PeopleService\PhoneNumber;
use Google\Service\PeopleService\Address;
use Google\Service\PeopleService\Organization;
use Google\Service\PeopleService\Url;
use Google\Service\PeopleService\PersonMetadata;
use Google\Service\PeopleService\Source;
use Google\Service\PeopleService\UpdateContactPhotoRequest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GoogleContactsExport
 *
 * Exports Caelis person records to Google Contacts.
 */
class GoogleContactsExport {

	/**
	 * WordPress user ID performing the export
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Initialize hooks for async export
	 *
	 * Called from functions.php to register save_post and cron hooks.
	 */
	public static function init(): void {
		$instance = new self( 0 ); // User ID set per-save.
		add_action( 'save_post_person', [ $instance, 'on_person_saved' ], 20, 3 );
		add_action( 'caelis_google_contact_export', [ $instance, 'handle_async_export' ], 10, 2 );
		add_action( 'before_delete_post', [ $instance, 'on_person_deleted' ], 10, 2 );
	}

	/**
	 * Handle person save to trigger async export
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function on_person_saved( int $post_id, \WP_Post $post, bool $update ): void {
		// Skip autosaves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip non-published posts.
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		// Get post author (the user who owns this contact).
		$user_id = (int) $post->post_author;

		// Check if user has Google Contacts connected with readwrite access.
		$connection = GoogleContactsConnection::get_connection( $user_id );
		if ( ! $connection || ( $connection['access_mode'] ?? '' ) !== 'readwrite' ) {
			return;
		}

		// Queue for async export.
		$this->queue_export( $post_id, $user_id );
	}

	/**
	 * Queue a contact export for async processing
	 *
	 * Uses WP-Cron to process exports in the background, following
	 * the same pattern as calendar rematch in class-auto-title.php.
	 *
	 * @param int $post_id Post ID to export.
	 * @param int $user_id User ID who owns the contact.
	 */
	private function queue_export( int $post_id, int $user_id ): void {
		static $queued = [];

		// Prevent duplicate scheduling in same request.
		$key = $post_id . '-' . $user_id;
		if ( isset( $queued[ $key ] ) ) {
			return;
		}
		$queued[ $key ] = true;

		// Clear any existing scheduled event.
		$timestamp = wp_next_scheduled( 'caelis_google_contact_export', [ $post_id, $user_id ] );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'caelis_google_contact_export', [ $post_id, $user_id ] );
		}

		// Schedule immediate execution.
		wp_schedule_single_event( time(), 'caelis_google_contact_export', [ $post_id, $user_id ] );
		spawn_cron();
	}

	/**
	 * Handle async export cron job
	 *
	 * Called by WP-Cron to process a queued contact export.
	 *
	 * @param int $post_id Post ID to export.
	 * @param int $user_id User ID who owns the contact.
	 */
	public function handle_async_export( int $post_id, int $user_id ): void {
		// Verify post still exists and is published.
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' || $post->post_type !== 'person' ) {
			return;
		}

		// Verify user still has readwrite access.
		$connection = GoogleContactsConnection::get_connection( $user_id );
		if ( ! $connection || ( $connection['access_mode'] ?? '' ) !== 'readwrite' ) {
			return;
		}

		try {
			// Create exporter for this user.
			$exporter = new self( $user_id );
			$exporter->export_contact( $post_id );
		} catch ( \Exception $e ) {
			// Log error but don't throw - this is async.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Google Contact export failed for post ' . $post_id . ': ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Handle person permanent deletion
	 *
	 * Called via before_delete_post hook when a person is permanently deleted.
	 * Uses before_delete_post (not delete_post) because:
	 * 1. It fires BEFORE meta is deleted, so we can still read _google_contact_id
	 * 2. It only fires on permanent delete (empty trash), not when trashing
	 *
	 * @param int      $post_id Post ID being deleted.
	 * @param \WP_Post $post    Post object being deleted.
	 */
	public function on_person_deleted( int $post_id, \WP_Post $post ): void {
		// Only handle person posts.
		if ( $post->post_type !== 'person' ) {
			return;
		}

		// Check if linked to Google.
		$google_id = get_post_meta( $post_id, '_google_contact_id', true );
		if ( empty( $google_id ) ) {
			return; // Not linked, nothing to delete in Google.
		}

		// Get post author (the user who owns this contact).
		$user_id = (int) $post->post_author;

		// Verify user has Google Contacts connected with readwrite access.
		$connection = GoogleContactsConnection::get_connection( $user_id );
		if ( ! $connection || ( $connection['access_mode'] ?? '' ) !== 'readwrite' ) {
			return; // Can't delete without write access.
		}

		// Attempt to delete from Google (non-blocking).
		$this->delete_google_contact( $google_id, $user_id );
	}

	/**
	 * Delete a contact from Google Contacts
	 *
	 * Calls the Google People API deleteContact method.
	 * Handles errors gracefully - never blocks local deletion.
	 *
	 * @param string $resource_name Google resource name (people/c123...).
	 * @param int    $user_id       WordPress user ID.
	 * @return bool True on success (including 404), false on failure.
	 */
	private function delete_google_contact( string $resource_name, int $user_id ): bool {
		try {
			// Create service for this user.
			$credentials = GoogleContactsConnection::get_decrypted_credentials( $user_id );
			if ( ! $credentials ) {
				return false;
			}

			$client = GoogleOAuth::get_contacts_client( false, false );
			if ( ! $client ) {
				return false;
			}

			$client->setAccessToken( $credentials );

			// Refresh token if expired.
			if ( $client->isAccessTokenExpired() ) {
				$refresh_token = $client->getRefreshToken();
				if ( ! $refresh_token ) {
					return false;
				}
				$client->fetchAccessTokenWithRefreshToken( $refresh_token );
				GoogleContactsConnection::update_credentials( $user_id, $client->getAccessToken() );
			}

			$service = new PeopleService( $client );

			// Delete the contact from Google.
			// https://developers.google.com/people/api/rest/v1/people/deleteContact
			$service->people->deleteContact( $resource_name );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'PRM: Deleted Google contact %s for user %d', $resource_name, $user_id ) );
			}

			return true;

		} catch ( \Google\Service\Exception $e ) {
			// 404 means already deleted - that's fine.
			if ( $e->getCode() === 404 ) {
				return true;
			}

			// Log error but don't throw - never block local deletion.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'PRM: Failed to delete Google contact %s: %s',
					$resource_name,
					$e->getMessage()
				) );
			}

			return false;

		} catch ( \Exception $e ) {
			// Log any other error but don't throw.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'PRM: Exception deleting Google contact %s: %s',
					$resource_name,
					$e->getMessage()
				) );
			}

			return false;
		}
	}

	/**
	 * Google People Service instance
	 *
	 * @var PeopleService|null
	 */
	private ?PeopleService $service = null;

	/**
	 * Export statistics
	 *
	 * @var array
	 */
	private array $stats = [
		'contacts_created' => 0,
		'contacts_updated' => 0,
		'photos_uploaded'  => 0,
		'errors'           => [],
	];

	/**
	 * Person fields to request/update from Google API
	 */
	private const PERSON_FIELDS = 'names,emailAddresses,phoneNumbers,addresses,organizations,urls,photos,metadata';

	/**
	 * Update person fields mask for update requests
	 */
	private const UPDATE_PERSON_FIELDS = 'names,emailAddresses,phoneNumbers,addresses,organizations,urls';

	/**
	 * Constructor
	 *
	 * @param int $user_id WordPress user ID performing the export.
	 *                     Pass 0 when instantiating for hook registration only.
	 */
	public function __construct( int $user_id ) {
		$this->user_id = $user_id;

		// Skip heavy initialization for hook registration (user_id = 0).
		if ( $user_id > 0 ) {
			// Increase limits for large exports.
			@set_time_limit( 600 );
			wp_raise_memory_limit( 'admin' );
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

		// Refresh token if expired.
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
	 * Check if user has readwrite access mode
	 *
	 * @return bool True if user can write to Google Contacts.
	 */
	private function has_write_access(): bool {
		$connection = GoogleContactsConnection::get_connection( $this->user_id );
		return ! empty( $connection ) && ( $connection['access_mode'] ?? '' ) === 'readwrite';
	}

	/**
	 * Export a single contact to Google
	 *
	 * Main export method - decides whether to create or update.
	 *
	 * @param int $post_id WordPress post ID of the person.
	 * @return bool True on success, false on failure.
	 * @throws \Exception If user doesn't have write access.
	 */
	public function export_contact( int $post_id ): bool {
		// Verify write access.
		if ( ! $this->has_write_access() ) {
			throw new \Exception( 'Write access to Google Contacts is required for export. Please reconnect with read/write permissions.' );
		}

		// Check if contact is already linked to Google.
		$google_contact_id = get_post_meta( $post_id, '_google_contact_id', true );

		try {
			if ( $google_contact_id ) {
				// Update existing Google contact.
				return $this->update_google_contact( $post_id, $google_contact_id );
			} else {
				// Create new Google contact.
				$resource_name = $this->create_google_contact( $post_id );
				return ! empty( $resource_name );
			}
		} catch ( \Exception $e ) {
			$this->stats['errors'][] = sprintf(
				'Export failed for post %d: %s',
				$post_id,
				$e->getMessage()
			);
			return false;
		}
	}

	/**
	 * Create a new contact in Google
	 *
	 * @param int $post_id WordPress post ID of the person.
	 * @return string|null Google resource name (people/c123...) or null on failure.
	 */
	public function create_google_contact( int $post_id ): ?string {
		$service = $this->get_people_service();
		$person  = $this->build_person_from_post( $post_id );

		try {
			$created = $service->people->createContact(
				$person,
				[
					'personFields' => self::PERSON_FIELDS,
				]
			);

			$resource_name = $created->getResourceName();
			$etag          = $created->getEtag();

			// Store Google IDs for future syncs.
			$this->store_google_ids( $post_id, $resource_name, $etag );

			++$this->stats['contacts_created'];

			// Upload photo if available.
			$this->upload_photo( $post_id, $resource_name );

			return $resource_name;
		} catch ( \Google\Service\Exception $e ) {
			$error_message = $this->parse_google_error( $e );
			$this->stats['errors'][] = sprintf( 'Create failed for post %d: %s', $post_id, $error_message );
			return null;
		}
	}

	/**
	 * Update an existing Google contact
	 *
	 * @param int    $post_id       WordPress post ID of the person.
	 * @param string $resource_name Google resource name (people/c123...).
	 * @return bool True on success, false on failure.
	 */
	public function update_google_contact( int $post_id, string $resource_name ): bool {
		$service = $this->get_people_service();
		$etag    = get_post_meta( $post_id, '_google_etag', true );

		$person = $this->build_person_from_post( $post_id );
		$person->setResourceName( $resource_name );

		// Set metadata with etag for optimistic locking.
		if ( $etag ) {
			$person->setMetadata( $this->build_metadata_with_etag( $etag ) );
		}

		try {
			$updated = $service->people->updateContact(
				$resource_name,
				$person,
				[
					'updatePersonFields' => self::UPDATE_PERSON_FIELDS,
					'personFields'       => self::PERSON_FIELDS,
				]
			);

			// Update stored etag with new value.
			$new_etag = $updated->getEtag();
			update_post_meta( $post_id, '_google_etag', $new_etag );
			update_post_meta( $post_id, '_google_last_export', current_time( 'c' ) );

			++$this->stats['contacts_updated'];

			// Upload photo if available.
			$this->upload_photo( $post_id, $resource_name );

			return true;
		} catch ( \Google\Service\Exception $e ) {
			// Handle etag mismatch (409 conflict) by re-fetching and retrying once.
			if ( $e->getCode() === 400 || $e->getCode() === 409 ) {
				$errors = $e->getErrors();
				$is_etag_conflict = false;
				foreach ( $errors as $error ) {
					if ( isset( $error['reason'] ) && $error['reason'] === 'failedPrecondition' ) {
						$is_etag_conflict = true;
						break;
					}
				}

				if ( $is_etag_conflict ) {
					return $this->retry_update_with_fresh_etag( $post_id, $resource_name );
				}
			}

			$error_message = $this->parse_google_error( $e );
			$this->stats['errors'][] = sprintf( 'Update failed for post %d: %s', $post_id, $error_message );
			return false;
		}
	}

	/**
	 * Retry update after fetching fresh etag
	 *
	 * Called when an etag mismatch occurs (contact was modified on Google side).
	 *
	 * @param int    $post_id       WordPress post ID of the person.
	 * @param string $resource_name Google resource name.
	 * @return bool True on success, false on failure.
	 */
	private function retry_update_with_fresh_etag( int $post_id, string $resource_name ): bool {
		$service = $this->get_people_service();

		try {
			// Fetch current contact to get fresh etag.
			$current = $service->people->get(
				$resource_name,
				[
					'personFields' => 'metadata',
				]
			);

			$fresh_etag = $current->getEtag();

			// Build person with fresh etag.
			$person = $this->build_person_from_post( $post_id );
			$person->setResourceName( $resource_name );
			$person->setMetadata( $this->build_metadata_with_etag( $fresh_etag ) );

			$updated = $service->people->updateContact(
				$resource_name,
				$person,
				[
					'updatePersonFields' => self::UPDATE_PERSON_FIELDS,
					'personFields'       => self::PERSON_FIELDS,
				]
			);

			// Update stored etag.
			update_post_meta( $post_id, '_google_etag', $updated->getEtag() );
			update_post_meta( $post_id, '_google_last_export', current_time( 'c' ) );

			++$this->stats['contacts_updated'];

			return true;
		} catch ( \Exception $e ) {
			$this->stats['errors'][] = sprintf(
				'Retry update failed for post %d: %s',
				$post_id,
				$e->getMessage()
			);
			return false;
		}
	}

	/**
	 * Upload featured image as Google contact photo
	 *
	 * @param int    $post_id       WordPress post ID of the person.
	 * @param string $resource_name Google resource name.
	 * @return bool True on success, false if no photo or on failure.
	 */
	public function upload_photo( int $post_id, string $resource_name ): bool {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumbnail_id ) {
			return false;
		}

		$image_path = get_attached_file( $thumbnail_id );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return false;
		}

		// Check file size - Google has undocumented limits around 4MB.
		$file_size = filesize( $image_path );
		if ( $file_size > 4 * 1024 * 1024 ) {
			$this->stats['errors'][] = sprintf(
				'Photo too large for post %d: %d bytes (max 4MB)',
				$post_id,
				$file_size
			);
			return false;
		}

		$service = $this->get_people_service();

		try {
			// Read and base64 encode image.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
			$photo_bytes = base64_encode( file_get_contents( $image_path ) );

			$request = new UpdateContactPhotoRequest();
			$request->setPhotoBytes( $photo_bytes );
			$request->setPersonFields( 'photos,metadata' );

			$service->people->updateContactPhoto( $resource_name, $request );

			++$this->stats['photos_uploaded'];
			return true;
		} catch ( \Exception $e ) {
			// Photo upload failures shouldn't fail the entire export.
			$this->stats['errors'][] = sprintf(
				'Photo upload failed for post %d: %s',
				$post_id,
				$e->getMessage()
			);
			return false;
		}
	}

	/**
	 * Build Google Person object from WordPress post
	 *
	 * @param int $post_id WordPress post ID of the person.
	 * @return Person Google Person object with all fields set.
	 */
	public function build_person_from_post( int $post_id ): Person {
		$person = new Person();

		// Build each field type.
		$name = $this->build_name( $post_id );
		if ( $name ) {
			$person->setNames( [ $name ] );
		}

		$emails = $this->build_email_addresses( $post_id );
		if ( ! empty( $emails ) ) {
			$person->setEmailAddresses( $emails );
		}

		$phones = $this->build_phone_numbers( $post_id );
		if ( ! empty( $phones ) ) {
			$person->setPhoneNumbers( $phones );
		}

		$urls = $this->build_urls( $post_id );
		if ( ! empty( $urls ) ) {
			$person->setUrls( $urls );
		}

		$addresses = $this->build_addresses( $post_id );
		if ( ! empty( $addresses ) ) {
			$person->setAddresses( $addresses );
		}

		$organizations = $this->build_organizations( $post_id );
		if ( ! empty( $organizations ) ) {
			$person->setOrganizations( $organizations );
		}

		return $person;
	}

	/**
	 * Build Name object from person post
	 *
	 * @param int $post_id WordPress post ID.
	 * @return Name|null Name object or null if no name data.
	 */
	private function build_name( int $post_id ): ?Name {
		$first_name = get_field( 'first_name', $post_id );
		$last_name  = get_field( 'last_name', $post_id );

		if ( empty( $first_name ) && empty( $last_name ) ) {
			return null;
		}

		$name = new Name();

		if ( ! empty( $first_name ) ) {
			$name->setGivenName( $first_name );
		}

		if ( ! empty( $last_name ) ) {
			$name->setFamilyName( $last_name );
		}

		return $name;
	}

	/**
	 * Build EmailAddress objects from contact_info repeater
	 *
	 * @param int $post_id WordPress post ID.
	 * @return EmailAddress[] Array of EmailAddress objects.
	 */
	private function build_email_addresses( int $post_id ): array {
		$contact_info = get_field( 'contact_info', $post_id ) ?: [];
		$emails       = [];

		foreach ( $contact_info as $info ) {
			if ( ( $info['contact_type'] ?? '' ) !== 'email' ) {
				continue;
			}

			$value = $info['contact_value'] ?? '';
			if ( empty( $value ) ) {
				continue;
			}

			$email = new EmailAddress();
			$email->setValue( $value );

			$label = $info['contact_label'] ?? '';
			if ( ! empty( $label ) ) {
				$email->setType( $this->map_label_to_google_type( $label ) );
			} else {
				$email->setType( 'other' );
			}

			$emails[] = $email;
		}

		return $emails;
	}

	/**
	 * Build PhoneNumber objects from contact_info repeater
	 *
	 * @param int $post_id WordPress post ID.
	 * @return PhoneNumber[] Array of PhoneNumber objects.
	 */
	private function build_phone_numbers( int $post_id ): array {
		$contact_info = get_field( 'contact_info', $post_id ) ?: [];
		$phones       = [];

		foreach ( $contact_info as $info ) {
			$contact_type = $info['contact_type'] ?? '';
			if ( ! in_array( $contact_type, [ 'phone', 'mobile' ], true ) ) {
				continue;
			}

			$value = $info['contact_value'] ?? '';
			if ( empty( $value ) ) {
				continue;
			}

			$phone = new PhoneNumber();
			$phone->setValue( $value );

			// Map Caelis type to Google type.
			if ( $contact_type === 'mobile' ) {
				$phone->setType( 'mobile' );
			} else {
				// Use label if available, otherwise 'home'.
				$label = $info['contact_label'] ?? '';
				if ( ! empty( $label ) && strtolower( $label ) === 'work' ) {
					$phone->setType( 'work' );
				} else {
					$phone->setType( 'home' );
				}
			}

			$phones[] = $phone;
		}

		return $phones;
	}

	/**
	 * Build Url objects from contact_info repeater
	 *
	 * @param int $post_id WordPress post ID.
	 * @return Url[] Array of Url objects.
	 */
	private function build_urls( int $post_id ): array {
		$contact_info = get_field( 'contact_info', $post_id ) ?: [];
		$urls         = [];
		$url_types    = [ 'website', 'linkedin', 'twitter', 'facebook', 'instagram' ];

		foreach ( $contact_info as $info ) {
			$contact_type = $info['contact_type'] ?? '';
			if ( ! in_array( $contact_type, $url_types, true ) ) {
				continue;
			}

			$value = $info['contact_value'] ?? '';
			if ( empty( $value ) ) {
				continue;
			}

			$url = new Url();
			$url->setValue( $value );

			// Set type based on contact_type.
			switch ( $contact_type ) {
				case 'linkedin':
					$url->setType( 'profile' );
					break;
				case 'twitter':
				case 'facebook':
				case 'instagram':
					$url->setType( 'profile' );
					break;
				default:
					$url->setType( 'homePage' );
			}

			$urls[] = $url;
		}

		return $urls;
	}

	/**
	 * Build Address objects from addresses repeater
	 *
	 * @param int $post_id WordPress post ID.
	 * @return Address[] Array of Address objects.
	 */
	private function build_addresses( int $post_id ): array {
		$addresses_data = get_field( 'addresses', $post_id ) ?: [];
		$addresses      = [];

		foreach ( $addresses_data as $addr ) {
			// Skip empty addresses.
			$has_data = ! empty( $addr['street'] )
				|| ! empty( $addr['city'] )
				|| ! empty( $addr['state'] )
				|| ! empty( $addr['postal_code'] )
				|| ! empty( $addr['country'] );

			if ( ! $has_data ) {
				continue;
			}

			$address = new Address();

			if ( ! empty( $addr['street'] ) ) {
				$address->setStreetAddress( $addr['street'] );
			}

			if ( ! empty( $addr['city'] ) ) {
				$address->setCity( $addr['city'] );
			}

			if ( ! empty( $addr['state'] ) ) {
				$address->setRegion( $addr['state'] );
			}

			if ( ! empty( $addr['postal_code'] ) ) {
				$address->setPostalCode( $addr['postal_code'] );
			}

			if ( ! empty( $addr['country'] ) ) {
				$address->setCountry( $addr['country'] );
			}

			// Set type based on label.
			$label = $addr['address_label'] ?? '';
			if ( ! empty( $label ) ) {
				$address->setType( $this->map_label_to_google_type( $label ) );
			} else {
				$address->setType( 'home' );
			}

			$addresses[] = $address;
		}

		return $addresses;
	}

	/**
	 * Build Organization objects from work_history repeater
	 *
	 * @param int $post_id WordPress post ID.
	 * @return Organization[] Array of Organization objects.
	 */
	private function build_organizations( int $post_id ): array {
		$work_history  = get_field( 'work_history', $post_id ) ?: [];
		$organizations = [];

		foreach ( $work_history as $job ) {
			// Get company name.
			$company    = $job['company'] ?? null;
			$company_id = is_object( $company ) ? $company->ID : (int) $company;

			if ( ! $company_id ) {
				continue;
			}

			$company_name = get_the_title( $company_id );
			if ( empty( $company_name ) ) {
				continue;
			}

			$org = new Organization();
			$org->setName( $company_name );

			$job_title = $job['job_title'] ?? '';
			if ( ! empty( $job_title ) ) {
				$org->setTitle( $job_title );
			}

			$is_current = ! empty( $job['is_current'] );
			$org->setCurrent( $is_current );

			// Set start date if available.
			$start_date = $job['start_date'] ?? '';
			if ( ! empty( $start_date ) ) {
				$org->setStartDate( $this->build_google_date( $start_date ) );
			}

			// Set end date if available and not current.
			$end_date = $job['end_date'] ?? '';
			if ( ! empty( $end_date ) && ! $is_current ) {
				$org->setEndDate( $this->build_google_date( $end_date ) );
			}

			$organizations[] = $org;
		}

		return $organizations;
	}

	/**
	 * Build Google Date object from date string
	 *
	 * @param string $date_string Date in Y-m-d format.
	 * @return \Google\Service\PeopleService\Date|null Google Date object.
	 */
	private function build_google_date( string $date_string ): ?\Google\Service\PeopleService\Date {
		if ( empty( $date_string ) ) {
			return null;
		}

		$parts = explode( '-', $date_string );
		if ( count( $parts ) !== 3 ) {
			return null;
		}

		$date = new \Google\Service\PeopleService\Date();
		$date->setYear( (int) $parts[0] );
		$date->setMonth( (int) $parts[1] );
		$date->setDay( (int) $parts[2] );

		return $date;
	}

	/**
	 * Build PersonMetadata with etag for update requests
	 *
	 * Required for update requests to prevent concurrent modification.
	 *
	 * @param string $etag Current etag value.
	 * @return PersonMetadata Metadata object with source containing etag.
	 */
	private function build_metadata_with_etag( string $etag ): PersonMetadata {
		$source = new Source();
		$source->setType( 'CONTACT' );
		$source->setEtag( $etag );

		$metadata = new PersonMetadata();
		$metadata->setSources( [ $source ] );

		return $metadata;
	}

	/**
	 * Store Google contact IDs as post meta
	 *
	 * @param int    $post_id       WordPress post ID.
	 * @param string $resource_name Google resource name (people/c123...).
	 * @param string $etag          Google etag for change detection.
	 */
	private function store_google_ids( int $post_id, string $resource_name, string $etag ): void {
		update_post_meta( $post_id, '_google_contact_id', $resource_name );
		update_post_meta( $post_id, '_google_etag', $etag );
		update_post_meta( $post_id, '_google_last_export', current_time( 'c' ) );
	}

	/**
	 * Map Caelis label to Google type
	 *
	 * @param string $label Caelis label (e.g., "Work", "Home").
	 * @return string Google type string.
	 */
	private function map_label_to_google_type( string $label ): string {
		$label_lower = strtolower( trim( $label ) );

		$mapping = [
			'work'     => 'work',
			'home'     => 'home',
			'mobile'   => 'mobile',
			'personal' => 'home',
			'business' => 'work',
			'office'   => 'work',
			'main'     => 'main',
		];

		return $mapping[ $label_lower ] ?? 'other';
	}

	/**
	 * Parse Google API error for human-readable message
	 *
	 * @param \Google\Service\Exception $e Google service exception.
	 * @return string Human-readable error message.
	 */
	private function parse_google_error( \Google\Service\Exception $e ): string {
		$errors = $e->getErrors();
		if ( ! empty( $errors ) ) {
			$messages = [];
			foreach ( $errors as $error ) {
				$messages[] = $error['message'] ?? $error['reason'] ?? 'Unknown error';
			}
			return implode( '; ', $messages );
		}

		return $e->getMessage();
	}

	/**
	 * Get export statistics
	 *
	 * @return array Export statistics.
	 */
	public function get_stats(): array {
		return $this->stats;
	}

	/**
	 * Reset statistics
	 *
	 * Useful when performing multiple export operations.
	 */
	public function reset_stats(): void {
		$this->stats = [
			'contacts_created' => 0,
			'contacts_updated' => 0,
			'photos_uploaded'  => 0,
			'errors'           => [],
		];
	}

	/**
	 * Bulk export all contacts without a Google Contact ID for a user.
	 *
	 * Processes contacts sequentially to avoid rate limits.
	 * Returns stats array with counts.
	 *
	 * @param callable|null $progress_callback Optional callback for progress updates.
	 *                                         Receives (int $current, int $total, int $post_id).
	 * @return array{total: int, exported: int, updated: int, skipped: int, failed: int, errors: array}
	 */
	public function bulk_export_unlinked( ?callable $progress_callback = null ): array {
		$stats = [
			'total'    => 0,
			'exported' => 0,  // New contacts created in Google.
			'updated'  => 0,  // Existing contacts updated (shouldn't happen for unlinked, but track it).
			'skipped'  => 0,  // Already has google_contact_id (not unlinked).
			'failed'   => 0,
			'errors'   => [],
		];

		// Verify user has readwrite access.
		$connection = GoogleContactsConnection::get_connection( $this->user_id );
		if ( ! $connection || ( $connection['access_mode'] ?? '' ) !== 'readwrite' ) {
			$stats['errors'][] = __( 'Google Contacts is not connected with read-write access.', 'caelis' );
			return $stats;
		}

		// Query all person posts for this user without _google_contact_id.
		$args = [
			'post_type'      => 'person',
			'post_status'    => 'publish',
			'author'         => $this->user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				'relation' => 'OR',
				[
					'key'     => '_google_contact_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_google_contact_id',
					'value'   => '',
					'compare' => '=',
				],
			],
		];

		$query    = new \WP_Query( $args );
		$post_ids = $query->posts;
		$stats['total'] = count( $post_ids );

		if ( $stats['total'] === 0 ) {
			return $stats;
		}

		// Process sequentially to avoid rate limits.
		foreach ( $post_ids as $index => $post_id ) {
			// Progress callback.
			if ( $progress_callback ) {
				$progress_callback( $index + 1, $stats['total'], $post_id );
			}

			// Double-check this contact doesn't have a google_contact_id.
			// (could have been exported in parallel by save hook).
			$google_id = get_post_meta( $post_id, '_google_contact_id', true );
			if ( ! empty( $google_id ) ) {
				$stats['skipped']++;
				continue;
			}

			try {
				$result = $this->export_contact( $post_id );
				if ( $result ) {
					$stats['exported']++;
				} else {
					$stats['failed']++;
					$stats['errors'][] = sprintf(
						/* translators: %d: contact ID */
						__( 'Failed to export contact ID %d.', 'caelis' ),
						$post_id
					);
				}
			} catch ( \Exception $e ) {
				$stats['failed']++;
				$stats['errors'][] = sprintf(
					/* translators: 1: contact ID, 2: error message */
					__( 'Error exporting contact ID %1$d: %2$s', 'caelis' ),
					$post_id,
					$e->getMessage()
				);
			}

			// Small delay between requests to be nice to Google API.
			// 100ms delay = max 10 requests/second, well under any rate limit.
			usleep( 100000 );
		}

		return $stats;
	}

	/**
	 * Get count of contacts without a Google Contact ID.
	 *
	 * @return int Count of unlinked contacts.
	 */
	public function get_unlinked_count(): int {
		$args = [
			'post_type'      => 'person',
			'post_status'    => 'publish',
			'author'         => $this->user_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				'relation' => 'OR',
				[
					'key'     => '_google_contact_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_google_contact_id',
					'value'   => '',
					'compare' => '=',
				],
			],
		];

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}
}
