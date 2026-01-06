<?php
/**
 * Extended REST API Endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('rest_api_init', [$this, 'register_acf_fields']);

        // Expand relationship data in person REST responses
        add_filter('rest_prepare_person', [$this, 'expand_person_relationships'], 10, 3);
    }
    
    /**
     * Register custom REST routes
     */
    public function register_routes() {
        // People by company
        register_rest_route('prm/v1', '/companies/(?P<company_id>\d+)/people', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_people_by_company'],
            'permission_callback' => '__return_true',
            'args'                => [
                'company_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
        // Dates by person
        register_rest_route('prm/v1', '/people/(?P<person_id>\d+)/dates', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_dates_by_person'],
            'permission_callback' => [$this, 'check_person_access'],
            'args'                => [
                'person_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
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
        
        // Restore default relationship type configurations
        register_rest_route('prm/v1', '/relationship-types/restore-defaults', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'restore_relationship_type_defaults'],
            'permission_callback' => [$this, 'check_user_approved'],
        ]);
        
        // Sideload Gravatar image
        register_rest_route('prm/v1', '/people/(?P<person_id>\d+)/gravatar', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'sideload_gravatar'],
            'permission_callback' => [$this, 'check_person_access'],
            'args'                => [
                'person_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'email' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_email($param);
                    },
                ],
            ],
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
        
        // Set company logo (featured image) - by media ID
        register_rest_route('prm/v1', '/companies/(?P<company_id>\d+)/logo', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'set_company_logo'],
            'permission_callback' => [$this, 'check_company_edit_permission'],
            'args'                => [
                'company_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'media_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
        // Upload person photo with proper filename
        register_rest_route('prm/v1', '/people/(?P<person_id>\d+)/photo', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'upload_person_photo'],
            'permission_callback' => [$this, 'check_person_edit_permission'],
            'args'                => [
                'person_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
        // Upload company logo with proper filename
        register_rest_route('prm/v1', '/companies/(?P<company_id>\d+)/logo/upload', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'upload_company_logo'],
            'permission_callback' => [$this, 'check_company_edit_permission'],
            'args'                => [
                'company_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
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
     * Check if user is logged in and approved
     */
    public function check_user_approved() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        
        // Admins are always approved
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if user is approved
        return PRM_User_Roles::is_user_approved($user_id);
    }
    
    /**
     * Check if user can access a person
     */
    public function check_person_access($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check approval first
        if (!$this->check_user_approved()) {
            return false;
        }
        
        $person_id = $request->get_param('person_id');
        $access_control = new PRM_Access_Control();
        
        return $access_control->user_can_access_post($person_id);
    }
    
    /**
     * Get people who work/worked at a company
     */
    public function get_people_by_company($request) {
        $company_id = (int) $request->get_param('company_id');
        $user_id = get_current_user_id();
        
        // Check if user can access this company
        $access_control = new PRM_Access_Control();
        if (!current_user_can('manage_options') && !$access_control->user_can_access_post($company_id, $user_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this company.', 'personal-crm'),
                ['status' => 403]
            );
        }
        
        // Get all people (if you can see the company, you can see who works there)
        // Don't rely on meta_query with ACF repeater fields - filter in PHP instead
        $people = get_posts([
            'post_type'      => 'person',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);
        
        $current = [];
        $former = [];
        
        // Loop through all people and check their work history
        foreach ($people as $person) {
            $work_history = get_field('work_history', $person->ID) ?: [];
            
            if (empty($work_history)) {
                continue;
            }
            
            // Find the relevant work history entry for this company
            foreach ($work_history as $job) {
                // Ensure type consistency for comparison
                $job_company_id = isset($job['company']) ? (int) $job['company'] : 0;
                
                if ($job_company_id === $company_id) {
                    $person_data = $this->format_person_summary($person);
                    $person_data['job_title'] = $job['job_title'] ?? '';
                    $person_data['start_date'] = $job['start_date'] ?? '';
                    $person_data['end_date'] = $job['end_date'] ?? '';
                    
                    // Determine if person is current or former
                    $is_current = false;
                    
                    // If is_current flag is set, check if end_date has passed
                    if (!empty($job['is_current'])) {
                        // If there's an end_date, check if it's in the future
                        if (!empty($job['end_date'])) {
                            $end_date = strtotime($job['end_date']);
                            $today = strtotime('today');
                            // Only current if end_date is today or in the future
                            $is_current = ($end_date >= $today);
                        } else {
                            // No end_date, so still current
                            $is_current = true;
                        }
                    } 
                    // If no is_current flag but no end_date, they're current
                    elseif (empty($job['end_date'])) {
                        $is_current = true;
                    }
                    // If end_date is in the future (and is_current not set), they're still current
                    elseif (!empty($job['end_date'])) {
                        $end_date = strtotime($job['end_date']);
                        $today = strtotime('today');
                        if ($end_date >= $today) {
                            $is_current = true;
                        }
                    }
                    
                    if ($is_current) {
                        $current[] = $person_data;
                    } else {
                        $former[] = $person_data;
                    }
                    break; // Found the matching job, move to next person
                }
            }
        }
        
        return rest_ensure_response([
            'current' => $current,
            'former'  => $former,
        ]);
    }
    
    /**
     * Get dates related to a person
     */
    public function get_dates_by_person($request) {
        $person_id = $request->get_param('person_id');
        
        $dates = get_posts([
            'post_type'      => 'important_date',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'related_people',
                    'value'   => '"' . $person_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ]);
        
        $formatted = array_map([$this, 'format_date'], $dates);
        
        return rest_ensure_response($formatted);
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
     * Global search across people, companies, and dates
     */
    public function global_search($request) {
        $query = sanitize_text_field($request->get_param('q'));
        
        $results = [
            'people'    => [],
            'companies' => [],
            'dates'     => [],
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
        
        // Search dates
        $dates = get_posts([
            'post_type'      => 'important_date',
            's'              => $query,
            'posts_per_page' => 10,
            'post_status'    => 'publish',
        ]);
        
        foreach ($dates as $date) {
            $results['dates'][] = $this->format_date($date);
        }
        
        return rest_ensure_response($results);
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
        
        return rest_ensure_response([
            'stats' => [
                'total_people'    => $total_people,
                'total_companies' => $total_companies,
                'total_dates'     => $total_dates,
            ],
            'recent_people'     => array_map([$this, 'format_person_summary'], $recent_people),
            'upcoming_reminders' => array_slice($upcoming_reminders, 0, 5),
            'favorites'         => array_map([$this, 'format_person_summary'], $favorites),
        ]);
    }
    
    /**
     * Expand relationship data with person names and relationship type names
     */
    public function expand_person_relationships($response, $post, $request) {
        $data = $response->get_data();

        if (!isset($data['acf']['relationships']) || !is_array($data['acf']['relationships'])) {
            return $response;
        }

        $expanded_relationships = [];

        foreach ($data['acf']['relationships'] as $rel) {
            // Get person ID - could be an object, array, or just an ID
            $person_id = null;
            if (is_object($rel['related_person'])) {
                $person_id = $rel['related_person']->ID;
            } elseif (is_array($rel['related_person'])) {
                $person_id = $rel['related_person']['ID'] ?? null;
            } else {
                $person_id = $rel['related_person'];
            }

            // Get relationship type - could be term object, array, or ID
            $type_id = null;
            $type_name = '';
            $type_slug = '';

            if (is_object($rel['relationship_type'])) {
                $type_id = $rel['relationship_type']->term_id;
                $type_name = $rel['relationship_type']->name;
                $type_slug = $rel['relationship_type']->slug;
            } elseif (is_array($rel['relationship_type'])) {
                $type_id = $rel['relationship_type']['term_id'] ?? null;
                $type_name = $rel['relationship_type']['name'] ?? '';
                $type_slug = $rel['relationship_type']['slug'] ?? '';
            } else {
                $type_id = $rel['relationship_type'];
                if ($type_id) {
                    $term = get_term($type_id, 'relationship_type');
                    if ($term && !is_wp_error($term)) {
                        $type_name = $term->name;
                        $type_slug = $term->slug;
                    }
                }
            }

            // Get person name
            $person_name = '';
            $person_thumbnail = '';
            if ($person_id) {
                $person_name = get_the_title($person_id);
                $person_thumbnail = get_the_post_thumbnail_url($person_id, 'thumbnail');
            }

            $expanded_relationships[] = [
                'related_person'     => $person_id,
                'person_name'        => $person_name,
                'person_thumbnail'   => $person_thumbnail ?: '',
                'relationship_type'  => $type_id,
                'relationship_name'  => $type_name,
                'relationship_slug'  => $type_slug,
                'relationship_label' => $rel['relationship_label'] ?? '',
            ];
        }

        $data['acf']['relationships'] = $expanded_relationships;
        $response->set_data($data);

        return $response;
    }

    /**
     * Format person for summary response
     */
    private function format_person_summary($post) {
        return [
            'id'          => $post->ID,
            'name'        => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'first_name'  => get_field('first_name', $post->ID),
            'last_name'   => get_field('last_name', $post->ID),
            'thumbnail'   => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            'is_favorite' => (bool) get_field('is_favorite', $post->ID),
            'labels'      => wp_get_post_terms($post->ID, 'person_label', ['fields' => 'names']),
        ];
    }

    /**
     * Format company for summary response
     */
    private function format_company_summary($post) {
        return [
            'id'        => $post->ID,
            'name'      => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            'website'   => get_field('website', $post->ID),
            'labels'    => wp_get_post_terms($post->ID, 'company_label', ['fields' => 'names']),
        ];
    }
    
    /**
     * Format date for response
     */
    private function format_date($post) {
        $related_people = get_field('related_people', $post->ID) ?: [];
        $people_names = [];

        foreach ($related_people as $person) {
            $person_id = is_object($person) ? $person->ID : $person;
            $people_names[] = [
                'id'   => $person_id,
                'name' => html_entity_decode(get_the_title($person_id), ENT_QUOTES, 'UTF-8'),
            ];
        }

        return [
            'id'                   => $post->ID,
            'title'                => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'date_value'           => get_field('date_value', $post->ID),
            'is_recurring'         => (bool) get_field('is_recurring', $post->ID),
            'reminder_days_before' => (int) get_field('reminder_days_before', $post->ID),
            'date_type'            => wp_get_post_terms($post->ID, 'date_type', ['fields' => 'names']),
            'related_people'       => $people_names,
        ];
    }
    
    /**
     * Sideload Gravatar image for a person
     */
    public function sideload_gravatar($request) {
        $person_id = (int) $request->get_param('person_id');
        $email = sanitize_email($request->get_param('email'));
        
        if (empty($email)) {
            return new WP_Error('missing_email', __('Email address is required.', 'personal-crm'), ['status' => 400]);
        }
        
        // Generate Gravatar URL
        $email_hash = md5(strtolower(trim($email)));
        $gravatar_url = sprintf('https://www.gravatar.com/avatar/%s?s=400&d=404', $email_hash);
        
        // Check if Gravatar exists (404 means no gravatar)
        $response = wp_remote_head($gravatar_url);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) === 404) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'No Gravatar found for this email address',
            ]);
        }
        
        // Sideload the image
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        // Download the file
        $tmp = download_url($gravatar_url);
        
        if (is_wp_error($tmp)) {
            return new WP_Error('download_failed', __('Failed to download Gravatar image.', 'personal-crm'), ['status' => 500]);
        }
        
        // Get person's name for filename
        $first_name = get_field('first_name', $person_id) ?: '';
        $last_name = get_field('last_name', $person_id) ?: '';
        $name_slug = sanitize_title(strtolower(trim($first_name . ' ' . $last_name)));
        $filename = !empty($name_slug) ? $name_slug . '.jpg' : 'gravatar-' . $person_id . '.jpg';
        
        // Get file info
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
        ];
        
        // Sideload the file
        $attachment_id = media_handle_sideload($file_array, $person_id, sprintf(__('%s Gravatar', 'personal-crm'), $first_name . ' ' . $last_name));
        
        // Clean up temp file if sideload failed
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return new WP_Error('sideload_failed', __('Failed to sideload Gravatar image.', 'personal-crm'), ['status' => 500]);
        }
        
        // Set as featured image
        set_post_thumbnail($person_id, $attachment_id);
        
        return rest_ensure_response([
            'success' => true,
            'attachment_id' => $attachment_id,
            'thumbnail_url' => get_the_post_thumbnail_url($person_id, 'thumbnail'),
        ]);
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
     * Check if user can edit a company
     */
    public function check_company_edit_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check approval first
        if (!$this->check_user_approved()) {
            return false;
        }
        
        $company_id = $request->get_param('company_id');
        $company = get_post($company_id);
        
        if (!$company || $company->post_type !== 'company') {
            return false;
        }
        
        // Check if user can edit this company
        return current_user_can('edit_post', $company_id);
    }
    
    /**
     * Check if user can edit a person
     */
    public function check_person_edit_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check approval first
        if (!$this->check_user_approved()) {
            return false;
        }
        
        $person_id = $request->get_param('person_id');
        $person = get_post($person_id);
        
        if (!$person || $person->post_type !== 'person') {
            return false;
        }
        
        // Check if user can edit this person
        return current_user_can('edit_post', $person_id);
    }
    
    /**
     * Set company logo (featured image)
     */
    public function set_company_logo($request) {
        $company_id = (int) $request->get_param('company_id');
        $media_id = (int) $request->get_param('media_id');
        
        // Verify company exists
        $company = get_post($company_id);
        if (!$company || $company->post_type !== 'company') {
            return new WP_Error('company_not_found', __('Company not found.', 'personal-crm'), ['status' => 404]);
        }
        
        // Verify media exists
        $media = get_post($media_id);
        if (!$media || $media->post_type !== 'attachment') {
            return new WP_Error('media_not_found', __('Media not found.', 'personal-crm'), ['status' => 404]);
        }
        
        // Set as featured image
        $result = set_post_thumbnail($company_id, $media_id);
        
        if (!$result) {
            return new WP_Error('set_thumbnail_failed', __('Failed to set company logo.', 'personal-crm'), ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'media_id' => $media_id,
            'thumbnail_url' => get_the_post_thumbnail_url($company_id, 'thumbnail'),
            'full_url' => get_the_post_thumbnail_url($company_id, 'full'),
        ]);
    }
    
    /**
     * Upload person photo with proper filename based on person's name
     */
    public function upload_person_photo($request) {
        $person_id = (int) $request->get_param('person_id');
        
        // Verify person exists
        $person = get_post($person_id);
        if (!$person || $person->post_type !== 'person') {
            return new WP_Error('person_not_found', __('Person not found.', 'personal-crm'), ['status' => 404]);
        }
        
        // Check for uploaded file
        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new WP_Error('no_file', __('No file uploaded.', 'personal-crm'), ['status' => 400]);
        }
        
        $file = $files['file'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_type', __('Invalid file type. Please upload an image.', 'personal-crm'), ['status' => 400]);
        }
        
        // Get person's name for filename
        $first_name = get_field('first_name', $person_id) ?: '';
        $last_name = get_field('last_name', $person_id) ?: '';
        $name_slug = sanitize_title(strtolower(trim($first_name . ' ' . $last_name)));
        
        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        
        // Generate filename
        $filename = !empty($name_slug) ? $name_slug . '.' . $extension : 'person-' . $person_id . '.' . $extension;
        
        // Load required files
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        // Prepare file array with new filename
        $file_array = [
            'name'     => $filename,
            'type'     => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error'    => $file['error'],
            'size'     => $file['size'],
        ];
        
        // Handle the upload
        $attachment_id = media_handle_sideload($file_array, $person_id, sprintf('%s %s', $first_name, $last_name));
        
        if (is_wp_error($attachment_id)) {
            return new WP_Error('upload_failed', $attachment_id->get_error_message(), ['status' => 500]);
        }
        
        // Set as featured image
        set_post_thumbnail($person_id, $attachment_id);
        
        return rest_ensure_response([
            'success'       => true,
            'attachment_id' => $attachment_id,
            'filename'      => $filename,
            'thumbnail_url' => get_the_post_thumbnail_url($person_id, 'thumbnail'),
            'full_url'      => get_the_post_thumbnail_url($person_id, 'full'),
        ]);
    }
    
    /**
     * Upload company logo with proper filename based on company name
     */
    public function upload_company_logo($request) {
        $company_id = (int) $request->get_param('company_id');
        
        // Verify company exists
        $company = get_post($company_id);
        if (!$company || $company->post_type !== 'company') {
            return new WP_Error('company_not_found', __('Company not found.', 'personal-crm'), ['status' => 404]);
        }
        
        // Check for uploaded file
        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new WP_Error('no_file', __('No file uploaded.', 'personal-crm'), ['status' => 400]);
        }
        
        $file = $files['file'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_type', __('Invalid file type. Please upload an image.', 'personal-crm'), ['status' => 400]);
        }
        
        // Get company name for filename
        $company_name = $company->post_title;
        $name_slug = sanitize_title(strtolower(trim($company_name)));
        
        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        
        // Generate filename
        $filename = !empty($name_slug) ? $name_slug . '-logo.' . $extension : 'company-' . $company_id . '.' . $extension;
        
        // Load required files
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        // Prepare file array with new filename
        $file_array = [
            'name'     => $filename,
            'type'     => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error'    => $file['error'],
            'size'     => $file['size'],
        ];
        
        // Handle the upload
        $attachment_id = media_handle_sideload($file_array, $company_id, sprintf('%s Logo', $company_name));
        
        if (is_wp_error($attachment_id)) {
            return new WP_Error('upload_failed', $attachment_id->get_error_message(), ['status' => 500]);
        }
        
        // Set as featured image
        set_post_thumbnail($company_id, $attachment_id);
        
        return rest_ensure_response([
            'success'       => true,
            'attachment_id' => $attachment_id,
            'filename'      => $filename,
            'thumbnail_url' => get_the_post_thumbnail_url($company_id, 'thumbnail'),
            'full_url'      => get_the_post_thumbnail_url($company_id, 'full'),
        ]);
    }
    
    /**
     * Check if user is admin
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
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
}
