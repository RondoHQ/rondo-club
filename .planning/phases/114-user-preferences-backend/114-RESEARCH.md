# Phase 114: User Preferences Backend - Research

**Researched:** 2026-01-29
**Domain:** WordPress REST API, User Meta Storage, Custom Fields Integration
**Confidence:** HIGH

## Summary

Research into implementing per-user column preference storage for the People list via WordPress REST API endpoints. This phase builds backend-only infrastructure to persist column visibility and order preferences in `wp_usermeta`, following established patterns in the Stadion codebase.

Key findings: Stadion already implements multiple user preference endpoints (`/stadion/v1/user/theme-preferences`, `/user/dashboard-settings`) that provide proven patterns for this work. The Custom Fields Manager class exposes ACF field metadata programmatically, enabling preference validation against current field definitions. WordPress user_meta provides simple, per-user storage with immediate consistency.

The architecture is straightforward: two endpoints (GET and PATCH) at `/stadion/v1/user/list-preferences`, storing a simple array of column IDs in display order, with validation against active ACF fields to reject deleted field references.

**Primary recommendation:** Follow existing `update_theme_preferences()` pattern from class-rest-api.php — PATCH endpoint with optional parameters, merge with defaults, store in single user_meta key.

## Standard Stack

### Core Technologies

| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| WordPress user_meta | WP 6.0+ | Per-user preference storage | Native WordPress API, zero additional dependencies, immediate consistency |
| WordPress REST API | WP 6.0+ | HTTP interface for preferences | Already used throughout Stadion (`/stadion/v1/*` namespace) |
| ACF Custom Fields Manager | Current | Field metadata retrieval | Exposes active field definitions for validation (Phase 87-88) |
| PHP 8.0+ | 8.0+ | Server-side validation | Project requirement |

### Supporting Utilities

| Utility | Purpose | When to Use |
|---------|---------|-------------|
| `get_user_meta()` | Retrieve user preferences | GET endpoint, default merging |
| `update_user_meta()` | Persist user preferences | PATCH endpoint, save after validation |
| `is_user_logged_in()` | Permission check | Both endpoints (existing pattern) |
| `sanitize_text_field()` | Input sanitization | Column ID validation |
| `\Stadion\CustomFields\Manager::get_fields()` | Active field retrieval | Validation against current schema |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Single array of column IDs | Separate order/visibility arrays | More complex data structure, separate `column_order` not needed (array order is the order) |
| user_meta | Options table | Options are site-wide, need per-user storage |
| user_meta | Custom table | Over-engineering for simple key-value storage |
| Full field objects in response | Column IDs only | Response includes field metadata for UI (labels, types) — see Response Format below |

**Installation:**
No additional packages needed — uses WordPress core and existing Stadion classes.

## Architecture Patterns

### Recommended Endpoint Structure

```
REST API Endpoints:
/stadion/v1/user/list-preferences
├── GET  - Retrieve current user's preferences (with defaults)
└── PATCH - Update current user's preferences (partial updates)

Storage:
wp_usermeta
├── meta_key: stadion_people_list_preferences
└── meta_value: JSON-encoded array of column IDs
```

### Pattern 1: User Meta Storage for Preferences

**What:** Store preferences in `wp_usermeta` with structured key naming.

**When to use:** Any per-user configuration that needs simple persistence.

**Example from Stadion:**
```php
// Source: includes/class-rest-api.php:834-842 (theme preferences)
public function get_theme_preferences( $request ) {
    $user_id = get_current_user_id();

    $color_scheme = get_user_meta( $user_id, 'stadion_color_scheme', true );
    if ( empty( $color_scheme ) ) {
        $color_scheme = 'system';
    }

    return rest_ensure_response([
        'color_scheme' => $color_scheme,
        'accent_color' => $accent_color,
    ]);
}
```

**Apply to Phase 114:**
```php
// Get preferences with defaults
$user_id = get_current_user_id();
$preferences = get_user_meta( $user_id, 'stadion_people_list_preferences', true );

// Default visible columns (hardcoded from CONTEXT.md)
if ( empty( $preferences ) || ! is_array( $preferences ) ) {
    $preferences = [ 'team', 'labels', 'modified' ];
}

return rest_ensure_response( $preferences );
```

