<?php
/**
 * Calendar Contact Matcher Class
 *
 * Matches calendar event attendees to CRM contacts using email-first matching
 * with fuzzy name fallback. Uses transient-based caching for performance.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Calendar_Matcher {

    /**
     * Transient key prefix for email lookup cache
     */
    const CACHE_PREFIX = 'prm_email_lookup_';

    /**
     * Cache expiration time (24 hours)
     */
    const CACHE_EXPIRATION = DAY_IN_SECONDS;

    /**
     * Match attendees from a calendar event to CRM people
     *
     * @param int   $user_id   WordPress user ID (owner of contacts)
     * @param array $attendees Array of [{email, name, status}]
     * @return array Array of [{person_id, match_type, confidence, attendee_email}]
     */
    public static function match_attendees(int $user_id, array $attendees): array {
        if (empty($attendees)) {
            return [];
        }

        // Build email lookup cache
        $lookup = self::get_email_lookup($user_id);
        $matches = [];

        foreach ($attendees as $attendee) {
            $match = self::match_single($user_id, $attendee, $lookup);
            if ($match !== null) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    /**
     * Build email->person_id lookup cache for a user
     * Uses transient with 24-hour expiration
     *
     * @param int  $user_id WordPress user ID
     * @param bool $force   Force rebuild even if cache exists
     * @return array Email->person_id lookup map
     */
    public static function get_email_lookup(int $user_id, bool $force = false): array {
        $cache_key = self::CACHE_PREFIX . $user_id;

        // Check cache first
        if (!$force) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Build lookup from user's people
        $lookup = [];

        $people = get_posts([
            'post_type'      => 'person',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'publish',
        ]);

        foreach ($people as $person_id) {
            // Get contact_info repeater field
            $contact_info = get_field('contact_info', $person_id);

            if (!is_array($contact_info)) {
                continue;
            }

            foreach ($contact_info as $contact) {
                // Only process email contacts
                if (empty($contact['contact_type']) || $contact['contact_type'] !== 'email') {
                    continue;
                }

                if (empty($contact['contact_value'])) {
                    continue;
                }

                // Normalize email: lowercase and trim
                $email = strtolower(trim($contact['contact_value']));

                // Store in lookup (first person wins if duplicate emails)
                if (!isset($lookup[$email])) {
                    $lookup[$email] = $person_id;
                }
            }
        }

        // Cache the lookup
        set_transient($cache_key, $lookup, self::CACHE_EXPIRATION);

        return $lookup;
    }

    /**
     * Invalidate email lookup cache (call on contact save)
     *
     * @param int $user_id WordPress user ID
     */
    public static function invalidate_cache(int $user_id): void {
        $cache_key = self::CACHE_PREFIX . $user_id;
        delete_transient($cache_key);
    }

    /**
     * Re-match all calendar events for a user against their contacts
     *
     * Called when a person's email addresses change to update event matches.
     *
     * @param int $user_id WordPress user ID
     * @return int Number of events re-matched
     */
    public static function rematch_events_for_user(int $user_id): int {
        $events = get_posts([
            'post_type'      => 'calendar_event',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => '_attendees',
                    'compare' => '!=',
                    'value'   => '',
                ],
            ],
        ]);

        $count = 0;

        foreach ($events as $event) {
            $attendees_json = get_post_meta($event->ID, '_attendees', true);

            if (empty($attendees_json)) {
                continue;
            }

            $attendees = json_decode($attendees_json, true);

            if (!is_array($attendees) || empty($attendees)) {
                continue;
            }

            // Re-match attendees against updated contact list
            $matches = self::match_attendees($user_id, $attendees);

            // Update matched people meta
            update_post_meta($event->ID, '_matched_people', wp_json_encode($matches));

            $count++;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("PRM_Calendar_Matcher: Re-matched {$count} calendar events for user {$user_id}");
        }

        return $count;
    }

    /**
     * Handle person save - invalidate cache and re-match events
     *
     * Called via acf/save_post hook when a person is saved.
     *
     * @param int $post_id Person post ID
     */
    public static function on_person_saved(int $post_id): void {
        // Verify it's a person post type
        if (get_post_type($post_id) !== 'person') {
            return;
        }

        // Get the post author (user who owns this contact)
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $user_id = (int) $post->post_author;

        // Invalidate the email lookup cache
        self::invalidate_cache($user_id);

        // Re-match all calendar events for this user
        self::rematch_events_for_user($user_id);
    }

    /**
     * Match single attendee - try email then fuzzy name
     *
     * @param int   $user_id  WordPress user ID
     * @param array $attendee Single attendee {email, name}
     * @param array $lookup   Email->person_id lookup map
     * @return array|null Match data or null if no match
     */
    private static function match_single(int $user_id, array $attendee, array $lookup): ?array {
        $email = isset($attendee['email']) ? strtolower(trim($attendee['email'])) : '';
        $name = isset($attendee['name']) ? trim($attendee['name']) : '';

        // 1. Try exact email match (100% confidence)
        if (!empty($email) && isset($lookup[$email])) {
            return [
                'person_id'      => $lookup[$email],
                'match_type'     => 'email_exact',
                'confidence'     => 100,
                'attendee_email' => $email,
            ];
        }

        // 2. Try fuzzy name match if we have a name
        if (!empty($name)) {
            $name_match = self::fuzzy_name_match($user_id, $name);
            if ($name_match !== null) {
                $name_match['attendee_email'] = $email ?: null;
                return $name_match;
            }
        }

        return null;
    }

    /**
     * Try fuzzy name match when email fails
     *
     * @param int    $user_id WordPress user ID
     * @param string $name    Attendee display name
     * @return array|null Match with person_id, match_type, confidence
     */
    private static function fuzzy_name_match(int $user_id, string $name): ?array {
        // Parse name into first/last
        $parsed = self::parse_name($name);
        $first_name = $parsed['first'];
        $last_name = $parsed['last'];

        if (empty($first_name)) {
            return null;
        }

        // Query user's people
        $people = get_posts([
            'post_type'      => 'person',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        if (empty($people)) {
            return null;
        }

        // Check for exact full name match first (80% confidence)
        foreach ($people as $person) {
            $person_first = strtolower(trim(get_field('first_name', $person->ID) ?: ''));
            $person_last = strtolower(trim(get_field('last_name', $person->ID) ?: ''));
            $person_full = trim($person_first . ' ' . $person_last);

            $search_first = strtolower($first_name);
            $search_last = strtolower($last_name);
            $search_full = trim($search_first . ' ' . $search_last);

            // Exact full name match
            if (!empty($search_full) && !empty($person_full) && $person_full === $search_full) {
                return [
                    'person_id'  => $person->ID,
                    'match_type' => 'name_exact',
                    'confidence' => 80,
                ];
            }
        }

        // Check for first name only match if unique (60% confidence)
        $first_name_lower = strtolower($first_name);
        $first_name_matches = [];

        foreach ($people as $person) {
            $person_first = strtolower(trim(get_field('first_name', $person->ID) ?: ''));
            if ($person_first === $first_name_lower) {
                $first_name_matches[] = $person->ID;
            }
        }

        // Only use first name match if it's unique
        if (count($first_name_matches) === 1) {
            return [
                'person_id'  => $first_name_matches[0],
                'match_type' => 'name_partial',
                'confidence' => 60,
            ];
        }

        // Check for Levenshtein distance <= 2 on full name (50% confidence)
        $search_full = strtolower(trim($first_name . ' ' . $last_name));

        foreach ($people as $person) {
            $person_first = strtolower(trim(get_field('first_name', $person->ID) ?: ''));
            $person_last = strtolower(trim(get_field('last_name', $person->ID) ?: ''));
            $person_full = trim($person_first . ' ' . $person_last);

            if (empty($person_full) || empty($search_full)) {
                continue;
            }

            $distance = levenshtein($search_full, $person_full);

            if ($distance <= 2 && $distance > 0) {
                return [
                    'person_id'  => $person->ID,
                    'match_type' => 'name_fuzzy',
                    'confidence' => 50,
                ];
            }
        }

        return null;
    }

    /**
     * Parse attendee name into first/last components
     *
     * Handles formats:
     * - "First Last"
     * - "Last, First"
     * - "First" (first name only)
     *
     * @param string $name Full name string
     * @return array ['first' => string, 'last' => string]
     */
    private static function parse_name(string $name): array {
        $name = trim($name);

        if (empty($name)) {
            return ['first' => '', 'last' => ''];
        }

        // Handle "Last, First" format
        if (strpos($name, ',') !== false) {
            $parts = array_map('trim', explode(',', $name, 2));
            return [
                'first' => $parts[1] ?? '',
                'last'  => $parts[0] ?? '',
            ];
        }

        // Handle "First Last" or "First" format
        $parts = preg_split('/\s+/', $name, 2);
        return [
            'first' => $parts[0] ?? '',
            'last'  => $parts[1] ?? '',
        ];
    }
}
