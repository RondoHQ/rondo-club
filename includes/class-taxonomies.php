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

		// Workspace term sync hooks
		add_action( 'save_post_workspace', [ $this, 'ensure_workspace_term' ], 10, 3 );
		add_action( 'before_delete_post', [ $this, 'cleanup_workspace_term' ] );
	}

	/**
	 * Ensure workspace_access term exists for a workspace
	 *
	 * Creates or updates a term when a workspace is created/published.
	 * Term slug: 'workspace-{ID}', Term name: workspace title
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	public function ensure_workspace_term( $post_id, $post, $update ) {
		// Only for published workspaces
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		// Don't run on autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$term_slug = 'workspace-' . $post_id;
		$term_name = $post->post_title;

		// Check if term exists
		$existing_term = term_exists( $term_slug, 'workspace_access' );

		if ( $existing_term ) {
			// Term exists - update name if it changed
			wp_update_term(
				$existing_term['term_id'],
				'workspace_access',
				[
					'name' => $term_name,
				]
			);
		} else {
			// Create new term
			wp_insert_term(
				$term_name,
				'workspace_access',
				[
					'slug' => $term_slug,
				]
			);
		}
	}

	/**
	 * Clean up workspace_access term when workspace is permanently deleted
	 *
	 * Removes the workspace-{ID} term when a workspace is deleted.
	 * This also automatically removes term relationships from contacts.
	 *
	 * @param int $post_id Post ID.
	 */
	public function cleanup_workspace_term( $post_id ) {
		$post = get_post( $post_id );

		// Only for workspace post type
		if ( ! $post || $post->post_type !== 'workspace' ) {
			return;
		}

		$term_slug = 'workspace-' . $post_id;
		$term      = get_term_by( 'slug', $term_slug, 'workspace_access' );

		if ( $term && ! is_wp_error( $term ) ) {
			wp_delete_term( $term->term_id, 'workspace_access' );
		}
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
		$this->register_workspace_access_taxonomy();
	}

	/**
	 * Register Workspace Access Taxonomy
	 * Used to link people, teams, commissies, and important dates to workspaces.
	 * Terms are auto-created when workspaces are created (Phase 8).
	 */
	private function register_workspace_access_taxonomy() {
		$labels = [
			'name'          => _x( 'Workspace Access', 'taxonomy general name', 'stadion' ),
			'singular_name' => _x( 'Workspace Access', 'taxonomy singular name', 'stadion' ),
			'search_items'  => __( 'Search Workspace Access', 'stadion' ),
			'all_items'     => __( 'All Workspace Access', 'stadion' ),
			'edit_item'     => __( 'Edit Workspace Access', 'stadion' ),
			'update_item'   => __( 'Update Workspace Access', 'stadion' ),
			'add_new_item'  => __( 'Add New Workspace Access', 'stadion' ),
			'new_item_name' => __( 'New Workspace Access Name', 'stadion' ),
			'menu_name'     => __( 'Workspace Access', 'stadion' ),
		];

		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false, // We'll control display in React
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => false, // No frontend permalinks needed
		];

		register_taxonomy( 'workspace_access', [ 'person', 'team', 'commissie', 'important_date' ], $args );
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
	 * Add default date types - aligned with Monica CRM life event types
	 */
	private function add_default_date_types() {
		$defaults = [
			// Core types
			'birthday'                => __( 'Birthday', 'stadion' ),
			'memorial'                => __( 'Memorial', 'stadion' ),
			'first-met'               => __( 'First Met', 'stadion' ),

			// Family & relationships (Monica category 2)
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

			// Work & education (Monica category 1)
			'new-job'                 => __( 'New Job', 'stadion' ),
			'retirement'              => __( 'Retirement', 'stadion' ),
			'new-school'              => __( 'New School', 'stadion' ),
			'study-abroad'            => __( 'Study Abroad', 'stadion' ),
			'volunteer-work'          => __( 'Volunteer Work', 'stadion' ),
			'published-book-or-paper' => __( 'Published Book or Paper', 'stadion' ),
			'military-service'        => __( 'Military Service', 'stadion' ),

			// Home & living (Monica category 3)
			'moved'                   => __( 'Moved', 'stadion' ),
			'bought-a-home'           => __( 'Bought a Home', 'stadion' ),
			'home-improvement'        => __( 'Home Improvement', 'stadion' ),
			'holidays'                => __( 'Holidays', 'stadion' ),
			'new-vehicle'             => __( 'New Vehicle', 'stadion' ),
			'new-roommate'            => __( 'New Roommate', 'stadion' ),

			// Health & wellness (Monica category 4)
			'overcame-an-illness'     => __( 'Overcame an Illness', 'stadion' ),
			'quit-a-habit'            => __( 'Quit a Habit', 'stadion' ),
			'new-eating-habits'       => __( 'New Eating Habits', 'stadion' ),
			'weight-loss'             => __( 'Weight Loss', 'stadion' ),
			'surgery'                 => __( 'Surgery', 'stadion' ),

			// Travel & experiences (Monica category 5)
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
}
