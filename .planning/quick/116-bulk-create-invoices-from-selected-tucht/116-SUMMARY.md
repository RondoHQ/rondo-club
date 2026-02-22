---
phase: quick-116
plan: 01
subsystem: discipline-cases, invoices
tags: [bulk-operations, invoices, tuchtzaken, rest-api]
key-files:
  modified:
    - includes/class-rest-invoices.php
    - src/api/client.js
    - src/hooks/useInvoices.js
    - src/pages/DisciplineCases/DisciplineCasesList.jsx
    - src/components/DisciplineCaseTable.jsx
decisions:
  - "Route /invoices/all-invoiced-cases and /invoices/bulk registered before /invoices to avoid WordPress REST router matching /invoices first"
  - "bulk_create_invoices reuses existing create_invoice() method via WP_REST_Request — no code duplication"
  - "N.v.t. cases (zero fee, not charged) excluded from uninvoicedCaseIds and show empty div instead of checkbox"
  - "Button text adapts: Maak factuur (single person) vs Maak facturen (multiple persons) based on selectedPersonCount"
  - "Person count shown in toolbar only when multiple persons selected"
metrics:
  duration: ~20 minutes
  completed: 2026-02-22
  tasks_completed: 2
  files_modified: 5
---

# Quick Task 116: Bulk Create Invoices from Selected Tuchtzaken

One-liner: Bulk invoice creation from /tuchtzaken list page — cases grouped by person into separate draft invoices with selection UI and navigation to facturen list on success.

## What Was Built

Users can now select multiple discipline cases on the /tuchtzaken list page and create invoices for all of them in one click. Cases for the same person are automatically grouped into a single invoice.

## Changes Made

### Backend: `includes/class-rest-invoices.php`

Added two new REST routes (registered before `/invoices` to avoid routing conflicts):

- `GET /rondo/v1/invoices/all-invoiced-cases` — returns all discipline case IDs that appear in any invoice (across all persons). Used to mark cases as already-invoiced on the list page.

- `POST /rondo/v1/invoices/bulk` — accepts `case_ids` array, loads each discipline case, groups by person ACF field, and calls the existing `create_invoice()` method for each person group. Returns `{ invoices: [...], errors: [...] }` — partial success is allowed (errors for one person don't block others).

### Frontend API: `src/api/client.js`

Added two methods to `prmApi`:
- `getAllInvoicedCaseIds()` — calls the new GET endpoint
- `bulkCreateInvoices(caseIds)` — calls the new POST endpoint

### Frontend Hooks: `src/hooks/useInvoices.js`

Added two new hooks:
- `useAllInvoicedCaseIds(options)` — query with `['invoiced-case-ids', 'all']` key, 30s stale time
- `useBulkCreateInvoices()` — mutation that invalidates `invoiced-case-ids`, `invoices`, and `invoices/person` query keys on success

### List Page: `src/pages/DisciplineCases/DisciplineCasesList.jsx`

- Added `useNavigate`, `useCurrentUser`, `useAllInvoicedCaseIds`, `useBulkCreateInvoices` imports
- Added `canCreateInvoice` derived from `can_access_fairplay && can_access_financieel` on current user
- Added `selectedCaseIds` state (Set) and `invoicedCaseIds` query (enabled only when `canCreateInvoice`)
- Added `handleBulkCreateInvoice`: calls mutateAsync, clears selection, navigates to `/financien/facturen` on success; shows alert on error
- `handleSeasonChange` now clears selection before changing season
- `handleRefresh` now also invalidates `invoiced-case-ids` queries
- Wired all new props to `<DisciplineCaseTable>`

### Table Component: `src/components/DisciplineCaseTable.jsx`

- `uninvoicedCaseIds` now excludes N.v.t. cases (`isDoorbelastNVT()` check) in addition to already-invoiced
- Added `selectedPersonCount` useMemo to count distinct persons among selected cases
- Selection toolbar: shows person count `(N personen)` suffix when multiple persons selected
- Button text: `Maak facturen` when `selectedPersonCount > 1`, otherwise `Maak factuur`
- Checkbox column: N.v.t. cases now render an empty `<div>` (no checkbox, no icon) — invoiced cases still show FileText icon

## Verification

- `npm run lint` passes with 0 warnings
- `npm run build` produces a clean build (16.3s)
- Deployed to production: https://rondo.svawc.nl/

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- [x] `includes/class-rest-invoices.php` modified — confirmed
- [x] `src/api/client.js` modified — confirmed
- [x] `src/hooks/useInvoices.js` modified — confirmed
- [x] `src/pages/DisciplineCases/DisciplineCasesList.jsx` modified — confirmed
- [x] `src/components/DisciplineCaseTable.jsx` modified — confirmed
- [x] Commit c828aaea exists — feat(quick-116): add bulk invoice creation endpoints and hooks
- [x] Commit 2ce6d17e exists — feat(quick-116): wire bulk invoice selection UI on tuchtzaken list page
