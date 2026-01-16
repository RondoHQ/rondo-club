<?php
/**
 * Custom Taxonomies Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Taxonomies {

	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		// Workspace term sync hooks
		add_action( 'save_post_workspace', array( $this, 'ensure_workspace_term' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'cleanup_workspace_term' ) );
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
				array(
					'name' => $term_name,
				)
			);
		} else {
			// Create new term
			wp_insert_term(
				$term_name,
				'workspace_access',
				array(
					'slug' => $term_slug,
				)
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
		$labels = array(
			'name'          => _x( 'Workspace Access', 'taxonomy general name', 'personal-crm' ),
			'singular_name' => _x( 'Workspace Access', 'taxonomy singular name', 'personal-crm' ),
			'search_items'  => __( 'Search Workspace Access', 'personal-crm' ),
			'all_items'     => __( 'All Workspace Access', 'personal-crm' ),
			'edit_item'     => __( 'Edit Workspace Access', 'personal-crm' ),
			'update_item'   => __( 'Update Workspace Access', 'personal-crm' ),
			'add_new_item'  => __( 'Add New Workspace Access', 'personal-crm' ),
			'new_item_name' => __( 'New Workspace Access Name', 'personal-crm' ),
			'menu_name'     => __( 'Workspace Access', 'personal-crm' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false, // We'll control display in React
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => false, // No frontend permalinks needed
		);

		register_taxonomy( 'workspace_access', array( 'person', 'company', 'important_date' ), $args );
	}

	/**
	 * Register Person Label Taxonomy
	 */
	private function register_person_label_taxonomy() {
		$labels = array(
			'name'          => _x( 'Labels', 'taxonomy general name', 'personal-crm' ),
			'singular_name' => _x( 'Label', 'taxonomy singular name', 'personal-crm' ),
			'search_items'  => __( 'Search Labels', 'personal-crm' ),
			'all_items'     => __( 'All Labels', 'personal-crm' ),
			'edit_item'     => __( 'Edit Label', 'personal-crm' ),
			'update_item'   => __( 'Update Label', 'personal-crm' ),
			'add_new_item'  => __( 'Add New Label', 'personal-crm' ),
			'new_item_name' => __( 'New Label Name', 'personal-crm' ),
			'menu_name'     => __( 'Labels', 'personal-crm' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'person-label' ),
		);

		register_taxonomy( 'person_label', array( 'person' ), $args );
	}

	/**
	 * Register Company Label Taxonomy
	 */
	private function register_company_label_taxonomy() {
		$labels = array(
			'name'          => _x( 'Company Labels', 'taxonomy general name', 'personal-crm' ),
			'singular_name' => _x( 'Company Label', 'taxonomy singular name', 'personal-crm' ),
			'search_items'  => __( 'Search Company Labels', 'personal-crm' ),
			'all_items'     => __( 'All Company Labels', 'personal-crm' ),
			'edit_item'     => __( 'Edit Company Label', 'personal-crm' ),
			'update_item'   => __( 'Update Company Label', 'personal-crm' ),
			'add_new_item'  => __( 'Add New Company Label', 'personal-crm' ),
			'new_item_name' => __( 'New Company Label Name', 'personal-crm' ),
			'menu_name'     => __( 'Labels', 'personal-crm' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'company-label' ),
		);

		register_taxonomy( 'company_label', array( 'company' ), $args );
	}

	/**
	 * Register Relationship Type Taxonomy
	 */
	private function register_relationship_type_taxonomy() {
		$labels = array(
			'name'          => _x( 'Relationship Types', 'taxonomy general name', 'personal-crm' ),
			'singular_name' => _x( 'Relationship Type', 'taxonomy singular name', 'personal-crm' ),
			'search_items'  => __( 'Search Relationship Types', 'personal-crm' ),
			'all_items'     => __( 'All Relationship Types', 'personal-crm' ),
			'edit_item'     => __( 'Edit Relationship Type', 'personal-crm' ),
			'update_item'   => __( 'Update Relationship Type', 'personal-crm' ),
			'add_new_item'  => __( 'Add New Relationship Type', 'personal-crm' ),
			'new_item_name' => __( 'New Relationship Type Name', 'personal-crm' ),
			'menu_name'     => __( 'Relationship Types', 'personal-crm' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'relationship-type' ),
		);

		register_taxonomy( 'relationship_type', array( 'person' ), $args );

		// Add default terms on activation
		$this->add_default_relationship_types();
	}

	/**
	 * Register Date Type Taxonomy
	 */
	private function register_date_type_taxonomy() {
		$labels = array(
			'name'          => _x( 'Date Types', 'taxonomy general name', 'personal-crm' ),
			'singular_name' => _x( 'Date Type', 'taxonomy singular name', 'personal-crm' ),
			'search_items'  => __( 'Search Date Types', 'personal-crm' ),
			'all_items'     => __( 'All Date Types', 'personal-crm' ),
			'edit_item'     => __( 'Edit Date Type', 'personal-crm' ),
			'update_item'   => __( 'Update Date Type', 'personal-crm' ),
			'add_new_item'  => __( 'Add New Date Type', 'personal-crm' ),
			'new_item_name' => __( 'New Date Type Name', 'personal-crm' ),
			'menu_name'     => __( 'Date Types', 'personal-crm' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'date-type' ),
		);

		register_taxonomy( 'date_type', array( 'important_date' ), $args );

		// Add default terms on activation
		$this->add_default_date_types();
	}

	/**
	 * Add default relationship types
	 */
	private function add_default_relationship_types() {
		$defaults = array(
			// Basic relationships
			'partner'      => __( 'Partner', 'personal-crm' ),
			'spouse'       => __( 'Spouse', 'personal-crm' ),
			'friend'       => __( 'Friend', 'personal-crm' ),
			'colleague'    => __( 'Colleague', 'personal-crm' ),
			'acquaintance' => __( 'Acquaintance', 'personal-crm' ),
			'ex'           => __( 'Ex', 'personal-crm' ),

			// Family - immediate
			'parent'       => __( 'Parent', 'personal-crm' ),
			'child'        => __( 'Child', 'personal-crm' ),
			'sibling'      => __( 'Sibling', 'personal-crm' ),

			// Family - extended
			'grandparent'  => __( 'Grandparent', 'personal-crm' ),
			'grandchild'   => __( 'Grandchild', 'personal-crm' ),
			'uncle'        => __( 'Uncle', 'personal-crm' ),
			'aunt'         => __( 'Aunt', 'personal-crm' ),
			'nephew'       => __( 'Nephew', 'personal-crm' ),
			'niece'        => __( 'Niece', 'personal-crm' ),
			'cousin'       => __( 'Cousin', 'personal-crm' ),

			// Family - step/in-law
			'stepparent'   => __( 'Stepparent', 'personal-crm' ),
			'stepchild'    => __( 'Stepchild', 'personal-crm' ),
			'stepsibling'  => __( 'Stepsibling', 'personal-crm' ),
			'inlaw'        => __( 'In-law', 'personal-crm' ),

			// Family - other
			'godparent'    => __( 'Godparent', 'personal-crm' ),
			'godchild'     => __( 'Godchild', 'personal-crm' ),

			// Professional
			'boss'         => __( 'Boss', 'personal-crm' ),
			'subordinate'  => __( 'Subordinate', 'personal-crm' ),
			'mentor'       => __( 'Mentor', 'personal-crm' ),
			'mentee'       => __( 'Mentee', 'personal-crm' ),
		);

		foreach ( $defaults as $slug => $name ) {
			if ( ! term_exists( $slug, 'relationship_type' ) ) {
				wp_insert_term( $name, 'relationship_type', array( 'slug' => $slug ) );
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
		$types     = array();
		$all_terms = get_terms(
			array(
				'taxonomy'   => 'relationship_type',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $all_terms ) ) {
			foreach ( $all_terms as $term ) {
				$types[ $term->slug ] = $term->term_id;
			}
		}

		// Symmetric relationships (same type as inverse)
		$symmetric = array( 'spouse', 'friend', 'colleague', 'acquaintance', 'sibling', 'cousin', 'partner' );
		foreach ( $symmetric as $slug ) {
			if ( isset( $types[ $slug ] ) ) {
				$inverse = get_field( 'inverse_relationship_type', 'relationship_type_' . $types[ $slug ] );
				if ( ! $inverse ) {
					update_field( 'inverse_relationship_type', $types[ $slug ], 'relationship_type_' . $types[ $slug ] );
				}
			}
		}

		// Asymmetric parent-child relationships
		$asymmetric = array(
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
		);

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
		$aunt_uncle_group = array( 'aunt', 'uncle' );
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
		$niece_nephew_group = array( 'niece', 'nephew' );
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
		$defaults = array(
			// Core types
			'birthday'                => __( 'Birthday', 'personal-crm' ),
			'memorial'                => __( 'Memorial', 'personal-crm' ),
			'first-met'               => __( 'First Met', 'personal-crm' ),

			// Family & relationships (Monica category 2)
			'new-relationship'        => __( 'New Relationship', 'personal-crm' ),
			'engagement'              => __( 'Engagement', 'personal-crm' ),
			'wedding'                 => __( 'Wedding', 'personal-crm' ),
			'marriage'                => __( 'Marriage', 'personal-crm' ),
			'expecting-a-baby'        => __( 'Expecting a Baby', 'personal-crm' ),
			'new-child'               => __( 'New Child', 'personal-crm' ),
			'new-family-member'       => __( 'New Family Member', 'personal-crm' ),
			'new-pet'                 => __( 'New Pet', 'personal-crm' ),
			'end-of-relationship'     => __( 'End of Relationship', 'personal-crm' ),
			'loss-of-a-loved-one'     => __( 'Loss of a Loved One', 'personal-crm' ),

			// Work & education (Monica category 1)
			'new-job'                 => __( 'New Job', 'personal-crm' ),
			'retirement'              => __( 'Retirement', 'personal-crm' ),
			'new-school'              => __( 'New School', 'personal-crm' ),
			'study-abroad'            => __( 'Study Abroad', 'personal-crm' ),
			'volunteer-work'          => __( 'Volunteer Work', 'personal-crm' ),
			'published-book-or-paper' => __( 'Published Book or Paper', 'personal-crm' ),
			'military-service'        => __( 'Military Service', 'personal-crm' ),

			// Home & living (Monica category 3)
			'moved'                   => __( 'Moved', 'personal-crm' ),
			'bought-a-home'           => __( 'Bought a Home', 'personal-crm' ),
			'home-improvement'        => __( 'Home Improvement', 'personal-crm' ),
			'holidays'                => __( 'Holidays', 'personal-crm' ),
			'new-vehicle'             => __( 'New Vehicle', 'personal-crm' ),
			'new-roommate'            => __( 'New Roommate', 'personal-crm' ),

			// Health & wellness (Monica category 4)
			'overcame-an-illness'     => __( 'Overcame an Illness', 'personal-crm' ),
			'quit-a-habit'            => __( 'Quit a Habit', 'personal-crm' ),
			'new-eating-habits'       => __( 'New Eating Habits', 'personal-crm' ),
			'weight-loss'             => __( 'Weight Loss', 'personal-crm' ),
			'surgery'                 => __( 'Surgery', 'personal-crm' ),

			// Travel & experiences (Monica category 5)
			'new-sport'               => __( 'New Sport', 'personal-crm' ),
			'new-hobby'               => __( 'New Hobby', 'personal-crm' ),
			'new-instrument'          => __( 'New Instrument', 'personal-crm' ),
			'new-language'            => __( 'New Language', 'personal-crm' ),
			'travel'                  => __( 'Travel', 'personal-crm' ),
			'achievement-or-award'    => __( 'Achievement or Award', 'personal-crm' ),
			'first-word'              => __( 'First Word', 'personal-crm' ),
			'first-kiss'              => __( 'First Kiss', 'personal-crm' ),

			// Fallback
			'other'                   => __( 'Other', 'personal-crm' ),
		);

		foreach ( $defaults as $slug => $name ) {
			if ( ! term_exists( $slug, 'date_type' ) ) {
				wp_insert_term( $name, 'date_type', array( 'slug' => $slug ) );
			}
		}
	}
}
