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
                $people_links = $this->format_people_links($date['related_people'], $site_url);
                $html .= sprintf(
                    '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                    esc_html($date['title']),
                    esc_html($date_formatted)
                );
                if (!empty($people_links)) {
                    $html .= sprintf('<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links);
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
                $people_links = $this->format_people_links($date['related_people'], $site_url);
                $html .= sprintf(
                    '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                    esc_html($date['title']),
                    esc_html($date_formatted)
                );
                if (!empty($people_links)) {
                    $html .= sprintf('<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links);
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
                $people_links = $this->format_people_links($date['related_people'], $site_url);
                $html .= sprintf(
                    '<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
                    esc_html($date['title']),
                    esc_html($date_formatted)
                );
                if (!empty($people_links)) {
                    $html .= sprintf('<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links);
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
        // Also check if webhook is configured
        $webhook = get_user_meta($user_id, 'caelis_slack_webhook', true);
        return !empty($webhook);
    }
    
    public function get_user_config($user_id) {
        $webhook = get_user_meta($user_id, 'caelis_slack_webhook', true);
        if (empty($webhook)) {
            return false;
        }
        return [
            'webhook_url' => $webhook,
        ];
    }
    
    public function send($user_id, $digest_data) {
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
        
        $webhook_url = $config['webhook_url'];
        $blocks = $this->format_slack_blocks($digest_data);
        
        // Get logo URL - Slack prefers PNG/JPG over SVG
        $logo_url = $this->get_logo_url();
        
        $payload = [
            'text' => __('Your Important Dates for This Week', 'personal-crm'),
            'blocks' => $blocks,
            'username' => 'Caelis',
        ];
        
        // Add logo/icon if available - Slack requires publicly accessible image
        // Note: SVG may not work, but we'll try. For best results, use PNG/JPG
        if ($logo_url) {
            $payload['icon_url'] = $logo_url;
        }
        
        $response = wp_remote_post($webhook_url, [
            'body' => json_encode($payload),
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
     */
    private function format_slack_blocks($digest_data) {
        $blocks = [];
        $today_formatted = date_i18n(get_option('date_format'));
        
        // Header
        $blocks[] = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => sprintf(__('Your Important Dates - %s', 'personal-crm'), $today_formatted),
            ],
        ];
        
        // Today section
        if (!empty($digest_data['today'])) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*' . __('TODAY', 'personal-crm') . '*',
                ],
            ];
            
            foreach ($digest_data['today'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                $people_links = $this->format_slack_people_links($date['related_people']);
                $text = sprintf("• *%s* - %s", $date['title'], $date_formatted);
                if (!empty($people_links)) {
                    $text .= "\n  " . $people_links;
                }
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $text,
                    ],
                ];
            }
        }
        
        // Tomorrow section
        if (!empty($digest_data['tomorrow'])) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*' . __('TOMORROW', 'personal-crm') . '*',
                ],
            ];
            
            foreach ($digest_data['tomorrow'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                $people_links = $this->format_slack_people_links($date['related_people']);
                $text = sprintf("• *%s* - %s", $date['title'], $date_formatted);
                if (!empty($people_links)) {
                    $text .= "\n  " . $people_links;
                }
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $text,
                    ],
                ];
            }
        }
        
        // Rest of week section
        if (!empty($digest_data['rest_of_week'])) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*' . __('THIS WEEK', 'personal-crm') . '*',
                ],
            ];
            
            foreach ($digest_data['rest_of_week'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                $people_links = $this->format_slack_people_links($date['related_people']);
                $text = sprintf("• *%s* - %s", $date['title'], $date_formatted);
                if (!empty($people_links)) {
                    $text .= "\n  " . $people_links;
                }
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $text,
                    ],
                ];
            }
        }
        
        // Footer
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => sprintf(
                    __('<%s|Visit Caelis> to see more details.', 'personal-crm'),
                    home_url()
                ),
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

