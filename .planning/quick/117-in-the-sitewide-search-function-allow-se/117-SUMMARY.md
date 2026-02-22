---
phase: quick-117
plan: "01"
subsystem: search
tags: [search, invoices, finance, global-search]
dependency_graph:
  requires: [rondo_invoice post type, financieel capability, ACF invoice fields]
  provides: [invoice search results in global search modal]
  affects: [includes/class-rest-api.php, src/hooks/useDashboard.js, src/components/layout/Layout.jsx]
tech_stack:
  added: []
  patterns: [capability-gated search, lightweight result projection, meta_query LIKE search]
key_files:
  created: []
  modified:
    - includes/class-rest-api.php
    - src/hooks/useDashboard.js
    - src/components/layout/Layout.jsx
decisions:
  - Build lightweight invoice result inline in class-rest-api.php rather than reusing format_invoice() from class-rest-invoices.php (different class context, search needs less data)
  - Gate invoice results on financieel capability in backend only — frontend renders whatever backend returns, no duplicate capability check needed
  - Sort invoice results by invoice_number descending using strcmp for most-recent-first ordering
  - Map ACF status values (draft/sent/paid/overdue) to Dutch labels (Concept/Verzonden/Betaald/Te laat) with color-coded badges in frontend
metrics:
  duration: "~15 minutes"
  completed: "2026-02-22"
  tasks_completed: 2
  files_modified: 3
---

# Quick Task 117: Invoice search in global search modal

**One-liner:** Invoice number search in global search modal using meta_query LIKE on rondo_invoice with financieel capability gate and Dutch status badges.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add invoice search to backend global_search endpoint | 30ed70f5 | includes/class-rest-api.php |
| 2 | Render invoice search results in frontend search modal | ca68558c | src/hooks/useDashboard.js, src/components/layout/Layout.jsx |

## What Was Built

Finance users can now type invoice numbers (e.g. "2026T", "2026C001") in the global search modal (Cmd+K) and see matching invoices in a "Facturen" section. Each result shows the invoice number, the linked person's name, and a color-coded Dutch status badge. Clicking navigates directly to `/financien/facturen/{id}`. Non-finance users see no invoice results since the backend gates on `current_user_can('financieel')`.

### Backend Changes (`includes/class-rest-api.php`)

- Added `invoices: []` key to the initial `$results` array
- Added invoice search block after teams search, gated on `current_user_can('financieel')`
- Queries `rondo_invoice` posts across all statuses (publish, rondo_sent, rondo_paid, rondo_overdue, draft) via `meta_query LIKE` on `invoice_number`
- Builds lightweight result array inline: `id`, `invoice_number`, `person_name` (from first_name + infix + last_name ACF fields), `total_amount`, `status`
- Sorts results by `invoice_number` descending before returning

### Frontend Changes

**`src/hooks/useDashboard.js`:**
- Updated `useSearch` default empty return from `{ people: [], teams: [] }` to `{ people: [], teams: [], invoices: [] }`

**`src/components/layout/Layout.jsx`:**
- Updated `safeResults` fallback to include `invoices: []`
- Added invoices to `allResults` mapped with `type: 'invoice'`
- Added `invoice` case to `handleResultClick` navigating to `/financien/facturen/${id}`
- Added "Facturen" section after teams in SearchModal with:
  - Receipt icon in rounded square (matching teams pattern)
  - Invoice number (bold) with person name as secondary text
  - Status badge with Dutch labels: Concept (gray), Verzonden (blue), Betaald (green), Te laat (red)
  - Correct `globalIndex` accounting for people + teams before invoice items
  - Keyboard navigation support via the existing allResults array

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check

- [x] `includes/class-rest-api.php` modified with invoice search block
- [x] `src/hooks/useDashboard.js` updated with invoices key
- [x] `src/components/layout/Layout.jsx` updated with Facturen section
- [x] Commit `30ed70f5` exists (Task 1)
- [x] Commit `ca68558c` exists (Task 2)
- [x] `npm run build` passes
- [x] `npm run lint` passes
- [x] Deployed to production

## Self-Check: PASSED
