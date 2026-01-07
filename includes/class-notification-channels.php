<?php
/**
 * Notification Channels System
 * 
 * Handles multi-channel notification delivery (Email, Slack, etc.)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for notification channels
 */
abstract class PRM_Notification_Channel {
    
    /**
     * Send notification to user via this channel
     * 
     * @param int   $user_id     User ID
     * @param array $digest_data Weekly digest data (today, tomorrow, rest_of_week)
     * @return bool Success status
     */
    abstract public function send($user_id, $digest_data);
    
    /**
     * Check if this channel is enabled for a user
     * 
     * @param int $user_id User ID
     * @return bool
     */
    abstract public function is_enabled_for_user($user_id);
    
    /**
     * Get user-specific configuration for this channel
     * 
     * @param int $user_id User ID
     * @return array|false
     */
    abstract public function get_user_config($user_id);
    
    /**
     * Get channel identifier
     * 
     * @return string
     */
    abstract public function get_channel_id();
    
    /**
     * Get channel display name
     * 
     * @return string
     */
    abstract public function get_channel_name();
    
    /**
     * Check if a person's name appears in a title and return the person if found
     * Returns the first person whose name appears in the title, or null
     * 
     * @param string $title The date title
     * @param array  $people Array of person data
     * @return array|null Person data if found, null otherwise
     */
    protected function find_person_in_title($title, $people) {
        foreach ($people as $person) {
            $person_name = $person['name'];
            // Check if the person's name appears in the title (case-insensitive)
            if (stripos($title, $person_name) !== false) {
                return $person;
            }
        }
        return null;
    }
}

/**
 * Email notification channel
 */
class PRM_Email_Channel extends PRM_Notification_Channel {
    
    public function get_channel_id() {
        return 'email';
    }
    
    public function get_channel_name() {
        return __('Email', 'personal-crm');
    }
    
    public function is_enabled_for_user($user_id) {
        $channels = get_user_meta($user_id, 'caelis_notification_channels', true);
        if (!is_array($channels)) {
            // Default to enabled for email if not set
            return true;
        }
        return in_array('email', $channels);
    }
    
