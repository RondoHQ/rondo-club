---
phase: quick-100
plan: 01
subsystem: finance
tags: [pdf, settings, membership, payment-clause]
dependency_graph:
  requires: [class-finance-config.php, class-rest-api.php, class-invoice-pdf-generator.php]
  provides: [membership_payment_clause option, separate betalingsclausule fields in UI]
  affects: [membership invoice PDF, FinanceSettings page]
tech_stack:
  added: []
  patterns: [WordPress Options API, positional PHP parameter appending]
key_files:
  created: []
  modified:
    - includes/class-finance-config.php
    - includes/class-rest-api.php
    - includes/class-invoice-pdf-generator.php
    - src/pages/Finance/FinanceSettings.jsx
decisions:
  - Used existing .payment-clause CSS class for membership clause rendering — no new CSS needed
  - Added $membership_payment_clause as last positional parameter to build_html() — keeps signature backward compatible
  - Existing payment_clause (discipline) untouched — new membership_payment_clause is fully independent
metrics:
  duration: 8min
  completed: 2026-02-19T19:24:27Z
  tasks_completed: 2
  files_changed: 4
---

# Quick Task 100: Add Separate Betalingsclausule for Contributie — Summary

**One-liner:** Separate `membership_payment_clause` WP option stored, exposed via REST, rendered in membership PDF payment section, and configurable in FinanceSettings UI with renamed existing label.

## What Was Done

Added a distinct payment clause setting for contributie (membership) invoices, separate from the existing tuchtzaken (discipline) clause. Previously only discipline invoices had a configurable payment clause text in their PDF. Membership invoices now have their own clause shown below the "Je ontvangt per e-mail een betaallink" paragraph.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add membership_payment_clause to FinanceConfig and REST API | f5b95f94 | class-finance-config.php, class-rest-api.php |
| 2 | Render membership_payment_clause in PDF and update FinanceSettings UI | 3a560c97 | class-invoice-pdf-generator.php, FinanceSettings.jsx |

## Key Changes

**class-finance-config.php:**
- `OPTION_MEMBERSHIP_PAYMENT_CLAUSE` constant → `rondo_finance_membership_payment_clause`
- Default `''` in DEFAULTS array
- `get_membership_payment_clause()` getter
- `get_all_settings()` includes `membership_payment_clause`
- `get_setting()` switch has `case 'membership_payment_clause'`
- `update_settings()` handler with `sanitize_textarea_field`

**class-rest-api.php:**
- `membership_payment_clause` REST arg registered with `sanitize_textarea_field`

**class-invoice-pdf-generator.php:**
- `$membership_payment_clause = $finance_config->get_membership_payment_clause()` in `generate()`
- Passed as last arg to `build_html()`
- `$membership_payment_clause = ''` added to `build_html()` signature
- Rendered as `<div class="payment-clause">` inside membership payment `<td>` when non-empty

**FinanceSettings.jsx:**
- `membership_payment_clause: ''` in useState
- Loaded in useEffect from `settings.membership_payment_clause`
- Included in handleSubmit payload
- Existing "Betalingsclausule" renamed to "Betalingsclausule tuchtzaken" with updated placeholder
- New "Betalingsclausule contributie" textarea added below existing one

## Deviations from Plan

None — plan executed exactly as written.

## Deployment

Deployed to production (https://rondo.svawc.nl/) after both tasks.

## Self-Check: PASSED

- `includes/class-finance-config.php` — FOUND (8 occurrences of membership_payment_clause)
- `includes/class-rest-api.php` — FOUND (membership_payment_clause REST arg)
- `includes/class-invoice-pdf-generator.php` — FOUND (fetch, call, signature, render)
- `src/pages/Finance/FinanceSettings.jsx` — FOUND (state, load, save, 2 UI fields)
- Commits f5b95f94 and 3a560c97 — FOUND in git log
- Build passed with no errors
