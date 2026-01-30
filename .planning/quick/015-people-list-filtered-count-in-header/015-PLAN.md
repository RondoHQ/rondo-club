---
phase: quick
plan: 015
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/layout/Layout.jsx
  - src/pages/People/PeopleList.jsx
autonomous: true

must_haves:
  truths:
    - "When filters are applied on People list, filtered count shows in header"
    - "Count appears next to 'Leden' title in smaller/muted text"
    - "Count disappears when no filters are active"
  artifacts:
    - path: "src/components/layout/Layout.jsx"
      provides: "Header renders filtered count when available"
    - path: "src/pages/People/PeopleList.jsx"
      provides: "Communicates filtered count to header"
  key_links:
    - from: "src/pages/People/PeopleList.jsx"
      to: "src/components/layout/Layout.jsx"
      via: "URL search params (filteredCount param)"
      pattern: "searchParams.*filteredCount"
---

<objective>
Display the filtered result count in the page header when filters are applied on the People list.

Purpose: When a user filters the People list, they should immediately see in the header how many results match their filter criteria, providing quick feedback without needing to count rows.

Output: Header shows "Leden (42)" when filters are active, just "Leden" when no filters.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@src/components/layout/Layout.jsx
@src/pages/People/PeopleList.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add filtered count to URL params when filters active</name>
  <files>src/pages/People/PeopleList.jsx</files>
  <action>
Modify PeopleList.jsx to add a `filteredCount` URL search parameter when filters are active AND data is loaded:

1. Add a useEffect that watches `hasActiveFilters`, `totalPeople`, and `isLoading`:
   - When `hasActiveFilters` is true and data is loaded (`!isLoading`), set `filteredCount` param to `totalPeople`
   - When `hasActiveFilters` is false, remove the `filteredCount` param
   - Use `setSearchParams` with `replace: true` to avoid adding history entries

2. Place this effect after the existing filter-related state and data loading hooks.

Note: Using URL params allows the Header component to access the count without prop drilling or context, since it can read useSearchParams. This approach is lightweight and follows the existing pattern of URL-based state in this component.
  </action>
  <verify>
Apply a filter (e.g., birth year) and check URL contains `filteredCount=XX` param. Remove filter and verify param disappears.
  </verify>
  <done>URL contains filteredCount param when filters active, absent when no filters</done>
</task>

<task type="auto">
  <name>Task 2: Display filtered count in Header component</name>
  <files>src/components/layout/Layout.jsx</files>
  <action>
Modify the Header component in Layout.jsx to display the filtered count:

1. Import `useSearchParams` from 'react-router-dom' (already imported in the file for useLocation)

2. In the Header component, read the filteredCount from search params:
   ```jsx
   const [searchParams] = useSearchParams();
   const filteredCount = searchParams.get('filteredCount');
   ```

3. Update the h1 element (around line 475) to conditionally render the count:
   ```jsx
   <h1 className="ml-2 text-lg font-semibold lg:ml-0 dark:text-gray-100">
     {getPageTitle()}
     {filteredCount && (
       <span className="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
         ({filteredCount})
       </span>
     )}
   </h1>
   ```

The count should:
- Be smaller than the title (text-sm vs text-lg)
- Use muted colors (text-gray-500, dark:text-gray-400)
- Have normal font weight (font-normal vs font-semibold)
- Include parentheses for clarity
  </action>
  <verify>
Navigate to People list, apply a filter, verify header shows "Leden (XX)" with count in smaller/muted text. Remove filters and verify it shows just "Leden".
  </verify>
  <done>Header displays filtered count in muted text when filters are active</done>
</task>

<task type="auto">
  <name>Task 3: Build, deploy and verify</name>
  <files>dist/.vite/manifest.json</files>
  <action>
1. Run `npm run build` to create production assets
2. Run `bin/deploy.sh` to deploy to production
3. Verify the feature works on production by:
   - Going to the People list
   - Applying a filter (e.g., "Type lid: Junior")
   - Confirming the header shows "Leden (XX)" with the filtered count
   - Removing filters and confirming it shows just "Leden"
  </action>
  <verify>
Production site shows filtered count in header when People list has active filters.
  </verify>
  <done>Feature deployed and working on production</done>
</task>

</tasks>

<verification>
- [ ] Header shows "Leden" without count when no filters active
- [ ] Header shows "Leden (XX)" with count when filters active
- [ ] Count updates correctly when filters change
- [ ] Count styling is smaller and muted compared to title
- [ ] Works correctly with pagination (shows total filtered count, not page count)
- [ ] Dark mode styling is correct
</verification>

<success_criteria>
- Filtered result count visible in page header when People list has active filters
- Count disappears when all filters cleared
- Visual styling is subtle (smaller, muted) to not distract from title
</success_criteria>

<output>
After completion, create `.planning/quick/015-people-list-filtered-count-in-header/015-SUMMARY.md`
</output>
