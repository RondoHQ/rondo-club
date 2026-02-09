---
phase: 42-todo-ux-polish
plan: 01
subsystem: membership-fees
tags:
  - ux-improvement
  - settings
  - fee-categories
dependency_graph:
  requires: []
  provides:
    - manual-season-copy
  affects:
    - fee-category-settings-ui
tech_stack:
  added:
    - REST endpoint: POST /rondo/v1/membership-fees/copy-season
  patterns:
    - Explicit user control over data mutations
    - Conditional UI rendering based on data state
    - Optimistic updates with query invalidation
key_files:
  created: []
  modified:
    - includes/class-membership-fees.php
    - includes/class-rest-api.php
    - src/api/client.js
    - src/pages/Settings/FeeCategorySettings.jsx
decisions:
  - decision: Remove auto-copy on first read
    rationale: Auto-copy causes unexpected data mutations on read operations, violates principle of least surprise
    alternative: Manual copy button gives users explicit control
  - decision: Validate destination season is empty
    rationale: Prevents accidental overwrites of existing configurations
    alternative: Could allow overwrite with stronger confirmation, but safer to block entirely
  - decision: Copy both categories AND family discount together
    rationale: These settings are semantically related and should stay in sync between seasons
    alternative: Could have separate copy buttons, but creates risk of partial copies
metrics:
  duration_minutes: ~15
  tasks_completed: 2
  files_modified: 4
  commits: 2
completed_date: 2026-02-09
---

# Quick Task 42: Add Copy from Current Season Button to Next Season Tab

**One-liner:** Manual copy button for fee categories with auto-copy removal

## Implementation Summary

Replaced automatic season-to-season copy behavior with an explicit copy button that appears only when the next season is empty and the current season has categories to copy.

### What Changed

**Backend:**
1. Removed auto-copy logic from `get_categories_for_season()` (lines 755-768)
2. Removed auto-copy logic from `get_family_discount_config()` (lines 831-846)
3. Added `POST /rondo/v1/membership-fees/copy-season` endpoint with validation:
   - Validates seasons are different
   - Checks destination is empty before copying
   - Returns WP_Error if source is empty or destination already has data
4. Endpoint copies both categories and family discount configuration atomically

**Frontend:**
1. Added `Copy` icon to lucide-react imports
2. Added `copySeasonCategories()` method to prmApi client
3. Implemented copy mutation with:
   - Success message: "Categorieën gekopieerd van huidig seizoen"
   - Error handling with user-friendly Dutch messages
   - Query invalidation to refresh UI after copy
4. Added conditional copy button that only shows when:
   - User is on next season tab (`selectedSeason === 'next'`)
   - Next season has no categories (`Object.keys(categories).length === 0`)
   - Current season has categories to copy (`Object.keys(data?.current_season?.categories || {}).length > 0`)
5. Button includes:
   - Confirmation dialog before executing copy
   - Loading state with spinner during mutation
   - Blue theme to distinguish from save/delete actions
   - Explanatory text about what the button does

### Deviations from Plan

None - plan executed exactly as written.

### Technical Details

**Copy validation logic (backend):**
```php
// Prevents accidental overwrites
$existing_categories = $membership_fees->get_categories_for_season( $to_season );
if ( ! empty( $existing_categories ) ) {
    return new \WP_Error(
        'destination_not_empty',
        'Bestemmingsseizoen heeft al categorieën gedefinieerd',
        [ 'status' => 400 ]
    );
}
```

**Conditional button rendering (frontend):**
```jsx
{selectedSeason === 'next' &&
 Object.keys(categories).length === 0 &&
 Object.keys(data?.current_season?.categories || {}).length > 0 && (
  <div className="card p-4 bg-blue-50 dark:bg-blue-900/20">
    <button onClick={handleCopy}>...</button>
  </div>
)}
```

### User Experience Flow

1. Admin configures current season categories and family discount
2. Admin switches to "Volgend seizoen" tab
3. Blue card appears with copy button and explanation
4. Admin clicks "Kopieer categorieën van huidig seizoen"
5. Confirmation dialog: "Categorieën van seizoen 2025-2026 kopiëren naar 2026-2027?"
6. Admin confirms → loading state → success message
7. Categories and family discount appear on next season tab
8. Copy button disappears (destination no longer empty)
9. Admin can now edit next season categories independently

### Why This Pattern?

**Before (auto-copy):**
- First read of next season automatically copied from current
- Unexpected side effect on GET operation
- No user awareness of when copy occurred
- Risk of overwriting manual edits if season option got deleted

**After (manual copy):**
- User explicitly triggers copy via button click
- Confirmation dialog prevents accidents
- Clear feedback on success/failure
- Button visibility makes feature discoverable
- Destination validation prevents overwrites

This follows the principle: **reads should be safe, writes should be explicit.**

## Self-Check

Verifying implementation claims:

```bash
# Backend files modified
[ -f "includes/class-membership-fees.php" ] && echo "FOUND: class-membership-fees.php" || echo "MISSING"
[ -f "includes/class-rest-api.php" ] && echo "FOUND: class-rest-api.php" || echo "MISSING"

# Frontend files modified
[ -f "src/api/client.js" ] && echo "FOUND: client.js" || echo "MISSING"
[ -f "src/pages/Settings/FeeCategorySettings.jsx" ] && echo "FOUND: FeeCategorySettings.jsx" || echo "MISSING"

# Commits exist
git log --oneline --all | grep -q "173f516a" && echo "FOUND: 173f516a" || echo "MISSING"
git log --oneline --all | grep -q "742369d5" && echo "FOUND: 742369d5" || echo "MISSING"
```

**Result:**
```
FOUND: class-membership-fees.php
FOUND: class-rest-api.php
FOUND: client.js
FOUND: FeeCategorySettings.jsx
FOUND: 173f516a
FOUND: 742369d5
```

## Self-Check: PASSED

All files modified, both commits present in git history. Changes deployed to production at https://stadion.svawc.nl/.
