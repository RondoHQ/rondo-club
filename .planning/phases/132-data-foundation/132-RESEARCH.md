# Phase 132: Data Foundation - Research

**Researched:** 2026-02-03
**Domain:** WordPress Custom Post Types, ACF field groups, REST API, taxonomy system
**Confidence:** HIGH

## Summary

Phase 132 establishes the backend data infrastructure for discipline cases (Dutch: "tuchtzaken") in a WordPress theme environment. The research focused on WordPress native data storage patterns, ACF field configuration for complex data types, shared taxonomy implementation, and REST API exposure best practices.

The standard approach in WordPress is to use Custom Post Types (CPT) for entities, ACF field groups for structured metadata, and taxonomies for categorization. This system already uses this pattern extensively for `person`, `team`, `commissie`, `important_date`, and `stadion_todo` post types.

Key findings confirm that:
- WordPress CPT + ACF + REST API is the established stack for this use case
- ACF fields automatically expose via REST when `show_in_rest` is enabled on both CPT and field group
- Shared taxonomies work seamlessly across multiple post types
- Unique field validation requires custom implementation via ACF filters
- Date fields need special handling for REST API formatting

**Primary recommendation:** Follow existing Stadion CPT patterns (see `person` and `important_date` implementations), register `discipline_case` with `show_in_rest: true`, create ACF field group with `show_in_rest: 1`, register `seizoen` taxonomy for multiple post types, implement unique validation for `dossier-id` via `acf/validate_value` filter.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress | 6.0+ | CPT registration, REST API foundation | Native platform, no alternatives |
| ACF Pro | Latest | Complex field management, REST exposure | Required dependency per project rules, superior to native custom fields |
| WordPress REST API | Core (wp/v2) | Data access layer | Native, supports CPT/taxonomy/meta automatically |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress Term Meta API | Core | Store "current season" flag on taxonomy terms | When taxonomies need metadata (like active/inactive flags) |
| ACF Validation Filters | Core | Enforce unique `dossier-id` constraint | When field values must be unique across all posts |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| ACF | Custom meta boxes | ACF provides REST integration, repeaters, validation hooks - custom meta boxes require manual REST field registration |
| ACF Relationship Field | Post Object Field | Relationship field ALWAYS returns array (even for single value), Post Object returns single value or array based on "Select Multiple" setting. For single person link, Post Object is more appropriate. |
| Taxonomy for seasons | ACF Select Field | Taxonomy allows shared use across features, supports term metadata for "current season" flag, enables future filtering/querying |

**Installation:**
No additional packages needed - all core WordPress + existing ACF Pro dependency.

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-post-types.php      # Add discipline_case registration
├── class-taxonomies.php      # Add seizoen registration
├── class-rest-api.php        # No changes needed (standard wp/v2 endpoints work)
└── class-access-control.php  # Will need discipline_case filtering (Phase 133)

