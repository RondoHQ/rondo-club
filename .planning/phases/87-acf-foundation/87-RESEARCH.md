# Phase 87: ACF Foundation - Research

**Researched:** 2026-01-18
**Domain:** ACF Pro programmatic field management (PHP)
**Confidence:** HIGH

## Summary

This phase establishes PHP infrastructure for managing custom field definitions programmatically via ACF Pro. The goal is to allow admins to create, update, and deactivate custom fields for People and Teams through the React frontend, with all data persisted to the WordPress database using ACF-native storage patterns.

ACF Pro provides two distinct approaches for field management:
1. **Local registration** (`acf_add_local_field_group()`) - Runtime-only, not editable in admin UI
2. **Database persistence** (`acf_import_field_group()`, `acf_update_field_group()`) - Stored as `acf-field-group` and `acf-field` CPTs

For this use case, we need **database persistence** because users create custom fields dynamically at runtime, and these must survive theme updates and be manageable without code changes.

**Primary recommendation:** Use `acf_import_field_group()` for creating new field groups and `acf_update_field_group()` for updates. Store custom field definitions in a dedicated ACF field group per target post type (one for `person`, one for `team`).

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| ACF Pro | 6.x | Field group management | Already required, native API for field CRUD |
| WordPress | 6.0+ | Database storage via CPTs | Field groups stored as `acf-field-group` post type |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None | - | - | ACF Pro provides complete API |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| ACF database storage | ACF local JSON | JSON doesn't support dynamic user-created fields |
| ACF field groups | Custom post meta | Loses ACF field type system, more work |
| Single field group | Multiple groups | Single group per post type is simpler to manage |

**Installation:**
```bash
# ACF Pro is already required and installed
# No additional packages needed
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── CustomFields/
│   ├── Manager.php          # Main orchestration class
│   ├── FieldDefinition.php  # Field definition data model
│   └── KeyGenerator.php     # Field key generation utilities
```

### Pattern 1: Field Group per Post Type
**What:** Create one dynamic ACF field group for People custom fields, one for Teams
**When to use:** Always - keeps custom fields separate from built-in ACF JSON field groups
**Example:**
```php
// Source: ACF official docs - acf_import_field_group()
$field_group = [
    'key'      => 'group_custom_fields_person',
    'title'    => 'Custom Fields',
    'fields'   => [], // Managed dynamically
    'location' => [
        [
            [
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'person',
            ],
        ],
    ],
    'active'       => true,
    'show_in_rest' => 1,
];
acf_import_field_group( $field_group );
```

### Pattern 2: Field Key Generation
**What:** Generate unique, stable field keys from user-provided labels
**When to use:** When creating new field definitions
**Example:**
```php
// Source: ACF official docs - field keys must be unique and start with 'field_'
function generate_field_key( $label, $post_type ) {
    // Sanitize label to slug
    $slug = sanitize_title( $label );
    // Namespace by post type to ensure uniqueness
    $base_key = 'field_custom_' . $post_type . '_' . $slug;

    // If key exists, append unique suffix
    if ( acf_get_field( $base_key ) ) {
        $base_key .= '_' . substr( uniqid(), -6 );
    }

    return $base_key;
}
```

### Pattern 3: Soft Delete via Active Flag
**What:** Deactivate fields by marking them inactive rather than deleting
**When to use:** When admin "deletes" a field to preserve stored data
**Example:**
```php
// Custom meta on the field definition tracks active state
// Field values in wp_postmeta remain untouched
function deactivate_field( $field_key ) {
    $field = acf_get_field( $field_key );
    if ( $field ) {
        $field['active'] = 0; // ACF will not render inactive fields
        acf_update_field( $field );
    }
}
```

### Anti-Patterns to Avoid
- **Using acf_add_local_field_group() for user-defined fields:** Local fields are runtime-only and not persisted to database
- **Mixing JSON sync with programmatic database writes:** Can cause conflicts when Local JSON is enabled
- **Using same field keys across post types:** Namespace keys by post type to avoid collisions
- **Deleting field definitions when user wants to "remove" field:** Deleting ACF field does NOT delete stored values - use soft delete pattern

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Field storage | Custom database tables | ACF native storage (`acf-field` CPT) | ACF handles all edge cases, REST integration |
| Field key uniqueness | Manual ID generation | `uniqid('field_')` with namespace prefix | ACF standard approach |
| Field validation | Custom validators | ACF field type settings (required, min, max) | Built-in, well-tested |
| Field rendering | Custom form fields | ACF renders fields automatically | Type-appropriate UI out of box |
| Field value storage | Custom meta handling | `update_field()` / `get_field()` | ACF handles serialization, format |

