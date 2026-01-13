<?php
/**
 * Companies REST API Endpoints
 *
 * Handles REST API endpoints related to companies domain.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_REST_Companies extends PRM_REST_Base {

    /**
     * Constructor
     *
     * Register routes for company endpoints.
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register custom REST routes for companies domain
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

        // Sharing endpoints
        register_rest_route('prm/v1', '/companies/(?P<id>\d+)/shares', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_shares'],
                'permission_callback' => [$this, 'check_post_owner'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'add_share'],
                'permission_callback' => [$this, 'check_post_owner'],
            ],
        ]);

        register_rest_route('prm/v1', '/companies/(?P<id>\d+)/shares/(?P<user_id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'remove_share'],
            'permission_callback' => [$this, 'check_post_owner'],
        ]);
    }

    /**
     * Get people who work/worked at a company
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing current and former employees.
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
     * Set company logo (featured image) by media ID
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with logo info or error.
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
     * Upload company logo with proper filename based on company name
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with attachment info or error.
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
     * Check if current user owns this post (can share it)
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if user owns the post or is admin.
     */
    public function check_post_owner($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        $post_id = $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'company') {
            return false;
        }

        // Must be post author or admin
        return (int) $post->post_author === get_current_user_id() || current_user_can('administrator');
    }

    /**
     * Get list of users this post is shared with
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing share list.
     */
    public function get_shares($request) {
        $post_id = $request->get_param('id');
        $shares = get_field('_shared_with', $post_id) ?: [];

        $result = [];
        foreach ($shares as $share) {
            $user = get_user_by('ID', $share['user_id']);
            if ($user) {
                $result[] = [
                    'user_id'      => (int) $share['user_id'],
                    'display_name' => $user->display_name,
                    'email'        => $user->user_email,
                    'avatar_url'   => get_avatar_url($user->ID, ['size' => 48]),
                    'permission'   => $share['permission'],
                ];
            }
        }

        return rest_ensure_response($result);
    }

    /**
     * Share post with a user
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with success or error.
     */
    public function add_share($request) {
        $post_id = $request->get_param('id');
        $user_id = (int) $request->get_param('user_id');
        $permission = $request->get_param('permission') ?: 'view';

        // Validate user exists
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('User not found.', 'personal-crm'), ['status' => 404]);
        }

        // Can't share with yourself
        if ($user_id === get_current_user_id()) {
            return new WP_Error('invalid_share', __('Cannot share with yourself.', 'personal-crm'), ['status' => 400]);
        }

        // Get current shares
        $shares = get_field('_shared_with', $post_id) ?: [];

        // Check if already shared
        foreach ($shares as $key => $share) {
            if ((int) $share['user_id'] === $user_id) {
                // Update permission
                $shares[$key]['permission'] = $permission;
                update_field('_shared_with', $shares, $post_id);
                return rest_ensure_response(['success' => true, 'message' => __('Share updated.', 'personal-crm')]);
            }
        }

        // Add new share
        $shares[] = [
            'user_id'    => $user_id,
            'permission' => $permission,
        ];
        update_field('_shared_with', $shares, $post_id);

        return rest_ensure_response(['success' => true, 'message' => __('Shared successfully.', 'personal-crm')]);
    }

    /**
     * Remove share from a user
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response with success status.
     */
    public function remove_share($request) {
        $post_id = $request->get_param('id');
        $user_id = (int) $request->get_param('user_id');

        $shares = get_field('_shared_with', $post_id) ?: [];
        $shares = array_filter($shares, function($share) use ($user_id) {
            return (int) $share['user_id'] !== $user_id;
        });
        $shares = array_values($shares); // Re-index

        update_field('_shared_with', $shares, $post_id);

        return rest_ensure_response(['success' => true, 'message' => __('Share removed.', 'personal-crm')]);
    }
}
