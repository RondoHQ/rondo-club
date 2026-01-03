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
        $this->register_person_post_type();
        $this->register_company_post_type();
        $this->register_important_date_post_type();
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
            'query_var'           => true,
            'rewrite'             => ['slug' => 'person'],
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
            'name'                  => _x('Companies', 'Post type general name', 'personal-crm'),
            'singular_name'         => _x('Company', 'Post type singular name', 'personal-crm'),
            'menu_name'             => _x('Companies', 'Admin Menu text', 'personal-crm'),
            'add_new'               => __('Add New', 'personal-crm'),
            'add_new_item'          => __('Add New Company', 'personal-crm'),
            'edit_item'             => __('Edit Company', 'personal-crm'),
            'new_item'              => __('New Company', 'personal-crm'),
            'view_item'             => __('View Company', 'personal-crm'),
            'search_items'          => __('Search Companies', 'personal-crm'),
            'not_found'             => __('No companies found', 'personal-crm'),
            'not_found_in_trash'    => __('No companies found in Trash', 'personal-crm'),
            'all_items'             => __('All Companies', 'personal-crm'),
        ];
        
        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'rest_base'           => 'companies',
            'query_var'           => true,
            'rewrite'             => ['slug' => 'company'],
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 6,
            'menu_icon'           => 'dashicons-building',
            'supports'            => ['title', 'editor', 'thumbnail', 'author'],
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
            'query_var'           => true,
            'rewrite'             => ['slug' => 'important-date'],
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
