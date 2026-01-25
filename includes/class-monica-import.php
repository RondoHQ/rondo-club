<?php
/**
 * Monica CRM Import Handler
 *
 * Imports data from Monica CRM SQL export files.
 */

namespace Stadion\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Monica {

	/**
	 * Monica ID to WordPress ID mapping
	 */
	private array $contact_map         = [];
	private array $company_map         = [];
	private array $photo_map           = [];
	private array $relationship_types  = [];
	private array $contact_field_types = [];
	private array $life_event_types    = [];
	private array $genders             = [];

	/**
	 * Monica instance URL for photo sideloading
	 */
	private string $monica_url = '';

	/**
	 * Import statistics
	 */
	private array $stats = [
		'contacts_imported'     => 0,
		'contacts_updated'      => 0,
		'contacts_skipped'      => 0,
		'companies_created'     => 0,
		'relationships_created' => 0,
		'dates_created'         => 0,
		'notes_created'         => 0,
		'photos_imported'       => 0,
		'errors'                => [],
	];

	/**
	 * Parsed SQL data
	 */
	private array $tables = [];

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes for import
	 */
	public function register_routes() {
		register_rest_route(
			'stadion/v1',
			'/import/monica',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_import' ],
				'permission_callback' => [ $this, 'check_import_permission' ],
			]
		);

		register_rest_route(
			'stadion/v1',
			'/import/monica/validate',
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
			return new \WP_Error( 'upload_error', __( 'File upload failed.', 'stadion' ), [ 'status' => 400 ] );
		}

		$sql_content = file_get_contents( $file['tmp_name'] );

		if ( empty( $sql_content ) ) {
			return new \WP_Error( 'empty_file', __( 'File is empty.', 'stadion' ), [ 'status' => 400 ] );
		}

		// Check if it's a valid Monica SQL export
		if ( strpos( $sql_content, 'INSERT IGNORE INTO `contacts`' ) === false ) {
			return new \WP_Error( 'invalid_format', __( 'Invalid Monica SQL export format. File must contain contacts table.', 'stadion' ), [ 'status' => 400 ] );
		}

		// Parse the SQL to get summary
		$this->parse_sql( $sql_content );
		$summary = $this->get_import_summary();

