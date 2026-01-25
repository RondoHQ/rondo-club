# Phase 95: Backend Foundation - Research

**Researched:** 2026-01-21
**Domain:** WordPress Custom Post Types, ACF Field Groups
**Confidence:** HIGH

## Summary

This phase establishes the WordPress data layer for the feedback system. The codebase already has established patterns for custom post type registration (8 existing CPTs) and ACF field group configuration (7 existing field groups in `acf-json/`). The feedback CPT is straightforward to implement following these patterns.

The key decisions are:
- Use ACF select fields for type/status/priority (enforced by ACF, no custom validation needed)
- Status uses ACF select (not WordPress post status) for simplicity - the todo system uses custom post statuses, but that added complexity without clear benefit for feedback
- Global scope (not workspace-scoped) as confirmed in prior decisions
- Gallery field for attachments using existing WordPress media library pattern

**Primary recommendation:** Follow existing `stadion_todo` and `person` CPT patterns exactly - register CPT in `class-post-types.php` with a new method, create ACF JSON file with conditional logic for bug vs feature request fields.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress CPT API | 6.0+ | `register_post_type()` | Native WordPress, already used for 8 CPTs in codebase |
| ACF Pro | Latest | Field groups for metadata | Required dependency, 7 existing field groups |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ACF JSON | N/A | Version control for field groups | Store in `acf-json/` directory |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| ACF select for status | Custom post_status | `stadion_todo` uses custom post statuses, but adds complexity for minimal benefit here |
| ACF gallery | Custom attachment handling | Gallery field handles media library integration already |

