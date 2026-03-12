---
estimated_steps: 4
estimated_files: 2
---

# T02: Route credit invoices to credit template and remove auto-paid transition

**Slice:** S01 — Credit Invoice Email Template & Status Fix
**Milestone:** M003

## Description

Wire the send flow so credit invoices use the new credit template and heading, and remove the auto-paid status transition that currently overwrites "Verstuurd" with "Betaald" for credit invoices. These are the two core behavioral changes in this milestone.

## Steps

1. In `class-rest-invoices.php` `send_invoice()`, after the `_email_body_override` check (~line 1375) but BEFORE the `invoice_type`-based template selection (~line 1376-1378), add credit invoice routing:
   ```php
   // Select email template based on invoice kind (credit first) then invoice type
   if ( 'credit' === $invoice_kind && empty( $email_options['template'] ) ) {
       $email_options['template'] = $finance_config->get_credit_email_template();
   }
   ```
   This ensures: (a) custom `_email_body_override` still takes precedence, (b) credit template is used for credit invoices, (c) existing invoice_type-based selection continues to work for normal invoices.

2. Remove the auto-paid block at ~line 1440-1448 in `send_invoice()`. Delete the entire `if ( 'credit' === $invoice_kind )` block that does:
   - `wp_update_post` to `rondo_paid`
   - `update_field( 'status', 'paid', ... )`
   - `update_post_meta( ..., '_credit_payment_adjustment_recorded_at', ... )`
   
   After this removal, credit invoices follow the same path as all other invoices: they stay in `rondo_sent` status.

3. In `class-invoice-email-sender.php`, update the `heading_type` match expression (~line 356) to detect credit invoices. Read `_invoice_kind` from post meta inside `send()` and use 'credit' as heading_type when it equals 'credit':
   ```php
   $invoice_kind = get_post_meta( $invoice_id, '_invoice_kind', true ) ?: 'normal';
   $heading_type = 'credit' === $invoice_kind ? 'credit' : match ( $invoice_type ) {
       'membership' => 'membership',
       'manual'     => 'regular_invoice',
       default      => 'discipline',
   };
   ```
   This ensures the credit email heading from FinanceConfig is used.

4. Verify the changes: confirm the auto-paid block is gone, and the credit template + heading routing is in place.

## Must-Haves

- [ ] Credit invoices use `get_credit_email_template()` when no custom override exists
- [ ] Custom `_email_body_override` still takes precedence over credit template
- [ ] Auto-paid block removed (no `_credit_payment_adjustment_recorded_at` meta write)
- [ ] Credit invoices stay in `rondo_sent` status after sending
- [ ] InvoiceEmailSender uses 'credit' heading_type for credit invoices
- [ ] Normal (non-credit) invoices are completely unaffected

## Verification

- `grep -c "credit_payment_adjustment_recorded_at" includes/class-rest-invoices.php` returns 0
- `grep "get_credit_email_template" includes/class-rest-invoices.php` shows credit template selection
- `grep "_invoice_kind" includes/class-invoice-email-sender.php` shows credit detection
- `grep "'credit'" includes/class-invoice-email-sender.php` shows heading_type routing

## Observability Impact

- Signals added/changed: None — existing email send error logging applies equally to credit invoices
- How a future agent inspects this: Check invoice post_status after send_invoice — credit invoices should be `rondo_sent` (not `rondo_paid`)
- Failure state exposed: None beyond existing WP_Error returns

## Inputs

- `includes/class-finance-config.php` — T01 output: `get_credit_email_template()` getter and `get_email_heading('credit')` support
- `includes/class-rest-invoices.php` — `send_invoice()` method with `$invoice_kind` read at line 1297, template selection at ~1376, auto-paid block at ~1440
- `includes/class-invoice-email-sender.php` — `send()` method with `heading_type` match at ~line 356

## Expected Output

- `includes/class-rest-invoices.php` — Credit template routing added before invoice_type check; auto-paid block removed
- `includes/class-invoice-email-sender.php` — `heading_type` match updated to detect credit invoice_kind
