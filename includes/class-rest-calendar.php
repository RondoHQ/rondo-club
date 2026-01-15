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

        // GET /prm/v1/calendar/sync/status - Background sync status
        register_rest_route('prm/v1', '/calendar/sync/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_sync_status'],
            'permission_callback' => [$this, 'check_user_approved'],
        ]);

        // GET /prm/v1/calendar/today-meetings - Today's meetings for dashboard
        register_rest_route('prm/v1', '/calendar/today-meetings', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_today_meetings'],
            'permission_callback' => [$this, 'check_user_approved'],
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

        if (!in_array($provider, ['google', 'caldav'], true)) {
            return new WP_Error(
                'invalid_provider',
                __('Unknown calendar provider.', 'personal-crm'),
                ['status' => 400]
            );
        }

        // Add user_id to connection for token refresh (Google provider)
        $connection['user_id'] = $user_id;

        try {
            // Route to appropriate provider
            if ($provider === 'caldav') {
                $result = PRM_CalDAV_Provider::sync($user_id, $connection);
            } else {
                $result = PRM_Google_Calendar_Provider::sync($user_id, $connection);
            }

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
     * Note: Uses HTML redirect instead of wp_redirect() because REST API endpoints
     * don't properly handle HTTP redirects - the response object gets processed
     * rather than the redirect being executed.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return void Outputs HTML redirect and exits.
     */
    public function google_auth_callback($request) {
        // Check for error from Google (user denied access)
        $error = $request->get_param('error');
        if ($error) {
            $error_desc = $request->get_param('error_description') ?? 'Authorization denied';
            $this->html_redirect(home_url('/settings?tab=connections&subtab=calendars&error=' . urlencode($error_desc)));
        }

        // Get and validate state parameter (token stored in transient)
        $token = $request->get_param('state');
        if (empty($token)) {
            $this->html_redirect(home_url('/settings?tab=connections&subtab=calendars&error=' . urlencode('Invalid state parameter')));
        }

        // Retrieve user_id from transient (database-stored, not session-dependent)
        $user_id = get_transient('google_oauth_' . $token);
        if (!$user_id) {
            $this->html_redirect(home_url('/settings?tab=connections&subtab=calendars&error=' . urlencode('Security verification failed or link expired. Please try again.')));
        }

        // Delete the transient to prevent reuse
        delete_transient('google_oauth_' . $token);
        $user_id = absint($user_id);

        // Get authorization code
        $code = $request->get_param('code');
        if (empty($code)) {
            $this->html_redirect(home_url('/settings?tab=connections&subtab=calendars&error=' . urlencode('No authorization code received')));
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
            $this->html_redirect(home_url('/settings?tab=connections&subtab=calendars&connected=google'));

        } catch (Exception $e) {
            $this->html_redirect(home_url('/settings?tab=connections&subtab=calendars&error=' . urlencode($e->getMessage())));
        }
    }

    /**
     * Output an HTML redirect and exit
     *
     * Used in OAuth callbacks where wp_redirect() doesn't work because
     * REST API endpoints process the response object rather than executing
     * the HTTP redirect headers.
     *
     * @param string $url The URL to redirect to.
     * @return void Outputs HTML and exits.
     */
    private function html_redirect($url) {
        $safe_url = esc_url($url);

        // Clear any output buffers that REST API may have started
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Send headers and HTML redirect
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo '<!DOCTYPE html><html><head>';
        echo '<meta http-equiv="refresh" content="0;url=' . $safe_url . '">';
        echo '<script>window.location.replace("' . esc_js($url) . '");</script>';
        echo '</head><body>Redirecting...</body></html>';
        exit;
    }

    /**
     * Test CalDAV credentials
     *
     * Tests the provided CalDAV URL, username, and password by attempting
     * to discover available calendars on the server.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with calendars or error.
     */
    public function test_caldav($request) {
        $url = $request->get_param('url');
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        // Validate required parameters
        if (empty($url)) {
            return new WP_Error('missing_url', __('CalDAV server URL is required.', 'personal-crm'), ['status' => 400]);
        }
        if (empty($username)) {
            return new WP_Error('missing_username', __('Username is required.', 'personal-crm'), ['status' => 400]);
        }
        if (empty($password)) {
            return new WP_Error('missing_password', __('Password is required.', 'personal-crm'), ['status' => 400]);
        }

        // Test the connection
        $result = PRM_CalDAV_Provider::test_connection($url, $username, $password);

        if (!$result['success']) {
            return new WP_Error('connection_failed', $result['error'], ['status' => 400]);
        }

        return rest_ensure_response([
            'success'   => true,
            'calendars' => $result['calendars'],
            'message'   => $result['message'] ?? __('Connection successful.', 'personal-crm'),
        ]);
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
     * Returns calendar events where the person was matched as an attendee.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Meetings for the person.
     */
    public function get_person_meetings($request) {
        $person_id = absint($request->get_param('person_id'));
        $show_upcoming = (bool) $request->get_param('upcoming');
        $show_past = (bool) $request->get_param('past');
        $limit = absint($request->get_param('limit'));
        $user_id = get_current_user_id();

        // Get current time for date comparison
        $now = current_time('mysql');

        // Build base meta query to find events with this person matched
        // _matched_people is a JSON array, we search for the person_id within it
        $base_meta_query = [
            [
                'key'     => '_matched_people',
                'value'   => '"person_id":' . $person_id,
                'compare' => 'LIKE',
            ],
        ];

        $upcoming = [];
        $past = [];
        $total_upcoming = 0;
        $total_past = 0;

        // Query upcoming events
        if ($show_upcoming) {
            $upcoming_args = [
                'post_type'      => 'calendar_event',
                'author'         => $user_id,
                'posts_per_page' => $limit,
                'orderby'        => 'meta_value',
                'meta_key'       => '_start_time',
                'order'          => 'ASC',
                'meta_query'     => array_merge($base_meta_query, [
                    [
                        'key'     => '_start_time',
                        'value'   => $now,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ],
                ]),
            ];

            $upcoming_query = new WP_Query($upcoming_args);
            $total_upcoming = $upcoming_query->found_posts;

            foreach ($upcoming_query->posts as $event) {
                $upcoming[] = $this->format_meeting_event($event, $person_id, $user_id);
            }
        }

        // Query past events
        if ($show_past) {
            $past_args = [
                'post_type'      => 'calendar_event',
                'author'         => $user_id,
                'posts_per_page' => $limit,
                'orderby'        => 'meta_value',
                'meta_key'       => '_start_time',
                'order'          => 'DESC',
                'meta_query'     => array_merge($base_meta_query, [
                    [
                        'key'     => '_start_time',
                        'value'   => $now,
                        'compare' => '<',
                        'type'    => 'DATETIME',
                    ],
                ]),
            ];

            $past_query = new WP_Query($past_args);
            $total_past = $past_query->found_posts;

            foreach ($past_query->posts as $event) {
                $past[] = $this->format_meeting_event($event, $person_id, $user_id);
            }
        }

        return rest_ensure_response([
            'upcoming'       => $upcoming,
            'past'           => $past,
            'total_upcoming' => $total_upcoming,
            'total_past'     => $total_past,
        ]);
    }

    /**
     * Format a calendar event for the meetings endpoint
     *
     * @param WP_Post $event     The calendar event post.
     * @param int     $person_id The matched person ID.
     * @param int     $user_id   The current user ID.
     * @return array Formatted event data.
     */
    private function format_meeting_event($event, $person_id, $user_id) {
        $matched_people_json = get_post_meta($event->ID, '_matched_people', true);
        $matched_people = $matched_people_json ? json_decode($matched_people_json, true) : [];

        // Find the match info for this person
        $match_type = '';
        $confidence = 0;
        $matched_attendee_email = null;

        if (is_array($matched_people)) {
            foreach ($matched_people as $match) {
                if (isset($match['person_id']) && $match['person_id'] === $person_id) {
                    $match_type = $match['match_type'] ?? '';
                    $confidence = $match['confidence'] ?? 0;
                    $matched_attendee_email = $match['attendee_email'] ?? null;
                    break;
                }
            }
        }

        // Get attendees and filter out the matched person's email
        $attendees_json = get_post_meta($event->ID, '_attendees', true);
        $attendees = $attendees_json ? json_decode($attendees_json, true) : [];
        $other_attendees = [];

        if (is_array($attendees)) {
            foreach ($attendees as $attendee) {
                $email = $attendee['email'] ?? '';
                // Exclude the matched person's email from other_attendees
                if (!empty($email) && $email !== $matched_attendee_email) {
                    $other_attendees[] = $email;
                }
            }
        }

        // Get connection name
        $connection_id = get_post_meta($event->ID, '_connection_id', true);
        $calendar_name = '';

        if ($connection_id) {
            $connection = PRM_Calendar_Connections::get_connection($user_id, $connection_id);
            if ($connection) {
                $calendar_name = $connection['name'] ?? '';
            }
        }

        // Format times with timezone for proper JavaScript parsing
        // Using ISO 8601 format (c) includes timezone offset: 2026-01-15T10:00:00+01:00
        $wp_timezone = wp_timezone();

        $start_meta = get_post_meta($event->ID, '_start_time', true);
        $end_meta = get_post_meta($event->ID, '_end_time', true);

        // Create DateTime objects in WordPress timezone (handles empty/invalid gracefully)
        $start_datetime = new DateTime($start_meta ?: 'now', $wp_timezone);
        $end_datetime = new DateTime($end_meta ?: $start_meta ?: 'now', $wp_timezone);

        return [
            'id'                     => $event->ID,
            'title'                  => sanitize_text_field($event->post_title),
            'start_time'             => $start_datetime->format('c'), // ISO 8601 with timezone offset
            'end_time'               => $end_datetime->format('c'),   // ISO 8601 with timezone offset
            'location'               => get_post_meta($event->ID, '_location', true),
            'meeting_url'            => get_post_meta($event->ID, '_meeting_url', true),
            'all_day'                => (bool) get_post_meta($event->ID, '_all_day', true),
            'match_type'             => $match_type,
            'confidence'             => $confidence,
            'matched_attendee_email' => $matched_attendee_email,
            'other_attendees'        => $other_attendees,
            'calendar_name'          => $calendar_name,
            'connection_id'          => $connection_id,
            'logged_as_activity'     => (bool) get_post_meta($event->ID, '_logged_as_activity', true),
        ];
    }

    /**
     * Get background sync status
     *
     * Returns information about the WP-Cron scheduled sync and user connections.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response with sync status.
     */
    public function get_sync_status($request) {
        $status = PRM_Calendar_Sync::get_sync_status();

        // Add current user's connection details
        $user_id = get_current_user_id();
        $user_connections = PRM_Calendar_Connections::get_user_connections($user_id);

        // Remove sensitive credential data
        $safe_connections = array_map(function($conn) {
            unset($conn['credentials']);
            return $conn;
        }, $user_connections);

        $status['user_connections'] = $safe_connections;

        return rest_ensure_response($status);
    }

    /**
     * Get today's meetings for dashboard widget
     *
     * Returns calendar events for today with matched attendees and their details.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response with today's meetings.
     */
    public function get_today_meetings($request) {
        $user_id = get_current_user_id();

        // Check if user has any calendar connections
        $connections = PRM_Calendar_Connections::get_user_connections($user_id);
        $has_connections = !empty($connections);

        if (!$has_connections) {
            return rest_ensure_response([
                'meetings'        => [],
                'total'           => 0,
                'has_connections' => false,
            ]);
        }

        // Get today's date range in site timezone
        $today_start = current_time('Y-m-d') . ' 00:00:00';
        $today_end = current_time('Y-m-d') . ' 23:59:59';

        // Query calendar events for today
        $args = [
            'post_type'      => 'calendar_event',
            'author'         => $user_id,
            'posts_per_page' => 50, // Reasonable limit for one day
            'orderby'        => 'meta_value',
            'meta_key'       => '_start_time',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_start_time',
                    'value'   => $today_start,
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ],
                [
                    'key'     => '_start_time',
                    'value'   => $today_end,
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ],
            ],
        ];

        $query = new WP_Query($args);

        // Format meetings with matched people details
        $meetings = [];
        foreach ($query->posts as $event) {
            $meetings[] = $this->format_today_meeting($event, $user_id);
        }

        return rest_ensure_response([
            'meetings'        => $meetings,
            'total'           => count($meetings),
            'has_connections' => true,
        ]);
    }

    /**
     * Format a calendar event for the today's meetings endpoint
     *
     * @param WP_Post $event   The calendar event post.
     * @param int     $user_id The current user ID.
     * @return array Formatted meeting data with matched people details.
     */
    private function format_today_meeting($event, $user_id) {
        // Get matched people and expand with person details
        $matched_people_json = get_post_meta($event->ID, '_matched_people', true);
        $matched_people_raw = $matched_people_json ? json_decode($matched_people_json, true) : [];
        $matched_people = [];

        if (is_array($matched_people_raw)) {
            foreach ($matched_people_raw as $match) {
                $person_id = $match['person_id'] ?? 0;
                if (!$person_id) {
                    continue;
                }

                // Get person details
                $person = get_post($person_id);
                if (!$person || $person->post_type !== 'person') {
                    continue;
                }

                // Get person thumbnail
                $thumbnail = null;
                $featured_image_id = get_post_thumbnail_id($person_id);
                if ($featured_image_id) {
                    $thumbnail_url = wp_get_attachment_image_url($featured_image_id, 'thumbnail');
                    if ($thumbnail_url) {
                        $thumbnail = $thumbnail_url;
                    }
                }

                $matched_people[] = [
                    'person_id' => $person_id,
                    'name'      => $person->post_title,
                    'thumbnail' => $thumbnail,
                ];
            }
        }

        // Get connection/calendar name
        $connection_id = get_post_meta($event->ID, '_connection_id', true);
        $calendar_name = '';

        if ($connection_id) {
            $connection = PRM_Calendar_Connections::get_connection($user_id, $connection_id);
            if ($connection) {
                $calendar_name = $connection['name'] ?? '';
            }
        }

        // Format times with timezone for proper JavaScript parsing
        // Using ISO 8601 format (c) includes timezone offset: 2026-01-15T10:00:00+01:00
        $wp_timezone = wp_timezone();

        $start_meta = get_post_meta($event->ID, '_start_time', true);
        $end_meta = get_post_meta($event->ID, '_end_time', true);

        // Create DateTime objects in WordPress timezone (handles empty/invalid gracefully)
        $start_datetime = new DateTime($start_meta ?: 'now', $wp_timezone);
        $end_datetime = new DateTime($end_meta ?: $start_meta ?: 'now', $wp_timezone);

        return [
            'id'             => $event->ID,
            'title'          => sanitize_text_field($event->post_title),
            'start_time'     => $start_datetime->format('c'), // ISO 8601 with timezone offset
            'end_time'       => $end_datetime->format('c'),   // ISO 8601 with timezone offset
            'all_day'        => (bool) get_post_meta($event->ID, '_all_day', true),
            'location'       => get_post_meta($event->ID, '_location', true),
            'meeting_url'    => get_post_meta($event->ID, '_meeting_url', true),
            'matched_people' => $matched_people,
            'calendar_name'  => $calendar_name,
        ];
    }

    /**
     * Log a calendar event as an activity
     *
     * Creates activity records for all matched people on the event.
     * Uses shared logic from PRM_Calendar_Sync::create_activity_from_event().
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with activity count or error.
     */
    public function log_event_as_activity($request) {
        $event_id = absint($request->get_param('id'));
        $user_id = get_current_user_id();

        // Get and verify the event
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'calendar_event') {
            return new WP_Error('not_found', __('Event not found.', 'personal-crm'), ['status' => 404]);
        }

        // Verify ownership
        if ((int) $event->post_author !== $user_id) {
            return new WP_Error('forbidden', __('You do not have permission to log this event.', 'personal-crm'), ['status' => 403]);
        }

        // Check if already logged
        $already_logged = get_post_meta($event_id, '_logged_as_activity', true);
        if ($already_logged) {
            return new WP_Error('already_logged', __('This event has already been logged as an activity.', 'personal-crm'), ['status' => 400]);
        }

        // Get matched people
        $matched_people_json = get_post_meta($event_id, '_matched_people', true);
        $matched_people = $matched_people_json ? json_decode($matched_people_json, true) : [];

        if (empty($matched_people) || !is_array($matched_people)) {
            return new WP_Error('no_matches', __('No matched people found for this event.', 'personal-crm'), ['status' => 400]);
        }

        // Use shared activity creation logic from PRM_Calendar_Sync
        $activities_created = PRM_Calendar_Sync::create_activity_from_event($event_id, $user_id, $matched_people);

        if ($activities_created === 0) {
            return new WP_Error('no_matches', __('No matched people found for this event.', 'personal-crm'), ['status' => 400]);
        }

        // Mark event as logged
        update_post_meta($event_id, '_logged_as_activity', true);

        return rest_ensure_response([
            'success'            => true,
            'activities_created' => $activities_created,
            'message'            => sprintf(
                /* translators: %d: number of activities created */
                _n(
                    '%d activity logged.',
                    '%d activities logged.',
                    $activities_created,
                    'personal-crm'
                ),
                $activities_created
            ),
        ]);
    }
}
