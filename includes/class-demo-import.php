<?php
/**
 * Demo Import Class
 *
 * Imports demo fixture data into WordPress in portable JSON format.
 *
 * @package Rondo
 */

namespace Rondo\Demo;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DemoImport
 *
 * Orchestrates the import of all Rondo data from a fixture file with reference ID resolution.
 */
class DemoImport {

	/**
	 * Reference ID mappings from fixture refs to WordPress post/term IDs
	 *
	 * @var array
	 */
	private $ref_map = [];

	/**
	 * Parsed fixture data
	 *
	 * @var array
	 */
	private $fixture;

	/**
	 * Path to fixture file
	 *
	 * @var string
	 */
	private $fixture_path;

	/**
	 * Date offset interval for shifting dates
	 *
	 * @var \DateInterval
	 */
	private $date_offset;

	/**
	 * Export year
	 *
	 * @var int
	 */
	private $export_year;

	/**
	 * Import year
	 *
	 * @var int
	 */
	private $import_year;

	/**
	 * Year shift amount
	 *
	 * @var int
	 */
	private $year_shift;

	/**
	 * Constructor
	 *
	 * @param string $fixture_path Path to read the JSON fixture file.
	 */
	public function __construct( $fixture_path ) {
		$this->fixture_path = $fixture_path;
	}

	/**
	 * Clean all existing Rondo data before import
	 *
	 * Removes all Rondo-specific posts, taxonomy terms, and options.
	 * Does NOT remove user accounts or WordPress core data.
	 */
	public function clean() {
		WP_CLI::log( "Cleaning existing Rondo data..." );

		// 1. Delete all comments of custom types first (before posts to avoid FK issues)
		$comment_types = [ "rondo_note", "rondo_activity", "rondo_email" ];
		$comments = get_comments( [
			"type__in" => $comment_types,
			"number"   => 0,
		] );

		foreach ( $comments as $comment ) {
			wp_delete_comment( $comment->comment_ID, true ); // force delete, skip trash
		}

		WP_CLI::log( sprintf( "  Deleted %d comments", count( $comments ) ) );

		// 2. Delete all posts for each CPT (force delete, skip trash)
		$post_types = [ "rondo_todo", "discipline_case", "person", "team", "commissie" ];
		$total_posts = 0;

		foreach ( $post_types as $post_type ) {
			// Special handling for rondo_todo which has custom statuses
			if ( "rondo_todo" === $post_type ) {
				$posts = get_posts( [
					"post_type"      => $post_type,
					"numberposts"    => -1,
					"post_status"    => [ "rondo_open", "rondo_awaiting", "rondo_completed", "any" ],
					"fields"         => "ids",
				] );
			} else {
				$posts = get_posts( [
					"post_type"      => $post_type,
					"numberposts"    => -1,
					"post_status"    => "any",
					"fields"         => "ids",
				] );
			}

			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id, true ); // force delete, bypass trash
			}

