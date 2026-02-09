# Phase 157: Fee Category REST API - Research

**Researched:** 2026-02-09
**Domain:** WordPress REST API with JSON validation and CRUD operations
**Confidence:** HIGH

## Summary

Phase 157 extends the existing membership fee REST endpoints to expose full category definitions (not just amounts) and support CRUD operations for managing categories per season. The research reveals a well-established WordPress REST API pattern in the codebase: GET endpoints return full data structures, POST endpoints use partial updates with validation, and errors return WP_Error objects with status codes. The key decision is whether to use full replacement (frontend sends entire category config) or individual operations (add/edit/remove/reorder).

The codebase already has the complete category CRUD infrastructure in `MembershipFees` class (Phase 155-156), so Phase 157 is primarily about exposing these operations through REST endpoints with proper validation. The existing `/membership-fees/settings` endpoint currently returns flat amounts; it needs to evolve to return full category objects, while maintaining backward compatibility for existing frontends until Phase 159 updates display code.

**Primary recommendation:** Use full replacement pattern (frontend sends complete category array) for settings POST endpoint. This matches the existing pattern in `update_membership_fee_settings()` which receives partial updates and merges them, and simplifies Settings UI implementation in Phase 158.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress REST API | 6.0+ | REST endpoint registration and validation | Built-in WordPress REST infrastructure used throughout codebase |
| WP_Error | Core | Error response objects | WordPress standard for REST API error handling |
| WP_REST_Server | Core | HTTP method constants | WordPress constant for REST route registration |
| WP_REST_Request | Core | Request parameter access | WordPress request abstraction for REST endpoints |
| WP_REST_Response | Core | Response wrapper | WordPress response abstraction via `rest_ensure_response()` |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| validate_callback | Core | Inline validation | Parameter-level validation in route args |
| sanitize_callback | Core | Input sanitization | Cleaning input before processing |
| permission_callback | Core | Authorization | Check user permissions for endpoint access |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Full replacement | Individual operations (add/edit/remove) | Full replacement is simpler to implement and matches existing `update_settings_for_season()` pattern, but individual operations would provide more granular control |
| WP_Error | Custom JSON errors | WP_Error is WordPress standard and works seamlessly with REST infrastructure |
| Inline validation | Schema validation | Inline callbacks provide more flexibility for complex validation logic like duplicate slug detection |

**Installation:**
No additional packages required - uses WordPress core REST API.

## Architecture Patterns

### Recommended Endpoint Structure
```
GET  /rondo/v1/membership-fees/settings           # Returns full category objects (update existing)
POST /rondo/v1/membership-fees/settings           # Updates categories (extend existing)
GET  /rondo/v1/fees                               # Add top-level categories key to response (update existing)
```

### Pattern 1: Full Replacement with Validation
**What:** POST endpoint receives complete category configuration for a season, validates it, and replaces the stored config
**When to use:** For admin settings where the UI manages the full state
**Example:**
```php
// Source: includes/class-rest-api.php (existing update_membership_fee_settings pattern)
public function update_membership_fee_settings( $request ) {
    $season = $request->get_param( 'season' );
    $categories = $request->get_param( 'categories' );

    // Validate entire category structure
    $validation_errors = $this->validate_categories( $categories );
    if ( ! empty( $validation_errors ) ) {
        return new \WP_Error(
            'invalid_categories',
            'Category validation failed',
            [ 'status' => 400, 'errors' => $validation_errors ]
        );
    }

    // Save entire config
    $membership_fees = new \Rondo\Fees\MembershipFees();
    $membership_fees->save_categories_for_season( $categories, $season );

    // Return updated state for both seasons
    return rest_ensure_response( /* full response */ );
}
```

### Pattern 2: Validation with Warnings vs Errors
**What:** Distinguish between hard errors (duplicate slugs, missing required fields) and warnings (duplicate age class assignments)
**When to use:** When user needs feedback but shouldn't be blocked
**Example:**
```php
// Validation returns both errors and warnings
function validate_categories( $categories ) {
    $errors = [];
    $warnings = [];

    // Hard errors prevent save
    if ( /* duplicate slug */ ) {
        $errors[] = [ 'field' => 'slug', 'message' => 'Duplicate slug' ];
    }

    // Warnings inform but don't block
    if ( /* age class overlap */ ) {
        $warnings[] = [ 'field' => 'age_classes', 'message' => 'Age class assigned to multiple categories' ];
    }

    return [ 'errors' => $errors, 'warnings' => $warnings ];
}
```

