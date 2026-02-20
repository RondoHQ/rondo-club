---
status: resolved
trigger: "mollie-payment-link-expired"
created: 2026-02-20T00:00:00Z
updated: 2026-02-20T00:05:00Z
---

## Current Focus

hypothesis: RESOLVED
test: Fix applied, deployed, and verified
expecting: Members can now click "Volledig betalen" at any time and get a fresh working Mollie payment URL
next_action: complete

## Symptoms

expected: Clicking "Volledig betalen" on an invoice takes the member to a working Mollie payment page
actual: The Mollie payment link is expired/invalid by the time the member clicks it
errors: Expired link error from Mollie
reproduction: Open any invoice, click "Volledig betalen" — wait 15+ min after first click (or check on a second visit)
started: Design issue — idempotency check redirects to stale stored Mollie payment URLs

## Eliminated

- hypothesis: payment link created at invoice-generation-time (pre-stored Mollie URL in email)
  evidence: The payment_link field stores `/betaling/{token}` (a Rondo URL), not a Mollie URL; the Mollie checkout URL is created fresh on form submission via InstallmentPaymentService::create_payment()
  timestamp: 2026-02-20

## Evidence

- timestamp: 2026-02-20
  checked: class-public-payment-page.php handle_plan_selection() lines 494-502
  found: Idempotency check reads _installment_1_mollie_payment_id from post meta; if present, redirects to stored _installment_1_payment_link URL
  implication: Stored URL is a Mollie payment checkout URL (from $mollie->payments->create()) which expires in ~15 minutes; if member returns after expiry, they are redirected to an expired/invalid Mollie URL

- timestamp: 2026-02-20
  checked: class-installment-payment-service.php create_payment()
  found: Uses $mollie->payments->create() (NOT paymentLinks->create()) — this creates a regular Mollie payment that expires in ~15 minutes
  implication: Confirms the expiry mechanism; unlike Mollie payment-links, regular payments have a short TTL

- timestamp: 2026-02-20
  checked: class-mollie-payment.php (admin payment links)
  found: Admin payment links use $mollie->paymentLinks->create() — persistent, no expiry. But this is for the admin-created payment_link field, not the public payment page
  implication: The public payment page uses a different payment creation path (InstallmentPaymentService) that creates expiring regular payments

## Resolution

root_cause: In PublicPaymentPage::handle_plan_selection(), the idempotency check redirected returning members to a stored Mollie payment checkout URL (_installment_1_payment_link). That URL was created by InstallmentPaymentService::create_payment() using $mollie->payments->create() which creates a regular Mollie payment that expires in ~15 minutes. Any member who returned to the payment page after 15 minutes got an expired Mollie payment URL.
fix: Removed the idempotency redirect. Added paid-status guards (rondo_paid post status + installment_1_status = betaald) to prevent duplicate payments. Added delete_post_meta calls for _installment_1_mollie_payment_id and _installment_1_payment_link before creating a new payment, ensuring a fresh Mollie payment is always created. Also fixed pre-existing $invoice_season variable scope issue by moving $available_dates computation inside the installment plan branches where it is actually needed.
verification: Build passes, lint passes, deployed to production
files_changed:
  - includes/class-public-payment-page.php
