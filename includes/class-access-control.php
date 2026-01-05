<?php
/**
 * Access Control for Personal CRM
 * 
 * Users can only see posts they created themselves.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Access_Control {
    
    /**
     * Post types that should have access control
     */
    private $controlled_post_types = ['person', 'company', 'important_date'];
    
    /**
     * Check if we're on the frontend (not admin area)
     */
    private function is_frontend() {
        return !is_admin();
    }
    
    public function __construct() {
        // Filter queries to only show accessible posts
        add_action('pre_get_posts', [$this, 'filter_queries']);
        
        // Filter REST API queries
        add_filter('rest_person_query', [$this, 'filter_rest_query'], 10, 2);
        add_filter('rest_company_query', [$this, 'filter_rest_query'], 10, 2);
        add_filter('rest_important_date_query', [$this, 'filter_rest_query'], 10, 2);
        
        // Check single post access
        add_filter('the_posts', [$this, 'filter_single_post_access'], 10, 2);
        
        // Filter REST API single item access
        add_filter('rest_prepare_person', [$this, 'filter_rest_single_access'], 10, 3);
        add_filter('rest_prepare_company', [$this, 'filter_rest_single_access'], 10, 3);
        add_filter('rest_prepare_important_date', [$this, 'filter_rest_single_access'], 10, 3);
    }
    
    /**
     * Check if a user can access a post
     */
    public function user_can_access_post($post_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Admins can access everything in admin area, but are restricted on frontend
        if (user_can($user_id, 'manage_options')) {
            // On frontend, admins are restricted like regular users
            if ($this->is_frontend()) {
                // Continue with normal access checks below
            } else {
                // In admin area, admins have full access (except trashed posts)
                $post = get_post($post_id);
                if ($post && $post->post_status === 'trash') {
                    return false;
                }
                return true;
            }
        }
        
        $post = get_post($post_id);
        
        if (!$post || !in_array($post->post_type, $this->controlled_post_types)) {
            return true; // Not a controlled post type
        }
        
        // Don't allow access to trashed posts
        if ($post->post_status === 'trash') {
            return false;
        }
        
        // Check if user is the author
        if ((int) $post->post_author === (int) $user_id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Filter WP_Query to only show accessible posts
     */
    public function filter_queries($query) {
        // Don't filter admin queries for admins
        if (is_admin() && current_user_can('manage_options')) {
            return;
        }
        
        // Only filter our controlled post types
        $post_type = $query->get('post_type');
        
        if (!$post_type || !in_array($post_type, $this->controlled_post_types)) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            // Not logged in - show nothing
            $query->set('post__in', [0]);
            return;
        }
        
        // On frontend, admins are also restricted
        // Only skip filtering for admins in admin area
        if (current_user_can('manage_options') && is_admin()) {
            return;
        }
        
        // Get IDs of posts authored by user
        $accessible_ids = $this->get_accessible_post_ids($post_type, $user_id);
        
        if (empty($accessible_ids)) {
            $query->set('post__in', [0]); // No accessible posts
        } else {
            $query->set('post__in', $accessible_ids);
        }
    }
    
    /**
     * Get all post IDs accessible by a user
     */
    public function get_accessible_post_ids($post_type, $user_id) {
        global $wpdb;
        
        // Get posts authored by user
        $authored = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = %s 
             AND post_status = 'publish' 
             AND post_author = %d",
            $post_type,
            $user_id
        ));
        
        return $authored;
    }
    
    /**
     * Filter REST API queries
     */
    public function filter_rest_query($args, $request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            $args['post__in'] = [0];
            return $args;
        }
        
        // REST API calls are typically from the frontend React app
        // Admins should be restricted on the frontend, so we filter REST API calls for admins too
        // The admin area uses WP_Query directly, not REST API
        
        $post_type = $args['post_type'] ?? '';
        $accessible_ids = $this->get_accessible_post_ids($post_type, $user_id);
        
        if (empty($accessible_ids)) {
            $args['post__in'] = [0];
        } else {
            $args['post__in'] = $accessible_ids;
        }
        
        return $args;
    }
    
    /**
     * Filter single post access in queries
     */
    public function filter_single_post_access($posts, $query) {
        if (empty($posts)) {
            return $posts;
        }
        
        $user_id = get_current_user_id();
        
        // On frontend, admins are also restricted
        // Only skip filtering for admins in admin area
        if (current_user_can('manage_options') && is_admin()) {
            return $posts;
        }
        
        $filtered = [];
        
        foreach ($posts as $post) {
            if (!in_array($post->post_type, $this->controlled_post_types)) {
                $filtered[] = $post;
                continue;
            }
            
            if ($this->user_can_access_post($post->ID, $user_id)) {
                $filtered[] = $post;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Filter REST API single item access
     */
    public function filter_rest_single_access($response, $post, $request) {
        $user_id = get_current_user_id();
        
        // Don't allow access to trashed posts
        if ($post->post_status === 'trash') {
            return new WP_Error(
                'rest_forbidden',
                __('This item has been deleted.', 'personal-crm'),
                ['status' => 404]
            );
        }
        
        if (!$this->user_can_access_post($post->ID, $user_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this item.', 'personal-crm'),
                ['status' => 403]
            );
        }
        
        return $response;
    }
}