**Key insight:** ACF Pro already has a complete internal API for field CRUD operations. The challenge is using the right functions (`acf_import_field_group()`, `acf_update_field()`) rather than the documentation-focused functions (`acf_add_local_field_group()`).

## Common Pitfalls

### Pitfall 1: Local vs Database Registration Confusion
**What goes wrong:** Using `acf_add_local_field_group()` expecting persistence
**Why it happens:** ACF documentation emphasizes local registration for performance
**How to avoid:** Use `acf_import_field_group()` for initial creation, `acf_update_field_group()` for updates
**Warning signs:** Fields disappear after page reload or don't appear in ACF admin

### Pitfall 2: Parent ID vs Parent Key
**What goes wrong:** Passing field group key as parent when ACF expects post ID
**Why it happens:** `acf_update_field()` requires numeric post ID for parent
**How to avoid:** Look up field group post ID first: `acf_get_field_group('group_key')['ID']`
**Warning signs:** Fields created but not associated with field group

### Pitfall 3: Field Key Must Remain Constant
**What goes wrong:** Changing field key for existing field
**Why it happens:** Trying to rename or reorganize fields
**How to avoid:** Once created, field key is permanent. Update label/name, not key.
**Warning signs:** Existing field values become orphaned

### Pitfall 4: JSON Sync Interference
**What goes wrong:** Local JSON file overwrites database changes
**Why it happens:** ACF prioritizes JSON for admin field group editing
**How to avoid:** Custom field groups should not have corresponding JSON files in `acf-json/`
**Warning signs:** Changes revert after visiting ACF admin

### Pitfall 5: Assuming Field Deletion Removes Data
**What goes wrong:** Deleting field thinking values are cleaned up
**Why it happens:** Logical assumption that doesn't match ACF behavior
**How to avoid:** Use soft delete (set `active` = 0) to preserve data
**Warning signs:** Orphaned postmeta after field deletion

## Code Examples

Verified patterns from official sources:

### Create Field Group for Custom Fields
```php
// Source: ACF GitHub - acf-field-group-functions.php
function create_custom_fields_group( $post_type ) {
    $group_key = 'group_custom_fields_' . $post_type;

    // Check if already exists
    $existing = acf_get_field_group( $group_key );
    if ( $existing ) {
        return $existing;
    }

    $field_group = [
        'key'                   => $group_key,
        'title'                 => 'Custom Fields',
        'fields'                => [],
        'location'              => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => $post_type,
                ],
            ],
        ],
        'menu_order'            => 100, // After built-in groups
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'show_in_rest'          => 1,
    ];

    return acf_import_field_group( $field_group );
}
```

### Add Field to Group
```php
// Source: ACF GitHub - acf-field-functions.php
function add_custom_field( $post_type, $field_config ) {
    $group_key = 'group_custom_fields_' . $post_type;
    $group = acf_get_field_group( $group_key );

    if ( ! $group ) {
        $group = create_custom_fields_group( $post_type );
    }

    $field = [
        'key'          => $field_config['key'],
        'label'        => $field_config['label'],
        'name'         => $field_config['name'],
        'type'         => $field_config['type'],
        'instructions' => $field_config['instructions'] ?? '',
        'required'     => $field_config['required'] ?? 0,
        'parent'       => $group['ID'], // Must be post ID, not key
    ];

    // Add type-specific settings
    if ( isset( $field_config['choices'] ) ) {
        $field['choices'] = $field_config['choices'];
    }

    return acf_update_field( $field );
}
```

### Update Field Definition
```php
// Source: ACF GitHub - acf-field-functions.php
function update_custom_field( $field_key, $updates ) {
    $field = acf_get_field( $field_key );

    if ( ! $field ) {
        return new WP_Error( 'not_found', 'Field not found' );
    }

    // Only allow updating certain properties
    $allowed = [ 'label', 'instructions', 'required', 'choices', 'default_value', 'placeholder' ];

    foreach ( $allowed as $prop ) {
        if ( isset( $updates[ $prop ] ) ) {
            $field[ $prop ] = $updates[ $prop ];
        }
    }

    return acf_update_field( $field );
}
```

### Deactivate Field (Soft Delete)
```php
// Source: ACF behavior - deleting field does NOT delete values
function deactivate_custom_field( $field_key ) {
    $field = acf_get_field( $field_key );

    if ( ! $field ) {
        return new WP_Error( 'not_found', 'Field not found' );
    }

    // Mark field as inactive - ACF won't render it
    // But values remain in wp_postmeta
    $field['active'] = 0;

    return acf_update_field( $field );
}
```

