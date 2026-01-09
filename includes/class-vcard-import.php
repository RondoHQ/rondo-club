<?php
/**
 * vCard Import Handler
 *
 * Imports contacts from vCard (.vcf) files.
 * Supports vCard versions 2.1, 3.0, and 4.0.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_VCard_Import {

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
        'photos_imported'       => 0,
        'errors'                => [],
    ];

    /**
     * Company name to ID mapping
     */
    private array $company_map = [];

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes for import
     */
    public function register_routes() {
        register_rest_route('prm/v1', '/import/vcard', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_import'],
            'permission_callback' => [$this, 'check_import_permission'],
        ]);

        register_rest_route('prm/v1', '/import/vcard/validate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'validate_import'],
            'permission_callback' => [$this, 'check_import_permission'],
        ]);

        register_rest_route('prm/v1', '/import/vcard/parse', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'parse_single_contact'],
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

        $vcf_content = file_get_contents($file['tmp_name']);

        if (empty($vcf_content)) {
            return new WP_Error('empty_file', __('File is empty.', 'personal-crm'), ['status' => 400]);
        }

        // Check if it's a valid vCard file
        if (strpos($vcf_content, 'BEGIN:VCARD') === false) {
            return new WP_Error('invalid_format', __('Invalid vCard format. File must contain BEGIN:VCARD.', 'personal-crm'), ['status' => 400]);
        }

        // Parse vCards to get summary
        $vcards = $this->parse_vcards($vcf_content);
        $summary = $this->get_import_summary($vcards);

        return rest_ensure_response([
            'valid'   => true,
            'version' => 'vcard',
            'summary' => $summary,
        ]);
    }

    /**
     * Parse a single contact from a vCard file and return the data
     * Used for pre-filling the person form
     */
    public function parse_single_contact($request) {
        $file = $request->get_file_params()['file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload failed.', 'personal-crm'), ['status' => 400]);
        }

        $vcf_content = file_get_contents($file['tmp_name']);

        if (empty($vcf_content)) {
            return new WP_Error('empty_file', __('File is empty.', 'personal-crm'), ['status' => 400]);
        }

        // Check if it's a valid vCard file
        if (strpos($vcf_content, 'BEGIN:VCARD') === false) {
            return new WP_Error('invalid_format', __('Invalid vCard format. File must contain BEGIN:VCARD.', 'personal-crm'), ['status' => 400]);
        }

        // Parse vCards and get the first one
        $vcards = $this->parse_vcards($vcf_content);

        if (empty($vcards)) {
            return new WP_Error('no_contacts', __('No contacts found in file.', 'personal-crm'), ['status' => 400]);
        }

        // Get the first contact
        $vcard = $vcards[0];

        // Get primary email
        $email = '';
        if (!empty($vcard['emails'])) {
            $email = $vcard['emails'][0]['value'] ?? '';
        }

        // Get primary phone with type
        $phone = '';
        $phone_type = 'phone';
        if (!empty($vcard['phones'])) {
            $phone = $vcard['phones'][0]['value'] ?? '';
            $phone_type = $vcard['phones'][0]['type'] ?? 'phone';
        }

        // Get notes - return all notes and first one for backward compatibility
        $notes = $vcard['notes'] ?? [];
        $note = !empty($notes) ? $notes[0] : '';
        
        return rest_ensure_response([
            'first_name'  => $vcard['first_name'] ?? '',
            'last_name'   => $vcard['last_name'] ?? '',
            'nickname'    => $vcard['nickname'] ?? '',
            'email'       => $email,
            'phone'       => $phone,
            'phone_type'  => $phone_type,
            'birthday'    => $vcard['bday'] ?? '',
            'organization'=> $vcard['org'] ?? '',
            'job_title'   => $vcard['title'] ?? '',
            'note'        => $note,  // First note for backward compatibility (populates how_we_met)
            'notes'       => $notes, // All notes for timeline import
            'has_photo'   => !empty($vcard['photo']),
            'contact_count' => count($vcards),
        ]);
    }

    /**
     * Get summary of what will be imported
     */
    private function get_import_summary(array $vcards): array {
        $contacts = 0;
        $companies = [];
        $birthdays = 0;
        $photos = 0;
        $notes = 0;

        foreach ($vcards as $vcard) {
            if (!empty($vcard['first_name']) || !empty($vcard['last_name'])) {
                $contacts++;
            }
            if (!empty($vcard['org'])) {
                $companies[$vcard['org']] = true;
            }
            if (!empty($vcard['bday'])) {
                $birthdays++;
            }
            if (!empty($vcard['photo'])) {
                $photos++;
            }
            if (!empty($vcard['notes'])) {
                $notes += count($vcard['notes']);
            }
        }

        return [
            'contacts'        => $contacts,
            'companies_count' => count($companies),
            'birthdays'       => $birthdays,
            'photos'          => $photos,
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

        $vcf_content = file_get_contents($file['tmp_name']);

        if (empty($vcf_content)) {
            return new WP_Error('empty_file', __('File is empty.', 'personal-crm'), ['status' => 400]);
        }

        // Parse and import vCards
        $vcards = $this->parse_vcards($vcf_content);
        $this->import_vcards($vcards);

        return rest_ensure_response([
            'success' => true,
            'stats'   => $this->stats,
        ]);
    }

    /**
     * Parse vCard content into an array of contact data
     */
    private function parse_vcards(string $content): array {
        $vcards = [];
        
        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        // Handle line folding (lines starting with space or tab are continuations)
        $content = preg_replace('/\n[ \t]/', '', $content);
        
        // Split into individual vCards
        preg_match_all('/BEGIN:VCARD\n(.+?)\nEND:VCARD/s', $content, $matches);
        
        foreach ($matches[1] as $vcard_content) {
            $vcard = $this->parse_single_vcard($vcard_content);
            if (!empty($vcard)) {
                $vcards[] = $vcard;
            }
        }
        
        return $vcards;
    }

    /**
     * Parse a single vCard into structured data
     */
    private function parse_single_vcard(string $content): array {
        $vcard = [
            'first_name'   => '',
            'last_name'    => '',
            'nickname'     => '',
            'org'          => '',
            'title'        => '',
            'emails'       => [],
            'phones'       => [],
            'addresses'    => [],
            'urls'         => [],
            'bday'         => '',
            'notes'        => [],
            'photo'        => null,
            'photo_type'   => '',
        ];
        
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Parse property name, parameters, and value
            $parsed = $this->parse_vcard_line($line);
            if (!$parsed) {
                continue;
            }
            
            $property = strtoupper($parsed['property']);
            $params = $parsed['params'];
            $value = $parsed['value'];
            
            switch ($property) {
                case 'N':
                    // Name: Last;First;Middle;Prefix;Suffix
                    $parts = explode(';', $value);
                    $vcard['last_name'] = $this->decode_vcard_value($parts[0] ?? '');
                    $vcard['first_name'] = $this->decode_vcard_value($parts[1] ?? '');
                    break;
                    
                case 'FN':
                    // Formatted name - use as fallback if N is not present
                    if (empty($vcard['first_name']) && empty($vcard['last_name'])) {
                        $full_name = $this->decode_vcard_value($value);
                        $name_parts = explode(' ', $full_name, 2);
                        $vcard['first_name'] = $name_parts[0] ?? '';
                        $vcard['last_name'] = $name_parts[1] ?? '';
                    }
                    break;
                    
                case 'NICKNAME':
                    $vcard['nickname'] = $this->decode_vcard_value($value);
                    break;
                    
                case 'ORG':
                    // Organization - may have department after semicolon
                    $parts = explode(';', $value);
                    $vcard['org'] = $this->decode_vcard_value($parts[0] ?? '');
                    break;
                    
                case 'TITLE':
                    $vcard['title'] = $this->decode_vcard_value($value);
                    break;
                    
                case 'EMAIL':
                    $type = $this->get_type_param($params);
                    $vcard['emails'][] = [
                        'value' => $this->decode_vcard_value($value),
                        'type'  => $type,
                    ];
                    break;
                    
                case 'TEL':
                    $raw_type = $this->get_type_param($params);
                    $vcard['phones'][] = [
                        'value'    => $this->decode_vcard_value($value),
                        'type'     => $this->normalize_phone_type($raw_type),
                        'raw_type' => $raw_type, // Preserve for label extraction
                    ];
                    break;
                    
                case 'ADR':
                    // Address: PO Box;Extended;Street;City;State;Postal;Country
                    $parts = explode(';', $value);
                    $street = $this->decode_vcard_value($parts[2] ?? '');
                    $city = $this->decode_vcard_value($parts[3] ?? '');
                    $state = $this->decode_vcard_value($parts[4] ?? '');
                    $postal_code = $this->decode_vcard_value($parts[5] ?? '');
                    $country = $this->decode_vcard_value($parts[6] ?? '');
                    
                    // Only add if there's at least some address data
                    if (!empty($street) || !empty($city) || !empty($state) || !empty($postal_code) || !empty($country)) {
                        $type = $this->get_type_param($params);
                        $vcard['addresses'][] = [
                            'street'      => $street,
                            'city'        => $city,
                            'state'       => $state,
                            'postal_code' => $postal_code,
                            'country'     => $country,
                            'type'        => $type,
                        ];
                    }
                    break;
                    
                case 'URL':
                    $type = $this->get_type_param($params);
                    $url = $this->decode_vcard_value($value);
                    $vcard['urls'][] = [
                        'value' => $url,
                        'type'  => $this->detect_url_type($url, $type),
                    ];
                    break;
                    
                case 'BDAY':
                    $vcard['bday'] = $this->parse_vcard_date($value);
                    break;
                    
                case 'NOTE':
                    $note_content = trim($this->decode_vcard_value($value));
                    if (!empty($note_content)) {
                        $vcard['notes'][] = $note_content;
                    }
                    break;
                    
                case 'PHOTO':
                    $photo_data = $this->parse_photo($value, $params);
                    if ($photo_data) {
                        $vcard['photo'] = $photo_data['data'];
                        $vcard['photo_type'] = $photo_data['type'];
                    }
                    break;
            }
        }
        
        return $vcard;
    }

    /**
     * Parse a vCard line into property, parameters, and value
     */
    private function parse_vcard_line(string $line): ?array {
        // Match property with optional parameters and value
        if (!preg_match('/^([A-Za-z\-]+)((?:;[^:]+)*):(.*)$/s', $line, $match)) {
            return null;
        }
        
        $property = $match[1];
        $param_str = $match[2];
        $value = $match[3];
        
        // Parse parameters
        $params = [];
        if (!empty($param_str)) {
            $param_parts = explode(';', ltrim($param_str, ';'));
            foreach ($param_parts as $param) {
                if (strpos($param, '=') !== false) {
                    [$key, $val] = explode('=', $param, 2);
                    $key = strtoupper($key);
                    // Append multiple TYPE values with comma (e.g., type=CELL;type=VOICE)
                    if ($key === 'TYPE' && isset($params['TYPE'])) {
                        $params['TYPE'] .= ',' . $val;
                    } else {
                        $params[$key] = $val;
                    }
                } else {
                    // vCard 2.1 style: type without key
                    $params['TYPE'] = ($params['TYPE'] ?? '') . ',' . $param;
                }
            }
        }
        
        return [
            'property' => $property,
            'params'   => $params,
            'value'    => $value,
        ];
    }

    /**
     * Get TYPE parameter from params array
     * Prioritizes meaningful types (like CELL) over generic ones (like VOICE, pref)
     */
    private function get_type_param(array $params): string {
        $type = $params['TYPE'] ?? '';
        // Remove surrounding quotes if present
        $type = trim($type, '"\'');
        
        // If multiple types, find the most meaningful one
        if (strpos($type, ',') !== false) {
            $types = array_map('trim', explode(',', $type));
            $types = array_map('strtolower', $types);
            
            // Priority types for phone numbers
            $priority_types = ['cell', 'mobile', 'iphone', 'home', 'work', 'fax'];
            foreach ($priority_types as $priority) {
                if (in_array($priority, $types)) {
                    return $priority;
                }
            }
            
            // Filter out generic types that don't indicate the actual type
            $generic_types = ['voice', 'pref', 'text', 'msg'];
            $meaningful_types = array_filter($types, function($t) use ($generic_types) {
                return !in_array($t, $generic_types) && !empty($t);
            });
            
            // Return first meaningful type, or first type if all are generic
            return !empty($meaningful_types) ? reset($meaningful_types) : ($types[0] ?? '');
        }
        
        return strtolower($type);
    }

    /**
     * Normalize phone type to our contact types
     */
    private function normalize_phone_type(string $type): string {
        $type = strtolower($type);
        if (in_array($type, ['cell', 'mobile', 'iphone'])) {
            return 'mobile';
        }
        return 'phone';
    }

    /**
     * Detect URL type from URL content
     */
    private function detect_url_type(string $url, string $type): string {
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
     * Parse vCard date format to Y-m-d
     */
    private function parse_vcard_date(string $value): string {
        $value = trim($value);
        
        // Try various date formats
        // ISO 8601: YYYY-MM-DD or YYYYMMDD
        if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})/', $value, $match)) {
            return sprintf('%s-%s-%s', $match[1], $match[2], $match[3]);
        }
        
        // Try parsing with strtotime
        $timestamp = strtotime($value);
        if ($timestamp) {
            return date('Y-m-d', $timestamp);
        }
        
        return '';
    }

    /**
     * Parse photo data from vCard
     */
    private function parse_photo(string $value, array $params): ?array {
        $encoding = strtoupper($params['ENCODING'] ?? '');
        $type = $params['TYPE'] ?? $params['MEDIATYPE'] ?? 'jpeg';
        
        // Handle TYPE parameter variations
        if (strpos($type, '/') !== false) {
            $type = explode('/', $type)[1];
        }
        $type = strtolower(str_replace(['image/', 'IMAGE/'], '', $type));
        
        // Base64 encoded photo
        if ($encoding === 'B' || $encoding === 'BASE64' || preg_match('/^[a-zA-Z0-9+\/=]+$/', $value)) {
            return [
                'data' => $value,
                'type' => $type,
            ];
        }
        
        // URL to photo
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return [
                'data' => $value,
                'type' => 'url',
            ];
        }
        
        return null;
    }

    /**
     * Decode vCard encoded value
     */
    private function decode_vcard_value(string $value): string {
        // Decode escaped characters
        $value = str_replace(['\\n', '\\N'], "\n", $value);
        $value = str_replace(['\\,'], ',', $value);
        $value = str_replace(['\\;'], ';', $value);
        $value = str_replace(['\\\\'], '\\', $value);
        
        // Decode quoted-printable if present
        if (preg_match('/=([0-9A-F]{2})/i', $value)) {
            $decoded = quoted_printable_decode($value);
            if ($decoded !== false) {
                $value = $decoded;
            }
        }
        
        return trim($value);
    }

    /**
     * Import parsed vCards
     */
    private function import_vcards(array $vcards): void {
        foreach ($vcards as $vcard) {
            $this->import_single_vcard($vcard);
        }
    }

    /**
     * Import a single vCard contact
     */
    private function import_single_vcard(array $vcard): void {
        $first_name = $vcard['first_name'];
        $last_name = $vcard['last_name'];

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

        // Set basic ACF fields (only update if empty or different)
        if (!empty($first_name)) {
            update_field('first_name', $first_name, $post_id);
        }
        if (!empty($last_name)) {
            update_field('last_name', $last_name, $post_id);
        }

        if (!empty($vcard['nickname'])) {
            update_field('nickname', $vcard['nickname'], $post_id);
        }

        // Handle company/work history (add to existing, don't replace)
        if (!empty($vcard['org']) || !empty($vcard['title'])) {
            $company_id = null;
            if (!empty($vcard['org'])) {
                $company_id = $this->get_or_create_company($vcard['org']);
            }

            if ($company_id || $vcard['title']) {
                $existing_work_history = [];
                if ($is_update) {
                    $existing_work_history = get_field('work_history', $post_id) ?: [];
                }
                
                // Check if this work history entry already exists
                $work_exists = false;
                foreach ($existing_work_history as $existing_job) {
                    if ($existing_job['company'] == $company_id && 
                        $existing_job['job_title'] == $vcard['title']) {
                        $work_exists = true;
                        break;
                    }
                }
                
                if (!$work_exists) {
                    $work_history = array_merge($existing_work_history, [
                        [
                            'company'    => $company_id,
                            'job_title'  => $vcard['title'],
                            'is_current' => true,
                        ],
                    ]);
                    update_field('work_history', $work_history, $post_id);
                }
            }
        }

        // Import contact info
        $this->import_contact_info($post_id, $vcard, $is_update);

        // Import birthday
        if (!empty($vcard['bday'])) {
            $this->import_birthday($post_id, $vcard['bday'], $first_name, $last_name);
        }

        // Import notes
        if (!empty($vcard['notes'])) {
            foreach ($vcard['notes'] as $note_content) {
                $this->import_note($post_id, $note_content);
            }
        }

        // Import photo (always import, even if person already has a photo)
        if (!empty($vcard['photo'])) {
            $this->import_photo($post_id, $vcard['photo'], $vcard['photo_type'], $first_name, $last_name);
        }
    }

    /**
     * Import contact information (emails, phones, addresses, URLs)
     */
    private function import_contact_info(int $post_id, array $vcard, bool $is_update = false): void {
        // Get existing contact info if updating
        $existing_contact_info = [];
        if ($is_update) {
            $existing_contact_info = get_field('contact_info', $post_id) ?: [];
        }

        // Create array to track existing entries (to avoid duplicates)
        $existing_keys = [];
        foreach ($existing_contact_info as $existing) {
            $key = strtolower(trim($existing['contact_type'] . '|' . $existing['contact_value']));
            $existing_keys[$key] = true;
        }

        $contact_info = $existing_contact_info;

        // Emails
        foreach ($vcard['emails'] as $email) {
            $key = 'email|' . strtolower(trim($email['value']));
            if (!isset($existing_keys[$key])) {
                $contact_info[] = [
                    'contact_type'  => 'email',
                    'contact_label' => ucfirst($email['type']),
                    'contact_value' => $email['value'],
                ];
                $existing_keys[$key] = true;
            }
        }

        // Phones
        foreach ($vcard['phones'] as $phone) {
            $key = strtolower(trim($phone['type'] . '|' . $phone['value']));
            if (!isset($existing_keys[$key])) {
                // Determine label from raw_type (home/work)
                $label = '';
                $raw_type = strtolower($phone['raw_type'] ?? '');
                if ($raw_type === 'home') {
                    $label = 'Home';
                } elseif ($raw_type === 'work') {
                    $label = 'Work';
                }
                
                $contact_info[] = [
                    'contact_type'  => $phone['type'],
                    'contact_label' => $label,
                    'contact_value' => $phone['value'],
                ];
                $existing_keys[$key] = true;
            }
        }

        // URLs
        foreach ($vcard['urls'] as $url) {
            $key = strtolower(trim($url['type'] . '|' . $url['value']));
            if (!isset($existing_keys[$key])) {
                $contact_info[] = [
                    'contact_type'  => $url['type'],
                    'contact_label' => '',
                    'contact_value' => $url['value'],
                ];
                $existing_keys[$key] = true;
            }
        }

        if (!empty($contact_info)) {
            update_field('contact_info', $contact_info, $post_id);
        }

        // Import addresses to the dedicated addresses field
        if (!empty($vcard['addresses'])) {
            $existing_addresses = [];
            if ($is_update) {
                $existing_addresses = get_field('addresses', $post_id) ?: [];
            }

            // Create array to track existing entries (to avoid duplicates)
            $existing_address_keys = [];
            foreach ($existing_addresses as $existing) {
                $key = strtolower(trim($existing['street'] . '|' . $existing['city'] . '|' . $existing['postal_code']));
                $existing_address_keys[$key] = true;
            }

            $addresses = $existing_addresses;

            foreach ($vcard['addresses'] as $address) {
                $key = strtolower(trim($address['street'] . '|' . $address['city'] . '|' . $address['postal_code']));
                if (!isset($existing_address_keys[$key])) {
                    $addresses[] = [
                        'address_label' => ucfirst($address['type'] ?: ''),
                        'street'        => $address['street'],
                        'postal_code'   => $address['postal_code'],
                        'city'          => $address['city'],
                        'state'         => $address['state'],
                        'country'       => $address['country'],
                    ];
                    $existing_address_keys[$key] = true;
                }
            }

            if (!empty($addresses)) {
                update_field('addresses', $addresses, $post_id);
            }
        }
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
     * Import photo
     */
    private function import_photo(int $post_id, string $photo_data, string $photo_type, string $first_name, string $last_name): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Delete existing thumbnail if present
        $existing_thumbnail_id = get_post_thumbnail_id($post_id);
        if ($existing_thumbnail_id) {
            wp_delete_attachment($existing_thumbnail_id, true); // Force delete
        }

        $attachment_id = null;

        if ($photo_type === 'url') {
            // Download from URL
            $attachment_id = $this->sideload_image($photo_data, $post_id, "{$first_name} {$last_name}");
        } else {
            // Base64 encoded
            $attachment_id = $this->save_base64_image($photo_data, $photo_type, $post_id, $first_name, $last_name);
        }

        if ($attachment_id && !is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            $this->stats['photos_imported']++;
        }
    }

    /**
     * Save base64 encoded image
     */
    private function save_base64_image(string $base64_data, string $type, int $post_id, string $first_name, string $last_name): ?int {
        $image_data = base64_decode($base64_data);
        if ($image_data === false) {
            return null;
        }

        // Determine extension
        $extension = 'jpg';
        if (in_array($type, ['png', 'gif', 'webp'])) {
            $extension = $type;
        }

        // Create filename
        $filename = sanitize_title(strtolower(trim($first_name . ' ' . $last_name))) . '.' . $extension;

        // Save to temp file
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/' . $filename;
        file_put_contents($temp_file, $image_data);

        // Prepare file array
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $temp_file,
        ];

        // Upload
        $attachment_id = media_handle_sideload($file_array, $post_id, "{$first_name} {$last_name}");

        // Clean up temp file if still exists
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }

        if (is_wp_error($attachment_id)) {
            return null;
        }

        return $attachment_id;
    }

    /**
     * Sideload image from URL
     */
    private function sideload_image(string $url, int $post_id, string $description): ?int {
        $tmp = download_url($url);

        if (is_wp_error($tmp)) {
            return null;
        }

        $filename = sanitize_title(strtolower($description)) . '.jpg';
        
        // Get extension from URL
        $path = parse_url($url, PHP_URL_PATH);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $filename = sanitize_title(strtolower($description)) . '.' . strtolower($ext);
        }

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id, $description);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return null;
        }

        return $attachment_id;
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

