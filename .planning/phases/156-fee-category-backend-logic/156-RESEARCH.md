# Phase 156: Fee Category Backend Logic - Research

**Researched:** 2026-02-08
**Domain:** WordPress Options API, PHP fee calculation logic, Sportlink age class matching
**Confidence:** HIGH

## Summary

Phase 156 transforms the fee calculation engine from hardcoded constants to config-driven category lookups. The research reveals:

1. **Critical data model update needed**: Phase 155 stored `age_min`/`age_max` fields, but Phase 156 CONTEXT.md requires replacing them with an `age_classes` array that stores Sportlink age class strings (e.g., `["Onder 9", "Onder 10", "Onder 11"]`)
2. **Current implementation uses regex parsing**: `parse_age_group()` extracts numbers from Sportlink age strings like "Onder 14" or "JO14" and maps them to hardcoded age ranges
3. **Three hardcoded systems must be replaced**: (1) `parse_age_group()` age range matching, (2) `VALID_TYPES` constant, (3) `youth_categories` hardcoded arrays
4. **Forecast mode exists**: Current `/rondo/v1/fees` endpoint supports `?forecast=true` which uses next-season fees with 100% pro-rata
5. **Fee result shape already includes category**: `calculate_fee()` returns `['category' => 'senior', 'base_fee' => 255, ...]` so no API change needed

**Primary recommendation:** Update Phase 155's data model to use `age_classes` arrays instead of `age_min`/`age_max`, replace `parse_age_group()` with exact string matching against these arrays, derive `VALID_TYPES` and `youth_categories` dynamically from category config, and remove duplicated `category_order` arrays in PHP (defer frontend cleanup to Phase 159).

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | 6.0+ | Category config storage | Already used in Phase 155 for `rondo_membership_fees_{season}` |
| PHP native arrays | 8.0+ | Age class storage and matching | Simple exact string matching with `in_array()` |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None required | - | - | Pure PHP string matching and array operations |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Exact string match | Regex pattern matching | String match is simpler and matches CONTEXT.md decision |
| `age_classes` array | Keep `age_min`/`age_max` + calculation | CONTEXT.md explicitly requires age_classes for Sportlink integration |
| Derive from config | Keep hardcoded constants | Config-driven is the phase goal |

**Installation:**
None required - pure PHP logic transformation.

## Architecture Patterns

### Current Data Structure (Phase 155)
```php
// Option: rondo_membership_fees_2025-2026 (as of Phase 155)
[
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

### Updated Data Structure (Phase 156 requirement)
```php
// Option: rondo_membership_fees_2025-2026 (Phase 156 onwards)
[
  'pupil' => [
    'label' => 'Pupil',
    'amount' => 180,
    'age_classes' => ['Onder 8', 'Onder 9', 'Onder 10', 'Onder 11'],
    'is_youth' => true,
    'sort_order' => 2
  ],
  'senior' => [
    'label' => 'Senior',
    'amount' => 255,
    'age_classes' => ['Senioren', 'JO23'],
    'is_youth' => false,
    'sort_order' => 4
  ],
  'donateur' => [
    'label' => 'Donateur',
    'amount' => 55,
    'age_classes' => null, // Catch-all: matches any age class not in other categories
    'is_youth' => false,
    'sort_order' => 6
  ]
]
```

### Pattern 1: Age Class Matching (Replaces parse_age_group)

**What:** Match member's Sportlink `leeftijdsgroep` field against category `age_classes` arrays
**When to use:** Every fee calculation
**Current implementation (Phase 155 and before):**
```php
// Current: Regex extraction + hardcoded ranges (lines 177-229)
public function parse_age_group( string $leeftijdsgroep ): ?string {
    $normalized = preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', trim( $leeftijdsgroep ) );

    if ( strcasecmp( $normalized, 'Senioren' ) === 0 ) {
        return 'senior';
    }

    if ( preg_match( '/^Onder\s+(\d+)$/i', $normalized, $matches ) ) {
        $age = (int) $matches[1];

        // HARDCODED RANGES - to be removed
        if ( $age >= 6 && $age <= 7 ) {
            return 'mini';
        }
        if ( $age >= 8 && $age <= 11 ) {
            return 'pupil';
        }
        // ... etc
    }

    return null;
}
```

**New implementation (Phase 156):**
```php
/**
 * Find category by matching Sportlink age class
 *
 * @param string      $leeftijdsgroep Sportlink AgeClassDescription (e.g., "Onder 10", "Senioren")
 * @param string|null $season         Optional season key, defaults to current
 * @return string|null Category slug or null if no match
 */
