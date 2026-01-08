<?php
/**
 * CardDAV Authentication Backend
 * 
 * Uses WordPress Application Passwords for authentication.
 * 
 * @package Caelis
 */

namespace Caelis\CardDAV;

use Sabre\DAV\Auth\Backend\AbstractBasic;

if (!defined('ABSPATH')) {
    exit;
}

class AuthBackend extends AbstractBasic {
    
    /**
     * Current authenticated user
     *
     * @var \WP_User|null
     */
    protected $current_user = null;
    
    /**
     * Validate user credentials against WordPress Application Passwords
     *
     * @param string $username Username
     * @param string $password Application password
     * @return bool True if valid
     */
    protected function validateUserPass($username, $password) {
        // Use wp_authenticate which handles both regular and application passwords
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            error_log('CardDAV Auth Failed for user: ' . $username . ' - ' . $user->get_error_message());
            return false;
        }
        
        if (!$user || !($user instanceof \WP_User)) {
            error_log('CardDAV Auth Failed for user: ' . $username . ' - Invalid user object');
            return false;
        }
        
        // Store the authenticated user for later use
        $this->current_user = $user;
        
        // Set WordPress current user
        wp_set_current_user($user->ID);
        
        return true;
    }
    
    /**
     * Get the current authenticated user
     *
     * @return \WP_User|null
     */
    public function getCurrentUser() {
        return $this->current_user;
    }
    
    /**
     * Get the current authenticated user ID
     *
     * @return int User ID or 0 if not authenticated
     */
    public function getCurrentUserId() {
        return $this->current_user ? $this->current_user->ID : 0;
    }
}

