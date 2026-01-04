<?php
/**
 * Inverse Relationship Synchronization
 * 
 * Automatically creates/updates/deletes inverse relationships when relationships are modified.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Inverse_Relationships {
    
    /**
     * Mapping of relationship type slugs to their inverse types
     * Key = relationship type slug, Value = inverse relationship type slug
     */
    private $inverse_mappings = [
        // Symmetric relationships (same type both ways)
        'spouse'        => 'spouse',
        'friend'        => 'friend',
        'colleague'     => 'colleague',
        'acquaintance'  => 'acquaintance',
        'sibling'      => 'sibling',
        'cousin'        => 'cousin',
        'stepsibling'   => 'stepsibling',
        'inlaw'         => 'inlaw',
        'partner'       => 'partner',
        'ex'            => 'ex',
        
        // Parent ↔ Child relationships
        'parent'        => 'child',
        'child'         => 'parent',
        'grandparent'   => 'grandchild',
        'grandchild'    => 'grandparent',
        'stepparent'    => 'stepchild',
        'stepchild'     => 'stepparent',
        'godparent'     => 'godchild',
        'godchild'      => 'godparent',
        
        // Uncle/Aunt ↔ Nephew/Niece
        'uncle'         => 'nephew',
        'nephew'        => 'uncle',
        'aunt'          => 'niece',
        'niece'         => 'aunt',
        
        // Professional relationships
        'boss'          => 'subordinate',
        'subordinate'   => 'boss',
        'mentor'        => 'mentee',
        'mentee'        => 'mentor',
    ];
    
    /**
     * Track which posts are currently being processed to prevent infinite loops
     */
    private $processing = [];
    
    /**
     * Store old relationship values before they're updated
     */
    private $old_relationships = [];
    
    public function __construct() {
        // Capture old value before update
        add_filter('acf/update_value/name=relationships', [$this, 'capture_old_relationships'], 5, 3);
        
        // Sync inverse relationships after relationships field is saved
        add_action('acf/save_post', [$this, 'sync_inverse_relationships'], 20);
    }
    
    /**
     * Capture old relationships value before it's updated
     */
    public function capture_old_relationships($value, $post_id, $field) {
        // Store the old value before it's overwritten
        $old_value = get_field('relationships', $post_id, false);
        $this->old_relationships[$post_id] = $old_value;
        
        // Return the new value unchanged
        return $value;
    }
    
    /**
     * Sync inverse relationships when relationships field is saved
     */
    public function sync_inverse_relationships($post_id) {
        // Only process person post types
        if (get_post_type($post_id) !== 'person') {
            return;
        }
        
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Prevent infinite loops
        if (isset($this->processing[$post_id])) {
            return;
        }
        
        // Get current relationships (new value)
        $current_relationships = get_field('relationships', $post_id);
        if (!is_array($current_relationships)) {
            $current_relationships = [];
        }
        
        // Get previous relationships (old value we captured)
        $previous_relationships = $this->old_relationships[$post_id] ?? [];
        if (!is_array($previous_relationships)) {
            $previous_relationships = [];
        }
        
        // Clean up stored old value
        unset($this->old_relationships[$post_id]);
        
        // Normalize relationship arrays for comparison
        $current_normalized = $this->normalize_relationships($current_relationships);
        $previous_normalized = $this->normalize_relationships($previous_relationships);
        
        // Find added/updated relationships
        foreach ($current_normalized as $current_rel) {
            $related_person_id = $current_rel['related_person'];
            $relationship_type_id = $current_rel['relationship_type'];
            
            if (!$related_person_id || !$relationship_type_id) {
                continue;
            }
            
            // Find matching previous relationship
            $previous_rel = null;
            foreach ($previous_normalized as $prev_rel) {
                if ($prev_rel['related_person'] == $related_person_id) {
                    $previous_rel = $prev_rel;
                    break;
                }
            }
            
            // Check if this is new or updated
            if (!$previous_rel || $previous_rel['relationship_type'] != $relationship_type_id) {
                // New or updated relationship - sync inverse
                $this->sync_single_inverse_relationship(
                    $post_id,
                    $related_person_id,
                    $relationship_type_id,
                    $current_rel['relationship_label'] ?? ''
                );
                
                // If type changed, remove old inverse
                if ($previous_rel && $previous_rel['relationship_type'] != $relationship_type_id) {
                    $this->remove_inverse_relationship(
                        $related_person_id,
                        $post_id,
                        $previous_rel['relationship_type']
                    );
                }
            }
        }
        
        // Find deleted relationships
        foreach ($previous_normalized as $previous_rel) {
            $related_person_id = $previous_rel['related_person'];
            $relationship_type_id = $previous_rel['relationship_type'];
            
            if (!$related_person_id || !$relationship_type_id) {
                continue;
            }
            
            // Check if this relationship still exists
            $still_exists = false;
            foreach ($current_normalized as $current_rel) {
                if ($current_rel['related_person'] == $related_person_id) {
                    $still_exists = true;
                    break;
                }
            }
            
            if (!$still_exists) {
                // Relationship was deleted - remove inverse
                $this->remove_inverse_relationship(
                    $related_person_id,
                    $post_id,
                    $relationship_type_id
                );
            }
        }
    }
    
    /**
     * Normalize relationship array for comparison
     * Handles different ACF return formats
     */
    private function normalize_relationships($relationships) {
        $normalized = [];
        
        if (!is_array($relationships)) {
            return $normalized;
        }
        
        foreach ($relationships as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            
            // Extract related person ID
            $related_person_id = null;
            if (isset($rel['related_person'])) {
                if (is_numeric($rel['related_person'])) {
                    $related_person_id = (int) $rel['related_person'];
                } elseif (is_object($rel['related_person']) && isset($rel['related_person']->ID)) {
                    $related_person_id = (int) $rel['related_person']->ID;
                }
            }
            
            // Extract relationship type ID
            $relationship_type_id = null;
            if (isset($rel['relationship_type'])) {
                if (is_numeric($rel['relationship_type'])) {
                    $relationship_type_id = (int) $rel['relationship_type'];
                } elseif (is_object($rel['relationship_type']) && isset($rel['relationship_type']->term_id)) {
                    $relationship_type_id = (int) $rel['relationship_type']->term_id;
                } elseif (is_array($rel['relationship_type']) && isset($rel['relationship_type']['term_id'])) {
                    $relationship_type_id = (int) $rel['relationship_type']['term_id'];
                }
            }
            
            if ($related_person_id && $relationship_type_id) {
                $normalized[] = [
                    'related_person'     => $related_person_id,
                    'relationship_type'  => $relationship_type_id,
                    'relationship_label' => $rel['relationship_label'] ?? '',
                ];
            }
        }
        
        return $normalized;
    }
    
    /**
     * Sync a single inverse relationship
     */
    private function sync_single_inverse_relationship($from_person_id, $to_person_id, $relationship_type_id, $relationship_label = '') {
        // Prevent infinite loops
        if (isset($this->processing[$to_person_id])) {
            return;
        }
        
        // Validate person exists
        if (!get_post($to_person_id) || get_post_type($to_person_id) !== 'person') {
            return;
        }
        
        // Get relationship type slug
        $term = get_term($relationship_type_id, 'relationship_type');
        if (!$term || is_wp_error($term)) {
            return;
        }
        
        $relationship_slug = $term->slug;
        
        // Get inverse relationship type slug
        $inverse_slug = $this->inverse_mappings[$relationship_slug] ?? null;
        if (!$inverse_slug) {
            // No inverse mapping defined - skip
            return;
        }
        
        // Get inverse relationship type term
        $inverse_term = get_term_by('slug', $inverse_slug, 'relationship_type');
        if (!$inverse_term) {
            return;
        }
        
        $inverse_type_id = $inverse_term->term_id;
        
        // Get existing relationships for the related person
        $related_person_relationships = get_field('relationships', $to_person_id);
        if (!is_array($related_person_relationships)) {
            $related_person_relationships = [];
        }
        
        // Check if inverse relationship already exists
        $inverse_exists = false;
        $inverse_index = null;
        
        foreach ($related_person_relationships as $index => $rel) {
            $rel_person_id = null;
            if (isset($rel['related_person'])) {
                if (is_numeric($rel['related_person'])) {
                    $rel_person_id = (int) $rel['related_person'];
                } elseif (is_object($rel['related_person']) && isset($rel['related_person']->ID)) {
                    $rel_person_id = (int) $rel['related_person']->ID;
                }
            }
            
            if ($rel_person_id == $from_person_id) {
                $inverse_exists = true;
                $inverse_index = $index;
                break;
            }
        }
        
        // Prepare inverse relationship data
        $inverse_rel = [
            'related_person'     => $from_person_id,
            'relationship_type'  => $inverse_type_id,
            'relationship_label' => $relationship_label, // Keep same label
        ];
        
        // Mark as processing to prevent infinite loop
        $this->processing[$to_person_id] = true;
        
        if ($inverse_exists) {
            // Update existing inverse relationship
            $related_person_relationships[$inverse_index] = $inverse_rel;
        } else {
            // Add new inverse relationship
            $related_person_relationships[] = $inverse_rel;
        }
        
        // Save relationships (this will trigger the hook again, but we're protected by $processing)
        update_field('relationships', $related_person_relationships, $to_person_id);
        
        // Unmark as processing
        unset($this->processing[$to_person_id]);
    }
    
    /**
     * Remove an inverse relationship
     */
    private function remove_inverse_relationship($from_person_id, $to_person_id, $relationship_type_id) {
        // Prevent infinite loops
        if (isset($this->processing[$from_person_id])) {
            return;
        }
        
        // Validate person exists
        if (!get_post($from_person_id) || get_post_type($from_person_id) !== 'person') {
            return;
        }
        
        // Get relationship type slug
        $term = get_term($relationship_type_id, 'relationship_type');
        if (!$term || is_wp_error($term)) {
            return;
        }
        
        $relationship_slug = $term->slug;
        
        // Get inverse relationship type slug
        $inverse_slug = $this->inverse_mappings[$relationship_slug] ?? null;
        if (!$inverse_slug) {
            // No inverse mapping defined - skip
            return;
        }
        
        // Get inverse relationship type term
        $inverse_term = get_term_by('slug', $inverse_slug, 'relationship_type');
        if (!$inverse_term) {
            return;
        }
        
        $inverse_type_id = $inverse_term->term_id;
        
        // Get existing relationships for the related person
        $related_person_relationships = get_field('relationships', $from_person_id);
        if (!is_array($related_person_relationships)) {
            return;
        }
        
        // Find and remove inverse relationship
        $found = false;
        foreach ($related_person_relationships as $index => $rel) {
            $rel_person_id = null;
            if (isset($rel['related_person'])) {
                if (is_numeric($rel['related_person'])) {
                    $rel_person_id = (int) $rel['related_person'];
                } elseif (is_object($rel['related_person']) && isset($rel['related_person']->ID)) {
                    $rel_person_id = (int) $rel['related_person']->ID;
                }
            }
            
            $rel_type_id = null;
            if (isset($rel['relationship_type'])) {
                if (is_numeric($rel['relationship_type'])) {
                    $rel_type_id = (int) $rel['relationship_type'];
                } elseif (is_object($rel['relationship_type']) && isset($rel['relationship_type']->term_id)) {
                    $rel_type_id = (int) $rel['relationship_type']->term_id;
                } elseif (is_array($rel['relationship_type']) && isset($rel['relationship_type']['term_id'])) {
                    $rel_type_id = (int) $rel['relationship_type']['term_id'];
                }
            }
            
            if ($rel_person_id == $to_person_id && $rel_type_id == $inverse_type_id) {
                // Found the inverse relationship - remove it
                unset($related_person_relationships[$index]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            // Re-index array
            $related_person_relationships = array_values($related_person_relationships);
            
            // Mark as processing to prevent infinite loop
            $this->processing[$from_person_id] = true;
            
            // Save relationships (this will trigger the hook again, but we're protected by $processing)
            update_field('relationships', $related_person_relationships, $from_person_id);
            
            // Unmark as processing
            unset($this->processing[$from_person_id]);
        }
    }
}

