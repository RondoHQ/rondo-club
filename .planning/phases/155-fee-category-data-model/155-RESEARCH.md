# Phase 155: Fee Category Data Model - Research

**Researched:** 2026-02-08
**Domain:** WordPress Options API, PHP data structures, season-based configuration
**Confidence:** HIGH

## Summary

Phase 155 transforms the fee category storage from hardcoded constants to per-season configuration stored in WordPress options. The research reveals that:

1. The existing `rondo_membership_fees_{season}` option currently stores only amounts as a flat key-value array (`{ "mini": 130, "pupil": 180, ... }`)
2. The new structure will be a slug-keyed object where each category contains all its properties: `{ "senior": { label, amount, age_min, age_max, is_youth, sort_order }, ... }`
3. WordPress Options API is the correct storage mechanism (already established in v12.0) and requires no additional libraries
4. The existing season copy-forward logic occurs on first read when a season option doesn't exist - this needs updating to copy full category objects instead of just amounts

**Primary recommendation:** Use WordPress Options API with the existing option key pattern, store categories as a slug-keyed object with no wrapper, and update the season copy-forward logic in `get_settings_for_season()` to copy from previous season when available.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | 6.0+ | Persistent key-value storage | Built-in WordPress data storage for site-wide settings |
| PHP native arrays | 8.0+ | Data structure storage | WordPress options serialize PHP arrays natively |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None required | - | - | Pure WordPress core functionality |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WordPress Options | Custom database table | Options API is WordPress-native and matches existing v12.0 architecture decision |
| WordPress Options | WordPress Transients API | Transients are for temporary data; fee categories are permanent configuration |
| WordPress Options | Post meta | Post meta is for entity-level data; fee categories are site-wide settings |

**Installation:**
None required - WordPress Options API is built-in.

## Architecture Patterns

### Recommended Data Structure

**Current (Phase 154 and before):**
```php
// Option: rondo_membership_fees_2025-2026
[
  'mini' => 130,
  'pupil' => 180,
  'junior' => 230,
  'senior' => 255,
  'recreant' => 65,
  'donateur' => 55
]
```

**New (Phase 155 onwards):**
```php
// Option: rondo_membership_fees_2025-2026
[
  'mini' => [
    'label' => 'Mini',
    'amount' => 130,
    'age_min' => 6,
    'age_max' => 7,
    'is_youth' => true,
    'sort_order' => 1
  ],
  'pupil' => [
    'label' => 'Pupil',
    'amount' => 180,
    'age_min' => 8,
    'age_max' => 11,
    'is_youth' => true,
    'sort_order' => 2
  ],
  // ... etc
]
```

### Pattern 1: Helper Class with Read/Write Methods

**What:** Provide static or instance methods for reading and writing category configurations
**When to use:** Any code that needs to access category definitions
**Example:**
```php
// Read all categories for a season
$categories = $fee_helper->get_categories_for_season('2025-2026');
// Returns: ['mini' => [...], 'pupil' => [...], ...]

// Read single category
$senior = $fee_helper->get_category('senior', '2025-2026');
// Returns: ['label' => 'Senior', 'amount' => 255, ...]

// Write categories (full replacement)
$fee_helper->save_categories_for_season($categories, '2025-2026');
```

### Pattern 2: Season Copy-Forward with Fallback Chain

**What:** When a season option doesn't exist, copy from previous season or fall back to defaults
**When to use:** On first read of a new season's option
**Current implementation in MembershipFees::get_settings_for_season():**
```php
// If season option exists, use it
if ( $stored !== false && is_array( $stored ) ) {
    $settings = array_merge( self::DEFAULTS, $stored );
    return array_map( 'intval', $settings );
}

// Season option doesn't exist - check for migration (v12.0 legacy)
$current_season = $this->get_season_key();
if ( $season === $current_season ) {
    // Check if old global option exists (migration needed)
    $old_stored = get_option( self::OPTION_KEY, false );
    if ( $old_stored !== false && is_array( $old_stored ) ) {
        // Migrate and return
        update_option( $season_key, $old_stored );
        delete_option( self::OPTION_KEY );
        return $merged;
    }
}

// No data for this season, return defaults
return self::DEFAULTS;
```

**Updated pattern for Phase 155:**
```php
// If season option exists, use it
if ( $stored !== false && is_array( $stored ) ) {
    return $stored; // Categories object
}

// Season option doesn't exist - try copy from previous season
$previous_season = $this->get_previous_season_key( $season );
if ( $previous_season !== null ) {
    $previous_categories = get_option(
        $this->get_option_key_for_season( $previous_season ),
        false
    );

    if ( $previous_categories !== false && is_array( $previous_categories ) ) {
        // Copy previous season as starting point for new season
        update_option( $season_key, $previous_categories );
        return $previous_categories;
    }
}

// No previous season data - return empty categories object
return [];
```

### Pattern 3: Age Range Mapping (Current Implementation)

