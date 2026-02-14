<?php
/**
 * CardDAV Backend
 *
 * Performs CRUD operations on Person CPT for CardDAV sync.
 * Includes sync token support for efficient synchronization.
 *
 * @package Rondo
 */

namespace Rondo\CardDAV;

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\CardDAV\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CardDAVBackend extends AbstractBackend implements SyncSupport {

	/**
	 * Option name for sync tokens
	 */
	const SYNC_TOKEN_OPTION = 'rondo_carddav_sync_tokens';

	/**
	 * Option name for change log
	 */
	const CHANGE_LOG_OPTION = 'rondo_carddav_changes';

	/**
	 * Flag to prevent duplicate logging during CardDAV operations
	 */
	private static $skip_hooks = false;

	/**
	 * Initialize WordPress hooks for tracking changes made outside CardDAV
	 */
	public static function init_hooks() {
		// Track when persons are created or updated via web UI
		add_action( 'save_post_person', [ __CLASS__, 'on_person_saved' ], 10, 3 );

		// Track when persons are trashed or permanently deleted via web UI
		add_action( 'wp_trash_post', [ __CLASS__, 'on_person_removed' ] );
		add_action( 'before_delete_post', [ __CLASS__, 'on_person_removed' ] );
	}

	/**
	 * Handle person saved (created or updated) via web UI
	 *
	 * @param int $post_id Post ID
	 * @param \WP_Post $post Post object
	 * @param bool $update Whether this is an update
	 */
	public static function on_person_saved( $post_id, $post, $update ) {
		// Skip if this change came from CardDAV
		if ( self::$skip_hooks ) {
			return;
		}

		// Skip autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only track published persons
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$user_id = (int) $post->post_author;
		$type    = $update ? 'modified' : 'added';

		self::log_external_change( $user_id, $post_id, $type );
	}

	/**
	 * Handle person trashed or permanently deleted via web UI
	 *
	 * @param int $post_id Post ID
	 */
	public static function on_person_removed( $post_id ) {
		if ( self::$skip_hooks ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'person' ) {
			return;
		}

		$user_id = (int) $post->post_author;
		$uri     = get_post_meta( $post_id, '_carddav_uri', true ) ?: $post_id . '.vcf';

		self::log_external_change( $user_id, $post_id, 'deleted', $uri );
	}

	/**
	 * Log a change made outside of CardDAV (via web UI or REST API)
	 *
	 * @param int $user_id User/address book ID
	 * @param int $person_id Person post ID
	 * @param string $type Change type (added, modified, deleted)
	 * @param string|null $uri Optional URI for deleted cards
	 */
	public static function log_external_change( $user_id, $person_id, $type, $uri = null ) {
		$changes = get_option( self::CHANGE_LOG_OPTION, [] );

		if ( ! isset( $changes[ $user_id ] ) ) {
			$changes[ $user_id ] = [];
		}

		$stored_uri = $uri ?: $person_id . '.vcf';

		$changes[ $user_id ][ $person_id ] = [
			'type'      => $type,
			'timestamp' => time(),
			'uri'       => $stored_uri,
		];

		update_option( self::CHANGE_LOG_OPTION, $changes );

		// Update sync token
		$tokens             = get_option( self::SYNC_TOKEN_OPTION, [] );
		$tokens[ $user_id ] = time();
		update_option( self::SYNC_TOKEN_OPTION, $tokens );

		error_log( "CardDAV: Logged external change - user {$user_id}, person {$person_id}, type: {$type}" );
	}

	/**
	 * Set flag to skip hooks during CardDAV operations
	 *
	 * @param bool $skip Whether to skip hooks
	 */
	public static function set_skip_hooks( $skip ) {
		self::$skip_hooks = $skip;
	}

	/**
	 * Get address books for a principal
	 *
	 * @param string $principalUri Principal URI (e.g., 'principals/username')
	 * @return array Array of address books
	 */
	public function getAddressBooksForUser( $principalUri ) {
		error_log( "CardDAV: getAddressBooksForUser called for principal: {$principalUri}" );

		$parts    = explode( '/', $principalUri );
		$username = end( $parts );
		$user     = get_user_by( 'login', $username );

		if ( ! $user ) {
			error_log( "CardDAV: getAddressBooksForUser - user not found for: {$username}" );
			return [];
		}

		wp_set_current_user( $user->ID );

		$ctag       = $this->getCtag( $user->ID );
		$sync_token = $this->getCurrentSyncToken( $user->ID );

		error_log( "CardDAV: Returning address book for user ID {$user->ID}, ctag: {$ctag}, sync-token: {$sync_token}" );

		// Each user has one address book containing their contacts
		return [
			[
				'id'                                     => $user->ID,
				'uri'                                    => 'contacts',
				'principaluri'                           => $principalUri,
				'{DAV:}displayname'                      => 'Rondo Contacts',
				'{' . Plugin::NS_CARDDAV . '}addressbook-description' => 'Contacts from Rondo CRM',
				'{http://calendarserver.org/ns/}getctag' => $ctag,
				'{http://sabredav.org/ns}sync-token'     => $sync_token,
			],
		];
	}

	/**
	 * Update address book properties
	 *
	 * @param string $addressBookId Address book ID
	 * @param \Sabre\DAV\PropPatch $propPatch Property patch
	 * @return void
	 */
	public function updateAddressBook( $addressBookId, \Sabre\DAV\PropPatch $propPatch ) {
		// We don't support updating address book properties
	}

	/**
	 * Create a new address book
	 *
	 * @param string $principalUri Principal URI
	 * @param string $url Address book URL
	 * @param array $properties Address book properties
	 * @return void
	 */
	public function createAddressBook( $principalUri, $url, array $properties ) {
		// Each user automatically has one address book, no need to create
	}

	/**
	 * Delete an address book
	 *
	 * @param mixed $addressBookId Address book ID
	 * @return void
	 */
	public function deleteAddressBook( $addressBookId ) {
		// We don't allow deleting the address book
	}

	/**
	 * Get all cards in an address book
	 *
	 * @param mixed $addressBookId Address book ID (user ID)
	 * @return array Array of card data
	 */
	public function getCards( $addressBookId ) {
		error_log( "CardDAV: getCards called for address book (user) ID: {$addressBookId}" );

		$cards = [];

		// Set current user for access control
		wp_set_current_user( $addressBookId );

		// Get all persons for this user
		$persons = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'author'         => $addressBookId,
			]
		);

		foreach ( $persons as $person ) {
			$vcard = \Rondo\Export\VCard::generate( $person );
			$etag  = $this->generateEtag( $person );

			$cards[] = [
				'id'           => $person->ID,
				'uri'          => $this->getUriForPerson( $person->ID ),
				'lastmodified' => strtotime( $person->post_modified_gmt ),
				'etag'         => $etag,
				'size'         => strlen( $vcard ),
			];
		}

		error_log( 'CardDAV: getCards returning ' . count( $cards ) . ' cards' );

		return $cards;
	}

	/**
	 * Get a specific card
	 *
	 * @param mixed $addressBookId Address book ID
	 * @param string $cardUri Card URI (e.g., '123.vcf')
	 * @return array|null Card data or null if not found
	 */
	public function getCard( $addressBookId, $cardUri ) {
		$person_id = $this->getPersonIdFromUri( $cardUri );

		error_log( "CardDAV: getCard called for user {$addressBookId}, URI: {$cardUri}, Person ID: " . ( $person_id ?: 'null' ) );

		if ( ! $person_id ) {
			return null;
		}

		// Set current user for access control
		wp_set_current_user( $addressBookId );

		$person = get_post( $person_id );

		if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return null;
		}

		// Verify ownership
		if ( (int) $person->post_author !== (int) $addressBookId ) {
			return null;
		}

		$vcard = \Rondo\Export\VCard::generate( $person );
		$etag  = $this->generateEtag( $person );

		return [
			'id'           => $person->ID,
			'uri'          => $cardUri,
			'lastmodified' => strtotime( $person->post_modified_gmt ),
			'etag'         => $etag,
			'size'         => strlen( $vcard ),
			'carddata'     => $vcard,
		];
	}

	/**
	 * Get multiple cards
	 *
	 * @param mixed $addressBookId Address book ID
	 * @param array $uris Array of card URIs
	 * @return array Array of card data
	 */
	public function getMultipleCards( $addressBookId, array $uris ) {
		$cards = [];

		foreach ( $uris as $uri ) {
			$card = $this->getCard( $addressBookId, $uri );
			if ( $card ) {
				$cards[] = $card;
			}
		}

		return $cards;
	}

	/**
	 * Create a new card
	 *
	 * @param mixed $addressBookId Address book ID
	 * @param string $cardUri Card URI
	 * @param string $cardData vCard data
	 * @return string|null ETag of the new card
	 */
	public function createCard( $addressBookId, $cardUri, $cardData ) {
		error_log( "CardDAV: Creating new card for user {$addressBookId}, URI: {$cardUri}" );

		// Skip WordPress hooks to avoid double-logging
		self::set_skip_hooks( true );

		// Parse the vCard data
		$parsed = \Rondo\Export\VCard::parse( $cardData );

		if ( empty( $parsed['first_name'] ) && empty( $parsed['last_name'] ) && empty( $parsed['full_name'] ) ) {
			error_log( 'CardDAV: Create failed - no name found in vCard data' );
			self::set_skip_hooks( false );
			return null;
		}

		// Set current user
		wp_set_current_user( $addressBookId );

		list( $first_name, $infix, $last_name ) = $this->parseNameFields( $parsed );

		// Create the person post
		$post_id = wp_insert_post(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
				'post_author' => $addressBookId,
				'post_title'  => $this->buildPostTitle( $first_name, $infix, $last_name ),
			]
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			self::set_skip_hooks( false );
			return null;
		}

		$this->updatePersonFields( $post_id, $parsed, $first_name, $infix, $last_name );

		// Store the client's URI for future lookups
		update_post_meta( $post_id, '_carddav_uri', $cardUri );

		// Import notes as timeline notes
		if ( ! empty( $parsed['notes'] ) ) {
			foreach ( $parsed['notes'] as $note_content ) {
				wp_insert_comment(
					[
						'comment_post_ID'  => $post_id,
						'comment_content'  => wp_kses_post( $note_content ),
						'comment_type'     => \RONDO_Comment_Types::TYPE_NOTE,
						'user_id'          => $addressBookId,
						'comment_approved' => 1,
					]
				);
			}
		}

		// Import photo (base64 or URL)
		if ( ! empty( $parsed['photo_base64'] ) || ! empty( $parsed['photo_url'] ) ) {
			$this->importPhoto( $post_id, $parsed, $first_name, $last_name );
		}

		// Log the change for sync
		$this->logChange( $addressBookId, $post_id, 'added' );

		error_log( "CardDAV: Created new person ID {$post_id} - {$first_name} {$last_name} (URI: {$cardUri})" );

		// Re-enable WordPress hooks
		self::set_skip_hooks( false );

		// Return the etag
		$person = get_post( $post_id );
		return $this->generateEtag( $person );
	}

	/**
	 * Update an existing card
	 *
	 * @param mixed $addressBookId Address book ID
	 * @param string $cardUri Card URI
	 * @param string $cardData vCard data
	 * @return string|null ETag of the updated card
	 */
	public function updateCard( $addressBookId, $cardUri, $cardData ) {
		$person_id = $this->getPersonIdFromUri( $cardUri );

		error_log( "CardDAV: Updating card for user {$addressBookId}, URI: {$cardUri}, Person ID: " . ( $person_id ?: 'null' ) );

		if ( ! $person_id ) {
			error_log( 'CardDAV: Update failed - could not parse person ID from URI' );
			return null;
		}

		// Skip WordPress hooks to avoid double-logging
		self::set_skip_hooks( true );

		// Set current user
		wp_set_current_user( $addressBookId );

		$person = get_post( $person_id );

		if ( ! $person || $person->post_type !== 'person' ) {
			self::set_skip_hooks( false );
			return null;
		}

		// Verify ownership
		if ( (int) $person->post_author !== (int) $addressBookId ) {
			self::set_skip_hooks( false );
			return null;
		}

		// Parse the vCard data
		$parsed = \Rondo\Export\VCard::parse( $cardData );

		list( $first_name, $infix, $last_name ) = $this->parseNameFields( $parsed );

		// Update the post
		wp_update_post(
			[
				'ID'         => $person_id,
				'post_title' => $this->buildPostTitle( $first_name, $infix, $last_name ),
			]
		);

		$this->updatePersonFields( $person_id, $parsed, $first_name, $infix, $last_name );

		// Import new notes as timeline notes
		// Note: We only add new notes, we don't sync/delete existing notes
		if ( ! empty( $parsed['notes'] ) ) {
			foreach ( $parsed['notes'] as $note_content ) {
				// Check if this exact note already exists to avoid duplicates
				$existing = get_comments(
					[
						'post_id' => $person_id,
						'type'    => \RONDO_Comment_Types::TYPE_NOTE,
						'search'  => $note_content,
						'number'  => 1,
					]
				);

				if ( empty( $existing ) ) {
					wp_insert_comment(
						[
							'comment_post_ID'  => $person_id,
							'comment_content'  => wp_kses_post( $note_content ),
							'comment_type'     => \RONDO_Comment_Types::TYPE_NOTE,
							'user_id'          => $addressBookId,
							'comment_approved' => 1,
						]
					);
				}
			}
		}

		// Import/update photo (base64 or URL)
		if ( ! empty( $parsed['photo_base64'] ) || ! empty( $parsed['photo_url'] ) ) {
			$this->importPhoto( $person_id, $parsed, $first_name, $last_name );
		}

		// Log the change for sync
		$this->logChange( $addressBookId, $person_id, 'modified' );

		error_log( "CardDAV: Updated person ID {$person_id} - {$first_name} {$last_name}" );

		// Re-enable WordPress hooks
		self::set_skip_hooks( false );

		// Return new etag
		$person = get_post( $person_id );
		return $this->generateEtag( $person );
	}

	/**
	 * Delete a card
	 *
	 * @param mixed $addressBookId Address book ID
	 * @param string $cardUri Card URI
	 * @return bool True if deleted
	 */
	public function deleteCard( $addressBookId, $cardUri ) {
		$person_id = $this->getPersonIdFromUri( $cardUri );

		error_log( "CardDAV: Deleting card for user {$addressBookId}, URI: {$cardUri}, Person ID: " . ( $person_id ?: 'null' ) );

		if ( ! $person_id ) {
			error_log( 'CardDAV: Delete failed - could not parse person ID from URI' );
			return false;
		}

		// Skip WordPress hooks to avoid double-logging
		self::set_skip_hooks( true );

		// Set current user
		wp_set_current_user( $addressBookId );

		$person = get_post( $person_id );

		if ( ! $person || $person->post_type !== 'person' ) {
			self::set_skip_hooks( false );
			return false;
		}

		// Verify ownership
		if ( (int) $person->post_author !== (int) $addressBookId ) {
			self::set_skip_hooks( false );
			return false;
		}

		// Log the change before deletion (store URI since post will be gone)
		$this->logChange( $addressBookId, $person_id, 'deleted', $cardUri );

		// Delete the post (move to trash)
		$result = wp_trash_post( $person_id );

		if ( $result !== false ) {
			error_log( "CardDAV: Deleted (trashed) person ID {$person_id}" );
		} else {
			error_log( "CardDAV: Delete failed for person ID {$person_id}" );
		}

		// Re-enable WordPress hooks
		self::set_skip_hooks( false );

		return $result !== false;
	}

	/**
	 * Get changes since a sync token
	 *
	 * Required for SyncSupport interface
	 *
	 * @param string $addressBookId Address book ID
	 * @param string $syncToken Sync token to compare from
	 * @param int $syncLevel Sync level (1 = infinity)
	 * @param int $limit Maximum number of results
	 * @return array Changes since the token
	 */
	public function getChangesForAddressBook( $addressBookId, $syncToken, $syncLevel, $limit = null ) {
		error_log( "CardDAV: getChangesForAddressBook called for user {$addressBookId}, syncToken: " . ( $syncToken ?: 'none' ) );

		$result = [
			'syncToken' => $this->getCurrentSyncToken( $addressBookId ),
			'added'     => [],
			'modified'  => [],
			'deleted'   => [],
		];

		// If no sync token provided, return all contacts as added
		if ( empty( $syncToken ) ) {
			error_log( 'CardDAV: No sync token - returning all contacts as added (initial sync)' );
			wp_set_current_user( $addressBookId );

			$persons = get_posts(
				[
					'post_type'      => 'person',
					'posts_per_page' => $limit ?: -1,
					'post_status'    => 'publish',
					'author'         => $addressBookId,
				]
			);

			foreach ( $persons as $person ) {
				$result['added'][] = $this->getUriForPerson( $person->ID );
			}

			error_log( 'CardDAV: Initial sync returning ' . count( $result['added'] ) . ' contacts' );
			return $result;
		}

		// Get changes since the sync token
		$changes = get_option( self::CHANGE_LOG_OPTION, [] );
		// Use string key for consistency (WP stores numeric keys as strings)
		$user_changes = $changes[ (string) $addressBookId ] ?? [];

		// Parse sync token to get timestamp
		$token_timestamp = $this->parseSyncToken( $syncToken );

		$count = 0;
		foreach ( $user_changes as $person_id => $change ) {
			if ( $limit && $count >= $limit ) {
				break;
			}

			if ( $change['timestamp'] > $token_timestamp ) {
				// Use stored URI if available (especially important for deleted cards)
				$uri = $change['uri'] ?? $this->getUriForPerson( $person_id );

				switch ( $change['type'] ) {
					case 'added':
						$result['added'][] = $uri;
						break;
					case 'modified':
						$result['modified'][] = $uri;
						break;
					case 'deleted':
						$result['deleted'][] = $uri;
						break;
				}

				++$count;
			}
		}

		error_log( 'CardDAV: getChangesForAddressBook returning - added: ' . count( $result['added'] ) . ', modified: ' . count( $result['modified'] ) . ', deleted: ' . count( $result['deleted'] ) );

		return $result;
	}

	/**
	 * Get current sync token for an address book
	 *
	 * @param int $addressBookId Address book/user ID
	 * @return string Sync token
	 */
	private function getCurrentSyncToken( $addressBookId ) {
		$tokens = get_option( self::SYNC_TOKEN_OPTION, [] );
		// Cast to string for consistent array key comparison (WP stores numeric keys as strings)
		$key = (string) $addressBookId;

		if ( ! isset( $tokens[ $key ] ) ) {
			$tokens[ $key ] = time();
			update_option( self::SYNC_TOKEN_OPTION, $tokens );
		}

		return 'sync-' . $tokens[ $key ];
	}

	/**
	 * Parse sync token to get timestamp
	 *
	 * @param string $syncToken Sync token
	 * @return int Timestamp
	 */
	private function parseSyncToken( $syncToken ) {
		if ( strpos( $syncToken, 'sync-' ) === 0 ) {
			return (int) substr( $syncToken, 5 );
		}
		return 0;
	}

	/**
	 * Log a change for sync tracking
	 *
	 * @param int $addressBookId Address book/user ID
	 * @param int $personId Person ID
	 * @param string $type Change type (added, modified, deleted)
	 * @param string|null $uri Optional URI for deleted cards
	 * @return void
	 */
	private function logChange( $addressBookId, $personId, $type, $uri = null ) {
		$stored_uri = $uri ?: $this->getUriForPerson( $personId );
		self::log_external_change( $addressBookId, $personId, $type, $stored_uri );
	}

	/**
	 * Get CTag for an address book
	 *
	 * CTag changes whenever any card in the address book changes
	 *
	 * @param int $addressBookId Address book/user ID
	 * @return string CTag
	 */
	private function getCtag( $addressBookId ) {
		// Get the most recent modification time
		wp_set_current_user( $addressBookId );

		$persons = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'author'         => $addressBookId,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		if ( ! empty( $persons ) ) {
			return md5( $persons[0]->post_modified_gmt );
		}

		return md5( 'empty-' . $addressBookId );
	}

	/**
	 * Generate ETag for a person
	 *
	 * @param \WP_Post $person Person post
	 * @return string ETag
	 */
	private function generateEtag( $person ) {
		return '"' . md5( $person->ID . $person->post_modified_gmt ) . '"';
	}

	/**
	 * Get person ID from card URI
	 *
	 * Supports both numeric URIs (123.vcf) and custom client URIs (stored in meta)
	 *
	 * @param string $cardUri Card URI
	 * @return int|null Person ID or null if not found
	 */
	private function getPersonIdFromUri( $cardUri ) {
		// First try numeric format (123.vcf)
		if ( preg_match( '/^(\d+)\.vcf$/', $cardUri, $matches ) ) {
			return (int) $matches[1];
		}

		// Try looking up by stored URI in post meta
		$posts = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_key'       => '_carddav_uri',
				'meta_value'     => $cardUri,
			]
		);

		if ( ! empty( $posts ) ) {
			return $posts[0]->ID;
		}

		return null;
	}

	/**
	 * Get the URI for a person
	 *
	 * Returns stored custom URI if available, otherwise uses post ID
	 *
	 * @param int $person_id Person post ID
	 * @return string Card URI
	 */
	private function getUriForPerson( $person_id ) {
		$stored_uri = get_post_meta( $person_id, '_carddav_uri', true );
		return $stored_uri ?: $person_id . '.vcf';
	}

	/**
	 * Extract name fields from parsed vCard data
	 *
	 * @param array $parsed Parsed vCard data
	 * @return array [ first_name, infix, last_name ]
	 */
	private function parseNameFields( $parsed ) {
		$first_name = $parsed['first_name'] ?: '';
		$infix      = $parsed['infix'] ?? '';
		$last_name  = $parsed['last_name'] ?: '';

		if ( empty( $first_name ) && empty( $last_name ) && ! empty( $parsed['full_name'] ) ) {
			$name_parts = explode( ' ', $parsed['full_name'], 2 );
			$first_name = $name_parts[0];
			$last_name  = $name_parts[1] ?? '';
		}

		return [ $first_name, $infix, $last_name ];
	}

	/**
	 * Build post title from name parts
	 *
	 * @param string $first_name First name
	 * @param string $infix Name infix
	 * @param string $last_name Last name
	 * @return string Post title
	 */
	private function buildPostTitle( $first_name, $infix, $last_name ) {
		return implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) ) ?: 'Unknown';
	}

	/**
	 * Update ACF fields from parsed vCard data
	 *
	 * @param int $post_id Person post ID
	 * @param array $parsed Parsed vCard data
	 * @param string $first_name First name
	 * @param string $infix Name infix
	 * @param string $last_name Last name
	 */
	private function updatePersonFields( $post_id, $parsed, $first_name, $infix, $last_name ) {
		update_field( 'first_name', $first_name, $post_id );
		update_field( 'infix', $infix, $post_id );
		update_field( 'last_name', $last_name, $post_id );

		$optional_fields = [ 'nickname', 'gender', 'pronouns', 'contact_info', 'addresses' ];
		foreach ( $optional_fields as $field ) {
			if ( isset( $parsed[ $field ] ) ) {
				update_field( $field, $parsed[ $field ], $post_id );
			}
		}
	}

	/**
	 * Import photo from vCard data
	 *
	 * Handles both base64 encoded photos and URL-based photos
	 *
	 * @param int $person_id Person post ID
	 * @param array $parsed Parsed vCard data
	 * @param string $first_name Person's first name
	 * @param string $last_name Person's last name
	 */
	private function importPhoto( $person_id, $parsed, $first_name, $last_name ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = null;

		// Handle base64 encoded photo
		if ( ! empty( $parsed['photo_base64'] ) ) {
			$attachment_id = $this->saveBase64Photo(
				$parsed['photo_base64'],
				$parsed['photo_type'] ?? 'jpeg',
				$person_id,
				$first_name,
				$last_name
			);
		}
		// Handle URL-based photo
		elseif ( ! empty( $parsed['photo_url'] ) ) {
			$attachment_id = $this->sideloadPhotoFromUrl(
				$parsed['photo_url'],
				$person_id,
				$first_name,
				$last_name
			);
		}

		if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
			// Delete existing thumbnail if present
			$existing_thumbnail_id = get_post_thumbnail_id( $person_id );
			if ( $existing_thumbnail_id && $existing_thumbnail_id != $attachment_id ) {
				wp_delete_attachment( $existing_thumbnail_id, true );
			}

			set_post_thumbnail( $person_id, $attachment_id );
			error_log( "CardDAV: Imported photo for person {$person_id}, attachment ID: {$attachment_id}" );
		}
	}

	/**
	 * Save base64 encoded photo as attachment
	 *
	 * @param string $base64_data Base64 encoded image data
	 * @param string $type Image type (jpeg, png, gif)
	 * @param int $person_id Person post ID
	 * @param string $first_name Person's first name
	 * @param string $last_name Person's last name
	 * @return int|null Attachment ID or null on failure
	 */
	private function saveBase64Photo( $base64_data, $type, $person_id, $first_name, $last_name ) {
		$image_data = base64_decode( $base64_data );
		if ( $image_data === false ) {
			error_log( 'CardDAV: Failed to decode base64 photo data' );
			return null;
		}

		// Determine extension
		$extension  = 'jpg';
		$type_lower = strtolower( $type );
		if ( in_array( $type_lower, [ 'png', 'gif', 'webp' ] ) ) {
			$extension = $type_lower;
		} elseif ( $type_lower === 'jpeg' ) {
			$extension = 'jpg';
		}

		// Create filename
		$filename = sanitize_title( strtolower( trim( $first_name . ' ' . $last_name ) ) );
		if ( empty( $filename ) ) {
			$filename = 'photo-' . $person_id;
		}
		$filename .= '.' . $extension;

		// Save to temp file
		$upload_dir = wp_upload_dir();
		$temp_file  = $upload_dir['basedir'] . '/' . $filename;

		if ( file_put_contents( $temp_file, $image_data ) === false ) {
			error_log( 'CardDAV: Failed to write temp photo file' );
			return null;
		}

		// Prepare file array
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $temp_file,
		];

		// Upload
		$attachment_id = media_handle_sideload( $file_array, $person_id, "{$first_name} {$last_name}" );

		// Clean up temp file if still exists
		if ( file_exists( $temp_file ) ) {
			@unlink( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			error_log( 'CardDAV: Failed to sideload photo: ' . $attachment_id->get_error_message() );
			return null;
		}

		return $attachment_id;
	}

	/**
	 * Sideload photo from URL
	 *
	 * @param string $url Photo URL
	 * @param int $person_id Person post ID
	 * @param string $first_name Person's first name
	 * @param string $last_name Person's last name
	 * @return int|null Attachment ID or null on failure
	 */
	private function sideloadPhotoFromUrl( $url, $person_id, $first_name, $last_name ) {
		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			error_log( 'CardDAV: Failed to download photo from URL: ' . $tmp->get_error_message() );
			return null;
		}

		// Determine filename
		$filename = sanitize_title( strtolower( trim( $first_name . ' ' . $last_name ) ) );
		if ( empty( $filename ) ) {
			$filename = 'photo-' . $person_id;
		}

		// Get extension from URL
		$path = parse_url( $url, PHP_URL_PATH );
		$ext  = pathinfo( $path, PATHINFO_EXTENSION );
		if ( in_array( strtolower( $ext ), [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ] ) ) {
			$filename .= '.' . strtolower( $ext );
		} else {
			$filename .= '.jpg';
		}

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, $person_id, "{$first_name} {$last_name}" );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			error_log( 'CardDAV: Failed to sideload photo: ' . $attachment_id->get_error_message() );
			return null;
		}

		return $attachment_id;
	}
}
