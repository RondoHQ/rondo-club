<?php
/**
 * Google Contacts CSV Import Handler
 *
 * Imports contacts from Google Contacts CSV export files.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Google_Contacts_Import {

    /**
     * Import statistics
     */
    private array $stats = [
        'contacts_imported'     => 0,
        'contacts_updated'      => 0,
        'contacts_skipped'      => 0,
        'companies_created'     => 0,
        'dates_created'         => 0,
        'notes_created'         => 0,
        'errors'                => [],
    ];

    /**
     * Company name to ID mapping
     */
    private array $company_map = [];

    /**
     * CSV column headers
     */
    private array $headers = [];

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes for import
     */
    public function register_routes() {
        register_rest_route('prm/v1', '/import/google-contacts', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_import'],
            'permission_callback' => [$this, 'check_import_permission'],
        ]);

        register_rest_route('prm/v1', '/import/google-contacts/validate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'validate_import'],
            'permission_callback' => [$this, 'check_import_permission'],
        ]);
    }

    /**
     * Check if user can perform import
     */
    public function check_import_permission() {
        return current_user_can('manage_options') || current_user_can('edit_posts');
    }

    /**
     * Validate import file without importing
     */
    public function validate_import($request) {
        $file = $request->get_file_params()['file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload failed.', 'personal-crm'), ['status' => 400]);
        }

        $csv_content = file_get_contents($file['tmp_name']);

        if (empty($csv_content)) {
            return new WP_Error('empty_file', __('File is empty.', 'personal-crm'), ['status' => 400]);
        }

        // Parse CSV to validate and get summary
        $contacts = $this->parse_csv($csv_content);

        if (empty($contacts)) {
            return new WP_Error('invalid_format', __('No valid contacts found in CSV. Make sure you exported from Google Contacts.', 'personal-crm'), ['status' => 400]);
        }

        // Check for required Google Contacts columns
        $required_columns = ['Given Name', 'Family Name'];
        $has_name_columns = false;
        foreach ($required_columns as $col) {
            if (in_array($col, $this->headers)) {
                $has_name_columns = true;
                break;
            }
        }

        if (!$has_name_columns && !in_array('Name', $this->headers)) {
            return new WP_Error('invalid_format', __('This doesn\'t appear to be a Google Contacts export. Missing name columns.', 'personal-crm'), ['status' => 400]);
        }

        $summary = $this->get_import_summary($contacts);

        return rest_ensure_response([
            'valid'   => true,
            'version' => 'google-contacts-csv',
            'summary' => $summary,
        ]);
    }

    /**
     * Get summary of what will be imported
     */
    private function get_import_summary(array $contacts): array {
        $valid_contacts = 0;
        $companies = [];
        $birthdays = 0;
        $notes = 0;

        foreach ($contacts as $contact) {
            $first = $contact['Given Name'] ?? '';
            $last = $contact['Family Name'] ?? '';
            $name = $contact['Name'] ?? '';

            if (!empty($first) || !empty($last) || !empty($name)) {
                $valid_contacts++;
            }

            $org = $contact['Organization 1 - Name'] ?? '';
            if (!empty($org)) {
                $companies[$org] = true;
            }

            if (!empty($contact['Birthday'])) {
                $birthdays++;
            }

            if (!empty($contact['Notes'])) {
                $notes++;
            }
        }

        return [
            'contacts'        => $valid_contacts,
            'companies_count' => count($companies),
            'birthdays'       => $birthdays,
            'notes'           => $notes,
        ];
    }

    /**
     * Handle the import request
     */
    public function handle_import($request) {
        // Increase limits for large imports
        @set_time_limit(600);
        wp_raise_memory_limit('admin');

        $file = $request->get_file_params()['file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload failed.', 'personal-crm'), ['status' => 400]);
        }

        $csv_content = file_get_contents($file['tmp_name']);

        if (empty($csv_content)) {
            return new WP_Error('empty_file', __('File is empty.', 'personal-crm'), ['status' => 400]);
        }

        // Parse and import contacts
        $contacts = $this->parse_csv($csv_content);
        $this->import_contacts($contacts);

        return rest_ensure_response([
            'success' => true,
            'stats'   => $this->stats,
        ]);
    }

    /**
     * Parse CSV content into array of contacts
     */
    private function parse_csv(string $content): array {
        $contacts = [];
        
        // Handle BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // Split into lines, handling various line endings
        $lines = preg_split('/\r\n|\r|\n/', $content);
        
        if (empty($lines)) {
            return $contacts;
        }
        
        // First line is headers
        $this->headers = str_getcsv(array_shift($lines));
        
        // Parse each row
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $values = str_getcsv($line);
            
            // Create associative array
            $contact = [];
            foreach ($this->headers as $i => $header) {
                $contact[$header] = $values[$i] ?? '';
            }
            
            $contacts[] = $contact;
        }
        
        return $contacts;
    }

    /**
     * Import parsed contacts
     */
    private function import_contacts(array $contacts): void {
        foreach ($contacts as $contact) {
            $this->import_single_contact($contact);
        }
    }

    /**
     * Import a single contact
     */
    private function import_single_contact(array $contact): void {
        // Extract name
        $first_name = trim($contact['Given Name'] ?? '');
        $last_name = trim($contact['Family Name'] ?? '');
        
        // Fallback to Name field if Given/Family Name are empty
        if (empty($first_name) && empty($last_name) && !empty($contact['Name'])) {
            $name_parts = explode(' ', trim($contact['Name']), 2);
            $first_name = $name_parts[0] ?? '';
            $last_name = $name_parts[1] ?? '';
        }

        if (empty($first_name) && empty($last_name)) {
            $this->stats['contacts_skipped']++;
            return;
        }

        // Check if contact already exists
        $existing = $this->find_existing_person($first_name, $last_name);
        $is_update = false;

        if ($existing) {
            $post_id = $existing;
            $is_update = true;
            $this->stats['contacts_updated']++;
        } else {
            $post_id = wp_insert_post([
                'post_type'   => 'person',
                'post_status' => 'publish',
                'post_title'  => trim($first_name . ' ' . $last_name),
                'post_author' => get_current_user_id(),
            ]);

            if (is_wp_error($post_id)) {
                $this->stats['errors'][] = "Failed to create contact: {$first_name} {$last_name}";
                return;
            }

            $this->stats['contacts_imported']++;
        }

        // Set basic ACF fields
        update_field('first_name', $first_name, $post_id);
        update_field('last_name', $last_name, $post_id);

        // Nickname
        $nickname = trim($contact['Nickname'] ?? '');
        if (!empty($nickname)) {
            update_field('nickname', $nickname, $post_id);
        }

        // Handle company/work history
        $org_name = trim($contact['Organization 1 - Name'] ?? '');
        $job_title = trim($contact['Organization 1 - Title'] ?? '');

        if (!empty($org_name) || !empty($job_title)) {
            $company_id = null;
            if (!empty($org_name)) {
                $company_id = $this->get_or_create_company($org_name);
            }

            if ($company_id || $job_title) {
                $work_history = [
                    [
                        'company'    => $company_id,
                        'job_title'  => $job_title,
                        'is_current' => true,
                    ],
                ];
                update_field('work_history', $work_history, $post_id);
            }
        }

        // Import contact info
        $this->import_contact_info($post_id, $contact);

        // Import birthday
        $birthday = trim($contact['Birthday'] ?? '');
        if (!empty($birthday)) {
            $parsed_date = $this->parse_date($birthday);
            if ($parsed_date) {
                $this->import_birthday($post_id, $parsed_date, $first_name, $last_name);
            }
        }

        // Import notes
        $notes = trim($contact['Notes'] ?? '');
        if (!empty($notes)) {
            $this->import_note($post_id, $notes);
        }
    }

    /**
     * Import contact information (emails, phones, addresses)
     */
    private function import_contact_info(int $post_id, array $contact): void {
        $contact_info = [];

        // Import emails (Google uses E-mail 1 - Value, E-mail 2 - Value, etc.)
        for ($i = 1; $i <= 5; $i++) {
            $email = trim($contact["E-mail {$i} - Value"] ?? '');
            $type = trim($contact["E-mail {$i} - Type"] ?? '');
            
            if (!empty($email)) {
                $contact_info[] = [
                    'contact_type'  => 'email',
                    'contact_label' => $this->format_label($type),
                    'contact_value' => $email,
                ];
            }
        }

        // Import phones (Google uses Phone 1 - Value, Phone 2 - Value, etc.)
        for ($i = 1; $i <= 5; $i++) {
            $phone = trim($contact["Phone {$i} - Value"] ?? '');
            $type = strtolower(trim($contact["Phone {$i} - Type"] ?? ''));
            
            if (!empty($phone)) {
                $contact_type = 'phone';
                if (strpos($type, 'mobile') !== false || strpos($type, 'cell') !== false) {
                    $contact_type = 'mobile';
                }
                
                $contact_info[] = [
                    'contact_type'  => $contact_type,
                    'contact_label' => $this->format_label($type),
                    'contact_value' => $phone,
                ];
            }
        }

        // Import addresses (Google uses Address 1 - Formatted, Address 2 - Formatted, etc.)
        for ($i = 1; $i <= 3; $i++) {
            $address = trim($contact["Address {$i} - Formatted"] ?? '');
            $type = trim($contact["Address {$i} - Type"] ?? '');
            
            // If no formatted address, try to build from components
            if (empty($address)) {
                $parts = array_filter([
                    $contact["Address {$i} - Street"] ?? '',
                    $contact["Address {$i} - City"] ?? '',
                    $contact["Address {$i} - Region"] ?? '',
                    $contact["Address {$i} - Postal Code"] ?? '',
                    $contact["Address {$i} - Country"] ?? '',
                ]);
                $address = implode(', ', $parts);
            }
            
            if (!empty($address)) {
                $contact_info[] = [
                    'contact_type'  => 'address',
                    'contact_label' => $this->format_label($type),
                    'contact_value' => $address,
                ];
            }
        }

        // Import websites (Google uses Website 1 - Value, etc.)
        for ($i = 1; $i <= 3; $i++) {
            $url = trim($contact["Website {$i} - Value"] ?? '');
            $type = trim($contact["Website {$i} - Type"] ?? '');
            
            if (!empty($url)) {
                $contact_info[] = [
                    'contact_type'  => $this->detect_url_type($url),
                    'contact_label' => $this->format_label($type),
                    'contact_value' => $url,
                ];
            }
        }

        if (!empty($contact_info)) {
            update_field('contact_info', $contact_info, $post_id);
        }
    }

    /**
     * Format label for display
     */
    private function format_label(string $type): string {
        $type = strtolower(trim($type));
        
        // Remove common prefixes
        $type = str_replace(['* ', ':: '], '', $type);
        
        // Skip generic labels
        if (in_array($type, ['', 'other', 'custom'])) {
            return '';
        }
        
        return ucfirst($type);
    }

    /**
     * Detect URL type from URL content
     */
    private function detect_url_type(string $url): string {
        $url_lower = strtolower($url);
        
        if (strpos($url_lower, 'linkedin.com') !== false) {
            return 'linkedin';
        }
        if (strpos($url_lower, 'twitter.com') !== false || strpos($url_lower, 'x.com') !== false) {
            return 'twitter';
        }
        if (strpos($url_lower, 'facebook.com') !== false) {
            return 'facebook';
        }
        if (strpos($url_lower, 'instagram.com') !== false) {
            return 'instagram';
        }
        
        return 'website';
    }

    /**
     * Parse date from various formats
     */
    private function parse_date(string $date): string {
        $date = trim($date);
        
        // Try various formats Google might use
        // YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match)) {
            return $date;
        }
        
        // MM/DD/YYYY or M/D/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $match)) {
            return sprintf('%s-%02d-%02d', $match[3], $match[1], $match[2]);
        }
        
        // DD/MM/YYYY (alternative)
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $match)) {
            return sprintf('%s-%02d-%02d', $match[3], $match[2], $match[1]);
        }
        
        // Try strtotime
        $timestamp = strtotime($date);
        if ($timestamp) {
            return date('Y-m-d', $timestamp);
        }
        
        return '';
    }

    /**
     * Import birthday as important_date
     */
    private function import_birthday(int $post_id, string $date, string $first_name, string $last_name): void {
        $full_name = trim($first_name . ' ' . $last_name);

        // Check if birthday already exists
        $existing = get_posts([
            'post_type'      => 'important_date',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'related_people',
                    'value'   => '"' . $post_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
            'tax_query'      => [
                [
                    'taxonomy' => 'date_type',
                    'field'    => 'slug',
                    'terms'    => 'birthday',
                ],
            ],
        ]);

        if (!empty($existing)) {
            return;
        }

        $title = sprintf(__("%s's Birthday", 'personal-crm'), $full_name);

        $date_post_id = wp_insert_post([
            'post_type'   => 'important_date',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($date_post_id)) {
            return;
        }

        update_field('date_value', $date, $date_post_id);
        update_field('is_recurring', true, $date_post_id);
        update_field('related_people', [$post_id], $date_post_id);
        update_field('reminder_days_before', 7, $date_post_id);

        // Ensure the birthday term exists
        $term = term_exists('birthday', 'date_type');
        if (!$term) {
            $term = wp_insert_term('Birthday', 'date_type', ['slug' => 'birthday']);
        }
        if ($term && !is_wp_error($term)) {
            $term_id = is_array($term) ? $term['term_id'] : $term;
            wp_set_post_terms($date_post_id, [(int) $term_id], 'date_type');
        }

        $this->stats['dates_created']++;
    }

    /**
     * Import note as comment
     */
    private function import_note(int $post_id, string $content): void {
        $comment_id = wp_insert_comment([
            'comment_post_ID'  => $post_id,
            'comment_content'  => $content,
            'comment_type'     => PRM_Comment_Types::TYPE_NOTE,
            'user_id'          => get_current_user_id(),
            'comment_approved' => 1,
            'comment_date'     => current_time('mysql'),
            'comment_date_gmt' => current_time('mysql', true),
        ]);

        if ($comment_id) {
            $this->stats['notes_created']++;
        }
    }

    /**
     * Find an existing person by name
     */
    private function find_existing_person(string $first_name, string $last_name): ?int {
        $query = new WP_Query([
            'post_type'      => 'person',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'first_name',
                    'value'   => $first_name,
                    'compare' => '=',
                ],
                [
                    'key'     => 'last_name',
                    'value'   => $last_name,
                    'compare' => '=',
                ],
            ],
        ]);

        if ($query->have_posts()) {
            return $query->posts[0]->ID;
        }

        return null;
    }

    /**
     * Get or create a company
     */
    private function get_or_create_company(string $name): int {
        if (isset($this->company_map[$name])) {
            return $this->company_map[$name];
        }

        // Check if company exists
        $existing = get_page_by_title($name, OBJECT, 'company');
        if ($existing) {
            $this->company_map[$name] = $existing->ID;
            return $existing->ID;
        }

        // Create new company
        $post_id = wp_insert_post([
            'post_type'   => 'company',
            'post_status' => 'publish',
            'post_title'  => $name,
            'post_author' => get_current_user_id(),
        ]);

        if (!is_wp_error($post_id)) {
            $this->company_map[$name] = $post_id;
            $this->stats['companies_created']++;
            return $post_id;
        }

        return 0;
    }
}

