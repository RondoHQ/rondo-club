---
phase: quick-116
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-invoices.php
  - src/api/client.js
  - src/hooks/useInvoices.js
  - src/pages/DisciplineCases/DisciplineCasesList.jsx
  - src/components/DisciplineCaseTable.jsx
autonomous: true
requirements: [BULK-01]

must_haves:
  truths:
    - "User can select discipline cases on the /tuchtzaken list page that have not been invoiced"
    - "User can bulk-create invoices from selected cases, grouped by person (one invoice per person)"
    - "Cases already on an invoice show a file icon and cannot be selected"
    - "Button says 'Maak facturen' (plural) when cases span multiple people, 'Maak factuur' (singular) when one person"
    - "After successful bulk creation, user is navigated to the facturen list"
  artifacts:
    - path: "includes/class-rest-invoices.php"
      provides: "POST /rondo/v1/invoices/bulk and GET /rondo/v1/invoices/all-invoiced-cases endpoints"
    - path: "src/api/client.js"
      provides: "bulkCreateInvoices and getAllInvoicedCaseIds API methods"
    - path: "src/hooks/useInvoices.js"
      provides: "useAllInvoicedCaseIds and useBulkCreateInvoices hooks"
    - path: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      provides: "Selection state, bulk create handler, wiring to DisciplineCaseTable"
  key_links:
    - from: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      to: "/rondo/v1/invoices/bulk"
      via: "useBulkCreateInvoices hook"
      pattern: "bulkCreateInvoices"
    - from: "src/pages/DisciplineCases/DisciplineCasesList.jsx"
      to: "/rondo/v1/invoices/all-invoiced-cases"
      via: "useAllInvoicedCaseIds hook"
      pattern: "getAllInvoicedCaseIds"
    - from: "DisciplineCasesList.jsx"
      to: "DisciplineCaseTable"
      via: "canCreateInvoice, selectedCaseIds, invoicedCaseIds props"
      pattern: "canCreateInvoice.*selectedCaseIds"
---

