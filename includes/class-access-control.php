<?php
/**
 * Access Control for Personal CRM
 * 
 * Users can only see posts they created or that have been shared with them.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Access_Control {
    
    /**
     * Post types that should have access control
     */
    private $controlled_post_types = ['person', 'company', 'important_date'];
    
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
        
        // Admins can access everything
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        $post = get_post($post_id);
        
        if (!$post || !in_array($post->post_type, $this->controlled_post_types)) {
            return true; // Not a controlled post type
        }
        
        // Check if user is the author
        if ((int) $post->post_author === (int) $user_id) {
            return true;
        }
        
        // Check if post is shared with user
        $shared_with = get_field('shared_with', $post_id);
        
        if (is_array($shared_with)) {
            foreach ($shared_with as $shared_user) {
                $shared_user_id = is_object($shared_user) ? $shared_user->ID : $shared_user;
                if ((int) $shared_user_id === (int) $user_id) {
                    return true;
                }
            }
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
        
        // Skip for admins
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Build meta query for shared_with
        $meta_query = $query->get('meta_query') ?: [];
        
        // Get IDs of posts authored by user
        $author_query = [
            'author' => $user_id,
        ];
        
        // We need to use a custom approach: get all accessible post IDs
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
        
        // Get posts shared with user (ACF stores user IDs in serialized format)
        $shared = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = 'shared_with' 
             AND (meta_value LIKE %s OR meta_value LIKE %s)",
            '%"' . $user_id . '"%',
            '%i:' . $user_id . ';%'
        ));
        
        // Merge and deduplicate
        $all_ids = array_unique(array_merge($authored, $shared));
        
        return $all_ids;
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
        
        if (current_user_can('manage_options')) {
            return $args;
        }
        
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
        
        // Skip for admins
        if (current_user_can('manage_options')) {
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
