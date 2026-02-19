---
phase: quick-87
plan: 01
subsystem: FinancesCard / invoice creation UX
tags: [navigation, ux, invoicing, react-router]
dependency_graph:
  requires: []
  provides: [post-creation invoice navigation]
  affects: [src/components/FinancesCard.jsx]
tech_stack:
  added: []
  patterns: [useNavigate for post-mutation navigation, .then(res => res.data) to extract axios payload]
key_files:
  created: []
  modified:
    - src/components/FinancesCard.jsx
key_decisions:
  - Extract res.data from axios response in mutationFn so TanStack Query onSuccess receives API payload directly
  - Navigate after invalidateQueries so cache refresh and navigation happen atomically
metrics:
  duration: 4min
  completed: 2026-02-19T14:55:36Z
  tasks: 1
  files: 1
---

# Quick Task 87: Redirect to Invoice Page After Creating Summary

## One-liner

Navigate to `/financien/facturen/{invoice_id}` immediately after "Maak factuur" succeeds using useNavigate in the onSuccess callback.

## What Was Done

### Task 1: Navigate to invoice detail after creation

Updated `src/components/FinancesCard.jsx` with three targeted changes:

1. Added `useNavigate` to the existing `react-router-dom` import.
2. Added `const navigate = useNavigate();` at the top of the `FinancesCard` component.
3. Updated the `createInvoice` mutation:
   - Changed `mutationFn` to extract `.data` from the axios response via `.then(res => res.data)` so TanStack Query's `onSuccess` receives the API payload (not the axios wrapper).
   - Updated `onSuccess` to accept `data` and call `navigate(`/financien/facturen/${data.invoice_id}`)` after invalidating the invoice query cache.

The API's `createMembershipInvoice` response already returns `invoice_id` in its payload — no backend changes were needed.

## Verification

- `npm run build`: passed (17s, 0 errors)
- `npm run lint`: passed (0 warnings)
- Deployed to production: https://rondo.svawc.nl/

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- File modified: `src/components/FinancesCard.jsx` — confirmed present
- Commit be3d9f86 — confirmed in git log
- useNavigate import present on line 2
- navigate() call in onSuccess on line 64
