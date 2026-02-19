---
status: resolved
trigger: "mollie-payment-link-expiry"
created: 2026-02-19T00:00:00Z
updated: 2026-02-19T00:05:00Z
---

## Current Focus

hypothesis: RESOLVED
test: deployed and committed
expecting: new discipline case invoices use Mollie Payment Links API with persistent URLs
next_action: user to regenerate payment links for existing expired invoices 6181, 6182, 6187

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
  found: PaymentLink has getCheckoutUrl() that reads _links->paymentLink->href; isPaid() checks paidAt
  implication: The fix uses paymentLinks->create() and returned PaymentLink object works the same way

- timestamp: 2026-02-19T00:01:00Z
  checked: includes/class-mollie-webhook.php — webhook handler
  found: Payment link webhook fires with payment link ID (pl_xxx), not payment ID (tr_xxx)
  implication: Added Path 0 to webhook handler to handle pl_xxx IDs separately; existing paths unchanged

- timestamp: 2026-02-19T00:01:15Z
  checked: includes/class-rest-invoices.php — delete_post_meta calls
  found: Lines 602, 1024, 1124 delete _mollie_payment_id meta when resetting/voiding invoices
  implication: Updated all three references to _mollie_payment_link_id

- timestamp: 2026-02-19T00:01:20Z
  checked: Two code paths for invoice payment
  found: Membership invoice path (/betaling/{token}) is fine — Mollie payment created fresh when member selects plan, no expiry issue
  implication: Only MolliePayment (discipline invoices) needed the fix

## Resolution

root_cause: class-mollie-payment.php called $mollie->payments->create() (POST /v2/payments) which creates a regular Mollie payment with ~15-minute expiry. For discipline case invoices sent via email, members could not pay before the payment expired. The correct API is $mollie->paymentLinks->create() (POST /v2/payment-links) which creates a persistent link that remains valid until paid or archived.

fix: |
  1. Switched MolliePayment::create_payment_link() from $mollie->payments->create() to $mollie->paymentLinks->create()
  2. Renamed idempotency meta key from _mollie_payment_id to _mollie_payment_link_id
  3. Added Path 0 in MollieWebhook::handle_webhook() to handle payment link webhooks (pl_xxx IDs)
  4. Updated all _mollie_payment_id references in class-rest-invoices.php to _mollie_payment_link_id
  5. Backward-compatible: Path 2 legacy lookup (_mollie_payment_id) preserved for pre-fix invoices

verification: PHP syntax checks pass, ESLint passes, build succeeds, deployed to production. Committed as 1da7f0c1.

files_changed:
  - includes/class-mollie-payment.php
  - includes/class-mollie-webhook.php
  - includes/class-rest-invoices.php
