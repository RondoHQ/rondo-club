---
phase: 188-mollie-webhook-automatic-status-update
verified: 2026-02-17T22:40:31Z
status: passed
score: 5/5 must-haves verified
re_verification: false
human_verification:
  - test: "Send curl POST to production webhook endpoint without authentication"
    expected: "HTTP 200 with {\"ok\":true}"
    why_human: "SSH to production is currently unreachable; cannot run curl test"
  - test: "Send curl POST with missing id parameter"
    expected: "HTTP 200 with {\"ok\":true}"
    why_human: "SSH to production is currently unreachable"
  - test: "Send curl POST with unknown Mollie payment ID (e.g. tr_nonexistent)"
    expected: "HTTP 200 with {\"ok\":true} and error logged server-side"
    why_human: "SSH to production is currently unreachable; also requires live Mollie API key"
---

# Phase 188: MollieWebhook Verification Report

**Phase Goal:** A dedicated public REST endpoint receives Mollie webhook events and idempotently transitions the matching invoice to `rondo_paid` when payment is confirmed.
**Verified:** 2026-02-17T22:40:31Z
**Status:** passed (code-level) — 3 runtime checks deferred to human (production SSH unreachable)
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | POST /wp-json/rondo/v1/mollie/webhook returns HTTP 200 without WordPress authentication | VERIFIED | `register_rest_route` in `register_routes()` uses `'permission_callback' => '__return_true'`; every code path in `handle_webhook()` returns `rest_ensure_response(['ok' => true])` |
| 2 | Webhook handler re-fetches payment from Mollie API using the received ID | VERIFIED | Lines 74–79: `new MollieClient(); $mollie_client->get()->payments->get($payment_id)` — POST body is only used for the ID, status is verified by re-fetching |
| 3 | When Mollie confirms payment is paid, the matching invoice transitions to rondo_paid post status | VERIFIED | Lines 83–129: `$payment->isPaid()` guard, `WP_Query` lookup by `_mollie_payment_id` meta, `wp_update_post(['post_status' => 'rondo_paid'])` + `update_field('status', 'paid', $invoice->ID)` |
| 4 | Sending the same webhook payload twice does not cause double-updates or errors | VERIFIED | Line 113: `if ('rondo_paid' === $invoice->post_status) { return rest_ensure_response(['ok' => true]); }` — early exit when already paid |
| 5 | Errors (missing ID, API failure, invoice not found) are logged but handler still returns 200 | VERIFIED | Line 69: missing ID returns 200; lines 77–78: ApiException caught, logs error, returns 200; lines 106–107: no invoice found, logs error, returns 200 |

