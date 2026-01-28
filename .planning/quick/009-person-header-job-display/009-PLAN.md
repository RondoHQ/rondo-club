---
phase: quick
plan: 009
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/People/PersonDetail.jsx
autonomous: true

must_haves:
  truths:
    - "Verenigingsbreed functions show only job title without 'bij Verenigingsbreed' link"
    - "Functions are grouped by commissie in header display"
    - "Kaderlid Algemeen is not shown when person has multiple functions"
  artifacts:
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "Updated currentPositions display logic"
      contains: "Verenigingsbreed"
  key_links:
    - from: "currentPositions render"
      to: "teamMap lookup"
      via: "conditional 'bij' display based on team name"
---

<objective>
Improve person header job/function display logic.

Purpose: Clean up how functions are displayed - hide redundant "bij Verenigingsbreed" link, group by commissie, and skip showing "Kaderlid Algemeen" when person has multiple functions.

Output: Updated PersonDetail.jsx with smarter header display logic.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/People/PersonDetail.jsx (lines 1580-1610 - currentPositions render in header)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Update person header job display logic</name>
  <files>src/pages/People/PersonDetail.jsx</files>
  <action>
Modify the currentPositions display logic in the header section (around lines 1584-1610) with these changes:

1. **Hide "bij Verenigingsbreed" link**: When the team name is "Verenigingsbreed", show only the job title without "bij [link]". Check `teamMap[job.team]?.name === 'Verenigingsbreed'` to detect this case.

2. **Filter out "Kaderlid Algemeen" when multiple functions**: Before mapping over currentPositions, filter out any job where `job.job_title === 'Kaderlid Algemeen'` IF currentPositions.length > 1. If "Kaderlid Algemeen" is the only function, still show it.

3. **Group functions by commissie**: Instead of showing a flat comma-separated list, group jobs by their commissie/team. Display as:
   - For jobs at same commissie: "Title1, Title2 bij [Commissie]"
   - For jobs at "Verenigingsbreed": just show the title(s)
   - Example: "Voorzitter, Penningmeester bij Financiele Commissie, Kaderlid"

Implementation approach:
- Create a useMemo that processes currentPositions:
  a. Filter out "Kaderlid Algemeen" if multiple positions exist
  b. Group remaining positions by team ID (use null key for Verenigingsbreed or no-team)
  c. Sort groups: Verenigingsbreed jobs first (no "bij" needed), then other teams
- Update the JSX to render grouped structure instead of flat map

Current structure to modify (lines ~1584-1610):
```jsx
{currentPositions.length > 0 && (
  <p className="text-base text-gray-600 dark:text-gray-300">
    {currentPositions.map((job, idx) => {
      const hasTitle = !!job.job_title;
      const hasTeam = job.team && teamMap[job.team];
      // ... renders "Title bij Team" for each
    })}
  </p>
)}
```

New structure should group and conditionally show "bij":
```jsx
{groupedPositions.length > 0 && (
  <p className="text-base text-gray-600 dark:text-gray-300">
    {groupedPositions.map((group, groupIdx) => (
      <span key={groupIdx}>
        {groupIdx > 0 && ', '}
        {group.titles.join(', ')}
        {group.showTeamLink && group.team && (
          <>
            <span className="text-gray-400 dark:text-gray-500"> bij </span>
            <Link ...>{group.teamName}</Link>
          </>
        )}
      </span>
    ))}
  </p>
)}
```
  </action>
  <verify>
    - Load a person detail page with a function at "Verenigingsbreed" - should NOT show "bij Verenigingsbreed"
    - Load a person with multiple functions including "Kaderlid Algemeen" - "Kaderlid Algemeen" should be hidden
    - Load a person with only "Kaderlid Algemeen" - should show it (no other functions to display)
    - Load a person with multiple functions at same commissie - should group: "Title1, Title2 bij Commissie"
    - Run `npm run lint` - no new errors
    - Run `npm run build` - builds successfully
  </verify>
  <done>
    Person header displays functions cleanly: no "bij Verenigingsbreed", groups by commissie, hides redundant "Kaderlid Algemeen" when multiple functions exist.
  </done>
</task>

</tasks>

<verification>
- Visual inspection of person detail pages with various function configurations
- No regressions in existing header display for normal cases
- Build and lint pass
</verification>

<success_criteria>
1. Functions at "Verenigingsbreed" show title only (no "bij Verenigingsbreed" link)
2. Functions are grouped by commissie (multiple titles at same commissie shown together)
3. "Kaderlid Algemeen" is not shown when person has other functions
4. "Kaderlid Algemeen" IS shown when it's the only function
5. Build succeeds, no lint errors
</success_criteria>

<output>
After completion, create `.planning/quick/009-person-header-job-display/009-SUMMARY.md`
</output>
