---
phase: quick-78
plan: "01"
subsystem: Finance / Invoice Email
tags: [invoices, email, test-mode, safety]
dependency_graph:
  requires: []
  provides:
    - InvoiceEmailSender.send() $options array with override_email and skip_bcc
    - Test-mode email redirection to current logged-in user
    - [TEST] subject prefix for test emails
    - UI button labels reflecting test mode state
  affects:
    - includes/class-invoice-email-sender.php
    - includes/class-rest-invoices.php
    - src/pages/Finance/FactuurDetail.jsx
tech_stack:
  added: []
  patterns:
    - Options array parameter pattern for backward-compatible method extension
    - Test-mode guard using existing is_test_mode_active() helper
key_files:
  created: []
  modified:
    - includes/class-invoice-email-sender.php
    - includes/class-rest-invoices.php
    - src/pages/Finance/FactuurDetail.jsx
decisions:
  - Override email applied after person-email validation — person must still have a valid email even in test mode (prevents masking data issues)
  - Both override_email and skip_bcc are independently checkable options — [TEST] prefix triggers on either being set
  - $email_options empty array in production mode — zero behavioral change for existing code
metrics:
  duration: 153s
  completed: "2026-02-18"
  tasks: 3
  files: 3
---

# Quick Task 78: Test Mode — Send Invoice to Current User

## Summary

Redirect invoice emails to the current logged-in admin user when Mollie (test) or Rabobank (sandbox) is active, with BCC suppressed and a [TEST] subject prefix, preventing accidental emails to real members during testing.

## Tasks Completed

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | Add $options support to InvoiceEmailSender::send() | 646adae6 | class-invoice-email-sender.php |
| 2 | Pass test-mode options from REST send/resend endpoints | 3258ed63 | class-rest-invoices.php |
| 3 | Show (test) suffix on send/resend buttons in test mode | 2884f1a7 | FactuurDetail.jsx |

## What Was Built

### InvoiceEmailSender::send() options support

The method signature changed from `send( int $invoice_id )` to `send( int $invoice_id, array $options = [] )`. The options array supports:

- `override_email` (string): Redirect the email to this address instead of the person's email
- `skip_bcc` (bool): Suppress the BCC header entirely

When either option is active, the subject is prefixed with `[TEST] ` to make test emails clearly identifiable. The `$recipient_email` variable is resolved from the override when set, falling back to `$person_email`.

### REST endpoint test-mode detection

Both `send_invoice()` and `resend_invoice()` in `class-rest-invoices.php` now build `$email_options` before calling `InvoiceEmailSender::send()`. The existing private `is_test_mode_active()` method is used. In test mode, `override_email` is set to `wp_get_current_user()->user_email` and `skip_bcc` is set to `true`. In production mode, `$email_options` is an empty array and behavior is completely unchanged.

### UI button labels

In `FactuurDetail.jsx`, both send buttons conditionally show a `(test)` suffix when `isTestMode` is true (the variable was already computed from `financeSettings`):

- "Verstuur factuur" becomes "Verstuur factuur (test)"
- "Opnieuw versturen" becomes "Opnieuw versturen (test)"

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- [x] `includes/class-invoice-email-sender.php` — exists and modified
- [x] `includes/class-rest-invoices.php` — exists and modified
- [x] `src/pages/Finance/FactuurDetail.jsx` — exists and modified
- [x] Commit 646adae6 — exists
- [x] Commit 3258ed63 — exists
- [x] Commit 2884f1a7 — exists
- [x] `php -l includes/class-invoice-email-sender.php` — no syntax errors
- [x] `php -l includes/class-rest-invoices.php` — no syntax errors
- [x] `npm run build` — success
- [x] Deployed to production at https://rondo.svawc.nl/