### Pattern 3: GET Response Enhancement
**What:** Add category metadata to fee list endpoint without breaking existing structure
**When to use:** When extending existing endpoints with new data
**Example:**
```php
// Source: includes/class-rest-api.php get_fee_list() pattern
public function get_fee_list( $request ) {
    $season = /* determine season */;
    $fees = new \Rondo\Fees\MembershipFees();

    // Get member list (existing logic)
    $results = /* existing member calculation */;

    // Add categories metadata (new)
    $categories = $fees->get_categories_for_season( $season );

    return rest_ensure_response([
        'members' => $results,
        'categories' => $categories,  // NEW: Full category definitions
        'season' => $season,
    ]);
}
```

### Anti-Patterns to Avoid
- **Hardcoded category slugs in validation:** Category slugs are dynamic per season, don't validate against hardcoded list
- **Silent failure on validation errors:** Use WP_Error to return meaningful error messages, don't just return false
- **Breaking changes to existing response structure:** Add new keys, don't replace existing ones until Phase 159

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSON validation | Custom validator functions | WordPress validate_callback with WP_Error | Built-in REST API validation with proper error formatting |
| Response formatting | Manual JSON encoding | rest_ensure_response() | Handles headers, status codes, error formatting automatically |
| Slug sanitization | Custom slug generator | sanitize_title() | WordPress standard, handles Unicode, special chars |
| Permission checking | Custom auth logic | check_admin_permission() | Already exists in codebase, maintains consistency |
| Category storage | Custom table or file storage | WordPress Options API | Already used by MembershipFees class, maintains consistency |

**Key insight:** WordPress REST API provides comprehensive validation, sanitization, and error handling infrastructure. Use it rather than building custom solutions.

## Common Pitfalls

### Pitfall 1: Validation Callback Return Values
**What goes wrong:** Validation callbacks that return false don't provide error messages to the client
**Why it happens:** WordPress validation callbacks expect boolean return, but clients need detailed error messages
**How to avoid:** Return WP_Error from endpoint callback after validation, not from validate_callback
**Warning signs:** Generic "400 Bad Request" with no error details

### Pitfall 2: Breaking Existing Frontend with Response Changes
**What goes wrong:** Changing GET response structure breaks existing ContributieList.jsx which expects flat amounts
**Why it happens:** Frontend code relies on specific response shape
**How to avoid:**
- Phase 157: GET settings returns full objects, GET fees adds categories key (non-breaking)
- Phase 158: Settings UI uses new format
- Phase 159: Fee list UI switches to category metadata
**Warning signs:** Frontend errors after API deploy, missing data in UI

### Pitfall 3: Empty Category Config Edge Cases
**What goes wrong:** Validation rejects empty category arrays, but they're valid for new seasons being set up
**Why it happens:** Overly strict validation
**How to avoid:** Follow Phase 156 error handling pattern: silent for missing/empty config, loud for invalid data
**Warning signs:** Cannot save settings for new season, errors when first loading empty season

### Pitfall 4: Season Parameter Validation
**What goes wrong:** Accepting any season parameter allows frontend to create arbitrary future seasons
**Why it happens:** Insufficient validation of season parameter
**How to avoid:** Validate season is exactly current or next season (matches existing pattern in update_membership_fee_settings)
**Warning signs:** Database fills with future season options, confusion about which seasons exist

### Pitfall 5: Duplicate Slug Detection Across Categories
**What goes wrong:** Validation checks for duplicate slugs but doesn't account for case sensitivity or whitespace
**Why it happens:** Simple array_count_values() doesn't normalize
**How to avoid:** Normalize slugs before checking duplicates (lowercase, trim, sanitize_title)
**Warning signs:** Frontend shows validation error but slugs look different

## Code Examples

Verified patterns from the codebase:

