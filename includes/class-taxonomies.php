<?php
/**
 * Custom Taxonomies Registration
 */

namespace Rondo\Core;

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
		$this->register_commissie_label_taxonomy();
		$this->register_relationship_type_taxonomy();
		$this->register_seizoen_taxonomy();
	}

	/**
	 * Register Commissie Label Taxonomy
	 */
	private function register_commissie_label_taxonomy() {
		$labels = [
			'name'          => _x( 'Commissie Labels', 'taxonomy general name', 'rondo' ),
			'singular_name' => _x( 'Commissie Label', 'taxonomy singular name', 'rondo' ),
			'search_items'  => __( 'Search Commissie Labels', 'rondo' ),
			'all_items'     => __( 'All Commissie Labels', 'rondo' ),
			'edit_item'     => __( 'Edit Commissie Label', 'rondo' ),
			'update_item'   => __( 'Update Commissie Label', 'rondo' ),
			'add_new_item'  => __( 'Add New Commissie Label', 'rondo' ),
			'new_item_name' => __( 'New Commissie Label Name', 'rondo' ),
			'menu_name'     => __( 'Labels', 'rondo' ),
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
			'name'          => _x( 'Relationship Types', 'taxonomy general name', 'rondo' ),
			'singular_name' => _x( 'Relationship Type', 'taxonomy singular name', 'rondo' ),
			'search_items'  => __( 'Search Relationship Types', 'rondo' ),
			'all_items'     => __( 'All Relationship Types', 'rondo' ),
			'edit_item'     => __( 'Edit Relationship Type', 'rondo' ),
			'update_item'   => __( 'Update Relationship Type', 'rondo' ),
			'add_new_item'  => __( 'Add New Relationship Type', 'rondo' ),
			'new_item_name' => __( 'New Relationship Type Name', 'rondo' ),
			'menu_name'     => __( 'Relationship Types', 'rondo' ),
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
	 * Add default relationship types
	 */
	private function add_default_relationship_types() {
		$defaults = [
			'parent'  => __( 'Parent', 'rondo' ),
			'child'   => __( 'Child', 'rondo' ),
			'sibling' => __( 'Sibling', 'rondo' ),
		];

		foreach ( $defaults as $slug => $name ) {
			if ( ! term_exists( $slug, 'relationship_type' ) ) {
				wp_insert_term( $name, 'relationship_type', [ 'slug' => $slug ] );
			}
		}

		// Set up default configurations (inverse mappings)
		$this->setup_default_relationship_configurations();
	}

	/**
	 * Set up default relationship type configurations
	 * Includes inverse mappings for symmetric and asymmetric types
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
		$symmetric = [ 'sibling' ];
		foreach ( $symmetric as $slug ) {
			if ( isset( $types[ $slug ] ) ) {
				$inverse = get_field( 'inverse_relationship_type', 'relationship_type_' . $types[ $slug ] );
				if ( ! $inverse ) {
					update_field( 'inverse_relationship_type', $types[ $slug ], 'relationship_type_' . $types[ $slug ] );
				}
			}
		}

		// Asymmetric relationships
		$asymmetric = [
			'parent' => 'child',
			'child'  => 'parent',
		];

		foreach ( $asymmetric as $from_slug => $to_slug ) {
			if ( isset( $types[ $from_slug ] ) && isset( $types[ $to_slug ] ) ) {
				$inverse = get_field( 'inverse_relationship_type', 'relationship_type_' . $types[ $from_slug ] );
				if ( ! $inverse ) {
					update_field( 'inverse_relationship_type', $types[ $to_slug ], 'relationship_type_' . $types[ $from_slug ] );
				}
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
			'name'          => _x( 'Seizoenen', 'taxonomy general name', 'rondo' ),
			'singular_name' => _x( 'Seizoen', 'taxonomy singular name', 'rondo' ),
			'search_items'  => __( 'Search Seizoenen', 'rondo' ),
			'all_items'     => __( 'All Seizoenen', 'rondo' ),
			'edit_item'     => __( 'Edit Seizoen', 'rondo' ),
			'update_item'   => __( 'Update Seizoen', 'rondo' ),
			'add_new_item'  => __( 'Add New Seizoen', 'rondo' ),
			'new_item_name' => __( 'New Seizoen Name', 'rondo' ),
			'menu_name'     => __( 'Seizoenen', 'rondo' ),
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
			return __( 'Dit dossier-ID bestaat al. Elk dossier moet een uniek ID hebben.', 'rondo' );
		}

		return $valid;
	}
}