<objective>
Add bulk invoice creation from the /tuchtzaken list page. Users select discipline cases (that haven't been invoiced), and create invoices for all selected cases in one go. Cases for the same person are grouped into a single invoice.

Purpose: Currently invoice creation from discipline cases is only possible from the PersonDetail page (one person at a time). The list view already has the selection UI in DisciplineCaseTable but it's not wired up. This enables batch processing of discipline charges.

Output: Working bulk invoice flow on /tuchtzaken page with backend bulk endpoint.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-rest-invoices.php (route registration pattern, get_invoiced_case_ids, create_invoice methods)
@src/api/client.js (prmApi methods for invoices, lines 290-310)
@src/hooks/useInvoices.js (useInvoicedCaseIds, useCreateInvoice patterns)
@src/pages/DisciplineCases/DisciplineCasesList.jsx (current page - needs selection wiring)
@src/components/DisciplineCaseTable.jsx (already has canCreateInvoice, selectedCaseIds, invoicedCaseIds props)
@src/pages/People/PersonDetail.jsx (reference: how PersonDetail wires selection - lines 74-75, 86-90, 486-506, 1589-1594)
@src/utils/disciplineCases.js (isDoorbelastNVT utility)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add backend endpoints for bulk invoice creation and all-invoiced-cases</name>
  <files>includes/class-rest-invoices.php, src/api/client.js, src/hooks/useInvoices.js</files>
  <action>
**In `includes/class-rest-invoices.php`:**

1. Add route registration for `GET /rondo/v1/invoices/all-invoiced-cases` in `register_routes()` (after the existing `invoiced-cases` route around line 57). This endpoint takes no parameters. Permission callback: `check_financieel_permission`. Callback: `get_all_invoiced_case_ids`.

2. Add route registration for `POST /rondo/v1/invoices/bulk` in `register_routes()`. Permission callback: `check_financieel_permission`. Callback: `bulk_create_invoices`.

3. Add `get_all_invoiced_case_ids()` method: Same logic as existing `get_invoiced_case_ids()` (lines 336-373) but WITHOUT the `meta_query` filter on `person`. Query ALL `rondo_invoice` posts with statuses `['rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue']`, extract all `discipline_case` IDs from line items, return unique IDs as `{ case_ids: [...] }`.

4. Add `bulk_create_invoices($request)` method:
   - Accept `case_ids` param (array of discipline case post IDs).
   - Validate: non-empty array, all numeric.
   - Load each discipline case via `get_post()`, validate they exist and are `discipline_case` post type.
   - Group cases by their `person` ACF field value (person_id).
   - For each person group, build `line_items` array where each item has: `discipline_case_id` = case ID, `description` = case's `match_description` or `sanction_description` (fallback), `amount` = case's `administrative_fee` (float, default 0).
   - Create a WP_REST_Request for each group and call `$this->create_invoice()` internally (reuse existing logic which handles invoice numbering, admin fee injection, ACF field setting).
   - Collect results. If any create returns a WP_Error, include it in the response but continue with other groups.
   - Return `{ invoices: [...created invoice objects...], errors: [...any errors...] }`.

**In `src/api/client.js`:**

Add two new methods to the `prmApi` object (near line 300, after `getInvoicedCaseIds`):

```javascript
getAllInvoicedCaseIds: () => api.get('/rondo/v1/invoices/all-invoiced-cases'),
bulkCreateInvoices: (caseIds) => api.post('/rondo/v1/invoices/bulk', { case_ids: caseIds }),
```

**In `src/hooks/useInvoices.js`:**

1. Add `useAllInvoicedCaseIds(options)` hook:
   - queryKey: `['invoiced-case-ids', 'all']`
   - queryFn: calls `prmApi.getAllInvoicedCaseIds()`, returns `response.data.case_ids`
   - staleTime: 30000
   - Spread options

2. Add `useBulkCreateInvoices()` hook:
   - Uses `useMutation` pattern (same as `useCreateInvoice`)
   - mutationFn: calls `prmApi.bulkCreateInvoices(caseIds)`, returns `response.data`
   - onSuccess: invalidate `['invoiced-case-ids']`, `['invoices']`, `['invoices', 'person']` (same pattern as useCreateInvoice)
  </action>
  <verify>Run `npm run lint` and `npm run build` to confirm no syntax errors. Verify the new endpoint registrations are syntactically correct PHP.</verify>
  <done>Two new REST endpoints registered and implemented. API client methods and React hooks added for both endpoints.</done>
</task>

<task type="auto">
  <name>Task 2: Wire up selection UI and bulk create handler in DisciplineCasesList</name>
  <files>src/pages/DisciplineCases/DisciplineCasesList.jsx, src/components/DisciplineCaseTable.jsx</files>
  <action>
**In `src/pages/DisciplineCases/DisciplineCasesList.jsx`:**

1. Add imports:
   - `{ useNavigate }` from `react-router-dom`
   - `{ useCurrentUser }` from `@/hooks/useCurrentUser`
   - `{ useAllInvoicedCaseIds, useBulkCreateInvoices }` from `@/hooks/useInvoices`

2. Inside the component, add:
   - `const navigate = useNavigate();`
   - `const { data: currentUser } = useCurrentUser();`
   - `const canAccessFinancieel = currentUser?.can_access_financieel ?? false;`
   - `const canAccessFairplay = currentUser?.can_access_fairplay ?? false;`
   - `const canCreateInvoice = canAccessFairplay && canAccessFinancieel;`
   - `const [selectedCaseIds, setSelectedCaseIds] = useState(new Set());`
   - `const { data: invoicedCaseIds = [] } = useAllInvoicedCaseIds({ enabled: canCreateInvoice });`
   - `const bulkCreate = useBulkCreateInvoices();`

3. Add `handleBulkCreateInvoice` async function:
   - Guard: if `selectedCaseIds.size === 0` return.
   - Get selected case IDs as array: `[...selectedCaseIds]`.
   - Call `await bulkCreate.mutateAsync([...selectedCaseIds])`.
   - On success: `setSelectedCaseIds(new Set())`, then `navigate('/financien/facturen')` to go to the invoices list.
   - On error: `alert('Facturen konden niet worden aangemaakt. Probeer het opnieuw.')`.
   - Wrap the mutateAsync call in try/catch.

4. Clear selection when season changes: In `handleSeasonChange`, add `setSelectedCaseIds(new Set())` before setting the new season.

5. Wire DisciplineCaseTable props (in the JSX where `<DisciplineCaseTable>` is rendered, around line 368):
   - Add `canCreateInvoice={canCreateInvoice}`
   - Add `selectedCaseIds={selectedCaseIds}`
   - Add `onSelectionChange={setSelectedCaseIds}`
   - Add `onCreateInvoice={handleBulkCreateInvoice}`
   - Add `isCreatingInvoice={bulkCreate.isPending}`
   - Add `invoicedCaseIds={new Set(invoicedCaseIds)}`

6. Also invalidate `invoiced-case-ids` on refresh: in `handleRefresh`, add `await queryClient.invalidateQueries({ queryKey: ['invoiced-case-ids'] });`.

**In `src/components/DisciplineCaseTable.jsx`:**

1. Modify the "Maak factuur" button text (line 252) to be dynamic based on whether selected cases span multiple people. Add a `useMemo` that computes the number of distinct person IDs among selected cases:

```javascript
const selectedPersonCount = useMemo(() => {
  if (!cases || selectedCaseIds.size === 0) return 0;
  const personIds = new Set(
    cases
      .filter(dc => selectedCaseIds.has(dc.id))
      .map(dc => dc.acf?.person)
      .filter(Boolean)
  );
  return personIds.size;
}, [cases, selectedCaseIds]);
```

2. Change the button text from hardcoded `Maak factuur` to: `selectedPersonCount > 1 ? 'Maak facturen' : 'Maak factuur'`.

3. Update the selection toolbar text (line 239) to also show person count when multiple: Change from just `{selectedCaseIds.size} tuchtzaken geselecteerd` to `{selectedCaseIds.size} tuchtzaken geselecteerd ({selectedPersonCount} {selectedPersonCount === 1 ? 'persoon' : 'personen'})` when `selectedPersonCount > 1`. When `selectedPersonCount <= 1`, keep the existing text.

4. The `uninvoicedCaseIds` computation (line 118-121) should also exclude cases where `isDoorbelastNVT()` is true (no fee = nothing to invoice). Import `isDoorbelastNVT` (already imported at line 8). Update the filter: `cases.filter(dc => !invoicedSet.has(dc.id) && !isDoorbelastNVT(dc.acf || {}))`.

5. The checkbox display logic (lines 366-378): Also hide the checkbox (show nothing, not the FileText icon) for cases where `isDoorbelastNVT()` is true. Update the condition: if `isInvoiced` show FileText icon, else if `isDoorbelastNVT(acf)` show nothing (empty `<div>`), else show checkbox.
  </action>
  <verify>Run `npm run lint` and `npm run build` to verify clean compilation. Test the full flow mentally: load /tuchtzaken, checkboxes appear for non-invoiced non-NVT cases, selecting cases shows toolbar, clicking button calls bulk endpoint, success navigates to facturen list.</verify>
  <done>Selection UI fully wired on /tuchtzaken list page. Selecting cases and clicking "Maak facturen" calls the bulk endpoint, groups by person, creates draft invoices, and navigates to the invoices list. Cases already invoiced show a file icon. N.v.t. cases have no checkbox. Button text adapts between singular/plural based on person count.</done>
</task>

</tasks>

<verification>
1. `npm run lint` passes with 0 warnings
2. `npm run build` produces a clean build
3. On /tuchtzaken page, non-invoiced, non-NVT discipline cases show checkboxes
4. Selecting cases shows the cyan selection toolbar with count and total
5. Button says "Maak factuur" for single-person selection, "Maak facturen" for multi-person
6. Clicking the button creates draft invoices grouped by person
7. After creation, navigates to /financien/facturen
8. Returning to /tuchtzaken shows those cases with the file icon (invoiced)
</verification>

<success_criteria>
- Bulk invoice creation works from /tuchtzaken list page
- Cases grouped by person into separate invoices
- Existing single-person invoice flow on PersonDetail is unaffected
- Selection toolbar shows correct counts and totals
- N.v.t. cases excluded from selection
- Already-invoiced cases show file icon and cannot be selected
</success_criteria>

<output>
After completion, create `.planning/quick/116-bulk-create-invoices-from-selected-tucht/116-SUMMARY.md`
</output>
