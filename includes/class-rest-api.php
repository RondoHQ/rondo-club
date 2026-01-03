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
    }
    
    /**
     * Register ACF fields to REST API
     */
    public function register_acf_fields() {
        // Expose ACF fields in REST API
        if (function_exists('acf_get_field_groups')) {
            // ACF Pro automatically exposes fields to REST if show_in_rest is enabled on CPT
            // This function can be extended for custom handling
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
        $company_id = $request->get_param('company_id');
        $user_id = get_current_user_id();
        
        // Apply access control - get accessible person IDs
        $access_control = new PRM_Access_Control();
        $accessible_ids = $access_control->get_accessible_post_ids('person', $user_id);
        
        if (empty($accessible_ids)) {
            return rest_ensure_response([
                'current' => [],
                'former'  => [],
            ]);
        }
        
        // Query only accessible people with this company in their work history
        $people = get_posts([
            'post_type'      => 'person',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'post__in'       => $accessible_ids,
            'meta_query'     => [
                [
                    'key'     => 'work_history_%_company',
                    'value'   => $company_id,
                    'compare' => '=',
                ],
            ],
        ]);
        
        $current = [];
        $former = [];
        
        foreach ($people as $person) {
            $work_history = get_field('work_history', $person->ID) ?: [];
            
            $person_data = $this->format_person_summary($person);
            
            // Find the relevant work history entry
            foreach ($work_history as $job) {
                if (isset($job['company']) && $job['company'] == $company_id) {
                    $person_data['job_title'] = $job['job_title'] ?? '';
                    $person_data['start_date'] = $job['start_date'] ?? '';
                    $person_data['end_date'] = $job['end_date'] ?? '';
                    
                    if (empty($job['end_date']) || !empty($job['is_current'])) {
                        $current[] = $person_data;
                    } else {
                        $former[] = $person_data;
                    }
                    break;
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
}