**Installation:**
No additional packages needed - all dependencies already present.

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-post-types.php       # Add register_feedback_post_type() method
acf-json/
├── group_feedback_fields.json # New ACF field group
```

### Pattern 1: CPT Registration Method
**What:** Add a private method to `Stadion\Core\PostTypes` class
**When to use:** For any new custom post type
**Example:**
```php
// Source: Existing pattern from class-post-types.php (lines 283-318)
private function register_feedback_post_type() {
    $labels = [
        'name'               => _x( 'Feedback', 'Post type general name', 'stadion' ),
        'singular_name'      => _x( 'Feedback', 'Post type singular name', 'stadion' ),
        'menu_name'          => _x( 'Feedback', 'Admin Menu text', 'stadion' ),
        'add_new'            => __( 'Add New', 'stadion' ),
        'add_new_item'       => __( 'Add New Feedback', 'stadion' ),
        'edit_item'          => __( 'Edit Feedback', 'stadion' ),
        'new_item'           => __( 'New Feedback', 'stadion' ),
        'view_item'          => __( 'View Feedback', 'stadion' ),
        'search_items'       => __( 'Search Feedback', 'stadion' ),
        'not_found'          => __( 'No feedback found', 'stadion' ),
        'not_found_in_trash' => __( 'No feedback found in Trash', 'stadion' ),
        'all_items'          => __( 'All Feedback', 'stadion' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'rest_base'          => 'feedback',
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 26, // After Settings typically
        'menu_icon'          => 'dashicons-megaphone',
        'supports'           => [ 'title', 'editor', 'author' ],
    ];

    register_post_type( 'stadion_feedback', $args );
}
```

### Pattern 2: ACF Select Field with Enforced Values
**What:** Select fields naturally enforce valid values
**When to use:** For status, type, priority fields
**Example:**
```json
// Source: Existing pattern from group_todo_fields.json
{
    "key": "field_feedback_status",
    "label": "Status",
    "name": "status",
    "type": "select",
    "required": 1,
    "choices": {
        "new": "New",
        "in_progress": "In Progress",
        "resolved": "Resolved",
        "declined": "Declined"
    },
    "default_value": "new",
    "return_format": "value",
    "allow_null": 0
}
```

### Pattern 3: Conditional Logic for Field Display
**What:** Show/hide fields based on feedback type
**When to use:** Bug-specific fields vs feature request fields
**Example:**
```json
// Source: Existing pattern from group_todo_fields.json (lines 73-81)
{
    "key": "field_feedback_steps_to_reproduce",
    "label": "Steps to Reproduce",
    "name": "steps_to_reproduce",
    "type": "textarea",
    "required": 0,
    "conditional_logic": [
        [
            {
                "field": "field_feedback_type",
                "operator": "==",
                "value": "bug"
            }
        ]
    ]
}
```

### Anti-Patterns to Avoid
- **Custom validation for select fields:** ACF select fields already enforce valid values - don't add PHP validation
- **Custom post statuses for simple workflows:** The `stadion_todo` CPT uses custom post statuses (`stadion_open`, `stadion_awaiting`, `stadion_completed`) but this adds complexity. For feedback, ACF select is simpler
- **Workspace scoping:** Per prior decisions, feedback is global per installation, not workspace-scoped

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Status enforcement | Custom validation | ACF select field | Select fields only allow configured values |
| File attachments | Custom upload handling | ACF gallery field | Handles media library, multiple files, previews |
| Conditional fields | PHP logic to show/hide | ACF conditional_logic | JSON config, no code needed |
| Admin UI columns | Custom admin columns | ACF admin column display | ACF handles this automatically |

**Key insight:** ACF Pro handles 90% of the complexity - status enforcement, conditional fields, gallery management, and admin UI integration are all built-in features.

## Common Pitfalls

### Pitfall 1: Forgetting to Call Registration Method
**What goes wrong:** CPT not registered, posts can't be created
**Why it happens:** Adding method but not calling it in `register_post_types()`
**How to avoid:** Add call immediately after creating method
**Warning signs:** "Invalid post type" errors

### Pitfall 2: Incorrect ACF Field Keys
**What goes wrong:** Fields not loading, conditional logic broken
**Why it happens:** Duplicate keys or incorrect key format
**How to avoid:** Use consistent naming: `field_feedback_{fieldname}`, `group_feedback_fields`
**Warning signs:** Fields appear in admin but values don't save

### Pitfall 3: Missing show_in_rest for REST API
**What goes wrong:** CPT not accessible via REST API
**Why it happens:** Forgetting `'show_in_rest' => true` in CPT args
**How to avoid:** Always include `show_in_rest` for any CPT that needs API access
**Warning signs:** 404 on `/wp-json/wp/v2/feedback`

### Pitfall 4: ACF JSON Not Loading
**What goes wrong:** Field group not appearing in admin
**Why it happens:** JSON file not in `acf-json/` directory or JSON syntax error
**How to avoid:** Validate JSON syntax, verify file path
**Warning signs:** Field group missing from ACF admin, but file exists

## Code Examples

Verified patterns from existing codebase:

### Complete ACF Field Group Structure
```json
// Source: /Users/joostdevalk/Code/stadion/acf-json/group_todo_fields.json
{
    "key": "group_feedback_fields",
    "title": "Feedback Fields",
    "fields": [
        // Fields array here
    ],
    "location": [
        [
            {
                "param": "post_type",
                "operator": "==",
                "value": "stadion_feedback"
            }
        ]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": [
        "excerpt",
        "discussion",
        "comments",
        "slug"
    ],
    "active": true,
    "show_in_rest": 1
}
```

### Gallery Field for Attachments
```json
// Source: /Users/joostdevalk/Code/stadion/acf-json/group_person_fields.json (lines 75-84)
{
    "key": "field_feedback_attachments",
    "label": "Attachments",
    "name": "attachments",
    "type": "gallery",
    "return_format": "array",
    "preview_size": "medium",
    "library": "all",
    "min": 0,
    "max": 50
}
```

### Textarea Field for Long Text
```json
// Source: /Users/joostdevalk/Code/stadion/acf-json/group_person_fields.json (lines 102-107)
{
    "key": "field_feedback_steps_to_reproduce",
    "label": "Steps to Reproduce",
    "name": "steps_to_reproduce",
    "type": "textarea",
    "rows": 4,
    "required": 0
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Direct meta queries | ACF field queries | N/A | ACF provides consistent API |
| Manual status validation | ACF select enforcement | N/A | Less code, more reliable |

**Deprecated/outdated:**
- None identified - existing patterns are current WordPress/ACF best practices

## Open Questions

Things that couldn't be fully resolved:

1. **Menu position for feedback**
   - What we know: Existing CPTs use positions 4-8
   - What's unclear: Where feedback should appear in admin menu
   - Recommendation: Use position 26 (after Settings area, before admin tools)

2. **Dashicon for feedback**
   - What we know: `dashicons-megaphone` seems appropriate
   - What's unclear: User preference
   - Recommendation: Use `dashicons-megaphone` (announcement/feedback metaphor)

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/includes/class-post-types.php` - 8 CPT registration patterns
- `/Users/joostdevalk/Code/stadion/acf-json/group_todo_fields.json` - ACF field group with select, conditional logic
- `/Users/joostdevalk/Code/stadion/acf-json/group_person_fields.json` - ACF gallery field pattern

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/docs/prd-feedback-system.md` - PRD with field specifications
- `/Users/joostdevalk/Code/stadion/.planning/milestones/v6.1-feedback-system/REQUIREMENTS.md` - Detailed requirements

### Tertiary (LOW confidence)
- None - all research based on existing codebase patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Based on existing codebase patterns (8 CPTs, 7 ACF groups)
- Architecture: HIGH - Direct copy of existing patterns
- Pitfalls: HIGH - Based on common WordPress/ACF issues

**Research date:** 2026-01-21
**Valid until:** 2026-02-21 (30 days - stable WordPress/ACF patterns)
