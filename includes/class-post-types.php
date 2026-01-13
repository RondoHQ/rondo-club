<?php
/**
 * Custom Post Types Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Post_Types {
    
    public function __construct() {
        add_action('init', [$this, 'register_post_types']);
    }
    
    /**
     * Register all custom post types
     */
    public function register_post_types() {
        $this->register_workspace_post_type();
        $this->register_workspace_invite_post_type();
        $this->register_person_post_type();
        $this->register_company_post_type();
        $this->register_important_date_post_type();
    }

    /**
     * Register Workspace CPT
     */
    private function register_workspace_post_type() {
        $labels = [
            'name'                  => _x('Workspaces', 'Post type general name', 'personal-crm'),
            'singular_name'         => _x('Workspace', 'Post type singular name', 'personal-crm'),
            'menu_name'             => _x('Workspaces', 'Admin Menu text', 'personal-crm'),
            'add_new'               => __('Add New', 'personal-crm'),
            'add_new_item'          => __('Add New Workspace', 'personal-crm'),
            'edit_item'             => __('Edit Workspace', 'personal-crm'),
            'new_item'              => __('New Workspace', 'personal-crm'),
            'view_item'             => __('View Workspace', 'personal-crm'),
            'search_items'          => __('Search Workspaces', 'personal-crm'),
            'not_found'             => __('No workspaces found', 'personal-crm'),
            'not_found_in_trash'    => __('No workspaces found in Trash', 'personal-crm'),
            'all_items'             => __('All Workspaces', 'personal-crm'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'rest_base'           => 'workspaces',
            'query_var'           => false,
            'rewrite'             => false, // Disable rewrite rules - React Router handles routing
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 4,
            'menu_icon'           => 'dashicons-networking',
            'supports'            => ['title', 'editor', 'author', 'thumbnail'],
        ];

        register_post_type('workspace', $args);
    }

    /**
     * Register Workspace Invite CPT
     *
     * Used for tracking workspace invitations sent to users via email.
     * Not exposed via standard wp/v2 REST - uses custom endpoints only.
     */
    private function register_workspace_invite_post_type() {
        $labels = [
            'name'                  => _x('Workspace Invites', 'Post type general name', 'personal-crm'),
            'singular_name'         => _x('Workspace Invite', 'Post type singular name', 'personal-crm'),
            'menu_name'             => _x('Invites', 'Admin Menu text', 'personal-crm'),
            'add_new'               => __('Add New', 'personal-crm'),
            'add_new_item'          => __('Add New Invite', 'personal-crm'),
            'edit_item'             => __('Edit Invite', 'personal-crm'),
            'new_item'              => __('New Invite', 'personal-crm'),
            'view_item'             => __('View Invite', 'personal-crm'),
            'search_items'          => __('Search Invites', 'personal-crm'),
            'not_found'             => __('No invites found', 'personal-crm'),
            'not_found_in_trash'    => __('No invites found in Trash', 'personal-crm'),
            'all_items'             => __('All Invites', 'personal-crm'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=workspace', // Submenu under Workspaces
            'show_in_rest'        => false, // Custom endpoints only
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => ['title'], // Title = email for easy identification
        ];

        register_post_type('workspace_invite', $args);
    }

    /**
     * Register Person CPT
     */
    private function register_person_post_type() {
        $labels = [
            'name'                  => _x('People', 'Post type general name', 'personal-crm'),
            'singular_name'         => _x('Person', 'Post type singular name', 'personal-crm'),
            'menu_name'             => _x('People', 'Admin Menu text', 'personal-crm'),
            'add_new'               => __('Add New', 'personal-crm'),
            'add_new_item'          => __('Add New Person', 'personal-crm'),
            'edit_item'             => __('Edit Person', 'personal-crm'),
            'new_item'              => __('New Person', 'personal-crm'),
            'view_item'             => __('View Person', 'personal-crm'),
            'search_items'          => __('Search People', 'personal-crm'),
            'not_found'             => __('No people found', 'personal-crm'),
            'not_found_in_trash'    => __('No people found in Trash', 'personal-crm'),
            'all_items'             => __('All People', 'personal-crm'),
        ];
        
        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'rest_base'           => 'people',
            'query_var'           => false,
            'rewrite'             => false, // Disable rewrite rules - React Router handles routing
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-groups',
            'supports'            => ['title', 'thumbnail', 'comments', 'author'],
        ];
        
        register_post_type('person', $args);
    }
    
    /**
     * Register Company CPT
     */
    private function register_company_post_type() {
        $labels = [
            'name'                  => _x('Organizations', 'Post type general name', 'personal-crm'),
            'singular_name'         => _x('Organization', 'Post type singular name', 'personal-crm'),
            'menu_name'             => _x('Organizations', 'Admin Menu text', 'personal-crm'),
            'add_new'               => __('Add New', 'personal-crm'),
            'add_new_item'          => __('Add New Organization', 'personal-crm'),
            'edit_item'             => __('Edit Organization', 'personal-crm'),
            'new_item'              => __('New Organization', 'personal-crm'),
            'view_item'             => __('View Organization', 'personal-crm'),
            'search_items'          => __('Search Organizations', 'personal-crm'),
            'not_found'             => __('No organizations found', 'personal-crm'),
            'not_found_in_trash'    => __('No organizations found in Trash', 'personal-crm'),
            'all_items'             => __('All Organizations', 'personal-crm'),
        ];
        
        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'rest_base'           => 'companies',
            'query_var'           => false,
            'rewrite'             => false, // Disable rewrite rules - React Router handles routing
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => true, // Enable parent-child relationships
            'menu_position'       => 6,
            'menu_icon'           => 'dashicons-building',
            'supports'            => ['title', 'editor', 'thumbnail', 'author', 'page-attributes'],
        ];
        
        register_post_type('company', $args);
    }
    
    /**
     * Register Important Date CPT
     */
    private function register_important_date_post_type() {
        $labels = [
            'name'                  => _x('Important Dates', 'Post type general name', 'personal-crm'),
            'singular_name'         => _x('Important Date', 'Post type singular name', 'personal-crm'),
            'menu_name'             => _x('Dates', 'Admin Menu text', 'personal-crm'),
            'add_new'               => __('Add New', 'personal-crm'),
            'add_new_item'          => __('Add New Date', 'personal-crm'),
            'edit_item'             => __('Edit Date', 'personal-crm'),
            'new_item'              => __('New Date', 'personal-crm'),
            'view_item'             => __('View Date', 'personal-crm'),
            'search_items'          => __('Search Dates', 'personal-crm'),
            'not_found'             => __('No dates found', 'personal-crm'),
            'not_found_in_trash'    => __('No dates found in Trash', 'personal-crm'),
            'all_items'             => __('All Dates', 'personal-crm'),
        ];
        
        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'rest_base'           => 'important-dates',
            'query_var'           => false,
            'rewrite'             => false, // Disable rewrite rules - React Router handles routing
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 7,
            'menu_icon'           => 'dashicons-calendar-alt',
            'supports'            => ['title', 'editor', 'author'],
        ];
        
        register_post_type('important_date', $args);
    }
}
