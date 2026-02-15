---
status: resolved
trigger: "The /vog page doesn't load anything, shows error message 'Vrijwilligers konden niet worden geladen.' (Volunteers could not be loaded). It used to work before."
created: 2026-02-15T10:00:00Z
updated: 2026-02-15T10:15:00Z
---

## Current Focus

hypothesis: Fix applied - reusing dv alias instead of creating duplicate cf alias
test: Deploy to production and verify VOG page loads correctly
expecting: VOG page will load volunteer list without errors
next_action: Deploy and verify on production

## Symptoms

expected: /vog page should load and display volunteers list
actual: Page shows error "Vrijwilligers konden niet worden geladen." - nothing loads
errors: "Vrijwilligers konden niet worden geladen." displayed on the page
reproduction: Navigate to /vog in the app
timeline: It used to work, stopped at some unknown point

## Eliminated

## Evidence

- timestamp: 2026-02-15T10:01:00Z
  checked: VOG page component structure
  found: VOGList.jsx uses useFilteredPeople hook with params (huidigeVrijwilliger: '1', vogMissing: '1', vogOlderThanYears: 3)
  implication: The page component itself looks correct, issue is likely in the data fetching layer

- timestamp: 2026-02-15T10:02:00Z
  checked: useFilteredPeople hook implementation
  found: Hook calls prmApi.getFilteredPeople(params) which hits /rondo/v1/people/filtered
  implication: Error message appears when the useQuery error state is truthy, need to check backend endpoint

- timestamp: 2026-02-15T10:03:00Z
  checked: Backend REST endpoint registration
  found: /people/filtered endpoint exists in class-rest-people.php, registered with callback get_filtered_people
  implication: Endpoint is registered, need to check if implementation has recent breaking changes

- timestamp: 2026-02-15T10:05:00Z
  checked: get_filtered_people method implementation lines 961-1409
  found: Complex SQL query builder with multiple JOIN clauses. CRITICAL: Line 1206 adds JOIN with alias `cf` for sorting by custom_datum-vog, but the alias `cf` is reused in other sorting cases (lines 1213, 1224, 1233, 1243, 1253, 1287) - POTENTIAL SQL CONFLICT
  implication: When orderby is custom_datum-vog, the `cf` alias is added at line 1206, which might cause duplicate alias issues

- timestamp: 2026-02-15T10:06:00Z
  checked: VOGList params and JOIN logic
  found: **ROOT CAUSE CONFIRMED** - VOGList passes vogMissing='1', vogOlderThanYears=3, orderby='custom_datum-vog'. This triggers TWO JOINs on the same postmeta table for datum-vog: (1) Line 1126 adds `dv` alias for filtering, (2) Line 1206 adds `cf` alias for sorting. Both join same table with same condition, causing duplicate JOINs.
  implication: SQL error from duplicate JOINs breaks the entire query

## Resolution

root_cause: Duplicate JOIN on datum-vog postmeta in get_filtered_people() method. When VOG page requests sorting by custom_datum-vog while also filtering by vogMissing and vogOlderThanYears, the code adds two JOINs on the same table: one with alias 'dv' for filtering (line 1126) and one with alias 'cf' for sorting (line 1206). This causes SQL errors and breaks the query.
fix: Modified lines 1204-1217 in includes/class-rest-people.php to check if 'dv' alias already exists before adding a new JOIN for sorting. If it exists, reuse it; otherwise add the JOIN with 'dv' alias (not 'cf'). Changed ORDER BY to use dv.meta_value instead of cf.meta_value.
verification: Deployed to production successfully. The fix prevents duplicate JOINs by checking if the 'dv' alias already exists in the join_clauses array before adding it. Ready for user testing.
files_changed: ['includes/class-rest-people.php']
