---
phase: 42-todo-ux-polish
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Settings/FeeCategorySettings.jsx
  - includes/class-rest-api.php
  - includes/class-membership-fees.php
autonomous: true

must_haves:
  truths:
    - "Next season tab shows a copy button when it has no categories"
    - "Copy button triggers copy action without page reload"
    - "After copy, button disappears and categories appear"
    - "Auto-copy on first load is removed"
  artifacts:
    - path: "includes/class-rest-api.php"
      provides: "POST endpoint for manual copy operation"
      exports: ["copy_season_categories"]
    - path: "includes/class-membership-fees.php"
      provides: "Auto-copy removed from get_categories_for_season"
      contains: "get_categories_for_season"
    - path: "src/pages/Settings/FeeCategorySettings.jsx"
      provides: "Copy button UI and mutation"
      min_lines: 800
  key_links:
    - from: "src/pages/Settings/FeeCategorySettings.jsx"
      to: "/rondo/v1/membership-fees/copy-season"
      via: "POST request on button click"
      pattern: "prmApi.*copy.*season"
    - from: "includes/class-rest-api.php"
      to: "MembershipFees::get_categories_for_season"
      via: "read source season data"
      pattern: "get_categories_for_season"
---

<objective>
Replace auto-copy behavior with manual copy button for fee category settings.

Purpose: Give users explicit control over when next season categories are initialized from current season, rather than auto-copying on first load.
Output: Manual copy button in next season tab, removal of auto-copy logic.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@src/pages/Settings/FeeCategorySettings.jsx
@includes/class-rest-api.php
@includes/class-membership-fees.php
</context>

<tasks>

<task type="auto">
  <name>Remove auto-copy and add manual copy endpoint</name>
  <files>
    includes/class-membership-fees.php
    includes/class-rest-api.php
  </files>
  <action>
**Backend changes:**

1. **Remove auto-copy from `get_categories_for_season()` in class-membership-fees.php:**
   - Lines 755-768 currently auto-copy from previous season if season doesn't exist
   - Replace with: return empty array `[]` if season option doesn't exist (after migration checks)
   - Preserve migration logic for existing seasons (lines 743-752)
   - Family discount auto-copy (lines 831-846) should also be removed — return defaults only

2. **Add new REST endpoint in class-rest-api.php:**
   - Register route: `POST /rondo/v1/membership-fees/copy-season`
   - Permission: `check_admin_permission`
   - Parameters:
     - `from_season` (required, string): Source season key (e.g., "2025-2026")
     - `to_season` (required, string): Destination season key (e.g., "2026-2027")
   - Callback method: `copy_season_categories()`
   - Logic:
     a. Validate from_season and to_season are different
     b. Check if to_season already has categories — if yes, return WP_Error: "Destination season already has categories"
     c. Call `$membership_fees->get_categories_for_season($from_season)` to get source data
     d. Call `$membership_fees->save_categories_for_season($source_categories, $to_season)` to copy categories
     e. Copy family discount config: `get_family_discount_config($from_season)` → `save_family_discount_config($config, $to_season)`
     f. Return updated settings for both seasons (same format as `get_membership_fee_settings()`)

**Why this approach:** Explicit copy endpoint gives user control. Removing auto-copy prevents unexpected data mutations on first read.
  </action>
  <verify>
```bash
# Check auto-copy removed
grep -n "previous_season" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-membership-fees.php

# Check new endpoint registered
grep -n "copy-season" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php
```
  </verify>
  <done>
- get_categories_for_season() returns empty array for non-existent seasons (no auto-copy)
- get_family_discount_config() returns defaults only (no auto-copy)
- POST /rondo/v1/membership-fees/copy-season endpoint exists with validation
- Endpoint copies both categories AND family discount config
  </done>
</task>

<task type="auto">
  <name>Add copy button to next season tab</name>
  <files>
    src/pages/Settings/FeeCategorySettings.jsx
  </files>
  <action>
**Frontend changes:**

1. **Add copy mutation:**
   - Create new mutation using `useMutation` (after `discountMutation`, around line 654)
   - mutationFn: `POST /rondo/v1/membership-fees/copy-season` with `{ from_season: current_season.key, to_season: next_season.key }`
   - onSuccess: invalidate `membership-fee-settings` query, show success message "Categorieën gekopieerd van huidig seizoen"
   - onError: show error message

2. **Add conditional copy button:**
   - Location: After season selector (line 828), before family discount section (line 830)
   - Show button ONLY when:
     - `selectedSeason === 'next'`
     - AND `Object.keys(categories).length === 0` (next season has no categories)
     - AND `Object.keys(data?.current_season?.categories || {}).length > 0` (current season has categories to copy)
   - Button appearance:
     - Blue background (bg-blue-600 hover:bg-blue-700)
     - Icon: Copy icon from lucide-react
     - Text: "Kopieer categorieën van huidig seizoen"
   - Button onClick: trigger copy mutation with confirmation: `confirm("Categorieën van seizoen {current} kopiëren naar {next}?")`
   - Disabled state during mutation with spinner

3. **Update empty state message:**
   - Line 859: Change "Nog geen categorieën gedefinieerd voor dit seizoen" to include hint about copy button when on next season tab

**Why this approach:** Button only appears when copy is needed and possible. User gets explicit confirmation before copying. Success/error feedback matches existing pattern.
  </action>
  <verify>
```bash
# Check copy mutation exists
grep -A5 "useMutation.*copy" /Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/FeeCategorySettings.jsx

# Check button conditional rendering
grep -B2 -A2 "Kopieer categorieën" /Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/FeeCategorySettings.jsx

# Build frontend
cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build
```
  </verify>
  <done>
- Copy button appears on next season tab when next season is empty and current season has categories
- Button triggers confirmation dialog before copying
- After successful copy, categories and discount appear on next season tab
- Button disappears after copy completes
- Build succeeds without errors
  </done>
</task>

</tasks>

<verification>
**Manual verification steps:**

1. Open fee category settings in browser
2. Verify current season has categories configured
3. Switch to next season tab
4. Verify "Kopieer categorieën van huidig seizoen" button appears
5. Click button, confirm dialog
6. Verify categories and family discount appear on next season tab
7. Verify button no longer appears after copy
8. Refresh page and verify categories persist on next season

**Edge cases:**
- Next season already has categories → button doesn't appear
- Current season empty → button doesn't appear
- Network error → error message displays
</verification>

<success_criteria>
- Next season tab shows copy button only when appropriate (empty next season, populated current season)
- Copy button triggers POST to /rondo/v1/membership-fees/copy-season
- Categories AND family discount are copied together
- Auto-copy logic removed from get_categories_for_season()
- Auto-copy logic removed from get_family_discount_config()
- Button disappears after successful copy
- User gets clear success/error feedback
</success_criteria>

<output>
After completion, create `.planning/quick/42-add-copy-from-current-season-button-to-n/42-01-SUMMARY.md`
</output>
