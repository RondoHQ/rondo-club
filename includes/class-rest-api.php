<?php
/**
 * Extended REST API Endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_REST_API extends PRM_REST_Base {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_api_init', [$this, 'register_acf_fields']);
    }
    
    /**
     * Register custom REST routes
     */
    public function register_routes() {
        // Upcoming reminders
        register_rest_route('prm/v1', '/reminders', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_upcoming_reminders'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'days_ahead' => [
                    'default'           => 30,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 365;
                    },
                ],
            ],
        ]);
        
        // Trigger reminders manually (admin only)
        register_rest_route('prm/v1', '/reminders/trigger', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'trigger_reminders'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
        
        // Check cron status (admin only)
        register_rest_route('prm/v1', '/reminders/cron-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_cron_status'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
        
        // Reschedule all user reminder cron jobs (admin only)
        register_rest_route('prm/v1', '/reminders/reschedule-cron', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'reschedule_all_cron_jobs'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
        
        // Get user notification channels
        register_rest_route('prm/v1', '/user/notification-channels', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_notification_channels'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Update user notification channels
        register_rest_route('prm/v1', '/user/notification-channels', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_notification_channels'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'channels' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_array($param);
                    },
                ],
            ],
        ]);
        
        // Update Slack webhook URL
        register_rest_route('prm/v1', '/user/slack-webhook', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_slack_webhook'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'webhook' => [
                    'required'          => false,
                    'validate_callback' => function($param) {
                        return empty($param) || filter_var($param, FILTER_VALIDATE_URL);
                    },
                ],
            ],
        ]);
        
        // Update notification time
        register_rest_route('prm/v1', '/user/notification-time', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_notification_time'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'time' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        // Validate HH:MM format
                        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $param);
                    },
                ],
            ],
        ]);
        
        // Search across all content
        register_rest_route('prm/v1', '/search', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'global_search'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'q' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen($param) >= 2;
                    },
                ],
            ],
        ]);
        
        // Dashboard summary
        register_rest_route('prm/v1', '/dashboard', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_dashboard_summary'],
            'permission_callback' => [$this, 'check_user_approved'],
        ]);
        
        // Version check (public endpoint for cache invalidation)
        register_rest_route('prm/v1', '/version', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_version'],
            'permission_callback' => '__return_true',
        ]);
        
        // All todos (across all people)
        register_rest_route('prm/v1', '/todos', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_all_todos'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'completed' => [
                    'default'           => false,
                    'validate_callback' => function($param) {
                        return is_bool($param) || $param === 'true' || $param === 'false' || $param === '1' || $param === '0';
                    },
                ],
            ],
        ]);
        
        // Get companies where a person or company is an investor
        register_rest_route('prm/v1', '/investments/(?P<investor_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_investments'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'investor_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
        // Restore default relationship type configurations
        register_rest_route('prm/v1', '/relationship-types/restore-defaults', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'restore_relationship_type_defaults'],
            'permission_callback' => [$this, 'check_user_approved'],
        ]);

        // Current user info
        // Allow logged-in users (not just approved) so we can check approval status
        register_rest_route('prm/v1', '/user/me', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_current_user'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);

        // User management (admin only)
        register_rest_route('prm/v1', '/users', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_users'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
        
        register_rest_route('prm/v1', '/users/(?P<user_id>\d+)/approve', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'approve_user'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args'                => [
                'user_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
        register_rest_route('prm/v1', '/users/(?P<user_id>\d+)/deny', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'deny_user'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args'                => [
                'user_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
        register_rest_route('prm/v1', '/users/(?P<user_id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_user'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args'                => [
                'user_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
        // Export contacts
        register_rest_route('prm/v1', '/export/vcard', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'export_vcard'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        register_rest_route('prm/v1', '/export/google-csv', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'export_google_csv'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // CardDAV URLs
        register_rest_route('prm/v1', '/carddav/urls', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_carddav_urls'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }
    
    /**
     * Register ACF fields to REST API
     */
    public function register_acf_fields() {
        // Expose ACF fields in REST API for taxonomy terms
        add_filter('rest_prepare_relationship_type', [$this, 'add_acf_to_relationship_type'], 10, 3);
        
        // Allow updating ACF fields via REST API
        add_action('rest_insert_relationship_type', [$this, 'update_relationship_type_acf'], 10, 3);
    }
    
    /**
     * Add ACF fields to relationship_type REST response
     */
    public function add_acf_to_relationship_type($response, $term, $request) {
        $acf_data = get_fields('relationship_type_' . $term->term_id);
        if ($acf_data) {
            $response->data['acf'] = $acf_data;
        }
        return $response;
    }
    
    /**
     * Update ACF fields when relationship_type is updated via REST API
     */
    public function update_relationship_type_acf($term, $request, $creating) {
        $acf_data = $request->get_param('acf');
        if (is_array($acf_data)) {
            foreach ($acf_data as $field_name => $value) {
                update_field($field_name, $value, 'relationship_type_' . $term->term_id);
            }
        }
    }
    
    /**
     * Restore default relationship type configurations
     */
    public function restore_relationship_type_defaults($request) {
        // Get the taxonomies class instance
        $taxonomies = new PRM_Taxonomies();
        
        // Call the setup method (make it public or add a public wrapper)
        if (method_exists($taxonomies, 'setup_default_relationship_configurations')) {
            $taxonomies->setup_default_relationship_configurations();
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Default relationship type configurations have been restored.', 'personal-crm'),
            ], 200);
        }
        
        return new WP_Error(
            'restore_failed',
            __('Failed to restore defaults.', 'personal-crm'),
            ['status' => 500]
        );
    }
    /**
     * Get upcoming reminders
     */
    public function get_upcoming_reminders($request) {
        $days_ahead = (int) $request->get_param('days_ahead');
        
        $reminders_handler = new PRM_Reminders();
        $upcoming = $reminders_handler->get_upcoming_reminders($days_ahead);
        
        return rest_ensure_response($upcoming);
    }
    
    /**
     * Manually trigger reminder emails for today (admin only)
     */
    public function trigger_reminders($request) {
        $reminders_handler = new PRM_Reminders();
        
        // Get all users who should receive reminders
        $users_to_notify = $this->get_all_users_to_notify_for_trigger();
        
        $users_processed = 0;
        $notifications_sent = 0;
        
        foreach ($users_to_notify as $user_id) {
            // Get weekly digest for this user
            $digest_data = $reminders_handler->get_weekly_digest($user_id);
            
            // Send via all enabled channels
            $email_channel = new PRM_Email_Channel();
            $slack_channel = new PRM_Slack_Channel();
            
            if ($email_channel->is_enabled_for_user($user_id)) {
                if ($email_channel->send($user_id, $digest_data)) {
                    $notifications_sent++;
                }
            }
            
            if ($slack_channel->is_enabled_for_user($user_id)) {
                if ($slack_channel->send($user_id, $digest_data)) {
                    $notifications_sent++;
                }
            }
            
            $users_processed++;
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(
                __('Processed %d user(s), sent %d notification(s).', 'personal-crm'),
                $users_processed,
                $notifications_sent
            ),
            'users_processed' => $users_processed,
            'notifications_sent' => $notifications_sent,
        ]);
    }
    
    /**
     * Get all users who should receive reminders (for trigger endpoint)
     */
    private function get_all_users_to_notify_for_trigger() {
        // Use direct database query to bypass access control filters
        // Admin trigger endpoint needs to see all dates regardless of user
        global $wpdb;
        
        $date_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = %s 
             AND post_status = 'publish'",
            'important_date'
        ));
        
        if (empty($date_ids)) {
            return [];
        }
        
        // Get full post objects
        $dates = array_map('get_post', $date_ids);
        
        $user_ids = [];
        
        foreach ($dates as $date_post) {
            // Get related people using ACF (handles repeater fields correctly)
            $related_people = get_field('related_people', $date_post->ID);
            
            if (empty($related_people)) {
                continue;
            }
            
            // Ensure it's an array
            if (!is_array($related_people)) {
                $related_people = [$related_people];
            }
            
            // Get user IDs from people post authors
            foreach ($related_people as $person) {
                $person_id = is_object($person) ? $person->ID : (is_array($person) ? $person['ID'] : $person);
                
                if (!$person_id) {
                    continue;
                }
                
                $person_post = get_post($person_id);
                if ($person_post) {
                    $user_ids[] = (int) $person_post->post_author;
                }
            }
        }
        
        return array_unique($user_ids);
    }
    
    /**
     * Get cron job status for reminders
     */
    public function get_cron_status($request) {
        $reminders = new PRM_Reminders();
        $users_to_notify = $reminders->get_all_users_to_notify();
        
        // Count users with scheduled cron jobs
        $scheduled_users = [];
        foreach ($users_to_notify as $user_id) {
            $next_run = wp_next_scheduled('prm_user_reminder', [$user_id]);
            if ($next_run !== false) {
                $user = get_userdata($user_id);
                $scheduled_users[] = [
                    'user_id' => $user_id,
                    'display_name' => $user ? $user->display_name : "User $user_id",
                    'next_run' => date('Y-m-d H:i:s', $next_run),
                    'next_run_timestamp' => $next_run,
                ];
            }
        }
        
        // Check legacy cron (deprecated)
        $legacy_scheduled = wp_next_scheduled('prm_daily_reminder_check');
        
        return rest_ensure_response([
            'total_users' => count($users_to_notify),
            'scheduled_users' => count($scheduled_users),
            'users' => $scheduled_users,
            'current_time' => date('Y-m-d H:i:s', time()),
            'current_timestamp' => time(),
            'legacy_cron_scheduled' => $legacy_scheduled !== false,
            'legacy_next_run' => $legacy_scheduled ? date('Y-m-d H:i:s', $legacy_scheduled) : null,
        ]);
    }
    
    /**
     * Reschedule all user reminder cron jobs (admin only)
     */
    public function reschedule_all_cron_jobs($request) {
        $reminders = new PRM_Reminders();
        
        // Reschedule all user cron jobs
        $scheduled_count = $reminders->schedule_all_user_reminders();
        
        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(
                __('Successfully rescheduled reminder cron jobs for %d user(s).', 'personal-crm'),
                $scheduled_count
            ),
            'users_scheduled' => $scheduled_count,
        ]);
    }
    
    /**
     * Get user's notification channel preferences
     */
    public function get_notification_channels($request) {
        $user_id = get_current_user_id();
        
        $channels = get_user_meta($user_id, 'caelis_notification_channels', true);
        if (!is_array($channels)) {
            // Default to email only
            $channels = ['email'];
        }
        
        $slack_webhook = get_user_meta($user_id, 'caelis_slack_webhook', true);
        
        $notification_time = get_user_meta($user_id, 'caelis_notification_time', true);
        if (empty($notification_time)) {
            // Default to 9:00 AM
            $notification_time = '09:00';
        }
        
        return rest_ensure_response([
            'channels' => $channels,
            'slack_webhook' => $slack_webhook ?: '',
            'notification_time' => $notification_time,
        ]);
    }
    
    /**
     * Update user's notification channel preferences
     */
    public function update_notification_channels($request) {
        $user_id = get_current_user_id();
        $channels = $request->get_param('channels');
        
        // Validate channels
        $valid_channels = ['email', 'slack'];
        $channels = array_intersect($channels, $valid_channels);
        
        // If Slack is enabled, check if webhook is configured
        if (in_array('slack', $channels)) {
            $webhook = get_user_meta($user_id, 'caelis_slack_webhook', true);
            if (empty($webhook)) {
                return new WP_Error(
                    'slack_webhook_required',
                    __('Slack webhook URL must be configured before enabling Slack notifications.', 'personal-crm'),
                    ['status' => 400]
                );
            }
        }
        
        update_user_meta($user_id, 'caelis_notification_channels', $channels);
        
        return rest_ensure_response([
            'success' => true,
            'channels' => $channels,
        ]);
    }
    
    /**
     * Update user's Slack webhook URL
     */
    public function update_slack_webhook($request) {
        $user_id = get_current_user_id();
        $webhook = $request->get_param('webhook');
        
        if (empty($webhook)) {
            // Remove webhook
            delete_user_meta($user_id, 'caelis_slack_webhook');
            
            // Also disable Slack channel if it's enabled
            $channels = get_user_meta($user_id, 'caelis_notification_channels', true);
            if (is_array($channels)) {
                $channels = array_diff($channels, ['slack']);
                update_user_meta($user_id, 'caelis_notification_channels', $channels);
            }
            
            return rest_ensure_response([
                'success' => true,
                'message' => __('Slack webhook removed.', 'personal-crm'),
            ]);
        }
        
        // Validate webhook URL
        if (!filter_var($webhook, FILTER_VALIDATE_URL)) {
            return new WP_Error(
                'invalid_webhook',
                __('Invalid webhook URL.', 'personal-crm'),
                ['status' => 400]
            );
        }
        
        // Test webhook with a simple message
        $test_payload = [
            'text' => __('Caelis notification test', 'personal-crm'),
        ];
        
        $response = wp_remote_post($webhook, [
            'body' => json_encode($test_payload),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'webhook_test_failed',
                sprintf(__('Webhook test failed: %s', 'personal-crm'), $response->get_error_message()),
                ['status' => 400]
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error(
                'webhook_test_failed',
                sprintf(__('Webhook test failed with status code: %d', 'personal-crm'), $status_code),
                ['status' => 400]
            );
        }
        
        // Save webhook
        update_user_meta($user_id, 'caelis_slack_webhook', $webhook);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Slack webhook configured successfully.', 'personal-crm'),
        ]);
    }
    
    /**
     * Update user's notification time preference
     */
    public function update_notification_time($request) {
        $user_id = get_current_user_id();
        $time = $request->get_param('time');
        
        // Validate time format (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return new WP_Error(
                'invalid_time',
                __('Invalid time format. Please use HH:MM format (e.g., 09:00).', 'personal-crm'),
                ['status' => 400]
            );
        }
        
        update_user_meta($user_id, 'caelis_notification_time', $time);
        
        // Reschedule user's reminder cron job at the new time
        $reminders = new PRM_Reminders();
        $schedule_result = $reminders->schedule_user_reminder($user_id);
        
        if (is_wp_error($schedule_result)) {
            return rest_ensure_response([
                'success' => true,
                'notification_time' => $time,
                'message' => __('Notification time updated, but failed to reschedule cron job.', 'personal-crm'),
                'cron_error' => $schedule_result->get_error_message(),
            ]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'notification_time' => $time,
            'message' => __('Notification time updated and cron job rescheduled successfully.', 'personal-crm'),
        ]);
    }
    
    /**
     * Global search across people, companies, and dates
     */
    public function global_search($request) {
        $query = sanitize_text_field($request->get_param('q'));
        
        $results = [
            'people'    => [],
            'companies' => [],
        ];
        
        // Search people
        $people = get_posts([
            'post_type'      => 'person',
            's'              => $query,
            'posts_per_page' => 10,
            'post_status'    => 'publish',
        ]);
        
        foreach ($people as $person) {
            $results['people'][] = $this->format_person_summary($person);
        }
        
        // Search companies
        $companies = get_posts([
            'post_type'      => 'company',
            's'              => $query,
            'posts_per_page' => 10,
            'post_status'    => 'publish',
        ]);
        
        foreach ($companies as $company) {
            $results['companies'][] = $this->format_company_summary($company);
        }
        
        return rest_ensure_response($results);
    }
    
    /**
     * Get current theme version
     * Used for cache invalidation on PWA/mobile apps
     */
    public function get_version($request) {
        return rest_ensure_response([
            'version' => PRM_THEME_VERSION,
        ]);
    }
    
    /**
     * Get dashboard summary
     */
    public function get_dashboard_summary($request) {
        $user_id = get_current_user_id();
        
        // Get accessible post counts (respects access control)
        $access_control = new PRM_Access_Control();
        
        // For admins, use wp_count_posts for efficiency
        // For regular users, count only their accessible posts
        if (current_user_can('manage_options')) {
            $total_people = wp_count_posts('person')->publish;
            $total_companies = wp_count_posts('company')->publish;
            $total_dates = wp_count_posts('important_date')->publish;
        } else {
            $total_people = count($access_control->get_accessible_post_ids('person', $user_id));
            $total_companies = count($access_control->get_accessible_post_ids('company', $user_id));
            $total_dates = count($access_control->get_accessible_post_ids('important_date', $user_id));
        }
        
        // Recent people
        $recent_people = get_posts([
            'post_type'      => 'person',
            'posts_per_page' => 5,
            'post_status'    => 'publish',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]);
        
        // Upcoming reminders
        $reminders_handler = new PRM_Reminders();
        $upcoming_reminders = $reminders_handler->get_upcoming_reminders(14);
        
        // Favorites
        $favorites = get_posts([
            'post_type'      => 'person',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => 'is_favorite',
                    'value' => '1',
                ],
            ],
        ]);
        
        // Get open todos count
        $open_todos_count = $this->count_open_todos();
        
        // Recently contacted (people with most recent activities)
        $recently_contacted = $this->get_recently_contacted_people(5);
        
        return rest_ensure_response([
            'stats' => [
                'total_people'     => $total_people,
                'total_companies'  => $total_companies,
                'total_dates'      => $total_dates,
                'open_todos_count' => $open_todos_count,
            ],
            'recent_people'       => array_map([$this, 'format_person_summary'], $recent_people),
            'upcoming_reminders'  => array_slice($upcoming_reminders, 0, 5),
            'favorites'           => array_map([$this, 'format_person_summary'], $favorites),
            'recently_contacted'  => $recently_contacted,
        ]);
    }
    
    /**
     * Count open (non-completed) todos for current user
     */
    private function count_open_todos() {
        $user_id = get_current_user_id();
        
        // Get all people accessible by this user
        $access_control = new PRM_Access_Control();
        $accessible_people = $access_control->get_accessible_post_ids('person', $user_id);
        
        if (empty($accessible_people)) {
            return 0;
        }
        
        // Count todos that are not completed
        $comments = get_comments([
            'post__in' => $accessible_people,
            'type'     => 'prm_todo',
            'status'   => 'approve',
            'count'    => true,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key'     => 'is_completed',
                    'value'   => '0',
                    'compare' => '=',
                ],
                [
                    'key'     => 'is_completed',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);
        
        return (int) $comments;
    }
    
    /**
     * Get people with most recent activities
     *
     * @param int $limit Number of people to return
     * @return array Array of person summaries with last activity info
     */
    private function get_recently_contacted_people($limit = 5) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $access_control = new PRM_Access_Control();
        $accessible_people = $access_control->get_accessible_post_ids('person', $user_id);
        
        if (empty($accessible_people)) {
            return [];
        }
        
        // Get the most recent activity for each person
        $placeholders = implode(',', array_fill(0, count($accessible_people), '%d'));
        
        // Query to get people with their most recent activity date
        $query = $wpdb->prepare(
            "SELECT c.comment_post_ID as person_id, MAX(cm.meta_value) as last_activity_date
             FROM {$wpdb->comments} c
             INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'activity_date'
             WHERE c.comment_type = 'prm_activity'
             AND c.comment_approved = '1'
             AND c.comment_post_ID IN ($placeholders)
             GROUP BY c.comment_post_ID
             ORDER BY last_activity_date DESC
             LIMIT %d",
            ...array_merge($accessible_people, [$limit])
        );
        
        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            return [];
        }
        
        $recently_contacted = [];
        foreach ($results as $row) {
            $person = get_post($row->person_id);
            if ($person && $person->post_status === 'publish') {
                $summary = $this->format_person_summary($person);
                $summary['last_activity_date'] = $row->last_activity_date;
                $recently_contacted[] = $summary;
            }
        }
        
        return $recently_contacted;
    }
    
    /**
     * Get all todos across all people for current user
     */
    public function get_all_todos($request) {
        $user_id = get_current_user_id();
        $include_completed = $request->get_param('completed');
        
        // Normalize boolean parameter
        if ($include_completed === 'true' || $include_completed === '1') {
            $include_completed = true;
        } else {
            $include_completed = false;
        }
        
        // Get all people accessible by this user
        $access_control = new PRM_Access_Control();
        $accessible_people = $access_control->get_accessible_post_ids('person', $user_id);
        
        if (empty($accessible_people)) {
            return rest_ensure_response([]);
        }
        
        // Build query args
        $args = [
            'post__in' => $accessible_people,
            'type'     => 'prm_todo',
            'status'   => 'approve',
            'orderby'  => 'comment_date',
            'order'    => 'DESC',
            'number'   => 100, // Reasonable limit
        ];
        
        // Filter by completion status
        if (!$include_completed) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'is_completed',
                    'value'   => '0',
                    'compare' => '=',
                ],
                [
                    'key'     => 'is_completed',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        }
        
        $comments = get_comments($args);
        
        $todos = [];
        foreach ($comments as $comment) {
            $person_id = (int) $comment->comment_post_ID;
            $person_name = get_the_title($person_id);
            $person_thumbnail = get_the_post_thumbnail_url($person_id, 'thumbnail');
            
            $is_completed = get_comment_meta($comment->comment_ID, 'is_completed', true);
            $due_date = get_comment_meta($comment->comment_ID, 'due_date', true);
            
            $todos[] = [
                'id'               => (int) $comment->comment_ID,
                'type'             => 'todo',
                'content'          => $comment->comment_content,
                'person_id'        => $person_id,
                'person_name'      => $person_name,
                'person_thumbnail' => $person_thumbnail ?: '',
                'author_id'        => (int) $comment->user_id,
                'created'          => $comment->comment_date,
                'is_completed'     => !empty($is_completed),
                'due_date'         => $due_date ?: null,
            ];
        }
        
        // Sort by due date (earliest first), todos without due date at end
        usort($todos, function($a, $b) {
            // Completed todos go to the bottom
            if ($a['is_completed'] && !$b['is_completed']) return 1;
            if (!$a['is_completed'] && $b['is_completed']) return -1;
            
            // For incomplete todos, sort by due date
            if (!$a['is_completed'] && !$b['is_completed']) {
                if ($a['due_date'] && $b['due_date']) {
                    return strtotime($a['due_date']) - strtotime($b['due_date']);
                }
                if ($a['due_date'] && !$b['due_date']) return -1;
                if (!$a['due_date'] && $b['due_date']) return 1;
            }
            
            // For completed or same status, sort by creation date (newest first)
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return rest_ensure_response($todos);
    }
    
    /**
     * Get companies where a person or company is listed as an investor
     */
    public function get_investments($request) {
        $investor_id = (int) $request->get_param('investor_id');
        $user_id = get_current_user_id();
        
        // Get all companies accessible by this user
        $access_control = new PRM_Access_Control();
        $accessible_companies = $access_control->get_accessible_post_ids('company', $user_id);
        
        if (empty($accessible_companies)) {
            return rest_ensure_response([]);
        }
        
        // Query companies where this ID appears in the investors field
        $companies = get_posts([
            'post_type'      => 'company',
            'post__in'       => $accessible_companies,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'investors',
                    'value'   => sprintf('"%d"', $investor_id),
                    'compare' => 'LIKE',
                ],
            ],
        ]);
        
        // Also check with serialized format (ACF stores as serialized array)
        $companies_serialized = get_posts([
            'post_type'      => 'company',
            'post__in'       => $accessible_companies,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'investors',
                    'value'   => serialize(strval($investor_id)),
                    'compare' => 'LIKE',
                ],
            ],
        ]);
        
        // Merge and dedupe
        $all_companies = array_merge($companies, $companies_serialized);
        $seen_ids = [];
        $unique_companies = [];
        foreach ($all_companies as $company) {
            if (!in_array($company->ID, $seen_ids)) {
                $seen_ids[] = $company->ID;
                $unique_companies[] = $company;
            }
        }
        
        // Format response
        $investments = [];
        foreach ($unique_companies as $company) {
            $thumbnail_id = get_post_thumbnail_id($company->ID);
            $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : '';
            
            $investments[] = [
                'id'        => $company->ID,
                'name'      => html_entity_decode($company->post_title, ENT_QUOTES, 'UTF-8'),
                'industry'  => get_field('industry', $company->ID) ?: '',
                'website'   => get_field('website', $company->ID) ?: '',
                'thumbnail' => $thumbnail_url,
            ];
        }
        
        // Sort alphabetically by name
        usort($investments, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        return rest_ensure_response($investments);
    }

    /**
     * Get current user information
     */
    public function get_current_user($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('User is not logged in.', 'personal-crm'), ['status' => 401]);
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            return new WP_Error('user_not_found', __('User not found.', 'personal-crm'), ['status' => 404]);
        }
        
        // Get avatar URL
        $avatar_url = get_avatar_url($user_id, ['size' => 96]);
        
        // Check if user is admin
        $is_admin = current_user_can('manage_options');
        
        // Get profile edit URL
        $profile_url = admin_url('profile.php');
        
        // Get admin URL
        $admin_url = admin_url();
        
        // Check approval status
        $is_approved = PRM_User_Roles::is_user_approved($user_id);
        
        return rest_ensure_response([
            'id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar_url' => $avatar_url,
            'is_admin' => $is_admin,
            'is_approved' => $is_approved,
            'profile_url' => $profile_url,
            'admin_url' => $admin_url,
        ]);
    }

    /**
     * Get list of users (admin only)
     */
    public function get_users($request) {
        $users = get_users(['role' => PRM_User_Roles::ROLE_NAME]);
        
        $user_list = [];
        foreach ($users as $user) {
            $user_list[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'is_approved' => PRM_User_Roles::is_user_approved($user->ID),
                'registered' => $user->user_registered,
            ];
        }
        
        return rest_ensure_response($user_list);
    }
    
    /**
     * Approve a user (admin only)
     */
    public function approve_user($request) {
        $user_id = (int) $request->get_param('user_id');
        $user_roles = new PRM_User_Roles();
        $user_roles->approve_user($user_id);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('User approved.', 'personal-crm'),
        ]);
    }
    
    /**
     * Deny a user (admin only)
     */
    public function deny_user($request) {
        $user_id = (int) $request->get_param('user_id');
        $user_roles = new PRM_User_Roles();
        $user_roles->deny_user($user_id);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('User denied.', 'personal-crm'),
        ]);
    }
    
    /**
     * Delete a user and all their related data (admin only)
     */
    public function delete_user($request) {
        $user_id = (int) $request->get_param('user_id');
        
        // Prevent deleting yourself
        if ($user_id === get_current_user_id()) {
            return new WP_Error(
                'cannot_delete_self',
                __('You cannot delete your own account.', 'personal-crm'),
                ['status' => 400]
            );
        }
        
        // Check if user exists
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error(
                'user_not_found',
                __('User not found.', 'personal-crm'),
                ['status' => 404]
            );
        }
        
        // Delete all user's posts (people, organizations, dates)
        $this->delete_user_posts($user_id);
        
        // Delete the user
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($user_id);
        
        if (!$result) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete user.', 'personal-crm'),
                ['status' => 500]
            );
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('User and all related data deleted.', 'personal-crm'),
        ]);
    }
    
    /**
     * Delete all posts belonging to a user
     */
    private function delete_user_posts($user_id) {
        $post_types = ['person', 'company', 'important_date'];
        
        foreach ($post_types as $post_type) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'author'         => $user_id,
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]);
            
            foreach ($posts as $post) {
                wp_delete_post($post->ID, true); // Force delete (bypass trash)
            }
        }
    }
    
    /**
     * Export all contacts as vCard
     */
    public function export_vcard($request) {
        $user_id = get_current_user_id();
        $access_control = new PRM_Access_Control();
        
        // Get all accessible people
        $people_ids = $access_control->get_accessible_post_ids('person', $user_id);
        
        if (empty($people_ids)) {
            return new WP_Error('no_contacts', __('No contacts to export.', 'personal-crm'), ['status' => 404]);
        }
        
        // Get companies for work history
        $company_ids = $access_control->get_accessible_post_ids('company', $user_id);
        $company_map = [];
        foreach ($company_ids as $company_id) {
            $company = get_post($company_id);
            if ($company) {
                $company_map[$company_id] = $company->post_title;
            }
        }
        
        // Build vCard content
        $vcards = [];
        foreach ($people_ids as $person_id) {
            $person = get_post($person_id);
            if (!$person || $person->post_status !== 'publish') {
                continue;
            }
            
            // Get person data via REST API to ensure proper formatting
            $rest_request = new WP_REST_Request('GET', "/wp/v2/people/{$person_id}");
            $rest_request->set_query_params(['_embed' => true]);
            $rest_response = rest_do_request($rest_request);
            
            if (is_wp_error($rest_response) || $rest_response->get_status() !== 200) {
                continue;
            }
            
            $person_data = $rest_response->get_data();
            
            // Get dates for birthday
            $dates_request = new WP_REST_Request('GET', "/prm/v1/people/{$person_id}/dates");
            $dates_response = rest_do_request($dates_request);
            $person_dates = [];
            if (!is_wp_error($dates_response) && $dates_response->get_status() === 200) {
                $person_dates = $dates_response->get_data();
            }
            
            // Generate vCard
            $vcard = $this->generate_vcard_from_person($person_data, $company_map, $person_dates);
            if ($vcard) {
                $vcards[] = $vcard;
            }
        }
        
        if (empty($vcards)) {
            return new WP_Error('export_failed', __('Failed to generate vCard export.', 'personal-crm'), ['status' => 500]);
        }
        
        $vcard_content = implode("\n", $vcards);
        
        // Return as download
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="caelis-contacts.vcf"');
        header('Content-Length: ' . strlen($vcard_content));
        echo $vcard_content;
        exit;
    }
    
    /**
     * Export all contacts as Google Contacts CSV
     */
    public function export_google_csv($request) {
        $user_id = get_current_user_id();
        $access_control = new PRM_Access_Control();
        
        // Get all accessible people
        $people_ids = $access_control->get_accessible_post_ids('person', $user_id);
        
        if (empty($people_ids)) {
            return new WP_Error('no_contacts', __('No contacts to export.', 'personal-crm'), ['status' => 404]);
        }
        
        // Google Contacts CSV headers
        $headers = [
            'Name',
            'Given Name',
            'Additional Name',
            'Family Name',
            'Yomi Name',
            'Given Name Yomi',
            'Additional Name Yomi',
            'Family Name Yomi',
            'Name Prefix',
            'Name Suffix',
            'Initials',
            'Nickname',
            'Short Name',
            'Maiden Name',
            'Birthday',
            'Gender',
            'Location',
            'Billing Information',
            'Directory Server',
            'Mileage',
            'Occupation',
            'Hobby',
            'Sensitivity',
            'Priority',
            'Subject',
            'Notes',
            'Language',
            'Photo',
            'Group Membership',
            'E-mail 1 - Type',
            'E-mail 1 - Value',
            'E-mail 2 - Type',
            'E-mail 2 - Value',
            'E-mail 3 - Type',
            'E-mail 3 - Value',
            'Phone 1 - Type',
            'Phone 1 - Value',
            'Phone 2 - Type',
            'Phone 2 - Value',
            'Phone 3 - Type',
            'Phone 3 - Value',
            'Address 1 - Type',
            'Address 1 - Formatted',
            'Address 1 - Street',
            'Address 1 - City',
            'Address 1 - PO Box',
            'Address 1 - Region',
            'Address 1 - Postal Code',
            'Address 1 - Country',
            'Organization 1 - Type',
            'Organization 1 - Name',
            'Organization 1 - Yomi Name',
            'Organization 1 - Title',
            'Organization 1 - Department',
            'Organization 1 - Symbol',
            'Organization 1 - Location',
            'Organization 1 - Job Description',
        ];
        
        $rows = [];
        foreach ($people_ids as $person_id) {
            $person = get_post($person_id);
            if (!$person || $person->post_status !== 'publish') {
                continue;
            }
            
            // Get person data via REST API
            $rest_request = new WP_REST_Request('GET', "/wp/v2/people/{$person_id}");
            $rest_request->set_query_params(['_embed' => true]);
            $rest_response = rest_do_request($rest_request);
            
            if (is_wp_error($rest_response) || $rest_response->get_status() !== 200) {
                continue;
            }
            
            $person_data = $rest_response->get_data();
            $acf = $person_data['acf'] ?? [];
            
            $row = array_fill(0, count($headers), '');
            
            // Name fields
            $first_name = $acf['first_name'] ?? '';
            $last_name = $acf['last_name'] ?? '';
            $full_name = trim("{$first_name} {$last_name}");
            
            $row[0] = $full_name; // Name
            $row[1] = $first_name; // Given Name
            $row[3] = $last_name; // Family Name
            $row[11] = $acf['nickname'] ?? ''; // Nickname
            
            // Birthday
            $dates_request = new WP_REST_Request('GET', "/prm/v1/people/{$person_id}/dates");
            $dates_response = rest_do_request($dates_request);
            if (!is_wp_error($dates_response) && $dates_response->get_status() === 200) {
                $dates = $dates_response->get_data();
                foreach ($dates as $date) {
                    if (isset($date['date_type']) && $date['date_type'] === 'birthday' && isset($date['date_value'])) {
                        $row[14] = date('Y-m-d', strtotime($date['date_value'])); // Birthday
                        break;
                    }
                }
            }
            
            // Contact info
            $contact_info = $acf['contact_info'] ?? [];
            $email_count = 0;
            $phone_count = 0;
            
            foreach ($contact_info as $contact) {
                $type = $contact['contact_type'] ?? '';
                $value = $contact['contact_value'] ?? '';
                $label = $contact['contact_label'] ?? '';
                
                if ($type === 'email' && $email_count < 3) {
                    $email_count++;
                    $row[28 + ($email_count - 1) * 2] = $label ?: '* My Contacts'; // Type
                    $row[29 + ($email_count - 1) * 2] = $value; // Value
                } elseif (($type === 'phone' || $type === 'mobile') && $phone_count < 3) {
                    $phone_count++;
                    $phone_type = ($type === 'mobile') ? 'Mobile' : ($label ?: 'Work');
                    $row[34 + ($phone_count - 1) * 2] = $phone_type; // Type
                    $row[35 + ($phone_count - 1) * 2] = $value; // Value
                } elseif ($type === 'address') {
                    // Address parsing would be complex, just use formatted value
                    $row[40] = $label ?: 'Work'; // Type
                    $row[41] = $value; // Formatted
                }
            }
            
            // Work history
            $work_history = $acf['work_history'] ?? [];
            if (!empty($work_history)) {
                $current_job = null;
                foreach ($work_history as $job) {
                    if (!empty($job['is_current'])) {
                        $current_job = $job;
                        break;
                    }
                }
                if (!$current_job && !empty($work_history)) {
                    $current_job = $work_history[0];
                }
                
                if ($current_job) {
                    $company_id = $current_job['company'] ?? null;
                    if ($company_id) {
                        $company = get_post($company_id);
                        if ($company) {
                            $row[48] = 'Work'; // Organization Type
                            $row[49] = $company->post_title; // Organization Name
                        }
                    }
                    $row[51] = $current_job['job_title'] ?? ''; // Title
                    $row[52] = $current_job['department'] ?? ''; // Department
                }
            }
            
            // Photo
            if (isset($person_data['thumbnail']) && !empty($person_data['thumbnail'])) {
                $row[27] = $person_data['thumbnail']; // Photo URL
            }
            
            $rows[] = $row;
        }
        
        // Generate CSV
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility
        echo "\xEF\xBB\xBF";
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write rows
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="caelis-contacts.csv"');
        exit;
    }
    
    /**
     * Generate vCard from person data
     */
    private function generate_vcard_from_person($person_data, $company_map = [], $person_dates = []) {
        $acf = $person_data['acf'] ?? [];
        $lines = [];
        
        $lines[] = 'BEGIN:VCARD';
        $lines[] = 'VERSION:3.0';
        
        // Name
        $first_name = $acf['first_name'] ?? '';
        $last_name = $acf['last_name'] ?? '';
        $full_name = $person_data['name'] ?? trim("{$first_name} {$last_name}") ?: 'Unknown';
        
        $lines[] = 'FN:' . $this->escape_vcard_value($full_name);
        $lines[] = 'N:' . $this->escape_vcard_value($last_name) . ';' . $this->escape_vcard_value($first_name) . ';;;';
        
        if (!empty($acf['nickname'])) {
            $lines[] = 'NICKNAME:' . $this->escape_vcard_value($acf['nickname']);
        }
        
        // Contact info
        $contact_info = $acf['contact_info'] ?? [];
        foreach ($contact_info as $contact) {
            $type = $contact['contact_type'] ?? '';
            $value = $contact['contact_value'] ?? '';
            $label = $contact['contact_label'] ?? '';
            
            if (empty($value)) {
                continue;
            }
            
            $escaped_value = $this->escape_vcard_value($value);
            
            switch ($type) {
                case 'email':
                    $email_type = $label ? "EMAIL;TYPE=INTERNET,{$label}" : 'EMAIL;TYPE=INTERNET';
                    $lines[] = "{$email_type}:{$escaped_value}";
                    break;
                    
                case 'phone':
                case 'mobile':
                    $phone_type = ($type === 'mobile') ? 'CELL' : 'VOICE';
                    $tel_type = $label ? "TEL;TYPE={$phone_type},{$label}" : "TEL;TYPE={$phone_type}";
                    $lines[] = "{$tel_type}:{$escaped_value}";
                    break;
                    
                case 'address':
                    $lines[] = "ADR;TYPE=HOME:;;{$escaped_value};;;;";
                    break;
                    
                case 'website':
                case 'linkedin':
                case 'twitter':
                case 'instagram':
                case 'facebook':
                    $url = $value;
                    if (!preg_match('/^https?:\/\//i', $url)) {
                        $url = 'https://' . $url;
                    }
                    $lines[] = 'URL;TYPE=WORK:' . $this->escape_vcard_value($url);
                    break;
            }
        }
        
        // Organization
        $work_history = $acf['work_history'] ?? [];
        if (!empty($work_history)) {
            $current_job = null;
            foreach ($work_history as $job) {
                if (!empty($job['is_current'])) {
                    $current_job = $job;
                    break;
                }
            }
            if (!$current_job && !empty($work_history)) {
                $current_job = $work_history[0];
            }
            
            if ($current_job) {
                $company_id = $current_job['company'] ?? null;
                if ($company_id && isset($company_map[$company_id])) {
                    $lines[] = 'ORG:' . $this->escape_vcard_value($company_map[$company_id]);
                }
                if (!empty($current_job['job_title'])) {
                    $lines[] = 'TITLE:' . $this->escape_vcard_value($current_job['job_title']);
                }
            }
        }
        
        // Birthday
        foreach ($person_dates as $date) {
            if (isset($date['date_type']) && $date['date_type'] === 'birthday' && isset($date['date_value'])) {
                $birthday = date('Ymd', strtotime($date['date_value']));
                $lines[] = "BDAY:{$birthday}";
                break;
            }
        }
        
        // Photo
        if (isset($person_data['thumbnail']) && !empty($person_data['thumbnail'])) {
            // vCard photo would need to be base64 encoded, skip for now
            // Could be added later if needed
        }
        
        $lines[] = 'END:VCARD';
        
        return implode("\r\n", $lines);
    }
    
    /**
     * Escape vCard value
     */
    private function escape_vcard_value($value) {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace("\n", '\\n', $value);
        return $value;
    }

    /**
     * Get CardDAV URLs for the current user
     */
    public function get_carddav_urls($request) {
        $user = wp_get_current_user();
        
        if (!$user || !$user->ID) {
            return new WP_Error(
                'not_logged_in',
                __('You must be logged in.', 'personal-crm'),
                ['status' => 401]
            );
        }
        
        $base_url = home_url('/carddav/');
        
        return rest_ensure_response([
            'server' => $base_url,
            'principal' => $base_url . 'principals/' . $user->user_login . '/',
            'addressbook' => $base_url . 'addressbooks/' . $user->user_login . '/contacts/',
            'username' => $user->user_login,
        ]);
    }
    
}
