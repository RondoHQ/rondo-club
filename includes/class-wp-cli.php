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
         * ## OPTIONS
         * 
         * [--user=<user_id>]
         * : User ID to send reminders to (if not specified, processes all users)
         * 
         * [--force]
         * : Force send regardless of preferred notification time
         * 
         * ## EXAMPLES
         * 
         *     wp prm reminders trigger
         *     wp prm reminders trigger --user=1
         *     wp prm reminders trigger --user=1 --force
         * 
         * @when after_wp_load
         */
        public function trigger($args, $assoc_args) {
            WP_CLI::log('Processing daily reminders...');
            
            $reminders = new PRM_Reminders();
            
            // Check if specific user ID is provided
            $specific_user_id = isset($assoc_args['user']) ? (int) $assoc_args['user'] : null;
            
            if ($specific_user_id) {
                $user = get_userdata($specific_user_id);
                if (!$user) {
                    WP_CLI::error(sprintf('User with ID %d not found.', $specific_user_id));
                    return;
                }
                
                // Verify user has dates they can access
                $digest_data = $reminders->get_weekly_digest($specific_user_id);
                $has_dates = !empty($digest_data['today']) ||
                             !empty($digest_data['tomorrow']) ||
                             !empty($digest_data['rest_of_week']);
                
                if (!$has_dates) {
                    WP_CLI::warning(sprintf('User %s (ID: %d) has no upcoming dates.', $user->display_name, $specific_user_id));
                    return;
                }
                
                WP_CLI::log(sprintf('Processing reminders for user: %s (ID: %d)', $user->display_name, $specific_user_id));
                $users_to_notify = [$specific_user_id];
            } else {
                // Get all users who should receive reminders
                $users_to_notify = $this->get_all_users_to_notify();
                
                if (empty($users_to_notify)) {
                    WP_CLI::warning('No users found to notify.');
                    WP_CLI::log('');
                    WP_CLI::log('This could mean:');
                    WP_CLI::log('1. No important dates exist in the system');
                    WP_CLI::log('2. Important dates exist but have no related people');
                    WP_CLI::log('3. People exist but are not linked to any dates');
                    WP_CLI::log('');
                    WP_CLI::log('Run with --debug flag for more details: wp prm reminders trigger --debug');
                    WP_CLI::log('Or specify a user: wp prm reminders trigger --user=1');
                    return;
                }
                
                WP_CLI::log(sprintf('Found %d user(s) to notify.', count($users_to_notify)));
            }
            
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
                
                // Check if it's the right time for this user (unless --force flag is set or specific user)
                if (!isset($assoc_args['force']) && !$specific_user_id) {
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
            // Use direct database query to bypass access control filters
            // WP-CLI runs without a logged-in user, so get_posts() would return nothing
            global $wpdb;
            
            $date_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND post_status = 'publish'",
                'important_date'
            ));
            
            WP_CLI::log(sprintf('Found %d important date(s) in system.', count($date_ids)));
            
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
                    WP_CLI::debug(sprintf('Date "%s" (ID: %d) has no related people.', $date_post->post_title, $date_post->ID));
                    continue;
                }
                
                // Ensure it's an array
                if (!is_array($related_people)) {
                    $related_people = [$related_people];
                }
                
                WP_CLI::debug(sprintf('Date "%s" (ID: %d) has %d related people.', $date_post->post_title, $date_post->ID, count($related_people)));
                
                // Get user IDs from people post authors
                foreach ($related_people as $person) {
                    $person_id = is_object($person) ? $person->ID : (is_array($person) ? $person['ID'] : $person);
                    
                    if (!$person_id) {
                        WP_CLI::debug('  Skipping invalid person ID.');
                        continue;
                    }
                    
                    $person_post = get_post($person_id);
                    if (!$person_post) {
                        WP_CLI::debug(sprintf('  Person ID %d not found.', $person_id));
                        continue;
                    }
                    
                    $author_id = (int) $person_post->post_author;
                    if ($author_id > 0) {
                        $user_ids[] = $author_id;
                        WP_CLI::debug(sprintf('  Added user ID %d (author of person "%s")', $author_id, $person_post->post_title));
                    }
                }
            }
            
            $unique_user_ids = array_unique($user_ids);
            WP_CLI::log(sprintf('Found %d unique user(s) to notify.', count($unique_user_ids)));
            
            return $unique_user_ids;
        }
    }
    
    /**
     * Migration WP-CLI Commands
     */
    class PRM_Migration_CLI_Command {
        
        /**
         * Migrate addresses from contact_info to dedicated addresses field
         * 
         * This command moves all address-type entries from the contact_info repeater
         * to the new structured addresses field.
         * 
         * ## OPTIONS
         * 
         * [--dry-run]
         * : Preview changes without making them
         * 
         * ## EXAMPLES
         * 
         *     wp prm migrate addresses
         *     wp prm migrate addresses --dry-run
         * 
         * @when after_wp_load
         */
        public function addresses($args, $assoc_args) {
            $dry_run = isset($assoc_args['dry-run']);
            
            if ($dry_run) {
                WP_CLI::log('DRY RUN MODE - No changes will be made');
            }
            
            WP_CLI::log('Migrating addresses from contact_info to addresses field...');
            
            // Get all people
            global $wpdb;
            $person_ids = $wpdb->get_col(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'person' 
                 AND post_status = 'publish'"
            );
            
            if (empty($person_ids)) {
                WP_CLI::warning('No people found in the system.');
                return;
            }
            
            WP_CLI::log(sprintf('Found %d person(s) to check.', count($person_ids)));
            
            $migrated_count = 0;
            $addresses_migrated = 0;
            $people_with_addresses = 0;
            
            foreach ($person_ids as $post_id) {
                $contact_info = get_field('contact_info', $post_id) ?: [];
                $existing_addresses = get_field('addresses', $post_id) ?: [];
                
                // Find address entries in contact_info
                $address_entries = array_filter($contact_info, function($item) {
                    return isset($item['contact_type']) && $item['contact_type'] === 'address';
                });
                
                if (empty($address_entries)) {
                    continue;
                }
                
                $people_with_addresses++;
                $person_title = get_the_title($post_id);
                WP_CLI::log(sprintf('Processing: %s (ID: %d) - Found %d address(es)', 
                    $person_title, $post_id, count($address_entries)));
                
                // Build new addresses array from address entries
                $new_addresses = $existing_addresses;
                $updated_contact_info = [];
                
                foreach ($contact_info as $item) {
                    if (isset($item['contact_type']) && $item['contact_type'] === 'address') {
                        // Migrate to addresses field
                        $new_addresses[] = [
                            'address_label' => $item['contact_label'] ?? '',
                            'street'        => $item['contact_value'] ?? '', // Put full address in street
                            'postal_code'   => '',
                            'city'          => '',
                            'state'         => '',
                            'country'       => '',
                        ];
                        $addresses_migrated++;
                        WP_CLI::log(sprintf('  → Moving: "%s" to addresses field', $item['contact_value'] ?? ''));
                    } else {
                        // Keep non-address entries
                        $updated_contact_info[] = $item;
                    }
                }
                
                if (!$dry_run) {
                    // Save updated addresses
                    update_field('addresses', $new_addresses, $post_id);
                    
                    // Save updated contact_info (without addresses)
                    update_field('contact_info', $updated_contact_info, $post_id);
                    
                    $migrated_count++;
                }
            }
            
            if ($dry_run) {
                WP_CLI::success(sprintf(
                    'DRY RUN: Would migrate %d address(es) from %d person(s)',
                    $addresses_migrated,
                    $people_with_addresses
                ));
            } else {
                WP_CLI::success(sprintf(
                    'Migration complete: Migrated %d address(es) from %d person(s)',
                    $addresses_migrated,
                    $migrated_count
                ));
            }
        }
    }
    
    /**
     * VCard WP-CLI Commands
     */
    class PRM_VCard_CLI_Command {
        
        /**
         * Get the vCard for a person (as CardDAV would serve it)
         * 
         * ## OPTIONS
         * 
         * <person_id>
         * : The ID of the person to export
         * 
         * [--output=<file>]
         * : Optional file path to save the vCard (otherwise outputs to stdout)
         * 
         * ## EXAMPLES
         * 
         *     wp prm vcard get 123
         *     wp prm vcard get 123 --output=/tmp/contact.vcf
         * 
         * @when after_wp_load
         */
        public function get($args, $assoc_args) {
            $person_id = (int) $args[0];
            
            if (!$person_id) {
                WP_CLI::error('Please provide a valid person ID.');
                return;
            }
            
            $person = get_post($person_id);
            
            if (!$person) {
                WP_CLI::error(sprintf('Person with ID %d not found.', $person_id));
                return;
            }
            
            if ($person->post_type !== 'person') {
                WP_CLI::error(sprintf('Post ID %d is not a person (it is a %s).', $person_id, $person->post_type));
                return;
            }
            
            // Generate vCard using the same method CardDAV uses
            $vcard = PRM_VCard_Export::generate($person);
            
            if (empty($vcard)) {
                WP_CLI::error('Failed to generate vCard.');
                return;
            }
            
            // Output to file or stdout
            if (isset($assoc_args['output'])) {
                $file_path = $assoc_args['output'];
                $result = file_put_contents($file_path, $vcard);
                
                if ($result === false) {
                    WP_CLI::error(sprintf('Failed to write to file: %s', $file_path));
                    return;
                }
                
                WP_CLI::success(sprintf('vCard saved to: %s (%d bytes)', $file_path, $result));
            } else {
                // Output to stdout
                WP_CLI::log($vcard);
            }
        }
        
        /**
         * Parse a vCard file and show what would be imported
         * 
         * ## OPTIONS
         * 
         * <file>
         * : Path to the vCard file to parse
         * 
         * ## EXAMPLES
         * 
         *     wp prm vcard parse /tmp/contact.vcf
         * 
         * @when after_wp_load
         */
        public function parse($args, $assoc_args) {
            $file_path = $args[0];
            
            if (!file_exists($file_path)) {
                WP_CLI::error(sprintf('File not found: %s', $file_path));
                return;
            }
            
            $vcard_data = file_get_contents($file_path);
            
            if (empty($vcard_data)) {
                WP_CLI::error('File is empty or could not be read.');
                return;
            }
            
            // Parse the vCard
            $parsed = PRM_VCard_Export::parse($vcard_data);
            
            if (empty($parsed)) {
                WP_CLI::error('Failed to parse vCard.');
                return;
            }
            
            WP_CLI::log('Parsed vCard data:');
            WP_CLI::log('');
            
            if (!empty($parsed['first_name'])) {
                WP_CLI::log(sprintf('First Name: %s', $parsed['first_name']));
            }
            if (!empty($parsed['last_name'])) {
                WP_CLI::log(sprintf('Last Name: %s', $parsed['last_name']));
            }
            if (!empty($parsed['full_name'])) {
                WP_CLI::log(sprintf('Full Name: %s', $parsed['full_name']));
            }
            if (!empty($parsed['nickname'])) {
                WP_CLI::log(sprintf('Nickname: %s', $parsed['nickname']));
            }
            
            if (!empty($parsed['contact_info'])) {
                WP_CLI::log('');
                WP_CLI::log('Contact Info:');
                foreach ($parsed['contact_info'] as $contact) {
                    $type = $contact['contact_type'] ?? 'unknown';
                    $value = $contact['contact_value'] ?? '';
                    $label = $contact['contact_label'] ?? '';
                    $display = $label ? "{$type} ({$label})" : $type;
                    WP_CLI::log(sprintf('  %s: %s', $display, $value));
                }
            }
            
            if (!empty($parsed['addresses'])) {
                WP_CLI::log('');
                WP_CLI::log('Addresses:');
                foreach ($parsed['addresses'] as $addr) {
                    $parts = array_filter([
                        $addr['street'] ?? '',
                        $addr['city'] ?? '',
                        $addr['state'] ?? '',
                        $addr['postal_code'] ?? '',
                        $addr['country'] ?? '',
                    ]);
                    $label = $addr['address_label'] ?? 'Address';
                    WP_CLI::log(sprintf('  %s: %s', $label, implode(', ', $parts)));
                }
            }
            
            WP_CLI::log('');
            WP_CLI::success('vCard parsed successfully.');
        }
    }
    
    /**
     * Register WP-CLI commands
     */
    WP_CLI::add_command('prm reminders', 'PRM_Reminders_CLI_Command');
    WP_CLI::add_command('prm migrate', 'PRM_Migration_CLI_Command');
    WP_CLI::add_command('prm vcard', 'PRM_VCard_CLI_Command');
}

