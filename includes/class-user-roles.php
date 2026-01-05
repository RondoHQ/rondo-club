<?php
/**
 * User Roles for Caelis
 * 
 * Registers custom user role for Caelis users with minimal permissions
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_User_Roles {
    
    const ROLE_NAME = 'caelis_user';
    const ROLE_DISPLAY_NAME = 'Caelis User';
    
    public function __construct() {
        // Register role on theme activation
        add_action('after_switch_theme', [$this, 'register_role']);
        
        // Remove role on theme deactivation
        add_action('switch_theme', [$this, 'remove_role']);
        
        // Ensure role exists on init (in case theme was already active)
        add_action('init', [$this, 'ensure_role_exists'], 20);
    }
    
    /**
     * Ensure the role exists (for themes already active)
     */
    public function ensure_role_exists() {
        if (!get_role(self::ROLE_NAME)) {
            $this->register_role();
        }
    }
    
    /**
     * Register the Caelis User role
     */
    public function register_role() {
        // Get the role capabilities
        $capabilities = $this->get_role_capabilities();
        
        // Add the role
        add_role(
            self::ROLE_NAME,
            self::ROLE_DISPLAY_NAME,
            $capabilities
        );
    }
    
    /**
     * Remove the Caelis User role
     */
    public function remove_role() {
        // Get all users with this role
        $users = get_users(['role' => self::ROLE_NAME]);
        
        // Reassign to subscriber role before removing
        foreach ($users as $user) {
            $user->set_role('subscriber');
        }
        
        // Remove the role
        remove_role(self::ROLE_NAME);
    }
    
    /**
     * Get capabilities for Caelis User role
     * 
     * Minimal permissions needed to:
     * - Create, edit, and delete their own people and companies
     * - Upload files (for photos and logos)
     * - Read content (required for WordPress)
     */
    private function get_role_capabilities() {
        return [
            // Basic WordPress capabilities
            'read' => true,
            
            // Post capabilities (used by person, company, important_date post types)
            'edit_posts' => true,                    // Can create and edit their own posts
            'publish_posts' => true,                 // Can publish their own posts
            'delete_posts' => true,                  // Can delete their own posts
            'edit_published_posts' => true,          // Can edit their own published posts
            'delete_published_posts' => true,        // Can delete their own published posts
            
            // Media capabilities
            'upload_files' => true,                  // Can upload files (photos, logos)
            
            // No other capabilities - users can't:
            // - Edit other users' posts
            // - Manage other users
            // - Access WordPress admin settings
            // - Install plugins or themes
            // - Edit themes or plugins
        ];
    }
}