public function get_category_by_age_class( string $leeftijdsgroep, ?string $season = null ): ?string {
    $season = $season ?? $this->get_season_key();
    $categories = $this->get_categories_for_season( $season );

    // Normalize: strip " Meiden" and " Vrouwen" suffixes (preserve current behavior)
    $normalized = preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', trim( $leeftijdsgroep ) );

    if ( empty( $normalized ) ) {
        return null;
    }

    // Sort categories by sort_order to ensure lowest sort_order wins if overlap
    uasort( $categories, function( $a, $b ) {
        return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
    });

    $catch_all_slug = null;

    foreach ( $categories as $slug => $category ) {
        // Skip if category is incomplete (loud failure per CONTEXT.md)
        if ( ! isset( $category['amount'] ) ) {
            error_log( "Category '{$slug}' missing amount for season {$season}" );
            return null;
        }

        $age_classes = $category['age_classes'] ?? null;

        // Null/empty age_classes = catch-all (matches anything not matched by other categories)
        if ( $age_classes === null || ( is_array( $age_classes ) && empty( $age_classes ) ) ) {
            if ( $catch_all_slug === null ) {
                $catch_all_slug = $slug; // Save for later, try specific matches first
            }
            continue;
        }

        // Exact string match (case-insensitive)
        foreach ( (array) $age_classes as $age_class ) {
            if ( strcasecmp( $normalized, trim( $age_class ) ) === 0 ) {
                return $slug;
            }
        }
    }

    // No specific match found - use catch-all if exists
    return $catch_all_slug;
}
```

### Pattern 2: Deriving VALID_TYPES from Config

**What:** Replace hardcoded `VALID_TYPES` constant with dynamic lookup
**Current (Phase 155 and before):**
```php
// Line 45
const VALID_TYPES = [ 'mini', 'pupil', 'junior', 'senior', 'recreant', 'donateur' ];

// Usage example (line 110)
if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
    continue;
}
```

**New (Phase 156):**
```php
/**
 * Get valid category slugs for a season
 *
 * @param string|null $season Optional season key, defaults to current
 * @return array<string> Array of category slugs
 */
public function get_valid_category_slugs( ?string $season = null ): array {
    $season = $season ?? $this->get_season_key();
    $categories = $this->get_categories_for_season( $season );

    // If no categories exist, return empty array (silent per CONTEXT.md)
    return array_keys( $categories );
}

// Usage update
$valid_types = $this->get_valid_category_slugs( $season );
if ( ! in_array( $type, $valid_types, true ) ) {
    continue;
}
```

### Pattern 3: Deriving youth_categories from Config

**What:** Replace hardcoded `youth_categories` arrays with `is_youth` flag filtering
**Current (scattered across codebase):**
```php
// Line 978 in class-membership-fees.php
$youth_categories = [ 'mini', 'pupil', 'junior' ];

// Line 367 in calculate_fee()
if ( in_array( $category, [ 'mini', 'pupil', 'junior' ], true ) ) {
    // Youth logic
}
```

**New (Phase 156):**
```php
/**
 * Get youth category slugs for a season
 *
 * @param string|null $season Optional season key, defaults to current
 * @return array<string> Array of youth category slugs
 */
public function get_youth_category_slugs( ?string $season = null ): array {
    $season = $season ?? $this->get_season_key();
    $categories = $this->get_categories_for_season( $season );

    return array_keys(
        array_filter(
            $categories,
            function( $cat ) {
                return ! empty( $cat['is_youth'] );
            }
        )
    );
}