			WP_CLI::log( sprintf( "  Deleted %d %s posts", count( $posts ), $post_type ) );
			$total_posts += count( $posts );
		}

		// 3. Delete taxonomy terms
		$taxonomies = [ "relationship_type", "seizoen" ];
		$total_terms = 0;

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( [
				"taxonomy"   => $taxonomy,
				"hide_empty" => false,
				"fields"     => "ids",
			] );

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					wp_delete_term( $term_id, $taxonomy );
				}

				WP_CLI::log( sprintf( "  Deleted %d %s terms", count( $terms ), $taxonomy ) );
				$total_terms += count( $terms );
			}
		}

		// 4. Delete Rondo-specific WordPress options
		// Static option keys
		$option_keys = [
			"rondo_club_name",
			"rondo_player_roles",
			"rondo_excluded_roles",
			"rondo_vog_from_email",
			"rondo_vog_from_name",
			"rondo_vog_template_new",
			"rondo_vog_template_renewal",
			"rondo_vog_reminder_template_new",
			"rondo_vog_reminder_template_renewal",
			"rondo_vog_exempt_commissies",
		];

		foreach ( $option_keys as $key ) {
			delete_option( $key );
		}

		// Dynamic season-keyed options (scan for pattern)
		global $wpdb;
		$dynamic_options = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			 WHERE option_name LIKE 'rondo_membership_fees_%'
			    OR option_name LIKE 'rondo_family_discount_%'"
		);

		foreach ( $dynamic_options as $option_name ) {
			delete_option( $option_name );
		}

		WP_CLI::log( sprintf( "  Deleted %d options", count( $option_keys ) + count( $dynamic_options ) ) );

		WP_CLI::log( "Clean complete." );
	}


	/**
	 * Main import orchestration method
	 *
	 * Imports all entities in correct dependency order.
	 */
	public function import() {
		WP_CLI::log( 'Starting demo data import...' );

		// Read and validate fixture file
		WP_CLI::log( sprintf( 'Reading fixture from: %s', $this->fixture_path ) );
		$this->read_fixture();

		// Calculate date shift offset
		$export_date = new \DateTime( $this->fixture['meta']['exported_at'] );
		$today = new \DateTime( 'today', wp_timezone() );
		$this->date_offset = $today->diff( $export_date );
		$this->export_year = (int) $export_date->format( 'Y' );
		$this->import_year = (int) $today->format( 'Y' );
		$this->year_shift = $this->import_year - $this->export_year;

		WP_CLI::log(
			sprintf(
				'Date shift: %d years, %d months, %d days (from %s to %s)',
				$this->date_offset->y,
				$this->date_offset->m,
				$this->date_offset->d,
				$export_date->format( 'Y-m-d' ),
				$today->format( 'Y-m-d' )
			)
		);

		// Import in dependency order
		WP_CLI::log( 'Importing taxonomies...' );
		$this->import_taxonomies();

		WP_CLI::log( 'Importing teams...' );
		$this->import_teams();

		WP_CLI::log( 'Importing commissies...' );
		$this->import_commissies();

		WP_CLI::log( 'Importing people...' );
		$this->import_people();

		WP_CLI::log( 'Importing discipline cases...' );
		$this->import_discipline_cases();

		WP_CLI::log( 'Importing todos...' );
		$this->import_todos();

		WP_CLI::log( 'Importing comments...' );
		$this->import_comments();

		WP_CLI::log( 'Importing settings...' );
		$this->import_settings();

		WP_CLI::log( 'Import complete!' );
	}

	/**
	 * Read and validate fixture JSON file
	 */
	private function read_fixture() {
		if ( ! file_exists( $this->fixture_path ) ) {
			WP_CLI::error( sprintf( 'Fixture file not found: %s', $this->fixture_path ) );
		}

		if ( ! is_readable( $this->fixture_path ) ) {
			WP_CLI::error( sprintf( 'Fixture file not readable: %s', $this->fixture_path ) );
		}

		$json = file_get_contents( $this->fixture_path );

		if ( false === $json ) {
			WP_CLI::error( sprintf( 'Failed to read fixture file: %s', $this->fixture_path ) );
		}

		$this->fixture = json_decode( $json, true );

		if ( null === $this->fixture ) {
			WP_CLI::error( 'Failed to decode fixture JSON: ' . json_last_error_msg() );
		}

		// Validate fixture format
		if ( ! isset( $this->fixture['meta']['version'] ) ) {
			WP_CLI::error( 'Invalid fixture: missing meta.version' );
		}

		if ( '1.0' !== $this->fixture['meta']['version'] ) {
			WP_CLI::error( sprintf( 'Unsupported fixture version: %s', $this->fixture['meta']['version'] ) );
		}

		// Validate required top-level keys
		$required_keys = [ 'people', 'teams', 'commissies', 'discipline_cases', 'todos', 'comments', 'taxonomies', 'settings' ];
		foreach ( $required_keys as $key ) {
			if ( ! isset( $this->fixture[ $key ] ) ) {
				WP_CLI::error( sprintf( 'Invalid fixture: missing required key "%s"', $key ) );
			}
		}

		WP_CLI::log( sprintf( 'Fixture loaded: version %s, exported %s', $this->fixture['meta']['version'], $this->fixture['meta']['exported_at'] ) );
		WP_CLI::log( sprintf( 'Record counts: %d people, %d teams, %d commissies, %d discipline cases, %d todos, %d comments',
			$this->fixture['meta']['record_counts']['people'] ?? 0,
			$this->fixture['meta']['record_counts']['teams'] ?? 0,
			$this->fixture['meta']['record_counts']['commissies'] ?? 0,
			$this->fixture['meta']['record_counts']['discipline_cases'] ?? 0,
			$this->fixture['meta']['record_counts']['todos'] ?? 0,
			$this->fixture['meta']['record_counts']['comments'] ?? 0
		) );
	}

	/**
	 * Resolve a fixture reference to a WordPress post/term ID
	 *
	 * @param string $ref Fixture reference (e.g., "person:1", "team:5", "relationship_type:parent").
	 * @return int|null WordPress ID or null if not found.
	 */
	private function resolve_ref( $ref ) {
		if ( isset( $this->ref_map[ $ref ] ) ) {
			return $this->ref_map[ $ref ];
		}
		return null;
	}

	/**
	 * Shift a date by the calculated offset
	 *
	 * @param string|null $date_string Date string to shift.
	 * @param string      $format Date format ('Y-m-d', 'Ymd', 'Y-m-d H:i:s', 'c').
	 * @return string|null Shifted date or null if input is null/empty.
	 */
	private function shift_date( $date_string, $format = 'Y-m-d' ) {
		if ( empty( $date_string ) ) {
			return null;
		}

		try {
			$date = new \DateTime( $date_string, wp_timezone() );
			$date->add( $this->date_offset );

			// Return in the requested format
			if ( 'c' === $format ) {
				// ISO 8601 format
				return $date->format( 'Y-m-d\TH:i:s' );
			}
			return $date->format( $format );
		} catch ( \Exception $e ) {
			WP_CLI::warning( sprintf( 'Failed to shift date "%s": %s', $date_string, $e->getMessage() ) );
			return $date_string;
		}
	}

	/**
	 * Shift a birthdate by full years only (preserving month/day)
	 *
	 * @param string|null $date_string Birthdate in Y-m-d format.
	 * @return string|null Shifted birthdate or null if input is null/empty.
	 */
	private function shift_birthdate( $date_string ) {
		if ( empty( $date_string ) ) {
			return null;
		}

		try {
			$date = new \DateTime( $date_string, wp_timezone() );

			// Shift by full years only to preserve age accuracy
			$new_year = (int) $date->format( 'Y' ) + $this->year_shift;
			$month = $date->format( 'm' );
			$day = $date->format( 'd' );

			// Handle leap year edge case (Feb 29 -> Feb 28 in non-leap years)
			if ( '02' === $month && '29' === $day && ! checkdate( 2, 29, $new_year ) ) {
				$day = '28';
			}

			return sprintf( '%04d-%s-%s', $new_year, $month, $day );
		} catch ( \Exception $e ) {
			WP_CLI::warning( sprintf( 'Failed to shift birthdate "%s": %s', $date_string, $e->getMessage() ) );
			return $date_string;
		}
	}

	/**
	 * Shift a season slug (e.g., "2025-2026" -> "2026-2027")
	 *
	 * @param string|null $slug Season slug in "YYYY-YYYY" format.
	 * @return string|null Shifted season slug or null if input is null/empty.
	 */
	private function shift_season_slug( $slug ) {
		if ( empty( $slug ) ) {
			return null;
		}

		// Parse season slug like "2025-2026"
		if ( preg_match( '/^(\d{4})-(\d{4})$/', $slug, $matches ) ) {
			$start_year = (int) $matches[1] + $this->year_shift;
			$end_year = (int) $matches[2] + $this->year_shift;
			return sprintf( '%04d-%04d', $start_year, $end_year );
		}

		WP_CLI::warning( sprintf( 'Invalid season slug format: %s', $slug ) );
		return $slug;
	}

	/**
	 * Import taxonomies (relationship_types and seizoenen)
	 */
	private function import_taxonomies() {
		$taxonomies = $this->fixture['taxonomies'] ?? [];

		// Import relationship types
		$relationship_types = $taxonomies['relationship_types'] ?? [];
		$relationship_type_count = 0;

		foreach ( $relationship_types as $rel_type ) {
			$name = $rel_type['name'];
			$slug = $rel_type['slug'];
			$ref  = $rel_type['_ref'];

			// Insert term (or get existing)
			$term = wp_insert_term( $name, 'relationship_type', [ 'slug' => $slug ] );

			if ( is_wp_error( $term ) ) {
				// Term might already exist
				if ( 'term_exists' === $term->get_error_code() ) {
					$existing = get_term_by( 'slug', $slug, 'relationship_type' );
					if ( $existing ) {
						$term_id = $existing->term_id;
					} else {
						WP_CLI::warning( sprintf( 'Failed to get existing relationship_type: %s', $slug ) );
						continue;
					}
				} else {
					WP_CLI::warning( sprintf( 'Failed to create relationship_type "%s": %s', $name, $term->get_error_message() ) );
					continue;
				}
			} else {
				$term_id = $term['term_id'];
			}

			// Store ref mapping
			$this->ref_map[ $ref ] = $term_id;
			$relationship_type_count++;
		}

		// Second pass: set inverse relationship types
		foreach ( $relationship_types as $rel_type ) {
			$ref = $rel_type['_ref'];
			$inverse_ref = $rel_type['acf']['inverse_relationship_type'] ?? null;

			if ( $inverse_ref ) {
				$term_id = $this->resolve_ref( $ref );
				$inverse_term_id = $this->resolve_ref( $inverse_ref );

				if ( $term_id && $inverse_term_id ) {
					update_field( 'inverse_relationship_type', $inverse_term_id, 'relationship_type_' . $term_id );
				}
			}
		}

		WP_CLI::log( sprintf( '  Imported %d relationship types', $relationship_type_count ) );

		// Import seizoenen
		$seizoenen = $taxonomies['seizoenen'] ?? [];
		$seizoen_count = 0;

		foreach ( $seizoenen as $seizoen ) {
			$name = $this->shift_season_slug( $seizoen['name'] );
			$slug = $this->shift_season_slug( $seizoen['slug'] );
			$is_current = $seizoen['is_current'] ?? false;

			// Insert term (or get existing)
			$term = wp_insert_term( $name, 'seizoen', [ 'slug' => $slug ] );

			if ( is_wp_error( $term ) ) {
				// Term might already exist
				if ( 'term_exists' === $term->get_error_code() ) {
					$existing = get_term_by( 'slug', $slug, 'seizoen' );
					if ( $existing ) {
						$term_id = $existing->term_id;
					} else {
						WP_CLI::warning( sprintf( 'Failed to get existing seizoen: %s', $slug ) );
						continue;
					}
				} else {
					WP_CLI::warning( sprintf( 'Failed to create seizoen "%s": %s', $name, $term->get_error_message() ) );
					continue;
				}
			} else {
				$term_id = $term['term_id'];
			}

			// Set is_current_season term meta
			update_term_meta( $term_id, 'is_current_season', $is_current ? '1' : '0' );

			$seizoen_count++;
		}

		WP_CLI::log( sprintf( '  Imported %d seizoenen', $seizoen_count ) );
	}

	/**
	 * Import teams
	 */
	private function import_teams() {
		$teams = $this->fixture['teams'] ?? [];
		$total = count( $teams );

		// Pass 1: Create all team posts
		foreach ( $teams as $team ) {
			$ref     = $team['_ref'];
			$title   = $team['title'];
			$content = $team['content'] ?? '';
			$status  = $team['status'];

			$post_id = wp_insert_post( [
				'post_type'    => 'team',
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
			], true );

			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( sprintf( 'Failed to create team "%s": %s', $title, $post_id->get_error_message() ) );
				continue;
			}

			// Store ref mapping
			$this->ref_map[ $ref ] = $post_id;

			// Set ACF fields
			$acf = $team['acf'] ?? [];

			if ( isset( $acf['website'] ) ) {
				update_field( 'website', $acf['website'], $post_id );
			}

			if ( isset( $acf['contact_info'] ) ) {
				update_field( 'contact_info', $acf['contact_info'], $post_id );
			}
		}

		// Pass 2: Resolve parent references
		foreach ( $teams as $team ) {
			$ref = $team['_ref'];
			$parent_ref = $team['parent'] ?? null;

			if ( $parent_ref ) {
				$post_id = $this->resolve_ref( $ref );
				$parent_id = $this->resolve_ref( $parent_ref );

				if ( $post_id && $parent_id ) {
					wp_update_post( [
						'ID'          => $post_id,
						'post_parent' => $parent_id,
					] );
				}
			}
		}

		WP_CLI::log( sprintf( '  Imported %d teams', $total ) );
	}

	/**
	 * Import commissies
	 */
	private function import_commissies() {
		$commissies = $this->fixture['commissies'] ?? [];
		$total = count( $commissies );

		// Pass 1: Create all commissie posts
		foreach ( $commissies as $commissie ) {
			$ref     = $commissie['_ref'];
			$title   = $commissie['title'];
			$content = $commissie['content'] ?? '';
			$status  = $commissie['status'];

			$post_id = wp_insert_post( [
				'post_type'    => 'commissie',
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
			], true );

			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( sprintf( 'Failed to create commissie "%s": %s', $title, $post_id->get_error_message() ) );
				continue;
			}

			// Store ref mapping
			$this->ref_map[ $ref ] = $post_id;

			// Set ACF fields
			$acf = $commissie['acf'] ?? [];

			if ( isset( $acf['website'] ) ) {
				update_field( 'website', $acf['website'], $post_id );
			}

			if ( isset( $acf['contact_info'] ) ) {
				update_field( 'contact_info', $acf['contact_info'], $post_id );
			}
		}

		// Pass 2: Resolve parent references
		foreach ( $commissies as $commissie ) {
			$ref = $commissie['_ref'];
			$parent_ref = $commissie['parent'] ?? null;

			if ( $parent_ref ) {
				$post_id = $this->resolve_ref( $ref );
				$parent_id = $this->resolve_ref( $parent_ref );

				if ( $post_id && $parent_id ) {
					wp_update_post( [
						'ID'          => $post_id,
						'post_parent' => $parent_id,
					] );
				}
			}
		}

		WP_CLI::log( sprintf( '  Imported %d commissies', $total ) );
	}

	/**
	 * Import people
	 */
	private function import_people() {
		$people = $this->fixture['people'] ?? [];
		$total = count( $people );

		// Pass 1: Create all person posts with simple ACF fields
		foreach ( $people as $i => $person ) {
			// Progress logging every 100 people
			if ( 0 === ( $i + 1 ) % 100 ) {
				WP_CLI::log( sprintf( '  Pass 1: Created %d / %d people...', $i + 1, $total ) );
			}

			$ref    = $person['_ref'];
			$title  = $person['title'];
			$status = $person['status'];

			$post_id = wp_insert_post( [
				'post_type'   => 'person',
				'post_title'  => $title,
				'post_status' => $status,
			], true );

			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( sprintf( 'Failed to create person "%s": %s', $title, $post_id->get_error_message() ) );
				continue;
			}

			// Store ref mapping
			$this->ref_map[ $ref ] = $post_id;

			// Set simple ACF fields
			$acf = $person['acf'] ?? [];

			// Basic Information
			update_field( 'first_name', $acf['first_name'] ?? '', $post_id );
			update_field( 'infix', $acf['infix'] ?? null, $post_id );
			update_field( 'last_name', $acf['last_name'] ?? '', $post_id );
			update_field( 'nickname', $acf['nickname'] ?? null, $post_id );
			update_field( 'gender', $acf['gender'] ?? null, $post_id );
			update_field( 'pronouns', $acf['pronouns'] ?? null, $post_id );
			update_field( 'birthdate', $this->shift_birthdate( $acf['birthdate'] ?? '' ), $post_id );
			update_field( 'former_member', $acf['former_member'] ?? false, $post_id );
			update_field( 'lid-tot', $this->shift_date( $acf['lid-tot'] ?? null ), $post_id );
			update_field( 'datum-overlijden', $this->shift_date( $acf['datum-overlijden'] ?? null ), $post_id );

			// Contact Information
			update_field( 'contact_info', $acf['contact_info'] ?? [], $post_id );

			// Addresses
			update_field( 'addresses', $acf['addresses'] ?? [], $post_id );

			// Sportlink-Synced Fields
			update_field( 'lid-sinds', $this->shift_date( $acf['lid-sinds'] ?? null ), $post_id );
			update_field( 'leeftijdsgroep', $acf['leeftijdsgroep'] ?? null, $post_id );
			update_field( 'datum-vog', $this->shift_date( $acf['datum-vog'] ?? null ), $post_id );
			update_field( 'datum-foto', $this->shift_date( $acf['datum-foto'] ?? null ), $post_id );
			update_field( 'type-lid', $acf['type-lid'] ?? null, $post_id );
			update_field( 'huidig-vrijwilliger', $acf['huidig-vrijwilliger'] ?? null, $post_id );
			update_field( 'financiele-blokkade', $acf['financiele-blokkade'] ?? false, $post_id );
			update_field( 'relatiecode', $acf['relatiecode'] ?? null, $post_id );
			update_field( 'freescout-id', $acf['freescout-id'] ?? null, $post_id );
			update_field( 'factuur-adres', $acf['factuur-adres'] ?? null, $post_id );
			update_field( 'factuur-email', $acf['factuur-email'] ?? null, $post_id );
			update_field( 'factuur-referentie', $acf['factuur-referentie'] ?? null, $post_id );

			// Set post_meta directly (non-ACF fields)
			$post_meta = $person['post_meta'] ?? [];
			foreach ( $post_meta as $meta_key => $meta_value ) {
				if ( null !== $meta_value ) {
					// Shift year in _nikki_YEAR_* keys
					if ( preg_match( '/^_nikki_(\d{4})_/', $meta_key, $matches ) ) {
						$old_year = (int) $matches[1];
						$new_year = $old_year + $this->year_shift;
						$meta_key = str_replace( '_nikki_' . $old_year . '_', '_nikki_' . $new_year . '_', $meta_key );
					}

					// Shift season in _fee_snapshot_SEASON and _fee_forecast_SEASON keys
					if ( preg_match( '/^_fee_(snapshot|forecast)_(.+)$/', $meta_key, $matches ) ) {
						$type = $matches[1];
						$season = $matches[2];
						$shifted_season = $this->shift_season_slug( $season );
						$meta_key = '_fee_' . $type . '_' . $shifted_season;
					}

					// Shift VOG tracking dates
					if ( in_array( $meta_key, [ 'vog_email_sent_date', 'vog_justis_submitted_date', 'vog_reminder_sent_date' ], true ) ) {
						$meta_value = $this->shift_date( $meta_value );
					}

					update_post_meta( $post_id, $meta_key, $meta_value );
				}
			}
		}

		WP_CLI::log( sprintf( '  Pass 1: Created %d people', $total ) );

		// Pass 2: Resolve refs in work_history, werkfuncties, and relationships
		WP_CLI::log( '  Pass 2: Resolving relationships...' );

		foreach ( $people as $i => $person ) {
			// Progress logging every 100 people
			if ( 0 === ( $i + 1 ) % 100 ) {
				WP_CLI::log( sprintf( '  Pass 2: Resolved %d / %d people...', $i + 1, $total ) );
			}

			$ref = $person['_ref'];
			$post_id = $this->resolve_ref( $ref );

			if ( ! $post_id ) {
				continue;
			}

			$acf = $person['acf'] ?? [];

			// Resolve work_history refs
			$work_history = $acf['work_history'] ?? [];
			$resolved_work_history = [];

			foreach ( $work_history as $row ) {
				$team_ref = $row['team'] ?? null;
				$resolved_team_id = $team_ref ? $this->resolve_ref( $team_ref ) : null;

				$resolved_work_history[] = [
					'team'        => $resolved_team_id,
					'entity_type' => $row['entity_type'] ?? '',
					'job_title'   => $row['job_title'] ?? '',
					'description' => $row['description'] ?? null,
					'start_date'  => $this->shift_date( $row['start_date'] ?? null ),
					'end_date'    => $this->shift_date( $row['end_date'] ?? null ),
					'is_current'  => $row['is_current'] ?? false,
				];
			}

			if ( ! empty( $resolved_work_history ) ) {
				update_field( 'work_history', $resolved_work_history, $post_id );
			}

			// Resolve werkfuncties refs
			$werkfuncties = $acf['werkfuncties'] ?? [];
			$resolved_werkfuncties = [];

			foreach ( $werkfuncties as $row ) {
				$team_ref = $row['team'] ?? null;
				$resolved_team_id = $team_ref ? $this->resolve_ref( $team_ref ) : null;

				$resolved_werkfuncties[] = [
					'team'        => $resolved_team_id,
					'entity_type' => $row['entity_type'] ?? '',
					'job_title'   => $row['job_title'] ?? '',
					'description' => $row['description'] ?? null,
					'start_date'  => $this->shift_date( $row['start_date'] ?? null ),
					'end_date'    => $this->shift_date( $row['end_date'] ?? null ),
					'is_current'  => $row['is_current'] ?? false,
				];
			}

			if ( ! empty( $resolved_werkfuncties ) ) {
				update_field( 'werkfuncties', $resolved_werkfuncties, $post_id );
			}

			// Resolve relationships refs
			$relationships = $acf['relationships'] ?? [];
			$resolved_relationships = [];

			foreach ( $relationships as $row ) {
				$related_person_ref = $row['related_person'] ?? null;
				$relationship_type_ref = $row['relationship_type'] ?? null;

				$resolved_person_id = $related_person_ref ? $this->resolve_ref( $related_person_ref ) : null;
				$resolved_type_id = $relationship_type_ref ? $this->resolve_ref( $relationship_type_ref ) : null;

				if ( $resolved_person_id && $resolved_type_id ) {
					$resolved_relationships[] = [
						'related_person'     => $resolved_person_id,
						'relationship_type'  => $resolved_type_id,
						'relationship_label' => $row['relationship_label'] ?? null,
					];
				}
			}

			if ( ! empty( $resolved_relationships ) ) {
				update_field( 'relationships', $resolved_relationships, $post_id );
			}
		}

		WP_CLI::log( sprintf( '  Pass 2: Resolved relationships for %d people', $total ) );
	}

	/**
	 * Import discipline cases
	 */
	private function import_discipline_cases() {
		$cases = $this->fixture['discipline_cases'] ?? [];
		$total = count( $cases );

		foreach ( $cases as $case ) {
			$ref    = $case['_ref'];
			$title  = $case['title'];
			$status = $case['status'];

			$post_id = wp_insert_post( [
				'post_type'   => 'discipline_case',
				'post_title'  => $title,
				'post_status' => $status,
			], true );

			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( sprintf( 'Failed to create discipline case "%s": %s', $title, $post_id->get_error_message() ) );
				continue;
			}

			// Store ref mapping
			$this->ref_map[ $ref ] = $post_id;

			// Set ACF fields
			$acf = $case['acf'] ?? [];

			update_field( 'dossier_id', $acf['dossier_id'] ?? '', $post_id );
			update_field( 'match_date', $this->shift_date( $acf['match_date'] ?? null, 'Ymd' ), $post_id );
			update_field( 'processing_date', $this->shift_date( $acf['processing_date'] ?? null, 'Ymd' ), $post_id );
			update_field( 'match_description', $acf['match_description'] ?? null, $post_id );
			update_field( 'team_name', $acf['team_name'] ?? null, $post_id );
			update_field( 'charge_codes', $acf['charge_codes'] ?? null, $post_id );
			update_field( 'charge_description', $acf['charge_description'] ?? null, $post_id );
			update_field( 'sanction_description', $acf['sanction_description'] ?? null, $post_id );
			update_field( 'administrative_fee', $acf['administrative_fee'] ?? null, $post_id );
			update_field( 'is_charged', $acf['is_charged'] ?? false, $post_id );

			// Resolve person ref
			$person_ref = $acf['person'] ?? null;
			if ( $person_ref ) {
				$person_id = $this->resolve_ref( $person_ref );
				if ( $person_id ) {
					update_field( 'person', $person_id, $post_id );
				}
			}

			// Set seizoen taxonomy (shift the season slug)
			$seizoen_slug = $case['seizoen'] ?? null;
			if ( $seizoen_slug ) {
				$shifted_seizoen = $this->shift_season_slug( $seizoen_slug );
				wp_set_object_terms( $post_id, $shifted_seizoen, 'seizoen' );
			}
		}

		WP_CLI::log( sprintf( '  Imported %d discipline cases', $total ) );
	}

	/**
	 * Import todos
	 */
	private function import_todos() {
		$todos = $this->fixture['todos'] ?? [];
		$total = count( $todos );

		foreach ( $todos as $todo ) {
			$ref     = $todo['_ref'];
			$title   = $todo['title'];
			$content = $todo['content'] ?? '';
			$status  = $todo['status'];
			$date    = $this->shift_date( $todo['date'], 'c' );

			$post_id = wp_insert_post( [
				'post_type'    => 'rondo_todo',
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
				'post_date'    => $date,
			], true );

			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( sprintf( 'Failed to create todo "%s": %s', $title, $post_id->get_error_message() ) );
				continue;
			}

			// Store ref mapping
			$this->ref_map[ $ref ] = $post_id;

			// Set ACF fields
			$acf = $todo['acf'] ?? [];

			// Resolve related_persons refs
			$related_persons_refs = $acf['related_persons'] ?? [];
			$resolved_person_ids = [];

			foreach ( $related_persons_refs as $person_ref ) {
				$person_id = $this->resolve_ref( $person_ref );
				if ( $person_id ) {
					$resolved_person_ids[] = $person_id;
				}
			}

			update_field( 'related_persons', $resolved_person_ids, $post_id );
			update_field( 'notes', $acf['notes'] ?? null, $post_id );
			update_field( 'awaiting_since', $this->shift_date( $acf['awaiting_since'] ?? null, 'Y-m-d H:i:s' ), $post_id );
			update_field( 'due_date', $this->shift_date( $acf['due_date'] ?? null ), $post_id );
		}

		WP_CLI::log( sprintf( '  Imported %d todos', $total ) );
	}

	/**
	 * Import comments (notes, activities, emails)
	 */
	private function import_comments() {
		$comments = $this->fixture['comments'] ?? [];
		$total = count( $comments );
		$counts = [
			'rondo_note'     => 0,
			'rondo_activity' => 0,
			'rondo_email'    => 0,
		];

		foreach ( $comments as $comment ) {
			$type       = $comment['type'];
			$person_ref = $comment['person'];
			$content    = $comment['content'];
			$date       = $this->shift_date( $comment['date'], 'c' );
			$author_id  = $comment['author_id'] ?? 0;

			// Resolve person ref to get post_id
			$post_id = $this->resolve_ref( $person_ref );

			if ( ! $post_id ) {
				WP_CLI::warning( sprintf( 'Failed to resolve person ref "%s" for comment', $person_ref ) );
				continue;
			}

			// Insert comment
			$comment_id = wp_insert_comment( [
				'comment_post_ID'  => $post_id,
				'comment_type'     => $type,
				'comment_content'  => $content,
				'comment_date'     => $date,
				'comment_date_gmt' => get_gmt_from_date( $date ),
				'user_id'          => $author_id,
				'comment_approved' => 1,
			] );

			if ( ! $comment_id || is_wp_error( $comment_id ) ) {
				WP_CLI::warning( sprintf( 'Failed to create comment on post %d', $post_id ) );
				continue;
			}

			// Set comment meta based on type
			$meta = $comment['meta'] ?? [];

			switch ( $type ) {
				case 'rondo_note':
					if ( isset( $meta['_note_visibility'] ) ) {
						update_comment_meta( $comment_id, '_note_visibility', $meta['_note_visibility'] );
					}
					break;

				case 'rondo_activity':
					if ( isset( $meta['activity_type'] ) ) {
						update_comment_meta( $comment_id, 'activity_type', $meta['activity_type'] );
					}
					if ( isset( $meta['activity_date'] ) ) {
						update_comment_meta( $comment_id, 'activity_date', $this->shift_date( $meta['activity_date'] ) );
					}
					if ( isset( $meta['activity_time'] ) ) {
						update_comment_meta( $comment_id, 'activity_time', $meta['activity_time'] );
					}

					// Resolve participants refs
					$participants_refs = $meta['participants'] ?? [];
					$resolved_participants = [];

					foreach ( $participants_refs as $participant_ref ) {
						$participant_id = $this->resolve_ref( $participant_ref );
						if ( $participant_id ) {
							$resolved_participants[] = $participant_id;
						}
					}

					if ( ! empty( $resolved_participants ) ) {
						update_comment_meta( $comment_id, 'participants', $resolved_participants );
					}
					break;

				case 'rondo_email':
					if ( isset( $meta['email_template_type'] ) ) {
						update_comment_meta( $comment_id, 'email_template_type', $meta['email_template_type'] );
					}
					if ( isset( $meta['email_recipient'] ) ) {
						update_comment_meta( $comment_id, 'email_recipient', $meta['email_recipient'] );
					}
					if ( isset( $meta['email_subject'] ) ) {
						update_comment_meta( $comment_id, 'email_subject', $meta['email_subject'] );
					}
					if ( isset( $meta['email_content_snapshot'] ) ) {
						update_comment_meta( $comment_id, 'email_content_snapshot', $meta['email_content_snapshot'] );
					}
					break;
			}

			$counts[ $type ]++;
		}

		WP_CLI::log(
			sprintf(
				'  Imported %d comments (%d notes, %d activities, %d emails)',
				$total,
				$counts['rondo_note'],
				$counts['rondo_activity'],
				$counts['rondo_email']
			)
		);
	}

	/**
	 * Import settings (WordPress options)
	 */
	private function import_settings() {
		$settings = $this->fixture['settings'] ?? [];
		$count = 0;

		foreach ( $settings as $key => $value ) {
			// Special handling for VOG exempt commissies - resolve refs to post IDs
			if ( 'rondo_vog_exempt_commissies' === $key && is_array( $value ) ) {
				$resolved_ids = [];
				foreach ( $value as $commissie_ref ) {
					$commissie_id = $this->resolve_ref( $commissie_ref );
					if ( $commissie_id ) {
						$resolved_ids[] = $commissie_id;
					}
				}
				$value = $resolved_ids;
			}

			// Shift season in option keys (rondo_membership_fees_{season} and rondo_family_discount_{season})
			if ( preg_match( '/^(rondo_membership_fees|rondo_family_discount)_(.+)$/', $key, $matches ) ) {
				$prefix = $matches[1];
				$season = $matches[2];
				$shifted_season = $this->shift_season_slug( $season );
				$key = $prefix . '_' . $shifted_season;
			}

			update_option( $key, $value );
			$count++;
		}

		WP_CLI::log( sprintf( '  Imported %d settings', $count ) );
	}
}