**What:** `parse_age_group()` maps Sportlink age group strings to category slugs using hardcoded age ranges
**Current location:** `MembershipFees::parse_age_group()` (lines 177-229)
**Phase 155 impact:** No changes to this method in Phase 155 - it continues using hardcoded ranges. Phase 156 will update it to read from category config.

```php
// Current hardcoded implementation (unchanged in Phase 155)
if ( preg_match( '/^Onder\s+(\d+)$/i', $normalized, $matches ) ) {
    $age = (int) $matches[1];

    // Map age ranges to fee categories (HARDCODED)
    if ( $age >= 6 && $age <= 7 ) {
        return 'mini';
    }
    if ( $age >= 8 && $age <= 11 ) {
        return 'pupil';
    }
    // ... etc
}
```

### Anti-Patterns to Avoid

- **Top-level amount keys:** Don't keep backward-compatible `['mini' => 130]` alongside new structure. Clean break with no backward compatibility per CONTEXT.md.
- **Wrapper objects:** Don't nest categories inside `{ categories: { ... } }`. The option value IS the categories object directly.
- **Version fields:** Don't add `version: 2` or metadata fields. Trust the data structure per CONTEXT.md decision.
- **Inferring is_youth from age ranges:** Don't calculate `is_youth` - store it explicitly as a boolean field.

## Don't Hand-Roll

This phase uses only WordPress core functionality. No custom solutions needed.

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Data serialization | Custom JSON encoder | PHP array + WordPress Options | WordPress automatically serializes arrays |
| Season key calculation | New date utilities | Existing `get_season_key()` | Already implemented and tested in v12.0 |
| Option key generation | String concatenation | Existing `get_option_key_for_season()` | Already implemented (line 53) |
| Data validation | Custom validator class | Simple PHP validation in helpers | Minimal complexity per CONTEXT.md |

**Key insight:** WordPress Options API handles all persistence concerns. The work in this phase is data structure transformation, not storage infrastructure.

## Common Pitfalls

### Pitfall 1: Backward Compatibility Temptation

**What goes wrong:** Developer adds temporary code to support both old and new data formats during transition
**Why it happens:** Fear of breaking existing functionality
**How to avoid:** Follow CONTEXT.md decision: "No backward compatibility layer. Phase 155 changes the data shape with no backward compatibility... Do not deploy Phase 155 alone - deploy only after Phase 156 is also complete."
**Warning signs:**
- Code checking `if (is_int($value))` vs `if (is_array($value))`
- Temporary helper functions like `normalize_fee_data()`
- Feature flags or conditional logic based on data format

### Pitfall 2: Missing Season Copy-Forward Update

**What goes wrong:** New season reads return empty defaults instead of copying from previous season
**Why it happens:** Forgetting that `get_settings_for_season()` is the ONLY place where season data is initialized
**How to avoid:** Update the "season option doesn't exist" branch in `get_settings_for_season()` to copy full category objects from previous season
**Warning signs:**
- Next season shows empty categories when admin hasn't manually set them
- Admin needs to manually re-enter all categories every year
- Test with a season that has never been accessed before

### Pitfall 3: Not Handling Empty Previous Season

**What goes wrong:** Copy-forward logic crashes when previous season has no data either (fresh install scenario)
**Why it happens:** Assuming previous season always has data
**How to avoid:** After trying previous season copy, fall back to empty categories object `[]` per CONTEXT.md: "If previous season has no categories, new season gets an empty categories object"
**Warning signs:**
- PHP warnings when accessing non-existent options
- Fresh installs crash when first reading next season
- Unit tests fail on empty database scenario

### Pitfall 4: Sort Order Inconsistency

**What goes wrong:** Categories appear in different orders across seasons or after edits
**Why it happens:** Not preserving sort_order during copy-forward
**How to avoid:** Copy ALL fields including sort_order when copying from previous season
**Warning signs:**
- Category order changes unexpectedly when switching seasons
- Frontend display order differs from settings page order
- Categories appear alphabetically instead of by intended sequence

## Code Examples

### Reading Categories for a Season

```php
/**
 * Get all fee categories for a season
 *
 * @param string $season Season key (e.g., "2025-2026")
 * @return array Slug-keyed array of category objects
 */
public function get_categories_for_season( string $season ): array {
    $option_key = $this->get_option_key_for_season( $season );
    $stored = get_option( $option_key, false );

    // If exists, return as-is
    if ( $stored !== false && is_array( $stored ) ) {
        return $stored;
    }

    // Try copy from previous season
    $previous_season = $this->get_previous_season_key( $season );
    if ( $previous_season !== null ) {
        $previous = get_option(
            $this->get_option_key_for_season( $previous_season ),
            false
        );

        if ( $previous !== false && is_array( $previous ) ) {
            // Copy and save
            update_option( $option_key, $previous );
            return $previous;
        }
    }

    // No previous data - return empty
    return [];
}
```

