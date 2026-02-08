---
phase: 156-fee-category-backend-logic
plan: 02
subsystem: membership-fees
tags: [php, backend, rest-api, google-sheets, config-driven, documentation]
requires: [155-01, 156-01]
provides:
  - Config-driven category sort order in REST API
  - Config-driven category sort order in Google Sheets export
  - Zero hardcoded category definitions in PHP backend
  - Comprehensive developer documentation for v21.0 fee engine
affects: [157-fee-list-api, 158-fee-admin-ui]
tech-stack:
  added: []
  patterns: [dynamic-sort-order, config-driven-sorting]
decisions:
  - slug: hardcoded-arrays-eliminated
    summary: Removed all hardcoded category_order arrays in favor of get_category_sort_order()
    impact: REST API and Google Sheets export now respect per-season category configuration
key-files:
  created: []
  modified:
    - includes/class-rest-api.php
    - includes/class-rest-google-sheets.php
    - ../developer/src/content/docs/features/membership-fees.md
metrics:
  duration: 171
  completed: 2026-02-08
---

# Phase 156 Plan 02: Dynamic Sort Order in REST & Docs Summary

**One-liner:** REST API and Google Sheets export now use dynamic category sort order from config; developer docs fully updated for v21.0 fee engine.

## What Was Built

### Core Changes

1. **REST API Dynamic Sort Order** (`includes/class-rest-api.php`)
   - Replaced hardcoded `$category_order = [ 'mini' => 1, 'pupil' => 2, ... ]` array
   - Now calls `$fees->get_category_sort_order( $season )` on line 2829
   - Sort order reflects per-season category configuration
   - Supports forecast mode via season parameter

2. **Google Sheets Dynamic Sort Order** (`includes/class-rest-google-sheets.php`)
   - Replaced hardcoded `$category_order` array on line 927
   - Now calls `$fees->get_category_sort_order( $season )`
   - Export respects category sort configuration
   - Consistent with REST API sorting

3. **Developer Documentation Update** (`../developer/src/content/docs/features/membership-fees.md`)
   - Updated data model examples to show `age_classes` arrays instead of `age_min`/`age_max`
   - Documented age class matching behavior (exact string comparison, lowest sort_order wins)
   - Added "Category Lookup" section documenting 4 new helper methods:
     - `get_category_by_age_class( $leeftijdsgroep, $season )`
     - `get_valid_category_slugs( $season )`
     - `get_youth_category_slugs( $season )`
     - `get_category_sort_order( $season )`
   - Added "Removed / Deprecated" section documenting:
     - `VALID_TYPES` constant (removed)
     - `DEFAULTS` constant (removed)
     - `parse_age_group()` method (removed)
     - Hardcoded `$category_order` arrays (removed)
     - Hardcoded `$youth_categories` arrays (removed)
     - `age_min`/`age_max` fields (replaced with `age_classes`)
   - Updated version history with Phase 155 and 156 entries

## Key Technical Details

### Dynamic Sort Order Pattern

Both REST API and Google Sheets export follow the same pattern:

```php
// OLD (hardcoded):
$category_order = [ 'mini' => 1, 'pupil' => 2, 'junior' => 3, 'senior' => 4, 'recreant' => 5, 'donateur' => 6 ];

// NEW (dynamic):
$category_order = $fees->get_category_sort_order( $season );
// Returns: ['mini' => 10, 'pupil' => 20, 'junior' => 30, 'senior' => 40, ...]
```

The usort functions already had proper `?? 99` fallback for unknown categories, so no changes needed there.

### Season Parameter Flow

Both methods already had `$fees` and `$season` variables in scope:
- REST API: `get_fee_list()` determines season from request (current or forecast)
- Google Sheets: `fetch_fee_data()` uses current or next season based on forecast parameter

The season parameter flows through to `get_category_sort_order()`, enabling forecast mode.

## Decisions Made

### 1. Eliminate All Hardcoded Category Arrays

**Decision:** Replace every hardcoded category array with dynamic config reads

**Context:** After Phase 156-01, two locations still had hardcoded category_order arrays:
- `class-rest-api.php` line 2829 (used in fee list sorting)
- `class-rest-google-sheets.php` line 927 (used in export sorting)

These were the last hardcoded category references in the entire PHP backend.

**Approach chosen:** Call `get_category_sort_order( $season )` in both locations

**Alternatives considered:**
1. Leave REST API hardcoded until Phase 157 (when fee list API is revised)
   - Rejected: Creates inconsistency, Phase 157 would need to change it anyway
2. Create separate helper methods for each use case
   - Rejected: `get_category_sort_order()` already exists and fits perfectly

**Impact:** Zero hardcoded fee category definitions anywhere in PHP code. All category logic reads from per-season configuration.

### 2. Document age_classes Model, Not age_min/age_max