// Usage update
$youth_categories = $this->get_youth_category_slugs( $season );
if ( in_array( $category, $youth_categories, true ) ) {
    // Youth logic
}
```

### Pattern 4: Category Sort Order Helper

**What:** Centralize category sort order to replace duplicated arrays
**Current (3 locations with duplicate arrays):**
```php
// includes/class-rest-api.php line 2829
$category_order = [ 'mini' => 1, 'pupil' => 2, 'junior' => 3, 'senior' => 4, 'recreant' => 5, 'donateur' => 6 ];

// includes/class-rest-google-sheets.php line 927
$category_order = [ 'mini' => 1, 'pupil' => 2, 'junior' => 3, 'senior' => 4, 'recreant' => 5, 'donateur' => 6 ];

// src/pages/Contributie/ContributieList.jsx (frontend - defer to Phase 159)
```

**New (Phase 156):**
```php
/**
 * Get category sort order map for a season
 *
 * @param string|null $season Optional season key, defaults to current
 * @return array<string, int> Map of category slug => sort_order
 */
public function get_category_sort_order( ?string $season = null ): array {
    $season = $season ?? $this->get_season_key();
    $categories = $this->get_categories_for_season( $season );

    $order = [];
    foreach ( $categories as $slug => $category ) {
        $order[ $slug ] = $category['sort_order'] ?? 999;
    }

    return $order;
}

// Usage in class-rest-api.php (replace line 2829)
$category_order = $fees->get_category_sort_order( $season );
usort(
    $results,
    function ( $a, $b ) use ( $category_order ) {
        $cat_cmp = ( $category_order[ $a['category'] ] ?? 99 ) <=> ( $category_order[ $b['category'] ] ?? 99 );
        // ... rest of sort logic
    }
);
```

### Pattern 5: Forecast Mode with Per-Season Categories

**What:** Forecast uses next-season categories, falls back to current season if not available
**Current implementation (lines 2734-2747 in class-rest-api.php):**
```php
public function get_fee_list( $request ) {
    $forecast = $request->get_param( 'forecast' );
    $fees     = new \Rondo\Fees\MembershipFees();

    // Determine season
    if ( $forecast ) {
        // Forecast always uses next season
        $season = $fees->get_next_season_key();
    } else {
        $season = $request->get_param( 'season' );
        if ( $season === null ) {
            $season = $fees->get_season_key();
        }
    }

    // ... fee calculation uses $season
}
```

**Phase 156 update:** No change to season selection logic. Category lookup will automatically use next-season categories when `$season` is next season. Fallback if next-season categories don't exist:

```php
// In get_category_by_age_class() - no explicit fallback needed
// get_categories_for_season() handles copy-forward from previous season per Phase 155
// If next-season doesn't exist yet, it copies from current season automatically

