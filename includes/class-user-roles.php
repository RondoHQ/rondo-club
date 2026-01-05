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
    const APPROVAL_META_KEY = 'caelis_user_approved';
    
    public function __construct() {
        // Register role on theme activation
        add_action('after_switch_theme', [$this, 'register_role']);
        
        // Remove role on theme deactivation
        add_action('switch_theme', [$this, 'remove_role']);
        
        // Ensure role exists on init (in case theme was already active)
        add_action('init', [$this, 'ensure_role_exists'], 20);
        
        // Set default role for new users
        add_filter('pre_option_default_role', [$this, 'set_default_role']);
        
        // Set new users to Caelis User role and mark as unapproved
        add_action('user_register', [$this, 'handle_new_user_registration'], 10, 1);
        
        // Add admin columns for user approval
        add_filter('manage_users_columns', [$this, 'add_approval_column']);
        add_filter('manage_users_custom_column', [$this, 'show_approval_column'], 10, 3);
        
        // Add bulk actions for approval
        add_filter('bulk_actions-users', [$this, 'add_bulk_approval_actions']);
        add_filter('handle_bulk_actions-users', [$this, 'handle_bulk_approval'], 10, 3);
        
        // Add approve/deny actions to user row
        add_filter('user_row_actions', [$this, 'add_user_row_actions'], 10, 2);
        
        // Handle approve/deny actions
        add_action('admin_init', [$this, 'handle_approval_action']);
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
    
    /**
     * Set default role to Caelis User
     */
    public function set_default_role($value) {
        return self::ROLE_NAME;
    }
    
    /**
     * Handle new user registration
     */
    public function handle_new_user_registration($user_id) {
        // Set role to Caelis User
        $user = new WP_User($user_id);
        $user->set_role(self::ROLE_NAME);
        
        // Mark as unapproved by default
        update_user_meta($user_id, self::APPROVAL_META_KEY, '0');
    }
    
    /**
     * Check if a user is approved
     */
    public static function is_user_approved($user_id) {
        // Admins are always approved
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check approval status
        $approved = get_user_meta($user_id, self::APPROVAL_META_KEY, true);
        return $approved === '1' || $approved === true || $approved === 1;
    }
    
    /**
     * Approve a user
     */
    public function approve_user($user_id) {
        update_user_meta($user_id, self::APPROVAL_META_KEY, '1');
        
        // Send notification email
        $user = get_userdata($user_id);
        if ($user) {
            wp_mail(
                $user->user_email,
                __('Your Caelis account has been approved', 'personal-crm'),
                sprintf(
                    __('Hello %s,

Your Caelis account has been approved. You can now log in and start using Caelis.

Login: %s

Best regards,
Caelis Team', 'personal-crm'),
                    $user->display_name,
                    wp_login_url()
                )
            );
        }
    }
    
    /**
     * Deny/unapprove a user
     */
    public function deny_user($user_id) {
        update_user_meta($user_id, self::APPROVAL_META_KEY, '0');
    }
    
    /**
     * Add approval column to users list
     */
    public function add_approval_column($columns) {
        // Insert after role column
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'role') {
                $new_columns['caelis_approved'] = __('Approved', 'personal-crm');
            }
        }
        return $new_columns;
    }
    
    /**
     * Show approval status in column
     */
    public function show_approval_column($value, $column_name, $user_id) {
        if ($column_name === 'caelis_approved') {
            $is_approved = self::is_user_approved($user_id);
            $user = get_userdata($user_id);
            
            // Only show for Caelis Users
            if (in_array(self::ROLE_NAME, $user->roles)) {
                if ($is_approved) {
                    return '<span style="color: green;">✓ ' . __('Yes', 'personal-crm') . '</span>';
                } else {
                    return '<span style="color: red;">✗ ' . __('No', 'personal-crm') . '</span>';
                }
            }
            return '—';
        }
        return $value;
    }
    
    /**
     * Add bulk approval actions
     */
    public function add_bulk_approval_actions($actions) {
        $actions['caelis_approve'] = __('Approve', 'personal-crm');
        $actions['caelis_deny'] = __('Deny', 'personal-crm');
        return $actions;
    }
    
    /**
     * Handle bulk approval actions
     */
    public function handle_bulk_approval($sendback, $action, $user_ids) {
        if ($action === 'caelis_approve') {
            foreach ($user_ids as $user_id) {
                $this->approve_user($user_id);
            }
            $sendback = add_query_arg('caelis_approved', count($user_ids), $sendback);
        } elseif ($action === 'caelis_deny') {
            foreach ($user_ids as $user_id) {
                $this->deny_user($user_id);
            }
            $sendback = add_query_arg('caelis_denied', count($user_ids), $sendback);
        }
        return $sendback;
    }
    
    /**
     * Add approve/deny actions to user row
     */
    public function add_user_row_actions($actions, $user) {
        // Only show for Caelis Users
        if (in_array(self::ROLE_NAME, $user->roles)) {
            $is_approved = self::is_user_approved($user->ID);
            
            if (!$is_approved) {
                $actions['caelis_approve'] = sprintf(
                    '<a href="%s">%s</a>',
                    wp_nonce_url(
                        admin_url('users.php?action=caelis_approve&user=' . $user->ID),
                        'caelis_approve_user_' . $user->ID
                    ),
                    __('Approve', 'personal-crm')
                );
            } else {
                $actions['caelis_deny'] = sprintf(
                    '<a href="%s">%s</a>',
                    wp_nonce_url(
                        admin_url('users.php?action=caelis_deny&user=' . $user->ID),
                        'caelis_deny_user_' . $user->ID
                    ),
                    __('Deny', 'personal-crm')
                );
            }
        }
        
        return $actions;
    }
    
    /**
     * Handle approve/deny actions from user row
     */
    public function handle_approval_action() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['action']) && isset($_GET['user']) && isset($_GET['_wpnonce'])) {
            $action = sanitize_text_field($_GET['action']);
            $user_id = absint($_GET['user']);
            $nonce = sanitize_text_field($_GET['_wpnonce']);
            
            if ($action === 'caelis_approve') {
                if (wp_verify_nonce($nonce, 'caelis_approve_user_' . $user_id)) {
                    $this->approve_user($user_id);
                    wp_redirect(admin_url('users.php?caelis_approved=1'));
                    exit;
                }
            } elseif ($action === 'caelis_deny') {
                if (wp_verify_nonce($nonce, 'caelis_deny_user_' . $user_id)) {
                    $this->deny_user($user_id);
                    wp_redirect(admin_url('users.php?caelis_denied=1'));
                    exit;
                }
            }
        }
        
        // Show admin notices
        if (isset($_GET['caelis_approved'])) {
            add_action('admin_notices', function() {
                $count = absint($_GET['caelis_approved']);
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     sprintf(_n('User approved.', '%d users approved.', $count, 'personal-crm'), $count) . 
                     '</p></div>';
            });
        }
        
        if (isset($_GET['caelis_denied'])) {
            add_action('admin_notices', function() {
                $count = absint($_GET['caelis_denied']);
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     sprintf(_n('User denied.', '%d users denied.', $count, 'personal-crm'), $count) . 
                     '</p></div>';
            });
        }
    }
}