### Pattern 2: PATCH Endpoint with Validation

**What:** PATCH endpoint accepts optional parameters, validates, merges with existing data, persists atomically.

**When to use:** Updating user preferences that have multiple properties or need validation against system state.

**Example from Stadion:**
```php
// Source: includes/class-rest-api.php:986-1022 (dashboard settings)
public function update_dashboard_settings( $request ) {
    $user_id = get_current_user_id();

    $visible_cards = $request->get_param( 'visible_cards' );

    // Update visible cards if provided
    if ( $visible_cards !== null ) {
        // Filter to only valid card IDs
        $visible_cards = array_values(
            array_intersect( $visible_cards, self::VALID_DASHBOARD_CARDS )
        );
        update_user_meta( $user_id, 'stadion_dashboard_visible_cards', $visible_cards );
    }

    // Return updated settings
    return rest_ensure_response([
        'visible_cards' => $updated_visible,
    ]);
}
```

**Apply to Phase 114:**
```php
// PATCH /stadion/v1/user/list-preferences
public function update_list_preferences( $request ) {
    $user_id = get_current_user_id();

    // Handle reset action
    if ( $request->get_param( 'reset' ) === true ) {
        delete_user_meta( $user_id, 'stadion_people_list_preferences' );
        return rest_ensure_response([
            'visible_columns' => $this->get_default_columns(),
            'reset' => true,
        ]);
    }

    // Get new columns from request
    $columns = $request->get_param( 'visible_columns' );

    // Empty array = reset to defaults (CONTEXT.md requirement)
    if ( empty( $columns ) || ! is_array( $columns ) ) {
        delete_user_meta( $user_id, 'stadion_people_list_preferences' );
        return rest_ensure_response([
            'visible_columns' => $this->get_default_columns(),
        ]);
    }

    // Validate columns against available fields
    $valid_columns = $this->get_valid_column_ids();
    $columns = array_values( array_intersect( $columns, $valid_columns ) );

    // Persist
    update_user_meta( $user_id, 'stadion_people_list_preferences', $columns );

    return rest_ensure_response([
        'visible_columns' => $columns,
    ]);
}
```

### Pattern 3: Custom Fields Integration for Validation

**What:** Use `\Stadion\CustomFields\Manager::get_fields()` to retrieve active field definitions for validation.

**When to use:** When preferences reference custom fields that can be added/removed dynamically.

**Example:**
```php
// Source: includes/customfields/class-manager.php:412-459
public function get_fields( string $post_type, bool $include_inactive = false ): array {
    // Returns array of field definitions with 'name', 'type', 'label', etc.
    // $include_inactive = false filters to only active fields
}
```

**Apply to Phase 114:**
```php
// Get valid column IDs (core + active custom fields)
private function get_valid_column_ids(): array {
    // Core columns (always available)
    $core_columns = [ 'team', 'labels', 'modified' ];

    // Active custom fields from ACF
    $manager = new \Stadion\CustomFields\Manager();
    $custom_fields = $manager->get_fields( 'person', false ); // active only

    $custom_field_names = array_column( $custom_fields, 'name' );

    return array_merge( $core_columns, $custom_field_names );
}
```

### Pattern 4: Response Format with Metadata

**What:** Return both the preference data AND metadata about available options for UI rendering.

**When to use:** When frontend needs to render configuration UI (Phase 115) but this phase only builds backend.

**Structure:**
```php
// GET /stadion/v1/user/list-preferences response
[
    'visible_columns' => [ 'team', 'labels', 'telephone', 'modified' ],
    'available_columns' => [
        [
            'id' => 'team',
            'label' => 'Team',
            'type' => 'core',
        ],
        [
            'id' => 'labels',
            'label' => 'Labels',
            'type' => 'core',
        ],
        [
            'id' => 'telephone',
            'label' => 'Telephone',
            'type' => 'text',
            'custom' => true,
        ],
        [
            'id' => 'modified',
            'label' => 'Last Modified',
            'type' => 'core',
        ],
    ],
]
```

