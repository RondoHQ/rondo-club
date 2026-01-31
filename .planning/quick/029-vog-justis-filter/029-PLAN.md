---
phase: quick-029
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-people.php
  - src/pages/VOG/VOGList.jsx
  - src/hooks/usePeople.js
autonomous: true

must_haves:
  truths:
    - "User can filter VOG list by Justis status"
    - "User sees count of 'Niet aangevraagd' and 'Aangevraagd' in filter dropdown"
    - "User can clear the Justis filter"
  artifacts:
    - path: "includes/class-rest-people.php"
      provides: "vog_justis_status filter parameter"
      contains: "vog_justis_status"
    - path: "src/pages/VOG/VOGList.jsx"
      provides: "Justis filter UI in dropdown"
      contains: "justisStatusFilter"
    - path: "src/hooks/usePeople.js"
      provides: "vogJustisStatus parameter mapping"
      contains: "vog_justis_status"
  key_links:
    - from: "src/pages/VOG/VOGList.jsx"
      to: "src/hooks/usePeople.js"
      via: "vogJustisStatus prop"
      pattern: "vogJustisStatus"
    - from: "src/hooks/usePeople.js"
      to: "includes/class-rest-people.php"
      via: "REST API parameter"
      pattern: "vog_justis_status"
---

<objective>
Add a Justis status filter to the VOG page that filters by whether the `vog_justis_submitted_date` meta field is set or not.

Purpose: Allow users to quickly see which volunteers have had their VOG submitted to Justis vs those who haven't yet.
Output: Working filter dropdown with "Niet aangevraagd" (no date) and "Aangevraagd" (date set) options.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

Reference patterns:
- The `vog_email_status` filter in class-rest-people.php (lines 337-344, 1193-1201)
- The email status filter UI in VOGList.jsx (lines 242, 295-300, 710-777)
- The usePeople.js parameter mapping (line 133)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add backend vog_justis_status filter parameter</name>
  <files>includes/class-rest-people.php</files>
  <action>
Add `vog_justis_status` parameter to the `/people/filtered` endpoint registration (around line 337, after `vog_email_status`):

```php
'vog_justis_status' => [
    'description'       => 'Filter by VOG Justis status (submitted, not_submitted, empty=all)',
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => function ( $value ) {
        return in_array( $value, [ '', 'submitted', 'not_submitted' ], true );
    },
],
```

In `get_filtered_people()` method:
1. Extract the parameter (around line 1050, after `$vog_email_status`):
   ```php
   $vog_justis_status = $request->get_param( 'vog_justis_status' );
   ```

2. Add filter logic (around line 1201, after the vog_email_status filter):
   ```php
   // VOG Justis status filter (submitted/not_submitted based on vog_justis_submitted_date meta field)
   if ( $vog_justis_status !== null && $vog_justis_status !== '' ) {
       $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} vjs ON p.ID = vjs.post_id AND vjs.meta_key = 'vog_justis_submitted_date'";

       if ( $vog_justis_status === 'submitted' ) {
           $where_clauses[] = "(vjs.meta_value IS NOT NULL AND vjs.meta_value != '')";
       } elseif ( $vog_justis_status === 'not_submitted' ) {
           $where_clauses[] = "(vjs.meta_value IS NULL OR vjs.meta_value = '')";
       }
   }
   ```
  </action>
  <verify>Run `npm run lint` - should pass. The PHP syntax should be valid.</verify>
  <done>Backend accepts vog_justis_status parameter and filters people accordingly.</done>
</task>

<task type="auto">
  <name>Task 2: Add usePeople.js parameter mapping</name>
  <files>src/hooks/usePeople.js</files>
  <action>
In `useFilteredPeople()` function, add the parameter mapping for vogJustisStatus.

1. Add to JSDoc comment (around line 110, after vogEmailStatus):
   ```javascript
    * @param {string} filters.vogJustisStatus - 'submitted' or 'not_submitted' to filter by Justis status
   ```

2. Add to params object (around line 133, after `vog_email_status`):
   ```javascript
   vog_justis_status: filters.vogJustisStatus || null,
   ```
  </action>
  <verify>Run `npm run lint` - should pass with no warnings.</verify>
  <done>usePeople.js hook passes vogJustisStatus to backend API.</done>
</task>

<task type="auto">
  <name>Task 3: Add Justis filter UI to VOGList</name>
  <files>src/pages/VOG/VOGList.jsx</files>
  <action>
1. Add filter state (around line 243, after emailStatusFilter):
   ```javascript
   const [justisStatusFilter, setJustisStatusFilter] = useState('');
   ```

2. Pass to useFilteredPeople (around line 275, after vogEmailStatus):
   ```javascript
   vogJustisStatus: justisStatusFilter,
   ```

3. Calculate justisCounts (around line 302, after vogTypeCounts):
   ```javascript
   const justisCounts = useMemo(() => {
     const allPeople = allData?.people || [];
     const submitted = allPeople.filter(p => p.acf?.['vog_justis_submitted_date']).length;
     const notSubmitted = allPeople.length - submitted;
     return { total: allPeople.length, submitted, notSubmitted };
   }, [allData?.people]);
   ```

