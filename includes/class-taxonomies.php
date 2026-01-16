<?php
/**
 * Custom Taxonomies Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Taxonomies {

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
		$this->register_company_label_taxonomy();
		$this->register_relationship_type_taxonomy();
		$this->register_date_type_taxonomy();
		$this->register_workspace_access_taxonomy();
	}

	/**
	 * Register Workspace Access Taxonomy
	 * Used to link people, companies, and important dates to workspaces.
	 * Terms are auto-created when workspaces are created (Phase 8).
	 */
	private function register_workspace_access_taxonomy() {
		$labels = [
			'name'          => _x( 'Workspace Access', 'taxonomy general name', 'caelis' ),
			'singular_name' => _x( 'Workspace Access', 'taxonomy singular name', 'caelis' ),
			'search_items'  => __( 'Search Workspace Access', 'caelis' ),
			'all_items'     => __( 'All Workspace Access', 'caelis' ),
			'edit_item'     => __( 'Edit Workspace Access', 'caelis' ),
			'update_item'   => __( 'Update Workspace Access', 'caelis' ),
			'add_new_item'  => __( 'Add New Workspace Access', 'caelis' ),
			'new_item_name' => __( 'New Workspace Access Name', 'caelis' ),
			'menu_name'     => __( 'Workspace Access', 'caelis' ),
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

		register_taxonomy( 'workspace_access', [ 'person', 'company', 'important_date' ], $args );
	}

	/**
	 * Register Person Label Taxonomy
	 */
	private function register_person_label_taxonomy() {
		$labels = [
			'name'          => _x( 'Labels', 'taxonomy general name', 'caelis' ),
			'singular_name' => _x( 'Label', 'taxonomy singular name', 'caelis' ),
			'search_items'  => __( 'Search Labels', 'caelis' ),
			'all_items'     => __( 'All Labels', 'caelis' ),
			'edit_item'     => __( 'Edit Label', 'caelis' ),
			'update_item'   => __( 'Update Label', 'caelis' ),
			'add_new_item'  => __( 'Add New Label', 'caelis' ),
			'new_item_name' => __( 'New Label Name', 'caelis' ),
			'menu_name'     => __( 'Labels', 'caelis' ),
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
	 * Register Company Label Taxonomy
	 */
	private function register_company_label_taxonomy() {
		$labels = [
			'name'          => _x( 'Company Labels', 'taxonomy general name', 'caelis' ),
			'singular_name' => _x( 'Company Label', 'taxonomy singular name', 'caelis' ),
			'search_items'  => __( 'Search Company Labels', 'caelis' ),
			'all_items'     => __( 'All Company Labels', 'caelis' ),
			'edit_item'     => __( 'Edit Company Label', 'caelis' ),
			'update_item'   => __( 'Update Company Label', 'caelis' ),
			'add_new_item'  => __( 'Add New Company Label', 'caelis' ),
			'new_item_name' => __( 'New Company Label Name', 'caelis' ),
			'menu_name'     => __( 'Labels', 'caelis' ),
		];

		$args = [
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'company-label' ],
		];

		register_taxonomy( 'company_label', [ 'company' ], $args );
	}

	/**
	 * Register Relationship Type Taxonomy
	 */
	private function register_relationship_type_taxonomy() {
		$labels = [
			'name'          => _x( 'Relationship Types', 'taxonomy general name', 'caelis' ),
			'singular_name' => _x( 'Relationship Type', 'taxonomy singular name', 'caelis' ),
			'search_items'  => __( 'Search Relationship Types', 'caelis' ),
			'all_items'     => __( 'All Relationship Types', 'caelis' ),
			'edit_item'     => __( 'Edit Relationship Type', 'caelis' ),
			'update_item'   => __( 'Update Relationship Type', 'caelis' ),
			'add_new_item'  => __( 'Add New Relationship Type', 'caelis' ),
			'new_item_name' => __( 'New Relationship Type Name', 'caelis' ),
			'menu_name'     => __( 'Relationship Types', 'caelis' ),
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
			'name'          => _x( 'Date Types', 'taxonomy general name', 'caelis' ),
			'singular_name' => _x( 'Date Type', 'taxonomy singular name', 'caelis' ),
			'search_items'  => __( 'Search Date Types', 'caelis' ),
			'all_items'     => __( 'All Date Types', 'caelis' ),
			'edit_item'     => __( 'Edit Date Type', 'caelis' ),
			'update_item'   => __( 'Update Date Type', 'caelis' ),
			'add_new_item'  => __( 'Add New Date Type', 'caelis' ),
			'new_item_name' => __( 'New Date Type Name', 'caelis' ),
			'menu_name'     => __( 'Date Types', 'caelis' ),
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
			'partner'      => __( 'Partner', 'caelis' ),
			'spouse'       => __( 'Spouse', 'caelis' ),
			'friend'       => __( 'Friend', 'caelis' ),
			'colleague'    => __( 'Colleague', 'caelis' ),
			'acquaintance' => __( 'Acquaintance', 'caelis' ),
			'ex'           => __( 'Ex', 'caelis' ),

			// Family - immediate
			'parent'       => __( 'Parent', 'caelis' ),
			'child'        => __( 'Child', 'caelis' ),
			'sibling'      => __( 'Sibling', 'caelis' ),

			// Family - extended
			'grandparent'  => __( 'Grandparent', 'caelis' ),
			'grandchild'   => __( 'Grandchild', 'caelis' ),
			'uncle'        => __( 'Uncle', 'caelis' ),
			'aunt'         => __( 'Aunt', 'caelis' ),
			'nephew'       => __( 'Nephew', 'caelis' ),
			'niece'        => __( 'Niece', 'caelis' ),
			'cousin'       => __( 'Cousin', 'caelis' ),

			// Family - step/in-law
			'stepparent'   => __( 'Stepparent', 'caelis' ),
			'stepchild'    => __( 'Stepchild', 'caelis' ),
			'stepsibling'  => __( 'Stepsibling', 'caelis' ),
			'inlaw'        => __( 'In-law', 'caelis' ),

			// Family - other
			'godparent'    => __( 'Godparent', 'caelis' ),
			'godchild'     => __( 'Godchild', 'caelis' ),

			// Professional
			'boss'         => __( 'Boss', 'caelis' ),
			'subordinate'  => __( 'Subordinate', 'caelis' ),
			'mentor'       => __( 'Mentor', 'caelis' ),
			'mentee'       => __( 'Mentee', 'caelis' ),
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
			'birthday'                => __( 'Birthday', 'caelis' ),
			'memorial'                => __( 'Memorial', 'caelis' ),
			'first-met'               => __( 'First Met', 'caelis' ),

			// Family & relationships (Monica category 2)
			'new-relationship'        => __( 'New Relationship', 'caelis' ),
			'engagement'              => __( 'Engagement', 'caelis' ),
			'wedding'                 => __( 'Wedding', 'caelis' ),
			'marriage'                => __( 'Marriage', 'caelis' ),
			'expecting-a-baby'        => __( 'Expecting a Baby', 'caelis' ),
			'new-child'               => __( 'New Child', 'caelis' ),
			'new-family-member'       => __( 'New Family Member', 'caelis' ),
			'new-pet'                 => __( 'New Pet', 'caelis' ),
			'end-of-relationship'     => __( 'End of Relationship', 'caelis' ),
			'loss-of-a-loved-one'     => __( 'Loss of a Loved One', 'caelis' ),

			// Work & education (Monica category 1)
			'new-job'                 => __( 'New Job', 'caelis' ),
			'retirement'              => __( 'Retirement', 'caelis' ),
			'new-school'              => __( 'New School', 'caelis' ),
			'study-abroad'            => __( 'Study Abroad', 'caelis' ),
			'volunteer-work'          => __( 'Volunteer Work', 'caelis' ),
			'published-book-or-paper' => __( 'Published Book or Paper', 'caelis' ),
			'military-service'        => __( 'Military Service', 'caelis' ),

			// Home & living (Monica category 3)
			'moved'                   => __( 'Moved', 'caelis' ),
			'bought-a-home'           => __( 'Bought a Home', 'caelis' ),
			'home-improvement'        => __( 'Home Improvement', 'caelis' ),
			'holidays'                => __( 'Holidays', 'caelis' ),
			'new-vehicle'             => __( 'New Vehicle', 'caelis' ),
			'new-roommate'            => __( 'New Roommate', 'caelis' ),

			// Health & wellness (Monica category 4)
			'overcame-an-illness'     => __( 'Overcame an Illness', 'caelis' ),
			'quit-a-habit'            => __( 'Quit a Habit', 'caelis' ),
			'new-eating-habits'       => __( 'New Eating Habits', 'caelis' ),
			'weight-loss'             => __( 'Weight Loss', 'caelis' ),
			'surgery'                 => __( 'Surgery', 'caelis' ),

			// Travel & experiences (Monica category 5)
			'new-sport'               => __( 'New Sport', 'caelis' ),
			'new-hobby'               => __( 'New Hobby', 'caelis' ),
			'new-instrument'          => __( 'New Instrument', 'caelis' ),
			'new-language'            => __( 'New Language', 'caelis' ),
			'travel'                  => __( 'Travel', 'caelis' ),
			'achievement-or-award'    => __( 'Achievement or Award', 'caelis' ),
			'first-word'              => __( 'First Word', 'caelis' ),
			'first-kiss'              => __( 'First Kiss', 'caelis' ),

			// Fallback
			'other'                   => __( 'Other', 'caelis' ),
		];

		foreach ( $defaults as $slug => $name ) {
			if ( ! term_exists( $slug, 'date_type' ) ) {
				wp_insert_term( $name, 'date_type', [ 'slug' => $slug ] );
			}
		}
	}
}
