---
phase: quick-87
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/FinancesCard.jsx
autonomous: true
must_haves:
  truths:
    - "After clicking 'Maak factuur' on a person's detail page, user is navigated to the newly created invoice's detail page"
  artifacts:
    - path: "src/components/FinancesCard.jsx"
      provides: "Navigate to invoice after creation"
      contains: "useNavigate"
  key_links:
    - from: "src/components/FinancesCard.jsx"
      to: "/financien/facturen/{id}"
      via: "navigate() in onSuccess callback"
      pattern: "navigate.*financien/facturen"
---

<objective>
Redirect the user to the invoice detail page after successfully creating a membership invoice from the FinancesCard on PersonDetail.

Purpose: Currently, after clicking "Maak factuur", the invoice is created but the user stays on the person page with no clear feedback. Navigating to the invoice page provides immediate confirmation and lets the user act on the invoice (send it, etc.).

Output: Updated FinancesCard.jsx with post-creation navigation.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/components/FinancesCard.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Navigate to invoice detail after creation</name>
  <files>src/components/FinancesCard.jsx</files>
  <action>
    In `src/components/FinancesCard.jsx`:

    1. Add `useNavigate` to the existing `react-router-dom` import (line 2):
       `import { Link, useNavigate } from 'react-router-dom';`

    2. Add `const navigate = useNavigate();` inside the `FinancesCard` component, near the top (e.g., after line 39).

    3. Update the `createInvoice` mutation (lines 59-64):
       - The `mutationFn` currently returns the raw axios response. TanStack Query passes the mutationFn return value to `onSuccess`. Since `prmApi.createMembershipInvoice()` returns an axios response object, extract `.data` so onSuccess receives just the API payload:
         ```js
         mutationFn: () => prmApi.createMembershipInvoice({ person_id: personId, season: feeData?.season }).then(res => res.data),
         ```
       - Update `onSuccess` to accept the `data` parameter and navigate after invalidating queries:
         ```js
         onSuccess: (data) => {
           queryClient.invalidateQueries({ queryKey: ['invoices', 'person', personId] });
           navigate(`/financien/facturen/${data.invoice_id}`);
         },
         ```

    The API response already includes `invoice_id` in its payload, so no backend changes are needed.
  </action>
  <verify>
    Run `npm run build` from `rondo-club/` to confirm the frontend compiles without errors.
    Run `npm run lint` to confirm no ESLint warnings.
  </verify>
  <done>After clicking "Maak factuur", the user is redirected to `/financien/facturen/{id}` for the newly created invoice. Build and lint pass.</done>
</task>

</tasks>

<verification>
- `npm run build` succeeds
- `npm run lint` passes with 0 warnings
- The `useNavigate` import and `navigate()` call are present in FinancesCard.jsx
- The mutation's `onSuccess` callback uses `data.invoice_id` from the API response
</verification>

<success_criteria>
Clicking "Maak factuur" in the FinancesCard creates the invoice and immediately navigates the user to `/financien/facturen/{invoice_id}`.
</success_criteria>

<output>
After completion, create `.planning/quick/87-redirect-to-invoice-page-after-creating-/87-SUMMARY.md`
</output>
