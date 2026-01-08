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
        // Get the user by login
        $user = get_user_by('login', $username);
        
        if (!$user) {
            error_log('CardDAV Auth Failed: User not found - ' . $username);
            return false;
        }
        
        // Normalize the password (remove spaces that WordPress adds for display)
        $password = preg_replace('/\s+/', '', $password);
        
        // Get the user's application passwords
        $app_passwords = \WP_Application_Passwords::get_user_application_passwords($user->ID);
        
        if (empty($app_passwords)) {
            error_log('CardDAV Auth Failed for user: ' . $username . ' - No application passwords found');
            return false;
        }
        
        // Check each application password
        foreach ($app_passwords as $app_password) {
            if (wp_check_password($password, $app_password['password'], $user->ID)) {
                // Store the authenticated user for later use
                $this->current_user = $user;
                
                // Set WordPress current user
                wp_set_current_user($user->ID);
                
                // Record the usage (optional but good for tracking)
                \WP_Application_Passwords::record_application_password_usage($user->ID, $app_password['uuid']);
                
                return true;
            }
        }
        
        error_log('CardDAV Auth Failed for user: ' . $username . ' - Invalid application password');
        return false;
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