### GET Endpoint with Full Category Objects
```php
// Source: includes/class-rest-api.php lines 2605-2622 (adapted)
public function get_membership_fee_settings( $request ) {
    $membership_fees = new \Rondo\Fees\MembershipFees();
    $current_season  = $membership_fees->get_season_key();
    $next_season     = $membership_fees->get_next_season_key();

    return rest_ensure_response([
        'current_season' => [
            'key'        => $current_season,
            'categories' => $membership_fees->get_categories_for_season( $current_season ),
        ],
        'next_season' => [
            'key'        => $next_season,
            'categories' => $membership_fees->get_categories_for_season( $next_season ),
        ],
    ]);
}
```

### POST Endpoint with Full Replacement
```php
// Source: Pattern from includes/class-rest-api.php lines 2632-2678
public function update_membership_fee_settings( $request ) {
    $membership_fees = new \Rondo\Fees\MembershipFees();
    $current_season  = $membership_fees->get_season_key();
    $next_season     = $membership_fees->get_next_season_key();
    $season          = $request->get_param( 'season' );

    // Validate season is current or next
    if ( ! in_array( $season, [ $current_season, $next_season ], true ) ) {
        return new \WP_Error(
            'invalid_season',
            'Season must be either current season or next season',
            [ 'status' => 400 ]
        );
    }

    // Get categories from request
    $categories = $request->get_param( 'categories' );

    // Validate category structure
    $validation = $this->validate_category_config( $categories );
    if ( ! empty( $validation['errors'] ) ) {
        return new \WP_Error(
            'invalid_categories',
            'Category validation failed',
            [ 'status' => 400, 'errors' => $validation['errors'], 'warnings' => $validation['warnings'] ]
        );
    }

    // Save categories
    $membership_fees->save_categories_for_season( $categories, $season );

    // Return updated settings for both seasons
    return rest_ensure_response([
        'current_season' => [
            'key'        => $current_season,
            'categories' => $membership_fees->get_categories_for_season( $current_season ),
        ],
        'next_season' => [
            'key'        => $next_season,
            'categories' => $membership_fees->get_categories_for_season( $next_season ),
        ],
        'warnings' => $validation['warnings'],
    ]);
}
```

### Category Validation Function
```php
// New validation function to add to class-rest-api.php
private function validate_category_config( $categories ) {
    $errors = [];
    $warnings = [];

    if ( ! is_array( $categories ) ) {
        $errors[] = [ 'field' => 'categories', 'message' => 'Categories must be an array' ];
        return [ 'errors' => $errors, 'warnings' => $warnings ];
    }

    // Allow empty array (silent for missing config per Phase 156 pattern)
    if ( empty( $categories ) ) {
        return [ 'errors' => [], 'warnings' => [] ];
    }

    $slugs = [];
    $age_class_map = []; // Track which categories use which age classes

    foreach ( $categories as $slug => $category ) {
        // Validate required fields
        if ( empty( $slug ) ) {
            $errors[] = [ 'field' => 'slug', 'message' => 'Slug is required' ];
            continue;
        }

        if ( ! isset( $category['label'] ) || trim( $category['label'] ) === '' ) {
            $errors[] = [ 'field' => "categories.{$slug}.label", 'message' => 'Label is required' ];
        }

        if ( ! isset( $category['amount'] ) || ! is_numeric( $category['amount'] ) || $category['amount'] < 0 ) {
            $errors[] = [ 'field' => "categories.{$slug}.amount", 'message' => 'Amount must be a non-negative number' ];
        }

        // Check for duplicate slugs (case-insensitive)
        $normalized_slug = strtolower( trim( $slug ) );
        if ( isset( $slugs[ $normalized_slug ] ) ) {
            $errors[] = [ 'field' => "categories.{$slug}.slug", 'message' => 'Duplicate slug' ];
        }
        $slugs[ $normalized_slug ] = true;

        // Track age class assignments for overlap detection
        if ( isset( $category['age_classes'] ) && is_array( $category['age_classes'] ) ) {
            foreach ( $category['age_classes'] as $age_class ) {
                $normalized_class = strtolower( trim( $age_class ) );
                if ( isset( $age_class_map[ $normalized_class ] ) ) {
                    // Duplicate age class assignment = WARNING, not error (per API-04)
                    $warnings[] = [
                        'field' => "categories.{$slug}.age_classes",
                        'message' => "Age class '{$age_class}' assigned to multiple categories",
                        'categories' => [ $age_class_map[ $normalized_class ], $slug ]
                    ];
                } else {
                    $age_class_map[ $normalized_class ] = $slug;
                }
            }
        }
    }

    return [ 'errors' => $errors, 'warnings' => $warnings ];
}
```