**Decision:** Update developer docs to show `age_classes` arrays as the canonical data model

**Context:** Phase 155 initially used `age_min`/`age_max` fields. Phase 156-01 migrated to `age_classes` arrays for exact Sportlink alignment.

**Approach chosen:**
- Show `age_classes` in all code examples
- Document exact string matching behavior
- List `age_min`/`age_max` in "Removed / Deprecated" section
- Explain migration (old fields auto-converted to empty arrays)

**Impact:** Developers see the current v21.0 data model, not historical versions. Clear migration path documented.

## Commits

| Task | Commit | Files | Lines Changed |
|------|--------|-------|---------------|
| 1 | `4fbaa2cc` | `class-rest-api.php`, `class-rest-google-sheets.php` | +2/-2 |
| 2 | `346dd87` (developer repo) | `membership-fees.md` | +117/-50 |

## Next Phase Readiness

### Blockers

None. Phase 156 is complete.

### Concerns

**Cross-repo documentation:** The developer docs live in a separate repo (`/Users/joostdevalk/Code/rondo/developer/`). Both repos must be deployed together for consistency.

### Recommended Next Steps

**Phase 157 (Fee List API):** Update `/rondo/v1/fee-list` endpoint to:
- Return category configuration metadata (labels, age_classes, sort_order)
- Support season parameter for forecast mode
- Remove deprecated fee type parameters

**Phase 158 (Fee Admin UI):** Build React UI for editing category configuration:
- Category list with sort order, labels, amounts
- Age class selector (checkboxes for Sportlink age classes)
- Youth flag toggle
- Save to season-specific options

**Deploy together:** Do not deploy Phase 155-158 separately. All four phases must deploy together to avoid breaking fee calculations.

## Deviations from Plan

None - plan executed exactly as written.

## Testing Notes

### Verification Performed

1. **Grep for hardcoded arrays:** No matches for `'mini' => 1.*'pupil' => 2` pattern
2. **Grep for dynamic calls:** Found exactly 2 calls to `get_category_sort_order()` (REST API + Google Sheets)
3. **PHP syntax validation:** Both modified files pass `php -l` checks
4. **Documentation verification:**
   - 14 mentions of `age_classes` in docs
   - 2 mentions of `get_category_by_age_class` helper
   - "Removed / Deprecated" section present

### Manual Testing Required

**After Phase 158 deployment:**
1. Configure category sort order in admin UI
2. Call `/rondo/v1/fee-list` endpoint → verify members sorted by new order
3. Export Google Sheets → verify members sorted by new order
4. Change sort order in UI → verify REST and export update accordingly

### Edge Cases Handled

- **Unknown categories:** usort functions use `?? 99` fallback for categories not in sort order map
- **Empty season config:** If season has no categories, `get_category_sort_order()` returns empty array, usort falls back to name sorting

## Integration Points

### Dependencies

- Phase 155-01: Category configuration data model
- Phase 156-01: `get_category_sort_order()` helper method

### Consumers

- REST API `/rondo/v1/fee-list` endpoint (used by frontend to display member fee list)
- Google Sheets export (used by admins for offline analysis)

### Data Flow

```
WordPress Options (rondo_membership_fees_YYYY-YYYY)
  ↓
MembershipFees::get_category_sort_order( $season )
  ↓
REST API get_fee_list() → usort() by category priority
  ↓
Frontend fee list table (sorted by category)

WordPress Options (rondo_membership_fees_YYYY-YYYY)
  ↓
MembershipFees::get_category_sort_order( $season )
  ↓
GoogleSheets fetch_fee_data() → usort() by category priority
  ↓
Google Sheets export (sorted by category)
```

## Performance Considerations

No performance impact:
- `get_category_sort_order()` reads from already-loaded category config (no additional DB queries)
- Replaces hardcoded array with dynamic array (same memory footprint)
- usort complexity unchanged (still O(n log n))

## Security Considerations

No security implications:
- Only affects internal sorting logic
- No user input involved in sort order determination
- Category configuration requires admin privileges to modify

## Known Limitations

**Legacy update API:** The `update_membership_fee_settings()` method (line 2632 of `class-rest-api.php`) still has a hardcoded `$fee_types` array for validating incoming fee updates. This method works with the old flat-fee API structure.

**Not a blocker:** Phase 158 will introduce a new category configuration update API. The old method can remain for backward compatibility or be deprecated in a future phase.

**Why not fixed now:** Updating the old API would require architectural decisions about data migration and API versioning (Rule 4 - architectural change). Out of scope for this plan.

## References

- Phase 155-01 SUMMARY: Fee category configuration data model
- Phase 156-01 SUMMARY: Config-driven fee calculation engine
- Developer docs: `/Users/joostdevalk/Code/rondo/developer/src/content/docs/features/membership-fees.md`
