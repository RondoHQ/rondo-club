<?php
/**
 * Reminders System
 * 
 * Handles date-based reminders and notifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Reminders {
    
    /**
     * Available notification channels
     */
    private $channels = [];
    
    public function __construct() {
        // Register cron hook
        add_action('prm_daily_reminder_check', [$this, 'process_daily_reminders']);
        
        // Add custom cron schedule if needed
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        
        // Initialize notification channels
        $this->channels = [
            new PRM_Email_Channel(),
            new PRM_Slack_Channel(),
        ];
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['prm_twice_daily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __('Twice Daily', 'personal-crm'),
        ];
        
        return $schedules;
    }
    
    /**
     * Get upcoming reminders
     */
    public function get_upcoming_reminders($days_ahead = 30) {
        $dates = get_posts([
            'post_type'      => 'important_date',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);
        
        $upcoming = [];
        $today = new DateTime('today', wp_timezone());
        $end_date = (clone $today)->modify("+{$days_ahead} days");
        
        foreach ($dates as $date_post) {
            $date_value = get_field('date_value', $date_post->ID);
            $is_recurring = get_field('is_recurring', $date_post->ID);
            
            if (!$date_value) {
                continue;
            }
            
            $next_occurrence = $this->calculate_next_occurrence($date_value, $is_recurring);
            
            if (!$next_occurrence) {
                continue;
            }
            
            // Check if the next occurrence falls within our window
            if ($next_occurrence > $end_date) {
                continue;
            }
            
            // Only include if next occurrence is today or in the future
            if ($next_occurrence < $today) {
                continue;
            }
            
            $related_people = get_field('related_people', $date_post->ID) ?: [];
            $people_data = [];
            
            foreach ($related_people as $person) {
                $person_id = is_object($person) ? $person->ID : $person;
                $people_data[] = [
                    'id'        => $person_id,
                    'name'      => html_entity_decode(get_the_title($person_id), ENT_QUOTES, 'UTF-8'),
                    'thumbnail' => get_the_post_thumbnail_url($person_id, 'thumbnail'),
                ];
            }
            
            $upcoming[] = [
                'id'              => $date_post->ID,
                'title'           => html_entity_decode($date_post->post_title, ENT_QUOTES, 'UTF-8'),
                'date_value'      => $date_value,
                'next_occurrence' => $next_occurrence->format('Y-m-d'),
                'days_until'      => (int) $today->diff($next_occurrence)->days,
                'is_recurring'    => (bool) $is_recurring,
                'date_type'       => wp_get_post_terms($date_post->ID, 'date_type', ['fields' => 'names']),
                'related_people'  => $people_data,
            ];
        }
        
        // Sort by next occurrence
        usort($upcoming, function($a, $b) {
            return strcmp($a['next_occurrence'], $b['next_occurrence']);
        });
        
        return $upcoming;
    }
    
    /**
     * Calculate the next occurrence of a date
     */
    public function calculate_next_occurrence($date_string, $is_recurring) {
        try {
            $date = DateTime::createFromFormat('Y-m-d', $date_string, wp_timezone());
            
            if (!$date) {
                // Try alternative formats
                $date = DateTime::createFromFormat('Ymd', $date_string, wp_timezone());
            }
            
            if (!$date) {
                return null;
            }
            
            $today = new DateTime('today', wp_timezone());
            
            if (!$is_recurring) {
                // One-time date: only return if today or in the future
                return $date >= $today ? $date : null;
            }
            
            // Recurring: find next occurrence (same month/day, this year or next)
            $this_year = (clone $date)->setDate(
                (int) $today->format('Y'),
                (int) $date->format('m'),
                (int) $date->format('d')
            );
            
            if ($this_year >= $today) {
                return $this_year;
            }
            
            // Already passed this year, return next year
            return $this_year->modify('+1 year');
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Process daily reminders (cron job)
     */
    public function process_daily_reminders() {
        // Get all users who should receive reminders
        $users_to_notify = $this->get_all_users_to_notify();
        
        $current_time = new DateTime('now', wp_timezone());
        $current_hour = (int) $current_time->format('H');
        
        foreach ($users_to_notify as $user_id) {
            // Check if it's the right time for this user
            if (!$this->should_send_now($user_id, $current_hour)) {
                continue;
            }
            
            // Get weekly digest for this user
            $digest_data = $this->get_weekly_digest($user_id);
            
            // Send via all enabled channels
            foreach ($this->channels as $channel) {
                if ($channel->is_enabled_for_user($user_id)) {
                    $channel->send($user_id, $digest_data);
                }
            }
        }
        
        // Check and update expired work history entries
        $this->update_expired_work_history();
    }
    
    /**
     * Check if reminders should be sent now for a user
     * 
     * @param int $user_id User ID
     * @param int $current_hour Current hour (0-23)
     * @return bool
     */
    private function should_send_now($user_id, $current_hour) {
        $preferred_time = get_user_meta($user_id, 'caelis_notification_time', true);
        
        // If no preference set, default to 9:00 AM
        if (empty($preferred_time)) {
            $preferred_time = '09:00';
        }
        
        // Parse preferred time
        list($preferred_hour, $preferred_minute) = explode(':', $preferred_time);
        $preferred_hour = (int) $preferred_hour;
        
        // Send if current hour matches preferred hour
        // This allows for a 1-hour window (e.g., if cron runs at 9:15, it will still send for 9:00 preference)
        return $current_hour === $preferred_hour;
    }
    
    /**
     * Get weekly digest for a user (today, tomorrow, rest of week)
     * 
     * @param int $user_id User ID
     * @return array Digest data with today, tomorrow, rest_of_week keys
     */
    public function get_weekly_digest($user_id) {
        $access_control = new PRM_Access_Control();
        $today = new DateTime('today', wp_timezone());
        $tomorrow = (clone $today)->modify('+1 day');
        $end_of_week = (clone $today)->modify('+7 days');
        
        // Get all dates
        $dates = get_posts([
            'post_type'      => 'important_date',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);
        
        $digest = [
            'today' => [],
            'tomorrow' => [],
            'rest_of_week' => [],
        ];
        
        foreach ($dates as $date_post) {
            $date_value = get_field('date_value', $date_post->ID);
            $is_recurring = get_field('is_recurring', $date_post->ID);
            
            if (!$date_value) {
                continue;
            }
            
            $next_occurrence = $this->calculate_next_occurrence($date_value, $is_recurring);
            
            if (!$next_occurrence) {
                continue;
            }
            
            // Check if date is within the week
            if ($next_occurrence > $end_of_week) {
                continue;
            }
            
            // Get related people and check access
            $related_people = get_field('related_people', $date_post->ID) ?: [];
            $people_data = [];
            $user_has_access = false;
            
            foreach ($related_people as $person) {
                $person_id = is_object($person) ? $person->ID : $person;
                
                // Only include if user can access this person
                if ($access_control->user_can_access_post($person_id, $user_id)) {
                    $user_has_access = true;
                    $people_data[] = [
                        'id'        => $person_id,
                        'name'      => html_entity_decode(get_the_title($person_id), ENT_QUOTES, 'UTF-8'),
                        'thumbnail' => get_the_post_thumbnail_url($person_id, 'thumbnail'),
                    ];
                }
            }
            
            // Skip if user doesn't have access to any related people
            if (!$user_has_access || empty($people_data)) {
                continue;
            }
            
            $date_item = [
                'id'              => $date_post->ID,
                'title'           => html_entity_decode($date_post->post_title, ENT_QUOTES, 'UTF-8'),
                'date_value'      => $date_value,
                'next_occurrence' => $next_occurrence->format('Y-m-d'),
                'days_until'      => (int) $today->diff($next_occurrence)->days,
                'is_recurring'    => (bool) $is_recurring,
                'date_type'       => wp_get_post_terms($date_post->ID, 'date_type', ['fields' => 'names']),
                'related_people'  => $people_data,
            ];
            
            // Categorize by when it occurs
            $occurrence_date = $next_occurrence->format('Y-m-d');
            $today_date = $today->format('Y-m-d');
            $tomorrow_date = $tomorrow->format('Y-m-d');
            
            if ($occurrence_date === $today_date) {
                $digest['today'][] = $date_item;
            } elseif ($occurrence_date === $tomorrow_date) {
                $digest['tomorrow'][] = $date_item;
            } elseif ($next_occurrence <= $end_of_week) {
                $digest['rest_of_week'][] = $date_item;
            }
        }
        
        // Sort each section by next occurrence
        foreach ($digest as $key => $items) {
            usort($digest[$key], function($a, $b) {
                return strcmp($a['next_occurrence'], $b['next_occurrence']);
            });
        }
        
        return $digest;
    }
    
    /**
     * Get all users who should receive reminders
     * (users who have created people with important dates)
     * 
     * @return array User IDs
     */
    private function get_all_users_to_notify() {
        // Use direct database query to bypass access control filters
        // Cron jobs need to see all dates regardless of user
        global $wpdb;
        
        $date_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = %s 
             AND post_status = 'publish'",
            'important_date'
        ));
        
        if (empty($date_ids)) {
            return [];
        }
        
        // Get full post objects
        $dates = array_map('get_post', $date_ids);
        
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
    
    /**
     * Update work history entries where is_current is true but end_date has passed
     */
    private function update_expired_work_history() {
        $people = get_posts([
            'post_type'      => 'person',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);
        
        $today = new DateTime('today', wp_timezone());
        $updated_count = 0;
        
        foreach ($people as $person) {
            $work_history = get_field('work_history', $person->ID) ?: [];
            
            if (empty($work_history)) {
                continue;
            }
            
            $needs_update = false;
            
            foreach ($work_history as $index => $job) {
                // Check if job is marked as current but has an end_date that has passed
                if (!empty($job['is_current']) && !empty($job['end_date'])) {
                    $end_date = DateTime::createFromFormat('Y-m-d', $job['end_date'], wp_timezone());
                    
                    if ($end_date && $end_date < $today) {
                        // End date has passed, mark as not current
                        $work_history[$index]['is_current'] = false;
                        $needs_update = true;
                    }
                }
            }
            
            if ($needs_update) {
                update_field('work_history', $work_history, $person->ID);
                $updated_count++;
            }
        }
        
        // Log if any updates were made (optional, for debugging)
        if ($updated_count > 0) {
            error_log(sprintf('PRM: Updated %d person(s) with expired work history entries', $updated_count));
        }
    }
    
    
    /**
     * Get reminders for a specific user
     */
    public function get_user_reminders($user_id, $days_ahead = 30) {
        $all_reminders = $this->get_upcoming_reminders($days_ahead);
        
        // Filter to only include reminders for people this user can access
        $access_control = new PRM_Access_Control();
        $user_reminders = [];
        
        foreach ($all_reminders as $reminder) {
            foreach ($reminder['related_people'] as $person) {
                if ($access_control->user_can_access_post($person['id'], $user_id)) {
                    $user_reminders[] = $reminder;
                    break; // Only add once per reminder
                }
            }
        }
        
        return $user_reminders;
    }
}