    public function get_user_config($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return false;
        }
        return [
            'email' => $user->user_email,
        ];
    }
    
    public function send($user_id, $digest_data) {
        $user = get_userdata($user_id);
        
        if (!$user || !$user->user_email) {
            return false;
        }
        
        // Don't send if there are no dates
        $has_dates = !empty($digest_data['today']) || 
                     !empty($digest_data['tomorrow']) || 
                     !empty($digest_data['rest_of_week']);
        
        if (!$has_dates) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $today_formatted = date_i18n(get_option('date_format'));
        
        $subject = sprintf(
            __('[%s] Your Important Dates - %s', 'personal-crm'),
            $site_name,
            $today_formatted
        );
        
        $message = $this->format_email_message($user, $digest_data);
        
        // Set custom from name and email
        add_filter('wp_mail_from', [$this, 'set_email_from_address']);
        add_filter('wp_mail_from_name', [$this, 'set_email_from_name']);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $result = wp_mail($user->user_email, $subject, $message, $headers);
        
        // Remove filters after sending
        remove_filter('wp_mail_from', [$this, 'set_email_from_address']);
        remove_filter('wp_mail_from_name', [$this, 'set_email_from_name']);
        
        return $result;
    }
    
    /**
     * Format email message body as HTML
     */
    private function format_email_message($user, $digest_data) {
        $site_url = home_url();
        $today_formatted = date_i18n(get_option('date_format'));
        
        $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $html .= sprintf(
            '<p>Hello %s,</p><p>Here are your important dates for this week:</p>',
            esc_html($user->display_name)
        );
        
        // Today section
        if (!empty($digest_data['today'])) {
            $html .= '<h3 style="margin-top: 20px; margin-bottom: 10px;">TODAY</h3>';
            foreach ($digest_data['today'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                
                // Check if person name is in title
                $person_in_title = $this->find_person_in_title($date['title'], $date['related_people']);
                if ($person_in_title) {
                    // Replace name in title with link
                    $title_with_link = $this->replace_name_in_title_email($date['title'], $person_in_title, $site_url);
                    $html .= sprintf(
                        '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                        $title_with_link,
                        esc_html($date_formatted)
                    );
                } else {
                    // Show title normally and add people links below
                    $html .= sprintf(
                        '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                        esc_html($date['title']),
                        esc_html($date_formatted)
                    );
                    $people_links = $this->format_people_links($date['related_people'], $site_url);
                    if (!empty($people_links)) {
                        $html .= sprintf('<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links);
                    }
                }
            }
        }
        
        // Tomorrow section
        if (!empty($digest_data['tomorrow'])) {
            $html .= '<h3 style="margin-top: 20px; margin-bottom: 10px;">TOMORROW</h3>';
            foreach ($digest_data['tomorrow'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                
                // Check if person name is in title
                $person_in_title = $this->find_person_in_title($date['title'], $date['related_people']);
                if ($person_in_title) {
                    // Replace name in title with link
                    $title_with_link = $this->replace_name_in_title_email($date['title'], $person_in_title, $site_url);
                    $html .= sprintf(
                        '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                        $title_with_link,
                        esc_html($date_formatted)
                    );
                } else {
                    // Show title normally and add people links below
                    $html .= sprintf(
                        '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                        esc_html($date['title']),
                        esc_html($date_formatted)
                    );
                    $people_links = $this->format_people_links($date['related_people'], $site_url);
                    if (!empty($people_links)) {
                        $html .= sprintf('<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links);
                    }
                }
            }
        }
        
        // Rest of week section
        if (!empty($digest_data['rest_of_week'])) {
            $html .= '<h3 style="margin-top: 20px; margin-bottom: 10px;">THIS WEEK</h3>';
            foreach ($digest_data['rest_of_week'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                
                // Check if person name is in title
                $person_in_title = $this->find_person_in_title($date['title'], $date['related_people']);
                if ($person_in_title) {
                    // Replace name in title with link
                    $title_with_link = $this->replace_name_in_title_email($date['title'], $person_in_title, $site_url);
                    $html .= sprintf(
                        '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                        $title_with_link,
                        esc_html($date_formatted)
                    );
                } else {
                    // Show title normally and add people links below
                    $html .= sprintf(
                        '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                        esc_html($date['title']),
                        esc_html($date_formatted)
                    );
                    $people_links = $this->format_people_links($date['related_people'], $site_url);
                    if (!empty($people_links)) {
                        $html .= sprintf('<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links);
                    }
                }
            }
        }
        
        $html .= sprintf(
            '<p style="margin-top: 20px;"><a href="%s">Visit Caelis</a> to see more details.</p>',
            esc_url($site_url)
        );
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Set email from address
     */
    public function set_email_from_address($from_email) {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        return 'notifications@' . $domain;
    }
    
    /**
     * Set email from name
     */
    public function set_email_from_name($from_name) {
        return 'Caelis';
    }
    
    /**
     * Format people names as clickable HTML links
     */
    private function format_people_links($people, $site_url) {
        $links = [];
        foreach ($people as $person) {
            $person_url = esc_url($site_url . '/people/' . $person['id']);
            $person_name = esc_html($person['name']);
            $links[] = sprintf('<a href="%s">%s</a>', $person_url, $person_name);
        }
        return implode(', ', $links);
    }
    
    /**
     * Replace person name in title with a clickable link (for email)
     */
    private function replace_name_in_title_email($title, $person, $site_url) {
        $person_name = esc_html($person['name']);
        $person_url = esc_url($site_url . '/people/' . $person['id']);
        $person_link = sprintf('<a href="%s">%s</a>', $person_url, $person_name);
        
        // Replace the name in the title (case-insensitive)
        return preg_replace('/' . preg_quote($person_name, '/') . '/i', $person_link, $title, 1);
    }
    
    /**
     * Replace person name in title with a clickable link (for Slack)
     */
    private function replace_name_in_title_slack($title, $person, $site_url) {
        $person_name = $person['name'];
        $person_url = $site_url . '/people/' . $person['id'];
        $person_link = sprintf('<%s|%s>', $person_url, $person_name);
        
        // Replace the name in the title (case-insensitive)
        return preg_replace('/' . preg_quote($person_name, '/') . '/i', $person_link, $title, 1);
    }
}

/**
 * Slack notification channel
 */
class PRM_Slack_Channel extends PRM_Notification_Channel {
    
    public function get_channel_id() {
        return 'slack';
    }
    
    public function get_channel_name() {
        return __('Slack', 'personal-crm');
    }
    
    public function is_enabled_for_user($user_id) {
        $channels = get_user_meta($user_id, 'caelis_notification_channels', true);
        if (!is_array($channels)) {
            return false;
        }
        if (!in_array('slack', $channels)) {
            return false;
        }
        // Check if bot token is configured (OAuth) or webhook (legacy)
        $bot_token = get_user_meta($user_id, 'caelis_slack_bot_token', true);
        $webhook = get_user_meta($user_id, 'caelis_slack_webhook', true);
        return !empty($bot_token) || !empty($webhook);
    }
    
    public function get_user_config($user_id) {
        // Prefer OAuth bot token over webhook
        $bot_token = get_user_meta($user_id, 'caelis_slack_bot_token', true);
        if (!empty($bot_token)) {
            return [
                'bot_token' => base64_decode($bot_token),
                'workspace_id' => get_user_meta($user_id, 'caelis_slack_workspace_id', true),
                'target' => get_user_meta($user_id, 'caelis_slack_target', true), // Channel or user ID
            ];
        }
        
        // Fallback to webhook for legacy support
        $webhook = get_user_meta($user_id, 'caelis_slack_webhook', true);
        if (!empty($webhook)) {
            return [
                'webhook_url' => $webhook,
            ];
        }
        
        return false;
    }
    
    public function send($user_id, $digest_data, $target = null) {
        $config = $this->get_user_config($user_id);
        if (!$config) {
            return false;
        }
        
        // Don't send if there are no dates
        $has_dates = !empty($digest_data['today']) || 
                     !empty($digest_data['tomorrow']) || 
                     !empty($digest_data['rest_of_week']);
        
        if (!$has_dates) {
            return false;
        }
        
        $blocks = $this->format_slack_blocks($digest_data);
        
        // Use OAuth bot token if available
        if (isset($config['bot_token']) && !empty($config['bot_token'])) {
            // Get targets - use parameter if provided, otherwise use configured targets
            $targets = [];
            if (!empty($target)) {
                // Single target passed as parameter (for backward compatibility)
                $targets = [$target];
            } else {
                // Get configured targets from user meta
                $targets = get_user_meta($user_id, 'caelis_slack_targets', true);
                if (!is_array($targets) || empty($targets)) {
                    // Fallback to user's Slack user ID (DM) if no targets configured
                    $slack_user_id = get_user_meta($user_id, 'caelis_slack_user_id', true);
                    if (!empty($slack_user_id)) {
                        $targets = [$slack_user_id];
                    }
                }
            }
            
            // If still no targets, can't send
            if (empty($targets)) {
                error_log('PRM Slack: No targets specified for user ' . $user_id);
                return false;
            }
            
            // Send to all targets
            $success = true;
            foreach ($targets as $target_id) {
                $result = $this->send_via_web_api($config['bot_token'], $blocks, $target_id);
                if (!$result) {
                    $success = false;
                }
            }
            return $success;
        }
        
        // Fallback to webhook for legacy support
        if (isset($config['webhook_url']) && !empty($config['webhook_url'])) {
            return $this->send_via_webhook($config['webhook_url'], $blocks);
        }
        
        return false;
    }
    
    /**
     * Send message via Slack Web API (OAuth)
     */
    private function send_via_web_api($bot_token, $blocks, $target) {
        
        $payload = [
            'channel' => $target,
            'text'    => __('Your Important Dates for This Week', 'personal-crm'),
            'blocks'  => $blocks,
        ];
        
        $response = wp_remote_post('https://slack.com/api/chat.postMessage', [
            'body'    => json_encode($payload),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $bot_token,
            ],
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            error_log('PRM Slack Web API error: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 200 && $status_code < 300 && !empty($body['ok'])) {
            return true;
        }
        
        error_log('PRM Slack Web API error: ' . (isset($body['error']) ? $body['error'] : 'Unknown error'));
        return false;
    }
    
    /**
     * Send message via webhook (legacy support)
     */
    private function send_via_webhook($webhook_url, $blocks) {
        $logo_url = $this->get_logo_url();
        
        $payload = [
            'text'     => __('Your Important Dates for This Week', 'personal-crm'),
            'blocks'   => $blocks,
            'username' => 'Caelis',
        ];
        
        if ($logo_url) {
            $payload['icon_url'] = $logo_url;
        }
        
        $response = wp_remote_post($webhook_url, [
            'body'    => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            error_log('PRM Slack notification error: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        return $status_code >= 200 && $status_code < 300;
    }
    
    /**
     * Format Slack message blocks
     * Made public so it can be called from REST API for slash commands
     */
    public function format_slack_blocks($digest_data) {
        $blocks = [];
        $site_url = home_url();
        
        // Build text content for all sections
        $text_parts = [];
        
        // Today section
        $text_parts[] = '**' . __('Today', 'personal-crm') . '**';
        $text_parts[] = ''; // Empty line
        
        if (!empty($digest_data['today'])) {
            foreach ($digest_data['today'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                
                // Check if person name is in title
                $person_in_title = $this->find_person_in_title($date['title'], $date['related_people']);
                if ($person_in_title) {
                    // Replace name in title with link
                    $title_with_link = $this->replace_name_in_title_slack($date['title'], $person_in_title, $site_url);
                    $text_parts[] = sprintf("* %s - %s", $title_with_link, $date_formatted);
                } else {
                    // Show title normally
                    $text_parts[] = sprintf("* %s - %s", $date['title'], $date_formatted);
                }
            }
        } else {
            $text_parts[] = "* " . __('No reminders', 'personal-crm');
        }
        
        $text_parts[] = ''; // Empty line
        
        // Tomorrow section
        $text_parts[] = '**' . __('Tomorrow', 'personal-crm') . '**';
        $text_parts[] = ''; // Empty line
        
        if (!empty($digest_data['tomorrow'])) {
            foreach ($digest_data['tomorrow'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                
                $person_in_title = $this->find_person_in_title($date['title'], $date['related_people']);
                if ($person_in_title) {
                    $title_with_link = $this->replace_name_in_title_slack($date['title'], $person_in_title, $site_url);
                    $text_parts[] = sprintf("* %s - %s", $title_with_link, $date_formatted);
                } else {
                    $text_parts[] = sprintf("* %s - %s", $date['title'], $date_formatted);
                }
            }
        } else {
            $text_parts[] = "* " . __('No reminders', 'personal-crm');
        }
        
        $text_parts[] = ''; // Empty line
        
        // Rest of week section
        $text_parts[] = '**' . __('Rest of the week', 'personal-crm') . '**';
        $text_parts[] = ''; // Empty line
        
        if (!empty($digest_data['rest_of_week'])) {
            foreach ($digest_data['rest_of_week'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                
                $person_in_title = $this->find_person_in_title($date['title'], $date['related_people']);
                if ($person_in_title) {
                    $title_with_link = $this->replace_name_in_title_slack($date['title'], $person_in_title, $site_url);
                    $text_parts[] = sprintf("* %s - %s", $title_with_link, $date_formatted);
                } else {
                    $text_parts[] = sprintf("* %s - %s", $date['title'], $date_formatted);
                }
            }
        } else {
            $text_parts[] = "* " . __('No reminders', 'personal-crm');
        }
        
        // Combine all text parts into a single block
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => implode("\n", $text_parts),
            ],
        ];
        
        return $blocks;
    }
    
    /**
     * Format people names as clickable Slack markdown links
     */
    private function format_slack_people_links($people) {
        $site_url = home_url();
        $links = [];
        foreach ($people as $person) {
            $person_url = $site_url . '/people/' . $person['id'];
            $links[] = sprintf('<%s|%s>', $person_url, $person['name']);
        }
        return implode(', ', $links);
    }
    
    /**
     * Replace person name in title with a clickable link (for Slack)
     */
    private function replace_name_in_title_slack($title, $person, $site_url) {
        $person_name = $person['name'];
        $person_url = $site_url . '/people/' . $person['id'];
        $person_link = sprintf('<%s|%s>', $person_url, $person_name);
        
        // Replace the name in the title (case-insensitive)
        return preg_replace('/' . preg_quote($person_name, '/') . '/i', $person_link, $title, 1);
    }
    
    /**
     * Get logo URL for Slack messages
     * 
     * Note: Slack prefers PNG/JPG images. SVG may not display correctly.
     * The image must be publicly accessible.
     */
    private function get_logo_url() {
        // Try to use favicon or logo from theme
        $theme_url = get_template_directory_uri();
        
        // Try PNG first (better Slack support)
        $logo_png = $theme_url . '/logo.png';
        $logo_jpg = $theme_url . '/logo.jpg';
        $favicon_svg = $theme_url . '/favicon.svg';
        
        // Return PNG if it exists, otherwise try JPG, then SVG
        // In production, ensure logo.png exists for best Slack compatibility
        return $favicon_svg; // For now, use SVG. Replace with PNG/JPG path when available
    }
}

