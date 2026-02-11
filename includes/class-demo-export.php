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

		// Build meta section
		WP_CLI::log( 'Building meta section...' );
		$meta = $this->build_meta();

		// Build all entity sections (stubs for now)
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
	 * Build meta section
	 *
	 * @return array Meta object with version, timestamp, source, and record counts.
	 */
	private function build_meta() {
		$comment_count = $this->count_comments_by_type();

		return [
			'version'       => '1.0',
			'exported_at'   => gmdate( 'c' ),
			'source'        => 'Production export',
			'record_counts' => [
				'people'           => count( $this->ref_maps['person'] ),
				'teams'            => count( $this->ref_maps['team'] ),
				'commissies'       => count( $this->ref_maps['commissie'] ),
				'discipline_cases' => count( $this->ref_maps['discipline_case'] ),
				'todos'            => count( $this->ref_maps['todo'] ),
				'comments'         => $comment_count,
			],
		];
	}

	/**
	 * Count comments by Rondo comment types
	 *
	 * @return int Total count of notes, activities, and email logs.
	 */
	private function count_comments_by_type() {
		$comment_count = get_comments(
			[
				'type__in' => [ 'rondo_note', 'rondo_activity', 'rondo_email' ],
				'count'    => true,
			]
		);

		return (int) $comment_count;
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
	 * Export people (stub - to be implemented in plan 02)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_people() {
		return [];
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
	 * Export discipline cases (stub - to be implemented in plan 02)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_discipline_cases() {
		return [];
	}

	/**
	 * Export todos (stub - to be implemented in plan 02)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_todos() {
		return [];
	}

	/**
	 * Export comments (stub - to be implemented in plan 02)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_comments() {
		return [];
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
	 * Export settings (stub - to be implemented in plan 04)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_settings() {
		return [];
	}
}
