<?php
/**
 * Auto-generate post titles from ACF fields
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Auto_Title {
    
    public function __construct() {
        add_action('acf/save_post', [$this, 'auto_generate_person_title'], 20);
        add_action('acf/save_post', [$this, 'auto_generate_date_title'], 20);
        
        // Hide title field in admin for person CPT
        add_filter('acf/prepare_field/name=_post_title', [$this, 'hide_title_field']);
    }
    
    /**
     * Auto-generate Person post title from first_name + last_name
     */
    public function auto_generate_person_title($post_id) {
        // Skip if not a person post type
        if (get_post_type($post_id) !== 'person') {
            return;
        }
        
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        $first_name = get_field('first_name', $post_id) ?: '';
        $last_name = get_field('last_name', $post_id) ?: '';
        
        $full_name = trim($first_name . ' ' . $last_name);
        
        if (empty($full_name)) {
            $full_name = __('Unnamed Person', 'personal-crm');
        }
        
        // Unhook to prevent infinite loop
        remove_action('acf/save_post', [$this, 'auto_generate_person_title'], 20);
        
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $full_name,
            'post_name'  => sanitize_title($full_name . '-' . $post_id),
        ]);
        
        // Re-hook
        add_action('acf/save_post', [$this, 'auto_generate_person_title'], 20);
    }
    
    /**
     * Auto-generate Important Date post title from type + related people
     */
    public function auto_generate_date_title($post_id) {
        // Skip if not an important_date post type
        if (get_post_type($post_id) !== 'important_date') {
            return;
        }
        
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check for custom label first
        $custom_label = get_field('custom_label', $post_id);
        
        if (!empty($custom_label)) {
            $title = $custom_label;
        } else {
            $title = $this->generate_date_title_from_fields($post_id);
        }
        
        // Unhook to prevent infinite loop
        remove_action('acf/save_post', [$this, 'auto_generate_date_title'], 20);
        
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $title,
            'post_name'  => sanitize_title($title . '-' . $post_id),
        ]);
        
        // Re-hook
        add_action('acf/save_post', [$this, 'auto_generate_date_title'], 20);
    }
    
    /**
     * Generate date title from type and related people
     */
    private function generate_date_title_from_fields($post_id) {
        // Get date type from taxonomy
        $date_types = wp_get_post_terms($post_id, 'date_type', ['fields' => 'names']);
        $type_label = !empty($date_types) ? $date_types[0] : __('Date', 'personal-crm');
        
        // Get related people
        $people = get_field('related_people', $post_id) ?: [];
        
        if (empty($people)) {
            return sprintf(__('Unnamed %s', 'personal-crm'), $type_label);
        }
        
        // Get first names of related people
        $names = [];
        foreach ($people as $person) {
            $person_id = is_object($person) ? $person->ID : $person;
            $first_name = get_field('first_name', $person_id);
            if ($first_name) {
                $names[] = $first_name;
            }
        }
        
        if (empty($names)) {
            return sprintf(__('Unnamed %s', 'personal-crm'), $type_label);
        }
        
        $count = count($names);
        
        if ($count === 1) {
            // "Sarah's Birthday"
            return sprintf(__("%s's %s", 'personal-crm'), $names[0], $type_label);
        } elseif ($count === 2) {
            // "Tom & Lisa's Anniversary"
            return sprintf(
                __("%s & %s's %s", 'personal-crm'),
                $names[0],
                $names[1],
                $type_label
            );
        } else {
            // "Mike, Sarah +2 Birthday"
            $first_two = implode(', ', array_slice($names, 0, 2));
            $remaining = $count - 2;
            return sprintf(
                __('%s +%d %s', 'personal-crm'),
                $first_two,
                $remaining,
                $type_label
            );
        }
    }
    
    /**
     * Hide the title field for Person CPT (since it's auto-generated)
     */
    public function hide_title_field($field) {
        global $post;
        
        if ($post && $post->post_type === 'person') {
            return false;
        }
        
        return $field;
    }
}
