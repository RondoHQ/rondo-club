<?php
/**
 * Demo Export Class
 *
 * Exports production data to a demo fixture file in portable JSON format.
 *
 * @package Rondo
 */

namespace Rondo\Demo;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DemoExport
 *
 * Orchestrates the export of all Rondo data to a fixture file with reference ID mapping.
 */
class DemoExport {

	/**
	 * Reference ID mappings from WordPress post IDs to fixture refs
	 *
	 * @var array
	 */
	private $ref_maps = [
		'person'          => [],
		'team'            => [],
		'commissie'       => [],
		'discipline_case' => [],
		'todo'            => [],
		'invoice'         => [],
	];

	/**
	 * Output file path
	 *
	 * @var string
	 */
	private $output_path;

	/**
	 * Anonymizer instance
	 *
	 * @var DemoAnonymizer
	 */
	private $anonymizer;

	/**
	 * Constructor
	 *
	 * @param string $output_path Path to write the JSON fixture file.
	 */
	public function __construct( $output_path ) {
		$this->output_path = $output_path;
	}

	/**
	 * Main export orchestration method
	 *
	 * @return array The complete fixture array.
	 */
	public function export() {
		WP_CLI::log( 'Starting demo data export...' );

		// Build reference ID mappings for all entities
		WP_CLI::log( 'Building reference ID mappings...' );
		$this->build_ref_maps();

		// Initialize anonymizer
		$this->anonymizer = new DemoAnonymizer();
		WP_CLI::log( 'Anonymizer initialized with seed 42' );

		// Export all entity sections
		WP_CLI::log( 'Exporting people...' );
		$people = $this->export_people();

		WP_CLI::log( 'Exporting teams...' );
		$teams = $this->export_teams();

		WP_CLI::log( 'Exporting commissies...' );
		$commissies = $this->export_commissies();

		WP_CLI::log( 'Exporting discipline cases...' );
		$discipline_cases = $this->export_discipline_cases();

		WP_CLI::log( 'Exporting invoices...' );
		$invoices = $this->export_invoices();

		WP_CLI::log( 'Exporting todos...' );
		$todos = $this->export_todos();

		WP_CLI::log( 'Exporting comments...' );
		$comments = $this->export_comments();

		WP_CLI::log( 'Exporting taxonomies...' );
		$taxonomies = $this->export_taxonomies();

		WP_CLI::log( 'Exporting settings...' );
		$settings = $this->export_settings();

		// Build meta section AFTER exporting entities (to use actual counts)
		WP_CLI::log( 'Building meta section...' );
		$meta = [
			'version'       => '1.0',
			'exported_at'   => gmdate( 'c' ),
			'source'        => 'Production export, anonymized',
			'record_counts' => [
				'people'           => count( $people ),
				'teams'            => count( $teams ),
				'commissies'       => count( $commissies ),
				'discipline_cases' => count( $discipline_cases ),
				'invoices'         => count( $invoices ),
				'todos'            => count( $todos ),
				'comments'         => count( $comments ),
			],
		];

		// Assemble complete fixture
		$fixture = [
			'meta'             => $meta,
			'people'           => $people,
			'teams'            => $teams,
			'commissies'       => $commissies,
			'discipline_cases' => $discipline_cases,
			'invoices'         => $invoices,
			'todos'            => $todos,
			'comments'         => $comments,
			'taxonomies'       => $taxonomies,
			'settings'         => $settings,
		];

		// Write to file
		WP_CLI::log( sprintf( 'Writing fixture to: %s', $this->output_path ) );
		$json = wp_json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( $json === false ) {
			WP_CLI::error( 'Failed to encode fixture to JSON' );
		}

		$result = file_put_contents( $this->output_path, $json );

		if ( $result === false ) {
			WP_CLI::error( sprintf( 'Failed to write fixture to %s', $this->output_path ) );
		}

		WP_CLI::log( 'Export complete!' );

		return $fixture;
	}

	/**
	 * Build reference ID mappings for all entity types
	 *
	 * Queries all post IDs and assigns sequential reference numbers.
	 */
	private function build_ref_maps() {
		$this->build_ref_map_for_type( 'person', 'any' );
		$this->build_ref_map_for_type( 'team', [ 'publish', 'draft', 'private' ] );
		$this->build_ref_map_for_type( 'commissie', [ 'publish', 'draft', 'private' ] );
		$this->build_ref_map_for_type( 'discipline_case', 'any' );
		$this->build_ref_map_for_type( 'rondo_todo', [ 'rondo_open', 'rondo_awaiting', 'rondo_completed' ], 'todo' );
		$this->build_ref_map_for_type( 'rondo_invoice', [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ], 'invoice' );

		WP_CLI::log(
			sprintf(
				'Reference maps built: %d people, %d teams, %d commissies, %d discipline cases, %d todos, %d invoices',
				count( $this->ref_maps['person'] ),
				count( $this->ref_maps['team'] ),
				count( $this->ref_maps['commissie'] ),
				count( $this->ref_maps['discipline_case'] ),
				count( $this->ref_maps['todo'] ),
				count( $this->ref_maps['invoice'] )
			)
		);
	}