4. Update the filter badge count (around line 628, in the Filter button):
   Change from:
   ```javascript
   {(emailStatusFilter || vogTypeFilter) && (
     <span className="ml-2 ...">
       {(emailStatusFilter ? 1 : 0) + (vogTypeFilter ? 1 : 0)}
     </span>
   )}
   ```
   To:
   ```javascript
   {(emailStatusFilter || vogTypeFilter || justisStatusFilter) && (
     <span className="ml-2 ...">
       {(emailStatusFilter ? 1 : 0) + (vogTypeFilter ? 1 : 0) + (justisStatusFilter ? 1 : 0)}
     </span>
   )}
   ```

5. Add Justis Status filter section in the dropdown (around line 777, after the Email status section, before the Clear Filters button):
   ```jsx
   {/* Justis Status Filter */}
   <div>
     <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
       Justis status
     </h3>
     <div className="space-y-1">
       <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
         <input
           type="checkbox"
           checked={justisStatusFilter === ''}
           onChange={() => setJustisStatusFilter('')}
           className="sr-only"
         />
         <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
           justisStatusFilter === ''
             ? 'bg-accent-600 border-accent-600'
             : 'border-gray-300 dark:border-gray-500'
         }`}>
           {justisStatusFilter === '' && (
             <Check className="w-3 h-3 text-white" />
           )}
         </div>
         <span className="text-sm text-gray-700 dark:text-gray-200">
           Alle ({justisCounts.total})
         </span>
       </label>
       <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
         <input
           type="checkbox"
           checked={justisStatusFilter === 'not_submitted'}
           onChange={() => setJustisStatusFilter('not_submitted')}
           className="sr-only"
         />
         <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
           justisStatusFilter === 'not_submitted'
             ? 'bg-accent-600 border-accent-600'
             : 'border-gray-300 dark:border-gray-500'
         }`}>
           {justisStatusFilter === 'not_submitted' && (
             <Check className="w-3 h-3 text-white" />
           )}
         </div>
         <span className="text-sm text-gray-700 dark:text-gray-200">
           Niet aangevraagd ({justisCounts.notSubmitted})
         </span>
       </label>
       <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
         <input
           type="checkbox"
           checked={justisStatusFilter === 'submitted'}
           onChange={() => setJustisStatusFilter('submitted')}
           className="sr-only"
         />
         <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
           justisStatusFilter === 'submitted'
             ? 'bg-accent-600 border-accent-600'
             : 'border-gray-300 dark:border-gray-500'
         }`}>
           {justisStatusFilter === 'submitted' && (
             <Check className="w-3 h-3 text-white" />
           )}
         </div>
         <span className="text-sm text-gray-700 dark:text-gray-200">
           Aangevraagd ({justisCounts.submitted})
         </span>
       </label>
     </div>
   </div>
   ```

6. Update "Clear Filters" button condition (around line 780):
   Change from:
   ```javascript
   {(emailStatusFilter || vogTypeFilter) && (
   ```
   To:
   ```javascript
   {(emailStatusFilter || vogTypeFilter || justisStatusFilter) && (
   ```

7. Update clear filters onClick (around line 783):
   Change from:
   ```javascript
   onClick={() => {
     setEmailStatusFilter('');
     setVogTypeFilter('');
   }}
   ```
   To:
   ```javascript
   onClick={() => {
     setEmailStatusFilter('');
     setVogTypeFilter('');
     setJustisStatusFilter('');
   }}
   ```

8. Add active filter chip for Justis (around line 819, after the vogTypeFilter chip):
   ```jsx
   {justisStatusFilter && (
     <span className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-accent-50 dark:bg-accent-900/30 text-accent-700 dark:text-accent-300 border border-accent-200 dark:border-accent-700 rounded">
       Justis: {justisStatusFilter === 'submitted' ? 'Aangevraagd' : 'Niet aangevraagd'}
       <button
         onClick={() => setJustisStatusFilter('')}
         className="hover:text-accent-900 dark:hover:text-accent-100"
       >
         <X className="w-3 h-3" />
       </button>
     </span>
   )}
   ```

9. Update Google Sheets export filters (around line 467, after vog_email_status):
   ```javascript
   vog_justis_status: justisStatusFilter || undefined,
   ```
  </action>
  <verify>Run `npm run lint` and `npm run build`. Both should complete without errors.</verify>
  <done>VOG page has working Justis status filter with counts, active chips, and clear functionality.</done>
</task>

</tasks>

<verification>
1. `npm run lint` passes
2. `npm run build` completes successfully
3. Manual test on production:
   - Visit VOG page
   - Open filter dropdown
   - See "Justis status" section with counts
   - Filter by "Niet aangevraagd" - shows only people without Justis date
   - Filter by "Aangevraagd" - shows only people with Justis date
   - Clear filters works
   - Filter chip appears when filter is active
</verification>

<success_criteria>
- Justis status filter appears in VOG filter dropdown
- Filter correctly shows people with/without vog_justis_submitted_date
- Counts are accurate
- Active filter chip displays and can be dismissed
- Clear all filters includes Justis filter
- Google Sheets export respects the Justis filter
</success_criteria>

<output>
After completion, create `.planning/quick/029-vog-justis-filter/029-SUMMARY.md`
</output>
