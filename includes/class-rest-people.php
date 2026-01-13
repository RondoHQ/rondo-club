<?php
/**
 * People REST API Endpoints
 *
 * Handles REST API endpoints related to people domain.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_REST_People extends PRM_REST_Base {

    /**
     * Constructor
     *
     * Register routes and filters for people endpoints.
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);

        // Expand relationship data in person REST responses
        add_filter('rest_prepare_person', [$this, 'expand_person_relationships'], 10, 3);

        // Add computed fields (is_deceased) to person REST responses
        add_filter('rest_prepare_person', [$this, 'add_person_computed_fields'], 20, 3);
    }

    /**
     * Register custom REST routes for people domain
     */
    public function register_routes() {
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
    }

    /**
     * Get dates related to a person
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing formatted dates.
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
     * Sideload Gravatar image for a person
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with attachment info or error.
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
     * Upload person photo with proper filename based on person's name
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with attachment info or error.
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
     * Expand relationship data with person names and relationship type names
     *
     * @param WP_REST_Response $response The REST response object.
     * @param WP_Post $post The post object.
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Modified response with expanded relationships.
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
     * Add computed fields to person REST response
     * This includes is_deceased and birth_year
     *
     * @param WP_REST_Response $response The REST response object.
     * @param WP_Post $post The post object.
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Modified response with computed fields.
     */
    public function add_person_computed_fields($response, $post, $request) {
        $data = $response->get_data();

        // Get all dates for this person to compute deceased status and birth year
        $person_dates = get_posts([
            'post_type'      => 'important_date',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'related_people',
                    'value'   => '"' . $post->ID . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        $data['is_deceased'] = false;
        $data['birth_year'] = null;

        foreach ($person_dates as $date_post) {
            $date_types = wp_get_post_terms($date_post->ID, 'date_type', ['fields' => 'slugs']);

            // Check for deceased status
            if (in_array('died', $date_types)) {
                $data['is_deceased'] = true;
            }

            // Check for birthday and extract year (only if year is known)
            if (in_array('birthday', $date_types)) {
                $year_unknown = get_field('year_unknown', $date_post->ID);
                if (!$year_unknown) {
                    $date_value = get_field('date_value', $date_post->ID);
                    if ($date_value) {
                        $year = (int) date('Y', strtotime($date_value));
                        if ($year > 0) {
                            $data['birth_year'] = $year;
                        }
                    }
                }
            }
        }

        $response->set_data($data);

        return $response;
    }
}
