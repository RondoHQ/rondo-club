# Phase 93: Search Integration - Research

**Researched:** 2026-01-20
**Domain:** WordPress meta queries, ACF custom fields, search integration
**Confidence:** HIGH

## Summary

This phase integrates custom field values into the existing global search functionality. The Stadion codebase already has a well-established search implementation in `global_search()` that uses a scoring system to prioritize results. Custom fields are stored using ACF's native storage (field name as meta key in `wp_postmeta`), and the CustomFields Manager provides methods to get active field definitions per post type.

The implementation requires:
1. Extending `global_search()` to query custom field meta values
2. Using the CustomFields Manager to get searchable fields for each post type
3. Adding custom field matches with a lower priority score (30, as decided)

**Primary recommendation:** Add a new query for custom field meta matches after the existing first_name and last_name queries, using a single `meta_query` with `relation: OR` for all searchable custom fields. This follows the existing pattern and keeps the code consistent.

## Standard Stack

No additional libraries needed. All functionality uses existing WordPress and PHP capabilities.

### Core (Already in Use)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress WP_Query | 6.0+ | Database queries | Native WordPress meta_query support |
| ACF Pro | Latest | Field storage | Custom fields stored as post meta |
| CustomFields Manager | N/A | Field definitions | Already provides `get_fields()` method |

## Architecture Patterns

### Pattern 1: Existing Search Structure
**What:** The `global_search()` method uses multiple queries with scoring
**When to use:** This is the pattern to extend, not replace
**Example:**
```php
// Source: includes/class-rest-api.php lines 1088-1208
// Query 1: First name matches (score: 60-100)
$first_name_matches = get_posts([
    'post_type' => 'person',
    'posts_per_page' => 20,
    'post_status' => 'publish',
    'meta_query' => [
        [
            'key'     => 'first_name',
            'value'   => $query,
            'compare' => 'LIKE',
        ],
    ],
]);

// Query 2: Last name matches (score: 40)
$last_name_matches = get_posts([
    'post_type' => 'person',
    'posts_per_page' => 20,
    'post_status' => 'publish',
    'meta_query' => [
        [
            'key'     => 'last_name',
            'value'   => $query,
            'compare' => 'LIKE',
        ],
    ],
]);

// Query 3: General WordPress search (score: 20)
$general_matches = get_posts([
    'post_type' => 'person',
    's'         => $query,
    'posts_per_page' => 20,
    'post_status' => 'publish',
]);

// Results are merged with scoring, sorted, and limited to 10
```

### Pattern 2: Custom Fields Manager Usage
**What:** Get active field definitions for a post type
**When to use:** To build the list of searchable meta keys
**Example:**
```php
// Source: includes/customfields/class-manager.php
use Stadion\CustomFields\Manager;

$manager = new Manager();
$fields = $manager->get_fields('person', false); // Active fields only

// Each field array contains:
// - 'key': ACF field key (e.g., 'field_custom_person_linkedin')
// - 'name': Field name used as meta key (e.g., 'linkedin')
// - 'type': Field type (e.g., 'text', 'email', 'url')
// - 'label': Display label (e.g., 'LinkedIn URL')
// - 'active': Whether field is active (1 or 0)
```

### Pattern 3: Multi-Meta Query with OR Relation
**What:** Search multiple meta keys with a single query
**When to use:** When searching across all searchable custom fields
**Example:**
```php
// WordPress meta_query with OR relation
$custom_field_matches = get_posts([
    'post_type' => 'person',
    'posts_per_page' => 20,
    'post_status' => 'publish',
    'meta_query' => [
        'relation' => 'OR',
        [
            'key'     => 'linkedin',
            'value'   => $query,
            'compare' => 'LIKE',
        ],
        [
            'key'     => 'team_email',
            'value'   => $query,
            'compare' => 'LIKE',
        ],
        // ... more fields
    ],
]);
```

### Anti-Patterns to Avoid
- **Separate query per field:** Inefficient. Use single query with `relation: OR`.
- **Searching all field types:** Don't search binary data (Image, File), dates, or boolean fields.
- **Custom database tables:** WordPress meta_query handles all needs.
- **Ignoring field active status:** Always filter to active fields only via `get_fields($type, false)`.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Getting field definitions | Custom database queries | `Manager::get_fields()` | Already handles active filtering |
| Meta LIKE queries | Custom SQL | WP_Query meta_query with LIKE | WordPress handles escaping, indexing |
| Scoring system | New scoring logic | Existing pattern in global_search | Maintains consistency |
| Access control | Custom filtering | Existing STADION_Access_Control | Already filters by user |

**Key insight:** The existing search already does multi-step scoring with meta queries. Just add another step for custom fields.

## Common Pitfalls

### Pitfall 1: Field Name vs Field Key Confusion
**What goes wrong:** Using ACF field key instead of field name for meta queries.
**Why it happens:** ACF stores two identifiers - `key` (e.g., `field_custom_person_linkedin`) and `name` (e.g., `linkedin`). Values are stored under `name`.
**How to avoid:** Always use `$field['name']` when building meta queries.
**Warning signs:** No results even when data exists in the field.

