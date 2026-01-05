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
        
        // Get relationship type term
        $term = get_term($relationship_type_id, 'relationship_type');
        if (!$term || is_wp_error($term)) {
            return;
        }
        
        // Get inverse relationship type from ACF field
        $inverse_type_id = get_field('inverse_relationship_type', 'relationship_type_' . $relationship_type_id);
        
        if (!$inverse_type_id) {
            // No inverse mapping defined - skip
            return;
        }
        
        // Normalize to integer (handle ACF return formats: id, array, object)
        if (is_array($inverse_type_id) && isset($inverse_type_id['term_id'])) {
            $inverse_type_id = (int) $inverse_type_id['term_id'];
        } elseif (is_object($inverse_type_id) && isset($inverse_type_id->term_id)) {
            $inverse_type_id = (int) $inverse_type_id->term_id;
        } elseif (is_numeric($inverse_type_id)) {
            $inverse_type_id = (int) $inverse_type_id;
        } else {
            // Invalid format - skip
            return;
        }
        
        // Validate inverse term exists
        $inverse_term = get_term($inverse_type_id, 'relationship_type');
        if (!$inverse_term || is_wp_error($inverse_term)) {
            return;
        }
        
        // Check if the SOURCE relationship type is gender-dependent
        // If so, we need to resolve the inverse based on the related person's gender
        $source_is_gender_dependent = get_field('is_gender_dependent', 'relationship_type_' . $relationship_type_id);
        $source_gender_group = get_field('gender_dependent_group', 'relationship_type_' . $relationship_type_id);
        
        if ($source_is_gender_dependent && !empty($source_gender_group)) {
            // The source type is gender-dependent, so the inverse needs to be resolved
            // based on the related person's gender ($to_person_id)
            $inverse_type_id = $this->resolve_gender_dependent_inverse($inverse_type_id, $to_person_id, $source_gender_group);
        } else {
            // Check if inverse is gender-dependent and resolve if needed
            // $to_person_id is the person who will have the inverse relationship created
            // Their gender determines which specific type to use
            $inverse_type_id = $this->resolve_gender_dependent_inverse($inverse_type_id, $to_person_id);
        }
        
        if (!$inverse_type_id) {
            return;
        }
        
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
        
        // Get relationship type term
        $term = get_term($relationship_type_id, 'relationship_type');
        if (!$term || is_wp_error($term)) {
            return;
        }
        
        // Get inverse relationship type from ACF field
        $inverse_type_id = get_field('inverse_relationship_type', 'relationship_type_' . $relationship_type_id);
        
        if (!$inverse_type_id) {
            // No inverse mapping defined - skip
            return;
        }
        
        // Normalize to integer (handle ACF return formats: id, array, object)
        if (is_array($inverse_type_id) && isset($inverse_type_id['term_id'])) {
            $inverse_type_id = (int) $inverse_type_id['term_id'];
        } elseif (is_object($inverse_type_id) && isset($inverse_type_id->term_id)) {
            $inverse_type_id = (int) $inverse_type_id->term_id;
        } elseif (is_numeric($inverse_type_id)) {
            $inverse_type_id = (int) $inverse_type_id;
        } else {
            // Invalid format - skip
            return;
        }
        
        // Validate inverse term exists
        $inverse_term = get_term($inverse_type_id, 'relationship_type');
        if (!$inverse_term || is_wp_error($inverse_term)) {
            return;
        }
        
        // Check if the SOURCE relationship type is gender-dependent
        // If so, we need to resolve the inverse based on the related person's gender
        $source_is_gender_dependent = get_field('is_gender_dependent', 'relationship_type_' . $relationship_type_id);
        $source_gender_group = get_field('gender_dependent_group', 'relationship_type_' . $relationship_type_id);
        
        // Determine target group for inverse resolution
        // aunt_uncle -> niece_nephew, niece_nephew -> aunt_uncle
        $target_group = null;
        if ($source_gender_group === 'aunt_uncle') {
            $target_group = 'niece_nephew';
        } elseif ($source_gender_group === 'niece_nephew') {
            $target_group = 'aunt_uncle';
        }
        
        if ($source_is_gender_dependent && !empty($target_group)) {
            // The source type is gender-dependent, so the inverse needs to be resolved
            // based on the related person's gender ($to_person_id)
            $inverse_type_id = $this->resolve_gender_dependent_inverse($inverse_type_id, $to_person_id, $target_group);
        } else {
            // Check if inverse is gender-dependent and resolve if needed
            // For removal, we need to check the to_person_id's gender (the person we're removing from)
            $inverse_type_id = $this->resolve_gender_dependent_inverse($inverse_type_id, $to_person_id);
        }
        
        if (!$inverse_type_id) {
            return;
        }
        
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
    
    /**
     * Resolve gender-dependent inverse relationship type
     * 
     * If the inverse type is gender-dependent, resolves to the correct specific type
     * based on the related person's gender.
     * 
     * @param int $inverse_type_id The inverse relationship type ID from ACF mapping
     * @param int $related_person_id The person whose gender determines the resolution
     * @param string|null $target_group Optional target group to resolve to (if source is gender-dependent)
     * @return int|null Resolved relationship type ID, or original if not gender-dependent
     */
    private function resolve_gender_dependent_inverse($inverse_type_id, $related_person_id, $target_group = null) {
        // If target_group is provided, resolve directly to that group
        if ($target_group) {
            $group_types = $this->get_types_in_gender_group($target_group);
            if (!empty($group_types)) {
                $related_person_gender = get_field('gender', $related_person_id);
                if (!empty($related_person_gender)) {
                    $resolved_type_id = $this->infer_gender_type_from_group($target_group, $related_person_gender, $group_types);
                    if ($resolved_type_id) {
                        return $resolved_type_id;
                    }
                }
            }
            // Fallback to original if resolution fails
            return $inverse_type_id;
        }
        
        // Check if inverse type is gender-dependent
        $is_gender_dependent = get_field('is_gender_dependent', 'relationship_type_' . $inverse_type_id);
        $gender_group = get_field('gender_dependent_group', 'relationship_type_' . $inverse_type_id);
        
        if (!$is_gender_dependent || empty($gender_group)) {
            // Not gender-dependent, return as-is
            return $inverse_type_id;
        }
        
        // Get related person's gender
        $related_person_gender = get_field('gender', $related_person_id);
        
        if (empty($related_person_gender)) {
            // No gender set - return original (could be improved with fallback logic)
            return $inverse_type_id;
        }
        
        // Find all relationship types in the same gender-dependent group
        $group_types = $this->get_types_in_gender_group($gender_group);
        
        if (empty($group_types)) {
            // No types found in group - return original
            return $inverse_type_id;
        }
        
        // Resolve based on gender and group
        $resolved_type_id = $this->infer_gender_type_from_group($gender_group, $related_person_gender, $group_types);
        
        // Return resolved type or fallback to original
        return $resolved_type_id ?: $inverse_type_id;
    }
    
    /**
     * Get all relationship types in a gender-dependent group
     * 
     * @param string $group_name The gender-dependent group name
     * @return array Array of type_id => slug
     */
    private function get_types_in_gender_group($group_name) {
        $types = [];
        
        // Get all relationship types
        $all_types = get_terms([
            'taxonomy' => 'relationship_type',
            'hide_empty' => false,
        ]);
        
        if (is_wp_error($all_types) || empty($all_types)) {
            return $types;
        }
        
        foreach ($all_types as $term) {
            $term_group = get_field('gender_dependent_group', 'relationship_type_' . $term->term_id);
            if ($term_group === $group_name) {
                $types[$term->term_id] = $term->slug;
            }
        }
        
        return $types;
    }
    
    /**
     * Infer the correct gender-specific type from a group based on gender
     * 
     * @param string $group_name The gender-dependent group name
     * @param string $gender The person's gender
     * @param array $group_types Array of type_id => slug in the group
     * @return int|null Resolved type ID
     */
    private function infer_gender_type_from_group($group_name, $gender, $group_types) {
        // Known gender-dependent group mappings
        // Maps group name → gender → expected slug
        $group_mappings = [
            'aunt_uncle' => [
                'female' => 'aunt',
                'male' => 'uncle',
            ],
            'niece_nephew' => [
                'female' => 'niece',
                'male' => 'nephew',
            ],
        ];
        
        // If we have a mapping for this group, use it
        if (isset($group_mappings[$group_name]) && isset($group_mappings[$group_name][$gender])) {
            $expected_slug = $group_mappings[$group_name][$gender];
            
            // Find the type with the expected slug
            foreach ($group_types as $type_id => $type_slug) {
                if ($type_slug === $expected_slug) {
                    return $type_id;
                }
            }
        }
        
        // Fallback: try to infer from slug patterns
        // Female variants typically: aunt, niece
        // Male variants typically: uncle, nephew
        if ($gender === 'female') {
            foreach ($group_types as $type_id => $type_slug) {
                if (in_array($type_slug, ['aunt', 'niece'])) {
                    return $type_id;
                }
            }
        } elseif ($gender === 'male') {
            foreach ($group_types as $type_id => $type_slug) {
                if (in_array($type_slug, ['uncle', 'nephew'])) {
                    return $type_id;
                }
            }
        }
        
        return null;
    }
}

