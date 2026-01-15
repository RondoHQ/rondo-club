<?php
/**
 * Calendar REST API Endpoints
 *
 * Handles REST API endpoints for calendar connection management,
 * OAuth flows, and calendar event operations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_REST_Calendar extends PRM_REST_Base {

    /**
     * Constructor
     *
     * Register routes for calendar endpoints.
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register custom REST routes for calendar domain
     */
    public function register_routes() {
        // ===== Connection CRUD endpoints =====

        // GET /prm/v1/calendar/connections - List user's connections
        register_rest_route('prm/v1', '/calendar/connections', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_connections'],
            'permission_callback' => [$this, 'check_user_approved'],
        ]);

        // POST /prm/v1/calendar/connections - Add new connection
        register_rest_route('prm/v1', '/calendar/connections', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_connection'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'provider' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return in_array($param, ['google', 'caldav'], true);
                    },
                ],
                'name' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'calendar_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'credentials' => [
                    'required'          => false,
                    'type'              => 'object',
                ],
                'sync_enabled' => [
                    'required'          => false,
                    'type'              => 'boolean',
                    'default'           => true,
                ],
                'auto_log' => [
                    'required'          => false,
                    'type'              => 'boolean',
                    'default'           => true,
                ],
                'sync_from_days' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 90,
                ],
            ],
        ]);

        // GET /prm/v1/calendar/connections/(?P<id>[a-z0-9_]+) - Get single connection
        register_rest_route('prm/v1', '/calendar/connections/(?P<id>[a-z0-9_]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_connection'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-z0-9_]+$/', $param);
                    },
                ],
            ],
        ]);

        // PUT /prm/v1/calendar/connections/(?P<id>[a-z0-9_]+) - Update connection
        register_rest_route('prm/v1', '/calendar/connections/(?P<id>[a-z0-9_]+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_connection'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-z0-9_]+$/', $param);
                    },
                ],
                'name' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'calendar_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'credentials' => [
                    'required'          => false,
                    'type'              => 'object',
                ],
                'sync_enabled' => [
                    'required'          => false,
                    'type'              => 'boolean',
                ],
                'auto_log' => [
                    'required'          => false,
                    'type'              => 'boolean',
                ],
                'sync_from_days' => [
                    'required'          => false,
                    'type'              => 'integer',
                ],
            ],
        ]);

        // DELETE /prm/v1/calendar/connections/(?P<id>[a-z0-9_]+) - Delete connection
        register_rest_route('prm/v1', '/calendar/connections/(?P<id>[a-z0-9_]+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_connection'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-z0-9_]+$/', $param);
                    },
                ],
            ],
        ]);

        // POST /prm/v1/calendar/connections/(?P<id>[a-z0-9_]+)/sync - Trigger sync
        register_rest_route('prm/v1', '/calendar/connections/(?P<id>[a-z0-9_]+)/sync', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'trigger_sync'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-z0-9_]+$/', $param);
                    },
                ],
            ],
        ]);

        // ===== OAuth endpoints (stubs for Phase 48) =====

        // GET /prm/v1/calendar/auth/google - Initiate OAuth
        register_rest_route('prm/v1', '/calendar/auth/google', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'google_auth_init'],
            'permission_callback' => [$this, 'check_user_approved'],
        ]);

        // GET /prm/v1/calendar/auth/google/callback - OAuth callback
        register_rest_route('prm/v1', '/calendar/auth/google/callback', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'google_auth_callback'],
            'permission_callback' => '__return_true', // Public for OAuth redirect
        ]);

        // POST /prm/v1/calendar/auth/caldav/test - Test CalDAV credentials
        register_rest_route('prm/v1', '/calendar/auth/caldav/test', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'test_caldav'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'url' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'username' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password' => [
                    'required'          => true,
                    'type'              => 'string',
                ],
            ],
        ]);

        // ===== Events and meetings endpoints (stubs for Phase 51+) =====

        // GET /prm/v1/calendar/events - List cached events
        register_rest_route('prm/v1', '/calendar/events', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_events'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'from' => [
                    'required'          => false,
                    'type'              => 'string',
                    'format'            => 'date',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'to' => [
                    'required'          => false,
                    'type'              => 'string',
                    'format'            => 'date',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'person_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /prm/v1/people/(?P<person_id>\d+)/meetings - Person meetings
        register_rest_route('prm/v1', '/people/(?P<person_id>\d+)/meetings', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_person_meetings'],
            'permission_callback' => [$this, 'check_person_access'],
            'args'                => [
                'person_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
                'upcoming' => [
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => true,
                ],
                'past' => [
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => true,
                ],
                'limit' => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 10,
                ],
            ],
        ]);

        // POST /prm/v1/calendar/events/(?P<id>\d+)/log - Log as activity
        register_rest_route('prm/v1', '/calendar/events/(?P<id>\d+)/log', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'log_event_as_activity'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }

    /**
     * Get all connections for the current user
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing array of connections.
     */
    public function get_connections($request) {
        $user_id = get_current_user_id();
        $connections = PRM_Calendar_Connections::get_user_connections($user_id);

        // Remove sensitive credentials from response
        $safe_connections = array_map(function($conn) {
            unset($conn['credentials']);
            return $conn;
        }, $connections);

        return rest_ensure_response($safe_connections);
    }

    /**
     * Create a new calendar connection
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing new connection ID or error.
     */
    public function create_connection($request) {
        $user_id = get_current_user_id();
        $data = $request->get_json_params();

        // Validate required fields
        if (empty($data['provider']) || !in_array($data['provider'], ['google', 'caldav'], true)) {
            return new WP_Error('invalid_provider', __('Invalid provider. Must be "google" or "caldav".', 'personal-crm'), ['status' => 400]);
        }
        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Connection name is required.', 'personal-crm'), ['status' => 400]);
        }

        // Encrypt credentials if provided
        $credentials = '';
        if (!empty($data['credentials']) && is_array($data['credentials'])) {
            $credentials = PRM_Credential_Encryption::encrypt($data['credentials']);
        }

        // Build connection data
        $connection = [
            'provider'       => sanitize_text_field($data['provider']),
            'name'           => sanitize_text_field($data['name']),
            'calendar_id'    => sanitize_text_field($data['calendar_id'] ?? ''),
            'credentials'    => $credentials,
            'sync_enabled'   => isset($data['sync_enabled']) ? (bool) $data['sync_enabled'] : true,
            'auto_log'       => isset($data['auto_log']) ? (bool) $data['auto_log'] : true,
            'sync_from_days' => isset($data['sync_from_days']) ? absint($data['sync_from_days']) : 90,
            'last_sync'      => null,
            'last_error'     => null,
        ];

        $id = PRM_Calendar_Connections::add_connection($user_id, $connection);

        return rest_ensure_response([
            'id'      => $id,
            'message' => __('Connection created.', 'personal-crm'),
        ]);
    }

    /**
     * Get a single connection by ID
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response containing connection data or error.
     */
    public function get_connection($request) {
        $user_id = get_current_user_id();
        $id = $request->get_param('id');
        $connection = PRM_Calendar_Connections::get_connection($user_id, $id);

        if (!$connection) {
            return new WP_Error('not_found', __('Connection not found.', 'personal-crm'), ['status' => 404]);
        }

        // Remove sensitive credentials from response
        unset($connection['credentials']);

        return rest_ensure_response($connection);
    }

    /**
     * Update an existing connection
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with success message or error.
     */
    public function update_connection($request) {
        $user_id = get_current_user_id();
        $id = $request->get_param('id');
        $data = $request->get_json_params();

        $connection = PRM_Calendar_Connections::get_connection($user_id, $id);
        if (!$connection) {
            return new WP_Error('not_found', __('Connection not found.', 'personal-crm'), ['status' => 404]);
        }

        // Sanitize updatable fields
        $updates = [];
        if (isset($data['name'])) {
            $updates['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['sync_enabled'])) {
            $updates['sync_enabled'] = (bool) $data['sync_enabled'];
        }
        if (isset($data['auto_log'])) {
            $updates['auto_log'] = (bool) $data['auto_log'];
        }
        if (isset($data['sync_from_days'])) {
            $updates['sync_from_days'] = absint($data['sync_from_days']);
        }
        if (isset($data['calendar_id'])) {
            $updates['calendar_id'] = sanitize_text_field($data['calendar_id']);
        }

        // Handle credential updates (re-encrypt)
        if (!empty($data['credentials']) && is_array($data['credentials'])) {
            $updates['credentials'] = PRM_Credential_Encryption::encrypt($data['credentials']);
        }

        PRM_Calendar_Connections::update_connection($user_id, $id, $updates);

        return rest_ensure_response(['message' => __('Connection updated.', 'personal-crm')]);
    }

    /**
     * Delete a connection
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with success message or error.
     */
    public function delete_connection($request) {
        $user_id = get_current_user_id();
        $id = $request->get_param('id');

        $connection = PRM_Calendar_Connections::get_connection($user_id, $id);
        if (!$connection) {
            return new WP_Error('not_found', __('Connection not found.', 'personal-crm'), ['status' => 404]);
        }

        PRM_Calendar_Connections::delete_connection($user_id, $id);

        return rest_ensure_response(['message' => __('Connection deleted.', 'personal-crm')]);
    }

    /**
     * Trigger manual sync for a connection
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with sync results or error.
     */
    public function trigger_sync($request) {
        $user_id = get_current_user_id();
        $connection_id = $request->get_param('id');

        // Get connection
        $connection = PRM_Calendar_Connections::get_connection($user_id, $connection_id);
        if (!$connection) {
            return new WP_Error('not_found', __('Connection not found.', 'personal-crm'), ['status' => 404]);
        }

        // Check provider
        $provider = $connection['provider'] ?? '';
        if ($provider === 'caldav') {
            return new WP_Error(
                'not_implemented',
                __('CalDAV sync is not yet implemented.', 'personal-crm'),
                ['status' => 501]
            );
        }

        if ($provider !== 'google') {
            return new WP_Error(
                'invalid_provider',
                __('Unknown calendar provider.', 'personal-crm'),
                ['status' => 400]
            );
        }

        // Add user_id to connection for token refresh
        $connection['user_id'] = $user_id;

        try {
            $result = PRM_Google_Calendar_Provider::sync($user_id, $connection);

            // Update last_sync timestamp and clear error
            PRM_Calendar_Connections::update_connection($user_id, $connection_id, [
                'last_sync'  => current_time('c'),
                'last_error' => null,
            ]);

            return rest_ensure_response([
                'message' => __('Sync completed successfully.', 'personal-crm'),
                'created' => $result['created'],
                'updated' => $result['updated'],
                'total'   => $result['total'],
            ]);
        } catch (Exception $e) {
            // Update last_error
            PRM_Calendar_Connections::update_connection($user_id, $connection_id, [
                'last_error' => $e->getMessage(),
            ]);

            return new WP_Error('sync_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Initiate Google OAuth flow
     *
     * Returns the authorization URL for the frontend to redirect to.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with auth_url or error.
     */
    public function google_auth_init($request) {
        // Check if Google OAuth is configured
        if (!PRM_Google_OAuth::is_configured()) {
            return new WP_Error(
                'not_configured',
                __('Google Calendar integration is not configured. Please add GOOGLE_CALENDAR_CLIENT_ID and GOOGLE_CALENDAR_CLIENT_SECRET to wp-config.php.', 'personal-crm'),
                ['status' => 400]
            );
        }

        $user_id = get_current_user_id();
        $auth_url = PRM_Google_OAuth::get_auth_url($user_id);

        if (empty($auth_url)) {
            return new WP_Error(
                'auth_url_failed',
                __('Failed to generate authorization URL.', 'personal-crm'),
                ['status' => 500]
            );
        }

        return rest_ensure_response(['auth_url' => $auth_url]);
    }

    /**
     * Handle Google OAuth callback
     *
     * Exchanges the authorization code for tokens and creates a calendar connection.
     * Redirects to settings page with success or error status.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Redirects to settings page.
     */
    public function google_auth_callback($request) {
        // Check for error from Google (user denied access)
        $error = $request->get_param('error');
        if ($error) {
            $error_desc = $request->get_param('error_description') ?? 'Authorization denied';
            wp_redirect(home_url('/settings/calendars?error=' . urlencode($error_desc)));
            exit;
        }

        // Get and validate state parameter (user_id|nonce)
        $state = $request->get_param('state');
        if (empty($state) || strpos($state, '|') === false) {
            wp_redirect(home_url('/settings/calendars?error=' . urlencode('Invalid state parameter')));
            exit;
        }

        list($user_id, $nonce) = explode('|', $state, 2);
        $user_id = absint($user_id);

        // Verify nonce for CSRF protection
        if (!wp_verify_nonce($nonce, 'google_oauth_' . $user_id)) {
            wp_redirect(home_url('/settings/calendars?error=' . urlencode('Security verification failed. Please try again.')));
            exit;
        }

        // Get authorization code
        $code = $request->get_param('code');
        if (empty($code)) {
            wp_redirect(home_url('/settings/calendars?error=' . urlencode('No authorization code received')));
            exit;
        }

        try {
            // Exchange code for tokens
            $tokens = PRM_Google_OAuth::handle_callback($code, $user_id);

            // Encrypt tokens for storage
            $encrypted_credentials = PRM_Credential_Encryption::encrypt($tokens);

            // Create calendar connection
            $connection = [
                'provider'       => 'google',
                'name'           => 'Google Calendar',
                'calendar_id'    => 'primary',
                'credentials'    => $encrypted_credentials,
                'sync_enabled'   => true,
                'auto_log'       => true,
                'sync_from_days' => 90,
                'last_sync'      => null,
                'last_error'     => null,
            ];

            PRM_Calendar_Connections::add_connection($user_id, $connection);

            // Redirect to settings page with success
            wp_redirect(home_url('/settings/calendars?connected=google'));
            exit;

        } catch (Exception $e) {
            wp_redirect(home_url('/settings/calendars?error=' . urlencode($e->getMessage())));
            exit;
        }
    }

    /**
     * Test CalDAV credentials (stub)
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_Error Always returns 501 - will be implemented in Phase 50.
     */
    public function test_caldav($request) {
        return new WP_Error(
            'not_implemented',
            __('CalDAV test is not yet implemented.', 'personal-crm'),
            ['status' => 501]
        );
    }

    /**
     * Get cached calendar events
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response with array of events.
     */
    public function get_events($request) {
        $user_id = get_current_user_id();

        // Build query args
        $args = [
            'post_type'      => 'calendar_event',
            'author'         => $user_id,
            'posts_per_page' => 100,
            'orderby'        => 'meta_value',
            'meta_key'       => '_start_time',
            'order'          => 'ASC',
        ];

        // Build meta query for date filtering
        $meta_query = [];

        // Filter by 'from' date
        $from = $request->get_param('from');
        if ($from) {
            $meta_query[] = [
                'key'     => '_start_time',
                'value'   => sanitize_text_field($from),
                'compare' => '>=',
                'type'    => 'DATE',
            ];
        }

        // Filter by 'to' date
        $to = $request->get_param('to');
        if ($to) {
            $meta_query[] = [
                'key'     => '_start_time',
                'value'   => sanitize_text_field($to) . ' 23:59:59',
                'compare' => '<=',
                'type'    => 'DATETIME',
            ];
        }

        if (!empty($meta_query)) {
            $meta_query['relation'] = 'AND';
            $args['meta_query'] = $meta_query;
        }

        // Execute query
        $query = new WP_Query($args);

        // Map results to response format
        $events = array_map(function($post) {
            $attendees_json = get_post_meta($post->ID, '_attendees', true);
            $attendees = $attendees_json ? json_decode($attendees_json, true) : [];

            return [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'description'   => $post->post_content,
                'start_time'    => get_post_meta($post->ID, '_start_time', true),
                'end_time'      => get_post_meta($post->ID, '_end_time', true),
                'all_day'       => (bool) get_post_meta($post->ID, '_all_day', true),
                'location'      => get_post_meta($post->ID, '_location', true),
                'meeting_url'   => get_post_meta($post->ID, '_meeting_url', true),
                'organizer'     => get_post_meta($post->ID, '_organizer_email', true),
                'attendees'     => is_array($attendees) ? $attendees : [],
                'connection_id' => get_post_meta($post->ID, '_connection_id', true),
                'calendar_id'   => get_post_meta($post->ID, '_calendar_id', true),
            ];
        }, $query->posts);

        return rest_ensure_response([
            'events' => $events,
            'total'  => count($events),
        ]);
    }

    /**
     * Get meetings for a person
     *
     * Returns empty structure for now so UI can be built against the expected format.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Empty meetings structure.
     */
    public function get_person_meetings($request) {
        // Return empty structure for now so UI can be built
        return rest_ensure_response([
            'upcoming'       => [],
            'past'           => [],
            'total_upcoming' => 0,
            'total_past'     => 0,
        ]);
    }

    /**
     * Log a calendar event as an activity (stub)
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_Error Always returns 501 - will be implemented in Phase 53.
     */
    public function log_event_as_activity($request) {
        return new WP_Error(
            'not_implemented',
            __('Activity logging is not yet implemented.', 'personal-crm'),
            ['status' => 501]
        );
    }
}