// Forecast behavior:
// 1. forecast=true → uses get_next_season_key() → e.g., "2026-2027"
// 2. get_categories_for_season("2026-2027") checks option
// 3. If "2026-2027" option doesn't exist, copies from "2025-2026" automatically (Phase 155 copy-forward)
// 4. Member's current leeftijdsgroep is matched against "2026-2027" categories
// 5. If match not found, returns null (uncategorized per CONTEXT.md)
```

### Anti-Patterns to Avoid

- **Inferring is_youth from age_classes:** Always read the explicit `is_youth` flag
- **Age calculation from birthdate:** Never calculate age - Sportlink `leeftijdsgroep` is the source of truth
- **Keeping VALID_TYPES constant:** Remove it completely, derive from config
- **Caching category lookups:** Categories can change per season, always read fresh
- **Partial data model migration:** Replace `age_min`/`age_max` completely, don't supplement

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Age range overlap detection | Custom overlap validator | Simple validation: check duplicates in flattened age_classes | CONTEXT.md says "both-inclusive: lowest sort_order wins" - overlap is allowed |
| Season fallback chain | Complex if/else tree | Rely on Phase 155's copy-forward in `get_categories_for_season()` | Already implemented and tested |
| Category sort comparator | Multiple usort implementations | Centralized `get_category_sort_order()` helper | Single source of truth |
| Age class normalization | New normalization logic | Keep existing regex strip for " Meiden" / " Vrouwen" | Current behavior is correct |

**Key insight:** Phase 155 already handles the hard parts (season copy-forward, option storage). Phase 156 is primarily replacing hardcoded matching logic with config lookups.

## Common Pitfalls

### Pitfall 1: Not Updating Phase 155 Data Model First

**What goes wrong:** Code tries to read `age_classes` array but Phase 155 stored `age_min`/`age_max` fields
**Why it happens:** Phase 155 was completed before CONTEXT.md clarified the age_classes requirement
**How to avoid:** Update Phase 155's helper methods to use `age_classes` field instead of `age_min`/`age_max`. This is a data model change, not just code.
**Warning signs:**
- `get_categories_for_season()` returns categories with `age_min`/`age_max` fields
- New matching code expects `age_classes` array
- Mismatch causes PHP warnings about undefined array keys

**Resolution:** Update Phase 155 documentation and helper code to use `age_classes` array. Existing manual data population (if any) will need updating.

### Pitfall 2: Forgetting Suffix Normalization

**What goes wrong:** "Onder 10 Meiden" doesn't match category's "Onder 10" age class
**Why it happens:** Sportlink adds " Meiden" or " Vrouwen" suffixes for girls' teams
**How to avoid:** Keep the existing regex normalization from `parse_age_group()` (line 179): `preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', trim( $leeftijdsgroep ) )`
**Warning signs:**
- Girls' team members show as uncategorized
- Same age boys match but girls don't
- Test with "Onder 10 Meiden" input

### Pitfall 3: Breaking Existing calculate_fee() Calls

**What goes wrong:** Other methods call `parse_age_group()` which gets replaced
**Why it happens:** Multiple methods use the old function name
**How to avoid:** Search for all usages of `parse_age_group()` before removing it. Update callers to use new `get_category_by_age_class()` method.
**Warning signs:**
- PHP fatal error: "Call to undefined method parse_age_group()"
- Fee calculation breaks for all members
- Test with actual fee calculation endpoint

**Found usages:**
- Line 363: `$category = $this->parse_age_group( $leeftijdsgroep );` in `calculate_fee()`
- Line 1329: `$parsed = ! empty( $leeftijdsgroep ) ? $this->parse_age_group( $leeftijdsgroep ) : null;` in `get_calculation_status()`

### Pitfall 4: Incomplete Category Data Handling

**What goes wrong:** Category missing `amount` field causes silent failures or PHP notices
**Why it happens:** CONTEXT.md says "fail loudly if incomplete data" but no validation on read
**How to avoid:** Check for required fields (`amount`, `label`) when using category data. Log error and return null to fail the calculation.
**Warning signs:**
- PHP notice: "Undefined array key 'amount'"
- Fees calculate as 0 instead of failing
- Silent failures hide data quality issues

### Pitfall 5: Forecast Using Current Season Categories

**What goes wrong:** Forecast shows current season fees instead of next season
**Why it happens:** Forgetting to pass `$season` parameter through the call chain
**How to avoid:** Every method that calls category helpers must accept optional `$season` parameter and pass it through:
  - `calculate_fee( $person_id, $season )` already has it (line 356)
  - `get_category_by_age_class()` needs it (new method)
  - All helper methods need it
**Warning signs:**
- Forecast shows same amounts as current season
- Test by changing next-season amounts manually
- Verify season parameter flows through entire call stack

## Code Examples

### Complete Age Class Matching Implementation

