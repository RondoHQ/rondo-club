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
	 * Export people (stub - to be implemented in plan 02)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_people() {
		return [];
	}

	/**
	 * Export teams (stub - to be implemented in plan 03)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_teams() {
		return [];
	}

	/**
	 * Export commissies (stub - to be implemented in plan 03)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_commissies() {
		return [];
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
	 * Export taxonomies (stub - to be implemented in plan 04)
	 *
	 * @return array Empty array for now.
	 */
	protected function export_taxonomies() {
		return [];
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
