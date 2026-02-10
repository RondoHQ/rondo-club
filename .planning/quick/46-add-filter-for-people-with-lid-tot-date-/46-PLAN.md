---
phase: quick-46
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-people.php
  - src/hooks/usePeople.js
  - src/pages/People/PeopleList.jsx
autonomous: true
must_haves:
  truths:
    - "User can filter the people list to show only people with a lid-tot date in the future"
    - "Filter chip shows when the lid-tot filter is active"
    - "Filter can be cleared via the chip or the clear all filters button"
  artifacts:
    - path: "includes/class-rest-people.php"
      provides: "lid_tot_future REST filter parameter"
      contains: "lid_tot_future"
    - path: "src/hooks/usePeople.js"
      provides: "lidTotFuture filter parameter passthrough"
      contains: "lid_tot_future"
    - path: "src/pages/People/PeopleList.jsx"
      provides: "Lid-tot future filter UI in filter dropdown"
      contains: "lidTotFuture"
  key_links:
    - from: "src/pages/People/PeopleList.jsx"
      to: "src/hooks/usePeople.js"
      via: "lidTotFuture filter param"
      pattern: "lidTotFuture"
    - from: "src/hooks/usePeople.js"
      to: "includes/class-rest-people.php"
      via: "lid_tot_future query param"
      pattern: "lid_tot_future"
---

<objective>
Add a filter to the People list that shows only people with a `lid-tot` date set in the future. This allows club administrators to easily see members whose membership has a known end date coming up.

Purpose: Identify members who are about to leave the club so administrators can take appropriate action.
Output: Working filter across backend REST API, React hook, and filter UI.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-rest-people.php
@src/hooks/usePeople.js
@src/pages/People/PeopleList.jsx
@acf-json/group_person_fields.json (lid-tot field definition: name="lid-tot", type=date_picker, return_format=Y-m-d)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add lid_tot_future filter to REST API and React</name>
  <files>includes/class-rest-people.php, src/hooks/usePeople.js, src/pages/People/PeopleList.jsx</files>
  <action>
**Backend (includes/class-rest-people.php):**

1. In the `register_routes()` method, inside the `/people/filtered` route args array, add a new parameter after `include_former`:

```php
'lid_tot_future' => [
    'description'       => 'Filter for people with lid-tot date in the future (1=future only, empty=all)',
    'type'              => 'string',
    'default'           => '',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => function ( $value ) {
        return in_array( $value, [ '', '1' ], true );
    },
],
```

2. In `get_filtered_people()`, after the `$include_former` param extraction (~line 1002), add:
```php
$lid_tot_future = $request->get_param( 'lid_tot_future' );
```

3. In the filter building section (after the VOG Justis status filter block, ~line 1183), add:
```php
// Lid-tot (membership end date) future filter
if ( $lid_tot_future === '1' ) {
    $join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} lt ON p.ID = lt.post_id AND lt.meta_key = 'lid-tot'";
    $today            = gmdate( 'Y-m-d' );
    $where_clauses[]  = "(lt.meta_value IS NOT NULL AND lt.meta_value != '' AND lt.meta_value >= %s)";
    $prepare_values[] = $today;
}
```

**Frontend hook (src/hooks/usePeople.js):**

4. In `useFilteredPeople()`, add to the params object (after `include_former`):
```js
lid_tot_future: filters.lidTotFuture || null,
```

5. Update the JSDoc for `useFilteredPeople` to document the new param:
```
 * @param {string} filters.lidTotFuture - '1' to show only people with lid-tot date in the future
```

**Frontend UI (src/pages/People/PeopleList.jsx):**

6. Add URL state for the new filter. After the `includeFormer` line (~line 661):
```js
const lidTotFuture = searchParams.get('lidTot') || '';
```

7. Add setter after `setIncludeFormer` (~line 741):
```js
const setLidTotFuture = useCallback((value) => {
    updateSearchParams({ lidTot: value });
}, [updateSearchParams]);
```