```php
/**
 * Find category by matching Sportlink age class
 *
 * Replaces parse_age_group() to use config-driven age class arrays.
 * Normalizes input by stripping " Meiden" and " Vrouwen" suffixes.
 *
 * @param string      $leeftijdsgroep Sportlink AgeClassDescription (e.g., "Onder 10", "Senioren")
 * @param string|null $season         Optional season key, defaults to current
 * @return string|null Category slug or null if no match
 */
public function get_category_by_age_class( string $leeftijdsgroep, ?string $season = null ): ?string {
    $season = $season ?? $this->get_season_key();
    $categories = $this->get_categories_for_season( $season );

    // Normalize: strip " Meiden" and " Vrouwen" suffixes
    $normalized = preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', trim( $leeftijdsgroep ) );

    if ( empty( $normalized ) ) {
        return null;
    }

    // Sort by sort_order (lowest wins if overlap per CONTEXT.md)
    uasort( $categories, function( $a, $b ) {
        return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
    });

    $catch_all_slug = null;

    foreach ( $categories as $slug => $category ) {
        // Validate required fields (fail loudly per CONTEXT.md)
        if ( ! isset( $category['amount'] ) ) {
            error_log( "Category '{$slug}' missing amount for season {$season}" );
            return null;
        }

        $age_classes = $category['age_classes'] ?? null;

        // Null/empty age_classes = catch-all
        if ( $age_classes === null || ( is_array( $age_classes ) && empty( $age_classes ) ) ) {
            if ( $catch_all_slug === null ) {
                $catch_all_slug = $slug;
            }
            continue;
        }

        // Exact string match (case-insensitive)
        foreach ( (array) $age_classes as $age_class ) {
            if ( strcasecmp( $normalized, trim( $age_class ) ) === 0 ) {
                return $slug;
            }
        }
    }

    return $catch_all_slug;
}
```

### Updating calculate_fee() to Use New Method

```php
// Line 356-374 - UPDATE this section
public function calculate_fee( int $person_id, ?string $season = null ): ?array {
    // Get leeftijdsgroep from person
    $leeftijdsgroep = get_field( 'leeftijdsgroep', $person_id );
    $category       = null;

    // Parse age group if available - OLD WAY
    // if ( ! empty( $leeftijdsgroep ) ) {
    //     $category = $this->parse_age_group( $leeftijdsgroep );
    // }

    // NEW WAY: Use config-driven matching
    if ( ! empty( $leeftijdsgroep ) ) {
        $category = $this->get_category_by_age_class( $leeftijdsgroep, $season );
    }

    // Youth categories: Return immediately (priority over everything)
    $youth_categories = $this->get_youth_category_slugs( $season );
    if ( in_array( $category, $youth_categories, true ) ) {
        return [
            'category'       => $category,
            'base_fee'       => $this->get_fee( $category, $season ),
            'leeftijdsgroep' => $leeftijdsgroep,
            'person_id'      => $person_id,
        ];
    }

    // ... rest of method unchanged
}
```

### Removing VALID_TYPES Usage

```php
// OLD: Line 103-125 in update_settings_for_season()
foreach ( $fees as $type => $amount ) {
    // Skip invalid types - OLD WAY
    // if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
    //     continue;
    // }

    // NEW WAY: Check against current season's category slugs
    $valid_types = $this->get_valid_category_slugs( $season );
    if ( ! in_array( $type, $valid_types, true ) ) {
        continue;
    }

    // Validate: must be numeric and non-negative
    if ( ! is_numeric( $amount ) || $amount < 0 ) {
        continue;
    }

    $current[ $type ] = (int) $amount;
}
```

### Removing Duplicated category_order Arrays

