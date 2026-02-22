---
phase: quick-115
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/DisciplineCases/DisciplineCasesList.jsx
autonomous: true
requirements: [QUICK-115]

must_haves:
  truths:
    - "User can select a team to exclude from the tuchtzaken list"
    - "When exclude team filter is active, all cases for that team are hidden"
    - "Exclude team filter works alongside the existing include team filter"
    - "Exclude team filter appears as a chip when active and can be cleared"
  artifacts:
    - path: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      provides: "Exclude team filter state, column definition, and filter logic"
  key_links:
    - from: "DisciplineCasesList excludeTeamFilter state"
      to: "filteredCases useMemo"
      via: "filter predicate excluding matching team_name"
      pattern: "excludeTeamFilter.*team_name"
---

<objective>
Add an "Exclude team" filter to the tuchtzaken (discipline cases) page, allowing users to hide all cases for one specific team from the report.

Purpose: Users want to exclude a specific team (e.g. a youth team with many minor infractions) from the overview to focus on other teams.
Output: Updated DisciplineCasesList.jsx with working exclude team filter.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/DisciplineCases/DisciplineCasesList.jsx
@src/components/DataTable/columnHelpers.js
@src/components/DataTable/FilterChips.jsx
@src/components/DataTable/FilterDropdown.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add exclude team filter to tuchtzaken page</name>
  <files>src/pages/DisciplineCases/DisciplineCasesList.jsx</files>
  <action>
Add an "Exclude team" (Uitsluiten team) filter to the discipline cases list page. This follows the exact same patterns as the existing team (include) filter:

1. **New state:** Add `const [excludeTeamFilter, setExcludeTeamFilter] = useState('');` alongside the existing filter states (line ~35).

2. **New filter column definition:** In the `filterColumns` useMemo, add a new `createColumn` entry AFTER the existing `team` filter:
   ```js
   createColumn({
     id: 'excludeTeam',
     header: 'Uitsluiten team',
     filterType: FILTER_TYPES.SELECT,
     filterOptions: teamFilterOptions,
   }),
   ```
   This reuses the same `teamFilterOptions` so the dropdown shows the same team list.

3. **Filter logic:** In the `filteredCases` useMemo, add this check after the existing `teamFilter` block (after line ~228):
   ```js
   if (excludeTeamFilter !== '') {
     if ((acf.team_name || '') === excludeTeamFilter) return false;
   }
   ```
   Note: the include filter uses `!==` (keep only matching), the exclude filter uses `===` (remove matching). These are independent filters that compose naturally.

4. **Wire into filters object:** Update the `filters` prop passed to `DataTableToolbar` to include `excludeTeam: excludeTeamFilter`.

5. **Wire into setFilter:** Add `else if (colId === 'excludeTeam') setExcludeTeamFilter(value || '');` in the `setFilter` callback.

6. **Wire into clearFilters:** Add `setExcludeTeamFilter('');` in the `clearFilters` function.

7. **Wire into hasActiveFilters/activeFilterCount:** Add `excludeTeamFilter` to both the boolean check and the count array (lines ~262-263).

8. **Wire into filteredCases dependencies:** Add `excludeTeamFilter` to the useMemo dependency array for `filteredCases`.

9. **Wire into empty state:** Add `excludeTeamFilter` to the condition on line ~369 that determines the empty state message.

10. **Add getFilterLabel:** Add `getFilterLabel` to the exclude team column definition so the filter chip shows the formatted team name instead of the raw value. Use a function that looks up the label from `teamFilterOptions`:
    ```js
    getFilterLabel: (val) => {
      const opt = teamFilterOptions.find(o => o.value === val);
      return opt ? opt.label : val;
    },
    ```
    Also add the same `getFilterLabel` to the existing team (include) filter column for consistency, since it currently lacks one and would show raw values in chips.
  </action>
  <verify>
    Run `npm run lint` from `/Users/joostdevalk/Code/rondo/rondo-club` — should pass with 0 warnings.
    Run `npm run build` from `/Users/joostdevalk/Code/rondo/rondo-club` — should compile successfully.
  </verify>
  <done>
    The tuchtzaken page has a new "Uitsluiten team" filter in the filter dropdown that shows all teams present in the current season's cases. Selecting a team excludes all its cases from the table. The filter works independently alongside the existing team include filter and all other filters. An active exclude filter shows as a labeled chip that can be cleared.
  </done>
</task>

</tasks>

<verification>
- `npm run lint` passes with 0 warnings
- `npm run build` compiles without errors
- Deploy to production and verify on https://rondo.svawc.nl
</verification>

<success_criteria>
- Exclude team dropdown appears in filter panel with all team options
- Selecting a team removes its cases from the table
- Filter chip shows "Uitsluiten team: [team name]" when active
- Clearing the filter restores all cases
- Works correctly alongside existing team include filter and all other filters
</success_criteria>

<output>
After completion, create `.planning/quick/115-add-exclude-team-filter-on-tuchtzaken-pa/115-SUMMARY.md`
</output>
