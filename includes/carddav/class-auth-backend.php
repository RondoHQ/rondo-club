<?php
/**
 * CardDAV Authentication Backend
 * 
 * Uses custom CardDAV passwords stored with standard WordPress hashing.
 * This bypasses SiteGround's custom $generic$ password format.
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
     * User meta key for CardDAV passwords
     */
    const META_KEY = '_caelis_carddav_passwords';
    
    /**
     * Current authenticated user
     *
     * @var \WP_User|null
     */
    protected $current_user = null;
    
    /**
     * Validate user credentials against CardDAV passwords
     *
     * @param string $username Username
     * @param string $password CardDAV password
     * @return bool True if valid
     */
    protected function validateUserPass($username, $password) {
        // Get the user by login
        $user = get_user_by('login', $username);
        
        if (!$user) {
            error_log('CardDAV Auth Failed: User not found - ' . $username);
            return false;
        }
        
        // Normalize the password (remove spaces)
        $password = preg_replace('/\s+/', '', $password);
        
        // Get the user's CardDAV passwords
        $carddav_passwords = self::get_passwords($user->ID);
        
        if (empty($carddav_passwords)) {
            error_log('CardDAV Auth Failed for user: ' . $username . ' - No CardDAV passwords found');
            return false;
        }
        
        // Check each CardDAV password
        foreach ($carddav_passwords as $uuid => $stored_password) {
            // Use standard WordPress password check (phpass)
            if (wp_check_password($password, $stored_password['hash'])) {
                // Store the authenticated user for later use
                $this->current_user = $user;
                
                // Set WordPress current user
                wp_set_current_user($user->ID);
                
                // Record the usage
                self::record_usage($user->ID, $uuid);
                
                error_log('CardDAV Auth: Success for user ' . $username . ' with password "' . $stored_password['name'] . '"');
                return true;
            }
        }
        
        error_log('CardDAV Auth Failed for user: ' . $username . ' - Invalid password');
        return false;
    }
    
    /**
     * Get all CardDAV passwords for a user
     *
     * @param int $user_id User ID
     * @return array Array of passwords
     */
    public static function get_passwords($user_id) {
        $passwords = get_user_meta($user_id, self::META_KEY, true);
        return is_array($passwords) ? $passwords : [];
    }
    
    /**
     * Create a new CardDAV password
     *
     * @param int    $user_id User ID
     * @param string $name    Password name/label
     * @return array Array with 'password' (plaintext) and 'uuid'
     */
    public static function create_password($user_id, $name) {
        // Generate a random 24-character password (same as WordPress app passwords)
        $password = wp_generate_password(24, false);
        
        // Hash using standard WordPress phpass (will produce $P$ hash)
        $hash = wp_hash_password($password);
        
        // Generate a UUID for this password
        $uuid = wp_generate_uuid4();
        
        // Get existing passwords
        $passwords = self::get_passwords($user_id);
        
        // Add new password
        $passwords[$uuid] = [
            'name'      => sanitize_text_field($name),
            'hash'      => $hash,
            'created'   => time(),
            'last_used' => null,
            'last_ip'   => null,
        ];
        
        // Save
        update_user_meta($user_id, self::META_KEY, $passwords);
        
        // Return the plaintext password (only shown once) and UUID
        return [
            'password' => self::chunk_password($password),
            'uuid'     => $uuid,
            'name'     => $name,
            'created'  => $passwords[$uuid]['created'],
        ];
    }
    
    /**
     * Delete a CardDAV password
     *
     * @param int    $user_id User ID
     * @param string $uuid    Password UUID
     * @return bool True if deleted
     */
    public static function delete_password($user_id, $uuid) {
        $passwords = self::get_passwords($user_id);
        
        if (!isset($passwords[$uuid])) {
            return false;
        }
        
        unset($passwords[$uuid]);
        update_user_meta($user_id, self::META_KEY, $passwords);
        
        return true;
    }
    
    /**
     * Record password usage
     *
     * @param int    $user_id User ID
     * @param string $uuid    Password UUID
     */
    public static function record_usage($user_id, $uuid) {
        $passwords = self::get_passwords($user_id);
        
        if (!isset($passwords[$uuid])) {
            return;
        }
        
        $passwords[$uuid]['last_used'] = time();
        $passwords[$uuid]['last_ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
        
        update_user_meta($user_id, self::META_KEY, $passwords);
    }
    
    /**
     * Format password with spaces for display
     *
     * @param string $password Raw password
     * @return string Chunked password
     */
    public static function chunk_password($password) {
        return trim(chunk_split($password, 4, ' '));
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
