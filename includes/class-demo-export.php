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
	];

	/**
	 * Output file path
	 *
	 * @var string
	 */
	private $output_path;

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

		// Export all entity sections
		WP_CLI::log( 'Exporting people...' );
		$people = $this->export_people();

		WP_CLI::log( 'Exporting teams...' );
		$teams = $this->export_teams();

		WP_CLI::log( 'Exporting commissies...' );
		$commissies = $this->export_commissies();

		WP_CLI::log( 'Exporting discipline cases...' );
		$discipline_cases = $this->export_discipline_cases();

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
			'source'        => 'Production export',
			'record_counts' => [
				'people'           => count( $people ),
				'teams'            => count( $teams ),
				'commissies'       => count( $commissies ),
				'discipline_cases' => count( $discipline_cases ),
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
			'todos'            => $todos,
			'comments'         => $comments,
			'taxonomies'       => $taxonomies,
			'settings'         => $settings,
		];

		// Write to file
		WP_CLI::log( sprintf( 'Writing fixture to: %s', $this->output_path ) );
		$json = wp_json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			WP_CLI::error( 'Failed to encode fixture to JSON' );
		}

		$result = file_put_contents( $this->output_path, $json );

		if ( false === $result ) {
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
		// People
		$people_ids = get_posts(
			[
				'post_type'      => 'person',
				'numberposts'    => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $people_ids as $index => $post_id ) {
			$this->ref_maps['person'][ $post_id ] = 'person:' . ( $index + 1 );
		}

		// Teams
		$teams_ids = get_posts(
			[
				'post_type'      => 'team',
				'numberposts'    => -1,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $teams_ids as $index => $post_id ) {
			$this->ref_maps['team'][ $post_id ] = 'team:' . ( $index + 1 );
		}

		// Commissies
		$commissies_ids = get_posts(
			[
				'post_type'      => 'commissie',
				'numberposts'    => -1,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $commissies_ids as $index => $post_id ) {
			$this->ref_maps['commissie'][ $post_id ] = 'commissie:' . ( $index + 1 );
		}

		// Discipline cases
		$discipline_cases_ids = get_posts(
			[
				'post_type'      => 'discipline_case',
				'numberposts'    => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $discipline_cases_ids as $index => $post_id ) {
			$this->ref_maps['discipline_case'][ $post_id ] = 'discipline_case:' . ( $index + 1 );
		}

		// Todos
		$todos_ids = get_posts(
			[
				'post_type'      => 'rondo_todo',
				'numberposts'    => -1,
				'post_status'    => [ 'rondo_open', 'rondo_awaiting', 'rondo_completed' ],
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $todos_ids as $index => $post_id ) {
			$this->ref_maps['todo'][ $post_id ] = 'todo:' . ( $index + 1 );
		}

		WP_CLI::log(
			sprintf(
				'Reference maps built: %d people, %d teams, %d commissies, %d discipline cases, %d todos',
				count( $this->ref_maps['person'] ),
				count( $this->ref_maps['team'] ),
				count( $this->ref_maps['commissie'] ),
				count( $this->ref_maps['discipline_case'] ),
				count( $this->ref_maps['todo'] )
			)
		);
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
		if ( is_string( $value ) && '' === $value ) {
			return null;
		}
		if ( false === $value || ( is_array( $value ) && empty( $value ) ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Export contact_info repeater field
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
				'post_type'    => 'person',
				'numberposts'  => -1,
				'post_status'  => 'any',
				'orderby'      => 'ID',
				'order'        => 'ASC',
			]
		);

		$people = [];
		$total  = count( $posts );

		foreach ( $posts as $i => $post ) {
			// Progress logging every 100 people
			if ( 0 === ( $i + 1 ) % 100 ) {
				WP_CLI::log( sprintf( '  Exported %d / %d people...', $i + 1, $total ) );
			}

			$person = [
				'_ref'   => $this->get_ref( $post->ID, 'person' ),
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'acf'    => [
					// Basic Information
					'first_name'        => get_field( 'first_name', $post->ID ),
					'infix'             => $this->normalize_value( get_field( 'infix', $post->ID ) ),
					'last_name'         => get_field( 'last_name', $post->ID ),
					'nickname'          => $this->normalize_value( get_field( 'nickname', $post->ID ) ),
					'gender'            => $this->normalize_value( get_field( 'gender', $post->ID ) ),
					'pronouns'          => $this->normalize_value( get_field( 'pronouns', $post->ID ) ),
					'birthdate'         => get_field( 'birthdate', $post->ID ),
					'former_member'     => (bool) get_field( 'former_member', $post->ID ),
					'lid-tot'           => $this->normalize_value( get_field( 'lid-tot', $post->ID ) ),
					'datum-overlijden'  => $this->normalize_value( get_field( 'datum-overlijden', $post->ID ) ),

					// Contact Information
					'contact_info'      => $this->export_contact_info( $post->ID ),

					// Addresses
					'addresses'         => $this->export_addresses( $post->ID ),

					// Work History
					'work_history'      => $this->export_work_history( $post->ID ),

					// Relationships
					'relationships'     => $this->export_relationships( $post->ID ),

					// Sportlink-Synced Fields
					'lid-sinds'         => $this->normalize_value( get_field( 'lid-sinds', $post->ID ) ),
					'leeftijdsgroep'    => $this->normalize_value( get_field( 'leeftijdsgroep', $post->ID ) ),
					'datum-vog'         => $this->normalize_value( get_field( 'datum-vog', $post->ID ) ),
					'datum-foto'        => $this->normalize_value( get_field( 'datum-foto', $post->ID ) ),
					'type-lid'          => $this->normalize_value( get_field( 'type-lid', $post->ID ) ),
					'huidig-vrijwilliger' => $this->normalize_value( get_field( 'huidig-vrijwilliger', $post->ID ) ),
					'financiele-blokkade' => (bool) get_field( 'financiele-blokkade', $post->ID ),
					'relatiecode'       => $this->normalize_value( get_field( 'relatiecode', $post->ID ) ),
					'werkfuncties'      => $this->export_werkfuncties( $post->ID ),
					'freescout-id'      => $this->normalize_value( get_field( 'freescout-id', $post->ID ) ),
					'factuur-adres'     => $this->normalize_value( get_field( 'factuur-adres', $post->ID ) ),
					'factuur-email'     => $this->normalize_value( get_field( 'factuur-email', $post->ID ) ),
					'factuur-referentie' => $this->normalize_value( get_field( 'factuur-referentie', $post->ID ) ),
				],
				'post_meta' => $this->export_person_post_meta( $post->ID ),
			];

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
				'address_label' => $row['address_label'] ?? '',
				'street'        => $row['street'] ?? '',
				'postal_code'   => $row['postal_code'] ?? '',
				'city'          => $row['city'] ?? '',
				'state'         => $row['state'] ?? '',
				'country'       => $row['country'] ?? '',
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
			$related_person_id = $row['related_person'] ?? null;
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
	 * Export werkfuncties repeater field (Sportlink-synced)
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of werkfunctie objects.
	 */
	private function export_werkfuncties( $post_id ) {
		$werkfuncties = get_field( 'werkfuncties', $post_id );

		if ( ! $werkfuncties || ! is_array( $werkfuncties ) ) {
			return [];
		}

		$exported = [];

		foreach ( $werkfuncties as $row ) {
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
	 * Export person post_meta (non-ACF fields)
	 *
	 * @param int $post_id Post ID.
	 * @return array Object with post meta fields.
	 */
	private function export_person_post_meta( $post_id ) {
		$post_meta = [
			'vog_email_sent_date'      => $this->normalize_value( get_post_meta( $post_id, 'vog_email_sent_date', true ) ),
			'vog_justis_submitted_date' => $this->normalize_value( get_post_meta( $post_id, 'vog_justis_submitted_date', true ) ),
			'vog_reminder_sent_date'   => $this->normalize_value( get_post_meta( $post_id, 'vog_reminder_sent_date', true ) ),
		];

		// Scan for dynamic meta keys (nikki, fee snapshots/forecasts)
		$all_meta = get_post_meta( $post_id );

		foreach ( $all_meta as $meta_key => $meta_values ) {
			// Match _nikki_*_total, _nikki_*_saldo, _fee_snapshot_*, _fee_forecast_*
			if ( preg_match( '/^_nikki_(\d+)_(total|saldo)$/', $meta_key ) ||
				preg_match( '/^_fee_(snapshot|forecast)_/', $meta_key ) ) {
				$value = $meta_values[0] ?? null;
				$post_meta[ $meta_key ] = $this->normalize_value( $value );
			}
		}

		return $post_meta;
	}

	/**
	 * Export teams
	 *
	 * @return array Array of team objects.
	 */
	protected function export_teams() {
		$posts = get_posts(
			[
				'post_type'    => 'team',
				'numberposts'  => -1,
				'post_status'  => [ 'publish', 'draft', 'private' ],
				'orderby'      => 'ID',
				'order'        => 'ASC',
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
					'contact_info' => $this->export_contact_info( $post->ID ),
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
				'post_type'    => 'commissie',
				'numberposts'  => -1,
				'post_status'  => [ 'publish', 'draft', 'private' ],
				'orderby'      => 'ID',
				'order'        => 'ASC',
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
					'contact_info' => $this->export_contact_info( $post->ID ),
				],
			];

			$commissies[] = $commissie;
		}

		WP_CLI::log( sprintf( '  Exported %d commissies', count( $commissies ) ) );

		return $commissies;
	}

	/**
	 * Resolve post ID from ACF field value
	 *
	 * ACF can return either a post object or just an ID depending on return format.
	 *
	 * @param mixed $field_value ACF field value.
	 * @return int|null Post ID or null if invalid.
	 */
	private function resolve_post_id( $field_value ) {
		if ( is_object( $field_value ) && isset( $field_value->ID ) ) {
			return (int) $field_value->ID;
		}
		if ( is_numeric( $field_value ) ) {
			return (int) $field_value;
		}
		return null;
	}

	/**
	 * Export discipline cases
	 *
	 * @return array Array of discipline case objects.
	 */
	protected function export_discipline_cases() {
		$posts = get_posts(
			[
				'post_type'    => 'discipline_case',
				'numberposts'  => -1,
				'post_status'  => 'any',
				'orderby'      => 'ID',
				'order'        => 'ASC',
			]
		);

		$cases = [];

		foreach ( $posts as $post ) {
			// Get person reference
			$person_field = get_field( 'person', $post->ID );
			$person_id    = $this->resolve_post_id( $person_field );
			$person_ref   = $person_id ? $this->get_ref( $person_id, 'person' ) : null;

			// Get seizoen (taxonomy term)
			$seizoen      = null;
			$seizoen_terms = wp_get_post_terms( $post->ID, 'seizoen' );
			if ( ! empty( $seizoen_terms ) && ! is_wp_error( $seizoen_terms ) ) {
				$seizoen = $seizoen_terms[0]->slug;
			}

			$case = [
				'_ref'   => $this->get_ref( $post->ID, 'discipline_case' ),
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'acf'    => [
					'dossier_id'            => get_field( 'dossier_id', $post->ID ),
					'person'                => $person_ref,
					'match_date'            => $this->normalize_value( get_field( 'match_date', $post->ID ) ),
					'processing_date'       => $this->normalize_value( get_field( 'processing_date', $post->ID ) ),
					'match_description'     => $this->normalize_value( get_field( 'match_description', $post->ID ) ),
					'team_name'             => $this->normalize_value( get_field( 'team_name', $post->ID ) ),
					'charge_codes'          => $this->normalize_value( get_field( 'charge_codes', $post->ID ) ),
					'charge_description'    => $this->normalize_value( get_field( 'charge_description', $post->ID ) ),
					'sanction_description'  => $this->normalize_value( get_field( 'sanction_description', $post->ID ) ),
					'administrative_fee'    => $this->normalize_value( (float) get_field( 'administrative_fee', $post->ID ) ),
					'is_charged'            => (bool) get_field( 'is_charged', $post->ID ),
				],
				'seizoen' => $seizoen,
			];

			$cases[] = $case;
		}

		WP_CLI::log( sprintf( '  Exported %d discipline cases', count( $cases ) ) );

		return $cases;
	}

	/**
	 * Export todos
	 *
	 * @return array Array of todo objects.
	 */
	protected function export_todos() {
		$posts = get_posts(
			[
				'post_type'    => 'rondo_todo',
				'numberposts'  => -1,
				'post_status'  => [ 'rondo_open', 'rondo_awaiting', 'rondo_completed' ],
				'orderby'      => 'ID',
				'order'        => 'ASC',
			]
		);

		$todos = [];

		foreach ( $posts as $post ) {
			// Get related persons (array of post IDs)
			$related_persons_field = get_field( 'related_persons', $post->ID );
			$related_persons_refs  = [];

			if ( is_array( $related_persons_field ) ) {
				foreach ( $related_persons_field as $person_field ) {
					$person_id = $this->resolve_post_id( $person_field );
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
					$note_visibility = get_comment_meta( $comment->comment_ID, '_note_visibility', true );
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

			$exported[] = $exported_comment;
			$counts[ $comment->comment_type ]++;
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

		$fee_config_count = 0;
		$discount_config_count = 0;

		if ( ! is_wp_error( $seasons ) ) {
			foreach ( $seasons as $season ) {
				$season_slug = $season->slug;

				// Membership fees for this season
				$fee_option_key = "rondo_membership_fees_{$season_slug}";
				$fee_config = get_option( $fee_option_key );
				if ( $fee_config ) {
					$settings[ $fee_option_key ] = $fee_config;
					$fee_config_count++;
				}

				// Family discount for this season
				$discount_option_key = "rondo_family_discount_{$season_slug}";
				$discount_config = get_option( $discount_option_key );
				if ( $discount_config ) {
					$settings[ $discount_option_key ] = $discount_config;
					$discount_config_count++;
				}
			}
		}

		// Player roles and excluded roles
		$settings['rondo_player_roles'] = get_option( 'rondo_player_roles', [] );
		$settings['rondo_excluded_roles'] = get_option( 'rondo_excluded_roles', [] );

		// VOG email settings (nullable)
		$settings['rondo_vog_from_email'] = $this->normalize_value( get_option( 'rondo_vog_from_email', '' ) );
		$settings['rondo_vog_from_name'] = $this->normalize_value( get_option( 'rondo_vog_from_name', '' ) );

		// VOG email templates (nullable, HTML)
		$settings['rondo_vog_template_new'] = $this->normalize_value( get_option( 'rondo_vog_template_new', '' ) );
		$settings['rondo_vog_template_renewal'] = $this->normalize_value( get_option( 'rondo_vog_template_renewal', '' ) );
		$settings['rondo_vog_reminder_template_new'] = $this->normalize_value( get_option( 'rondo_vog_reminder_template_new', '' ) );
		$settings['rondo_vog_reminder_template_renewal'] = $this->normalize_value( get_option( 'rondo_vog_reminder_template_renewal', '' ) );

		// VOG exempt commissies (convert post IDs to fixture refs)
		$exempt_commissies = get_option( 'rondo_vog_exempt_commissies', [] );
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

		WP_CLI::log( sprintf( '  Exported settings (%d fee configs, %d family discount configs)', $fee_config_count, $discount_config_count ) );

		return $settings;
	}
}