**Rationale:** Frontend (Phase 115) needs field labels and types to render column picker UI. Including `available_columns` in GET response eliminates need for separate endpoint.

### Anti-Patterns to Avoid

- **Separate column_order array:** Array order determines display order — no separate tracking needed
- **Storing full field objects:** Store field names only, join with field definitions on read
- **DELETE endpoint:** Use PATCH with `{ reset: true }` instead (follows CONTEXT.md decision)
- **Caching user_meta:** WordPress core already implements object cache for user_meta — don't add another layer
- **Validating on every GET:** Validate on PATCH only, trust stored data (deleted fields fail gracefully in UI)

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| User preference storage | Custom database table | WordPress user_meta API | Zero overhead, immediate consistency, core WordPress pattern |
| Permission checking | Custom JWT/token validation | `is_user_logged_in()` permission callback | Already configured with nonce in wpApi client |
| Column ID validation | Manual string checks | `array_intersect()` with valid list | Native PHP, efficient, readable |
| Default merging | Custom merge logic | Check `empty()` and return defaults | Simple, follows existing patterns |
| Field metadata retrieval | Direct ACF database queries | `CustomFields\Manager::get_fields()` | Already implemented (Phase 87), tested, handles active/inactive |

**Key insight:** WordPress and Stadion's existing abstractions cover all requirements — no custom infrastructure needed.

## Common Pitfalls

### Pitfall 1: Validating Against Deleted Fields Too Aggressively

**What goes wrong:** Rejecting entire preference payload when a single column ID references a deleted custom field causes user to lose all preferences.

**Why it happens:** Overly strict validation treats invalid column IDs as fatal errors.

