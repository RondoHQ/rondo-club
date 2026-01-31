---
phase: quick-028
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-people.php
  - src/hooks/usePeople.js
  - src/pages/People/PeopleList.jsx
autonomous: true
---

<objective>
Add "Leeftijdsgroep" filter dropdown and custom sorting to the /people list page.

Purpose: Allow users to filter people by age group and sort the list by age group in a logical order.

Output:
- New filter dropdown in PeopleList.jsx for "Leeftijdsgroep"
- Custom sorting algorithm in backend that sorts "Onder 6" < "Onder 7" < ... < "Onder 19" < "Senioren"
- Ability to sort the people list by leeftijdsgroep column
</objective>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@AGENTS.md

**Data Analysis:**
Database contains 798 people with leeftijdsgroep values. Distinct values:
- "Onder 6", "Onder 7", "Onder 8", "Onder 9", "Onder 9 Meiden", "Onder 10", "Onder 11", "Onder 11 Meiden", "Onder 12", "Onder 13", "Onder 13 Meiden", "Onder 14", "Onder 15", "Onder 15 Meiden", "Onder 16", "Onder 17", "Onder 17 Meiden", "Onder 18", "Onder 19", "Senioren", "Senioren Vrouwen"

**Sorting Challenge:**
Alphabetical sorting would incorrectly order "Onder 10" after "Onder 1" instead of after "Onder 9".
Need custom sort: extract numeric part from "Onder X" values, treat "Senioren" as highest value.

**Existing Patterns:**
- Filter pattern: See `typeLid` filter in PeopleList.jsx (lines 1230-1246) for select dropdown pattern
- Backend filter: See `type_lid` in class-rest-people.php for meta value filtering
- Sorting: Custom field sorting exists via `custom_leeftijdsgroep` orderby parameter
- Custom sort logic: See class-rest-people.php lines 1206-1246 for custom field ORDER BY handling
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add backend filter and custom sort logic for leeftijdsgroep</name>
  <files>includes/class-rest-people.php</files>
  <action>
1. Add `leeftijdsgroep` parameter to REST route args (after `type_lid`, around line 313):
   ```php
   'leeftijdsgroep' => [
       'description'       => 'Filter by age group',
       'type'              => 'string',
       'sanitize_callback' => 'sanitize_text_field',
   ],
   ```

2. In `get_filtered_people()` method, extract the param (after line 1045):
   ```php
   $leeftijdsgroep = $request->get_param( 'leeftijdsgroep' );
   ```

3. Add filter logic (after type_lid filter, around line 1145):
   ```php
   // Leeftijdsgroep (age group) - select filter
   if ( ! empty( $leeftijdsgroep ) ) {
       $join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} lg ON p.ID = lg.post_id AND lg.meta_key = 'leeftijdsgroep'";
       $where_clauses[]  = "lg.meta_value = %s";
       $prepare_values[] = $leeftijdsgroep;
   }
   ```

4. Modify the custom field sorting logic in the switch/default case (around line 1206) to add special handling for leeftijdsgroep. When `$field_name === 'leeftijdsgroep'`, use custom ORDER BY:
   ```php
   if ( $field_name === 'leeftijdsgroep' ) {
       // Custom sort for leeftijdsgroep: Onder 6 < Onder 7 < ... < Onder 19 < Senioren
       // Extract numeric part from "Onder X" values, treat "Senioren" as 99
       $order_clause = "ORDER BY
           CASE
               WHEN cf.meta_value LIKE 'Onder %' THEN CAST(SUBSTRING(cf.meta_value, 7) AS UNSIGNED)
               WHEN cf.meta_value LIKE 'Senioren%' THEN 99
               ELSE 100
           END $order,
           CASE
               WHEN cf.meta_value LIKE '%Meiden%' OR cf.meta_value LIKE '%Vrouwen%' THEN 1
               ELSE 0
           END $order,
           fn.meta_value ASC";
   } else if ( $field_type === 'number' ) {
       // ... existing code
   }
   ```
   This sorts: primary by age group number (Onder 6=6, Onder 7=7, ..., Senioren=99), secondary by gender variant (regular before Meiden/Vrouwen), tertiary by first name.
  </action>
  <verify>
  - Deploy and test filter: curl with leeftijdsgroep=Senioren should return only Senioren
  - Test sort: custom_leeftijdsgroep orderby should sort Onder 6 before Onder 10 before Senioren
  </verify>
  <done>Backend filter and custom sort for leeftijdsgroep work correctly</done>
</task>

<task type="auto">
  <name>Task 2: Add frontend filter and URL state for leeftijdsgroep</name>
  <files>src/hooks/usePeople.js, src/pages/People/PeopleList.jsx</files>
  <action>
1. In usePeople.js, add `leeftijdsgroep` to the useFilteredPeople params object (around line 134):
   ```javascript
   leeftijdsgroep: filters.leeftijdsgroep || null,
   ```