### Writing Categories for a Season

```php
/**
 * Save fee categories for a season
 *
 * @param array  $categories Slug-keyed category objects
 * @param string $season     Season key (e.g., "2025-2026")
 * @return bool Success status
 */
public function save_categories_for_season( array $categories, string $season ): bool {
    $option_key = $this->get_option_key_for_season( $season );
    return update_option( $option_key, $categories );
}
```

### Reading Single Category

```php
/**
 * Get a single category by slug
 *
 * @param string      $slug   Category slug (e.g., "senior")
 * @param string|null $season Optional season key, defaults to current
 * @return array|null Category object or null if not found
 */
public function get_category( string $slug, ?string $season = null ): ?array {
    $season = $season ?? $this->get_season_key();
    $categories = $this->get_categories_for_season( $season );

    return $categories[ $slug ] ?? null;
}
```

### Getting Previous Season Key

```php
/**
 * Get the previous season key (one year before specified season)
 *
 * @param string $season Season key (e.g., "2025-2026")
 * @return string|null Previous season key or null if invalid format
 */
public function get_previous_season_key( string $season ): ?string {
    // Extract start year from "YYYY-YYYY" format
    if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
        return null;
    }

    $season_start_year = (int) $matches[1];
    $previous_start_year = $season_start_year - 1;

    return $previous_start_year . '-' . ( $previous_start_year + 1 );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded fee category definitions in PHP constants | Per-season category storage in WordPress options | v21.0 (Phase 155) | Admin can configure categories; no code deploys for fee changes |
| Per-season amounts only | Per-season full category config (label, amount, age ranges, etc.) | v21.0 (Phase 155) | All category metadata is configurable |
| Copy-forward amounts from defaults | Copy-forward full category objects from previous season | v21.0 (Phase 155) | New seasons inherit complete configuration automatically |
| `VALID_TYPES` constant for category slugs | Category slugs derived from option data | v21.0 (Phase 156, not 155) | No hardcoded list of valid categories |

**Deprecated/outdated:**
- `MembershipFees::DEFAULTS` constant: Still used in Phase 155 for backward compatibility, removed in Phase 156
- `MembershipFees::VALID_TYPES` constant: Still used in Phase 155, replaced by dynamic lookup in Phase 156
- `youth_categories` hardcoded array: Still used in Phase 155, replaced by `is_youth` flag lookup in Phase 156

## Open Questions

1. **Should `get_previous_season_key()` be added to MembershipFees class or to a separate helper?**
   - What we know: `get_season_key()` and `get_next_season_key()` already exist in MembershipFees
   - What's unclear: Whether previous-season logic is needed elsewhere or only for fee category copy-forward
   - Recommendation: Add to MembershipFees for consistency, even if only used internally for now

2. **What happens if admin deletes all categories for a season?**
   - What we know: CONTEXT.md says "No validation on read - trust the data"
   - What's unclear: Whether empty category object `[]` is intentional or data corruption
   - Recommendation: Allow empty categories object - it represents "no fees configured for this season" which is valid state

3. **Should the helper class be instance-based or use static methods?**
   - What we know: Existing `MembershipFees` class uses instance methods with dependency on other instance methods
   - What's unclear: Whether to add helpers to existing class or create new `FeeCategoryConfig` class
   - Recommendation: Add to existing `MembershipFees` class for consistency, use instance methods

## Sources

### Primary (HIGH confidence)
- **Codebase inspection:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-membership-fees.php` - Existing implementation of season-specific fee storage (v12.0)
- **Codebase inspection:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` - Current REST API for fee settings (GET/POST `/rondo/v1/membership-fees/settings`)
- **Project documentation:** `.planning/REQUIREMENTS.md` - DATA-01, DATA-02, DATA-03 requirements
- **Phase context:** `.planning/phases/155-fee-category-data-model/155-CONTEXT.md` - User decisions on data structure and migration approach
- **WordPress Codex:** Options API documentation - `get_option()`, `update_option()`, `delete_option()` - https://developer.wordpress.org/plugins/settings/options-api/

### Secondary (MEDIUM confidence)
- **Frontend code:** `/Users/joostdevalk/Code/rondo/rondo-club/src/utils/formatters.js` - Current FEE_CATEGORIES hardcoded object (lines 36-43) showing expected category structure
- **Frontend code:** `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Contributie/ContributieList.jsx` - Usage of FEE_CATEGORIES for display

### Tertiary (LOW confidence)
None - all findings verified from codebase and official WordPress documentation.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress Options API is well-documented core functionality, already used in codebase
- Architecture: HIGH - Data structure defined in CONTEXT.md, existing season pattern established in v12.0
- Pitfalls: HIGH - Derived from analyzing existing copy-forward logic and understanding CONTEXT.md constraints

**Research date:** 2026-02-08
**Valid until:** 2026-03-08 (30 days - stable WordPress core functionality, no fast-moving dependencies)
