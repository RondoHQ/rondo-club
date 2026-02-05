<?php
/**
 * Custom Taxonomies Registration
 */

namespace Stadion\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Taxonomies {

	public function __construct() {
		add_action( 'init', [ $this, 'register_taxonomies' ] );

		// Add unique validation for discipline case dossier_id
		add_filter( 'acf/validate_value/name=dossier_id', [ $this, 'validate_unique_dossier_id' ], 10, 4 );
	}

	/**
	 * Register all custom taxonomies
	 */
	public function register_taxonomies() {
		$this->register_person_label_taxonomy();
		$this->register_team_label_taxonomy();
		$this->register_commissie_label_taxonomy();
		$this->register_relationship_type_taxonomy();
		$this->register_date_type_taxonomy();
		$this->register_seizoen_taxonomy();
	}

	/**
	 * Register Person Label Taxonomy
	 */
	private function register_person_label_taxonomy() {
		$labels = [
			'name'          => _x( 'Labels', 'taxonomy general name', 'stadion' ),
			'singular_name' => _x( 'Label', 'taxonomy singular name', 'stadion' ),
			'search_items'  => __( 'Search Labels', 'stadion' ),
			'all_items'     => __( 'All Labels', 'stadion' ),
			'edit_item'     => __( 'Edit Label', 'stadion' ),
			'update_item'   => __( 'Update Label', 'stadion' ),
			'add_new_item'  => __( 'Add New Label', 'stadion' ),
			'new_item_name' => __( 'New Label Name', 'stadion' ),
			'menu_name'     => __( 'Labels', 'stadion' ),
		];

		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'person-label' ],
		];

		register_taxonomy( 'person_label', [ 'person' ], $args );
	}

	/**
	 * Register Team Label Taxonomy
	 */
	private function register_team_label_taxonomy() {
		$labels = [
			'name'          => _x( 'Team Labels', 'taxonomy general name', 'stadion' ),
			'singular_name' => _x( 'Team Label', 'taxonomy singular name', 'stadion' ),
			'search_items'  => __( 'Search Team Labels', 'stadion' ),
			'all_items'     => __( 'All Team Labels', 'stadion' ),
			'edit_item'     => __( 'Edit Team Label', 'stadion' ),
			'update_item'   => __( 'Update Team Label', 'stadion' ),
			'add_new_item'  => __( 'Add New Team Label', 'stadion' ),
			'new_item_name' => __( 'New Team Label Name', 'stadion' ),
			'menu_name'     => __( 'Labels', 'stadion' ),
		];

		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'team-label' ],
		];

		register_taxonomy( 'team_label', [ 'team' ], $args );
	}

	/**
	 * Register Commissie Label Taxonomy
	 */
	private function register_commissie_label_taxonomy() {
		$labels = [
			'name'          => _x( 'Commissie Labels', 'taxonomy general name', 'stadion' ),
			'singular_name' => _x( 'Commissie Label', 'taxonomy singular name', 'stadion' ),
			'search_items'  => __( 'Search Commissie Labels', 'stadion' ),
			'all_items'     => __( 'All Commissie Labels', 'stadion' ),
			'edit_item'     => __( 'Edit Commissie Label', 'stadion' ),
			'update_item'   => __( 'Update Commissie Label', 'stadion' ),
			'add_new_item'  => __( 'Add New Commissie Label', 'stadion' ),
			'new_item_name' => __( 'New Commissie Label Name', 'stadion' ),
			'menu_name'     => __( 'Labels', 'stadion' ),
		];

		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'commissie-label' ],
		];

		register_taxonomy( 'commissie_label', [ 'commissie' ], $args );
	}

	/**
	 * Register Relationship Type Taxonomy
	 */
	private function register_relationship_type_taxonomy() {
		$labels = [
			'name'          => _x( 'Relationship Types', 'taxonomy general name', 'stadion' ),
			'singular_name' => _x( 'Relationship Type', 'taxonomy singular name', 'stadion' ),
			'search_items'  => __( 'Search Relationship Types', 'stadion' ),
			'all_items'     => __( 'All Relationship Types', 'stadion' ),
			'edit_item'     => __( 'Edit Relationship Type', 'stadion' ),
			'update_item'   => __( 'Update Relationship Type', 'stadion' ),
			'add_new_item'  => __( 'Add New Relationship Type', 'stadion' ),
			'new_item_name' => __( 'New Relationship Type Name', 'stadion' ),
			'menu_name'     => __( 'Relationship Types', 'stadion' ),
		];

		$args = [
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'relationship-type' ],
		];

		register_taxonomy( 'relationship_type', [ 'person' ], $args );

		// Add default terms on activation
		$this->add_default_relationship_types();
	}

	/**
	 * Register Date Type Taxonomy
	 */
	private function register_date_type_taxonomy() {
		$labels = [
			'name'          => _x( 'Date Types', 'taxonomy general name', 'stadion' ),
			'singular_name' => _x( 'Date Type', 'taxonomy singular name', 'stadion' ),
			'search_items'  => __( 'Search Date Types', 'stadion' ),
			'all_items'     => __( 'All Date Types', 'stadion' ),
			'edit_item'     => __( 'Edit Date Type', 'stadion' ),
			'update_item'   => __( 'Update Date Type', 'stadion' ),
			'add_new_item'  => __( 'Add New Date Type', 'stadion' ),
			'new_item_name' => __( 'New Date Type Name', 'stadion' ),
			'menu_name'     => __( 'Date Types', 'stadion' ),
		];

		$args = [
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'date-type' ],
		];

		register_taxonomy( 'date_type', [ 'important_date' ], $args );

		// Add default terms on activation
		$this->add_default_date_types();
	}

	/**
	 * Add default relationship types
	 */
	private function add_default_relationship_types() {
		$defaults = [
			// Basic relationships
			'partner'      => __( 'Partner', 'stadion' ),
			'spouse'       => __( 'Spouse', 'stadion' ),
			'friend'       => __( 'Friend', 'stadion' ),
			'colleague'    => __( 'Colleague', 'stadion' ),
			'acquaintance' => __( 'Acquaintance', 'stadion' ),
			'ex'           => __( 'Ex', 'stadion' ),

			// Family - immediate
			'parent'       => __( 'Parent', 'stadion' ),
			'child'        => __( 'Child', 'stadion' ),
			'sibling'      => __( 'Sibling', 'stadion' ),

			// Family - extended
			'grandparent'  => __( 'Grandparent', 'stadion' ),
			'grandchild'   => __( 'Grandchild', 'stadion' ),
			'uncle'        => __( 'Uncle', 'stadion' ),
			'aunt'         => __( 'Aunt', 'stadion' ),
			'nephew'       => __( 'Nephew', 'stadion' ),
			'niece'        => __( 'Niece', 'stadion' ),
			'cousin'       => __( 'Cousin', 'stadion' ),

			// Family - step/in-law
			'stepparent'   => __( 'Stepparent', 'stadion' ),
			'stepchild'    => __( 'Stepchild', 'stadion' ),
			'stepsibling'  => __( 'Stepsibling', 'stadion' ),
			'inlaw'        => __( 'In-law', 'stadion' ),

			// Family - other
			'godparent'    => __( 'Godparent', 'stadion' ),
			'godchild'     => __( 'Godchild', 'stadion' ),

			// Professional
			'boss'         => __( 'Boss', 'stadion' ),
			'subordinate'  => __( 'Subordinate', 'stadion' ),
			'mentor'       => __( 'Mentor', 'stadion' ),
			'mentee'       => __( 'Mentee', 'stadion' ),
		];

		foreach ( $defaults as $slug => $name ) {
			if ( ! term_exists( $slug, 'relationship_type' ) ) {
				wp_insert_term( $name, 'relationship_type', [ 'slug' => $slug ] );
			}
		}

		// Set up default configurations (inverse mappings and gender-dependent settings)
		$this->setup_default_relationship_configurations();
	}

	/**
	 * Set up default relationship type configurations
	 * Includes inverse mappings and gender-dependent settings
	 */
	public function setup_default_relationship_configurations() {
		// ACF must be loaded for get_field/update_field to work
		// During theme activation, ACF may not be ready yet
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}

		// Get all relationship type terms by slug
		$types     = [];
		$all_terms = get_terms(
			[
				'taxonomy'   => 'relationship_type',
				'hide_empty' => false,
			]
		);

		if ( ! is_wp_error( $all_terms ) ) {
			foreach ( $all_terms as $term ) {
				$types[ $term->slug ] = $term->term_id;
			}
		}

		// Symmetric relationships (same type as inverse)
		$symmetric = [ 'spouse', 'friend', 'colleague', 'acquaintance', 'sibling', 'cousin', 'partner' ];
		foreach ( $symmetric as $slug ) {
			if ( isset( $types[ $slug ] ) ) {
				$inverse = get_field( 'inverse_relationship_type', 'relationship_type_' . $types[ $slug ] );
				if ( ! $inverse ) {
					update_field( 'inverse_relationship_type', $types[ $slug ], 'relationship_type_' . $types[ $slug ] );
				}
			}
		}

		// Asymmetric parent-child relationships
		$asymmetric = [
			'parent'      => 'child',
			'child'       => 'parent',
			'grandparent' => 'grandchild',
			'grandchild'  => 'grandparent',
			'stepparent'  => 'stepchild',
			'stepchild'   => 'stepparent',
			'godparent'   => 'godchild',
			'godchild'    => 'godparent',
			'boss'        => 'subordinate',
			'subordinate' => 'boss',
			'mentor'      => 'mentee',
			'mentee'      => 'mentor',
		];

		foreach ( $asymmetric as $from_slug => $to_slug ) {
			if ( isset( $types[ $from_slug ] ) && isset( $types[ $to_slug ] ) ) {
				$inverse = get_field( 'inverse_relationship_type', 'relationship_type_' . $types[ $from_slug ] );
				if ( ! $inverse ) {
					update_field( 'inverse_relationship_type', $types[ $to_slug ], 'relationship_type_' . $types[ $from_slug ] );
				}
			}
		}

		// Gender-dependent relationships
		// Aunt/Uncle group - these map to Niece/Nephew based on the related person's gender
		$aunt_uncle_group = [ 'aunt', 'uncle' ];
		foreach ( $aunt_uncle_group as $slug ) {
			if ( isset( $types[ $slug ] ) ) {
				$term_id             = $types[ $slug ];
				$is_gender_dependent = get_field( 'is_gender_dependent', 'relationship_type_' . $term_id );
				if ( ! $is_gender_dependent ) {
					update_field( 'is_gender_dependent', true, 'relationship_type_' . $term_id );
					update_field( 'gender_dependent_group', 'aunt_uncle', 'relationship_type_' . $term_id );
				}

				// Set inverse to any type in niece_nephew group (will be resolved based on related person's gender)
				// Use niece as the default mapping, but resolution will pick niece or nephew based on gender
				if ( isset( $types['niece'] ) ) {
					$inverse = get_field( 'inverse_relationship_type', 'relationship_type_' . $term_id );
					if ( ! $inverse ) {
						// Map to niece_nephew group - will resolve to niece (if related person is female) or nephew (if male)
						update_field( 'inverse_relationship_type', $types['niece'], 'relationship_type_' . $term_id );
					}
				}
			}
		}

		// Niece/Nephew group - these map to Aunt/Uncle based on the related person's gender
		$niece_nephew_group = [ 'niece', 'nephew' ];
		foreach ( $niece_nephew_group as $slug ) {
			if ( isset( $types[ $slug ] ) ) {
				$term_id             = $types[ $slug ];
				$is_gender_dependent = get_field( 'is_gender_dependent', 'relationship_type_' . $term_id );
				if ( ! $is_gender_dependent ) {
					update_field( 'is_gender_dependent', true, 'relationship_type_' . $term_id );
					update_field( 'gender_dependent_group', 'niece_nephew', 'relationship_type_' . $term_id );
				}

				// Set inverse to any type in aunt_uncle group (will be resolved based on related person's gender)
				// Use aunt as the default mapping, but resolution will pick aunt or uncle based on gender
				if ( isset( $types['aunt'] ) ) {
					$inverse = get_field( 'inverse_relationship_type', 'relationship_type_' . $term_id );
					if ( ! $inverse ) {
						// Map to aunt_uncle group - will resolve to aunt (if related person is female) or uncle (if male)
						update_field( 'inverse_relationship_type', $types['aunt'], 'relationship_type_' . $term_id );
					}
				}
			}
		}
	}

	/**
	 * Add default date types
	 */
	private function add_default_date_types() {
		$defaults = [
			// Core types
			'birthday'                => __( 'Birthday', 'stadion' ),
			'memorial'                => __( 'Memorial', 'stadion' ),
			'first-met'               => __( 'First Met', 'stadion' ),

		// Family & relationships
			'new-relationship'        => __( 'New Relationship', 'stadion' ),
			'engagement'              => __( 'Engagement', 'stadion' ),
			'wedding'                 => __( 'Wedding', 'stadion' ),
			'marriage'                => __( 'Marriage', 'stadion' ),
			'expecting-a-baby'        => __( 'Expecting a Baby', 'stadion' ),
			'new-child'               => __( 'New Child', 'stadion' ),
			'new-family-member'       => __( 'New Family Member', 'stadion' ),
			'new-pet'                 => __( 'New Pet', 'stadion' ),
			'end-of-relationship'     => __( 'End of Relationship', 'stadion' ),
			'loss-of-a-loved-one'     => __( 'Loss of a Loved One', 'stadion' ),

		// Work & education
			'new-job'                 => __( 'New Job', 'stadion' ),
			'retirement'              => __( 'Retirement', 'stadion' ),
			'new-school'              => __( 'New School', 'stadion' ),
			'study-abroad'            => __( 'Study Abroad', 'stadion' ),
			'volunteer-work'          => __( 'Volunteer Work', 'stadion' ),
			'published-book-or-paper' => __( 'Published Book or Paper', 'stadion' ),
			'military-service'        => __( 'Military Service', 'stadion' ),

		// Home & living
			'moved'                   => __( 'Moved', 'stadion' ),
			'bought-a-home'           => __( 'Bought a Home', 'stadion' ),
			'home-improvement'        => __( 'Home Improvement', 'stadion' ),
			'holidays'                => __( 'Holidays', 'stadion' ),
			'new-vehicle'             => __( 'New Vehicle', 'stadion' ),
			'new-roommate'            => __( 'New Roommate', 'stadion' ),

		// Health & wellness
			'overcame-an-illness'     => __( 'Overcame an Illness', 'stadion' ),
			'quit-a-habit'            => __( 'Quit a Habit', 'stadion' ),
			'new-eating-habits'       => __( 'New Eating Habits', 'stadion' ),
			'weight-loss'             => __( 'Weight Loss', 'stadion' ),
			'surgery'                 => __( 'Surgery', 'stadion' ),

		// Travel & experiences
			'new-sport'               => __( 'New Sport', 'stadion' ),
			'new-hobby'               => __( 'New Hobby', 'stadion' ),
			'new-instrument'          => __( 'New Instrument', 'stadion' ),
			'new-language'            => __( 'New Language', 'stadion' ),
			'travel'                  => __( 'Travel', 'stadion' ),
			'achievement-or-award'    => __( 'Achievement or Award', 'stadion' ),
			'first-word'              => __( 'First Word', 'stadion' ),
			'first-kiss'              => __( 'First Kiss', 'stadion' ),

			// Fallback
			'other'                   => __( 'Other', 'stadion' ),
		];

		foreach ( $defaults as $slug => $name ) {
			if ( ! term_exists( $slug, 'date_type' ) ) {
				wp_insert_term( $name, 'date_type', [ 'slug' => $slug ] );
			}
		}
	}

	/**
	 * Register Seizoen (Season) Taxonomy
	 *
	 * Used for categorizing discipline cases and potentially other post types by sports season.
	 * Format: Full years (e.g., 2024-2025)
	 */
	private function register_seizoen_taxonomy() {
		$labels = [
			'name'          => _x( 'Seizoenen', 'taxonomy general name', 'stadion' ),
			'singular_name' => _x( 'Seizoen', 'taxonomy singular name', 'stadion' ),
			'search_items'  => __( 'Search Seizoenen', 'stadion' ),
			'all_items'     => __( 'All Seizoenen', 'stadion' ),
			'edit_item'     => __( 'Edit Seizoen', 'stadion' ),
			'update_item'   => __( 'Update Seizoen', 'stadion' ),
			'add_new_item'  => __( 'Add New Seizoen', 'stadion' ),
			'new_item_name' => __( 'New Seizoen Name', 'stadion' ),
			'menu_name'     => __( 'Seizoenen', 'stadion' ),
		];

		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'seizoen' ],
		];

		register_taxonomy( 'seizoen', [ 'discipline_case' ], $args );
	}

	/**
	 * Set a season as the current season
	 * Clears any previous current season flag
	 *
	 * @param string $season_slug The season slug (e.g., '2024-2025')
	 * @return bool True on success, false on failure
	 */
	public function set_current_season( $season_slug ) {
		// Clear previous current season
		$previous_current = get_terms(
			[
				'taxonomy'   => 'seizoen',
				'hide_empty' => false,
				'meta_query' => [
					[
						'key'   => 'is_current_season',
						'value' => '1',
					],
				],
			]
		);

		if ( ! is_wp_error( $previous_current ) ) {
			foreach ( $previous_current as $term ) {
				delete_term_meta( $term->term_id, 'is_current_season' );
			}
		}

		// Set new current season
		$term = get_term_by( 'slug', $season_slug, 'seizoen' );
		if ( $term && ! is_wp_error( $term ) ) {
			update_term_meta( $term->term_id, 'is_current_season', '1' );
			return true;
		}

		return false;
	}

	/**
	 * Get the current season term
	 *
	 * @return WP_Term|null The current season term or null if not set
	 */
	public function get_current_season() {
		$terms = get_terms(
			[
				'taxonomy'   => 'seizoen',
				'hide_empty' => false,
				'meta_query' => [
					[
						'key'   => 'is_current_season',
						'value' => '1',
					],
				],
			]
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			return $terms[0];
		}

		return null;
	}

	/**
	 * Validate that dossier_id is unique across discipline cases
	 *
	 * @param mixed  $valid      Whether the value is valid (true) or not (string error message)
	 * @param mixed  $value      The field value
	 * @param array  $field      The field array
	 * @param string $input_name The input name/key
	 * @return mixed True if valid, error message string if invalid
	 */
	public function validate_unique_dossier_id( $valid, $value, $field, $input_name ) {
		// Skip if already invalid or empty
		if ( ! $valid || empty( $value ) ) {
			return $valid;
		}

		// Get current post ID from various sources
		$post_id = 0;
		if ( isset( $_POST['post_ID'] ) ) {
			$post_id = intval( $_POST['post_ID'] );
		} elseif ( isset( $_POST['post_id'] ) ) {
			$post_id = intval( $_POST['post_id'] );
		}

		// Query for existing discipline cases with this dossier_id
		$existing = get_posts(
			[
				'post_type'      => 'discipline_case',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post__not_in'   => $post_id ? [ $post_id ] : [],
				'meta_query'     => [
					[
						'key'   => 'dossier_id',
						'value' => $value,
					],
				],
			]
		);

		if ( ! empty( $existing ) ) {
			return __( 'Dit dossier-ID bestaat al. Elk dossier moet een uniek ID hebben.', 'stadion' );
		}

		return $valid;
	}
}
