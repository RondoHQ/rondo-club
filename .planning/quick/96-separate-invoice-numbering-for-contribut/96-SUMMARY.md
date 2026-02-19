---
phase: quick-96
plan: "01"
subsystem: finance/invoice-numbering
tags:
  - invoicing
  - membership
  - numbering
dependency_graph:
  requires:
    - class-invoice-numbering.php
    - class-bulk-invoice-creator.php
  provides:
    - Type-aware invoice number generation (T=discipline, C=membership)
  affects:
    - class-rest-invoices.php (no change required — uses default 'discipline')
tech_stack:
  added: []
  patterns:
    - Optional type parameter with backward-compatible default
key_files:
  modified:
    - includes/class-invoice-numbering.php
    - includes/class-bulk-invoice-creator.php
decisions:
  - C letter chosen for membership (Contributie) to distinguish from T (Tucht/discipline)
  - Default parameter 'discipline' preserves backward compatibility for all existing callers
  - Sequences are independent — LIKE query scopes to year+letter prefix, so 2026C001 and 2026T001 coexist
  - is_valid() regex widened to [TC] — both prefixes now pass validation
metrics:
  duration: 3min
  completed: "2026-02-19"
  tasks: 1
  files: 2
---

# Quick Task 96: Separate Invoice Numbering for Contributie

Membership invoices now use C-prefixed numbers (2026C001) independent from discipline invoice T-prefixed numbers (2026T001), with each sequence maintained separately.

## What Was Done

Updated `InvoiceNumbering::generate_next()` to accept an optional `$type` parameter (default `'discipline'`) that determines the prefix letter: `T` for discipline, `C` for membership. The WP_Query inside the method uses the type-specific prefix for its LIKE comparison, so sequences are fully independent. Updated `BulkInvoiceCreator` to call `generate_next('membership')` and widened the `is_valid()` regex from `/^\d{4}T\d{3,}$/` to `/^\d{4}[TC]\d{3,}$/`.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add type parameter to InvoiceNumbering and update caller | b2e78ca6 | includes/class-invoice-numbering.php, includes/class-bulk-invoice-creator.php |

## Verification

- `grep "string \$type" includes/class-invoice-numbering.php` — shows `string $type = 'discipline'`
- `grep "'membership'" includes/class-bulk-invoice-creator.php` — shows `generate_next( 'membership' )`
- `grep "\[TC\]" includes/class-invoice-numbering.php` — shows `/^\d{4}[TC]\d{3,}$/`
- `npm run build` — clean build, no regressions

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- FOUND: includes/class-invoice-numbering.php
- FOUND: includes/class-bulk-invoice-creator.php
- FOUND: commit b2e78ca6
