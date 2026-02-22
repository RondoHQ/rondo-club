---
phase: quick-117
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-api.php
  - src/hooks/useDashboard.js
  - src/components/layout/Layout.jsx
autonomous: true
requirements: [QUICK-117]
must_haves:
  truths:
    - "Searching for an invoice number (e.g. '2026T' or '2026C001') returns matching invoices in the search modal"
    - "Invoice results only appear for users with the financieel capability"
    - "Clicking an invoice result navigates to /financien/facturen/{id}"
    - "Invoice results show invoice number, person name, and status"
    - "Existing people and team search still works unchanged"
  artifacts:
    - path: "includes/class-rest-api.php"
      provides: "Invoice search in global_search() method"
      contains: "rondo_invoice"
    - path: "src/hooks/useDashboard.js"
      provides: "Updated useSearch default shape with invoices key"
      contains: "invoices"
    - path: "src/components/layout/Layout.jsx"
      provides: "Invoice result rendering in SearchModal"
      contains: "invoices"
  key_links:
    - from: "includes/class-rest-api.php"
      to: "invoice_number meta field"
      via: "meta_query LIKE search on rondo_invoice post type"
      pattern: "invoice_number.*LIKE"
    - from: "src/components/layout/Layout.jsx"
      to: "/financien/facturen/{id}"
      via: "navigate on invoice result click"
      pattern: "financien/facturen"
---

<objective>
Add invoice number search to the sitewide search function so users with finance access can find invoices by typing invoice numbers (e.g. "2026T001", "2026C") in the search modal.

Purpose: Finance users need to quickly look up invoices by number without navigating to the Facturen page and filtering there.
Output: Updated search backend + frontend rendering invoice results in the search modal.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-rest-api.php (global_search method, lines ~2062-2309)
@includes/class-rest-invoices.php (format_invoice method, lines ~1460-1475)
@includes/class-invoice-numbering.php (invoice number format: YYYY + T/C + 3-digit sequence)
@src/hooks/useDashboard.js (useSearch hook, lines ~76-92)
@src/components/layout/Layout.jsx (SearchModal component, lines ~221-445)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add invoice search to backend global_search endpoint</name>
  <files>includes/class-rest-api.php</files>
  <action>
In the `global_search()` method (around line 2064), add an invoice search section AFTER the existing teams search (before `return rest_ensure_response`). This section should:

1. Initialize `$results['invoices'] = [];` at the top alongside people and teams (line ~2067).

2. Only search invoices if `current_user_can( 'financieel' )` — non-finance users should never see invoice results.

3. Inside the capability check, query `rondo_invoice` posts with a `meta_query` on the `invoice_number` field using `LIKE` comparison against `$query`. Include all post statuses since invoices use custom statuses (publish, rondo_sent, rondo_paid, rondo_overdue, draft). Limit to 10 results.

4. For each matched invoice, build a lightweight result array with:
   - `id` (post ID)
   - `invoice_number` (from `get_field('invoice_number', $id)`)
   - `person_name` (get person linked via `get_field('person', $id)`, then get their first_name + infix + last_name, or null if no person linked)
   - `total_amount` (float, from `get_field('total_amount', $id)`)
   - `status` (from `get_field('status', $id)`)

Do NOT reuse the full `format_invoice()` from class-rest-invoices.php — that class is not loaded in class-rest-api.php context and the search result needs much less data. Build the lightweight array inline.

Sort results by invoice_number descending (most recent first) before adding to `$results['invoices']`.
  </action>
  <verify>
Deploy to production and test via curl:
```bash
# As a user with financieel capability, search for an invoice prefix
curl -s "https://rondo.svawc.nl/wp-json/rondo/v1/search?q=2026T" -H "Cookie: ..." | jq '.invoices'
```
Verify invoices key exists in response and contains matching invoices with id, invoice_number, person_name, total_amount, status fields. Also verify people and teams still return correctly.
  </verify>
  <done>The /rondo/v1/search endpoint returns an `invoices` array (possibly empty) alongside people and teams. Invoice results only appear for financieel users and match on invoice_number LIKE query.</done>