		return rest_ensure_response(
			[
				'valid'   => true,
				'version' => 'sql',
				'summary' => $summary,
			]
		);
	}

	/**
	 * Get summary of what will be imported
	 */
	private function get_import_summary(): array {
		$contacts      = $this->tables['contacts'] ?? [];
		$relationships = $this->tables['relationships'] ?? [];
		$notes         = $this->tables['notes'] ?? [];
		$special_dates = $this->tables['special_dates'] ?? [];
		$life_events   = $this->tables['life_events'] ?? [];
		$photos        = $this->tables['photos'] ?? [];

		// Count non-partial contacts
		$contact_count = 0;
		$companies     = [];
		foreach ( $contacts as $contact ) {
			if ( empty( $contact['is_partial'] ) || $contact['is_partial'] == '0' ) {
				++$contact_count;
				if ( ! empty( $contact['company'] ) ) {
					$companies[ $contact['company'] ] = true;
				}
			}
		}

		return [
			'contacts'        => $contact_count,
			'relationships'   => count( $relationships ),
			'notes'           => count( $notes ),
			'reminders'       => count( $special_dates ),
			'life_events'     => count( $life_events ),
			'photos'          => count( $photos ),
			'companies_count' => count( $companies ),
		];
	}

	/**
	 * Handle the import request
	 */
	public function handle_import( $request ) {
		// Increase limits for large imports
		@set_time_limit( 600 );
		wp_raise_memory_limit( 'admin' );

		$file       = $request->get_file_params()['file'] ?? null;
		$monica_url = $request->get_param( 'monica_url' ) ?? '';

		if ( ! $file || $file['error'] !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'upload_error', __( 'File upload failed.', 'stadion' ), [ 'status' => 400 ] );
		}

		if ( empty( $monica_url ) ) {
			return new \WP_Error( 'missing_url', __( 'Monica instance URL is required for photo import.', 'stadion' ), [ 'status' => 400 ] );
		}

		// Normalize Monica URL
		$this->monica_url = rtrim( $monica_url, '/' );

		$sql_content = file_get_contents( $file['tmp_name'] );

		if ( empty( $sql_content ) ) {
			return new \WP_Error( 'empty_file', __( 'File is empty.', 'stadion' ), [ 'status' => 400 ] );
		}

		// Parse the SQL
		$this->parse_sql( $sql_content );

		// Run import
		$this->import_data();

		return rest_ensure_response(
			[
				'success' => true,
				'stats'   => $this->stats,
			]
		);
	}

	/**
	 * Parse SQL INSERT statements
	 */
	private function parse_sql( string $sql ): void {
		$tables_to_parse = [
			'contacts',
			'photos',
			'contact_photo',
			'relationships',
			'relationship_types',
			'notes',
			'special_dates',
			'contact_fields',
			'contact_field_types',
			'addresses',
			'places',
			'tags',
			'contact_tag',
			'life_events',
			'life_event_types',
			'genders',
		];

		foreach ( $tables_to_parse as $table ) {
			$this->tables[ $table ] = $this->parse_table_inserts( $sql, $table );
		}

		// Build lookup maps
		$this->build_lookup_maps();
	}

	/**
	 * Parse INSERT statements for a specific table
	 */
	private function parse_table_inserts( string $sql, string $table ): array {
		$results = [];

		// Match INSERT IGNORE INTO `table_name` (`columns`) VALUES ...
		$pattern = '/INSERT IGNORE INTO `' . preg_quote( $table, '/' ) . '` \(([^)]+)\) VALUES\s*\n?((?:\s*\([^)]+\),?\s*\n?)+)/i';

		if ( ! preg_match( $pattern, $sql, $match ) ) {
			return $results;
		}

		// Parse column names
		$columns_str = $match[1];
		$columns     = array_map(
			function ( $col ) {
				return trim( str_replace( '`', '', $col ) );
			},
			explode( ',', $columns_str )
		);

		// Parse value rows
		$values_str = $match[2];

		// Match each row of values
		preg_match_all( '/\(([^)]+)\)/', $values_str, $rows );

		foreach ( $rows[1] as $row ) {
			$values = $this->parse_sql_values( $row );

			if ( count( $values ) === count( $columns ) ) {
				$results[] = array_combine( $columns, $values );
			}
		}

		return $results;
	}

	/**
	 * Parse SQL values from a row string
	 */
	private function parse_sql_values( string $row ): array {
		$values      = [];
		$current     = '';
		$in_string   = false;
		$escape_next = false;
		$string_char = '';

		for ( $i = 0; $i < strlen( $row ); $i++ ) {
			$char = $row[ $i ];

			if ( $escape_next ) {
				$current    .= $char;
				$escape_next = false;
				continue;
			}

			if ( $char === '\\' ) {
				$escape_next = true;
				$current    .= $char;
				continue;
			}

			if ( ! $in_string && ( $char === "'" || $char === '"' ) ) {
				$in_string   = true;
				$string_char = $char;
				continue;
			}

			if ( $in_string && $char === $string_char ) {
				$in_string   = false;
				$string_char = '';
				continue;
			}

			if ( ! $in_string && $char === ',' ) {
				$values[] = $this->clean_sql_value( trim( $current ) );
				$current  = '';
				continue;
			}

			$current .= $char;
		}

		// Add the last value
		if ( $current !== '' || count( $values ) > 0 ) {
			$values[] = $this->clean_sql_value( trim( $current ) );
		}

		return $values;
	}

	/**
	 * Clean up a SQL value
	 */
	private function clean_sql_value( string $value ): string {
		// Handle NULL
		if ( strtoupper( $value ) === 'NULL' ) {
			return '';
		}

		// Remove surrounding quotes if present
		if ( ( substr( $value, 0, 1 ) === "'" && substr( $value, -1 ) === "'" ) ||
			( substr( $value, 0, 1 ) === '"' && substr( $value, -1 ) === '"' ) ) {
			$value = substr( $value, 1, -1 );
		}

		// Unescape common SQL escapes
		$value = str_replace( "\\'", "'", $value );
		$value = str_replace( '\\"', '"', $value );
		$value = str_replace( '\\\\', '\\', $value );
		$value = str_replace( '\\n', "\n", $value );
		$value = str_replace( '\\r', "\r", $value );

		return $value;
	}

	/**
	 * Build lookup maps from parsed data
	 */
	private function build_lookup_maps(): void {
		// Build relationship types map (id => name)
		foreach ( $this->tables['relationship_types'] ?? [] as $type ) {
			$this->relationship_types[ $type['id'] ] = $type['name'];
		}

		// Build contact field types map (id => type)
		foreach ( $this->tables['contact_field_types'] ?? [] as $type ) {
			$this->contact_field_types[ $type['id'] ] = $type['type'] ?? $type['name'];
		}

		// Build photo map (id => data)
		foreach ( $this->tables['photos'] ?? [] as $photo ) {
			$this->photo_map[ $photo['id'] ] = $photo;
		}

		// Build life event types map (id => key/slug)
		foreach ( $this->tables['life_event_types'] ?? [] as $type ) {
			$this->life_event_types[ $type['id'] ] = $type['default_life_event_type_key'] ?? 'other';
		}

		// Build genders map (id => type)
		foreach ( $this->tables['genders'] ?? [] as $gender ) {
			$this->genders[ $gender['id'] ] = $gender['type'] ?? '';
		}
	}

	/**
	 * Main import logic
	 */
	private function import_data(): void {
		$contacts = $this->tables['contacts'] ?? [];

		// First pass: Import all non-partial contacts
		foreach ( $contacts as $contact ) {
			$this->import_contact( $contact );
		}

		// Second pass: Import relationships
		foreach ( $this->tables['relationships'] ?? [] as $relationship ) {
			$this->import_relationship( $relationship );
		}
	}

	/**
	 * Import a single contact
	 */
	private function import_contact( array $contact ): void {
		$monica_id = $contact['id'];

		// Skip partial contacts
		if ( ! empty( $contact['is_partial'] ) && $contact['is_partial'] != '0' ) {
			++$this->stats['contacts_skipped'];
			return;
		}

		$first_name = $contact['first_name'] ?? '';
		$last_name  = $contact['last_name'] ?? '';

		if ( empty( $first_name ) && empty( $last_name ) ) {
			++$this->stats['contacts_skipped'];
			return;
		}

		// Check if contact already exists
		$existing  = $this->find_existing_person( $first_name, $last_name );
		$is_update = false;

		if ( $existing ) {
			$post_id                         = $existing;
			$is_update                       = true;
			$this->contact_map[ $monica_id ] = $post_id;
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

			$this->contact_map[ $monica_id ] = $post_id;
			++$this->stats['contacts_imported'];
		}

		// Set ACF fields
		update_field( 'first_name', $first_name, $post_id );
		update_field( 'last_name', $last_name, $post_id );

		if ( ! empty( $contact['nickname'] ) ) {
			update_field( 'nickname', $contact['nickname'], $post_id );
		}

		// Import gender
		$gender_id = $contact['gender_id'] ?? null;
		if ( $gender_id && isset( $this->genders[ $gender_id ] ) ) {
			$monica_gender_type = $this->genders[ $gender_id ];
			// Map Monica gender types to our gender values
			// M -> male, F -> female, O -> prefer_not_to_say
			$gender_value = '';
			switch ( $monica_gender_type ) {
				case 'M':
					$gender_value = 'male';
					break;
				case 'F':
					$gender_value = 'female';
					break;
				case 'O':
					$gender_value = 'prefer_not_to_say';
					break;
			}
			if ( $gender_value ) {
				update_field( 'gender', $gender_value, $post_id );
			}
		}

		if ( ! empty( $contact['is_starred'] ) && $contact['is_starred'] != '0' ) {
			update_field( 'is_favorite', true, $post_id );
		}

		// Handle company/work history
		if ( ! empty( $contact['company'] ) || ! empty( $contact['job'] ) ) {
			$company_name = $contact['company'] ?? '';
			$job_title    = $contact['job'] ?? '';

			$company_id = null;
			if ( ! empty( $company_name ) ) {
				$company_id = $this->get_or_create_company( $company_name );
			}

			if ( $company_id || $job_title ) {
				$work_history = [
					[
						'company'    => $company_id,
						'job_title'  => $job_title,
						'is_current' => true,
					],
				];
				update_field( 'work_history', $work_history, $post_id );
			}
		}

		// Import tags
		$this->import_contact_tags( $post_id, $monica_id );

		// Import contact fields (email, phone, etc.)
		$this->import_contact_fields( $post_id, $monica_id );

		// Import addresses
		$this->import_addresses( $post_id, $monica_id );

		// Import notes
		$this->import_notes( $post_id, $monica_id );

		// Import birthdate and special dates
		$this->import_special_dates( $post_id, $monica_id, $first_name, $last_name, $contact );

		// Import life events
		$this->import_life_events( $post_id, $monica_id, $first_name, $last_name );

		// Import photo
		if ( ! has_post_thumbnail( $post_id ) ) {
			$this->import_contact_photo( $post_id, $monica_id, $first_name, $last_name, $contact );
		}
	}

	/**
	 * Import tags for a contact
	 */
	private function import_contact_tags( int $post_id, string $monica_id ): void {
		$contact_tags = array_filter(
			$this->tables['contact_tag'] ?? [],
			function ( $ct ) use ( $monica_id ) {
				return $ct['contact_id'] === $monica_id;
			}
		);

		if ( empty( $contact_tags ) ) {
			return;
		}

		$tags_by_id = [];
		foreach ( $this->tables['tags'] ?? [] as $tag ) {
			$tags_by_id[ $tag['id'] ] = $tag['name'];
		}

		$term_ids = [];
		foreach ( $contact_tags as $ct ) {
			$tag_name = $tags_by_id[ $ct['tag_id'] ] ?? '';
			if ( empty( $tag_name ) ) {
				continue;
			}

			$term = term_exists( $tag_name, 'person_label' );
			if ( ! $term ) {
				$term = wp_insert_term( $tag_name, 'person_label' );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$term_id    = is_array( $term ) ? $term['term_id'] : $term;
				$term_ids[] = (int) $term_id;
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_post_terms( $post_id, $term_ids, 'person_label', false );
		}
	}

	/**
	 * Import contact fields (email, phone, etc.)
	 */
	private function import_contact_fields( int $post_id, string $monica_id ): void {
		$fields = array_filter(
			$this->tables['contact_fields'] ?? [],
			function ( $f ) use ( $monica_id ) {
				return $f['contact_id'] === $monica_id;
			}
		);

		if ( empty( $fields ) ) {
			return;
		}

		$contact_info = [];
		foreach ( $fields as $field ) {
			$type_id = $field['contact_field_type_id'];
			$value   = $field['data'] ?? '';

			if ( empty( $value ) ) {
				continue;
			}

			// Determine type
			$type_name    = $this->contact_field_types[ $type_id ] ?? '';
			$contact_type = $this->map_field_type( $type_name, $value );

			$contact_info[] = [
				'contact_type'  => $contact_type,
				'contact_label' => '',
				'contact_value' => $value,
			];
		}

		if ( ! empty( $contact_info ) ) {
			update_field( 'contact_info', $contact_info, $post_id );
		}
	}

	/**
	 * Map Monica field type to CRM type
	 */
	private function map_field_type( string $type_name, string $value ): string {
		$type_lower = strtolower( $type_name );

		if ( $type_lower === 'email' || strpos( $type_lower, 'email' ) !== false ) {
			return 'email';
		}
		if ( $type_lower === 'phone' || strpos( $type_lower, 'phone' ) !== false ) {
			return 'phone';
		}
		if ( strpos( $type_lower, 'whatsapp' ) !== false ) {
			return 'phone';
		}
		if ( strpos( $type_lower, 'linkedin' ) !== false ) {
			return 'linkedin';
		}
		if ( strpos( $type_lower, 'twitter' ) !== false || strpos( $type_lower, 'x.com' ) !== false ) {
			return 'twitter';
		}
		if ( strpos( $type_lower, 'facebook' ) !== false ) {
			return 'facebook';
		}
		if ( strpos( $type_lower, 'telegram' ) !== false ) {
			return 'telegram';
		}

		// Fallback: guess from value
		if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			return 'email';
		}
		if ( preg_match( '/^[+\d\s\-()]+$/', $value ) && strlen( preg_replace( '/\D/', '', $value ) ) >= 7 ) {
			return 'phone';
		}
		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return 'website';
		}

		return 'other';
	}

	/**
	 * Import addresses for a contact
	 */
	private function import_addresses( int $post_id, string $monica_id ): void {
		$addresses = array_filter(
			$this->tables['addresses'] ?? [],
			function ( $a ) use ( $monica_id ) {
				return $a['contact_id'] === $monica_id;
			}
		);

		if ( empty( $addresses ) ) {
			return;
		}

		// Build places lookup
		$places = [];
		foreach ( $this->tables['places'] ?? [] as $place ) {
			$places[ $place['id'] ] = $place;
		}

		$structured_addresses = [];

		foreach ( $addresses as $address ) {
			$place_id = $address['place_id'] ?? '';
			$place    = $places[ $place_id ] ?? [];

			$street      = trim( $place['street'] ?? '' );
			$city        = trim( $place['city'] ?? '' );
			$state       = trim( $place['province'] ?? '' );
			$postal_code = trim( $place['postal_code'] ?? '' );
			$country     = trim( $place['country'] ?? '' );

			// Only add if there's at least some address data
			if ( ! empty( $street ) || ! empty( $city ) || ! empty( $state ) || ! empty( $postal_code ) || ! empty( $country ) ) {
				$structured_addresses[] = [
					'address_label' => $address['name'] ?? '',
					'street'        => $street,
					'postal_code'   => $postal_code,
					'city'          => $city,
					'state'         => $state,
					'country'       => $country,
				];
			}
		}

		if ( ! empty( $structured_addresses ) ) {
			update_field( 'addresses', $structured_addresses, $post_id );
		}
	}

	/**
	 * Import notes for a contact
	 */
	private function import_notes( int $post_id, string $monica_id ): void {
		$notes = array_filter(
			$this->tables['notes'] ?? [],
			function ( $n ) use ( $monica_id ) {
				return $n['contact_id'] === $monica_id;
			}
		);

		foreach ( $notes as $note ) {
			$content = $note['body'] ?? '';
			if ( empty( $content ) ) {
				continue;
			}

			$created_at = $note['created_at'] ?? current_time( 'mysql' );

			$comment_id = wp_insert_comment(
				[
					'comment_post_ID'  => $post_id,
					'comment_content'  => $content,
					'comment_type'     => \STADION_Comment_Types::TYPE_NOTE,
					'user_id'          => get_current_user_id(),
					'comment_approved' => 1,
					'comment_date'     => $this->format_date( $created_at ),
					'comment_date_gmt' => $this->format_date( $created_at, true ),
				]
			);

			if ( $comment_id ) {
				++$this->stats['notes_created'];
			}
		}
	}

	/**
	 * Import special dates (birthdays, etc.)
	 */
	private function import_special_dates( int $post_id, string $monica_id, string $first_name, string $last_name, array $contact ): void {
		$full_name = trim( $first_name . ' ' . $last_name );

		// Import birthday from contacts table
		$birthday_date_id = $contact['birthday_special_date_id'] ?? '';
		if ( ! empty( $birthday_date_id ) ) {
			$this->import_birthday( $post_id, $birthday_date_id, $full_name );
		}

		// Import other special dates linked to this contact
		$dates = array_filter(
			$this->tables['special_dates'] ?? [],
			function ( $d ) use ( $monica_id ) {
				return $d['contact_id'] === $monica_id;
			}
		);

		foreach ( $dates as $date ) {
			// Skip if this is the birthday date (already handled)
			if ( $date['id'] === $birthday_date_id ) {
				continue;
			}

			$date_value = $date['date'] ?? '';
			if ( empty( $date_value ) ) {
				continue;
			}

			// These are miscellaneous special dates - import as "other" type
			$this->import_special_date( $post_id, $date, $full_name );
		}
	}

	/**
	 * Import birthday
	 */
	private function import_birthday( int $post_id, string $date_id, string $full_name ): void {
		// Find the special date
		$date_entry = null;
		foreach ( $this->tables['special_dates'] ?? [] as $d ) {
			if ( $d['id'] === $date_id ) {
				$date_entry = $d;
				break;
			}
		}

		if ( ! $date_entry || empty( $date_entry['date'] ) ) {
			return;
		}

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

		$title = sprintf( __( "%s's Birthday", 'stadion' ), $full_name );

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

		update_field( 'date_value', $this->format_date_for_acf( $date_entry['date'] ), $date_post_id );
		update_field( 'is_recurring', true, $date_post_id );
		update_field( 'related_people', [ $post_id ], $date_post_id );

		// Ensure the birthday term exists, create if needed
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
	 * Import a special date
	 */
	private function import_special_date( int $post_id, array $date, string $full_name ): void {
		$date_value = $date['date'] ?? '';
		if ( empty( $date_value ) ) {
			return;
		}

		$title = sprintf( __( '%s - Special Date', 'stadion' ), $full_name );

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

		update_field( 'date_value', $this->format_date_for_acf( $date_value ), $date_post_id );
		update_field( 'is_recurring', true, $date_post_id );
		update_field( 'related_people', [ $post_id ], $date_post_id );

		// Ensure the other term exists, create if needed
		$term = term_exists( 'other', 'date_type' );
		if ( ! $term ) {
			$term = wp_insert_term( 'Other', 'date_type', [ 'slug' => 'other' ] );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			$term_id = is_array( $term ) ? $term['term_id'] : $term;
			wp_set_post_terms( $date_post_id, [ (int) $term_id ], 'date_type' );
		}

		++$this->stats['dates_created'];
	}

	/**
	 * Import life events for a contact
	 */
	private function import_life_events( int $post_id, string $monica_id, string $first_name, string $last_name ): void {
		$full_name = trim( $first_name . ' ' . $last_name );

		$life_events = array_filter(
			$this->tables['life_events'] ?? [],
			function ( $event ) use ( $monica_id ) {
				return $event['contact_id'] === $monica_id;
			}
		);

		foreach ( $life_events as $event ) {
			$this->import_life_event( $post_id, $event, $full_name );
		}
	}

	/**
	 * Import a single life event
	 */
	private function import_life_event( int $post_id, array $event, string $full_name ): void {
		$event_date = $event['happened_at'] ?? '';
		if ( empty( $event_date ) ) {
			return;
		}

		// Get the life event type key
		$type_id  = $event['life_event_type_id'] ?? '';
		$type_key = $this->life_event_types[ $type_id ] ?? 'other';

		// Convert Monica's underscore format to our hyphen format
		$type_slug = str_replace( '_', '-', $type_key );

		// Generate a title based on the event type and any custom name
		$event_name = $event['name'] ?? '';
		if ( ! empty( $event_name ) ) {
			$title = $event_name;
		} else {
			// Generate title from type
			$type_label = ucwords( str_replace( [ '-', '_' ], ' ', $type_key ) );
			$title      = sprintf( __( "%1\$s's %2\$s", 'stadion' ), $full_name, $type_label );
		}

		// Check if this exact life event already exists (avoid duplicates).
		$date_for_query = gmdate( 'Y-m-d', strtotime( $event_date ) );
		$existing       = get_posts(
			[
				'post_type'      => 'important_date',
				'posts_per_page' => 1,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => 'related_people',
						'value'   => '"' . $post_id . '"',
						'compare' => 'LIKE',
					],
					[
						'key'     => 'date_value',
						'value'   => $date_for_query,
						'compare' => '=',
					],
				],
				'tax_query'      => [
					[
						'taxonomy' => 'date_type',
						'field'    => 'slug',
						'terms'    => $type_slug,
					],
				],
			]
		);

		if ( ! empty( $existing ) ) {
			return;
		}

		// Create the important_date post
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

		// Set the date value
		update_field( 'date_value', $this->format_date_for_acf( $event_date ), $date_post_id );

		// Life events like marriage/wedding should be recurring
		$recurring_types = [ 'marriage', 'wedding', 'engagement', 'new-relationship' ];
		$is_recurring    = in_array( $type_slug, $recurring_types );
		update_field( 'is_recurring', $is_recurring, $date_post_id );

		update_field( 'related_people', [ $post_id ], $date_post_id );

		// Add notes if present
		$notes = $event['note'] ?? '';
		if ( ! empty( $notes ) ) {
			update_field( 'notes', $notes, $date_post_id );
		}

		// Set the date type taxonomy
		$term = term_exists( $type_slug, 'date_type' );
		if ( ! $term ) {
			// Create the term if it doesn't exist
			$type_label = ucwords( str_replace( [ '-', '_' ], ' ', $type_key ) );
			$term       = wp_insert_term( $type_label, 'date_type', [ 'slug' => $type_slug ] );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			$term_id = is_array( $term ) ? $term['term_id'] : $term;
			wp_set_post_terms( $date_post_id, [ (int) $term_id ], 'date_type' );
		}

		++$this->stats['dates_created'];
	}

	/**
	 * Import contact photo by sideloading from Monica instance or Gravatar
	 */
	private function import_contact_photo( int $post_id, string $monica_id, string $first_name, string $last_name, array $contact ): void {
		$photo_url     = null;
		$avatar_source = $contact['avatar_source'] ?? '';

		// First, try to get photo from contact_photo table (uploaded photos)
		if ( $avatar_source === 'photo' ) {
			$contact_photos = array_filter(
				$this->tables['contact_photo'] ?? [],
				function ( $cp ) use ( $monica_id ) {
					return $cp['contact_id'] === $monica_id;
				}
			);

			if ( ! empty( $contact_photos ) ) {
				$photo_link = reset( $contact_photos );
				$photo_id   = $photo_link['photo_id'];
				$photo      = $this->photo_map[ $photo_id ] ?? null;

				if ( $photo ) {
					$photo_path = $photo['new_filename'] ?? '';
					if ( ! empty( $photo_path ) ) {
						$photo_url = $this->monica_url . '/storage/' . $photo_path;
					}
				}
			}
		}

		// If no photo found, try gravatar
		if ( ! $photo_url && $avatar_source === 'gravatar' ) {
			$gravatar_url = $contact['avatar_gravatar_url'] ?? '';
			if ( ! empty( $gravatar_url ) && strpos( $gravatar_url, 'gravatar.com' ) !== false ) {
				// Remove size parameter and get a larger version
				$photo_url  = preg_replace( '/\?.*$/', '', $gravatar_url );
				$photo_url .= '?s=400&d=404';
			}
		}

		if ( empty( $photo_url ) ) {
			return;
		}

		// Generate a clean filename based on the person's name
		$filename = $this->generate_photo_filename( $first_name, $last_name, $photo_url );

		// Sideload the image
		$attachment_id = $this->sideload_image( $photo_url, $post_id, "{$first_name} {$last_name}", $filename );

		if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
			++$this->stats['photos_imported'];
		} else {
			$error_msg               = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'Unknown error';
			$this->stats['errors'][] = "Failed to import photo for {$first_name} {$last_name}: {$error_msg}";
		}
	}

	/**
	 * Generate a clean filename for a photo based on the person's name
	 */
	private function generate_photo_filename( string $first_name, string $last_name, string $url ): string {
		// Create slug from name
		$name_slug = sanitize_title( strtolower( trim( $first_name . ' ' . $last_name ) ) );

		// Get extension from URL or default to jpg
		$path      = parse_url( $url, PHP_URL_PATH );
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		// Clean up extension (remove query strings, default to jpg)
		$extension = preg_replace( '/\?.*$/', '', $extension );
		if ( empty( $extension ) || ! in_array( strtolower( $extension ), [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ] ) ) {
			$extension = 'jpg';
		}

		return $name_slug . '.' . strtolower( $extension );
	}

	/**
	 * Sideload an image from URL with a custom filename
	 */
	private function sideload_image( string $url, int $post_id, string $description, string $filename = '' ): ?int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the file
		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return null;
		}

		// Use custom filename if provided, otherwise extract from URL
		if ( empty( $filename ) ) {
			$filename = basename( parse_url( $url, PHP_URL_PATH ) );
		}

		// Get file info
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp,
		];

		// Sideload the file
		$attachment_id = media_handle_sideload( $file_array, $post_id, $description );

		// Clean up temp file if sideload failed
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return null;
		}

		return $attachment_id;
	}

	/**
	 * Import a relationship
	 */
	private function import_relationship( array $relationship ): void {
		$type_id    = $relationship['relationship_type_id'] ?? '';
		$contact_is = $relationship['contact_is'] ?? '';
		$of_contact = $relationship['of_contact'] ?? '';

		// Get WordPress IDs from mapping
		$person_id  = $this->contact_map[ $contact_is ] ?? null;
		$related_id = $this->contact_map[ $of_contact ] ?? null;

		if ( ! $person_id || ! $related_id ) {
			return;
		}

		// Get relationship type name
		$type_name = $this->relationship_types[ $type_id ] ?? 'acquaintance';

		// Get the term ID for the relationship type
		$term = term_exists( $type_name, 'relationship_type' );
		if ( ! $term ) {
			$term = wp_insert_term( ucfirst( $type_name ), 'relationship_type', [ 'slug' => $type_name ] );
		}

		if ( is_wp_error( $term ) ) {
			return;
		}

		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		// Get existing relationships
		$existing = get_field( 'relationships', $person_id ) ?: [];

		// Check if relationship already exists
		foreach ( $existing as $rel ) {
			$existing_person_id = is_object( $rel['related_person'] ) ? $rel['related_person']->ID : $rel['related_person'];
			if ( $existing_person_id == $related_id ) {
				return;
			}
		}

		// Add the new relationship
		$existing[] = [
			'related_person'     => $related_id,
			'relationship_type'  => $term_id,
			'relationship_label' => '',
		];

		update_field( 'relationships', $existing, $person_id );
		++$this->stats['relationships_created'];
	}

	/**
	 * Find an existing person by name
	 */
	private function find_existing_person( string $first_name, string $last_name ): ?int {
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => [
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

	/**
	 * Format a date string for WordPress
	 */
	private function format_date( string $date, bool $gmt = false ): string {
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return current_time( 'mysql', $gmt );
		}
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Format a date string for ACF date picker (Y-m-d)
	 *
	 * @param string $date The date string to format.
	 * @return string Formatted date in Y-m-d format.
	 */
	private function format_date_for_acf( string $date ): string {
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return '';
		}
		return gmdate( 'Y-m-d', $timestamp );
	}
}
