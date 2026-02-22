---
phase: 113-add-awc-team-column-and-filter-to-tuchtz
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/DisciplineCases/DisciplineCasesList.jsx
  - src/components/DisciplineCaseTable.jsx
autonomous: true
requirements: [QUICK-113]
must_haves:
  truths:
    - "AWC team name is visible as a column in the tuchtzaken table"
    - "User can hide/show the Team column via column settings"
    - "User can filter tuchtzaken by team name using a SELECT filter"
    - "Filter options are dynamically derived from the loaded discipline cases"
  artifacts:
    - path: "src/components/DisciplineCaseTable.jsx"
      provides: "Team column rendering with isColVisible support and sorting"
      contains: "isColVisible('team')"
    - path: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      provides: "Team filter column definition and filter state"
      contains: "teamFilter"
  key_links:
    - from: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      to: "src/components/DisciplineCaseTable.jsx"
      via: "isColVisible prop"
      pattern: "isColVisible.*team"
---

<objective>
Add an AWC team column and filter to the tuchtzaken (discipline cases) list page.

Purpose: Each discipline case already has a `team_name` ACF field containing the AWC team name. This field is currently only visible in the expanded row detail. Surfacing it as a column with a filter lets users quickly see and filter which team each case belongs to.

Output: Updated DisciplineCasesList.jsx and DisciplineCaseTable.jsx with team column and filter.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/DisciplineCases/DisciplineCasesList.jsx
@src/components/DisciplineCaseTable.jsx
@src/hooks/useDisciplineCases.js
@src/components/DataTable/index.js
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add team column to DisciplineCaseTable</name>
  <files>src/components/DisciplineCaseTable.jsx</files>
  <action>
Add a "Team" column to the DisciplineCaseTable component. The data comes from `acf.team_name` which is already available on each discipline case object.

1. Add a SortableHeader for "Team" with columnId `team_name`, gated by `isColVisible('team')`. Place it after the "Wedstrijd" column (before "Sanctie").

2. Add the corresponding `<td>` in the body row, also gated by `isColVisible('team')`:
   - Display `acf.team_name || '-'`
   - Use same text styling as the sanctie column: `text-sm text-gray-900 dark:text-gray-100`

3. Add a `team_name` case to the `sortedCases` sort switch statement:
   - Sort alphabetically using `localeCompare` (same pattern as `sanction`).

4. Update the expanded row `colSpan` calculation to include `(isColVisible('team') ? 1 : 0)`.

5. The team_name info currently shown in the expanded detail section (lines ~482-487, "Team" heading) should remain as-is for when the column is hidden.
  </action>
  <verify>Run `npm run build` to confirm no compilation errors. Visually confirm the column appears in the correct position.</verify>
  <done>Team column renders in the tuchtzaken table between Wedstrijd and Sanctie, respects column visibility, and is sortable.</done>
</task>

<task type="auto">
  <name>Task 2: Add team filter and column visibility to DisciplineCasesList</name>
  <files>src/pages/DisciplineCases/DisciplineCasesList.jsx</files>
  <action>
Wire up the team column visibility toggle and the team SELECT filter in the list page.

1. Add `teamFilter` state: `const [teamFilter, setTeamFilter] = useState('');`

2. Add a `team` entry to `colVisColumns` array:
   `{ id: 'team', label: 'Team', isVisible: isVisible('team') }`
   Place it as the first entry (before 'wedstrijd') since the column appears early in the table.

3. Derive dynamic filter options from the loaded `cases` data using `useMemo`:
   ```
   const teamFilterOptions = useMemo(() => {
     if (!cases) return [];
     const names = [...new Set(cases.map(dc => dc.acf?.team_name).filter(Boolean))].sort();
     return names.map(name => ({ value: name, label: name }));
   }, [cases]);
   ```

4. Add a `team` entry to `FILTER_COLUMNS`. Since the options are dynamic, move FILTER_COLUMNS inside the component (or create it with `useMemo`) so it can reference `teamFilterOptions`. The team filter should be a SELECT type with the dynamic options. Place it after the 'persoon' filter column. Use `filterType: FILTER_TYPES.SELECT` and set `filterOptions` to the derived list.

   Note: Since FILTER_COLUMNS is currently a static constant outside the component, convert it to a `useMemo` inside the component that depends on `teamFilterOptions`. Keep all existing column definitions identical, just add the team entry and make it reactive.

5. Add team filter logic to the `filteredCases` useMemo:
   ```
   if (teamFilter !== '') {
     if ((acf.team_name || '') !== teamFilter) return false;
   }
   ```
   Add after the `persoonFilter` block.

6. Update `setFilter` to handle 'team': `else if (colId === 'team') setTeamFilter(value || '');`

7. Update `hasActiveFilters` to include `teamFilter`.

8. Update `activeFilterCount` to include `teamFilter`.

9. Update `clearFilters` to include `setTeamFilter('')`.

10. Update the `filters` prop on `DataTableToolbar` to include `team: teamFilter`.

11. Update the empty state condition (line ~298) to include `teamFilter`.
  </action>
  <verify>Run `npm run build` to confirm no compilation errors. Run `npm run lint` to confirm no lint warnings.</verify>
  <done>Team filter appears in the filter dropdown as a SELECT with dynamically populated options from case data. Column visibility toggle works. Filtering correctly shows only cases matching the selected team.</done>
</task>

</tasks>

<verification>
- `npm run build` succeeds without errors
- `npm run lint` passes with 0 warnings
- Team column visible by default in tuchtzaken table
- Team column can be hidden via column settings cog
- Team filter dropdown shows only team names present in current season's data
- Selecting a team in the filter shows only matching discipline cases
- Filter chip appears when team filter is active
- Clearing filters resets team filter
- Sorting by team column works alphabetically
</verification>

<success_criteria>
- AWC team name column visible in tuchtzaken table between Wedstrijd and Sanctie
- Column visibility toggle for Team works correctly
- Team SELECT filter populated with team names from loaded cases
- Filter correctly narrows displayed cases
- Build and lint pass clean
</success_criteria>

<output>
After completion, create `.planning/quick/113-add-awc-team-column-and-filter-to-tuchtz/113-SUMMARY.md`
</output>