### Get All Custom Fields for Post Type
```php
// Source: ACF official docs - acf_get_fields()
function get_custom_fields( $post_type ) {
    $group_key = 'group_custom_fields_' . $post_type;

    return acf_get_fields( $group_key ) ?: [];
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `register_field_group()` | `acf_add_local_field_group()` | ACF 5.0 | Old function deprecated but still works |
| Manual post type storage | `acf_import_field_group()` | ACF 5.x | Cleaner API for database persistence |
| Editing via admin only | Programmatic via PHP | Always available | Enables dynamic field creation |

**Deprecated/outdated:**
- `register_field_group()`: Use `acf_add_local_field_group()` for local or `acf_import_field_group()` for database
- Direct `wp_insert_post()` for field groups: Use `acf_import_field_group()` wrapper

## Open Questions

Things that couldn't be fully resolved:

1. **Menu Order for Dynamic Fields**
   - What we know: Fields have `menu_order` property for ordering
   - What's unclear: Best approach for user drag-drop reordering
   - Recommendation: Store order in field definition, update via `acf_update_field()`

2. **Conflict with Local JSON**
   - What we know: ACF prioritizes JSON for admin editing
   - What's unclear: Whether programmatic updates conflict when JSON sync enabled
   - Recommendation: Ensure custom field groups don't have corresponding JSON files

## Data Storage Model

### ACF Internal Storage

ACF stores field configuration using WordPress CPTs:

| Data | Storage | Post Type |
|------|---------|-----------|
| Field Group | `wp_posts` | `acf-field-group` |
| Field Definition | `wp_posts` | `acf-field` |
| Field Value (per post) | `wp_postmeta` | N/A (uses post's meta) |

**Field Group Post Structure:**
- `post_title`: Group title
- `post_name`: Group key
- `post_content`: Serialized settings (location, etc.)
- `post_status`: 'publish' (active) or 'acf-disabled'

**Field Post Structure:**
- `post_title`: Field label
- `post_name`: Field name
- `post_excerpt`: Field key
- `post_content`: Serialized settings (type, choices, etc.)
- `post_parent`: Field group post ID
- `menu_order`: Display order

**Field Value Storage:**
For each post with ACF fields:
- `wp_postmeta.meta_key`: Field name (e.g., `custom_text_field`)
- `wp_postmeta.meta_value`: The actual value
- `wp_postmeta.meta_key`: `_` + field name (e.g., `_custom_text_field`)
- `wp_postmeta.meta_value`: Field key reference (e.g., `field_custom_person_xyz`)

## API Functions Summary

### Database Operations (Use These)
| Function | Purpose | Returns |
|----------|---------|---------|
| `acf_import_field_group($group)` | Create new field group in DB | Field group array |
| `acf_update_field_group($group)` | Update existing field group | Field group array |
| `acf_delete_field_group($id)` | Permanently delete group | Boolean |
| `acf_get_field_group($id)` | Get field group by ID/key | Array or false |
| `acf_update_field($field)` | Create/update field in DB | Field array |
| `acf_delete_field($id)` | Permanently delete field | Boolean |
| `acf_get_field($id)` | Get field by ID/key/name | Array or false |
| `acf_get_fields($parent)` | Get all fields in group | Array |

### Local Registration (Don't Use for This Feature)
| Function | Purpose | Why Not |
|----------|---------|---------|
| `acf_add_local_field_group($group)` | Runtime registration | Not persisted |
| `acf_add_local_field($field)` | Runtime registration | Not persisted |

## Sources

### Primary (HIGH confidence)
- [ACF Official Docs - Register fields via PHP](https://www.advancedcustomfields.com/resources/register-fields-via-php/)
- [ACF GitHub - acf-field-group-functions.php](https://github.com/AdvancedCustomFields/acf/blob/master/includes/acf-field-group-functions.php)
- [ACF GitHub - acf-field-functions.php](https://github.com/wp-premium/advanced-custom-fields-pro/blob/master/includes/acf-field-functions.php)

### Secondary (MEDIUM confidence)
- [ACF Support Forums - Data storage understanding](https://support.advancedcustomfields.com/forums/topic/understanding-acf-data-storage/)
- [ACF Support Forums - Field key best practices](https://support.advancedcustomfields.com/forums/topic/local-fields-generated-with-php-auto-generate-unique-keys/)

### Tertiary (LOW confidence)
- [Moot Point Blog - Programmatic database persistence](https://www.mootpoint.org/blog/create-acf-field-programmatically-permanently-in-database/) - Concept verified via official sources

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - ACF Pro is already in use, APIs well documented
- Architecture: HIGH - Patterns verified against ACF source code
- Pitfalls: HIGH - Multiple official forum posts confirm these issues
- Code examples: HIGH - Based on ACF GitHub source code

**Research date:** 2026-01-18
**Valid until:** 60 days (ACF Pro API is stable)
