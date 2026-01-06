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
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Format email message body
     */
    private function format_email_message($user, $digest_data) {
        $site_url = home_url();
        $today_formatted = date_i18n(get_option('date_format'));
        
        $message = sprintf(
            __("Hello %s,\n\nHere are your important dates for this week:\n\n", 'personal-crm'),
            $user->display_name
        );
        
        // Today section
        if (!empty($digest_data['today'])) {
            $message .= __("TODAY\n", 'personal-crm');
            foreach ($digest_data['today'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                $people_list = implode(', ', array_column($date['related_people'], 'name'));
                $message .= sprintf("• %s - %s\n", $date['title'], $date_formatted);
                if (!empty($people_list)) {
                    $message .= sprintf("  %s\n", $people_list);
                }
            }
            $message .= "\n";
        }
        
        // Tomorrow section
        if (!empty($digest_data['tomorrow'])) {
            $message .= __("TOMORROW\n", 'personal-crm');
            foreach ($digest_data['tomorrow'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                $people_list = implode(', ', array_column($date['related_people'], 'name'));
                $message .= sprintf("• %s - %s\n", $date['title'], $date_formatted);
                if (!empty($people_list)) {
                    $message .= sprintf("  %s\n", $people_list);
                }
            }
            $message .= "\n";
        }
        
        // Rest of week section
        if (!empty($digest_data['rest_of_week'])) {
            $message .= __("THIS WEEK\n", 'personal-crm');
            foreach ($digest_data['rest_of_week'] as $date) {
                $date_formatted = date_i18n(
                    get_option('date_format'),
                    strtotime($date['next_occurrence'])
                );
                $people_list = implode(', ', array_column($date['related_people'], 'name'));
                $message .= sprintf("• %s - %s\n", $date['title'], $date_formatted);
                if (!empty($people_list)) {
                    $message .= sprintf("  %s\n", $people_list);
                }
            }
            $message .= "\n";
        }
        
        $message .= sprintf(
            __("Visit Caelis to see more details.\n\n%s", 'personal-crm'),
            $site_url
        );
        
        return $message;
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
        
        $payload = [
            'text' => __('Your Important Dates for This Week', 'personal-crm'),
            'blocks' => $blocks,
        ];
        
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
                $people_list = implode(', ', array_column($date['related_people'], 'name'));
                $text = sprintf("• *%s* - %s", $date['title'], $date_formatted);
                if (!empty($people_list)) {
                    $text .= "\n  " . $people_list;
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
                $people_list = implode(', ', array_column($date['related_people'], 'name'));
                $text = sprintf("• *%s* - %s", $date['title'], $date_formatted);
                if (!empty($people_list)) {
                    $text .= "\n  " . $people_list;
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
                $people_list = implode(', ', array_column($date['related_people'], 'name'));
                $text = sprintf("• *%s* - %s", $date['title'], $date_formatted);
                if (!empty($people_list)) {
                    $text .= "\n  " . $people_list;
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
}