</task>

<task type="auto">
  <name>Task 2: Render invoice search results in the frontend search modal</name>
  <files>src/hooks/useDashboard.js, src/components/layout/Layout.jsx</files>
  <action>
**In `src/hooks/useDashboard.js`:**
- In the `useSearch` hook, update the default empty return to `{ people: [], teams: [], invoices: [] }` (line ~85).

**In `src/components/layout/Layout.jsx` SearchModal component:**

1. Update `safeResults` (line ~232) to include `invoices: []`:
   ```js
   const safeResults = searchResults || { people: [], teams: [], invoices: [] };
   ```

2. Update `allResults` array (line ~233) to include invoices, mapped with `type: 'invoice'`:
   ```js
   const allResults = [
     ...(safeResults.people || []).map(p => ({ ...p, type: 'person' })),
     ...(safeResults.teams || []).map(c => ({ ...c, type: 'team' })),
     ...(safeResults.invoices || []).map(i => ({ ...i, type: 'invoice' })),
   ];
   ```

3. Update `handleResultClick` (line ~280) to handle the `'invoice'` type:
   ```js
   } else if (type === 'invoice') {
     navigate(`/financien/facturen/${id}`);
   }
   ```

4. Add an invoices results section AFTER the teams section (after line ~418), following the exact same pattern as teams. Use the `Receipt` icon (already imported). Section header: "Facturen". For each invoice result, show:
   - Left: Receipt icon in a rounded square (same style as Building2 for teams)
   - Main text: `invoice.invoice_number` (bold)
   - Secondary text: `invoice.person_name` (if present, in lighter text after the number)
   - Right side: Status badge. Map status values to Dutch labels and colors:
     - `draft` -> "Concept" (gray)
     - `sent` -> "Verzonden" (blue)
     - `paid` -> "Betaald" (green)
     - `overdue` -> "Te laat" (red)
     - default -> capitalize the status value (gray)

   The `globalIndex` calculation must account for people + teams length before invoices:
   ```js
   const globalIndex = (safeResults.people?.length || 0) + (safeResults.teams?.length || 0) + index;
   ```

Note: Invoice results will naturally only appear for users with `financieel` capability since the backend gates on that capability. No frontend capability check needed in the search modal — if the backend returns no invoices, the section simply won't render.
  </action>
  <verify>
Run `npm run build` to verify the frontend compiles. Then deploy and test:
1. Open the search modal (Cmd+K)
2. Type an invoice number prefix (e.g. "2026T")
3. Verify invoice results appear under a "Facturen" section heading
4. Verify clicking an invoice navigates to /financien/facturen/{id}
5. Verify keyboard navigation (arrow keys + enter) works across all three sections
6. Verify a user WITHOUT financieel capability does NOT see invoice results
  </verify>
  <done>Invoice search results render in the search modal with invoice number, person name, status badge, and Receipt icon. Clicking navigates to the invoice detail page. Keyboard navigation works correctly across people, teams, and invoice sections.</done>
</task>

</tasks>

<verification>
- Search for a known invoice number -> appears in results with correct data
- Search for a person name -> still works, no regression
- Search for a team name -> still works, no regression
- Click invoice result -> navigates to /financien/facturen/{id}
- Non-financieel user searches for invoice number -> no invoice results shown
- Keyboard navigation (up/down/enter) works across all three result sections
- `npm run build` passes
- `npm run lint` passes
</verification>

<success_criteria>
Users with finance access can search for invoice numbers in the sitewide search modal and navigate directly to the invoice detail page. Non-finance users see no invoice results. Existing people and team search is unaffected.
</success_criteria>

<output>
After completion, create `.planning/quick/117-in-the-sitewide-search-function-allow-se/117-SUMMARY.md`
</output>
