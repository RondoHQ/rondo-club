<?php
/**
 * WP-CLI Commands for Caelis
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only load if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    
    /**
     * Reminders WP-CLI Commands
     */
    class PRM_Reminders_CLI_Command {
        
        /**
         * Trigger daily reminders manually
         * 
         * ## EXAMPLES
         * 
         *     wp prm reminders trigger
         * 
         * @when after_wp_load
         */
        public function trigger($args, $assoc_args) {
            WP_CLI::log('Processing daily reminders...');
            
            $reminders = new PRM_Reminders();
            
            // Get all users who should receive reminders
            $users_to_notify = $this->get_all_users_to_notify();
            
            if (empty($users_to_notify)) {
                WP_CLI::warning('No users found to notify.');
                return;
            }
            
            WP_CLI::log(sprintf('Found %d user(s) to notify.', count($users_to_notify)));
            
            $current_time = new DateTime('now', wp_timezone());
            $current_hour = (int) $current_time->format('H');
            
            $users_processed = 0;
            $notifications_sent = 0;
            $users_skipped = 0;
            
            foreach ($users_to_notify as $user_id) {
                $user = get_userdata($user_id);
                if (!$user) {
                    continue;
                }
                
                // Check if it's the right time for this user (unless --force flag is set)
                if (!isset($assoc_args['force'])) {
                    $preferred_time = get_user_meta($user_id, 'caelis_notification_time', true);
                    if (empty($preferred_time)) {
                        $preferred_time = '09:00';
                    }
                    
                    list($preferred_hour, $preferred_minute) = explode(':', $preferred_time);
                    $preferred_hour = (int) $preferred_hour;
                    
                    if ($current_hour !== $preferred_hour) {
                        WP_CLI::log(sprintf(
                            'Skipping user %s (ID: %d) - preferred time is %s, current hour is %d',
                            $user->display_name,
                            $user_id,
                            $preferred_time,
                            $current_hour
                        ));
                        $users_skipped++;
                        continue;
                    }
                }
                
                WP_CLI::log(sprintf('Processing reminders for user: %s (ID: %d)', $user->display_name, $user_id));
                
                // Get weekly digest for this user
                $digest_data = $reminders->get_weekly_digest($user_id);
                
                // Check if there are any dates to notify about
                $has_dates = !empty($digest_data['today']) ||
                             !empty($digest_data['tomorrow']) ||
                             !empty($digest_data['rest_of_week']);
                
                if (!$has_dates) {
                    WP_CLI::log(sprintf('  No upcoming dates for user %s', $user->display_name));
                    $users_processed++;
                    continue;
                }
                
                // Count dates
                $date_count = count($digest_data['today']) + 
                              count($digest_data['tomorrow']) + 
                              count($digest_data['rest_of_week']);
                
                WP_CLI::log(sprintf(
                    '  Found %d date(s): %d today, %d tomorrow, %d rest of week',
                    $date_count,
                    count($digest_data['today']),
                    count($digest_data['tomorrow']),
                    count($digest_data['rest_of_week'])
                ));
                
                // Send via all enabled channels
                $email_channel = new PRM_Email_Channel();
                $slack_channel = new PRM_Slack_Channel();
                
                $user_notifications_sent = 0;
                
                if ($email_channel->is_enabled_for_user($user_id)) {
                    if ($email_channel->send($user_id, $digest_data)) {
                        WP_CLI::log(sprintf('  ✓ Email sent to %s', $user->user_email));
                        $user_notifications_sent++;
                        $notifications_sent++;
                    } else {
                        WP_CLI::warning(sprintf('  ✗ Failed to send email to %s', $user->user_email));
                    }
                }
                
                if ($slack_channel->is_enabled_for_user($user_id)) {
                    if ($slack_channel->send($user_id, $digest_data)) {
                        WP_CLI::log(sprintf('  ✓ Slack notification sent'));
                        $user_notifications_sent++;
                        $notifications_sent++;
                    } else {
                        WP_CLI::warning(sprintf('  ✗ Failed to send Slack notification'));
                    }
                }
                
                if ($user_notifications_sent === 0) {
                    WP_CLI::log(sprintf('  ⚠ No notification channels enabled for user %s', $user->display_name));
                }
                
                $users_processed++;
            }
            
            WP_CLI::success(sprintf(
                'Completed: Processed %d user(s), sent %d notification(s), skipped %d user(s)',
                $users_processed,
                $notifications_sent,
                $users_skipped
            ));
        }
        
        /**
         * Get all users who should receive reminders
         * 
         * @return array User IDs
         */
        private function get_all_users_to_notify() {
            // Get all important dates
            $dates = get_posts([
                'post_type'      => 'important_date',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ]);
            
            $user_ids = [];
            
            foreach ($dates as $date_post) {
                // Get related people using ACF (handles repeater fields correctly)
                $related_people = get_field('related_people', $date_post->ID);
                
                if (empty($related_people)) {
                    continue;
                }
                
                // Ensure it's an array
                if (!is_array($related_people)) {
                    $related_people = [$related_people];
                }
                
                // Get user IDs from people post authors
                foreach ($related_people as $person) {
                    $person_id = is_object($person) ? $person->ID : (is_array($person) ? $person['ID'] : $person);
                    
                    if (!$person_id) {
                        continue;
                    }
                    
                    $person_post = get_post($person_id);
                    if ($person_post) {
                        $user_ids[] = (int) $person_post->post_author;
                    }
                }
            }
            
            return array_unique($user_ids);
        }
    }
    
    /**
     * Register WP-CLI commands
     */
    WP_CLI::add_command('prm reminders', 'PRM_Reminders_CLI_Command');
}