2. In PeopleList.jsx:

   a. Add URL param parsing (after `typeLid` around line 657):
   ```javascript
   const leeftijdsgroep = searchParams.get('leeftijdsgroep') || '';
   ```

   b. Add setter function (after setTypeLid around line 718):
   ```javascript
   const setLeeftijdsgroep = useCallback((value) => {
       updateSearchParams({ leeftijdsgroep: value });
   }, [updateSearchParams]);
   ```

   c. Pass to useFilteredPeople (around line 778, add after typeLid):
   ```javascript
   leeftijdsgroep,
   ```

   d. Add to hasActiveFilters check (line 910):
   ```javascript
   || leeftijdsgroep
   ```

   e. Add to filter count badge (line 1113):
   ```javascript
   + (leeftijdsgroep ? 1 : 0)
   ```

   f. Add filter dropdown in filter panel (after Type Lid filter, around line 1246). Use the known values:
   ```jsx
   {/* Leeftijdsgroep Filter */}
   <div>
     <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
       Leeftijdsgroep
     </h3>
     <select
       value={leeftijdsgroep}
       onChange={(e) => setLeeftijdsgroep(e.target.value)}
       className="w-full text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-50 rounded-lg px-3 py-2 focus:ring-accent-500 focus:border-accent-500"
     >
       <option value="">Alle</option>
       <option value="Onder 6">Onder 6</option>
       <option value="Onder 7">Onder 7</option>
       <option value="Onder 8">Onder 8</option>
       <option value="Onder 9">Onder 9</option>
       <option value="Onder 9 Meiden">Onder 9 Meiden</option>
       <option value="Onder 10">Onder 10</option>
       <option value="Onder 11">Onder 11</option>
       <option value="Onder 11 Meiden">Onder 11 Meiden</option>
       <option value="Onder 12">Onder 12</option>
       <option value="Onder 13">Onder 13</option>
       <option value="Onder 13 Meiden">Onder 13 Meiden</option>
       <option value="Onder 14">Onder 14</option>
       <option value="Onder 15">Onder 15</option>
       <option value="Onder 15 Meiden">Onder 15 Meiden</option>
       <option value="Onder 16">Onder 16</option>
       <option value="Onder 17">Onder 17</option>
       <option value="Onder 17 Meiden">Onder 17 Meiden</option>
       <option value="Onder 18">Onder 18</option>
       <option value="Onder 19">Onder 19</option>
       <option value="Senioren">Senioren</option>
       <option value="Senioren Vrouwen">Senioren Vrouwen</option>
     </select>
   </div>
   ```

   g. Add filter chip (after typeLid chip, around line 1380):
   ```jsx
   {leeftijdsgroep && (
     <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
       Leeftijdsgroep: {leeftijdsgroep}
       <button
         onClick={() => setLeeftijdsgroep('')}
         className="hover:text-gray-600 dark:hover:text-gray-300"
       >
         <X className="w-3 h-3" />
       </button>
     </span>
   )}
   ```

   h. Add to clearSelection useEffect dependencies (line 976):
   ```javascript
   leeftijdsgroep,
   ```
  </action>
  <verify>
  - npm run build succeeds
  - Filter dropdown appears in filter panel
  - Selecting a leeftijdsgroep filters the list
  - Filter chip shows and can be cleared
  - URL parameter is set correctly
  </verify>
  <done>Filter dropdown and URL state work for leeftijdsgroep</done>
</task>

<task type="auto">
  <name>Task 3: Deploy, test, version bump, and commit</name>
  <files>style.css, package.json, CHANGELOG.md</files>
  <action>
1. Run npm run build
2. Deploy to production using bin/deploy.sh
3. Test on production:
   - Filter: Select "Senioren" in filter, verify only Senioren shown
   - Filter: Select "Onder 10" in filter, verify only Onder 10 shown
   - Sort: Click leeftijdsgroep column header, verify Onder 6 appears before Onder 10
   - Sort desc: Click again, verify Senioren appears first
4. Bump version to 8.3.3 in style.css and package.json
5. Add CHANGELOG entry:
   ```markdown
   ## [8.3.3] - 2026-01-31

   ### Added
   - Leeftijdsgroep filter dropdown on /people page
   - Custom sorting for leeftijdsgroep (Onder 6 < Onder 10 < Senioren)
   ```
6. Git add and commit:
   ```
   feat(quick-028): add leeftijdsgroep filter and custom sort

   - Add filter dropdown for age group selection
   - Implement custom ORDER BY for numeric sorting of "Onder X" values
   - Senioren sorts after all Onder groups
   ```
7. Git push
  </action>
  <verify>
  - Production shows filter and sorting working correctly
  - Version bumped to 8.3.3
  - Changelog updated
  - Changes committed and pushed
  </verify>
  <done>Leeftijdsgroep filter and sort deployed and working on production</done>
</task>

</tasks>

<verification>
- [ ] Filter dropdown appears in filter panel on /people
- [ ] Selecting a leeftijdsgroep filters the list correctly
- [ ] Sorting by leeftijdsgroep sorts numerically (Onder 6 < Onder 10 < Senioren)
- [ ] Filter chip appears and can be cleared
- [ ] URL state preserved on navigation
</verification>

<success_criteria>
- Users can filter /people by leeftijdsgroep
- Users can sort /people by leeftijdsgroep with correct numeric ordering
- Filter persists in URL for back navigation
</success_criteria>

<output>
After completion, create `.planning/quick/028-leeftijdsgroep-filter-and-sort/028-SUMMARY.md`
</output>
