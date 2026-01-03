<?php
/**
 * Custom Taxonomies Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Taxonomies {
    
    public function __construct() {
        add_action('init', [$this, 'register_taxonomies']);
    }
    
    /**
     * Register all custom taxonomies
     */
    public function register_taxonomies() {
        $this->register_person_label_taxonomy();
        $this->register_company_label_taxonomy();
        $this->register_relationship_type_taxonomy();
        $this->register_date_type_taxonomy();
    }
    
    /**
     * Register Person Label Taxonomy
     */
    private function register_person_label_taxonomy() {
        $labels = [
            'name'              => _x('Labels', 'taxonomy general name', 'personal-crm'),
            'singular_name'     => _x('Label', 'taxonomy singular name', 'personal-crm'),
            'search_items'      => __('Search Labels', 'personal-crm'),
            'all_items'         => __('All Labels', 'personal-crm'),
            'edit_item'         => __('Edit Label', 'personal-crm'),
            'update_item'       => __('Update Label', 'personal-crm'),
            'add_new_item'      => __('Add New Label', 'personal-crm'),
            'new_item_name'     => __('New Label Name', 'personal-crm'),
            'menu_name'         => __('Labels', 'personal-crm'),
        ];
        
        $args = [
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'person-label'],
        ];
        
        register_taxonomy('person_label', ['person'], $args);
    }
    
    /**
     * Register Company Label Taxonomy
     */
    private function register_company_label_taxonomy() {
        $labels = [
            'name'              => _x('Company Labels', 'taxonomy general name', 'personal-crm'),
            'singular_name'     => _x('Company Label', 'taxonomy singular name', 'personal-crm'),
            'search_items'      => __('Search Company Labels', 'personal-crm'),
            'all_items'         => __('All Company Labels', 'personal-crm'),
            'edit_item'         => __('Edit Company Label', 'personal-crm'),
            'update_item'       => __('Update Company Label', 'personal-crm'),
            'add_new_item'      => __('Add New Company Label', 'personal-crm'),
            'new_item_name'     => __('New Company Label Name', 'personal-crm'),
            'menu_name'         => __('Labels', 'personal-crm'),
        ];
        
        $args = [
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'company-label'],
        ];
        
        register_taxonomy('company_label', ['company'], $args);
    }
    
    /**
     * Register Relationship Type Taxonomy
     */
    private function register_relationship_type_taxonomy() {
        $labels = [
            'name'              => _x('Relationship Types', 'taxonomy general name', 'personal-crm'),
            'singular_name'     => _x('Relationship Type', 'taxonomy singular name', 'personal-crm'),
            'search_items'      => __('Search Relationship Types', 'personal-crm'),
            'all_items'         => __('All Relationship Types', 'personal-crm'),
            'edit_item'         => __('Edit Relationship Type', 'personal-crm'),
            'update_item'       => __('Update Relationship Type', 'personal-crm'),
            'add_new_item'      => __('Add New Relationship Type', 'personal-crm'),
            'new_item_name'     => __('New Relationship Type Name', 'personal-crm'),
            'menu_name'         => __('Relationship Types', 'personal-crm'),
        ];
        
        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => false,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'relationship-type'],
        ];
        
        register_taxonomy('relationship_type', ['person'], $args);
        
        // Add default terms on activation
        $this->add_default_relationship_types();
    }
    
    /**
     * Register Date Type Taxonomy
     */
    private function register_date_type_taxonomy() {
        $labels = [
            'name'              => _x('Date Types', 'taxonomy general name', 'personal-crm'),
            'singular_name'     => _x('Date Type', 'taxonomy singular name', 'personal-crm'),
            'search_items'      => __('Search Date Types', 'personal-crm'),
            'all_items'         => __('All Date Types', 'personal-crm'),
            'edit_item'         => __('Edit Date Type', 'personal-crm'),
            'update_item'       => __('Update Date Type', 'personal-crm'),
            'add_new_item'      => __('Add New Date Type', 'personal-crm'),
            'new_item_name'     => __('New Date Type Name', 'personal-crm'),
            'menu_name'         => __('Date Types', 'personal-crm'),
        ];
        
        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'date-type'],
        ];
        
        register_taxonomy('date_type', ['important_date'], $args);
        
        // Add default terms on activation
        $this->add_default_date_types();
    }
    
    /**
     * Add default relationship types
     */
    private function add_default_relationship_types() {
        $defaults = [
            // Basic relationships
            'partner'       => __('Partner', 'personal-crm'),
            'spouse'        => __('Spouse', 'personal-crm'),
            'friend'        => __('Friend', 'personal-crm'),
            'colleague'     => __('Colleague', 'personal-crm'),
            'acquaintance'  => __('Acquaintance', 'personal-crm'),
            'ex'            => __('Ex', 'personal-crm'),

            // Family - immediate
            'parent'        => __('Parent', 'personal-crm'),
            'child'         => __('Child', 'personal-crm'),
            'sibling'       => __('Sibling', 'personal-crm'),

            // Family - extended
            'grandparent'   => __('Grandparent', 'personal-crm'),
            'grandchild'    => __('Grandchild', 'personal-crm'),
            'uncle'         => __('Uncle', 'personal-crm'),
            'aunt'          => __('Aunt', 'personal-crm'),
            'nephew'        => __('Nephew', 'personal-crm'),
            'niece'         => __('Niece', 'personal-crm'),
            'cousin'        => __('Cousin', 'personal-crm'),

            // Family - step/in-law
            'stepparent'    => __('Stepparent', 'personal-crm'),
            'stepchild'     => __('Stepchild', 'personal-crm'),
            'stepsibling'   => __('Stepsibling', 'personal-crm'),
            'inlaw'         => __('In-law', 'personal-crm'),

            // Family - other
            'godparent'     => __('Godparent', 'personal-crm'),
            'godchild'      => __('Godchild', 'personal-crm'),

            // Professional
            'boss'          => __('Boss', 'personal-crm'),
            'subordinate'   => __('Subordinate', 'personal-crm'),
            'mentor'        => __('Mentor', 'personal-crm'),
            'mentee'        => __('Mentee', 'personal-crm'),
        ];

        foreach ($defaults as $slug => $name) {
            if (!term_exists($slug, 'relationship_type')) {
                wp_insert_term($name, 'relationship_type', ['slug' => $slug]);
            }
        }
    }
    
    /**
     * Add default date types - aligned with Monica CRM life event types
     */
    private function add_default_date_types() {
        $defaults = [
            // Core types
            'birthday'              => __('Birthday', 'personal-crm'),
            'memorial'              => __('Memorial', 'personal-crm'),
            'first-met'             => __('First Met', 'personal-crm'),

            // Family & relationships (Monica category 2)
            'new-relationship'      => __('New Relationship', 'personal-crm'),
            'engagement'            => __('Engagement', 'personal-crm'),
            'wedding'               => __('Wedding', 'personal-crm'),
            'marriage'              => __('Marriage', 'personal-crm'),
            'expecting-a-baby'      => __('Expecting a Baby', 'personal-crm'),
            'new-child'             => __('New Child', 'personal-crm'),
            'new-family-member'     => __('New Family Member', 'personal-crm'),
            'new-pet'               => __('New Pet', 'personal-crm'),
            'end-of-relationship'   => __('End of Relationship', 'personal-crm'),
            'loss-of-a-loved-one'   => __('Loss of a Loved One', 'personal-crm'),

            // Work & education (Monica category 1)
            'new-job'               => __('New Job', 'personal-crm'),
            'retirement'            => __('Retirement', 'personal-crm'),
            'new-school'            => __('New School', 'personal-crm'),
            'study-abroad'          => __('Study Abroad', 'personal-crm'),
            'volunteer-work'        => __('Volunteer Work', 'personal-crm'),
            'published-book-or-paper' => __('Published Book or Paper', 'personal-crm'),
            'military-service'      => __('Military Service', 'personal-crm'),

            // Home & living (Monica category 3)
            'moved'                 => __('Moved', 'personal-crm'),
            'bought-a-home'         => __('Bought a Home', 'personal-crm'),
            'home-improvement'      => __('Home Improvement', 'personal-crm'),
            'holidays'              => __('Holidays', 'personal-crm'),
            'new-vehicle'           => __('New Vehicle', 'personal-crm'),
            'new-roommate'          => __('New Roommate', 'personal-crm'),

            // Health & wellness (Monica category 4)
            'overcame-an-illness'   => __('Overcame an Illness', 'personal-crm'),
            'quit-a-habit'          => __('Quit a Habit', 'personal-crm'),
            'new-eating-habits'     => __('New Eating Habits', 'personal-crm'),
            'weight-loss'           => __('Weight Loss', 'personal-crm'),
            'surgery'               => __('Surgery', 'personal-crm'),

            // Travel & experiences (Monica category 5)
            'new-sport'             => __('New Sport', 'personal-crm'),
            'new-hobby'             => __('New Hobby', 'personal-crm'),
            'new-instrument'        => __('New Instrument', 'personal-crm'),
            'new-language'          => __('New Language', 'personal-crm'),
            'travel'                => __('Travel', 'personal-crm'),
            'achievement-or-award'  => __('Achievement or Award', 'personal-crm'),
            'first-word'            => __('First Word', 'personal-crm'),
            'first-kiss'            => __('First Kiss', 'personal-crm'),

            // Fallback
            'other'                 => __('Other', 'personal-crm'),
        ];

        foreach ($defaults as $slug => $name) {
            if (!term_exists($slug, 'date_type')) {
                wp_insert_term($name, 'date_type', ['slug' => $slug]);
            }
        }
    }
}
