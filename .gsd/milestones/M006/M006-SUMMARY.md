---
id: M006
provides:
  - Audit trail for manually-paid invoices (timestamp + user) stored as post meta
  - Betaalgegevens card renders for both Mollie-paid and manually-paid invoices
  - REST API returns manually_marked_paid_at and manually_marked_paid_by fields
key_decisions:
  - "[M006-S01] Manual-paid meta stored BEFORE artifact cleanup in update_invoice_status() — ensures meta is written even if cleanup has side effects"
  - "[M006-S01] Betaalgegevens card prioritizes Mollie data — when both mollie_payment_method and manually_marked_paid_at exist, only Mollie section renders"
  - "[M006-S01] Use post meta (not ACF) for _manually_marked_paid_at and _manually_marked_paid_by — consistent with _invoice_sent_by_user_id pattern"
patterns_established:
  - Audit trail meta stored BEFORE artifact cleanup in status transitions
  - Betaalgegevens card uses conditional rendering with Mollie-first priority
observability_surfaces:
  - Post meta _manually_marked_paid_at and _manually_marked_paid_by on invoice posts
  - REST field manually_marked_paid_at (string|null) and manually_marked_paid_by ({id,name}|null) in detail response
  - Betaalgegevens card visible on any manually-paid invoice detail page
requirement_outcomes: []
duration: 17m
verification_result: passed
completed_at: 2026-03-12T15:19:11.476Z
---

# M006: Markeer als betaald

**When a user manually marks an invoice as paid, an audit trail is now visible in the Betaalgegevens card showing who marked it and when.**

## What Happened

Single-slice milestone (S01) with two tasks:

**T01 (Backend):** Added audit meta storage in `update_invoice_status()` inside `class-rest-invoices.php`. When status transitions to `paid`, two post meta values are written — `_manually_marked_paid_at` (via `current_time('mysql')`) and `_manually_marked_paid_by` (via `get_current_user_id()`). Meta is stored BEFORE the existing payment artifact cleanup. Both fields are surfaced in `format_invoice_detail()` using the existing `get_user_summary_by_id()` helper for user name resolution.

**T02 (Frontend + Deploy):** Widened the Betaalgegevens card render condition from `invoice.mollie_payment_method` to `(invoice.mollie_payment_method || invoice.manually_marked_paid_at)`. Added a manual-paid section showing "Handmatig gemarkeerd als betaald" with a UserCheck icon, formatted date/time, and user name. Mollie data takes priority — the manual section only renders when no Mollie payment method exists. Version bumped to 31.13.0, deployed to production.

## Cross-Slice Verification

**✅ Manually marking an invoice as paid stores a timestamp and the acting user:**
Verified in `update_invoice_status()` — `_manually_marked_paid_at` set via `current_time('mysql')` and `_manually_marked_paid_by` set via `get_current_user_id()` when > 0. Confirmed on production by marking invoice 2026F016 as paid and inspecting the result.

**✅ The Betaalgegevens card shows "Handmatig gemarkeerd als betaald" with date/time and user name:**
Verified on production — card appeared with "Handmatig gemarkeerd als betaald", "12 mrt. 2026 15:17", "Joost de Valk". All 4 browser assertions passed during T02 verification.

**✅ The card renders for manually-paid invoices even without Mollie payment data:**
Card condition is `(invoice.mollie_payment_method || invoice.manually_marked_paid_at)`. Manual section is gated on `!invoice.mollie_payment_method && invoice.manually_marked_paid_at`. Verified on production with a non-Mollie invoice.

**✅ Invoices paid via Mollie continue to show Mollie details as before:**
Code review confirms: Mollie section renders when `invoice.mollie_payment_method` is truthy, unchanged from prior behavior. The manual section only renders when Mollie data is absent. No Mollie-paid test invoice was available, but the conditional logic is additive — existing Mollie rendering code is untouched.

**✅ Deployed to production:**
Production server confirmed at v31.13.0 via SSH `style.css` inspection.

## Requirement Changes

No requirements changed status during this milestone.

## Forward Intelligence

### What the next milestone should know
- The Betaalgegevens card now has two rendering paths (Mollie and manual). Any future payment provider should follow this pattern — add a new conditional section rather than modifying existing ones.
- `get_user_summary_by_id()` in `class-rest-invoices.php` is a reusable helper for resolving user IDs to `{id, name}` summaries.

### What's fragile
- The Mollie webhook also transitions invoices to `paid` via a separate code path in `class-mollie-webhook.php`. If Mollie-paid invoices are ever transitioned through `update_invoice_status()` instead, they would incorrectly get manual-paid meta. Currently safe because webhooks use `wp_update_post()` directly.

### Authoritative diagnostics
- `wp post meta get {invoice_id} _manually_marked_paid_at` on production — if null, meta was not stored on transition
- REST response at `/wp/v2/rondo_invoice/{id}` — check `manually_marked_paid_at` and `manually_marked_paid_by` fields

### What assumptions changed
- No assumptions changed — implementation matched the plan exactly.

## Files Created/Modified

- `includes/class-rest-invoices.php` — Added audit meta storage in `update_invoice_status()` and two new fields in `format_invoice_detail()`
- `src/pages/Finance/FactuurDetail.jsx` — Widened Betaalgegevens card condition, added manual-paid section with UserCheck icon
- `style.css` — Version bumped to 31.13.0
- `package.json` — Version bumped to 31.13.0
- `CHANGELOG.md` — Added 31.13.0 entry
