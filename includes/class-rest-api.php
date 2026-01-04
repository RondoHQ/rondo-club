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
            'permission_callback' => 'is_user_logged_in',
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
            'permission_callback' => 'is_user_logged_in',
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
            'permission_callback' => 'is_user_logged_in',
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
        register_rest_route('prm/v1', '/user/me', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_current_user'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Set company logo (featured image)
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
     * Check if user can access a person
     */
    public function check_person_access($request) {
        if (!is_user_logged_in()) {
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
        
        // Count accessible posts
        $access_control = new PRM_Access_Control();
        
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
                'total_people'    => wp_count_posts('person')->publish,
                'total_companies' => wp_count_posts('company')->publish,
                'total_dates'     => wp_count_posts('important_date')->publish,
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
        
        return rest_ensure_response([
            'id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar_url' => $avatar_url,
            'is_admin' => $is_admin,
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
        
        $company_id = $request->get_param('company_id');
        $company = get_post($company_id);
        
        if (!$company || $company->post_type !== 'company') {
            return false;
        }
        
        // Check if user can edit this company
        return current_user_can('edit_post', $company_id);
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
}