**How to avoid:**
- Filter out invalid column IDs silently (log warning, don't reject)
- Return filtered valid columns in response
- Frontend gracefully handles missing fields (Phase 115)

**Warning signs:**
- User loses all column preferences after field is deleted
- Error responses on GET endpoint (should never fail)

**Implementation:**
```php
// Good: Filter invalid, keep valid
$valid_columns = $this->get_valid_column_ids();
$columns = array_values( array_intersect( $columns, $valid_columns ) );

if ( count( $columns ) !== count( $request_columns ) ) {
    error_log( 'Stadion: Filtered invalid column IDs from preferences' );
}

// Bad: Reject entirely
if ( array_diff( $columns, $valid_columns ) ) {
    return new \WP_Error( 'invalid_columns', 'Unknown column IDs' );
}
```

### Pitfall 2: Not Handling Empty Array vs Null

**What goes wrong:** Empty array `[]` and missing user_meta both result in empty check, but have different meanings per CONTEXT.md (empty array = reset to defaults).

**Why it happens:** PHP's `empty()` returns true for both `[]` and `false` (missing user_meta).

**How to avoid:**
- Use strict type checking: `is_array( $prefs ) && count( $prefs ) === 0`
- Differentiate between "never set" and "explicitly cleared"

**Warning signs:**
- User can't reset preferences to defaults
- GET endpoint returns different defaults than PATCH reset

**Implementation:**
```php
// Get stored preferences
$preferences = get_user_meta( $user_id, 'stadion_people_list_preferences', true );

// Case 1: Never set (false from get_user_meta)
if ( $preferences === false || $preferences === '' ) {
    $preferences = $this->get_default_columns();
}

// Case 2: Explicitly set to empty array (reset to defaults)
if ( is_array( $preferences ) && count( $preferences ) === 0 ) {
    $preferences = $this->get_default_columns();
}
```

### Pitfall 3: Forgetting Name Column Exclusion

**What goes wrong:** Including 'name' (or 'first_name') in `visible_columns` array creates confusion — CONTEXT.md states "Name column is always visible and first — not included in preferences."

**Why it happens:** Mixing core field IDs with preference storage without clear exclusion rules.

**How to avoid:**
- Filter out 'name' from incoming PATCH requests
- Never include 'name' in `available_columns` metadata
- Document in code comments

**Warning signs:**
- Frontend receives 'name' in preferences and tries to toggle it
- Column order includes name position (should always be first)

**Implementation:**
```php
// Define columns excluded from preferences
private const EXCLUDED_COLUMNS = [ 'name', 'first_name' ];

// Filter on PATCH
$columns = array_values(
    array_diff(
        $request->get_param( 'visible_columns' ),
        self::EXCLUDED_COLUMNS
    )
);
```

## Code Examples

### Complete GET Endpoint Implementation

```php
// Source: Based on includes/class-rest-api.php patterns

/**
 * Get user's people list column preferences
 *
 * @param \WP_REST_Request $request The request object.
 * @return \WP_REST_Response Response with visible_columns and available_columns.
 */
public function get_list_preferences( $request ) {
    $user_id = get_current_user_id();

    // Get stored preferences
    $visible_columns = get_user_meta( $user_id, 'stadion_people_list_preferences', true );

    // Default visible columns if not set or empty
    if ( empty( $visible_columns ) || ! is_array( $visible_columns ) ) {
        $visible_columns = $this->get_default_columns();
    }

    // Get available columns for UI rendering
    $available_columns = $this->get_available_columns_metadata();

    return rest_ensure_response([
        'visible_columns' => $visible_columns,
        'available_columns' => $available_columns,
    ]);
}

/**
 * Default visible columns (hardcoded per CONTEXT.md)
 *
 * @return array Column IDs
 */
private function get_default_columns(): array {
    return [ 'team', 'labels', 'modified' ];
}

/**
 * Get metadata for all available columns
 *
 * @return array Column definitions with id, label, type, custom flag
 */
private function get_available_columns_metadata(): array {
    $columns = [];

    // Core columns (always available, order matters for UI)
    $core = [
        [ 'id' => 'team', 'label' => 'Team', 'type' => 'core' ],
        [ 'id' => 'labels', 'label' => 'Labels', 'type' => 'core' ],
        [ 'id' => 'modified', 'label' => 'Last Modified', 'type' => 'core' ],
    ];

    $columns = array_merge( $columns, $core );

    // Custom fields from ACF
    $manager = new \Stadion\CustomFields\Manager();
    $custom_fields = $manager->get_fields( 'person', false ); // active only

    foreach ( $custom_fields as $field ) {
        $columns[] = [
            'id' => $field['name'],
            'label' => $field['label'],
            'type' => $field['type'],
            'custom' => true,
        ];
    }

    return $columns;
}
```

### Complete PATCH Endpoint Implementation

```php
/**
 * Update user's people list column preferences
 *
 * @param \WP_REST_Request $request The request object.
 * @return \WP_REST_Response|\WP_Error Response with updated preferences or error.
 */
public function update_list_preferences( $request ) {
    $user_id = get_current_user_id();

    // Handle reset action
    if ( $request->get_param( 'reset' ) === true ) {
        delete_user_meta( $user_id, 'stadion_people_list_preferences' );

        return rest_ensure_response([
            'visible_columns' => $this->get_default_columns(),
            'available_columns' => $this->get_available_columns_metadata(),
            'reset' => true,
        ]);
    }

    // Get requested columns
    $columns = $request->get_param( 'visible_columns' );

    // Empty array = reset to defaults (per CONTEXT.md)
    if ( ! is_array( $columns ) || count( $columns ) === 0 ) {
        delete_user_meta( $user_id, 'stadion_people_list_preferences' );

        return rest_ensure_response([
            'visible_columns' => $this->get_default_columns(),
            'available_columns' => $this->get_available_columns_metadata(),
        ]);
    }

    // Validate columns against available fields
    $valid_columns = $this->get_valid_column_ids();
    $validated_columns = array_values( array_intersect( $columns, $valid_columns ) );

    // Log if filtering occurred (deleted fields)
    if ( count( $validated_columns ) !== count( $columns ) ) {
        error_log(
            sprintf(
                'Stadion: Filtered %d invalid column IDs from user %d preferences',
                count( $columns ) - count( $validated_columns ),
                $user_id
            )
        );
    }

    // Persist validated preferences
    update_user_meta( $user_id, 'stadion_people_list_preferences', $validated_columns );

    return rest_ensure_response([
        'visible_columns' => $validated_columns,
        'available_columns' => $this->get_available_columns_metadata(),
    ]);
}

/**
 * Get valid column IDs (core + active custom fields)
 *
 * @return array Column IDs
 */
private function get_valid_column_ids(): array {
    // Core columns
    $core = [ 'team', 'labels', 'modified' ];

    // Custom fields from ACF
    $manager = new \Stadion\CustomFields\Manager();
    $custom_fields = $manager->get_fields( 'person', false ); // active only
    $custom_names = array_column( $custom_fields, 'name' );

    return array_merge( $core, $custom_names );
}
```

### Route Registration

```php
// Add to includes/class-rest-api.php::register_routes()

// Get user's people list preferences
register_rest_route(
    'stadion/v1',
    '/user/list-preferences',
    [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_list_preferences' ],
        'permission_callback' => 'is_user_logged_in',
    ]
);

// Update user's people list preferences
register_rest_route(
    'stadion/v1',
    '/user/list-preferences',
    [
        'methods'             => 'PATCH',
        'callback'            => [ $this, 'update_list_preferences' ],
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'visible_columns' => [
                'required'          => false,
                'validate_callback' => function ( $param ) {
                    return $param === null || is_array( $param );
                },
            ],
            'reset' => [
                'required'          => false,
                'validate_callback' => function ( $param ) {
                    return is_bool( $param );
                },
            ],
        ],
    ]
);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Client-side preferences (localStorage) | Server-side user_meta | Phase 114 (2026-01) | Preferences persist across devices, survive cache clears |
| Full column config objects | Simple array of IDs | Phase 114 (2026-01) | Smaller payload, easier validation, join with field defs on read |
| Separate endpoints for each preference type | Single preferences endpoint with partial updates | Emerging pattern | Simpler API surface, fewer round-trips |

**Deprecated/outdated:**
- N/A - This is new functionality

## Open Questions

### Question 1: Cache Invalidation Strategy

**What we know:** WordPress automatically caches `get_user_meta()` results in object cache. TanStack Query on frontend caches API responses.

**What's unclear:** Should PATCH endpoint return cache headers to control frontend cache, or rely on TanStack Query's default staleTime?

**Recommendation:** Rely on TanStack Query defaults (staleTime: 0 means refetch on component mount). Preferences change infrequently — no special cache control needed.

### Question 2: Validation Log Level

**What we know:** Code logs warnings when filtering invalid column IDs (deleted fields).

**What's unclear:** Should warnings also be returned in API response for client-side debugging?

**Recommendation:** Log only (server-side). Returning warnings in response adds complexity without clear user benefit. Deleted fields fail gracefully in UI (Phase 115 responsibility).

### Question 3: Preference Versioning

**What we know:** Data structure may evolve (e.g., adding sort preferences in future phases).

**What's unclear:** Should we version the stored preference format now, or handle migrations ad-hoc?

**Recommendation:** No versioning yet. Single array structure is simple. If structure changes (e.g., becomes object with `{ visible_columns, default_sort }`), add migration in GET endpoint to convert old format.

## Sources

### Primary (HIGH confidence)

- **includes/class-rest-api.php** - Lines 144-210 (theme-preferences and dashboard-settings patterns)
- **includes/customfields/class-manager.php** - Lines 412-459 (get_fields() implementation)
- **WordPress Codex user_meta functions** - get_user_meta(), update_user_meta(), delete_user_meta()
- **.planning/phases/114-user-preferences-backend/114-CONTEXT.md** - User decisions from discuss phase

### Secondary (MEDIUM confidence)

- **src/hooks/usePeople.js** - Existing data fetching patterns for TanStack Query integration
- **.planning/research/ARCHITECTURE.md** - Lines 214-283 (preference storage patterns)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components already in use in Stadion codebase
- Architecture: HIGH - Direct application of existing patterns (theme-preferences, dashboard-settings)
- Pitfalls: HIGH - Based on real WordPress user_meta edge cases and CONTEXT.md decisions
- Code examples: HIGH - Adapted from working code in class-rest-api.php

**Research date:** 2026-01-29
**Valid until:** 2026-03-01 (30 days — stable WordPress APIs)
