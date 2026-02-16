---
phase: 184-invoice-management-ui
plan: 01
subsystem: invoice-management
tags: [backend, frontend, infrastructure, rest-api, react-hooks, routing]
dependency_graph:
  requires:
    - phase: 183
      plan: 01
      reason: "Uses InvoiceEmailSender service for resend functionality"
  provides:
    - "Resend invoice REST endpoint at /rondo/v1/invoices/{id}/resend"
    - "Complete set of invoice React hooks (list, detail, send, update status, resend)"
    - "Router infrastructure for Facturen pages"
    - "Enabled Facturen navigation item"
  affects:
    - subsystem: invoice-email
      nature: "Reuses InvoiceEmailSender for resend operation"
tech_stack:
  added:
    - "5 React Query hooks for invoice operations"
    - "2 lazy-loaded React Router routes with FinancieelRoute guard"
  patterns:
    - "REST endpoint validation (status checks before resend)"
    - "Query invalidation on mutations (cache consistency)"
    - "Capability-based route guards (FinancieelRoute)"
key_files:
  created:
    - src/pages/Finance/Facturen.jsx
    - src/pages/Finance/FactuurDetail.jsx
  modified:
    - includes/class-rest-invoices.php
    - src/hooks/useInvoices.js
    - src/api/client.js
    - src/router.jsx
    - src/components/layout/Layout.jsx
decisions:
  - decision: "Resend endpoint only allows sent/overdue invoices (400 error for others)"
    rationale: "Draft invoices should use send endpoint; paid invoices don't need resending"
    alternatives: ["Allow all statuses", "Silently skip invalid statuses"]
    chosen: "Explicit validation with error message"
  - decision: "Created placeholder page components for build compilation"
    rationale: "Vite requires imports to exist at build time; full UI comes in Plan 02"
    alternatives: ["Comment out routes until Plan 02", "Use dynamic imports with error boundaries"]
    chosen: "Minimal placeholder components"
  - decision: "useResendInvoice only invalidates ['invoice'] query (not ['invoices'])"
    rationale: "Resending doesn't change invoice list/status; only affects single invoice metadata"
    alternatives: ["Invalidate all invoice queries", "No invalidation"]
    chosen: "Targeted invalidation for performance"
metrics:
  duration: 162
  tasks_completed: 2
  files_modified: 7
  commits: 2
  lines_added: 196
  lines_removed: 1
  completed_at: "2026-02-16T11:41:02Z"
---

# Phase 184 Plan 01: Invoice Management Infrastructure Summary

**One-liner:** REST resend endpoint, React hooks for invoice CRUD operations, router infrastructure, and enabled Facturen navigation.

## What Was Built

Set up the foundational infrastructure for the Facturen (Invoices) page:

1. **Backend resend endpoint** (`POST /rondo/v1/invoices/{id}/resend`)
   - Validates invoice exists and is rondo_invoice type (404 if not)
   - Checks status is `rondo_sent` or `rondo_overdue` (400 error with Dutch message if not)
   - Reuses `InvoiceEmailSender::send()` service to re-deliver email
   - Returns formatted invoice detail on success

2. **Frontend API client method**
   - Added `resendInvoice(id)` to `prmApi` object in `src/api/client.js`
   - Placed after `sendInvoice` for logical ordering

3. **React Query hooks** (5 new hooks in `src/hooks/useInvoices.js`)
   - `useInvoices(params, options)` — List invoices with optional filtering (status, person_id)
   - `useInvoice(id, options)` — Fetch single invoice detail
   - `useSendInvoice()` — Send draft invoice mutation (invalidates all invoice queries)
   - `useUpdateInvoiceStatus()` — Update invoice status mutation (invalidates all invoice queries)
   - `useResendInvoice()` — Resend invoice email mutation (invalidates only single invoice query)
   - All queries use 30-second staleTime for consistency with existing invoice hooks

4. **Router configuration**
   - Registered `/financien/facturen` route → `<Facturen />` page (protected by FinancieelRoute)
   - Registered `/financien/facturen/:id` route → `<FactuurDetail />` page (protected by FinancieelRoute)
   - Created placeholder page components with basic structure (full UI in Plan 02)

