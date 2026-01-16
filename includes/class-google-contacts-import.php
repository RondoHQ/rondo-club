<?php
/**
 * Google Contacts CSV Import Handler
 *
 * Imports contacts from Google Contacts CSV export files.
 */

namespace Caelis\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleContacts {

	/**
	 * Import statistics
	 */
	private array $stats = [
		'contacts_imported' => 0,
		'contacts_updated'  => 0,
		'contacts_skipped'  => 0,
		'companies_created' => 0,
		'dates_created'     => 0,
		'notes_created'     => 0,
		'photos_imported'   => 0,
		'errors'            => [],
	];

	/**
	 * Company name to ID mapping
	 */
	private array $company_map = [];

	/**
	 * CSV column headers
	 */
	private array $headers = [];

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes for import
	 */
	public function register_routes() {
		register_rest_route(
			'prm/v1',
			'/import/google-contacts',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_import' ],
				'permission_callback' => [ $this, 'check_import_permission' ],
			]
		);

		register_rest_route(
			'prm/v1',
			'/import/google-contacts/validate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'validate_import' ],
				'permission_callback' => [ $this, 'check_import_permission' ],
			]
		);
	}

	/**
	 * Check if user can perform import
	 */
	public function check_import_permission() {
		return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );
	}

	/**
	 * Validate import file without importing
	 */
	public function validate_import( $request ) {
		$file = $request->get_file_params()['file'] ?? null;

		if ( ! $file || $file['error'] !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'upload_error', __( 'File upload failed.', 'caelis' ), [ 'status' => 400 ] );
		}

		$csv_content = file_get_contents( $file['tmp_name'] );

		if ( empty( $csv_content ) ) {
			return new \WP_Error( 'empty_file', __( 'File is empty.', 'caelis' ), [ 'status' => 400 ] );
		}

		// Parse CSV to validate and get summary
		$contacts = $this->parse_csv( $csv_content );

		if ( empty( $contacts ) ) {
			return new \WP_Error( 'invalid_format', __( 'No valid contacts found in CSV. Make sure you exported from Google Contacts.', 'caelis' ), [ 'status' => 400 ] );
		}

		// Check for required Google Contacts columns (supports both old and new format)
		// Old format: "Given Name", "Family Name"
		// New format: "First Name", "Last Name"
		$name_column_sets = [
			[ 'Given Name', 'Family Name' ],
			[ 'First Name', 'Last Name' ],
		];

		$has_name_columns = false;
		foreach ( $name_column_sets as $columns ) {
			foreach ( $columns as $col ) {
				if ( in_array( $col, $this->headers ) ) {
					$has_name_columns = true;
					break 2;
				}
			}
		}

		if ( ! $has_name_columns && ! in_array( 'Name', $this->headers ) ) {
			return new \WP_Error( 'invalid_format', __( 'This doesn\'t appear to be a Google Contacts export. Missing name columns.', 'caelis' ), [ 'status' => 400 ] );
		}

		$summary    = $this->get_import_summary( $contacts );
		$duplicates = $this->find_potential_duplicates( $contacts );

		return rest_ensure_response(
			[
				'valid'      => true,
				'version'    => 'google-contacts-csv',
				'summary'    => $summary,
				'duplicates' => $duplicates,
			]
		);
	}

	/**
	 * Find potential duplicates for contacts in the CSV
	 */
	private function find_potential_duplicates( array $contacts ): array {
		$duplicates = [];

		foreach ( $contacts as $index => $contact ) {
			$first_name = $this->get_field( $contact, [ 'Given Name', 'First Name' ] );
			$last_name  = $this->get_field( $contact, [ 'Family Name', 'Last Name' ] );

			// Fallback to Name field
			if ( empty( $first_name ) && empty( $last_name ) && ! empty( $contact['Name'] ) ) {
				$name_parts = explode( ' ', trim( $contact['Name'] ), 2 );
				$first_name = $name_parts[0] ?? '';
				$last_name  = $name_parts[1] ?? '';
			}

			if ( empty( $first_name ) && empty( $last_name ) ) {
				continue;
			}

			$full_name   = trim( $first_name . ' ' . $last_name );
			$existing_id = $this->find_existing_person( $first_name, $last_name );

			if ( $existing_id ) {
				// Get existing person details for display
				$existing_person = $this->get_person_details( $existing_id );

				// Get CSV contact preview
				$csv_org   = $this->get_field( $contact, [ 'Organization 1 - Name', 'Organization Name' ] );
				$csv_email = trim( $contact['E-mail 1 - Value'] ?? '' );

				$duplicates[] = [
					'index'          => $index,
					'csv_name'       => $full_name,
					'csv_org'        => $csv_org,
					'csv_email'      => $csv_email,
					'existing_id'    => $existing_id,
					'existing_name'  => $existing_person['name'],
					'existing_org'   => $existing_person['organization'],
					'existing_email' => $existing_person['email'],
					'existing_photo' => $existing_person['photo'],
				];
			}
		}

		return $duplicates;
	}

	/**
	 * Get person details for duplicate display
	 */
	private function get_person_details( int $post_id ): array {
		$first_name = get_field( 'first_name', $post_id ) ?: '';
		$last_name  = get_field( 'last_name', $post_id ) ?: '';
		$name       = trim( $first_name . ' ' . $last_name );

		// Get organization from work history
		$organization = '';
		$work_history = get_field( 'work_history', $post_id );
		if ( ! empty( $work_history ) ) {
			foreach ( $work_history as $job ) {
				if ( ! empty( $job['is_current'] ) && ! empty( $job['company'] ) ) {
					$organization = get_the_title( $job['company'] );
					break;
				}
			}
		}

		// Get primary email
		$email        = '';
		$contact_info = get_field( 'contact_info', $post_id );
		if ( ! empty( $contact_info ) ) {
			foreach ( $contact_info as $info ) {
				if ( $info['contact_type'] === 'email' ) {
					$email = $info['contact_value'];
					break;
				}
			}
		}

		// Get photo URL
		$photo        = '';
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			$photo = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
		}

		return [
			'name'         => $name,
			'organization' => $organization,
			'email'        => $email,
			'photo'        => $photo,
		];
	}

	/**
	 * Get summary of what will be imported
	 */
	private function get_import_summary( array $contacts ): array {
		$valid_contacts = 0;
		$companies      = [];
		$birthdays      = 0;
		$notes          = 0;
		$photos         = 0;

		foreach ( $contacts as $contact ) {
			// Support both old format (Given Name/Family Name) and new format (First Name/Last Name)
			$first = $this->get_field( $contact, [ 'Given Name', 'First Name' ] );
			$last  = $this->get_field( $contact, [ 'Family Name', 'Last Name' ] );
			$name  = $contact['Name'] ?? '';

			if ( ! empty( $first ) || ! empty( $last ) || ! empty( $name ) ) {
				++$valid_contacts;
			}

			// Support both old format (Organization 1 - Name) and new format (Organization Name)
			$org = $this->get_field( $contact, [ 'Organization 1 - Name', 'Organization Name' ] );
			if ( ! empty( $org ) ) {
				$companies[ $org ] = true;
			}

			if ( ! empty( $contact['Birthday'] ) ) {
				++$birthdays;
			}

			if ( ! empty( $contact['Notes'] ) ) {
				++$notes;
			}

			if ( ! empty( $contact['Photo'] ) ) {
				++$photos;
			}
		}

		return [
			'contacts'        => $valid_contacts,
			'companies_count' => count( $companies ),
			'birthdays'       => $birthdays,
			'notes'           => $notes,
			'photos'          => $photos,
		];
	}

	/**
	 * Get a field value trying multiple possible column names
	 */
	private function get_field( array $contact, array $possible_names ): string {
		foreach ( $possible_names as $name ) {
			if ( ! empty( $contact[ $name ] ) ) {
				return trim( $contact[ $name ] );
			}
		}
		return '';
	}

	/**
	 * User decisions for duplicate handling
	 * Key: CSV index, Value: 'merge', 'new', or 'skip'
	 */
	private array $decisions = [];

	/**
	 * Handle the import request
	 */
	public function handle_import( $request ) {
		// Increase limits for large imports
		@set_time_limit( 600 );
		wp_raise_memory_limit( 'admin' );

		$file = $request->get_file_params()['file'] ?? null;

		if ( ! $file || $file['error'] !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'upload_error', __( 'File upload failed.', 'caelis' ), [ 'status' => 400 ] );
		}

		$csv_content = file_get_contents( $file['tmp_name'] );

		if ( empty( $csv_content ) ) {
			return new \WP_Error( 'empty_file', __( 'File is empty.', 'caelis' ), [ 'status' => 400 ] );
		}

		// Get user decisions for duplicates (passed as JSON string in 'decisions' field)
		$decisions_json = $request->get_param( 'decisions' );
		if ( ! empty( $decisions_json ) ) {
			$this->decisions = json_decode( $decisions_json, true ) ?: [];
		}

		// Parse and import contacts
		$contacts = $this->parse_csv( $csv_content );
		$this->import_contacts( $contacts );

		return rest_ensure_response(
			[
				'success' => true,
				'stats'   => $this->stats,
			]
		);
	}

	/**
	 * Parse CSV content into array of contacts
	 */
	private function parse_csv( string $content ): array {
		$contacts = [];

		// Handle BOM
		$content = preg_replace( '/^\xEF\xBB\xBF/', '', $content );

		// Split into lines, handling various line endings
		$lines = preg_split( '/\r\n|\r|\n/', $content );

		if ( empty( $lines ) ) {
			return $contacts;
		}

		// First line is headers
		$this->headers = str_getcsv( array_shift( $lines ) );

		// Parse each row
		foreach ( $lines as $line ) {
			if ( empty( trim( $line ) ) ) {
				continue;
			}

			$values = str_getcsv( $line );

			// Create associative array
			$contact = [];
			foreach ( $this->headers as $i => $header ) {
				$contact[ $header ] = $values[ $i ] ?? '';
			}

			$contacts[] = $contact;
		}

		return $contacts;
	}

	/**
	 * Import parsed contacts
	 */
	private function import_contacts( array $contacts ): void {
		foreach ( $contacts as $index => $contact ) {
			$this->import_single_contact( $contact, $index );
		}
	}

	/**
	 * Import a single contact
	 */
	private function import_single_contact( array $contact, int $index = 0 ): void {
		// Extract name - support both old format (Given Name/Family Name) and new format (First Name/Last Name)
		$first_name = $this->get_field( $contact, [ 'Given Name', 'First Name' ] );
		$last_name  = $this->get_field( $contact, [ 'Family Name', 'Last Name' ] );

		// Fallback to Name field if first/last name are empty
		if ( empty( $first_name ) && empty( $last_name ) && ! empty( $contact['Name'] ) ) {
			$name_parts = explode( ' ', trim( $contact['Name'] ), 2 );
			$first_name = $name_parts[0] ?? '';
			$last_name  = $name_parts[1] ?? '';
		}

		if ( empty( $first_name ) && empty( $last_name ) ) {
			++$this->stats['contacts_skipped'];
			return;
		}

		// Check if contact already exists
		$existing = $this->find_existing_person( $first_name, $last_name );

		// Check user decision for this contact
		$decision = $this->decisions[ $index ] ?? null;

		// Handle based on decision
		if ( $existing ) {
			if ( $decision === 'skip' ) {
				++$this->stats['contacts_skipped'];
				return;
			} elseif ( $decision === 'new' ) {
				// Force create new even though duplicate exists
				$existing = null;
			}
			// 'merge' or no decision (default): use existing
		}

		if ( $existing ) {
			$post_id = $existing;
			++$this->stats['contacts_updated'];
		} else {
			$post_id = wp_insert_post(
				[
					'post_type'   => 'person',
					'post_status' => 'publish',
					'post_title'  => trim( $first_name . ' ' . $last_name ),
					'post_author' => get_current_user_id(),
				]
			);

			if ( is_wp_error( $post_id ) ) {
				$this->stats['errors'][] = "Failed to create contact: {$first_name} {$last_name}";
				return;
			}

			++$this->stats['contacts_imported'];
		}

		// Set basic ACF fields
		update_field( 'first_name', $first_name, $post_id );
		update_field( 'last_name', $last_name, $post_id );

		// Nickname
		$nickname = trim( $contact['Nickname'] ?? '' );
		if ( ! empty( $nickname ) ) {
			update_field( 'nickname', $nickname, $post_id );
		}

		// Handle company/work history - support both old format (Organization 1 - Name) and new format (Organization Name)
		$org_name   = $this->get_field( $contact, [ 'Organization 1 - Name', 'Organization Name' ] );
		$job_title  = $this->get_field( $contact, [ 'Organization 1 - Title', 'Organization Title' ] );
		$department = $this->get_field( $contact, [ 'Organization 1 - Department', 'Organization Department' ] );

		if ( ! empty( $org_name ) || ! empty( $job_title ) ) {
			$company_id = null;
			if ( ! empty( $org_name ) ) {
				$company_id = $this->get_or_create_company( $org_name );
			}

			if ( $company_id || $job_title ) {
				// Combine job title and department if both exist
				$full_title = $job_title;
				if ( ! empty( $department ) && ! empty( $job_title ) ) {
					$full_title = $job_title . ' (' . $department . ')';
				} elseif ( ! empty( $department ) ) {
					$full_title = $department;
				}

				$work_history = [
					[
						'company'    => $company_id,
						'job_title'  => $full_title,
						'is_current' => true,
					],
				];
				update_field( 'work_history', $work_history, $post_id );
			}
		}

		// Import contact info
		$this->import_contact_info( $post_id, $contact );

		// Import birthday
		$birthday = trim( $contact['Birthday'] ?? '' );
		if ( ! empty( $birthday ) ) {
			$parsed_date = $this->parse_date( $birthday );
			if ( $parsed_date ) {
				$this->import_birthday( $post_id, $parsed_date, $first_name, $last_name );
			}
		}

		// Import notes
		$notes = trim( $contact['Notes'] ?? '' );
		if ( ! empty( $notes ) ) {
			$this->import_note( $post_id, $notes );
		}

		// Import photo
		$photo_url = trim( $contact['Photo'] ?? '' );
		if ( ! empty( $photo_url ) && filter_var( $photo_url, FILTER_VALIDATE_URL ) && ! has_post_thumbnail( $post_id ) ) {
			$this->import_photo( $post_id, $photo_url, $first_name, $last_name );
		}
	}

	/**
	 * Import contact information (emails, phones, addresses)
	 */
	private function import_contact_info( int $post_id, array $contact ): void {
		$contact_info = [];

		// Import emails (Google uses E-mail 1 - Value, E-mail 2 - Value, etc.)
		// Type/Label field can be "E-mail X - Type" (old) or "E-mail X - Label" (new)
		for ( $i = 1; $i <= 5; $i++ ) {
			$email = trim( $contact[ "E-mail {$i} - Value" ] ?? '' );
			$type  = $this->get_field( $contact, [ "E-mail {$i} - Type", "E-mail {$i} - Label" ] );

			if ( ! empty( $email ) ) {
				$contact_info[] = [
					'contact_type'  => 'email',
					'contact_label' => $this->format_label( $type ),
					'contact_value' => $email,
				];
			}
		}

		// Import phones (Google uses Phone 1 - Value, Phone 2 - Value, etc.)
		// Type/Label field can be "Phone X - Type" (old) or "Phone X - Label" (new)
		for ( $i = 1; $i <= 5; $i++ ) {
			$phone = trim( $contact[ "Phone {$i} - Value" ] ?? '' );
			$type  = strtolower( $this->get_field( $contact, [ "Phone {$i} - Type", "Phone {$i} - Label" ] ) );

			if ( ! empty( $phone ) ) {
				$contact_type = 'phone';
				if ( strpos( $type, 'mobile' ) !== false || strpos( $type, 'cell' ) !== false ) {
					$contact_type = 'mobile';
				}

				$contact_info[] = [
					'contact_type'  => $contact_type,
					'contact_label' => $this->format_label( $type ),
					'contact_value' => $phone,
				];
			}
		}

		// Import websites (Google uses Website 1 - Value, etc.)
		// Type/Label field can be "Website X - Type" (old) or "Website X - Label" (new)
		for ( $i = 1; $i <= 3; $i++ ) {
			$url  = trim( $contact[ "Website {$i} - Value" ] ?? '' );
			$type = $this->get_field( $contact, [ "Website {$i} - Type", "Website {$i} - Label" ] );

			if ( ! empty( $url ) ) {
				$contact_info[] = [
					'contact_type'  => $this->detect_url_type( $url ),
					'contact_label' => $this->format_label( $type ),
					'contact_value' => $url,
				];
			}
		}

		if ( ! empty( $contact_info ) ) {
			update_field( 'contact_info', $contact_info, $post_id );
		}

		// Import addresses to the dedicated addresses field
		// Google uses Address X - Street, City, Region (state), Postal Code, Country
		$addresses = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$street      = trim( $contact[ "Address {$i} - Street" ] ?? '' );
			$city        = trim( $contact[ "Address {$i} - City" ] ?? '' );
			$state       = trim( $contact[ "Address {$i} - Region" ] ?? '' );
			$postal_code = trim( $contact[ "Address {$i} - Postal Code" ] ?? '' );
			$country     = trim( $contact[ "Address {$i} - Country" ] ?? '' );
			$type        = $this->get_field( $contact, [ "Address {$i} - Type", "Address {$i} - Label" ] );

			// Only add if there's at least some address data
			if ( ! empty( $street ) || ! empty( $city ) || ! empty( $state ) || ! empty( $postal_code ) || ! empty( $country ) ) {
				$addresses[] = [
					'address_label' => $this->format_label( $type ),
					'street'        => $street,
					'postal_code'   => $postal_code,
					'city'          => $city,
					'state'         => $state,
					'country'       => $country,
				];
			}
		}

		if ( ! empty( $addresses ) ) {
			update_field( 'addresses', $addresses, $post_id );
		}
	}

	/**
	 * Format label for display
	 */
	private function format_label( string $type ): string {
		$type = strtolower( trim( $type ) );

		// Remove common prefixes used by Google (e.g., "* Other", "* Work", "* myContacts")
		$type = preg_replace( '/^\*\s*/', '', $type );
		$type = str_replace( [ ':: ', '::: ' ], '', $type );

		// Skip generic labels
		if ( in_array( $type, [ '', 'other', 'custom', 'mycontacts' ] ) ) {
			return '';
		}

		return ucfirst( $type );
	}

	/**
	 * Detect URL type from URL content
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
	 * Parse date from various formats
	 */
	private function parse_date( string $date ): string {
		$date = trim( $date );

		// Try various formats Google might use
		// YYYY-MM-DD (full date)
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match ) ) {
			return $date;
		}

		// --MM-DD (Google format for date without year, e.g., --10-31)
		if ( preg_match( '/^--(\d{2})-(\d{2})$/', $date, $match ) ) {
			// Use a placeholder year (0000) to indicate year is unknown
			// The system will treat this as a recurring date
			return sprintf( '0000-%s-%s', $match[1], $match[2] );
		}

		// MM/DD/YYYY or M/D/YYYY (US format)
		if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $match ) ) {
			return sprintf( '%s-%02d-%02d', $match[3], $match[1], $match[2] );
		}

		// DD/MM/YYYY (European format with dots)
		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $match ) ) {
			return sprintf( '%s-%02d-%02d', $match[3], $match[2], $match[1] );
		}

		// DD-MM-YYYY (European format with dashes)
		if ( preg_match( '/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $date, $match ) ) {
			return sprintf( '%s-%02d-%02d', $match[3], $match[2], $match[1] );
		}

		// Try strtotime as fallback.
		$timestamp = strtotime( $date );
		if ( $timestamp ) {
			return gmdate( 'Y-m-d', $timestamp );
		}

		return '';
	}

	/**
	 * Import birthday as important_date
	 */
	private function import_birthday( int $post_id, string $date, string $first_name, string $last_name ): void {
		$full_name = trim( $first_name . ' ' . $last_name );

		// Check if birthday already exists
		$existing = get_posts(
			[
				'post_type'      => 'important_date',
				'posts_per_page' => 1,
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

		$title = sprintf( __( "%s's Birthday", 'caelis' ), $full_name );

		$date_post_id = wp_insert_post(
			[
				'post_type'   => 'important_date',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			]
		);

		if ( is_wp_error( $date_post_id ) ) {
			return;
		}

		update_field( 'date_value', $date, $date_post_id );
		update_field( 'is_recurring', true, $date_post_id );
		update_field( 'related_people', [ $post_id ], $date_post_id );

		// Ensure the birthday term exists
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
	 * Import note as comment
	 */
	private function import_note( int $post_id, string $content ): void {
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $post_id,
				'comment_content'  => $content,
				'comment_type'     => \PRM_Comment_Types::TYPE_NOTE,
				'user_id'          => get_current_user_id(),
				'comment_approved' => 1,
				'comment_date'     => current_time( 'mysql' ),
				'comment_date_gmt' => current_time( 'mysql', true ),
			]
		);

		if ( $comment_id ) {
			++$this->stats['notes_created'];
		}
	}

	/**
	 * Import photo from URL
	 */
	private function import_photo( int $post_id, string $url, string $first_name, string $last_name ): void {
		$attachment_id = $this->sideload_image( $url, $post_id, "{$first_name} {$last_name}" );

		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
			++$this->stats['photos_imported'];
		}
	}

	/**
	 * Sideload image from URL
	 */
	private function sideload_image( string $url, int $post_id, string $description ): ?int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return null;
		}

		// Create filename from person name
		$filename = sanitize_title( strtolower( $description ) ) . '.jpg';

		// Try to get extension from URL
		$path = parse_url( $url, PHP_URL_PATH );
		if ( $path ) {
			$ext = pathinfo( $path, PATHINFO_EXTENSION );
			if ( in_array( strtolower( $ext ), [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ] ) ) {
				$filename = sanitize_title( strtolower( $description ) ) . '.' . strtolower( $ext );
			}
		}

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
	 * Find an existing person by name
	 *
	 * Note: We only search within posts the current user can access (their own + shared)
	 * to avoid matching people from other users' contact lists.
	 */
	private function find_existing_person( string $first_name, string $last_name ): ?int {
		$user_id = get_current_user_id();

		// Get accessible post IDs for this user (bypass pre_get_posts filter)
		$accessible_ids = $this->get_user_accessible_person_ids( $user_id );

		if ( empty( $accessible_ids ) ) {
			return null;
		}

		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'posts_per_page'   => 1,
				'post_status'      => 'publish',
				'post__in'         => $accessible_ids,
				'suppress_filters' => true, // Bypass access control filter
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'     => 'first_name',
						'value'   => $first_name,
						'compare' => '=',
					],
					[
						'key'     => 'last_name',
						'value'   => $last_name,
						'compare' => '=',
					],
				],
			]
		);

		if ( $query->have_posts() ) {
			return $query->posts[0]->ID;
		}

		return null;
	}

	/**
	 * Get person IDs accessible by a user
	 */
	private function get_user_accessible_person_ids( int $user_id ): array {
		global $wpdb;

		// Skip access control for admins - return all people
		if ( user_can( $user_id, 'manage_options' ) ) {
			return $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'person' 
                 AND post_status = 'publish'"
			);
		}

		// Get posts authored by user
		$authored = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'person' 
             AND post_status = 'publish' 
             AND post_author = %d",
				$user_id
			)
		);

		return $authored;
	}

	/**
	 * Get or create a company
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
				'post_author' => get_current_user_id(),
			]
		);

		if ( ! is_wp_error( $post_id ) ) {
			$this->company_map[ $name ] = $post_id;
			++$this->stats['companies_created'];
			return $post_id;
		}

		return 0;
	}
}
