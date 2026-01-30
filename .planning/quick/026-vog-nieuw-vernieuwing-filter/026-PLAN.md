---
phase: quick-026
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-people.php
  - src/hooks/usePeople.js
  - src/pages/VOG/VOGList.jsx
autonomous: true

must_haves:
  truths:
    - "User can filter VOG list to show only Nieuw (no datum-vog)"
    - "User can filter VOG list to show only Vernieuwing (expired datum-vog)"
    - "User can see counts for each filter option"
    - "Filter chip displays active VOG type filter"
  artifacts:
    - path: "includes/class-rest-people.php"
      provides: "vog_type REST parameter"
      contains: "vog_type"
    - path: "src/pages/VOG/VOGList.jsx"
      provides: "VOG type filter dropdown"
      contains: "vogTypeFilter"
  key_links:
    - from: "src/pages/VOG/VOGList.jsx"
      to: "useFilteredPeople"
      via: "vogType parameter"
      pattern: "vogType.*Filter"
---

<objective>
Add a filter to the VOG page for "Nieuw" vs "Vernieuwing" status.

Purpose: Allow users to filter the VOG list by VOG type - "Nieuw" shows people who have never had a VOG (no datum_vog), "Vernieuwing" shows people whose VOG has expired (datum_vog exists but is older than 3 years).

Output: Working filter dropdown in VOG page with counts, matching the existing email status filter pattern.
</objective>

<execution_context>
@.claude/get-shit-done/workflows/execute-plan.md
@.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@includes/class-rest-people.php (lines 300-345 for vog filter params, lines 1140-1160 for filter logic)
@src/pages/VOG/VOGList.jsx (current VOG page with email status filter)
@src/hooks/usePeople.js (useFilteredPeople hook)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add vog_type backend filter</name>
  <files>includes/class-rest-people.php</files>
  <action>
Add a new `vog_type` parameter to the filtered people endpoint:

1. In the endpoint registration (around line 337, after vog_email_status), add:
```php
'vog_type' => [
    'description'       => 'Filter by VOG type (nieuw=no VOG, vernieuwing=expired VOG)',
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => function ( $value ) {
        return in_array( $value, [ '', 'nieuw', 'vernieuwing' ], true );
    },
],
```

2. In get_filtered_people method (around line 1036), add parameter extraction:
```php
$vog_type = $request->get_param( 'vog_type' );
```

3. Modify the VOG filter logic (around line 1146). Currently it ORs vog_missing and vog_older_than_years. Add vog_type handling:
- If vog_type is 'nieuw': Only match where datum-vog IS NULL or empty (ignore vog_older_than_years)
- If vog_type is 'vernieuwing': Only match where datum-vog EXISTS but is older than cutoff (requires vog_older_than_years to be set)
- If vog_type is empty (default): Keep current behavior (OR both conditions)

The logic should be:
```php
// Datum VOG filtering based on vog_type
if ( $vog_type === 'nieuw' ) {
    // Only show people WITHOUT a VOG date
    $join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
    $where_clauses[] = "(dv.meta_value IS NULL OR dv.meta_value = '')";
} elseif ( $vog_type === 'vernieuwing' && $vog_older_than_years !== null ) {
    // Only show people WITH an expired VOG date
    $join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
    $cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
    $where_clauses[]  = "(dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value < %s)";
    $prepare_values[] = $cutoff_date;
} elseif ( $vog_missing === '1' && $vog_older_than_years !== null ) {
    // Default: OR both conditions (show all needing VOG)
    // Keep existing logic
    $join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
    $cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
    $where_clauses[]  = "((dv.meta_value IS NULL OR dv.meta_value = '') OR (dv.meta_value < %s))";
    $prepare_values[] = $cutoff_date;
} elseif ( $vog_missing === '1' ) {
    // ... keep existing logic
} elseif ( $vog_older_than_years !== null ) {
    // ... keep existing logic
}
```
  </action>
  <verify>Test via curl:
- `curl -X GET "https://stadion.svawc.nl/wp-json/stadion/v1/people/filtered?huidig_vrijwilliger=1&vog_missing=1&vog_older_than_years=3&vog_type=nieuw"` - should only return people without datum-vog
- `curl -X GET "...&vog_type=vernieuwing"` - should only return people with expired datum-vog
- `curl -X GET "...&vog_type="` - should return both (current behavior)
  </verify>
  <done>Backend accepts vog_type parameter and correctly filters by VOG type</done>
</task>

<task type="auto">
  <name>Task 2: Add vog_type to frontend hook and UI filter</name>
  <files>src/hooks/usePeople.js, src/pages/VOG/VOGList.jsx</files>
  <action>
**In src/hooks/usePeople.js:**

1. Add vogType to the useFilteredPeople JSDoc (around line 110):
```js
* @param {string} filters.vogType - 'nieuw', 'vernieuwing', or '' for all
```

2. Add vog_type to params object (around line 132):
```js
vog_type: filters.vogType || null,
```

**In src/pages/VOG/VOGList.jsx:**

1. Add state for VOG type filter (after emailStatusFilter around line 242):
```js
const [vogTypeFilter, setVogTypeFilter] = useState('');
```

2. Pass vogType to both useFilteredPeople calls (around lines 265-286):
- In the main data fetch: add `vogType: vogTypeFilter,`
- The allData fetch (for counts) should NOT include vogType (it needs all people to calculate counts)

