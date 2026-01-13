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

        // Sharing endpoints
        register_rest_route('prm/v1', '/people/(?P<id>\d+)/shares', [
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

        register_rest_route('prm/v1', '/people/(?P<id>\d+)/shares/(?P<user_id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'remove_share'],
            'permission_callback' => [$this, 'check_post_owner'],
        ]);

        // Bulk update endpoint
        register_rest_route('prm/v1', '/people/bulk-update', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'bulk_update_people'],
            'permission_callback' => [$this, 'check_bulk_update_permission'],
            'args'                => [
                'ids' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        if (!is_array($param) || empty($param)) {
                            return false;
                        }
                        foreach ($param as $id) {
                            if (!is_numeric($id)) {
                                return false;
                            }
                        }
                        return true;
                    },
                    'sanitize_callback' => function($param) {
                        return array_map('intval', $param);
                    },
                ],
                'updates' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        if (!is_array($param) || empty($param)) {
                            return false;
                        }
                        // Must have at least visibility or assigned_workspaces
                        if (!isset($param['visibility']) && !isset($param['assigned_workspaces'])) {
                            return false;
                        }
                        // Validate visibility if provided
                        if (isset($param['visibility'])) {
                            $valid_visibilities = ['private', 'workspace', 'shared'];
                            if (!in_array($param['visibility'], $valid_visibilities, true)) {
                                return false;
                            }
                        }
                        // Validate assigned_workspaces if provided
                        if (isset($param['assigned_workspaces'])) {
                            if (!is_array($param['assigned_workspaces'])) {
                                return false;
                            }
                            foreach ($param['assigned_workspaces'] as $ws_id) {
                                if (!is_numeric($ws_id)) {
                                    return false;
                                }
                            }
                        }
                        return true;
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

        if (!$post || $post->post_type !== 'person') {
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

    /**
     * Check if current user can bulk update the specified people
     *
     * Verifies that the current user owns all posts in the request.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if user owns all posts, WP_Error otherwise.
     */
    public function check_bulk_update_permission($request) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('You must be logged in to perform this action.', 'personal-crm'),
                ['status' => 401]
            );
        }

        $ids = $request->get_param('ids');
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('administrator');

        foreach ($ids as $post_id) {
            $post = get_post($post_id);

            if (!$post || $post->post_type !== 'person') {
                return new WP_Error(
                    'rest_invalid_id',
                    sprintf(__('Person with ID %d not found.', 'personal-crm'), $post_id),
                    ['status' => 404]
                );
            }

            // Must be post author or admin
            if ((int) $post->post_author !== $current_user_id && !$is_admin) {
                return new WP_Error(
                    'rest_forbidden',
                    sprintf(__('You do not have permission to update person with ID %d.', 'personal-crm'), $post_id),
                    ['status' => 403]
                );
            }
        }

        return true;
    }

    /**
     * Bulk update multiple people
     *
     * Updates visibility and/or workspace assignments for multiple people at once.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response with success/failure details.
     */
    public function bulk_update_people($request) {
        $ids = $request->get_param('ids');
        $updates = $request->get_param('updates');

        $updated = [];
        $failed = [];

        foreach ($ids as $post_id) {
            try {
                // Update visibility if provided
                if (isset($updates['visibility'])) {
                    $result = PRM_Visibility::set_visibility($post_id, $updates['visibility']);
                    if (!$result) {
                        $failed[] = [
                            'id'    => $post_id,
                            'error' => __('Failed to update visibility.', 'personal-crm'),
                        ];
                        continue;
                    }
                }

                // Update workspace assignments if provided
                if (isset($updates['assigned_workspaces'])) {
                    $workspace_ids = array_map('intval', $updates['assigned_workspaces']);

                    // Convert workspace post IDs to term IDs
                    $term_ids = [];
                    foreach ($workspace_ids as $workspace_id) {
                        $term_slug = 'workspace-' . $workspace_id;
                        $term = get_term_by('slug', $term_slug, 'workspace_access');

                        if ($term && !is_wp_error($term)) {
                            $term_ids[] = $term->term_id;
                        }
                    }

                    // Set the terms on the post
                    wp_set_object_terms($post_id, $term_ids, 'workspace_access');

                    // Update the ACF field with term IDs
                    update_field('_assigned_workspaces', $term_ids, $post_id);
                }

                $updated[] = $post_id;
            } catch (Exception $e) {
                $failed[] = [
                    'id'    => $post_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return rest_ensure_response([
            'success' => empty($failed),
            'updated' => $updated,
            'failed'  => $failed,
        ]);
    }
}