```php
// class-rest-api.php line 2829 - UPDATE
// OLD: Hardcoded array
// $category_order = [ 'mini' => 1, 'pupil' => 2, 'junior' => 3, 'senior' => 4, 'recreant' => 5, 'donateur' => 6 ];

// NEW: Dynamic from config
$fees = new \Rondo\Fees\MembershipFees();
$category_order = $fees->get_category_sort_order( $season );

usort(
    $results,
    function ( $a, $b ) use ( $category_order ) {
        $cat_cmp = ( $category_order[ $a['category'] ] ?? 99 ) <=> ( $category_order[ $b['category'] ] ?? 99 );
        if ( $cat_cmp !== 0 ) {
            return $cat_cmp;
        }
        return strcasecmp( $a['first_name'] . ' ' . $a['last_name'], $b['first_name'] . ' ' . $b['last_name'] );
    }
);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded age ranges in `parse_age_group()` | Age class arrays in category config | v21.0 (Phase 156) | Age ranges configurable per season |
| `VALID_TYPES` constant | Dynamic from `get_valid_category_slugs()` | v21.0 (Phase 156) | Categories fully dynamic |
| Hardcoded `youth_categories` arrays | Derived from `is_youth` flag | v21.0 (Phase 156) | Youth status configurable |
| Duplicated `category_order` arrays | Centralized `get_category_sort_order()` | v21.0 (Phase 156) | Single source of truth for sorting |
| Age range numeric matching | Sportlink age class string matching | v21.0 (Phase 156) | Direct integration with Sportlink data |

**Deprecated/outdated:**
- `MembershipFees::VALID_TYPES` constant: Removed in Phase 156
- `parse_age_group()` method: Replaced by `get_category_by_age_class()` in Phase 156
- Inline `youth_categories` arrays: Replaced by `get_youth_category_slugs()` helper
- Hardcoded `category_order` arrays: Replaced by `get_category_sort_order()` helper

## Open Questions

1. **Should Phase 155's existing data (if any) be migrated to age_classes format?**
   - What we know: Phase 155 was completed with `age_min`/`age_max` fields
   - What's unclear: Whether any production data was manually entered using Phase 155 format
   - Recommendation: Check production database for existing category configs. If found, document manual migration steps. If not found, simply update Phase 155 helpers to use `age_classes`.

2. **What Sportlink age class values exist in production?**
   - What we know: Examples are "Onder 9", "Onder 10", "Senioren", "JO23" (from code and planning docs)
   - What's unclear: Complete list of possible values, especially edge cases
   - Recommendation: Query production `leeftijdsgroep` field to get unique values, document in admin settings UI as reference

3. **Should catch-all category be required or optional?**
   - What we know: CONTEXT.md says "null/empty age_classes acts as catch-all"
   - What's unclear: Should system require exactly one catch-all, or allow zero?
   - Recommendation: Allow zero catch-all categories. Members without match show as "uncategorized" per CONTEXT.md. This gives admin flexibility.

4. **Should we keep the DEFAULTS constant during Phase 156?**
   - What we know: `DEFAULTS` constant still exists (line 31), used in `get_settings_for_season()` (line 71)
   - What's unclear: Whether to remove it in Phase 156 or later phase
   - Recommendation: Remove `DEFAULTS` constant in Phase 156 as part of hardcoded cleanup. It's not used when categories exist. Empty categories array is the new "default".

## Sources

### Primary (HIGH confidence)
- **Codebase inspection:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-membership-fees.php` - Complete current implementation
- **Phase 155 artifacts:** `.planning/phases/155-fee-category-data-model/*` - Data model foundation and decisions
- **Phase 156 context:** `.planning/phases/156-fee-category-backend-logic/156-CONTEXT.md` - Implementation decisions requiring age_classes arrays
- **Requirements:** `.planning/REQUIREMENTS.md` - LOGIC-01 through LOGIC-05 requirements
- **REST API:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` - Forecast mode implementation and category_order usage

### Secondary (MEDIUM confidence)
- **Frontend code:** `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Contributie/ContributieList.jsx` - Frontend category_order (deferred to Phase 159)
- **Google Sheets export:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-google-sheets.php` - Additional category_order duplication
- **Planning docs:** Quick fix 028 - Sportlink age class examples ("Onder 6", "Senioren", etc.)

### Tertiary (LOW confidence)
None - all findings verified from codebase and project documentation.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Pure PHP transformation, no new dependencies
- Architecture: HIGH - Clear patterns from existing code and CONTEXT.md decisions
- Pitfalls: HIGH - Identified concrete risks from codebase analysis and Phase 155 completion state

**Research date:** 2026-02-08
**Valid until:** 2026-03-08 (30 days - stable PHP/WordPress core functionality)

**Critical finding:** Phase 155's data model uses `age_min`/`age_max` but Phase 156 CONTEXT.md requires `age_classes` arrays. This is a breaking change to Phase 155's completed work that must be addressed before planning Phase 156.
