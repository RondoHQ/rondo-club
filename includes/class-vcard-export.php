<?php
/**
 * vCard Export Class
 * 
 * Generates vCard 3.0 format from person data for CardDAV server.
 * 
 * @package Caelis
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_VCard_Export {
    
    /**
     * Escape special characters in vCard values
     *
     * @param string $value The value to escape
     * @return string Escaped value
     */
    public static function escape_value($value) {
        if (empty($value)) {
            return '';
        }
        
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace("\n", '\\n', $value);
        
        return $value;
    }
    
    /**
     * Format phone number for vCard
     *
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    public static function format_phone($phone) {
        if (empty($phone)) {
            return '';
        }
        // Remove all non-digit characters except +
        return preg_replace('/[^\d+]/', '', $phone);
    }
    
    /**
     * Format date for vCard (YYYYMMDD)
     *
     * @param string $date Date value
     * @return string Formatted date
     */
    public static function format_date($date) {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        
        return date('Ymd', $timestamp);
    }
    
    /**
     * Get current job title and organization from work history
     *
     * @param array $work_history Work history array
     * @return array ['title' => string, 'org' => string]
     */
    public static function get_current_job($work_history) {
        if (!is_array($work_history) || empty($work_history)) {
            return ['title' => '', 'org' => ''];
        }
        
        // Find current job
        foreach ($work_history as $job) {
            if (!empty($job['is_current'])) {
                $company_name = '';
                if (!empty($job['company'])) {
                    $company = get_post($job['company']);
                    if ($company) {
                        $company_name = $company->post_title;
                    }
                }
                return [
                    'title' => $job['job_title'] ?? '',
                    'org' => $company_name,
                ];
            }
        }
        
        // If no current job, get the most recent one
        usort($work_history, function($a, $b) {
            $date_a = strtotime($a['start_date'] ?? '1970-01-01');
            $date_b = strtotime($b['start_date'] ?? '1970-01-01');
            return $date_b - $date_a;
        });
        
        if (!empty($work_history[0])) {
            $job = $work_history[0];
            $company_name = '';
            if (!empty($job['company'])) {
                $company = get_post($job['company']);
                if ($company) {
                    $company_name = $company->post_title;
                }
            }
            return [
                'title' => $job['job_title'] ?? '',
                'org' => $company_name,
            ];
        }
        
        return ['title' => '', 'org' => ''];
    }
    
    /**
     * Get birthday from important dates linked to this person
     *
     * @param int $person_id Person post ID
     * @return string|null Birthday date in Y-m-d format or null
     */
    public static function get_birthday($person_id) {
        // Get the birthday date type term
        $birthday_term = get_term_by('slug', 'birthday', 'date_type');
        if (!$birthday_term) {
            return null;
        }
        
        // Query for important dates linked to this person with birthday type
        $dates = get_posts([
            'post_type' => 'important_date',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'related_people',
                    'value' => '"' . $person_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
            'tax_query' => [
                [
                    'taxonomy' => 'date_type',
                    'field' => 'slug',
                    'terms' => 'birthday',
                ],
            ],
        ]);
        
        if (!empty($dates)) {
            $date_value = get_field('date_value', $dates[0]->ID);
            return $date_value;
        }
        
        return null;
    }
    
    /**
     * Generate vCard 3.0 format from person post
     *
     * @param int|WP_Post $person Person post ID or object
     * @return string vCard content
     */
    public static function generate($person) {
        if (is_int($person)) {
            $person = get_post($person);
        }
        
        if (!$person || $person->post_type !== 'person') {
            return '';
        }
        
        $acf = get_fields($person->ID) ?: [];
        $lines = [];
        
        // BEGIN:VCARD
        $lines[] = 'BEGIN:VCARD';
        $lines[] = 'VERSION:3.0';
        
        // UID - use post ID with domain
        $site_url = parse_url(home_url(), PHP_URL_HOST);
        $lines[] = 'UID:' . $person->ID . '@' . $site_url;
        
        // Name fields
        $first_name = $acf['first_name'] ?? '';
        $last_name = $acf['last_name'] ?? '';
        $full_name = $person->post_title ?: trim($first_name . ' ' . $last_name) ?: 'Unknown';
        
        // FN (Full Name) - required
        $lines[] = 'FN:' . self::escape_value($full_name);
        
        // N (Name) - Family;Given;Additional;Prefix;Suffix
        $lines[] = 'N:' . self::escape_value($last_name) . ';' . self::escape_value($first_name) . ';;;';
        
        // Nickname
        if (!empty($acf['nickname'])) {
            $lines[] = 'NICKNAME:' . self::escape_value($acf['nickname']);
        }
        
        // Contact information
        if (!empty($acf['contact_info']) && is_array($acf['contact_info'])) {
            foreach ($acf['contact_info'] as $contact) {
                if (empty($contact['contact_value'])) {
                    continue;
                }
                
                $value = self::escape_value($contact['contact_value']);
                $label = !empty($contact['contact_label']) ? strtoupper($contact['contact_label']) : '';
                
                switch ($contact['contact_type']) {
                    case 'email':
                        $email_type = $label ? "EMAIL;TYPE=INTERNET,{$label}" : 'EMAIL;TYPE=INTERNET';
                        $lines[] = "{$email_type}:{$value}";
                        break;
                        
                    case 'phone':
                    case 'mobile':
                        $phone_type = $contact['contact_type'] === 'mobile' ? 'CELL' : 'VOICE';
                        $phone_label = $label ? "TEL;TYPE={$phone_type},{$label}" : "TEL;TYPE={$phone_type}";
                        $formatted_phone = self::format_phone($contact['contact_value']);
                        if ($formatted_phone) {
                            $lines[] = "{$phone_label}:{$formatted_phone}";
                        }
                        break;
                        
                    case 'website':
                    case 'linkedin':
                    case 'twitter':
                    case 'instagram':
                    case 'facebook':
                    case 'calendar':
                        $url = $contact['contact_value'];
                        if (!preg_match('/^https?:\/\//i', $url)) {
                            $url = 'https://' . $url;
                        }
                        $url_type = $contact['contact_type'] === 'linkedin' ? 'PROFILE' : 'WORK';
                        $url_label = $label ? "URL;TYPE={$url_type},{$label}" : "URL;TYPE={$url_type}";
                        $lines[] = "{$url_label}:" . self::escape_value($url);
                        break;
                }
            }
        }
        
        // Addresses (structured format)
        if (!empty($acf['addresses']) && is_array($acf['addresses'])) {
            foreach ($acf['addresses'] as $address) {
                $addr_type = !empty($address['address_label']) ? 
                    'ADR;TYPE=' . strtoupper($address['address_label']) : 
                    'ADR;TYPE=HOME';
                    
                $street = self::escape_value($address['street'] ?? '');
                $city = self::escape_value($address['city'] ?? '');
                $state = self::escape_value($address['state'] ?? '');
                $postal_code = self::escape_value($address['postal_code'] ?? '');
                $country = self::escape_value($address['country'] ?? '');
                
                // Only add if there's at least some address data
                if ($street || $city || $state || $postal_code || $country) {
                    // ADR: POBox;Extended;Street;City;State;PostalCode;Country
                    $lines[] = "{$addr_type}:;;{$street};{$city};{$state};{$postal_code};{$country}";
                }
            }
        }
        
        // Organization and title from work history
        $job = self::get_current_job($acf['work_history'] ?? []);
        if ($job['org']) {
            $lines[] = 'ORG:' . self::escape_value($job['org']);
        }
        if ($job['title']) {
            $lines[] = 'TITLE:' . self::escape_value($job['title']);
        }
        
        // Birthday
        $birthday = self::get_birthday($person->ID);
        if ($birthday) {
            $bday = self::format_date($birthday);
            if ($bday) {
                $lines[] = "BDAY:{$bday}";
            }
        }
        
        // Photo (include as URL if available)
        $thumbnail_id = get_post_thumbnail_id($person->ID);
        if ($thumbnail_id) {
            $thumbnail_url = wp_get_attachment_url($thumbnail_id);
            if ($thumbnail_url) {
                $lines[] = 'PHOTO;VALUE=URI:' . $thumbnail_url;
            }
        }
        
        // REV (Revision) - last modified date
        $modified = $person->post_modified_gmt;
        if ($modified) {
            $rev_date = date('Ymd\THis\Z', strtotime($modified));
            $lines[] = "REV:{$rev_date}";
        }
        
        // END:VCARD
        $lines[] = 'END:VCARD';
        
        return implode("\r\n", $lines);
    }
    
    /**
     * Generate vCard from array data (used by CardDAV backend)
     *
     * @param array $data Person data array with ACF fields
     * @return string vCard content
     */
    public static function generate_from_array($data) {
        $lines = [];
        
        // BEGIN:VCARD
        $lines[] = 'BEGIN:VCARD';
        $lines[] = 'VERSION:3.0';
        
        // UID
        if (!empty($data['uid'])) {
            $lines[] = 'UID:' . $data['uid'];
        }
        
        // Name fields
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $full_name = $data['full_name'] ?? trim($first_name . ' ' . $last_name) ?: 'Unknown';
        
        // FN (Full Name) - required
        $lines[] = 'FN:' . self::escape_value($full_name);
        
        // N (Name)
        $lines[] = 'N:' . self::escape_value($last_name) . ';' . self::escape_value($first_name) . ';;;';
        
        // Nickname
        if (!empty($data['nickname'])) {
            $lines[] = 'NICKNAME:' . self::escape_value($data['nickname']);
        }
        
        // Contact info
        if (!empty($data['contact_info']) && is_array($data['contact_info'])) {
            foreach ($data['contact_info'] as $contact) {
                if (empty($contact['contact_value'])) {
                    continue;
                }
                
                $value = self::escape_value($contact['contact_value']);
                
                switch ($contact['contact_type']) {
                    case 'email':
                        $lines[] = "EMAIL;TYPE=INTERNET:{$value}";
                        break;
                    case 'phone':
                        $lines[] = "TEL;TYPE=VOICE:" . self::format_phone($contact['contact_value']);
                        break;
                    case 'mobile':
                        $lines[] = "TEL;TYPE=CELL:" . self::format_phone($contact['contact_value']);
                        break;
                    case 'website':
                    case 'linkedin':
                    case 'twitter':
                    case 'instagram':
                    case 'facebook':
                    case 'calendar':
                        $url = $contact['contact_value'];
                        if (!preg_match('/^https?:\/\//i', $url)) {
                            $url = 'https://' . $url;
                        }
                        $lines[] = "URL:" . self::escape_value($url);
                        break;
                }
            }
        }
        
        // Addresses
        if (!empty($data['addresses']) && is_array($data['addresses'])) {
            foreach ($data['addresses'] as $address) {
                $addr_type = !empty($address['address_label']) ? 
                    'ADR;TYPE=' . strtoupper($address['address_label']) : 
                    'ADR;TYPE=HOME';
                    
                $street = self::escape_value($address['street'] ?? '');
                $city = self::escape_value($address['city'] ?? '');
                $state = self::escape_value($address['state'] ?? '');
                $postal_code = self::escape_value($address['postal_code'] ?? '');
                $country = self::escape_value($address['country'] ?? '');
                
                if ($street || $city || $state || $postal_code || $country) {
                    $lines[] = "{$addr_type}:;;{$street};{$city};{$state};{$postal_code};{$country}";
                }
            }
        }
        
        // Organization
        if (!empty($data['org'])) {
            $lines[] = 'ORG:' . self::escape_value($data['org']);
        }
        
        // Title
        if (!empty($data['title'])) {
            $lines[] = 'TITLE:' . self::escape_value($data['title']);
        }
        
        // Birthday
        if (!empty($data['birthday'])) {
            $bday = self::format_date($data['birthday']);
            if ($bday) {
                $lines[] = "BDAY:{$bday}";
            }
        }
        
        // Photo
        if (!empty($data['photo_url'])) {
            $lines[] = 'PHOTO;VALUE=URI:' . $data['photo_url'];
        }
        
        // REV
        if (!empty($data['modified'])) {
            $rev_date = date('Ymd\THis\Z', strtotime($data['modified']));
            $lines[] = "REV:{$rev_date}";
        }
        
        // END:VCARD
        $lines[] = 'END:VCARD';
        
        return implode("\r\n", $lines);
    }
    
    /**
     * Parse a vCard string and extract data
     * 
     * @param string $vcard_data Raw vCard string
     * @return array Parsed data
     */
    public static function parse($vcard_data) {
        // Use sabre/vobject for parsing if available
        if (class_exists('Sabre\VObject\Reader')) {
            try {
                $vcard = \Sabre\VObject\Reader::read($vcard_data);
                return self::vobject_to_array($vcard);
            } catch (\Exception $e) {
                // Fall back to manual parsing
            }
        }
        
        // Manual parsing fallback
        return self::manual_parse($vcard_data);
    }
    
    /**
     * Convert sabre/vobject VCard to array
     *
     * @param \Sabre\VObject\Component\VCard $vcard
     * @return array
     */
    private static function vobject_to_array($vcard) {
        $data = [
            'first_name' => '',
            'last_name' => '',
            'full_name' => '',
            'nickname' => '',
            'contact_info' => [],
            'addresses' => [],
            'org' => '',
            'title' => '',
            'birthday' => '',
            'photo_url' => '',
            'uid' => '',
        ];
        
        // UID
        if (isset($vcard->UID)) {
            $data['uid'] = (string) $vcard->UID;
        }
        
        // Name
        if (isset($vcard->N)) {
            $n = $vcard->N->getParts();
            $data['last_name'] = $n[0] ?? '';
            $data['first_name'] = $n[1] ?? '';
        }
        
        // Full name
        if (isset($vcard->FN)) {
            $data['full_name'] = (string) $vcard->FN;
        }
        
        // Nickname
        if (isset($vcard->NICKNAME)) {
            $data['nickname'] = (string) $vcard->NICKNAME;
        }
        
        // Email
        if (isset($vcard->EMAIL)) {
            foreach ($vcard->EMAIL as $email) {
                $data['contact_info'][] = [
                    'contact_type' => 'email',
                    'contact_value' => (string) $email,
                    'contact_label' => '',
                ];
            }
        }
        
        // Phone
        if (isset($vcard->TEL)) {
            foreach ($vcard->TEL as $tel) {
                $type = 'phone';
                $type_param = $tel['TYPE'];
                if ($type_param) {
                    $types = is_array($type_param) ? $type_param : [$type_param];
                    foreach ($types as $t) {
                        if (strtoupper($t) === 'CELL' || strtoupper($t) === 'MOBILE') {
                            $type = 'mobile';
                            break;
                        }
                    }
                }
                $data['contact_info'][] = [
                    'contact_type' => $type,
                    'contact_value' => (string) $tel,
                    'contact_label' => '',
                ];
            }
        }
        
        // URL
        if (isset($vcard->URL)) {
            foreach ($vcard->URL as $url) {
                $url_value = (string) $url;
                $type = 'website';
                
                // Detect URL type
                if (stripos($url_value, 'linkedin.com') !== false) {
                    $type = 'linkedin';
                } elseif (stripos($url_value, 'twitter.com') !== false || stripos($url_value, 'x.com') !== false) {
                    $type = 'twitter';
                } elseif (stripos($url_value, 'instagram.com') !== false) {
                    $type = 'instagram';
                } elseif (stripos($url_value, 'facebook.com') !== false) {
                    $type = 'facebook';
                } elseif (stripos($url_value, 'calendly.com') !== false || stripos($url_value, 'cal.com') !== false) {
                    $type = 'calendar';
                }
                
                $data['contact_info'][] = [
                    'contact_type' => $type,
                    'contact_value' => $url_value,
                    'contact_label' => '',
                ];
            }
        }
        
        // Address
        if (isset($vcard->ADR)) {
            foreach ($vcard->ADR as $adr) {
                $parts = $adr->getParts();
                $label = '';
                $type_param = $adr['TYPE'];
                if ($type_param) {
                    $label = strtolower(is_array($type_param) ? $type_param[0] : $type_param);
                }
                
                $data['addresses'][] = [
                    'address_label' => $label,
                    'street' => $parts[2] ?? '',
                    'city' => $parts[3] ?? '',
                    'state' => $parts[4] ?? '',
                    'postal_code' => $parts[5] ?? '',
                    'country' => $parts[6] ?? '',
                ];
            }
        }
        
        // Organization
        if (isset($vcard->ORG)) {
            $data['org'] = (string) $vcard->ORG;
        }
        
        // Title
        if (isset($vcard->TITLE)) {
            $data['title'] = (string) $vcard->TITLE;
        }
        
        // Birthday
        if (isset($vcard->BDAY)) {
            $bday = (string) $vcard->BDAY;
            // Convert YYYYMMDD to Y-m-d
            if (strlen($bday) === 8 && is_numeric($bday)) {
                $data['birthday'] = substr($bday, 0, 4) . '-' . substr($bday, 4, 2) . '-' . substr($bday, 6, 2);
            } else {
                $data['birthday'] = $bday;
            }
        }
        
        // Photo
        if (isset($vcard->PHOTO)) {
            $photo = $vcard->PHOTO;
            if (isset($photo['VALUE']) && strtoupper($photo['VALUE']) === 'URI') {
                $data['photo_url'] = (string) $photo;
            }
        }
        
        return $data;
    }
    
    /**
     * Manual vCard parsing fallback
     *
     * @param string $vcard_data
     * @return array
     */
    private static function manual_parse($vcard_data) {
        $data = [
            'first_name' => '',
            'last_name' => '',
            'full_name' => '',
            'nickname' => '',
            'contact_info' => [],
            'addresses' => [],
            'org' => '',
            'title' => '',
            'birthday' => '',
            'photo_url' => '',
            'uid' => '',
        ];
        
        $lines = preg_split('/\r\n|\r|\n/', $vcard_data);
        
        foreach ($lines as $line) {
            // Skip empty lines
            if (empty(trim($line))) {
                continue;
            }
            
            // Parse line
            if (strpos($line, ':') === false) {
                continue;
            }
            
            list($property, $value) = explode(':', $line, 2);
            $property_parts = explode(';', $property);
            $property_name = strtoupper($property_parts[0]);
            
            switch ($property_name) {
                case 'FN':
                    $data['full_name'] = self::unescape_value($value);
                    break;
                    
                case 'N':
                    $parts = explode(';', $value);
                    $data['last_name'] = self::unescape_value($parts[0] ?? '');
                    $data['first_name'] = self::unescape_value($parts[1] ?? '');
                    break;
                    
                case 'NICKNAME':
                    $data['nickname'] = self::unescape_value($value);
                    break;
                    
                case 'EMAIL':
                    $data['contact_info'][] = [
                        'contact_type' => 'email',
                        'contact_value' => self::unescape_value($value),
                        'contact_label' => '',
                    ];
                    break;
                    
                case 'TEL':
                    $type = 'phone';
                    if (stripos($property, 'CELL') !== false || stripos($property, 'MOBILE') !== false) {
                        $type = 'mobile';
                    }
                    $data['contact_info'][] = [
                        'contact_type' => $type,
                        'contact_value' => self::unescape_value($value),
                        'contact_label' => '',
                    ];
                    break;
                    
                case 'URL':
                    $data['contact_info'][] = [
                        'contact_type' => 'website',
                        'contact_value' => self::unescape_value($value),
                        'contact_label' => '',
                    ];
                    break;
                    
                case 'ORG':
                    $data['org'] = self::unescape_value($value);
                    break;
                    
                case 'TITLE':
                    $data['title'] = self::unescape_value($value);
                    break;
                    
                case 'BDAY':
                    $bday = self::unescape_value($value);
                    if (strlen($bday) === 8 && is_numeric($bday)) {
                        $data['birthday'] = substr($bday, 0, 4) . '-' . substr($bday, 4, 2) . '-' . substr($bday, 6, 2);
                    } else {
                        $data['birthday'] = $bday;
                    }
                    break;
                    
                case 'UID':
                    $data['uid'] = self::unescape_value($value);
                    break;
            }
        }
        
        return $data;
    }
    
    /**
     * Unescape vCard values
     *
     * @param string $value
     * @return string
     */
    private static function unescape_value($value) {
        $value = str_replace('\\n', "\n", $value);
        $value = str_replace('\\,', ',', $value);
        $value = str_replace('\\;', ';', $value);
        $value = str_replace('\\\\', '\\', $value);
        return $value;
    }
}