5. **Navigation**
   - Removed `disabled: true` from Facturen nav item in Layout.jsx
   - Item now clickable for users with `financieel` capability

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking Issue] Created placeholder page components to enable build**
- **Found during:** Task 2, during `npm run build`
- **Issue:** Vite build failed with ENOENT error — lazy imports in router.jsx referenced non-existent Facturen.jsx and FactuurDetail.jsx files
- **Fix:** Created minimal placeholder components with basic structure ("Page under construction" message)
- **Files created:** `src/pages/Finance/Facturen.jsx`, `src/pages/Finance/FactuurDetail.jsx`
- **Commit:** 41243630 (included in Task 2 commit)

**Rationale:** Plan stated "will warn about missing page components" but the build actually *failed* rather than warned. Creating placeholders allows the build to complete and the navigation to function, while Plan 02 will replace these with full implementations. This is a blocking issue (Rule 3) because the build must pass for deployment.

## Verification Results

All verification checks passed:

1. ✅ PHP syntax: `php -l includes/class-rest-invoices.php` — No syntax errors
2. ✅ Frontend build: `npm run build` — Compiled successfully in 16.66s (90 entries, 3100.61 KiB)
3. ✅ Resend route registered in class-rest-invoices.php at line 192-209
4. ✅ All 5 new hooks present in useInvoices.js (lines 64-147)
5. ✅ Routes for facturen and facturen/:id in router.jsx (lines 27-28, 209-225)
6. ✅ Facturen nav item no longer has `disabled: true` in Layout.jsx (line 50)
7. ✅ resendInvoice method in api/client.js (line 307)

## Task Breakdown

**Task 1: Add resend invoice endpoint and API client method** (Duration: ~81s)
- Modified `includes/class-rest-invoices.php` — Added route registration and `resend_invoice()` method
- Modified `src/api/client.js` — Added `resendInvoice: (id) => api.post(\`/rondo/v1/invoices/${id}/resend\`)`
- Verified PHP syntax and frontend build compilation
- Commit: 9defc8ea

**Task 2: Add invoice hooks, routes, and enable navigation** (Duration: ~81s)
- Modified `src/hooks/useInvoices.js` — Added 5 React Query hooks (useInvoices, useInvoice, useSendInvoice, useUpdateInvoiceStatus, useResendInvoice)
- Modified `src/router.jsx` — Added lazy imports and routes for Facturen and FactuurDetail pages
- Modified `src/components/layout/Layout.jsx` — Removed disabled property from Facturen nav item
- Created placeholder page components (deviation — see above)
- Verified frontend build compilation
- Commit: 41243630

## Output Artifacts

**REST Endpoints:**
- `POST /rondo/v1/invoices/{id}/resend` — Resend invoice email (sent/overdue only)

**React Hooks:**
- `useInvoices(params, options)` — List/filter invoices
- `useInvoice(id, options)` — Fetch single invoice
- `useSendInvoice()` — Send draft invoice
- `useUpdateInvoiceStatus()` — Update invoice status
- `useResendInvoice()` — Resend invoice email

**Routes:**
- `/financien/facturen` — Invoice list page (capability-protected)
- `/financien/facturen/:id` — Invoice detail page (capability-protected)

**Navigation:**
- Facturen menu item enabled for users with `financieel` capability

## Success Criteria

✅ **Backend resend endpoint functional** — POST endpoint validates status and sends email via InvoiceEmailSender
✅ **All frontend hooks available** — 5 hooks exported from useInvoices.js with proper query/mutation setup
✅ **Routes registered** — Both list and detail routes configured with FinancieelRoute guard
✅ **Facturen navigation enabled** — Nav item clickable, no longer grayed out
✅ **Infrastructure ready for Plan 02** — Placeholder pages allow navigation, await full UI implementation

## Self-Check: PASSED

**Files verified to exist:**
```bash
✓ FOUND: includes/class-rest-invoices.php
✓ FOUND: src/hooks/useInvoices.js
✓ FOUND: src/api/client.js
✓ FOUND: src/router.jsx
✓ FOUND: src/components/layout/Layout.jsx
✓ FOUND: src/pages/Finance/Facturen.jsx
✓ FOUND: src/pages/Finance/FactuurDetail.jsx
```

**Commits verified:**
```bash
✓ FOUND: 9defc8ea (Task 1 - resend endpoint and API method)
✓ FOUND: 41243630 (Task 2 - hooks, routes, navigation)
```

All claimed files exist on disk and all commits exist in git history.

## Next Steps

Plan 02 will build the actual Facturen UI pages on top of this infrastructure:
- Replace placeholder Facturen.jsx with full invoice list table
- Replace placeholder FactuurDetail.jsx with invoice detail view
- Implement filtering, status management, and bulk operations
- Add UI for resending invoices
