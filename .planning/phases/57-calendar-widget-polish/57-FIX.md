---
phase: 57-calendar-widget-polish
plan: 57-FIX
type: fix
wave: 1
depends_on: []
files_modified:
  - src/pages/Dashboard.jsx
  - functions.php
autonomous: true
---

<objective>
Fix 2 UAT issues from phase 57.

Source: 57-UAT.md
Diagnosed: yes
Priority: 0 blocker, 2 major, 0 minor, 0 cosmetic
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md

**Issues being fixed:**
@.planning/phases/57-calendar-widget-polish/57-UAT.md

**Original plan for reference:**
@.planning/phases/57-calendar-widget-polish/57-01-PLAN.md

Source files:
@src/pages/Dashboard.jsx
@functions.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix UAT-001 - Restructure Dashboard Layout to 3 Rows</name>
  <files>src/pages/Dashboard.jsx</files>
  <action>
**Root Cause:** Grid uses lg:grid-cols-4 forcing all cards into single row. Need to restructure into 3 separate rows with lg:grid-cols-3 each.

**Issue:** User wants 3-row layout, not 4-column layout:
- Row 1: Reminders | Todos | Awaiting
- Row 2: Today's Meetings | Recently contacted | Recently edited
- Row 3: Favorites

**Fix:**
1. Change row 1 back to `lg:grid-cols-3` (remove conditional 4-column logic)
2. Move Today's Meetings card OUT of row 1
3. Create new row 2 with `lg:grid-cols-3` containing:
   - Today's Meetings (when calendar connected, otherwise hide or show placeholder)
   - Recently Contacted
   - Recently Edited
4. Keep Favorites as row 3

The grid structure should be:
```jsx
{/* Row 1: Stats */}
<div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
  {/* Reminders */}
  {/* Todos */}
  {/* Awaiting */}
</div>

{/* Row 2: Activity */}
<div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
  {/* Today's Meetings (conditional on hasCalendarConnections) */}
  {/* Recently Contacted */}
  {/* Recently Edited */}
</div>

{/* Row 3: Favorites */}
<div className="mt-6">
  {/* Favorites */}
</div>
```

When no calendar is connected, row 2 should show 2 items (Recently Contacted, Recently Edited) which will naturally take up space.
  </action>
  <verify>npm run build succeeds, visually confirm 3-row layout on desktop</verify>
  <done>UAT-001 resolved - Dashboard shows 3-row layout (Stats, Activity, Favorites)</done>
</task>

<task type="auto">
  <name>Task 2: Fix UAT-002 - Dynamic Favicon Not Updating</name>
  <files>functions.php</files>
  <action>
**Root Cause:** PHP outputs static favicon link in functions.php before React can update it. Need to remove static favicon from PHP so React's updateFavicon() can manage it.

**Issue:** Favicon does not update when accent color changes in Settings → Appearance.

**Fix:**
1. Find the `prm_theme_add_favicon()` function in functions.php (around line 384-389)
2. Comment out or remove the static favicon output
3. React's useTheme.js already has the updateFavicon() function that will create the favicon link dynamically

The React app will create and manage the favicon link element, allowing it to update dynamically when the accent color changes.

**Alternative consideration:** If the static favicon is needed for initial page load before React hydrates, we could keep it but ensure React's updateFavicon() properly replaces the existing link element. However, the simpler fix is to let React manage it entirely.
  </action>
  <verify>Change accent color in Settings → Appearance, confirm browser tab favicon updates to match</verify>
  <done>UAT-002 resolved - Favicon dynamically updates when accent color changes</done>
</task>

</tasks>

<verification>
Before declaring plan complete:
- [ ] `npm run build` succeeds without errors
- [ ] `npm run lint` passes
- [ ] Dashboard shows 3-row layout (Stats row, Activity row, Favorites row)
- [ ] Favicon updates dynamically when accent color changes in Settings
</verification>

<success_criteria>
- All UAT issues from 57-UAT.md addressed
- Tests pass
- Ready for re-verification with /gsd:verify-work 57
</success_criteria>

<output>
After completion, create `.planning/phases/57-calendar-widget-polish/57-FIX-SUMMARY.md`
</output>
