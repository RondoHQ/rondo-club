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
    
    public function __construct() {
        // Register cron hook
        add_action('prm_daily_reminder_check', [$this, 'process_daily_reminders']);
        
        // Add custom cron schedule if needed
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
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
            $reminder_days = (int) get_field('reminder_days_before', $date_post->ID);
            
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
            
            // Calculate when to remind
            $remind_on = (clone $next_occurrence)->modify("-{$reminder_days} days");
            
            // Only include if remind_on is today or in the future
            if ($remind_on < $today) {
                // But the date itself might still be relevant
                if ($next_occurrence >= $today) {
                    $remind_on = $today;
                } else {
                    continue;
                }
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
                'remind_on'       => $remind_on->format('Y-m-d'),
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
        $upcoming = $this->get_upcoming_reminders(0); // Due today
        
        foreach ($upcoming as $reminder) {
            $this->send_reminder_notifications($reminder);
        }
        
        // Check and update expired work history entries
        $this->update_expired_work_history();
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
     * Send reminder notifications
     */
    private function send_reminder_notifications($reminder) {
        // Get users to notify (authors of related people posts)
        $users_to_notify = $this->get_users_to_notify($reminder['related_people']);
        
        foreach ($users_to_notify as $user_id) {
            $this->send_reminder_email($user_id, $reminder);
        }
    }
    
    /**
     * Get users who should be notified about a reminder
     */
    private function get_users_to_notify($people_data) {
        $user_ids = [];
        
        foreach ($people_data as $person) {
            $post = get_post($person['id']);
            
            if ($post) {
                $user_ids[] = (int) $post->post_author;
                
                // Also check shared_with
                $shared_with = get_field('shared_with', $person['id']) ?: [];
                
                foreach ($shared_with as $user) {
                    $shared_user_id = is_object($user) ? $user->ID : $user;
                    $user_ids[] = (int) $shared_user_id;
                }
            }
        }
        
        return array_unique($user_ids);
    }
    
    /**
     * Send reminder email to a user
     */
    private function send_reminder_email($user_id, $reminder) {
        $user = get_userdata($user_id);
        
        if (!$user || !$user->user_email) {
            return false;
        }
        
        // Check if user has reminders enabled (could add a user meta check here)
        
        $site_name = get_bloginfo('name');
        $subject = sprintf(
            __('[%s] Reminder: %s', 'personal-crm'),
            $site_name,
            $reminder['title']
        );
        
        $people_names = array_column($reminder['related_people'], 'name');
        $people_list = implode(', ', $people_names);
        
        $date_formatted = date_i18n(
            get_option('date_format'),
            strtotime($reminder['next_occurrence'])
        );
        
        $message = sprintf(
            __("Hello %s,\n\nThis is a reminder about: %s\n\nDate: %s\nPeople: %s\n\nVisit your Personal CRM to see more details.\n\n%s", 'personal-crm'),
            $user->display_name,
            $reminder['title'],
            $date_formatted,
            $people_list,
            home_url()
        );
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        return wp_mail($user->user_email, $subject, $message, $headers);
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
