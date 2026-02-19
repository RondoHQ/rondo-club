---
status: fixing
trigger: "mollie-payment-link-expiry"
created: 2026-02-19T00:00:00Z
updated: 2026-02-19T00:01:00Z
---

## Current Focus

hypothesis: CONFIRMED — MolliePayment::create_payment_link() calls $mollie->payments->create() (POST /v2/payments) which creates a regular Mollie payment with ~15-minute expiry, not a payment link (POST /v2/payment-links) which stays valid long-term
test: Read class-mollie-payment.php line 101 — confirmed $mollie->payments->create($payload)
expecting: Fix by switching to $mollie->paymentLinks->create() and updating idempotency meta key and webhook handling
next_action: Fix class-mollie-payment.php to use paymentLinks API; update class-mollie-webhook.php to handle payment link webhooks; update idempotency meta key

## Symptoms

expected: Payment links should remain valid long enough for members to pay (days/weeks)
actual: Links expired too quickly — members can't pay because links are already expired
errors: No specific error messages — links just show as expired on Mollie
reproduction: Create a discipline case invoice, the generated payment link expires before the member can pay
started: Invoices created 2026-02-19, links already expired same day

## Eliminated

(none — root cause confirmed on first investigation)

## Evidence

- timestamp: 2026-02-19T00:00:00Z
  checked: includes/class-mollie-payment.php line 101
  found: $payment = $mollie->payments->create($payload) — creates POST /v2/payments, a regular Mollie payment
  implication: Regular Mollie payments expire in ~15 minutes (default for iDEAL and most methods). This is the wrong API for links sent via email.

- timestamp: 2026-02-19T00:00:30Z
  checked: Mollie SDK vendor/mollie/mollie-api-php/src/Traits/HasEndpoints.php
  found: SDK exposes $mollie->paymentLinks (PaymentLinkEndpointCollection) with create() method
  implication: The correct API is available in the existing SDK — $mollie->paymentLinks->create($payload)

- timestamp: 2026-02-19T00:00:45Z
  checked: vendor/mollie/mollie-api-php/src/Resources/PaymentLink.php
  found: PaymentLink has getCheckoutUrl() that reads _links->paymentLink->href (different from Payment's _links->checkout->href)
  implication: The fix requires using paymentLinks->create() and the returned object is a PaymentLink, same getCheckoutUrl() method works

- timestamp: 2026-02-19T00:01:00Z
  checked: includes/class-mollie-webhook.php — webhook handler
  found: Webhook receives payment IDs (tr_xxx), not payment link IDs (pl_xxx). The webhook from a payment link fires with the payment ID of the payment created when the customer clicks the link.
  implication: The webhook handler is unchanged — it still receives payment IDs from Mollie. The payment link fires a webhook per-payment. We still need to store the payment link ID (not payment ID) for idempotency/display but webhook lookup remains the same. The meta key _mollie_payment_id should be renamed to _mollie_payment_link_id to reflect this.

- timestamp: 2026-02-19T00:01:15Z
  checked: includes/class-rest-invoices.php — delete_post_meta calls
  found: Lines 602, 1024, 1124 delete _mollie_payment_id meta when resetting/voiding invoices
  implication: Must update these references too when renaming the meta key.

- timestamp: 2026-02-19T00:01:20Z
  checked: Two code paths for invoice payment:
  1. Discipline invoices: MolliePayment::create_payment_link() -> $mollie->payments->create() — BUG HERE
  2. Membership invoices: PublicPaymentPage -> InstallmentPaymentService::create_payment() -> $mollie->payments->create()
  found: Membership invoice path is fine — the /betaling/{token} link itself doesn't expire; Mollie payment is created fresh when member selects plan
  implication: Only MolliePayment (discipline invoices) needs to switch to paymentLinks API

## Resolution

root_cause: class-mollie-payment.php calls $mollie->payments->create() (POST /v2/payments) which creates a regular Mollie payment with ~15-minute expiry. For discipline case invoices sent via email, members cannot pay before the payment expires. The correct API is $mollie->paymentLinks->create() (POST /v2/payment-links) which creates a persistent link that remains valid until paid or archived.

fix: Switch MolliePayment::create_payment_link() to use $mollie->paymentLinks->create(). Update idempotency meta key from _mollie_payment_id to _mollie_payment_link_id. Update all references in class-rest-invoices.php and class-mollie-webhook.php.

verification:
files_changed:
  - includes/class-mollie-payment.php
  - includes/class-mollie-webhook.php
  - includes/class-rest-invoices.php