**Score:** 5/5 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-mollie-webhook.php` | MollieWebhook class with public REST endpoint and webhook handler | VERIFIED | File exists, 132 lines, `class MollieWebhook` in `Rondo\Finance` namespace, `php -l` passes |
| `functions.php` | Class loading for MollieWebhook in REST context | VERIFIED | Line 79: `use Rondo\Finance\MollieWebhook;`; line 380: `new MollieWebhook();` inside `$is_rest` block; `php -l` passes |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `includes/class-mollie-webhook.php` | `includes/class-mollie-client.php` | `new MollieClient()->get()->payments->get(...)` | WIRED | Line 74: `$mollie_client = new MollieClient();` — MollieClient and MollieWebhook share the `Rondo\Finance` namespace, no import needed; line 75: `$mollie_client->get()->payments->get($payment_id)` — correct API call chain |
| `includes/class-mollie-webhook.php` | `includes/class-mollie-payment.php` | `_mollie_payment_id` post meta written by MolliePayment | WIRED | MolliePayment stores `_mollie_payment_id` via `update_post_meta($invoice_id, '_mollie_payment_id', $payment->id)` (class-mollie-payment.php line 118); MollieWebhook queries for `_mollie_payment_id` meta key at lines 96–98 — same key, correct linkage |
| `functions.php` | `includes/class-mollie-webhook.php` | `new MollieWebhook()` in `$is_rest` block | WIRED | Line 79: `use Rondo\Finance\MollieWebhook;`; line 380: `new MollieWebhook();` is inside the `if ($is_rest)` block (lines 364–381), consistent with all other REST-domain class registrations |

### Requirements Coverage

No REQUIREMENTS.md rows mapped to this phase — not applicable.

### Anti-Patterns Found

| File | Pattern | Severity | Result |
|------|---------|----------|--------|
| `includes/class-mollie-webhook.php` | TODO/FIXME/placeholder | Scanned | None found |
| `includes/class-mollie-webhook.php` | Empty return values (return null, return {}) | Scanned | None found — all returns are `rest_ensure_response(['ok' => true])` |
| `includes/class-mollie-webhook.php` | Handler-only-prevents-default stub | Scanned | None — handler performs real API re-fetch, DB query, and status transition |

### Human Verification Required

#### 1. Endpoint public accessibility (WHKT-01)

**Test:** `curl -s -o /dev/null -w "%{http_code}" -X POST https://rondo.svawc.nl/wp-json/rondo/v1/mollie/webhook -d "id=test"`
**Expected:** HTTP 200 (not 401, 403, or 404)
**Why human:** SSH to production server is currently unreachable; production has not been deployed yet

#### 2. Missing payment ID graceful handling (WHKT-05)

**Test:** `curl -s -X POST https://rondo.svawc.nl/wp-json/rondo/v1/mollie/webhook`
**Expected:** HTTP 200 with response body `{"ok":true}`
**Why human:** Requires production deployment and reachable SSH

#### 3. Unknown payment ID handled gracefully (WHKT-02 + WHKT-05)

**Test:** `curl -s -X POST https://rondo.svawc.nl/wp-json/rondo/v1/mollie/webhook -d "id=tr_nonexistent"`
**Expected:** HTTP 200 with `{"ok":true}` — ApiException is caught and logged server-side
**Why human:** Requires production deployment AND a live Mollie API key configured in FinanceConfig options

### Additional Findings

**Correct `isPaid()` method used:** Line 83 calls `$payment->isPaid()` not `$payment->status === 'paid'`. This matches Pitfall 1 guidance from the research — `isPaid()` checks `paidAt` which handles edge cases in some payment methods.

**Correct ACF field name:** Line 126 calls `update_field('status', 'paid', $invoice->ID)` not `update_field('payment_status', ...)`. This matches the existing `RestInvoices::update_invoice_status()` pattern (line 489 in `class-rest-invoices.php`) and the ACF field defined in `acf-json/group_invoice_fields.json`.

**`post_status => 'any'` correctly applied:** Line 93 in the `WP_Query` call includes `'post_status' => 'any'` to include custom statuses `rondo_sent`, `rondo_overdue`, `rondo_draft` which are excluded from WordPress default queries.

**`rondo_paid` post status is registered:** Confirmed in `class-post-types.php` line 416–426 via `register_post_status('rondo_paid', [...])`.

**Commits verified:** Both task commits exist in git history:
- `7fc77cc4` — `feat(188-01): create MollieWebhook class with public REST endpoint`
- `e8afa09e` — `feat(188-01): register MollieWebhook in functions.php`

**Deployment note:** SUMMARY documents that production deploy failed due to SSH timeout on port 18765. The code is committed and pushed. Deploy must be completed before production verification can be performed.

### Gaps Summary

No code-level gaps found. All five observable truths are satisfied by the implementation. All artifacts exist and are substantive (not stubs). All key links are wired correctly.

The three human verification items are blocked only by the currently unreachable production SSH — they are not code defects. Once SSH is accessible:
1. Run `bin/deploy.sh` to deploy the committed code
2. Run the three curl tests listed above

---

_Verified: 2026-02-17T22:40:31Z_
_Verifier: Claude (gsd-verifier)_
