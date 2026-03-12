---
status: awaiting_human_verify
trigger: "Recent invoice emails (2026F012 contributie invoice and 2026T008 discipline invoice) are missing both the QR code and the payment link."
created: 2026-03-10T12:00:00Z
updated: 2026-03-10T12:06:00Z
---

## Current Focus

hypothesis: CONFIRMED - Two issues: (1) Mollie API config problem, (2) code continues sending email even when payment link creation fails
test: Applied fix to return error when payment link creation fails
expecting: User verifies fix works and also resolves Mollie account issue
next_action: Await human verification

## Symptoms

expected: Invoice emails contain a QR code image and a payment link/button so members can pay
actual: Emails for invoices 2026F012 and 2026T008 do not contain the QR code nor the payment link
errors: Mollie API 422 "No suitable payment methods found" in debug.log (not visible to user)
reproduction: Send an invoice email for any recent invoice — the QR code and payment link are absent
started: Recent — Mollie account issue causing all payment link creations to fail

## Eliminated

- hypothesis: Template variables {betaallink} and {qr_code} not properly implemented
  evidence: Code in InvoiceEmailSender correctly replaces these variables, but payment_link and qr_code_path fields are empty because Mollie API fails
  timestamp: 2026-03-10T12:02:00Z

- hypothesis: BulkInvoiceCreator not setting payment_link for membership invoices
  evidence: 2026F012 is invoice_type=manual (not membership), and PublicPaymentPage::generate_token() does set payment_link for membership invoices
  timestamp: 2026-03-10T12:03:00Z

## Evidence

- timestamp: 2026-03-10T12:02:00Z
  checked: Production post meta for invoice 6435 (2026F012)
  found: invoice_type=manual, no payment_link, no qr_code_path, no _mollie_payment_link_id, no _payment_token
  implication: Payment link was never created for this invoice

- timestamp: 2026-03-10T12:02:30Z
  checked: Production post meta for invoice 6443 (2026T008)
  found: invoice_type=discipline, no payment_link, no qr_code_path, no _mollie_payment_link_id
  implication: Payment link was never created for this invoice either

- timestamp: 2026-03-10T12:03:00Z
  checked: Production debug.log for Mollie errors
  found: All Mollie payment link creations fail with 422 "No suitable payment methods found" (10 occurrences today for invoices 6443-6447)
  implication: Mollie account has a configuration issue (no payment methods enabled or account not fully onboarded)

- timestamp: 2026-03-10T12:03:30Z
  checked: send_invoice() error handling in class-rest-invoices.php lines 1319-1329
  found: When Mollie create_payment_link() fails, error is logged but execution continues to PDF generation and email sending
  implication: This is the code bug - email should NOT be sent without a working payment link

- timestamp: 2026-03-10T12:04:00Z
  checked: Active payment provider on production
  found: rondo_finance_active_payment_provider = "mollie"
  implication: Mollie code path is correctly entered, but API call itself fails

## Resolution

root_cause: TWO issues - (1) Mollie account configuration problem causing API to return 422 "No suitable payment methods found" for all payment link creations, (2) send_invoice() logs the Mollie error but continues to send the email anyway, resulting in emails without payment links or QR codes
fix: Made send_invoice() return WP_Error when Mollie or Rabobank payment link creation fails, preventing incomplete emails from being sent. The frontend already handles WP_Error responses and shows the error message to the user.
verification: Build succeeds. Frontend error handling at FactuurDetail.jsx:191 already shows err.response.data.message to user.
files_changed: [includes/class-rest-invoices.php]