3. Calculate VOG type counts from allData (add after emailCounts around line 298):
```js
const vogTypeCounts = useMemo(() => {
  const allPeople = allData?.people || [];
  const nieuw = allPeople.filter(p => !p.acf?.['datum-vog']).length;
  const vernieuwing = allPeople.length - nieuw;
  return { total: allPeople.length, nieuw, vernieuwing };
}, [allData?.people]);
```

4. In the Filter dropdown panel (around line 630), add a new section ABOVE "Email status":
```jsx
{/* VOG Type Filter */}
<div>
  <h3 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
    VOG type
  </h3>
  <div className="space-y-1">
    <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
      <input
        type="checkbox"
        checked={vogTypeFilter === ''}
        onChange={() => setVogTypeFilter('')}
        className="sr-only"
      />
      <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
        vogTypeFilter === ''
          ? 'bg-accent-600 border-accent-600'
          : 'border-gray-300 dark:border-gray-500'
      }`}>
        {vogTypeFilter === '' && (
          <Check className="w-3 h-3 text-white" />
        )}
      </div>
      <span className="text-sm text-gray-700 dark:text-gray-200">
        Alle ({vogTypeCounts.total})
      </span>
    </label>
    <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
      <input
        type="checkbox"
        checked={vogTypeFilter === 'nieuw'}
        onChange={() => setVogTypeFilter('nieuw')}
        className="sr-only"
      />
      <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
        vogTypeFilter === 'nieuw'
          ? 'bg-accent-600 border-accent-600'
          : 'border-gray-300 dark:border-gray-500'
      }`}>
        {vogTypeFilter === 'nieuw' && (
          <Check className="w-3 h-3 text-white" />
        )}
      </div>
      <span className="text-sm text-gray-700 dark:text-gray-200">
        Nieuw ({vogTypeCounts.nieuw})
      </span>
    </label>
    <label className="flex items-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded">
      <input
        type="checkbox"
        checked={vogTypeFilter === 'vernieuwing'}
        onChange={() => setVogTypeFilter('vernieuwing')}
        className="sr-only"
      />
      <div className={`flex items-center justify-center w-5 h-5 border-2 rounded mr-3 ${
        vogTypeFilter === 'vernieuwing'
          ? 'bg-accent-600 border-accent-600'
          : 'border-gray-300 dark:border-gray-500'
      }`}>
        {vogTypeFilter === 'vernieuwing' && (
          <Check className="w-3 h-3 text-white" />
        )}
      </div>
      <span className="text-sm text-gray-700 dark:text-gray-200">
        Vernieuwing ({vogTypeCounts.vernieuwing})
      </span>
    </label>
  </div>
</div>
```

5. Update the filter button badge count (line 618) to count active filters:
```js
{(emailStatusFilter || vogTypeFilter) && (
  <span className="ml-2 px-1.5 py-0.5 bg-accent-600 text-white text-xs rounded-full">
    {(emailStatusFilter ? 1 : 0) + (vogTypeFilter ? 1 : 0)}
  </span>
)}
```

6. Add VOG type filter chip display (after emailStatusFilter chip, around line 726):
```jsx
{vogTypeFilter && (
  <span className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-accent-50 dark:bg-accent-900/30 text-accent-700 dark:text-accent-300 border border-accent-200 dark:border-accent-700 rounded">
    Type: {vogTypeFilter === 'nieuw' ? 'Nieuw' : 'Vernieuwing'}
    <button
      onClick={() => setVogTypeFilter('')}
      className="hover:text-accent-900 dark:hover:text-accent-100"
    >
      <X className="w-3 h-3" />
    </button>
  </span>
)}
```

7. Update "Clear all filters" button (around line 701) to check both filters:
```jsx
{(emailStatusFilter || vogTypeFilter) && (
  <button
    onClick={() => {
      setEmailStatusFilter('');
      setVogTypeFilter('');
    }}
    className="w-full text-sm text-accent-600 dark:text-accent-400 hover:text-accent-700 dark:hover:text-accent-300 font-medium pt-2 border-t border-gray-200 dark:border-gray-700"
  >
    Alle filters wissen
  </button>
)}
```

8. Update the Google Sheets export to include vog_type filter (around line 458):
```js
vog_type: vogTypeFilter || undefined,
```
  </action>
  <verify>
- Run `npm run lint` - should pass
- Run `npm run build` - should succeed
- Test in browser: Filter dropdown shows "VOG type" section with Alle/Nieuw/Vernieuwing options
- Selecting "Nieuw" shows only people with blue "Nieuw" badge
- Selecting "Vernieuwing" shows only people with purple "Vernieuwing" badge
- Filter badge count shows correct number of active filters
- Filter chips display correctly and can be dismissed
  </verify>
  <done>VOG type filter works in UI, filters correctly, shows counts, and exports correctly</done>
</task>

</tasks>

<verification>
- [ ] Backend vog_type parameter accepted and filters correctly
- [ ] Frontend filter dropdown shows VOG type section with counts
- [ ] Nieuw filter shows only people without datum-vog
- [ ] Vernieuwing filter shows only people with expired datum-vog
- [ ] Filter badge shows correct count of active filters
- [ ] Filter chips display and dismiss correctly
- [ ] Google Sheets export respects VOG type filter
- [ ] npm run lint passes
- [ ] npm run build succeeds
</verification>

<success_criteria>
Users can filter the VOG list by "Nieuw" (no VOG) or "Vernieuwing" (expired VOG) using the filter dropdown, with accurate counts shown for each option.
</success_criteria>

<output>
After completion, create `.planning/quick/026-vog-nieuw-vernieuwing-filter/026-SUMMARY.md`
</output>
