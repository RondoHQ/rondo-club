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
	 * Constructor
	 *
	 * @param string $fixture_path Path to read the JSON fixture file.
	 */
	public function __construct( $fixture_path ) {
		$this->fixture_path = $fixture_path;
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
			$name = $seizoen['name'];
			$slug = $seizoen['slug'];
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
			update_field( 'birthdate', $acf['birthdate'] ?? '', $post_id );
			update_field( 'former_member', $acf['former_member'] ?? false, $post_id );
			update_field( 'lid-tot', $acf['lid-tot'] ?? null, $post_id );
			update_field( 'datum-overlijden', $acf['datum-overlijden'] ?? null, $post_id );

			// Contact Information
			update_field( 'contact_info', $acf['contact_info'] ?? [], $post_id );

			// Addresses
			update_field( 'addresses', $acf['addresses'] ?? [], $post_id );

			// Sportlink-Synced Fields
			update_field( 'lid-sinds', $acf['lid-sinds'] ?? null, $post_id );
			update_field( 'leeftijdsgroep', $acf['leeftijdsgroep'] ?? null, $post_id );
			update_field( 'datum-vog', $acf['datum-vog'] ?? null, $post_id );
			update_field( 'datum-foto', $acf['datum-foto'] ?? null, $post_id );
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
					'job_title'   => $row['job_title'] ?? '',
					'description' => $row['description'] ?? null,
					'start_date'  => $row['start_date'] ?? null,
					'end_date'    => $row['end_date'] ?? null,
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
					'job_title'   => $row['job_title'] ?? '',
					'description' => $row['description'] ?? null,
					'start_date'  => $row['start_date'] ?? null,
					'end_date'    => $row['end_date'] ?? null,
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
			update_field( 'match_date', $acf['match_date'] ?? null, $post_id );
			update_field( 'processing_date', $acf['processing_date'] ?? null, $post_id );
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

			// Set seizoen taxonomy
			$seizoen_slug = $case['seizoen'] ?? null;
			if ( $seizoen_slug ) {
				wp_set_object_terms( $post_id, $seizoen_slug, 'seizoen' );
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
			$date    = $todo['date'];

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
			update_field( 'awaiting_since', $acf['awaiting_since'] ?? null, $post_id );
			update_field( 'due_date', $acf['due_date'] ?? null, $post_id );
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
			$date       = $comment['date'];
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
						update_comment_meta( $comment_id, 'activity_date', $meta['activity_date'] );
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

			update_option( $key, $value );
			$count++;
		}

		WP_CLI::log( sprintf( '  Imported %d settings', $count ) );
	}
}