### Fee List Endpoint with Category Metadata
```php
// Source: includes/class-rest-api.php lines 2734-2850 (add category metadata)
public function get_fee_list( $request ) {
    $forecast = $request->get_param( 'forecast' );
    $fees     = new \Rondo\Fees\MembershipFees();

    // Determine season (existing logic)
    if ( $forecast ) {
        $season = $fees->get_next_season_key();
    } else {
        $season = $request->get_param( 'season' );
        if ( $season === null ) {
            $season = $fees->get_season_key();
        }
    }

    // Get member list (existing logic at lines 2752-2839)
    $results = /* ... existing member calculation ... */;

    // NEW: Get category metadata for frontend
    $categories_raw = $fees->get_categories_for_season( $season );
    $categories = [];
    foreach ( $categories_raw as $slug => $category ) {
        $categories[ $slug ] = [
            'label'      => $category['label'],
            'sort_order' => $category['sort_order'] ?? 999,
            'is_youth'   => $category['is_youth'] ?? false,
        ];
    }

    return rest_ensure_response([
        'members'    => $results,
        'categories' => $categories,  // NEW: Category metadata
        'season'     => $season,
        'forecast'   => $forecast,
    ]);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Flat fee amounts in REST response | Full category objects with metadata | Phase 157 (v21.0) | Frontend can display dynamic labels, sort order, colors based on config |
| Hardcoded category slugs in POST endpoint args | Accept arbitrary category structure | Phase 157 (v21.0) | Admin can create custom categories without code changes |
| Frontend hardcoded FEE_CATEGORIES in formatters.js | Frontend reads category metadata from API response | Phase 159 (after 157) | True per-season category configurability |

**Deprecated/outdated:**
- Flat amount-only settings response: Still returned for backward compatibility until Phase 159, but `categories` key is the new standard
- Hardcoded category slug validation: POST endpoint should accept any valid slug structure, not validate against hardcoded list

## Open Questions

1. **Should slug be user-editable or auto-generated from label?**
   - What we know: Phase 155 used hardcoded slugs (mini, pupil, etc), Phase 158 Settings UI needs to know
   - What's unclear: Whether admin should control slugs or they should auto-generate from labels
   - Recommendation: Make slugs user-editable but validate uniqueness. Slugs are keys in WordPress options array and used in API responses, so stability matters. Auto-generation would require slug regeneration on label edits, which could break external integrations. Better to let admin control slugs and validate uniqueness.

2. **Should validation allow completely empty category configs?**
   - What we know: Phase 156 pattern is "silent for missing config, loud for invalid data"
   - What's unclear: Whether POST endpoint should accept empty categories array
   - Recommendation: Accept empty array (saves empty config). This allows admin to clear all categories for a season if needed. Follow Phase 156 pattern: empty = valid, malformed = error.

3. **Should GET settings endpoint return warnings about config issues?**
   - What we know: POST endpoint returns warnings (duplicate age classes), GET returns current state
   - What's unclear: Whether GET should also analyze and return warnings
   - Recommendation: No, GET should only return stored data. Warnings are for save operations. GET running validation on every read would be expensive and unnecessary. Settings UI can call POST validation endpoint if it needs to check config without saving.

## Sources

### Primary (HIGH confidence)
- includes/class-rest-api.php - Existing REST endpoint patterns
- includes/class-membership-fees.php - Category CRUD methods from Phase 155-156
- src/pages/Contributie/ContributieList.jsx - Frontend usage patterns
- developer/src/content/docs/features/membership-fees.md - System documentation

### Secondary (MEDIUM confidence)
- .planning/phases/156-fee-category-backend-logic/156-01-PLAN.md - Phase 156 validation patterns
- .planning/phases/156-fee-category-backend-logic/156-CONTEXT.md - Error handling decisions

### Tertiary (LOW confidence)
- None - all research based on existing codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress core REST API, existing patterns in codebase
- Architecture: HIGH - Existing patterns in class-rest-api.php provide clear precedent
- Pitfalls: HIGH - Based on actual codebase structure and Phase 156 decisions

**Research date:** 2026-02-09
**Valid until:** 2026-03-09 (30 days - WordPress REST API patterns are stable)