	/**
	 * Build reference map for a single post type
	 *
	 * @param string       $post_type Post type slug.
	 * @param string|array $post_statuses Post status(es) to query.
	 * @param string|null  $ref_key Optional ref map key (defaults to post_type).
	 */
	private function build_ref_map_for_type( $post_type, $post_statuses, $ref_key = null ) {
		$ref_key = $ref_key ?? $post_type;

		$post_ids = get_posts(
			[
				'post_type'              => $post_type,
				'numberposts'            => -1,
				'post_status'            => $post_statuses,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $post_ids as $index => $post_id ) {
			$this->ref_maps[ $ref_key ][ $post_id ] = $ref_key . ':' . ( $index + 1 );
		}
	}

	/**
	 * Get reference ID for a post
	 *
	 * @param int    $post_id     WordPress post ID.
	 * @param string $entity_type Entity type (person, team, commissie, discipline_case, todo).
	 * @return string|null Reference ID string or null if not found.
	 */
	protected function get_ref( $post_id, $entity_type ) {
		if ( isset( $this->ref_maps[ $entity_type ][ $post_id ] ) ) {
			return $this->ref_maps[ $entity_type ][ $post_id ];
		}
		return null;
	}


	/**
	 * Normalize a value - convert empty values to null
	 *
	 * @param mixed $value The value to normalize.
	 * @return mixed The normalized value (null if empty).
	 */
	private function normalize_value( $value ) {
		if ( is_string( $value ) && $value === '' ) {
			return null;
		}
		if ( $value === false || ( is_array( $value ) && empty( $value ) ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Export fixed contact fields for person post type.
	 *
	 * @param int $post_id Post ID.
	 * @return array Associative array of fixed contact field values.
	 */
	private function export_contact_fields( $post_id ) {
		$fields   = [ 'email_1', 'email_2', 'mobile_1', 'mobile_2', 'telephone_1', 'telephone_2' ];
		$exported = [];

		foreach ( $fields as $field ) {
			$value = get_field( $field, $post_id );
			if ( ! empty( $value ) ) {
				$exported[ $field ] = $value;
			}
		}

		return $exported;
	}

	/**
	 * Export contact_info repeater field (for teams/commissies).
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of contact info objects.
	 */
	private function export_contact_info( $post_id ) {
		$contact_info = get_field( 'contact_info', $post_id );

		if ( ! $contact_info || ! is_array( $contact_info ) ) {
			return [];
		}

		$exported = [];

		foreach ( $contact_info as $row ) {
			$exported[] = [
				'contact_type'  => $row['contact_type'] ?? '',
				'contact_label' => $row['contact_label'] ?? '',
				'contact_value' => $row['contact_value'] ?? '',
			];
		}

		return $exported;
	}

	/**
	 * Export people
	 *
	 * @return array Array of person objects.
	 */
	protected function export_people() {
		$posts = get_posts(
			[
				'post_type'   => 'person',
				'numberposts' => -1,
				'post_status' => 'any',
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);

		$people = [];
		$total  = count( $posts );

		foreach ( $posts as $i => $post ) {
			// Progress logging every 100 people
			if ( ( $i + 1 ) % 100 === 0 ) {
				WP_CLI::log( sprintf( '  Exported %d / %d people...', $i + 1, $total ) );
			}

			$person = [
				'_ref'      => $this->get_ref( $post->ID, 'person' ),
				'title'     => $post->post_title,
				'status'    => $post->post_status,
				'acf'       => [
					// Basic Information
					'first_name'          => get_field( 'first_name', $post->ID ),
					'infix'               => $this->normalize_value( get_field( 'infix', $post->ID ) ),
					'last_name'           => get_field( 'last_name', $post->ID ),
					'nickname'            => $this->normalize_value( get_field( 'nickname', $post->ID ) ),
					'gender'              => $this->normalize_value( get_field( 'gender', $post->ID ) ),
					'pronouns'            => $this->normalize_value( get_field( 'pronouns', $post->ID ) ),
					'birthdate'           => get_field( 'birthdate', $post->ID ),
					'former_member'       => (bool) get_field( 'former_member', $post->ID ),
					'lid-tot'             => $this->normalize_value( get_field( 'lid-tot', $post->ID ) ),
					'datum-overlijden'    => $this->normalize_value( get_field( 'datum-overlijden', $post->ID ) ),

					// Contact Information (fixed fields)
					...$this->export_contact_fields( $post->ID ),

					// Addresses
					'addresses'           => $this->export_addresses( $post->ID ),

					// Work History
					'work_history'        => $this->export_work_history( $post->ID ),

					// Relationships
					'relationships'       => $this->export_relationships( $post->ID ),

					// Sportlink-Synced Fields
					'lid-sinds'           => $this->normalize_value( get_field( 'lid-sinds', $post->ID ) ),
					'vrijwilliger-sinds'  => $this->normalize_value( get_field( 'vrijwilliger-sinds', $post->ID ) ),
					'leeftijdsgroep'      => $this->normalize_value( get_field( 'leeftijdsgroep', $post->ID ) ),
					'datum-vog'           => $this->normalize_value( get_field( 'datum-vog', $post->ID ) ),
					'datum-foto'          => $this->normalize_value( get_field( 'datum-foto', $post->ID ) ),
					'type-lid'            => $this->normalize_value( get_field( 'type-lid', $post->ID ) ),
					'huidig-vrijwilliger' => $this->normalize_value( get_field( 'huidig-vrijwilliger', $post->ID ) ),
					'financiele-blokkade' => (bool) get_field( 'financiele-blokkade', $post->ID ),
					'knvb-id'             => $this->normalize_value( get_field( 'knvb-id', $post->ID ) ),
					'freescout-id'        => $this->normalize_value( get_field( 'freescout-id', $post->ID ) ),
				],
				'post_meta' => $this->export_person_post_meta( $post->ID ),
			];

			// Anonymize person PII.
			$person = $this->anonymize_person( $person );

			$people[] = $person;
		}

		WP_CLI::log( sprintf( '  Exported %d people', count( $people ) ) );

		return $people;
	}

	/**
	 * Export addresses repeater field
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of address objects.
	 */
	private function export_addresses( $post_id ) {
		$addresses = get_field( 'addresses', $post_id );

		if ( ! $addresses || ! is_array( $addresses ) ) {
			return [];
		}

		$exported = [];

		foreach ( $addresses as $row ) {
			$exported[] = [
				'address_label'         => $row['address_label'] ?? '',
				'street_name'           => $row['street_name'] ?? '',
				'house_number'          => $row['house_number'] ?? '',
				'house_number_addition' => $row['house_number_addition'] ?? '',
				'postal_code'           => $row['postal_code'] ?? '',
				'city'                  => $row['city'] ?? '',
				'state'                 => $row['state'] ?? '',
				'country'               => $row['country'] ?? '',
			];
		}

		return $exported;
	}

	/**
	 * Export work_history repeater field
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of work history objects.
	 */
	private function export_work_history( $post_id ) {
		$work_history = get_field( 'work_history', $post_id );

		if ( ! $work_history || ! is_array( $work_history ) ) {
			return [];
		}

		$exported = [];

		foreach ( $work_history as $row ) {
			$team_id     = $row['team'] ?? null;
			$team_ref    = null;
			$entity_type = null;

			if ( $team_id ) {
				$post_type   = get_post_type( $team_id );
				$entity_type = $post_type;
				$team_ref    = $this->get_ref( $team_id, $post_type );
			}

			$exported[] = [
				'team'        => $team_ref,
				'entity_type' => $entity_type,
				'job_title'   => $row['job_title'] ?? '',
				'description' => $this->normalize_value( $row['description'] ?? '' ),
				'start_date'  => $this->normalize_value( $row['start_date'] ?? '' ),
				'end_date'    => $this->normalize_value( $row['end_date'] ?? '' ),
				'is_current'  => (bool) ( $row['is_current'] ?? false ),
			];
		}

		return $exported;
	}

	/**
	 * Export relationships repeater field
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of relationship objects.
	 */
	private function export_relationships( $post_id ) {
		$relationships = get_field( 'relationships', $post_id );

		if ( ! $relationships || ! is_array( $relationships ) ) {
			return [];
		}

		$exported = [];

		foreach ( $relationships as $row ) {
			$related_person_id    = $row['related_person'] ?? null;
			$relationship_type_id = $row['relationship_type'] ?? null;

			// Skip if related person is not in the ref map
			if ( ! $related_person_id || ! $this->get_ref( $related_person_id, 'person' ) ) {
				continue;
			}

			// Convert relationship type to slug-based ref
			$relationship_type_ref = null;
			if ( $relationship_type_id ) {
				$term = get_term( $relationship_type_id );
				if ( $term && ! is_wp_error( $term ) ) {
					$relationship_type_ref = 'relationship_type:' . $term->slug;
				}
			}

			// Skip if no relationship type
			if ( ! $relationship_type_ref ) {
				continue;
			}

			$exported[] = [
				'related_person'     => $this->get_ref( $related_person_id, 'person' ),
				'relationship_type'  => $relationship_type_ref,
				'relationship_label' => $this->normalize_value( $row['relationship_label'] ?? '' ),
			];
		}

		return $exported;
	}


	/**
	 * Export person post_meta (non-ACF fields)
	 *
	 * @param int $post_id Post ID.
	 * @return array Object with post meta fields.
	 */
	private function export_person_post_meta( $post_id ) {
		$exclude_from_contributie = get_post_meta( $post_id, '_exclude_from_contributie', true );

		$post_meta = [
			'vog_email_sent_date'       => $this->normalize_value( get_post_meta( $post_id, 'vog_email_sent_date', true ) ),
			'vog_justis_submitted_date' => $this->normalize_value( get_post_meta( $post_id, 'vog_justis_submitted_date', true ) ),
			'vog_reminder_sent_date'    => $this->normalize_value( get_post_meta( $post_id, 'vog_reminder_sent_date', true ) ),
			'_exclude_from_contributie' => $exclude_from_contributie !== '' ? (bool) $exclude_from_contributie : null,
		];

		// Scan for dynamic meta keys (nikki, fee snapshots/forecasts).
		// We fetch all meta to discover keys with dynamic season IDs that can't be queried individually.
		$all_meta = get_post_meta( $post_id );

		foreach ( $all_meta as $meta_key => $meta_values ) {
			// Match _nikki_*_total, _nikki_*_saldo, _fee_snapshot_*, _fee_forecast_*
			if ( preg_match( '/^_nikki_(\d+)_(total|saldo)$/', $meta_key ) ||
				preg_match( '/^_fee_(snapshot|forecast)_/', $meta_key ) ) {
				$value                  = $meta_values[0] ?? null;
				$post_meta[ $meta_key ] = $this->normalize_value( $value );
			}
		}

		return $post_meta;
	}

	/**
	 * Anonymize person PII
	 *
	 * @param array $person Person array from export_people.
	 * @return array Anonymized person array.
	 */
	private function anonymize_person( $person ) {
		$ref    = $person['_ref'];
		$gender = $person['acf']['gender'] ?? null;

		// Generate consistent fake identity for this person.
		$identity = $this->anonymizer->generate_identity( $ref, $gender );

		// Replace name fields.
		$person['acf']['first_name'] = $identity['first_name'];
		$person['acf']['infix']      = $identity['infix'];
		$person['acf']['last_name']  = $identity['last_name'];
		$person['acf']['nickname']   = null; // Strip nicknames.

		// Rebuild title from fake name.
		$name_parts      = array_filter( [ $identity['first_name'], $identity['infix'], $identity['last_name'] ] );
		$person['title'] = implode( ' ', $name_parts );

		// Replace contact fields with anonymized data.
		$person['acf'] = $this->anonymize_contact_fields( $person['acf'], $identity );

		// Replace addresses.
		$person['acf']['addresses'] = $this->anonymize_addresses( $person['acf']['addresses'] );

		// Replace Sportlink fields that contain PII.
		$person['acf']['knvb-id']      = $this->anonymizer->generate_knvb_id();
		$person['acf']['freescout-id'] = null; // Strip external system ID.

		// Strip photo date (no photo = no photo date).
		$person['acf']['datum-foto'] = null;

		// Anonymize financial data in post_meta.
		$person['post_meta'] = $this->anonymize_financials( $person['post_meta'] );

		return $person;
	}

	/**
	 * Anonymize fixed contact fields in ACF array.
	 *
	 * @param array $acf      ACF fields array.
	 * @param array $identity Generated identity.
	 * @return array ACF array with anonymized contact fields.
	 */
	private function anonymize_contact_fields( array $acf, array $identity ): array {
		// Anonymize emails
		if ( ! empty( $acf['email_1'] ) ) {
			$acf['email_1'] = $identity['email'];
		}
		if ( ! empty( $acf['email_2'] ) ) {
			$acf['email_2'] = str_replace( '@', '2@', $identity['email'] );
		}

		// Anonymize phone numbers
		foreach ( [ 'mobile_1', 'mobile_2', 'telephone_1', 'telephone_2' ] as $field ) {
			if ( ! empty( $acf[ $field ] ) ) {
				$acf[ $field ] = $this->anonymizer->generate_phone();
			}
		}

		return $acf;
	}

	/**
	 * Anonymize addresses array
	 *
	 * @param array $addresses Addresses array.
	 * @return array Anonymized addresses array.
	 */
	private function anonymize_addresses( $addresses ) {
		if ( empty( $addresses ) || ! is_array( $addresses ) ) {
			return [];
		}

		$anonymized = [];

		foreach ( $addresses as $row ) {
			$address_label = $row['address_label'] ?? '';

			// Generate fake address.
			$fake_address = $this->anonymizer->generate_address();

			$anonymized[] = [
				'address_label'         => $address_label,
				'street_name'           => $fake_address['street'],
				'house_number'          => $fake_address['house_number'],
				'house_number_addition' => '',
				'postal_code'           => $fake_address['postal_code'],
				'city'                  => $fake_address['city'],
				'state'                 => null,
				'country'               => 'Nederland',
			];
		}

		return $anonymized;
	}

	/**
	 * Anonymize financial data in post_meta
	 *
	 * @param array $post_meta Post meta array.
	 * @return array Anonymized post meta array.
	 */
	private function anonymize_financials( $post_meta ) {
		if ( empty( $post_meta ) || ! is_array( $post_meta ) ) {
			return $post_meta;
		}

		foreach ( $post_meta as $meta_key => $value ) {
			if ( preg_match( '/^_nikki_(\d+)_total$/', $meta_key ) ) {
				$post_meta[ $meta_key ] = $this->random_nikki_total();
			} elseif ( preg_match( '/^_nikki_(\d+)_saldo$/', $meta_key ) ) {
				$post_meta[ $meta_key ] = $this->random_nikki_saldo();
			} elseif ( preg_match( '/^_fee_snapshot_/', $meta_key ) ) {
				$post_meta[ $meta_key ] = $this->random_fee_data();
			} elseif ( preg_match( '/^_fee_forecast_/', $meta_key ) ) {
				$post_meta[ $meta_key ] = $this->random_fee_data();
			}
		}

		return $post_meta;
	}

	/**
	 * Generate random nikki total value
	 *
	 * @return string Random total as string.
	 */
	private function random_nikki_total() {
		$rand = wp_rand( 1, 100 );
		if ( $rand <= 70 ) {
			return (string) wp_rand( 100, 300 );
		} elseif ( $rand <= 90 ) {
			return (string) wp_rand( 50, 100 );
		} else {
			return '0';
		}
	}

	/**
	 * Generate random nikki saldo value
	 *
	 * @return string Random saldo as string.
	 */
	private function random_nikki_saldo() {
		$rand = wp_rand( 1, 100 );
		if ( $rand <= 80 ) {
			return '0';
		} elseif ( $rand <= 95 ) {
			return (string) wp_rand( 10, 100 );
		} else {
			return (string) ( -1 * wp_rand( 10, 50 ) );
		}
	}

	/**
	 * Generate random fee data (snapshot or forecast)
	 *
	 * @return string Serialized fee data.
	 */
	private function random_fee_data() {
		$fake_data = [
			'category'        => 'demo',
			'base_amount'     => wp_rand( 50, 300 ),
			'pro_rata_factor' => 1.0,
			'family_discount' => 0,
			'final_amount'    => wp_rand( 50, 300 ),
		];
		return serialize( $fake_data );
	}

	/**
	 * Strip organization contact info (teams/commissies)
	 *
	 * @param array $contact_info Contact info array.
	 * @return array Genericized contact info array.
	 */
	private function strip_org_contact_info( $contact_info ) {
		if ( empty( $contact_info ) || ! is_array( $contact_info ) ) {
			return [];
		}

		$stripped = [];

		foreach ( $contact_info as $row ) {
			$contact_type  = $row['contact_type'] ?? '';
			$contact_label = $row['contact_label'] ?? '';
			$contact_value = $row['contact_value'] ?? '';

			// Genericize based on contact type.
			switch ( $contact_type ) {
				case 'email':
					$contact_value = 'team@rondo-demo.nl';
					break;

				case 'phone':
				case 'mobile':
					$contact_value = '06-00000000';
					break;

				case 'website':
					// Keep websites as-is (public information).
					break;

				default:
					// Other types: set to null.
					$contact_value = null;
					break;
			}

			$stripped[] = [
				'contact_type'  => $contact_type,
				'contact_label' => $contact_label,
				'contact_value' => $contact_value,
			];
		}

		return $stripped;
	}

	/**
	 * Anonymize discipline case
	 *
	 * @param array $case Discipline case array.
	 * @return array Anonymized discipline case array.
	 */
	private function anonymize_discipline_case( $case ) {
		$person_ref = $case['acf']['person'] ?? null;

		// Rebuild title with fake name if person ref exists.
		if ( $person_ref ) {
			$identity   = $this->anonymizer->generate_identity( $person_ref );
			$name_parts = array_filter( [ $identity['first_name'], $identity['infix'], $identity['last_name'] ] );
			$fake_name  = implode( ' ', $name_parts );

			$match_description = $case['acf']['match_description'] ?? '';
			$match_date        = $case['acf']['match_date'] ?? '';

			$case['title'] = sprintf( '%s - %s - %s', $fake_name, $match_description, $match_date );
		}

		// Randomize dossier_id (7 digits + ".0").
		$case['acf']['dossier_id'] = sprintf( '%d.0', wp_rand( 1000000, 9999999 ) );

		// Randomize administrative fee (typical values: 10.00, 19.60, 30.00, 40.60, 50.00).
		$base                              = wp_rand( 1, 5 ) * 10;
		$cents                             = wp_rand( 0, 1 ) * 0.60;
		$case['acf']['administrative_fee'] = $base + $cents;

		return $case;
	}

	/**
	 * Anonymize comment
	 *
	 * @param array $comment Comment array.
	 * @return array Anonymized comment array.
	 */
	private function anonymize_comment( $comment ) {
		$type = $comment['type'] ?? '';

		switch ( $type ) {
			case 'rondo_email':
				// Replace email recipient with fake email from person's identity.
				$person_ref = $comment['person'] ?? null;
				if ( $person_ref && isset( $comment['meta'] ) ) {
					$identity                           = $this->anonymizer->generate_identity( $person_ref );
					$comment['meta']['email_recipient'] = $identity['email'];
					// Replace email content snapshot with placeholder.
					$comment['meta']['email_content_snapshot'] = 'Demo email content';
				}
				break;

			case 'rondo_note':
				// Replace note content with generic text.
				$comment['content'] = 'Demo notitie';
				break;

			case 'rondo_activity':
				// Replace activity content with generic text.
				$comment['content'] = 'Activiteit gelogd';
				break;
		}

		return $comment;
	}

	/**
	 * Anonymize todo
	 *
	 * @param array $todo Todo array.
	 * @return array Anonymized todo array.
	 */
	private function anonymize_todo( $todo ) {
		// List of generic Dutch todo titles.
		$generic_titles = [
			'Terugbellen',
			'Opvolgen',
			'Mail sturen',
			'Afspraak maken',
			'Documenten checken',
			'Gegevens bijwerken',
			'Contact opnemen',
			'Uitnodiging sturen',
			'Formulier invullen',
			'Betaling controleren',
		];

		// Pick a title based on seeded random using todo ref.
		$ref   = $todo['_ref'] ?? '';
		$hash  = crc32( $ref );
		$index = abs( $hash ) % count( $generic_titles );

		$todo['title'] = $generic_titles[ $index ];

		// Strip content and notes (may contain PII).
		$todo['content']      = null;
		$todo['acf']['notes'] = null;

		return $todo;
	}

	/**
	 * Export teams
	 *
	 * @return array Array of team objects.
	 */
	protected function export_teams() {
		$posts = get_posts(
			[
				'post_type'   => 'team',
				'numberposts' => -1,
				'post_status' => [ 'publish', 'draft', 'private' ],
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);

		$teams = [];

		foreach ( $posts as $post ) {
			$team = [
				'_ref'    => $this->get_ref( $post->ID, 'team' ),
				'title'   => $post->post_title,
				'content' => ! empty( $post->post_content ) ? $post->post_content : null,
				'status'  => $post->post_status,
				'parent'  => $post->post_parent > 0 ? $this->get_ref( $post->post_parent, 'team' ) : null,
				'acf'     => [
					'website'      => $this->normalize_value( get_field( 'website', $post->ID ) ),
					'contact_info' => $this->strip_org_contact_info( $this->export_contact_info( $post->ID ) ),
				],
			];

			$teams[] = $team;
		}

		WP_CLI::log( sprintf( '  Exported %d teams', count( $teams ) ) );

		return $teams;
	}

	/**
	 * Export commissies
	 *
	 * @return array Array of commissie objects.
	 */
	protected function export_commissies() {
		$posts = get_posts(
			[
				'post_type'   => 'commissie',
				'numberposts' => -1,
				'post_status' => [ 'publish', 'draft', 'private' ],
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);

		$commissies = [];

		foreach ( $posts as $post ) {
			$commissie = [
				'_ref'    => $this->get_ref( $post->ID, 'commissie' ),
				'title'   => $post->post_title,
				'content' => ! empty( $post->post_content ) ? $post->post_content : null,
				'status'  => $post->post_status,
				'parent'  => $post->post_parent > 0 ? $this->get_ref( $post->post_parent, 'commissie' ) : null,
				'acf'     => [
					'website'      => $this->normalize_value( get_field( 'website', $post->ID ) ),
					'contact_info' => $this->strip_org_contact_info( $this->export_contact_info( $post->ID ) ),
				],
			];

			$commissies[] = $commissie;
		}

		WP_CLI::log( sprintf( '  Exported %d commissies', count( $commissies ) ) );

		return $commissies;
	}


	/**
	 * Export discipline cases
	 *
	 * @return array Array of discipline case objects.
	 */
	protected function export_discipline_cases() {
		$posts = get_posts(
			[
				'post_type'   => 'discipline_case',
				'numberposts' => -1,
				'post_status' => 'any',
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);

		$cases = [];

		foreach ( $posts as $post ) {
			// Get person reference
			$person_field = get_field( 'person', $post->ID );
			$person_id    = null;
			if ( is_object( $person_field ) && isset( $person_field->ID ) ) {
				$person_id = (int) $person_field->ID;
			} elseif ( is_numeric( $person_field ) ) {
				$person_id = (int) $person_field;
			}
			$person_ref = $person_id ? $this->get_ref( $person_id, 'person' ) : null;

			// Get seizoen (taxonomy term)
			$seizoen       = null;
			$seizoen_terms = wp_get_post_terms( $post->ID, 'seizoen' );
			if ( ! empty( $seizoen_terms ) && ! is_wp_error( $seizoen_terms ) ) {
				$seizoen = $seizoen_terms[0]->slug;
			}

			$case = [
				'_ref'    => $this->get_ref( $post->ID, 'discipline_case' ),
				'title'   => $post->post_title,
				'status'  => $post->post_status,
				'acf'     => [
					'dossier_id'           => get_field( 'dossier_id', $post->ID ),
					'person'               => $person_ref,
					'match_date'           => $this->normalize_value( get_field( 'match_date', $post->ID ) ),
					'processing_date'      => $this->normalize_value( get_field( 'processing_date', $post->ID ) ),
					'match_description'    => $this->normalize_value( get_field( 'match_description', $post->ID ) ),
					'team_name'            => $this->normalize_value( get_field( 'team_name', $post->ID ) ),
					'charge_codes'         => $this->normalize_value( get_field( 'charge_codes', $post->ID ) ),
					'charge_description'   => $this->normalize_value( get_field( 'charge_description', $post->ID ) ),
					'sanction_description' => $this->normalize_value( get_field( 'sanction_description', $post->ID ) ),
					'administrative_fee'   => $this->normalize_value( (float) get_field( 'administrative_fee', $post->ID ) ),
					'is_charged'           => (bool) get_field( 'is_charged', $post->ID ),
				],
				'seizoen' => $seizoen,
			];

			// Anonymize discipline case.
			$case = $this->anonymize_discipline_case( $case );

			$cases[] = $case;
		}

		WP_CLI::log( sprintf( '  Exported %d discipline cases', count( $cases ) ) );

		return $cases;
	}

	/**
	 * Export invoices (discipline case invoices and membership/contributie invoices)
	 *
	 * @return array Array of invoice objects.
	 */
	protected function export_invoices() {
		$posts = get_posts(
			[
				'post_type'   => 'rondo_invoice',
				'numberposts' => -1,
				'post_status' => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ],
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);

		$invoices = [];
		$seq      = 1;

		foreach ( $posts as $post ) {
			$invoice_type = get_field( 'invoice_type', $post->ID );

			// Get person reference
			$person_field = get_field( 'person', $post->ID );
			$person_id    = null;
			if ( is_object( $person_field ) && isset( $person_field->ID ) ) {
				$person_id = (int) $person_field->ID;
			} elseif ( is_numeric( $person_field ) ) {
				$person_id = (int) $person_field;
			}
			$person_ref = $person_id ? $this->get_ref( $person_id, 'person' ) : null;

			// Get line items, resolving discipline_case refs
			$raw_line_items = get_field( 'line_items', $post->ID );
			$line_items     = [];
			if ( is_array( $raw_line_items ) ) {
				foreach ( $raw_line_items as $item ) {
					$dc_field = $item['discipline_case'] ?? null;
					$dc_id    = null;
					if ( is_object( $dc_field ) && isset( $dc_field->ID ) ) {
						$dc_id = (int) $dc_field->ID;
					} elseif ( is_numeric( $dc_field ) ) {
						$dc_id = (int) $dc_field;
					}

					$dc_ref = $dc_id ? $this->get_ref( $dc_id, 'discipline_case' ) : null;

					// Skip line items whose discipline_case cannot be resolved
					if ( $dc_ref === null && $dc_id ) {
						continue;
					}

					$line_items[] = [
						'discipline_case' => $dc_ref,
						'description'     => $item['description'] ?? null,
						'amount'          => (float) ( $item['amount'] ?? 0 ),
					];
				}
			}

			// Anonymize total_amount to realistic range
			if ( $invoice_type === 'membership' ) {
				$total_amount = (float) wp_rand( 50, 300 );
			} else {
				$total_amount = (float) wp_rand( 10, 100 );
			}

			// Get seizoen taxonomy term slug
			$seizoen       = null;
			$seizoen_terms = wp_get_post_terms( $post->ID, 'seizoen' );
			if ( ! empty( $seizoen_terms ) && ! is_wp_error( $seizoen_terms ) ) {
				$seizoen = $seizoen_terms[0]->slug;
			}

			// Export installment meta
			$installment_plan  = get_post_meta( $post->ID, '_installment_plan', true );
			$installment_count = (int) get_post_meta( $post->ID, '_installment_count', true );
			$invoice_season    = get_post_meta( $post->ID, '_invoice_season', true );

			$installments = [];
			if ( $installment_count > 0 ) {
				// Split anonymized total evenly across installments
				$base_per_installment = round( $total_amount / $installment_count, 2 );

				for ( $n = 1; $n <= $installment_count; $n++ ) {
					$installments[ $n ] = [
						'amount'    => $base_per_installment,
						'admin_fee' => (float) get_post_meta( $post->ID, "_installment_{$n}_admin_fee", true ),
						'status'    => get_post_meta( $post->ID, "_installment_{$n}_status", true ),
						'due_date'  => get_post_meta( $post->ID, "_installment_{$n}_due_date", true ),
					];
				}
			}

			// Demo invoice number (DEMO-YEAR-SEQ)
			$year           = gmdate( 'Y' );
			$invoice_number = sprintf( 'DEMO-%d-%04d', $year, $seq++ );

			$invoice = [
				'_ref'      => $this->get_ref( $post->ID, 'invoice' ),
				'title'     => $invoice_number,
				'status'    => $post->post_status,
				'acf'       => [
					'invoice_number' => $invoice_number,
					'invoice_type'   => $invoice_type,
					'person'         => $person_ref,
					'status'         => get_field( 'status', $post->ID ),
					'total_amount'   => $total_amount,
					'sent_date'      => $this->normalize_value( get_field( 'sent_date', $post->ID ) ),
					'due_date'       => $this->normalize_value( get_field( 'due_date', $post->ID ) ),
					'line_items'     => $line_items,
					'payment_link'   => null,
					'pdf_path'       => null,
					'qr_code_path'   => null,
				],
				'post_meta' => [
					'_installment_plan'  => $this->normalize_value( $installment_plan ),
					'_installment_count' => $installment_count > 0 ? $installment_count : null,
					'_invoice_season'    => $this->normalize_value( $invoice_season ),
					'_installments'      => $installments,
				],
				'seizoen'   => $seizoen,
			];

			$invoices[] = $invoice;
		}

		WP_CLI::log( sprintf( '  Exported %d invoices', count( $invoices ) ) );

		return $invoices;
	}

	/**
	 * Export todos
	 *
	 * @return array Array of todo objects.
	 */
	protected function export_todos() {
		$posts = get_posts(
			[
				'post_type'   => 'rondo_todo',
				'numberposts' => -1,
				'post_status' => [ 'rondo_open', 'rondo_awaiting', 'rondo_completed' ],
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);

		$todos = [];

		foreach ( $posts as $post ) {
			// Get related persons (array of post IDs)
			$related_persons_field = get_field( 'related_persons', $post->ID );
			$related_persons_refs  = [];

			if ( is_array( $related_persons_field ) ) {
				foreach ( $related_persons_field as $person_field ) {
					$person_id = null;
					if ( is_object( $person_field ) && isset( $person_field->ID ) ) {
						$person_id = (int) $person_field->ID;
					} elseif ( is_numeric( $person_field ) ) {
						$person_id = (int) $person_field;
					}

					if ( $person_id ) {
						$person_ref = $this->get_ref( $person_id, 'person' );
						if ( $person_ref ) {
							$related_persons_refs[] = $person_ref;
						}
					}
				}
			}

			// Convert post_date to ISO 8601
			$date = get_post_time( 'c', false, $post->ID );

			$todo = [
				'_ref'    => $this->get_ref( $post->ID, 'todo' ),
				'title'   => $post->post_title,
				'content' => ! empty( $post->post_content ) ? $post->post_content : null,
				'status'  => $post->post_status,
				'date'    => $date,
				'acf'     => [
					'related_persons' => $related_persons_refs,
					'notes'           => $this->normalize_value( get_field( 'notes', $post->ID ) ),
					'awaiting_since'  => $this->normalize_value( get_field( 'awaiting_since', $post->ID ) ),
					'due_date'        => $this->normalize_value( get_field( 'due_date', $post->ID ) ),
				],
			];

			// Anonymize todo.
			$todo = $this->anonymize_todo( $todo );

			$todos[] = $todo;
		}

		WP_CLI::log( sprintf( '  Exported %d todos', count( $todos ) ) );

		return $todos;
	}

	/**
	 * Export comments (notes, activities, emails)
	 *
	 * @return array Array of comment objects.
	 */
	protected function export_comments() {
		$comments = get_comments(
			[
				'type__in' => [ 'rondo_note', 'rondo_activity', 'rondo_email' ],
				'number'   => 0,
				'orderby'  => 'comment_date',
				'order'    => 'ASC',
			]
		);

		$exported = [];
		$counts   = [
			'rondo_note'     => 0,
			'rondo_activity' => 0,
			'rondo_email'    => 0,
		];

		foreach ( $comments as $comment ) {
			// Skip comments not on person posts
			$person_ref = $this->get_ref( $comment->comment_post_ID, 'person' );
			if ( ! $person_ref ) {
				continue;
			}

			// Build base comment object
			$exported_comment = [
				'type'      => $comment->comment_type,
				'person'    => $person_ref,
				'content'   => $comment->comment_content,
				'date'      => gmdate( 'Y-m-d\TH:i:s', strtotime( $comment->comment_date ) ),
				'author_id' => $comment->user_id > 0 ? (int) $comment->user_id : null,
			];

			// Build type-specific meta
			$meta = [];

			switch ( $comment->comment_type ) {
				case 'rondo_note':
					$note_visibility          = get_comment_meta( $comment->comment_ID, '_note_visibility', true );
					$meta['_note_visibility'] = ! empty( $note_visibility ) ? $note_visibility : 'shared';
					break;

				case 'rondo_activity':
					$meta['activity_type'] = $this->normalize_value( get_comment_meta( $comment->comment_ID, 'activity_type', true ) );
					$meta['activity_date'] = $this->normalize_value( get_comment_meta( $comment->comment_ID, 'activity_date', true ) );
					$meta['activity_time'] = $this->normalize_value( get_comment_meta( $comment->comment_ID, 'activity_time', true ) );

					// Participants - convert WordPress IDs to fixture refs
					$participants      = get_comment_meta( $comment->comment_ID, 'participants', true );
					$participants_refs = [];
					if ( is_array( $participants ) ) {
						foreach ( $participants as $participant_id ) {
							$participant_ref = $this->get_ref( $participant_id, 'person' );
							if ( $participant_ref ) {
								$participants_refs[] = $participant_ref;
							}
						}
					}
					$meta['participants'] = $participants_refs;
					break;

				case 'rondo_email':
					$meta['email_template_type']    = get_comment_meta( $comment->comment_ID, 'email_template_type', true );
					$meta['email_recipient']        = get_comment_meta( $comment->comment_ID, 'email_recipient', true );
					$meta['email_subject']          = get_comment_meta( $comment->comment_ID, 'email_subject', true );
					$meta['email_content_snapshot'] = get_comment_meta( $comment->comment_ID, 'email_content_snapshot', true );
					break;
			}

			// Only add meta if not empty
			if ( ! empty( $meta ) ) {
				$exported_comment['meta'] = $meta;
			}

			// Anonymize comment.
			$exported_comment = $this->anonymize_comment( $exported_comment );

			$exported[] = $exported_comment;
			++$counts[ $comment->comment_type ];
		}

		WP_CLI::log(
			sprintf(
				'  Exported %d comments (%d notes, %d activities, %d emails)',
				count( $exported ),
				$counts['rondo_note'],
				$counts['rondo_activity'],
				$counts['rondo_email']
			)
		);

		return $exported;
	}

	/**
	 * Export taxonomies
	 *
	 * @return array Object containing relationship_types and seizoenen arrays.
	 */
	protected function export_taxonomies() {
		$taxonomies = [
			'relationship_types' => $this->export_relationship_types(),
			'seizoenen'          => $this->export_seizoenen(),
		];

		return $taxonomies;
	}

	/**
	 * Export relationship type taxonomy terms
	 *
	 * @return array Array of relationship type objects.
	 */
	private function export_relationship_types() {
		$terms = get_terms(
			[
				'taxonomy'   => 'relationship_type',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) ) {
			WP_CLI::warning( 'Failed to get relationship_type terms: ' . $terms->get_error_message() );
			return [];
		}

		$relationship_types = [];

		foreach ( $terms as $term ) {
			$inverse_field = get_field( 'inverse_relationship_type', 'relationship_type_' . $term->term_id );
			$inverse_ref   = null;

			if ( $inverse_field ) {
				// ACF returns term ID - convert to slug
				if ( is_numeric( $inverse_field ) ) {
					$inverse_term = get_term( $inverse_field );
					if ( $inverse_term && ! is_wp_error( $inverse_term ) ) {
						$inverse_ref = 'relationship_type:' . $inverse_term->slug;
					}
				} elseif ( is_object( $inverse_field ) && isset( $inverse_field->slug ) ) {
					$inverse_ref = 'relationship_type:' . $inverse_field->slug;
				}
			}

			$relationship_type = [
				'_ref' => 'relationship_type:' . $term->slug,
				'name' => $term->name,
				'slug' => $term->slug,
				'acf'  => [
					'inverse_relationship_type' => $inverse_ref,
				],
			];

			$relationship_types[] = $relationship_type;
		}

		WP_CLI::log( sprintf( '  Exported %d relationship types', count( $relationship_types ) ) );

		return $relationship_types;
	}

	/**
	 * Export seizoen taxonomy terms
	 *
	 * @return array Array of seizoen objects.
	 */
	private function export_seizoenen() {
		$terms = get_terms(
			[
				'taxonomy'   => 'seizoen',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) ) {
			WP_CLI::warning( 'Failed to get seizoen terms: ' . $terms->get_error_message() );
			return [];
		}

		$seizoenen = [];

		foreach ( $terms as $term ) {
			$is_current = (bool) get_term_meta( $term->term_id, 'is_current_season', true );

			$seizoen = [
				'name'       => $term->name,
				'slug'       => $term->slug,
				'is_current' => $is_current,
			];

			$seizoenen[] = $seizoen;
		}

		WP_CLI::log( sprintf( '  Exported %d seizoenen', count( $seizoenen ) ) );

		return $seizoenen;
	}

	/**
	 * Export settings (WordPress options)
	 *
	 * @return array Object with all settings needed for demo.
	 */
	protected function export_settings() {
		$settings = [];

		// Club name (required)
		$settings['rondo_club_name'] = get_option( 'rondo_club_name', '' );

		// Get all seasons to discover dynamic option keys
		$seasons = get_terms(
			[
				'taxonomy'   => 'seizoen',
				'hide_empty' => false,
			]
		);

		$fee_config_count      = 0;
		$discount_config_count = 0;

		if ( ! is_wp_error( $seasons ) ) {
			foreach ( $seasons as $season ) {
				$season_slug = $season->slug;

				// Membership fees for this season
				$fee_option_key = "rondo_membership_fees_{$season_slug}";
				$fee_config     = get_option( $fee_option_key );
				if ( $fee_config ) {
					$settings[ $fee_option_key ] = $fee_config;
					++$fee_config_count;
				}

				// Family discount for this season
				$discount_option_key = "rondo_family_discount_{$season_slug}";
				$discount_config     = get_option( $discount_option_key );
				if ( $discount_config ) {
					$settings[ $discount_option_key ] = $discount_config;
					++$discount_config_count;
				}
			}
		}

		// Player roles and excluded roles
		$settings['rondo_player_roles']   = get_option( 'rondo_player_roles', [] );
		$settings['rondo_excluded_roles'] = get_option( 'rondo_excluded_roles', [] );

		// Anniversary milestone settings.
		$anniversary_milestones = get_option( 'rondo_anniversary_milestones' );
		if ( is_array( $anniversary_milestones ) ) {
			$settings['rondo_anniversary_milestones'] = $anniversary_milestones;
		}

		// VOG email settings (nullable)
		$settings['rondo_vog_from_email'] = $this->normalize_value( get_option( 'rondo_vog_from_email', '' ) );
		$settings['rondo_vog_from_name']  = $this->normalize_value( get_option( 'rondo_vog_from_name', '' ) );

		// VOG email templates (nullable, HTML)
		$settings['rondo_vog_template_new']              = $this->normalize_value( get_option( 'rondo_vog_template_new', '' ) );
		$settings['rondo_vog_template_renewal']          = $this->normalize_value( get_option( 'rondo_vog_template_renewal', '' ) );
		$settings['rondo_vog_reminder_template_new']     = $this->normalize_value( get_option( 'rondo_vog_reminder_template_new', '' ) );
		$settings['rondo_vog_reminder_template_renewal'] = $this->normalize_value( get_option( 'rondo_vog_reminder_template_renewal', '' ) );

		// VOG exempt commissies (convert post IDs to fixture refs)
		$exempt_commissies      = get_option( 'rondo_vog_exempt_commissies', [] );
		$exempt_commissies_refs = [];

		if ( is_array( $exempt_commissies ) ) {
			foreach ( $exempt_commissies as $commissie_id ) {
				$ref = $this->get_ref( $commissie_id, 'commissie' );
				if ( $ref ) {
					$exempt_commissies_refs[] = $ref;
				}
			}
		}

		$settings['rondo_vog_exempt_commissies'] = $exempt_commissies_refs;

		// Anonymize VOG email settings.
		$settings['rondo_vog_from_email'] = 'vog@rondo-demo.nl';
		$settings['rondo_vog_from_name']  = $settings['rondo_club_name'] ?: 'Demo Club';

		// Replace VOG email templates with demo placeholders.
		if ( ! empty( $settings['rondo_vog_template_new'] ) ) {
			$settings['rondo_vog_template_new'] = '<p>Dit is een demo e-mailtemplate.</p>';
		}
		if ( ! empty( $settings['rondo_vog_template_renewal'] ) ) {
			$settings['rondo_vog_template_renewal'] = '<p>Dit is een demo e-mailtemplate.</p>';
		}
		if ( ! empty( $settings['rondo_vog_reminder_template_new'] ) ) {
			$settings['rondo_vog_reminder_template_new'] = '<p>Dit is een demo e-mailtemplate.</p>';
		}
		if ( ! empty( $settings['rondo_vog_reminder_template_renewal'] ) ) {
			$settings['rondo_vog_reminder_template_renewal'] = '<p>Dit is een demo e-mailtemplate.</p>';
		}

		// Finance configuration settings (non-sensitive + anonymized).
		$settings['rondo_finance_org_name']      = 'Demo Club';
		$settings['rondo_finance_org_address']   = 'Sportlaan 1, 1234 AB Amsterdam';
		$settings['rondo_finance_contact_email'] = 'penningmeester@rondo-demo.nl';
		$settings['rondo_finance_bcc_email']     = 'financien@rondo-demo.nl';
		$settings['rondo_finance_iban']          = 'NL00DEMO0000000000';

		$payment_term_days = get_option( 'rondo_finance_payment_term_days' );
		if ( $payment_term_days !== false ) {
			$settings['rondo_finance_payment_term_days'] = (int) $payment_term_days;
		}

		$payment_clause = get_option( 'rondo_finance_payment_clause' );
		if ( $payment_clause !== false ) {
			$settings['rondo_finance_payment_clause'] = $payment_clause;
		}

		$membership_payment_clause = get_option( 'rondo_finance_membership_payment_clause' );
		if ( $membership_payment_clause !== false ) {
			$settings['rondo_finance_membership_payment_clause'] = $membership_payment_clause;
		}

		// Email templates: replace with demo placeholders
		$settings['rondo_finance_email_template']                    = '<p>Dit is een demo e-mailtemplate voor facturen.</p>';
		$settings['rondo_finance_membership_email_template']         = '<p>Dit is een demo e-mailtemplate voor contributie.</p>';
		$settings['rondo_finance_installment_email_template']        = '<p>Dit is een demo e-mailtemplate voor termijnen.</p>';
		$settings['rondo_finance_reminder_1_email_template']         = '<p>Dit is een demo herinnering.</p>';
		$settings['rondo_finance_reminder_2_email_template']         = '<p>Dit is een demo tweede herinnering.</p>';
		$settings['rondo_finance_invoice_reminder_1_email_template'] = '<p>Dit is een demo factuurherinnering.</p>';
		$settings['rondo_finance_invoice_reminder_2_email_template'] = '<p>Dit is een demo tweede factuurherinnering.</p>';

		$accent_color = get_option( 'rondo_finance_accent_color' );
		if ( $accent_color !== false ) {
			$settings['rondo_finance_accent_color'] = $accent_color;
		}

		$accent_background_color = get_option( 'rondo_finance_accent_background_color' );
		if ( $accent_background_color !== false ) {
			$settings['rondo_finance_accent_background_color'] = $accent_background_color;
		}

		$admin_fee = get_option( 'rondo_finance_admin_fee' );
		if ( $admin_fee !== false ) {
			$settings['rondo_finance_admin_fee'] = (float) $admin_fee;
		}

		$installment_admin_fee = get_option( 'rondo_finance_installment_admin_fee' );
		if ( $installment_admin_fee !== false ) {
			$settings['rondo_finance_installment_admin_fee'] = (float) $installment_admin_fee;
		}

		// Active payment provider: always set to mollie for demo
		$settings['rondo_finance_active_payment_provider'] = 'mollie';

		// Membership pass configuration (portable values only; no credentials/files).
		$settings['rondo_membership_pass_apple_cert_attachment_id']             = 0;
		$settings['rondo_membership_pass_apple_cert_password']                  = '';
		$settings['rondo_membership_pass_apple_pass_type_identifier']           = 'pass.nl.rondo.demo.membership';
		$settings['rondo_membership_pass_apple_team_identifier']                = 'DEMO123456';
		$settings['rondo_membership_pass_apple_organization_name']              = 'Demo Club';
		$settings['rondo_membership_pass_google_service_account_attachment_id'] = 0;
		$settings['rondo_membership_pass_google_issuer_id']                     = '3388000000022952745';

		$google_class_suffix                                   = get_option( 'rondo_membership_pass_google_class_suffix' );
		$settings['rondo_membership_pass_google_class_suffix'] = is_string( $google_class_suffix ) && $google_class_suffix !== ''
			? sanitize_key( $google_class_suffix )
			: 'rondo_membership';

		// Capability maps
		$functie_map   = get_option( 'rondo_functie_capability_map' );
		$commissie_map = get_option( 'rondo_commissie_capability_map' );

		if ( $functie_map !== false ) {
			$settings['rondo_functie_capability_map'] = $functie_map;
		}

		if ( $commissie_map !== false ) {
			$settings['rondo_commissie_capability_map'] = $commissie_map;
		}

		WP_CLI::log( sprintf( '  Exported settings (%d fee configs, %d family discount configs)', $fee_config_count, $discount_config_count ) );

		return $settings;
	}
}
