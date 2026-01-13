<?php
/**
 * Slack Integration REST API Endpoints
 *
 * Handles all Slack-related REST API functionality including OAuth,
 * notifications, channels, and slash commands.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_REST_Slack extends PRM_REST_Base {

    /**
     * Constructor - register routes via rest_api_init hook
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Encrypt a token using sodium_crypto_secretbox
     *
     * @param string $token The plaintext token to encrypt.
     * @return string Base64-encoded encrypted token (nonce + ciphertext).
     */
    protected function encrypt_token($token) {
        // Check if encryption key is defined, fallback to base64 if not
        if (!defined('CAELIS_ENCRYPTION_KEY') || empty(CAELIS_ENCRYPTION_KEY)) {
            return base64_encode($token);
        }

        // Generate random nonce
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Encrypt the token
        $ciphertext = sodium_crypto_secretbox($token, $nonce, CAELIS_ENCRYPTION_KEY);

        // Return base64-encoded nonce + ciphertext
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt a token encrypted with sodium_crypto_secretbox
     *
     * Supports migration from legacy base64-encoded tokens.
     *
     * @param string $encrypted The encrypted token (base64-encoded).
     * @return string|false The decrypted token, or false on failure.
     */
    protected function decrypt_token($encrypted) {
        // Check if encryption key is defined, fallback to base64 decode if not
        if (!defined('CAELIS_ENCRYPTION_KEY') || empty(CAELIS_ENCRYPTION_KEY)) {
            return base64_decode($encrypted);
        }

        $decoded = base64_decode($encrypted, true);

        // Check if decoded data is too short to be sodium-encrypted
        // Minimum length: nonce (24 bytes) + MAC (16 bytes) = 40 bytes
        $min_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
        if ($decoded === false || strlen($decoded) < $min_length) {
            // Assume legacy base64-encoded token
            return base64_decode($encrypted);
        }

        // Extract nonce and ciphertext
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Attempt decryption
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, CAELIS_ENCRYPTION_KEY);

        if ($plaintext === false) {
            // Decryption failed, try legacy base64 decode (migration path)
            return base64_decode($encrypted);
        }

        return $plaintext;
    }

    /**
     * Register Slack REST routes
     */
    public function register_routes() {
        // Slack OAuth - Authorize
        register_rest_route('prm/v1', '/slack/oauth/authorize', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'slack_oauth_authorize'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Slack OAuth - Callback
        register_rest_route('prm/v1', '/slack/oauth/callback', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'slack_oauth_callback'],
            'permission_callback' => '__return_true', // Public endpoint, we verify state
        ]);

        // Slack - Disconnect
        register_rest_route('prm/v1', '/slack/disconnect', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'slack_disconnect'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Slack - Status
        register_rest_route('prm/v1', '/user/slack-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_slack_status'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Slack - Commands (slash command endpoint)
        register_rest_route('prm/v1', '/slack/commands', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'slack_commands'],
            'permission_callback' => '__return_true', // Public endpoint, we verify signature
        ]);

        // Slack - Events (event subscription endpoint)
        register_rest_route('prm/v1', '/slack/events', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'slack_events'],
            'permission_callback' => '__return_true', // Public endpoint, we verify signature
        ]);

        // Slack - Get channels and users
        register_rest_route('prm/v1', '/slack/channels', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_slack_channels'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Slack - Get notification targets
        register_rest_route('prm/v1', '/slack/targets', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_slack_targets'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Slack - Update notification targets
        register_rest_route('prm/v1', '/slack/targets', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_slack_targets'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Slack - Update webhook URL (legacy)
        register_rest_route('prm/v1', '/user/slack-webhook', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_slack_webhook'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'webhook' => [
                    'required'          => false,
                    'validate_callback' => function($param) {
                        if (empty($param)) {
                            return true;
                        }
                        if (!filter_var($param, FILTER_VALIDATE_URL)) {
                            return false;
                        }
                        $host = parse_url($param, PHP_URL_HOST);
                        return $host === 'hooks.slack.com';
                    },
                ],
            ],
        ]);
    }

    /**
     * Slack OAuth - Authorize endpoint
     * Redirects user to Slack OAuth authorization page
     */
    public function slack_oauth_authorize($request) {
        if (!defined('CAELIS_SLACK_CLIENT_ID') || empty(CAELIS_SLACK_CLIENT_ID)) {
            return new WP_Error(
                'slack_not_configured',
                __('Slack integration is not configured.', 'personal-crm'),
                ['status' => 500]
            );
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error(
                'not_authenticated',
                __('You must be logged in to connect Slack.', 'personal-crm'),
                ['status' => 401]
            );
        }

        // Generate state for CSRF protection - include user_id for easier lookup
        $random_part = wp_generate_password(32, false);
        $state = base64_encode($user_id . ':' . $random_part);
        set_transient('slack_oauth_state_' . $user_id, $state, 600); // 10 minutes

        // Build OAuth URL
        $redirect_uri = rest_url('prm/v1/slack/oauth/callback');
        $scopes = 'chat:write,chat:write.public,channels:read,users:read,users:read.email,commands';

        $params = [
            'client_id'    => CAELIS_SLACK_CLIENT_ID,
            'scope'        => $scopes,
            'redirect_uri' => $redirect_uri,
            'state'        => $state,
        ];

        $oauth_url = 'https://slack.com/oauth/v2/authorize?' . http_build_query($params);

        // Return OAuth URL - frontend will handle redirect
        return rest_ensure_response([
            'oauth_url' => $oauth_url,
        ]);
    }

    /**
     * Slack OAuth - Callback endpoint
     * Handles OAuth callback from Slack
     */
    public function slack_oauth_callback($request) {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $error = $request->get_param('error');

        // Handle error from Slack
        if ($error) {
            $error_url = home_url('/settings?slack_error=' . urlencode($error));
            wp_redirect($error_url);
            exit;
        }

        if (empty($code) || empty($state)) {
            $error_url = home_url('/settings?slack_error=missing_parameters');
            wp_redirect($error_url);
            exit;
        }

        // Verify state - include user_id in state for easier lookup
        // State format: base64(user_id:random_string)
        $decoded_state = base64_decode($state, true);
        if ($decoded_state === false) {
            $error_url = home_url('/settings?slack_error=invalid_state');
            wp_redirect($error_url);
            exit;
        }

        list($user_id_from_state, $random_part) = explode(':', $decoded_state, 2);
        $user_id = absint($user_id_from_state);

        if (!$user_id) {
            $error_url = home_url('/settings?slack_error=invalid_state');
            wp_redirect($error_url);
            exit;
        }

        // Verify state matches stored state
        $stored_state = get_transient('slack_oauth_state_' . $user_id);
        if ($stored_state !== $state) {
            $error_url = home_url('/settings?slack_error=invalid_state');
            wp_redirect($error_url);
            exit;
        }

        // Clean up state
        delete_transient('slack_oauth_state_' . $user_id);

        // Exchange code for token
        $redirect_uri = rest_url('prm/v1/slack/oauth/callback');
        $response = wp_remote_post('https://slack.com/api/oauth.v2.access', [
            'body' => [
                'client_id'     => CAELIS_SLACK_CLIENT_ID,
                'client_secret' => CAELIS_SLACK_CLIENT_SECRET,
                'code'          => $code,
                'redirect_uri'  => $redirect_uri,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $error_url = home_url('/settings?slack_error=network_error');
            wp_redirect($error_url);
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['ok']) || empty($body['access_token'])) {
            $error_msg = isset($body['error']) ? $body['error'] : 'unknown_error';
            $error_url = home_url('/settings?slack_error=' . urlencode($error_msg));
            wp_redirect($error_url);
            exit;
        }

        // Store tokens and workspace info
        $bot_token = $body['access_token'];
        $workspace_id = isset($body['team']['id']) ? $body['team']['id'] : '';
        $workspace_name = isset($body['team']['name']) ? $body['team']['name'] : '';
        $slack_user_id = isset($body['authed_user']['id']) ? $body['authed_user']['id'] : '';

        // Encrypt token before storing
        update_user_meta($user_id, 'caelis_slack_bot_token', $this->encrypt_token($bot_token));
        update_user_meta($user_id, 'caelis_slack_workspace_id', $workspace_id);
        update_user_meta($user_id, 'caelis_slack_workspace_name', $workspace_name);
        update_user_meta($user_id, 'caelis_slack_user_id', $slack_user_id);

        // Auto-enable Slack channel
        $channels = get_user_meta($user_id, 'caelis_notification_channels', true);
        if (!is_array($channels)) {
            $channels = [];
        }
        if (!in_array('slack', $channels)) {
            $channels[] = 'slack';
            update_user_meta($user_id, 'caelis_notification_channels', $channels);
        }

        // Remove old webhook if exists
        delete_user_meta($user_id, 'caelis_slack_webhook');

        // Redirect to settings with success message
        $success_url = home_url('/settings?slack_connected=1');
        wp_redirect($success_url);
        exit;
    }

    /**
     * Slack - Disconnect endpoint
     */
    public function slack_disconnect($request) {
        $user_id = get_current_user_id();

        // Get bot token
        $encrypted_token = get_user_meta($user_id, 'caelis_slack_bot_token', true);
        if (empty($encrypted_token)) {
            return rest_ensure_response([
                'success' => true,
                'message' => __('Slack was not connected.', 'personal-crm'),
            ]);
        }

        $bot_token = $this->decrypt_token($encrypted_token);

        // Revoke token via Slack API
        wp_remote_post('https://slack.com/api/auth.revoke', [
            'body' => [
                'token' => $bot_token,
            ],
            'timeout' => 10,
        ]);

        // Remove stored data
        delete_user_meta($user_id, 'caelis_slack_bot_token');
        delete_user_meta($user_id, 'caelis_slack_workspace_id');
        delete_user_meta($user_id, 'caelis_slack_workspace_name');
        delete_user_meta($user_id, 'caelis_slack_user_id');

        // Disable Slack channel
        $channels = get_user_meta($user_id, 'caelis_notification_channels', true);
        if (is_array($channels)) {
            $channels = array_diff($channels, ['slack']);
            update_user_meta($user_id, 'caelis_notification_channels', $channels);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Slack disconnected successfully.', 'personal-crm'),
        ]);
    }

    /**
     * Get Slack connection status
     */
    public function get_slack_status($request) {
        $user_id = get_current_user_id();
        $bot_token = get_user_meta($user_id, 'caelis_slack_bot_token', true);

        if (empty($bot_token)) {
            return rest_ensure_response([
                'connected' => false,
            ]);
        }

        $workspace_name = get_user_meta($user_id, 'caelis_slack_workspace_name', true);

        return rest_ensure_response([
            'connected'      => true,
            'workspace_name' => $workspace_name ?: __('Unknown workspace', 'personal-crm'),
        ]);
    }

    /**
     * Slack - Commands endpoint (slash command)
     */
    public function slack_commands($request) {
        // Verify request signature
        if (!defined('CAELIS_SLACK_SIGNING_SECRET') || empty(CAELIS_SLACK_SIGNING_SECRET)) {
            return new WP_Error(
                'slack_not_configured',
                __('Slack integration is not configured.', 'personal-crm'),
                ['status' => 500]
            );
        }

        $signature = $request->get_header('X-Slack-Signature');
        $timestamp = $request->get_header('X-Slack-Request-Timestamp');

        // Get raw body - WordPress REST API may have parsed it, so we need to reconstruct
        $body = $request->get_body();
        if (empty($body)) {
            // If body is empty, try to get from POST data
            $body = file_get_contents('php://input');
        }

        // Verify timestamp (prevent replay attacks)
        if (empty($timestamp) || abs(time() - (int)$timestamp) > 300) { // 5 minutes
            return new WP_Error(
                'invalid_timestamp',
                __('Request timestamp is invalid or too old.', 'personal-crm'),
                ['status' => 401]
            );
        }

        // Verify signature
        if (empty($signature) || empty($body)) {
            return new WP_Error(
                'missing_signature',
                __('Missing request signature.', 'personal-crm'),
                ['status' => 401]
            );
        }

        $sig_basestring = 'v0:' . $timestamp . ':' . $body;
        $my_signature = 'v0=' . hash_hmac('sha256', $sig_basestring, CAELIS_SLACK_SIGNING_SECRET);

        if (!hash_equals($my_signature, $signature)) {
            return new WP_Error(
                'invalid_signature',
                __('Invalid request signature.', 'personal-crm'),
                ['status' => 401]
            );
        }

        // Parse request body
        parse_str($body, $params);
        $slack_user_id = isset($params['user_id']) ? $params['user_id'] : '';
        $command = isset($params['command']) ? $params['command'] : '';

        if ($command !== '/caelis') {
            return rest_ensure_response([
                'response_type' => 'ephemeral',
                'text'         => __('Unknown command.', 'personal-crm'),
            ]);
        }

        // Find WordPress user by Slack user ID
        $wp_user_id = null;
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $uid) {
            $stored_slack_user_id = get_user_meta($uid, 'caelis_slack_user_id', true);
            if ($stored_slack_user_id === $slack_user_id) {
                $wp_user_id = $uid;
                break;
            }
        }

        if (!$wp_user_id) {
            return rest_ensure_response([
                'response_type' => 'ephemeral',
                'text'         => __('You need to connect your Slack account first. Visit your Caelis settings to connect.', 'personal-crm'),
            ]);
        }

        // Get user's most recent reminder digest
        $reminders = new PRM_Reminders();
        $digest_data = $reminders->get_weekly_digest($wp_user_id);

        // Debug: Log what we found
        error_log(sprintf(
            'Slack command for user %d: Found %d today, %d tomorrow, %d rest_of_week',
            $wp_user_id,
            count($digest_data['today'] ?? []),
            count($digest_data['tomorrow'] ?? []),
            count($digest_data['rest_of_week'] ?? [])
        ));

        if (empty($digest_data['today']) && empty($digest_data['tomorrow']) && empty($digest_data['rest_of_week'])) {
            return rest_ensure_response([
                'response_type' => 'ephemeral',
                'text'         => __('You have no upcoming reminders.', 'personal-crm'),
            ]);
        }

        // Format Slack blocks
        $slack_channel = new PRM_Slack_Channel();
        $blocks = $slack_channel->format_slack_blocks($digest_data);

        return rest_ensure_response([
            'response_type' => 'ephemeral',
            'blocks'        => $blocks,
        ]);
    }

    /**
     * Slack - Events endpoint (event subscription URL verification)
     */
    public function slack_events($request) {
        $body = json_decode($request->get_body(), true);

        // Handle URL verification challenge
        if (isset($body['type']) && $body['type'] === 'url_verification') {
            return rest_ensure_response([
                'challenge' => $body['challenge'],
            ]);
        }

        // For other events, just acknowledge receipt
        // We're not subscribing to any events, so this shouldn't be called
        return rest_ensure_response(['ok' => true]);
    }

    /**
     * Slack - Get channels and users for notification targets
     */
    public function get_slack_channels($request) {
        $user_id = get_current_user_id();
        $bot_token = get_user_meta($user_id, 'caelis_slack_bot_token', true);

        if (empty($bot_token)) {
            return new WP_Error(
                'slack_not_connected',
                __('Slack is not connected.', 'personal-crm'),
                ['status' => 400]
            );
        }

        $bot_token = $this->decrypt_token($bot_token);
        $channels = [];
        $users = [];

        // Fetch public channels
        $channels_response = wp_remote_post('https://slack.com/api/conversations.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $bot_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'types' => 'public_channel,private_channel',
                'exclude_archived' => true,
            ]),
            'timeout' => 10,
        ]);

        if (!is_wp_error($channels_response)) {
            $channels_body = json_decode(wp_remote_retrieve_body($channels_response), true);
            if (!empty($channels_body['ok']) && !empty($channels_body['channels'])) {
                foreach ($channels_body['channels'] as $channel) {
                    $channels[] = [
                        'id'   => $channel['id'],
                        'name' => '#' . $channel['name'],
                        'type' => 'channel',
                    ];
                }
            }
        }

        // Fetch users
        $users_response = wp_remote_post('https://slack.com/api/users.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $bot_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 10,
        ]);

        if (!is_wp_error($users_response)) {
            $users_body = json_decode(wp_remote_retrieve_body($users_response), true);
            if (!empty($users_body['ok']) && !empty($users_body['members'])) {
                $slack_user_id = get_user_meta($user_id, 'caelis_slack_user_id', true);
                foreach ($users_body['members'] as $user) {
                    // Skip bots and deleted users
                    if (!empty($user['deleted']) || (!empty($user['is_bot']) && $user['id'] !== 'USLACKBOT')) {
                        continue;
                    }
                    $users[] = [
                        'id'   => $user['id'],
                        'name' => $user['real_name'] ?: $user['name'],
                        'type' => 'user',
                        'is_me' => ($user['id'] === $slack_user_id),
                    ];
                }
            }
        }

        return rest_ensure_response([
            'channels' => $channels,
            'users'    => $users,
        ]);
    }

    /**
     * Slack - Get current notification targets
     */
    public function get_slack_targets($request) {
        $user_id = get_current_user_id();
        $targets = get_user_meta($user_id, 'caelis_slack_targets', true);

        if (!is_array($targets)) {
            // Default to user's own Slack user ID (DM)
            $slack_user_id = get_user_meta($user_id, 'caelis_slack_user_id', true);
            $targets = $slack_user_id ? [$slack_user_id] : [];
        }

        return rest_ensure_response([
            'targets' => $targets,
        ]);
    }

    /**
     * Slack - Update notification targets
     */
    public function update_slack_targets($request) {
        $user_id = get_current_user_id();
        $targets = $request->get_param('targets');

        if (!is_array($targets)) {
            return new WP_Error(
                'invalid_targets',
                __('Targets must be an array.', 'personal-crm'),
                ['status' => 400]
            );
        }

        // Validate targets are strings (channel/user IDs)
        $targets = array_filter(array_map('sanitize_text_field', $targets));

        // If empty, default to user's Slack user ID
        if (empty($targets)) {
            $slack_user_id = get_user_meta($user_id, 'caelis_slack_user_id', true);
            $targets = $slack_user_id ? [$slack_user_id] : [];
        }

        update_user_meta($user_id, 'caelis_slack_targets', $targets);

        return rest_ensure_response([
            'success' => true,
            'targets' => $targets,
        ]);
    }

    /**
     * Update user's Slack webhook URL (legacy)
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

        // Validate webhook domain to prevent SSRF attacks
        $host = parse_url($webhook, PHP_URL_HOST);
        if ($host !== 'hooks.slack.com') {
            return new WP_Error(
                'invalid_webhook_domain',
                __('Webhook URL must be from hooks.slack.com domain.', 'personal-crm'),
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
}
