---
id: T01
parent: S01
milestone: M006
provides:
  - Manual-paid audit meta stored on paid transition
  - Two new fields in REST invoice detail response
key_files:
  - includes/class-rest-invoices.php
key_decisions: []
patterns_established:
  - Audit trail meta stored BEFORE artifact cleanup in status transitions
observability_surfaces:
  - Post meta `_manually_marked_paid_at` and `_manually_marked_paid_by` on invoice posts
  - REST fields `manually_marked_paid_at` (string|null) and `manually_marked_paid_by` ({id,name}|null) in detail response
duration: 5m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T01: Store manual-paid audit meta and return in REST response

**Added `_manually_marked_paid_at` and `_manually_marked_paid_by` post meta on manual paid transition, and surfaced both in the REST invoice detail response.**

## What Happened

Two changes to `includes/class-rest-invoices.php`:

1. **`update_invoice_status()`** (line ~1044): Inside the `$status === 'paid'` block, added two `update_post_meta` calls BEFORE the existing artifact cleanup (payment link removal, QR clearing, etc.):
   - `_manually_marked_paid_at` → `current_time('mysql')` (Y-m-d H:i:s)
   - `_manually_marked_paid_by` → `get_current_user_id()` (only stored when > 0)

2. **`format_invoice_detail()`** (line ~2478): After the `mollie_consumer_account` field, added two new fields:
   - `manually_marked_paid_at` → string or null
   - `manually_marked_paid_by` → `get_user_summary_by_id()` returning `{id, name}` or null

No changes to `class-mollie-webhook.php` — the Mollie webhook uses its own separate code path for marking payments as paid.

## Verification

- **Grep confirmation**: Both `_manually_marked_paid_at` and `_manually_marked_paid_by` appear in exactly two locations each — `update_invoice_status()` (write) and `format_invoice_detail()` (read)
- **`npm run lint`**: Passed with zero warnings
- **`npm run build`**: Passed — 5960 modules transformed, 109 precache entries

### Slice-level checks (partial — T01 is intermediate):
- ✅ `npm run build` — passes
- ✅ `npm run lint` — zero warnings
- ⏳ Deploy + manual test on production — deferred to T02
- ⏳ Mollie-paid invoice regression check — deferred to T02

## Diagnostics

- **Inspect stored meta**: `wp post meta get {invoice_id} _manually_marked_paid_at` on production
- **REST response**: `GET /wp/v2/rondo_invoice/{id}` now includes `manually_marked_paid_at` and `manually_marked_paid_by`
- **Null distinction**: Fields return `null` when meta is not set, distinguishing "never manually marked" from "marked"

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `includes/class-rest-invoices.php` — Added audit meta storage in `update_invoice_status()` and two new fields in `format_invoice_detail()`