8. Pass the new filter to `useFilteredPeople`. In the hook call (~line 791), add after `includeFormer`:
```js
lidTotFuture: lidTotFuture || null,
```

9. Add the filter UI in the filter dropdown. Place it right after the "Toon oud-leden" toggle section (after the closing `</div>` of the Former Members Toggle, ~line 1182) and BEFORE the Labels Filter section. Use the same toggle pattern as "Toon oud-leden":

```jsx
{/* Lid-tot in Future Toggle */}
<div>
    <label className="flex items-center cursor-pointer">
        <div className="relative">
            <input
                type="checkbox"
                checked={lidTotFuture === '1'}
                onChange={() => setLidTotFuture(lidTotFuture === '1' ? '' : '1')}
                className="sr-only peer"
            />
            <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:bg-electric-cyan transition-colors"></div>
            <div className="absolute left-[2px] top-[2px] bg-white w-4 h-4 rounded-full transition-transform peer-checked:translate-x-4"></div>
        </div>
        <span className="ml-3 text-sm font-medium text-gray-700 dark:text-gray-200">Lid-tot in de toekomst</span>
    </label>
</div>
```

10. Update `hasActiveFilters` (~line 929) to include the new filter. Add `|| lidTotFuture` at the end.

11. Update the filter count badge (~line 1152) to include `(lidTotFuture ? 1 : 0)` in the sum.

12. Add a filter chip for lid-tot future. After the VOG older than chip (~line 1549), add:
```jsx
{lidTotFuture === '1' && (
    <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
        Lid-tot in de toekomst
        <button
            onClick={() => setLidTotFuture('')}
            className="hover:text-gray-600 dark:hover:text-gray-300"
        >
            <X className="w-3 h-3" />
        </button>
    </span>
)}
```

13. Add `lidTotFuture` to the `useEffect` dependency array that clears selection when filters change (~line 1014).

14. Include `includeFormer` toggle chip. After the existing VOG chips block, before the closing `</div>` of the filter chips section, add:
```jsx
{includeFormer === '1' && (
    <span className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-xs">
        Incl. oud-leden
        <button
            onClick={() => setIncludeFormer('')}
            className="hover:text-gray-600 dark:hover:text-gray-300"
        >
            <X className="w-3 h-3" />
        </button>
    </span>
)}
```

Wait -- check if includeFormer already has a chip. If it does, skip step 14.

15. In the Google Sheets export filters object (~line 1098), add:
```js
lid_tot_future: lidTotFuture || undefined,
```
  </action>
  <verify>
1. Run `npm run build` from rondo-club/ to verify no build errors.
2. Run `npm run lint` to check for lint issues.
3. Verify the PHP syntax: `php -l includes/class-rest-people.php`
4. Grep for `lid_tot_future` in class-rest-people.php to confirm backend filter is present.
5. Grep for `lidTotFuture` in PeopleList.jsx to confirm frontend filter is wired.
  </verify>
  <done>
- The people list has a new toggle "Lid-tot in de toekomst" in the filter dropdown
- When enabled, only people with a lid-tot date >= today are shown
- A filter chip "Lid-tot in de toekomst" appears when the filter is active
- The filter persists in URL params as `lidTot=1`
- The filter count badge includes this filter
- The filter can be cleared via chip X button or "Alle filters wissen"
- The filter is passed through to Google Sheets export
  </done>
</task>

</tasks>

<verification>
1. `npm run build` succeeds
2. `npm run lint` passes (or only pre-existing warnings)
3. `php -l includes/class-rest-people.php` shows no syntax errors
4. Deploy to production and verify the filter works on the live site
</verification>

<success_criteria>
- People list shows "Lid-tot in de toekomst" toggle in filter panel
- Activating the filter shows only members with a future lid-tot date
- Filter state persists in URL and works with browser back/forward
- Filter integrates with existing filter infrastructure (chips, count badge, clear all)
</success_criteria>

<output>
After completion, create `.planning/quick/46-add-filter-for-people-with-lid-tot-date-/46-SUMMARY.md`
</output>