### Pitfall 2: Searching Non-Text Field Types
**What goes wrong:** Searching Image/File fields returns meaningless media IDs or URLs.
**Why it happens:** These fields store media library IDs or attachment data, not user-searchable text.
**How to avoid:** Filter fields by type before building search query. Searchable types: `text`, `textarea`, `email`, `url`, `number`, `select`, `checkbox`.
**Warning signs:** Search returns posts where only the attachment filename matches.

### Pitfall 3: Select/Checkbox Value Mismatch
**What goes wrong:** User searches for "Active" but value is stored as "active" or a different key.
**Why it happens:** ACF stores the key, not the label, for select/checkbox fields.
**How to avoid:** Search matches the stored value (key), not the display label. This is acceptable behavior - if user types the exact stored value, they'll find it.
**Warning signs:** Searching for displayed label text returns no results.

### Pitfall 4: Performance with Many Fields
**What goes wrong:** Slow search when many custom fields are defined.
**Why it happens:** OR meta queries with many conditions can be slow.
**How to avoid:** Limit to ~20 fields max in a single OR query. For typical CRM use (5-15 custom fields), this is not an issue.
**Warning signs:** Search taking >1 second with many custom fields defined.

### Pitfall 5: Empty Results for Teams
**What goes wrong:** Team custom fields not searched.
**Why it happens:** Only implementing for People, forgetting Teams use the same pattern.
**How to avoid:** Apply same logic to both post types in global_search().
**Warning signs:** Custom field search works for People but not Teams.

## Code Examples

### Getting Searchable Custom Fields
```php
/**
 * Get searchable custom field names for a post type.
 *
 * @param string $post_type 'person' or 'team'.
 * @return array Array of field names (meta keys) to search.
 */
private function get_searchable_custom_fields( string $post_type ): array {
    $manager = new \Stadion\CustomFields\Manager();
    $fields = $manager->get_fields( $post_type, false ); // Active only

    // Searchable field types (from CONTEXT.md decisions)
    $searchable_types = [
        'text',
        'textarea',
        'email',
        'url',
        'number',
        'select',
        'checkbox',
    ];

    $field_names = [];
    foreach ( $fields as $field ) {
        if ( in_array( $field['type'], $searchable_types, true ) ) {
            $field_names[] = $field['name'];
        }
    }

    return $field_names;
}
```

### Building Custom Field Meta Query
```php
/**
 * Build meta_query array for custom field search.
 *
 * @param array  $field_names Array of field names to search.
 * @param string $query       Search query string.
 * @return array Meta query array for get_posts().
 */
private function build_custom_field_meta_query( array $field_names, string $query ): array {
    if ( empty( $field_names ) ) {
        return [];
    }

    $meta_query = [ 'relation' => 'OR' ];

    foreach ( $field_names as $field_name ) {
        $meta_query[] = [
            'key'     => $field_name,
            'value'   => $query,
            'compare' => 'LIKE',
        ];
    }

    return $meta_query;
}
```

### Integration into global_search()
```php
// After existing first_name, last_name, and general searches for People:

// Query 4: Custom field matches (score: 30)
$custom_field_names = $this->get_searchable_custom_fields( 'person' );
if ( ! empty( $custom_field_names ) ) {
    $custom_meta_query = $this->build_custom_field_meta_query( $custom_field_names, $query );

    $custom_field_matches = get_posts([
        'post_type'      => 'person',
        'posts_per_page' => 20,
        'post_status'    => 'publish',
        'meta_query'     => $custom_meta_query,
    ]);

    foreach ( $custom_field_matches as $person ) {
        if ( ! isset( $people_results[ $person->ID ] ) ) {
            $people_results[ $person->ID ] = [
                'person' => $person,
                'score'  => 30,
            ];
        }
    }
}

// For teams, add similar logic after existing team search
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| No custom field search | Meta LIKE queries | Phase 93 | Users can find records by custom field content |

**Current in Stadion:**
- Custom fields use ACF-native storage (field name as meta key)
- Search uses WP_Query with meta_query for field-specific searching
- Scoring system prioritizes name matches over general content

## Open Questions

Things that couldn't be fully resolved:

1. **Maximum Fields Performance**
   - What we know: OR meta queries scale linearly with number of conditions
   - What's unclear: At what point does performance degrade noticeably
   - Recommendation: Monitor in production; consider pagination or field limits if issues arise

2. **Checkbox Multi-Value Search**
   - What we know: Checkbox fields store serialized arrays
   - What's unclear: LIKE query may partially match serialized data
   - Recommendation: Accept this limitation; LIKE on serialized data will find matches but may have edge cases

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/includes/class-rest-api.php` - Existing `global_search()` implementation (lines 1087-1209)
- `/Users/joostdevalk/Code/stadion/includes/customfields/class-manager.php` - `get_fields()` method (lines 376-414)
- `/Users/joostdevalk/Code/stadion/includes/class-rest-custom-fields.php` - REST API structure
- `/Users/joostdevalk/Code/stadion/src/components/FieldFormPanel.jsx` - Field types list (lines 6-21)

### Secondary (MEDIUM confidence)
- WordPress WP_Query documentation on meta_query
- ACF field storage patterns (field name as meta key)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using existing WordPress/ACF patterns
- Architecture: HIGH - Directly extending existing code with same patterns
- Pitfalls: HIGH - Based on direct code analysis and WordPress knowledge

**Research date:** 2026-01-20
**Valid until:** 2026-02-19 (30 days - stable WordPress patterns)