acf-json/
└── group_discipline_case_fields.json  # Auto-saved ACF field group
```

### Pattern 1: CPT Registration for Sportlink-Synced Data
**What:** Register CPT with REST enabled, minimal supports, hierarchical false, no public/queryable routes (React SPA handles routing)
**When to use:** Data synced from external systems (Sportlink) that needs REST API access but not public WordPress URLs
**Example:**
```php
// Source: /Users/joostdevalk/Code/stadion/includes/class-post-types.php (team CPT, lines 93-111)
$args = [
    'labels'             => $labels,
    'public'             => false,              // Not publicly accessible via WordPress URLs
    'publicly_queryable' => false,              // Disable front-end queries
    'show_ui'            => true,               // Show in admin
    'show_in_menu'       => true,               // Admin menu item
    'show_in_rest'       => true,               // CRITICAL: Enable REST API and Gutenberg
    'rest_base'          => 'teams',            // Custom REST endpoint name (wp/v2/teams)
    'query_var'          => false,              // No query var needed
    'rewrite'            => false,              // React Router handles routing, not WordPress
    'capability_type'    => 'post',
    'has_archive'        => false,
    'hierarchical'       => true,               // For parent-child if needed
    'menu_position'      => 6,
    'menu_icon'          => 'dashicons-groups',
    'supports'           => [ 'title', 'editor', 'thumbnail', 'author', 'page-attributes' ],
];
register_post_type( 'team', $args );
```

**Adaptation for discipline_case:**
- Set `hierarchical` to `false` (cases don't have parent-child relationships)
- Remove `page-attributes` from supports (only needed for hierarchical)
- Add `comments` to supports if notes/activities will be added later (Phase 134)
- Use `'rest_base' => 'discipline-cases'` for hyphenated REST endpoint

### Pattern 2: Shared Taxonomy Registration
**What:** Register taxonomy for one CPT initially, designed for future expansion to other CPTs
**When to use:** Categorization that may apply to multiple entity types (seasons span discipline cases, membership fees, events)
**Example:**
```php
// Source: WordPress Developer Handbook + existing Stadion patterns
register_taxonomy(
    'seizoen',
    [ 'discipline_case' ],  // Initial post type - can add more later
    [
        'hierarchical'      => false,  // Seasons are flat, not parent-child
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,   // Show in post list table
        'show_in_rest'      => true,   // CRITICAL: Expose via REST API
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'seizoen' ],
    ]
);
```

**Key insight:** To add more post types later, call `register_taxonomy_for_object_type( 'seizoen', 'other_post_type' )` on the `wp_loaded` hook.

### Pattern 3: ACF Field Group with REST Integration
**What:** ACF field group with `show_in_rest: 1` exposes all fields automatically via standard wp/v2 endpoints
**When to use:** Always, for any CPT that needs REST API access
**Example:**
```json
// Source: /Users/joostdevalk/Code/stadion/acf-json/group_important_date_fields.json
{
    "key": "group_discipline_case_fields",
    "title": "Discipline Case Fields",
    "fields": [ /* field definitions */ ],
    "location": [
        [
            {
                "param": "post_type",
                "operator": "==",
                "value": "discipline_case"
            }
        ]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": [ "excerpt", "discussion", "comments", "slug", "author" ],
    "active": true,
    "show_in_rest": 1  // CRITICAL: Exposes fields via REST API
}
```

**REST API Response Format:**
When `show_in_rest: 1` is set, GET request to `wp/v2/discipline-cases/{id}` returns:
```json
{
  "id": 123,
  "title": { "rendered": "Auto-generated title" },
  "acf": {
    "dossier_id": "2024-001",
    "person": 456,  // Post Object field returns integer (post ID)
    "match_date": "20240915",  // Date picker returns YYYYMMDD format
    "administrative_fee": "25.00",
    "is_charged": true
  },
  "seizoen": [ 12 ]  // Taxonomy term IDs
}
```

### Pattern 4: ACF Post Object Field for Single Person Link
**What:** Use ACF Post Object field (not Relationship field) for single entity references
**When to use:** Linking to exactly one related post (or zero if optional)
**Example:**
```json
{
    "key": "field_discipline_case_person",
    "label": "Person",
    "name": "person",
    "type": "post_object",
    "post_type": [ "person" ],
    "multiple": 0,              // Single selection only
    "return_format": "id",      // Returns integer post ID, not full object
    "allow_null": 1,            // Optional - cases can exist without person
    "required": 0
}
```

**Why Post Object over Relationship:**
- Post Object with `multiple: 0` returns single integer (cleaner API response)
- Relationship field ALWAYS returns array (even for single value)
- User decisions specify "single person per case" - Post Object matches this intent

### Pattern 5: Number Field for Currency Values
**What:** ACF Number field with step/min attributes for decimal currency values
**When to use:** Storing monetary amounts in database
**Example:**
```json
{
    "key": "field_administrative_fee",
    "label": "Administratiekosten",
    "name": "administrative_fee",
    "type": "number",
    "default_value": "",
    "min": 0,
    "max": "",
    "step": 0.01,  // Allow cent precision
    "prepend": "€",
    "append": ""
}
```

**Storage format:** ACF stores as string/integer in database (e.g., "25.00" or "25")
**REST API format:** Returns as string "25.00"
**Display formatting:** Apply `number_format()` in frontend for locale-specific display (€ 25,00 in Dutch locale)

### Pattern 6: Term Meta for "Current Season" Flag
**What:** Use WordPress term meta to mark one season as active/current
**When to use:** Taxonomies need additional metadata (flags, settings, order)
**Example:**
```php
// Set current season
update_term_meta( $term_id, 'is_current_season', true );

// Get current season
$terms = get_terms( [
    'taxonomy' => 'seizoen',
    'meta_key' => 'is_current_season',
    'meta_value' => true,
] );
```

**Note:** Only one season should have `is_current_season = true` at a time. Implement toggle logic to clear flag from previous season when setting new current season.

### Anti-Patterns to Avoid
- **Custom database tables:** WordPress Rule 0 mandates using CPT + meta, never custom tables
- **Post meta for person link:** Always use ACF Relationship/Post Object fields for entity references (provides REST support, admin UI, validation)
- **Select field for seasons:** Taxonomy provides better queryability, shared usage, and supports term metadata
- **Hardcoded season values:** Seasons should be created dynamically during Sportlink sync, not hardcoded in code
- **Skipping `show_in_rest`:** Without this, CPT/taxonomy won't appear in REST API or Gutenberg editor

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Unique field validation | Custom pre-save check in PHP | ACF `acf/validate_value` filter | ACF provides validation framework with error display in admin UI, REST API validation, and proper WordPress integration |
| Currency number formatting | Custom input masking | ACF Number field with step=0.01 + frontend `number_format()` | ACF handles storage, validation, REST exposure; formatting is frontend concern |
| Person relationship linking | Custom meta field with autocomplete | ACF Post Object field | Provides admin UI, REST API response formatting, relationship querying, validation |
| Season categorization | Custom meta field or option | WordPress Taxonomy | Enables shared usage across post types, supports term metadata, provides REST endpoints, allows future filtering/faceting |
| REST API field exposure | `register_rest_field()` for each field | ACF `show_in_rest: 1` | Exposes ALL fields automatically in consistent format, handles nested data, supports updates via REST |
| Date storage/formatting | Text field with manual validation | ACF Date Picker field | Handles storage format (YYYYMMDD), admin UI calendar picker, validation; format on frontend as needed |

**Key insight:** ACF already solved REST API integration for complex field types. Don't bypass ACF's `show_in_rest` feature by manually registering fields - let ACF handle the entire field-to-REST pipeline.

## Common Pitfalls

### Pitfall 1: Forgetting `show_in_rest` on CPT or Field Group
**What goes wrong:** CPT registered, ACF fields created, but REST API returns empty `acf` object or CPT doesn't appear in wp/v2 endpoints at all
**Why it happens:** Both CPT (`show_in_rest: true`) AND field group (`show_in_rest: 1`) must be enabled for fields to appear via REST
**How to avoid:**
- Check CPT registration includes `'show_in_rest' => true`
- Check ACF field group JSON has `"show_in_rest": 1` at root level
- Test with: `GET /wp-json/wp/v2/{rest_base}` - should return posts
- Test with: `GET /wp-json/wp/v2/{rest_base}/{id}` - should include `acf` object with field data
**Warning signs:** Empty `acf: {}` object in REST response, or 404 on wp/v2/{rest_base} endpoint

### Pitfall 2: Date Picker Field Returns YYYYMMDD Format in REST API
**What goes wrong:** Frontend receives date as "20240915" instead of expected "2024-09-15" or formatted string
**Why it happens:** ACF Date Picker always stores in Ymd format (YYYYMMDD) regardless of display/return format settings, and REST API returns raw storage format
**How to avoid:**
- Accept that REST API will return YYYYMMDD format
- Format dates on frontend using JavaScript (moment.js, date-fns, or native Intl.DateTimeFormat)
- Don't rely on ACF's "return format" setting - it only affects PHP `get_field()`, not REST API
**Warning signs:** Date fields showing "20240915" instead of readable format in React components

### Pitfall 3: Post Object Field Returns ID, Frontend Needs Full Person Object
**What goes wrong:** REST API returns `"person": 456` but frontend needs person's name, photo, etc.
**Why it happens:** Post Object field with `return_format: "id"` returns only the post ID (most efficient for REST)
**How to avoid:**
- Use REST API `_embed` parameter to include related person data: `GET /wp/v2/discipline-cases/{id}?_embed`
- OR make separate request to fetch person: `GET /wp/v2/people/{person_id}`
- OR use ACF's `?acf_format=standard` parameter (includes full WP_Post objects, but heavy payload)
- Best practice: Use `_embed` for list views, separate requests for detail views
**Warning signs:** Frontend making N+1 queries (one per discipline case to fetch person), slow list rendering

### Pitfall 4: Unique Validation Not Working for dossier-id
**What goes wrong:** Duplicate dossier-id values saved despite validation code
**Why it happens:**
- Validation filter not applied to correct field key
- Query excludes current post incorrectly (checks wrong post ID source)
- REST API creates bypass validation (ACF validation filters work in admin but may not trigger on REST creates)
**How to avoid:**
```php
add_filter( 'acf/validate_value/name=dossier_id', function( $valid, $value, $field, $input_name ) {
    if ( ! $valid ) {
        return $valid;
    }

    // Get current post ID from POST data or request
    $post_id = isset( $_POST['post_ID'] ) ? intval( $_POST['post_ID'] ) : 0;

    // Query for existing posts with this dossier_id
    $existing = get_posts( [
        'post_type'  => 'discipline_case',
        'meta_query' => [
            [
                'key'   => 'dossier_id',
                'value' => $value,
            ],
        ],
        'post__not_in' => [ $post_id ],  // Exclude current post
        'posts_per_page' => 1,
    ] );

    if ( ! empty( $existing ) ) {
        return 'Dit dossier-ID bestaat al. Gebruik een uniek ID.';
    }

    return $valid;
}, 10, 4 );
```
**Warning signs:** Duplicate dossier-ids in database, Sportlink sync creating multiple cases for same dossier

### Pitfall 5: Taxonomy Terms Not Auto-Created During Sync
**What goes wrong:** Sportlink sync attempts to assign season "2024-2025" but term doesn't exist, assignment silently fails
**Why it happens:** WordPress doesn't auto-create taxonomy terms - `wp_set_object_terms()` requires term to exist first
**How to avoid:**
```php
// During Sportlink sync, before assigning terms
$season_slug = '2024-2025';
$term = term_exists( $season_slug, 'seizoen' );

if ( ! $term ) {
    $term = wp_insert_term( $season_slug, 'seizoen', [
        'slug' => $season_slug,
    ] );
}

// Now assign term
wp_set_object_terms( $post_id, $season_slug, 'seizoen' );
```
**Warning signs:** Cases imported but have no season assigned, taxonomy terms list doesn't grow with new seasons

### Pitfall 6: Shared Taxonomy Registration Timing
**What goes wrong:** Register taxonomy for multiple post types before all post types are registered, causes errors
**Why it happens:** `register_taxonomy()` runs on `init` hook - if CPTs aren't registered yet, validation fails
**How to avoid:**
- Register all CPTs first (single method in class-post-types.php)
- Then register taxonomies (single method in class-taxonomies.php)
- Both run on same `init` hook but taxonomies class loads after post types class
- Or use `register_taxonomy_for_object_type()` on `wp_loaded` hook for safety
**Warning signs:** Taxonomy doesn't appear in admin for certain post types, `register_taxonomy()` warnings in debug log

## Code Examples

Verified patterns from official sources and existing Stadion implementation:

### Register discipline_case CPT
```php
// Source: Based on /Users/joostdevalk/Code/stadion/includes/class-post-types.php patterns
private function register_discipline_case_post_type() {
    $labels = [
        'name'               => _x( 'Tuchtzaken', 'Post type general name', 'stadion' ),
        'singular_name'      => _x( 'Tuchtzaak', 'Post type singular name', 'stadion' ),
        'menu_name'          => _x( 'Tuchtzaken', 'Admin Menu text', 'stadion' ),
        'add_new'            => __( 'Add New', 'stadion' ),
        'add_new_item'       => __( 'Add New Tuchtzaak', 'stadion' ),
        'edit_item'          => __( 'Edit Tuchtzaak', 'stadion' ),
        'new_item'           => __( 'New Tuchtzaak', 'stadion' ),
        'view_item'          => __( 'View Tuchtzaak', 'stadion' ),
        'search_items'       => __( 'Search Tuchtzaken', 'stadion' ),
        'not_found'          => __( 'No tuchtzaken found', 'stadion' ),
        'not_found_in_trash' => __( 'No tuchtzaken found in Trash', 'stadion' ),
        'all_items'          => __( 'All Tuchtzaken', 'stadion' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'rest_base'          => 'discipline-cases',
        'query_var'          => false,
        'rewrite'            => false,  // React Router handles routing
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,  // Cases are flat, no parent-child
        'menu_position'      => 9,      // After Todos (8)
        'menu_icon'          => 'dashicons-warning',  // Or 'dashicons-flag'
        'supports'           => [ 'title', 'author' ],  // Minimal - ACF holds all data
    ];

    register_post_type( 'discipline_case', $args );
}
```

### Register seizoen Taxonomy
```php
// Source: Based on /Users/joostdevalk/Code/stadion/includes/class-taxonomies.php patterns
private function register_seizoen_taxonomy() {
    $labels = [
        'name'          => _x( 'Seizoenen', 'taxonomy general name', 'stadion' ),
        'singular_name' => _x( 'Seizoen', 'taxonomy singular name', 'stadion' ),
        'search_items'  => __( 'Search Seizoenen', 'stadion' ),
        'all_items'     => __( 'All Seizoenen', 'stadion' ),
        'edit_item'     => __( 'Edit Seizoen', 'stadion' ),
        'update_item'   => __( 'Update Seizoen', 'stadion' ),
        'add_new_item'  => __( 'Add New Seizoen', 'stadion' ),
        'new_item_name' => __( 'New Seizoen Name', 'stadion' ),
        'menu_name'     => __( 'Seizoenen', 'stadion' ),
    ];

    $args = [
        'hierarchical'      => false,  // Flat taxonomy (like tags)
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,   // Show in discipline_case list table
        'show_in_rest'      => true,   // CRITICAL: Enable REST API
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'seizoen' ],
    ];

    register_taxonomy( 'seizoen', [ 'discipline_case' ], $args );
}
```

### ACF Field Group JSON Structure
```json
// Source: Pattern from /Users/joostdevalk/Code/stadion/acf-json/group_important_date_fields.json
{
    "key": "group_discipline_case_fields",
    "title": "Discipline Case Fields",
    "fields": [
        {
            "key": "field_dossier_id",
            "label": "Dossier ID",
            "name": "dossier_id",
            "type": "text",
            "instructions": "Uniek identificatienummer uit Sportlink",
            "required": 1,
            "wrapper": { "width": "50" }
        },
        {
            "key": "field_discipline_person",
            "label": "Persoon",
            "name": "person",
            "type": "post_object",
            "post_type": [ "person" ],
            "multiple": 0,
            "return_format": "id",
            "allow_null": 1,
            "wrapper": { "width": "50" }
        },
        {
            "key": "field_match_date",
            "label": "Wedstrijddatum",
            "name": "match_date",
            "type": "date_picker",
            "display_format": "d/m/Y",
            "return_format": "Ymd",
            "wrapper": { "width": "50" }
        },
        {
            "key": "field_processing_date",
            "label": "Verwerkingsdatum",
            "name": "processing_date",
            "type": "date_picker",
            "display_format": "d/m/Y",
            "return_format": "Ymd",
            "wrapper": { "width": "50" }
        },
        {
            "key": "field_match_description",
            "label": "Wedstrijdomschrijving",
            "name": "match_description",
            "type": "text"
        },
        {
            "key": "field_team_name",
            "label": "Teamnaam",
            "name": "team_name",
            "type": "text",
            "wrapper": { "width": "50" }
        },
        {
            "key": "field_charge_codes",
            "label": "Artikelnummer",
            "name": "charge_codes",
            "type": "text",
            "instructions": "Één artikelnummer per zaak",
            "wrapper": { "width": "50" }
        },
        {
            "key": "field_charge_description",
            "label": "Artikelomschrijving",
            "name": "charge_description",
            "type": "textarea",
            "rows": 3
        },
        {
            "key": "field_sanction_description",
            "label": "Strafbeschrijving",
            "name": "sanction_description",
            "type": "textarea",
            "rows": 3
        },
        {
            "key": "field_administrative_fee",
            "label": "Administratiekosten",
            "name": "administrative_fee",
            "type": "number",
            "min": 0,
            "step": 0.01,
            "prepend": "€",
            "wrapper": { "width": "50" }
        },
        {
            "key": "field_is_charged",
            "label": "Is doorbelast",
            "name": "is_charged",
            "type": "true_false",
            "message": "Kosten zijn doorbelast aan de persoon",
            "default_value": 0,
            "ui": 1,
            "wrapper": { "width": "50" }
        }
    ],
    "location": [
        [
            {
                "param": "post_type",
                "operator": "==",
                "value": "discipline_case"
            }
        ]
    ],
    "menu_order": 0,
    "position": "normal",
    "style": "default",
    "label_placement": "top",
    "instruction_placement": "label",
    "hide_on_screen": [ "excerpt", "discussion", "comments", "slug" ],
    "active": true,
    "show_in_rest": 1
}
```

### Unique dossier-id Validation
```php
// Source: ACF documentation + community patterns
add_filter( 'acf/validate_value/name=dossier_id', function( $valid, $value, $field, $input_name ) {
    // Skip if already invalid
    if ( ! $valid ) {
        return $valid;
    }

    // Get current post ID
    $post_id = 0;
    if ( isset( $_POST['post_ID'] ) ) {
        $post_id = intval( $_POST['post_ID'] );
    } elseif ( isset( $_POST['post_id'] ) ) {
        $post_id = intval( $_POST['post_id'] );
    }

    // Query for existing discipline cases with this dossier_id
    $existing = get_posts( [
        'post_type'      => 'discipline_case',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'post__not_in'   => [ $post_id ],
        'meta_query'     => [
            [
                'key'   => 'dossier_id',
                'value' => $value,
            ],
        ],
    ] );

    if ( ! empty( $existing ) ) {
        return 'Dit dossier-ID bestaat al. Elk dossier moet een uniek ID hebben.';
    }

    return $valid;
}, 10, 4 );
```

### Current Season Management
```php
// Source: WordPress term meta API patterns
function set_current_season( $season_slug ) {
    // Clear previous current season
    $previous_current = get_terms( [
        'taxonomy'   => 'seizoen',
        'hide_empty' => false,
        'meta_query' => [
            [
                'key'   => 'is_current_season',
                'value' => true,
            ],
        ],
    ] );

    foreach ( $previous_current as $term ) {
        delete_term_meta( $term->term_id, 'is_current_season' );
    }

    // Set new current season
    $term = get_term_by( 'slug', $season_slug, 'seizoen' );
    if ( $term ) {
        update_term_meta( $term->term_id, 'is_current_season', true );
    }
}

function get_current_season() {
    $terms = get_terms( [
        'taxonomy'   => 'seizoen',
        'hide_empty' => false,
        'meta_query' => [
            [
                'key'   => 'is_current_season',
                'value' => true,
            ],
        ],
    ] );

    return ! empty( $terms ) ? $terms[0] : null;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual `register_rest_field()` for each ACF field | ACF `show_in_rest: 1` auto-exposes all fields | ACF 5.11 (2021) | Eliminates boilerplate REST registration code |
| Post meta for entity relationships | ACF Relationship/Post Object fields | ACF 4.0+ (2013) | Provides admin UI, validation, REST formatting |
| Custom meta boxes | ACF field groups with JSON sync | ACF 5.0+ (2015) | Version control for fields, consistent REST API |
| Hierarchical categories for flat data | Non-hierarchical taxonomies (like tags) | WordPress 3.0+ (2010) | Simpler UI, no accidental nesting |
| Text fields for dates | ACF Date Picker field | ACF 4.0+ | Consistent storage format, calendar UI |

**Deprecated/outdated:**
- **`register_rest_field()` for ACF fields:** ACF now handles this automatically with `show_in_rest: 1`
- **`acf_format=standard` parameter:** Heavy payload, prefer `_embed` for related entities
- **ACF JSON in theme:** Still works, but plugin location is more portable - Stadion uses theme-based JSON (acceptable for theme-specific CRM)

## Open Questions

Things that couldn't be fully resolved:

1. **Auto-title Generation Logic**
   - What we know: Existing CPTs use `RONDO_Auto_Title` class to generate titles from ACF fields
   - What's unclear: Exact title format preference for discipline cases (e.g., "{dossier-id} - {person-name} - {match-date}" or different format)
   - Recommendation: Implement in Phase 133 (Business Logic) after planner defines title template format

2. **Season Format Validation**
   - What we know: User decisions specify "2024-2025" format (full years, hyphen-separated)
   - What's unclear: Whether to enforce format validation on term creation (regex pattern), or allow free-form
   - Recommendation: Implement validation during Sportlink sync - sync code should create terms in correct format, manual admin creation can be free-form (admin responsibility)

3. **Timezone Handling for Dates**
   - What we know: ACF Date Picker stores YYYYMMDD format (no time/timezone)
   - What's unclear: Whether match_date/processing_date need timezone awareness, or if date-only is sufficient
   - Recommendation: Date-only is appropriate for discipline cases (events happened on specific calendar day, not specific moment). If time precision needed later, upgrade to ACF Date Time Picker field.

4. **REST API Write Permissions**
   - What we know: Sportlink sync needs to create/update discipline cases via REST
   - What's unclear: Whether sync uses admin credentials, service account, or custom capability check
   - Recommendation: Follow existing Sportlink sync patterns for teams/commissies (Phase 133 research) - likely uses admin user or elevated capability

## Sources

### Primary (HIGH confidence)
- [WordPress register_post_type() Documentation](https://developer.wordpress.org/reference/functions/register_post_type/) - CPT registration parameters and best practices
- [WordPress register_taxonomy() Documentation](https://developer.wordpress.org/reference/functions/register_taxonomy/) - Taxonomy registration for multiple post types
- [ACF WP REST API Integration](https://www.advancedcustomfields.com/resources/wp-rest-api-integration/) - Official ACF REST exposure documentation
- [ACF Date Picker Field](https://www.advancedcustomfields.com/resources/date-picker/) - Date field storage and return formats
- [ACF Post Object Field](https://www.advancedcustomfields.com/resources/post-object/) - Single vs multiple selection behavior
- [ACF Relationship Field](https://www.advancedcustomfields.com/resources/relationship/) - Array return format documentation
- [ACF Number Field](https://www.advancedcustomfields.com/resources/number/) - Number field storage as string/integer
- [WordPress get_term_meta() Documentation](https://developer.wordpress.org/reference/functions/get_term_meta/) - Term meta API for current season flag
- [WordPress update_term_meta() Documentation](https://developer.wordpress.org/reference/functions/update_term_meta/) - Setting term metadata

### Secondary (MEDIUM confidence)
- [Adding REST API Support For Custom Content Types](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-rest-api-support-for-custom-content-types/) - Official REST API handbook (verified with official docs)
- [Using the Same Taxonomy for Multiple Post Types](https://metabox.io/using-same-taxonomy-for-post-types/) - Shared taxonomy patterns (verified approach)
- [Post Object vs Relationship Field Discussion](https://support.advancedcustomfields.com/forums/topic/post-object-vs-relationship-which-should-i-use/) - Community guidance on field type selection
- [ACF Field Validation Filter Documentation](https://www.advancedcustomfields.com/resources/acf-validate_value/) - Official validation hook documentation
- [ACF Tab Field Organization](https://www.advancedcustomfields.com/blog/organizing-custom-fields-inside-wordpress-acf/) - Official ACF blog on field organization

### Tertiary (LOW confidence)
- [Sportlink WordPress Plugin (open source)](https://github.com/RichardVanDerMeer/wordpress-sportlink.club.dataservices) - Community Sportlink integration example (for reference only, not authoritative)
- [WordPress API Integration Best Practices 2026](https://www.sevensquaretech.com/wordpress-api-integration-guide/) - General integration patterns (general advice, not WordPress-specific)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - ACF Pro is existing dependency, WordPress CPT/REST/Taxonomy APIs are native and well-documented
- Architecture: HIGH - Patterns directly from existing Stadion codebase (`class-post-types.php`, `class-taxonomies.php`, ACF JSON files)
- Pitfalls: MEDIUM to HIGH - Based on official documentation + known ACF REST API quirks (date format, show_in_rest requirement) verified in GitHub issues
- Code examples: HIGH - All examples derived from existing Stadion implementation or official WordPress/ACF documentation

**Research date:** 2026-02-03
**Valid until:** 60 days (2026-04-04) - WordPress/ACF core APIs are stable, REST API patterns well-established
