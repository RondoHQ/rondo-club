---
phase: 187-molliepayment-payment-link-creation
verified: 2026-02-17T21:50:20Z
status: passed
score: 6/6 must-haves verified
re_verification: false
---

# Phase 187: MolliePayment — Payment Link Creation Verification Report

**Phase Goal:** MolliePayment class creates a Mollie payment via the Payments API, stores the checkout URL in the invoice's ACF payment_link field, and stores the Mollie payment ID for later webhook lookup.
**Verified:** 2026-02-17T21:50:20Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `MolliePayment::create_payment_link($invoice_id)` calls `$mollie->payments->create()` (Payments API, not Payment Links API) | VERIFIED | Line 95: `$payment = $mollie->payments->create( $payload );` |
| 2 | Amount is formatted as string with exactly 2 decimal places using `number_format()` | VERIFIED | Line 73: `number_format( (float) $total_amount, 2, '.', '' )` — 4-argument form, locale-safe |
| 3 | Invoice `payment_link` ACF field is updated with the Mollie checkout URL | VERIFIED | Line 117: `update_field( 'payment_link', $checkout_url, $invoice_id );` |
| 4 | `_mollie_payment_id` post meta is stored on the invoice for webhook lookup | VERIFIED | Line 118: `update_post_meta( $invoice_id, '_mollie_payment_id', $payment->id );` |
| 5 | If `_mollie_payment_id` already exists and `payment_link` has a URL, the existing URL is returned without a new API call | VERIFIED | Lines 48-55: dual-check — reads both meta and ACF field; returns existing URL only if BOTH are non-empty; falls through if URL is missing |
| 6 | `webhookUrl` is omitted from payload when site URL contains `localhost` or `.local` | VERIFIED | Line 87: `false === strpos( $site_url, 'localhost' ) && false === strpos( $site_url, '.local' )` — both checked before adding `webhookUrl` |

**Score:** 6/6 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-mollie-payment.php` | Mollie payment service class containing `class MolliePayment` | VERIFIED | 124 lines, substantive implementation, PHP syntax valid |
| `functions.php` | Contains `use Rondo\Finance\MolliePayment` import | VERIFIED | Line 78: import present; `new MolliePayment` NOT instantiated in `rondo_init()` (correct) |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `class-mollie-payment.php` | `class-mollie-client.php` | `new MollieClient()` then `->get()->payments->create()` | WIRED | Line 93-95: constructs client, calls `->get()`, calls `->payments->create($payload)` |
| `class-mollie-payment.php` | ACF `payment_link` field | `update_field('payment_link', $checkout_url, $invoice_id)` | WIRED | Line 117: exact pattern from plan |
| `class-mollie-payment.php` | `_mollie_payment_id` post meta | `update_post_meta($invoice_id, '_mollie_payment_id', $payment->id)` | WIRED | Line 118: exact pattern from plan |

### Requirements Coverage

No REQUIREMENTS.md entries mapped to phase 187 — not applicable.

### Anti-Patterns Found

None. No TODO/FIXME/placeholder comments. No empty implementations. No stub return values. Full implementation present.

### Human Verification Required

**1. Live Mollie API call (with test API key)**

**Test:** Configure a Mollie test API key in Finance Settings, create an invoice with a total amount, call `(new MolliePayment())->create_payment_link($invoice_id)` via WP-CLI or a test endpoint.
**Expected:** Returns a `https://pay.mollie.com/...` checkout URL; `_mollie_payment_id` post meta is set to a `tr_xxx` value; ACF `payment_link` field on the invoice contains the checkout URL.
**Why human:** Cannot call live Mollie API programmatically during verification — requires actual credentials and network access.

**2. Idempotency behavior**

**Test:** Call `create_payment_link()` twice on the same invoice. The second call should return the stored URL without making a second Mollie API call.
**Expected:** Same checkout URL returned; only one `tr_xxx` value in `_mollie_payment_id` meta.
**Why human:** Requires a live Mollie test account and the ability to check request logs.

**3. webhookUrl omission on localhost**

**Test:** Inspect a Mollie test payment created from a local development environment (`localhost` in site URL). Check the payment in the Mollie dashboard.
**Expected:** Payment has no webhook URL attached; Mollie accepts the payment without rejecting it.
**Why human:** Requires access to the Mollie dashboard to inspect payment details.

### Gaps Summary

No gaps. All observable truths verified, all artifacts substantive and wired, all key links confirmed. The class is a complete, non-stub implementation that directly mirrors the plan's specified logic.

**Commit verification:**
- `c887c811` — `feat(187-01): create MolliePayment service class` — present
- `f7cf5673` — `feat(187-01): add MolliePayment use import to functions.php` — present

---

_Verified: 2026-02-17T21:50:20Z_
_Verifier: Claude (gsd-verifier)_
