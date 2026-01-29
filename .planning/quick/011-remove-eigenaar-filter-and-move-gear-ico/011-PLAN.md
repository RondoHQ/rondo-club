---
phase: quick
plan: 011
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/People/PeopleList.jsx
autonomous: true

must_haves:
  truths:
    - "Eigenaar filter is no longer visible in the filter dropdown"
    - "Gear icon appears at the far right of the header row"
    - "All other filters continue to work correctly"
  artifacts:
    - path: "src/pages/People/PeopleList.jsx"
      provides: "Updated PeopleList with removed owner filter and repositioned gear icon"
  key_links: []
---

<objective>
Remove the "Eigenaar" (owner) filter from the PeopleList filter dropdown and move the gear icon (column settings) to the far right of the header row.

Purpose: The ownership filter is no longer sensible for the current use case, and the gear icon should be positioned more intuitively at the end of the row.
Output: Updated PeopleList.jsx with cleaner filter dropdown and improved layout.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
</execution_context>

<context>
@src/pages/People/PeopleList.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Remove Eigenaar filter and move gear icon to far right</name>
  <files>src/pages/People/PeopleList.jsx</files>
  <action>
1. Remove the "Eigenaar" filter section from the filter dropdown (lines ~1008-1044):
   - Delete the entire `<div>` block containing the "Eigenaar" heading and radio buttons
   - This removes the "Alle leden", "Mijn leden", "Gedeeld met mij" options

2. Remove ownership from hasActiveFilters check (line ~774):
   - Change from: `selectedLabelIds.length > 0 || selectedBirthYear || lastModifiedFilter || ownershipFilter !== 'all'`
   - Change to: `selectedLabelIds.length > 0 || selectedBirthYear || lastModifiedFilter`

3. Remove ownership from filter count display (line ~925):
   - Change from: `{selectedLabelIds.length + (selectedBirthYear ? 1 : 0) + (lastModifiedFilter ? 1 : 0) + (ownershipFilter !== 'all' ? 1 : 0)}`
   - Change to: `{selectedLabelIds.length + (selectedBirthYear ? 1 : 0) + (lastModifiedFilter ? 1 : 0)}`

4. Remove ownership filter chip display (lines ~1113-1120):
   - Delete the conditional rendering of the ownership filter chip

5. Clean up ownership-related state and effects:
   - Remove `ownershipFilter` state (line ~590): `const [ownershipFilter, setOwnershipFilter] = useState('all');`
   - Remove `ownershipFilter` from useEffect dependencies (line ~637)
   - Remove `ownershipFilter` from clearFilters function (line ~788)
   - Remove `ownershipFilter` from people selection reset useEffect (line ~823)
   - Note: Keep `ownership: ownershipFilter` in useFilteredPeople but set to always 'all', or remove if backend handles missing param gracefully

6. Move the gear icon (column settings button) to the far right:
   - Restructure the header row to use `justify-between` properly
   - Group the left-side controls (sort, filter, filter chips) together
   - Put the "Lid toevoegen" button and gear icon on the right side
   - The gear icon should be after the "Lid toevoegen" button (far right)

   New structure:
   ```jsx
   <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
     <div className="flex flex-wrap items-center gap-2">
       {/* Sort Controls */}
       {/* Filter button */}
       {/* Active Filter Chips */}
     </div>
     <div className="flex items-center gap-2">
       <button onClick={() => setShowPersonModal(true)} className="btn-primary">
         {/* Add button */}
       </button>
       <button onClick={() => setShowColumnSettings(true)} className="btn-secondary" title="Kolommen aanpassen">
         <Settings className="w-4 h-4" />
       </button>
     </div>
   </div>
   ```

7. Clean up the useFilteredPeople call:
   - Either remove `ownership` param entirely if backend handles missing param, or keep as `ownership: 'all'`
  </action>
  <verify>
    - `npm run lint` passes without new errors
    - `npm run build` succeeds
    - Manual verification: Filter dropdown no longer shows "Eigenaar" section
    - Manual verification: Gear icon appears at far right after "Lid toevoegen" button
  </verify>
  <done>
    - Eigenaar filter completely removed from UI and state
    - Gear icon positioned at far right of header row
    - All remaining filters (labels, birth year, last modified) work correctly
  </done>
</task>

</tasks>

<verification>
1. Build succeeds: `npm run build`
2. No lint errors: `npm run lint`
3. Visual check on production after deploy
</verification>

<success_criteria>
- Filter dropdown shows only: Labels, Geboortejaar, Laatst gewijzigd
- Eigenaar filter is completely removed (UI, state, and filter chips)
- Gear icon is positioned at the far right of the header row
- Layout is clean and responsive
</success_criteria>

<output>
After completion, update `.planning/STATE.md` with quick task entry.
Deploy to production using `bin/deploy.sh`.
</output>
