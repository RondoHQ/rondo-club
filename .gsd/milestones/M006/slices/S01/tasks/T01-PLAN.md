---
estimated_steps: 4
estimated_files: 1
---

# T01: Store manual-paid audit meta and return in REST response

**Slice:** S01 — Manual paid audit trail + display
**Milestone:** M006

## Description

Add two post meta keys (`_manually_marked_paid_at`, `_manually_marked_paid_by`) when a user manually marks an invoice as paid via the REST API. Return these fields in the `format_invoice_detail()` response so the frontend can display them.

## Steps

1. In `update_invoice_status()` (around line 1047), inside the `$status === 'paid'` block, add two `update_post_meta` calls BEFORE the artifact cleanup lines:
   - `_manually_marked_paid_at` → `current_time('mysql')` (Y-m-d H:i:s format)
   - `_manually_marked_paid_by` → `get_current_user_id()` (only if > 0)
2. In `format_invoice_detail()` (around line 2470, after the existing `mollie_consumer_account` line), add two new fields to the `$invoice` array:
   - `manually_marked_paid_at` → `(string) get_post_meta($post->ID, '_manually_marked_paid_at', true) ?: null`
   - `manually_marked_paid_by` → `$this->get_user_summary_by_id((int) get_post_meta($post->ID, '_manually_marked_paid_by', true))`
3. Verify placement by grepping the file for both new meta key names
4. Run `npm run lint` to confirm no issues

## Must-Haves

- [ ] `_manually_marked_paid_at` stored with `current_time('mysql')` on paid transition
- [ ] `_manually_marked_paid_by` stored with `get_current_user_id()` on paid transition
- [ ] Meta stored BEFORE artifact cleanup (payment_link removal etc.)
- [ ] `manually_marked_paid_at` returned as string or null in `format_invoice_detail()`
- [ ] `manually_marked_paid_by` returned as `{id, name}` object or null via `get_user_summary_by_id()`
- [ ] Mollie webhook path (`class-mollie-webhook.php`) NOT modified — it uses separate code path

## Verification

- Grep `includes/class-rest-invoices.php` for `_manually_marked_paid_at` — should appear in both `update_invoice_status()` and `format_invoice_detail()`
- Grep `includes/class-rest-invoices.php` for `_manually_marked_paid_by` — same two locations
- `npm run lint` passes with zero warnings

## Observability Impact

- Signals added/changed: Two new post meta keys stored on manual paid transition; two new fields in REST detail response
- How a future agent inspects this: `wp post meta get {invoice_id} _manually_marked_paid_at` on production
- Failure state exposed: Fields return null when meta not set, distinguishing "never manually marked" from "marked"

## Inputs

- `includes/class-rest-invoices.php` — existing `update_invoice_status()` paid block (line ~1047) and `format_invoice_detail()` (line ~2470)
- Research confirmed `get_user_summary_by_id()` exists at line ~2097 as private method returning `{id, name}` or null

## Expected Output

- `includes/class-rest-invoices.php` — two `update_post_meta` calls added in paid transition block; two new fields in detail response array
