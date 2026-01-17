---
phase: 78-multi-calendar-selection
plan: FIX
type: fix
wave: 1
depends_on: []
autonomous: true
---

<objective>
Fix 1 UAT issue from phase 78.

Source: 78-UAT.md
Diagnosed: No (user-provided solution direction)
Priority: 0 blocker, 0 major, 1 minor, 0 cosmetic
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md

**Issues being fixed:**
@.planning/phases/78-multi-calendar-selection/78-UAT.md

**Original plans for reference:**
@.planning/phases/78-multi-calendar-selection/78-01-PLAN.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Two-column layout for EditConnectionModal</name>
  <files>
    src/pages/Settings/Settings.jsx
  </files>
  <action>
**Issue:** Modal is too high, doesn't fit on screen
**Expected:** EditConnectionModal fits on screen with good layout
**User suggestion:** Two columns, moving sync settings to right column

**Fix:** Restructure EditConnectionModal content into two-column grid layout:

1. Find the EditConnectionModal component in Settings.jsx

2. For Google Calendar connections, change the modal body to use a two-column grid:
   - Left column: Calendar selection (checkbox list)
   - Right column: Sync settings (sync frequency, sync range)

3. Implementation approach:
   ```jsx
   {/* Two-column layout for Google connections */}
   {isGoogleProvider && calendars.length > 0 && (
     <div className="grid grid-cols-2 gap-6">
       {/* Left column: Calendar selection */}
       <div>
         <label className="label mb-1">Calendars to sync</label>
         {/* Existing checkbox list */}
       </div>

       {/* Right column: Sync settings */}
       <div className="space-y-4">
         {/* Sync frequency dropdown */}
         {/* Sync range dropdown */}
       </div>
     </div>
   )}
   ```

4. Move sync settings (sync_frequency and sync_to_days selects) from below the calendar list to the right column

5. Keep non-Google connections (CalDAV) with single-column layout since they have fewer options

6. Ensure responsive behavior: On smaller screens, stack columns vertically if needed (use `md:grid-cols-2` instead of `grid-cols-2`)
  </action>
  <verify>
- `npm run lint` passes
- `npm run build` succeeds
- EditConnectionModal for Google connection shows two-column layout
- Calendar list on left, sync settings on right
- Modal height reduced, fits on screen
  </verify>
  <done>
- EditConnectionModal uses two-column grid layout
- Modal fits on screen without scrolling
- Calendar selection and sync settings side by side
  </done>
</task>

</tasks>

<verification>
Before declaring plan complete:
- [ ] Modal layout is two columns for Google connections
- [ ] Modal fits on screen (reduced height)
- [ ] Sync settings moved to right column
- [ ] Calendar checkbox list on left column
- [ ] Build and lint pass
</verification>

<success_criteria>
- UAT issue from 78-UAT.md addressed
- Modal fits on screen with improved layout
- Ready for re-verification with /gsd:verify-work 78
</success_criteria>

<output>
After completion, create `.planning/phases/78-multi-calendar-selection/78-FIX-SUMMARY.md`
</output>
