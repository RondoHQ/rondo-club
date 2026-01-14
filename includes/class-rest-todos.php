<?php
/**
 * REST API Endpoints for Todo Custom Post Type
 *
 * Provides CRUD operations for todos via the REST API,
 * replacing the comment-based todo endpoints.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_REST_Todos extends PRM_REST_Base {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Person-scoped endpoints
        register_rest_route('prm/v1', '/people/(?P<person_id>\d+)/todos', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_person_todos'],
                'permission_callback' => [$this, 'check_person_access'],
                'args'                => [
                    'person_id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_person_todo'],
                'permission_callback' => [$this, 'check_person_access'],
                'args'                => [
                    'person_id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
        ]);

        // Global endpoints
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
                'awaiting_response' => [
                    'default'           => null,
                    'validate_callback' => function($param) {
                        return $param === null || is_bool($param) || $param === 'true' || $param === 'false' || $param === '1' || $param === '0';
                    },
                ],
            ],
        ]);

        register_rest_route('prm/v1', '/todos/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_todo'],
                'permission_callback' => [$this, 'check_todo_access'],
                'args'                => [
                    'id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_todo'],
                'permission_callback' => [$this, 'check_todo_access'],
                'args'                => [
                    'id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_todo'],
                'permission_callback' => [$this, 'check_todo_access'],
                'args'                => [
                    'id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
        ]);
    }

    /**
     * Check if user can access a todo
     *
     * Permission callback for single-todo operations.
     * Verifies user is approved and can access the todo via access control.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if user can access the todo, false otherwise.
     */
    public function check_todo_access($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        // Check approval first
        if (!$this->check_user_approved()) {
            return false;
        }

        $todo_id = $request->get_param('id');
        $todo = get_post($todo_id);

        if (!$todo || $todo->post_type !== 'prm_todo') {
            return false;
        }

        // Use access control to check if user can access this todo
        $access_control = new PRM_Access_Control();
        return $access_control->user_can_access_post($todo_id);
    }

    /**
     * Get todos for a specific person
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing todos for the person.
     */
    public function get_person_todos($request) {
        $person_id = (int) $request->get_param('person_id');

        $todos = get_posts([
            'post_type'      => 'prm_todo',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'related_person',
                    'value'   => $person_id,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $formatted = array_map([$this, 'format_todo'], $todos);

        return rest_ensure_response($formatted);
    }

    /**
     * Create a todo linked to a person
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing created todo or error.
     */
    public function create_person_todo($request) {
        $person_id = (int) $request->get_param('person_id');
        $content = sanitize_textarea_field($request->get_param('content'));
        $due_date = sanitize_text_field($request->get_param('due_date'));
        $is_completed = $request->get_param('is_completed');
        $awaiting_response = $request->get_param('awaiting_response');

        if (empty($content)) {
            return new WP_Error('empty_content', __('Todo content is required.', 'personal-crm'), ['status' => 400]);
        }

        // Create the todo post
        $post_id = wp_insert_post([
            'post_type'   => 'prm_todo',
            'post_title'  => $content,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($post_id)) {
            return new WP_Error('create_failed', __('Failed to create todo.', 'personal-crm'), ['status' => 500]);
        }

        // Save ACF fields
        update_field('related_person', $person_id, $post_id);
        update_field('is_completed', $is_completed ? true : false, $post_id);

        if (!empty($due_date)) {
            update_field('due_date', $due_date, $post_id);
        }

        // Handle awaiting_response with auto-timestamp
        if ($awaiting_response) {
            update_field('awaiting_response', true, $post_id);
            update_field('awaiting_response_since', gmdate('Y-m-d H:i:s'), $post_id);
        } else {
            update_field('awaiting_response', false, $post_id);
            update_field('awaiting_response_since', '', $post_id);
        }

        // Set default visibility to private
        update_field('visibility', 'private', $post_id);

        $todo = get_post($post_id);

        return rest_ensure_response($this->format_todo($todo));
    }

    /**
     * Get all todos for the current user
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing all accessible todos.
     */
    public function get_all_todos($request) {
        $include_completed = $request->get_param('completed');
        $awaiting_response = $request->get_param('awaiting_response');

        // Normalize boolean parameters
        if ($include_completed === 'true' || $include_completed === '1') {
            $include_completed = true;
        } else {
            $include_completed = false;
        }

        // Normalize awaiting_response parameter (null means no filter)
        if ($awaiting_response === 'true' || $awaiting_response === '1') {
            $awaiting_response = true;
        } elseif ($awaiting_response === 'false' || $awaiting_response === '0') {
            $awaiting_response = false;
        } else {
            $awaiting_response = null;
        }

        // Build query args - access control filter will handle visibility
        $args = [
            'post_type'      => 'prm_todo',
            'posts_per_page' => 100, // Reasonable limit
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Build meta_query for filters
        $meta_queries = [];

        // Filter by completion status
        if (!$include_completed) {
            $meta_queries[] = [
                'relation' => 'OR',
                [
                    'key'     => 'is_completed',
                    'value'   => '0',
                    'compare' => '=',
                ],
                [
                    'key'     => 'is_completed',
                    'value'   => '',
                    'compare' => '=',
                ],
                [
                    'key'     => 'is_completed',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        }

        // Filter by awaiting_response status
        if ($awaiting_response === true) {
            $meta_queries[] = [
                'key'     => 'awaiting_response',
                'value'   => '1',
                'compare' => '=',
            ];
        } elseif ($awaiting_response === false) {
            $meta_queries[] = [
                'relation' => 'OR',
                [
                    'key'     => 'awaiting_response',
                    'value'   => '0',
                    'compare' => '=',
                ],
                [
                    'key'     => 'awaiting_response',
                    'value'   => '',
                    'compare' => '=',
                ],
                [
                    'key'     => 'awaiting_response',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        }

        // Combine meta queries with AND relation
        if (count($meta_queries) > 0) {
            if (count($meta_queries) === 1) {
                $args['meta_query'] = $meta_queries[0];
            } else {
                $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_queries);
            }
        }

        $todos = get_posts($args);
        $formatted = array_map([$this, 'format_todo'], $todos);

        // Sort by due date (earliest first), todos without due date at end
        usort($formatted, function($a, $b) {
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

        return rest_ensure_response($formatted);
    }

    /**
     * Get a single todo
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing todo or error.
     */
    public function get_todo($request) {
        $todo_id = (int) $request->get_param('id');
        $todo = get_post($todo_id);

        if (!$todo || $todo->post_type !== 'prm_todo') {
            return new WP_Error('not_found', __('Todo not found.', 'personal-crm'), ['status' => 404]);
        }

        return rest_ensure_response($this->format_todo($todo));
    }

    /**
     * Update a todo
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing updated todo or error.
     */
    public function update_todo($request) {
        $todo_id = (int) $request->get_param('id');
        $content = $request->get_param('content');
        $due_date = $request->get_param('due_date');
        $is_completed = $request->get_param('is_completed');
        $awaiting_response = $request->get_param('awaiting_response');

        $todo = get_post($todo_id);

        if (!$todo || $todo->post_type !== 'prm_todo') {
            return new WP_Error('not_found', __('Todo not found.', 'personal-crm'), ['status' => 404]);
        }

        // Update post title if content provided
        if ($content !== null) {
            $content = sanitize_textarea_field($content);
            wp_update_post([
                'ID'         => $todo_id,
                'post_title' => $content,
            ]);
        }

        // Update ACF fields
        if ($is_completed !== null) {
            update_field('is_completed', $is_completed ? true : false, $todo_id);
        }

        if ($due_date !== null) {
            if (empty($due_date)) {
                update_field('due_date', '', $todo_id);
            } else {
                update_field('due_date', sanitize_text_field($due_date), $todo_id);
            }
        }

        // Handle awaiting_response with auto-timestamp
        if ($awaiting_response !== null) {
            $current_awaiting = (bool) get_field('awaiting_response', $todo_id);
            $new_awaiting = (bool) $awaiting_response;

            if ($new_awaiting && !$current_awaiting) {
                // Changing from false to true: set timestamp
                update_field('awaiting_response', true, $todo_id);
                update_field('awaiting_response_since', gmdate('Y-m-d H:i:s'), $todo_id);
            } elseif (!$new_awaiting && $current_awaiting) {
                // Changing from true to false: clear timestamp
                update_field('awaiting_response', false, $todo_id);
                update_field('awaiting_response_since', '', $todo_id);
            }
            // If no change, leave as-is
        }

        // Refresh the post object
        $todo = get_post($todo_id);

        return rest_ensure_response($this->format_todo($todo));
    }

    /**
     * Delete a todo
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response indicating success or error.
     */
    public function delete_todo($request) {
        $todo_id = (int) $request->get_param('id');
        $todo = get_post($todo_id);

        if (!$todo || $todo->post_type !== 'prm_todo') {
            return new WP_Error('not_found', __('Todo not found.', 'personal-crm'), ['status' => 404]);
        }

        $result = wp_delete_post($todo_id, true); // Force delete (bypass trash)

        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete todo.', 'personal-crm'), ['status' => 500]);
        }

        return rest_ensure_response(['deleted' => true]);
    }

    /**
     * Format a todo post for REST response
     *
     * Matches the response format from the comment-based todo system.
     *
     * @param WP_Post $post The todo post object.
     * @return array Formatted todo data.
     */
    private function format_todo($post) {
        $person_id = (int) get_field('related_person', $post->ID);
        $person_name = '';
        $person_thumbnail = '';

        if ($person_id) {
            $person_name = get_the_title($person_id);
            $person_thumbnail = get_the_post_thumbnail_url($person_id, 'thumbnail');
        }

        $is_completed = get_field('is_completed', $post->ID);
        $due_date = get_field('due_date', $post->ID);
        $awaiting_response = get_field('awaiting_response', $post->ID);
        $awaiting_response_since = get_field('awaiting_response_since', $post->ID);

        return [
            'id'                      => $post->ID,
            'type'                    => 'todo',
            'content'                 => $this->sanitize_text($post->post_title),
            'person_id'               => $person_id,
            'person_name'             => $this->sanitize_text($person_name),
            'person_thumbnail'        => $this->sanitize_url($person_thumbnail),
            'author_id'               => (int) $post->post_author,
            'created'                 => $post->post_date,
            'is_completed'            => (bool) $is_completed,
            'due_date'                => $due_date ?: null,
            'awaiting_response'       => (bool) $awaiting_response,
            'awaiting_response_since' => $awaiting_response_since ?: null,
        ];
    }
}
