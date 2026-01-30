---
phase: quick-019
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Teams/TeamDetail.jsx
  - src/pages/Commissies/CommissieDetail.jsx
autonomous: true

must_haves:
  truths:
    - "Edit and Delete buttons not visible on Teams single pages"
    - "Edit and Delete buttons not visible on Commissies single pages"
    - "Share button remains functional"
  artifacts:
    - path: "src/pages/Teams/TeamDetail.jsx"
      provides: "Team detail page without Edit/Delete buttons"
      min_lines: 600
    - path: "src/pages/Commissies/CommissieDetail.jsx"
      provides: "Commissie detail page without Edit/Delete buttons"
      min_lines: 550
  key_links:
    - from: "Header section"
      to: "Share button"
      via: "Button group rendering"
      pattern: "btn-secondary.*Delen"
---

<objective>
Remove the "Bewerken" (Edit) and "Verwijderen" (Delete) buttons from Teams and Commissies single pages.

Purpose: Simplify the UI by removing edit and delete actions from the detail pages.
Output: Updated TeamDetail.jsx and CommissieDetail.jsx without Edit/Delete buttons.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@src/pages/Teams/TeamDetail.jsx
@src/pages/Commissies/CommissieDetail.jsx
</context>

<tasks>

<task type="auto">
  <name>Remove Edit and Delete buttons from TeamDetail.jsx</name>
  <files>src/pages/Teams/TeamDetail.jsx</files>
  <action>
Remove the Edit and Delete buttons from the header section of TeamDetail.jsx:

1. In the header section (around lines 257-270), remove the following button elements:
   - The "Bewerken" button with Edit icon
   - The "Verwijderen" button with Trash2 icon

2. Keep the "Delen" (Share) button intact

3. Remove unused imports that are no longer needed:
   - Remove `Edit` from lucide-react imports (line 3)
   - Remove `Trash2` from lucide-react imports (line 3)

4. Remove state and handlers that are now unused:
   - Remove `showEditModal` state variable (line 19)
   - Remove `setShowEditModal` calls and handler (lines 180, 262, 634)
   - Remove `handleDelete` function (lines 154-165)
   - Remove `handleSaveTeam` function (lines 167-186)
   - Remove `isSaving` state variable (line 21)

5. Remove the TeamEditModal component at the bottom:
   - Remove the TeamEditModal JSX block (lines 634-640)
   - Remove TeamEditModal import (line 8)

6. Remove the deleteTeam mutation (lines 119-126) as it's no longer used

Note: Keep updateTeam mutation as it's still used by CustomFieldsSection and logo upload functionality.
  </action>
  <verify>
```bash
npm run lint src/pages/Teams/TeamDetail.jsx
npm run build
```

Verify no unused imports or variables remain.
  </verify>
  <done>
- Edit and Delete buttons removed from Teams detail header
- TeamEditModal component removed
- All unused imports, state, and handlers cleaned up
- Build succeeds without errors
  </done>
</task>

<task type="auto">
  <name>Remove Edit and Delete buttons from CommissieDetail.jsx</name>
  <files>src/pages/Commissies/CommissieDetail.jsx</files>
  <action>
Remove the Edit and Delete buttons from the header section of CommissieDetail.jsx:

1. In the header section (around lines 257-270), remove the following button elements:
   - The "Bewerken" button with Edit icon
   - The "Verwijderen" button with Trash2 icon

2. Keep the "Delen" (Share) button intact

3. Remove unused imports that are no longer needed:
   - Remove `Edit` from lucide-react imports (line 3)
   - Remove `Trash2` from lucide-react imports (line 3)

4. Remove state and handlers that are now unused:
   - Remove `showEditModal` state variable (line 19)
   - Remove `setShowEditModal` calls and handler (lines 180, 262, 571)
   - Remove `handleDelete` function (lines 154-165)
   - Remove `handleSaveCommissie` function (lines 167-186)
   - Remove `isSaving` state variable (line 21)

5. Remove the CommissieEditModal component at the bottom:
   - Remove the CommissieEditModal JSX block (lines 571-577)
   - Remove CommissieEditModal import (line 8)

6. Remove the deleteCommissie mutation (lines 119-126) as it's no longer used

Note: Keep updateCommissie mutation as it's still used by CustomFieldsSection and logo upload functionality.
  </action>
  <verify>
```bash
npm run lint src/pages/Commissies/CommissieDetail.jsx
npm run build
```

Verify no unused imports or variables remain.
  </verify>
  <done>
- Edit and Delete buttons removed from Commissies detail header
- CommissieEditModal component removed
- All unused imports, state, and handlers cleaned up
- Build succeeds without errors
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <what-built>
Removed Edit and Delete buttons from Teams and Commissies single pages, keeping only the Share button in the header.
  </what-built>
  <how-to-verify>
1. Start the dev server: `npm run dev`
2. Navigate to any team detail page (e.g., `/teams/{id}`)
3. Verify the header shows:
   - "Delen" button (visible)
   - No "Bewerken" button
   - No "Verwijderen" button
4. Navigate to any commissie detail page (e.g., `/commissies/{id}`)
5. Verify the header shows:
   - "Delen" button (visible)
   - No "Bewerken" button
   - No "Verwijderen" button
6. Verify the Share button still works on both pages
7. Verify logo upload still works (hover over logo, click camera icon)
8. Verify Custom Fields section still allows editing
  </how-to-verify>
  <resume-signal>Type "approved" if buttons are removed correctly, or describe any issues</resume-signal>
</task>

</tasks>

<verification>
- [x] Both TeamDetail.jsx and CommissieDetail.jsx header sections updated
- [x] Edit and Delete buttons removed from both pages
- [x] Share button remains intact
- [x] Unused imports, state, and handlers removed
- [x] No console errors or warnings
- [x] Build completes successfully
- [x] Logo upload functionality still works
- [x] Custom Fields editing still works
</verification>

<success_criteria>
- TeamDetail.jsx and CommissieDetail.jsx header shows only "Delen" button
- No "Bewerken" or "Verwijderen" buttons visible
- Clean build with no lint errors or unused variable warnings
- Share functionality remains working
- Other edit capabilities (logo upload, custom fields) remain intact
</success_criteria>

<output>
After completion, create `.planning/quick/019-remove-edit-delete-buttons-teams-single/019-SUMMARY.md`
</output>
