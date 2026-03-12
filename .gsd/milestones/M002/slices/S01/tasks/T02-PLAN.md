---
estimated_steps: 4
estimated_files: 1
---

# T02: Expose payment details via REST API and clear on reset

**Slice:** S01 — Webhook payment detail extraction + REST API + Invoice detail UI
**Milestone:** M002

## Description

Enrich the `format_invoice_detail()` REST response with Mollie payment detail fields at both invoice level and per-installment level. Also update `reset_payment_state()` to clear all new meta keys so test-mode resets produce a clean slate.

The invoice-level fields are simple `get_post_meta()` reads — no performance concern. The per-installment fields extend the existing installment loop that already reads `_installment_N_status`, `_installment_N_due_date`, etc.

## Steps

1. In `format_invoice_detail()`, after the installments block and before the `return $invoice;`, add invoice-level payment detail fields:
   ```php
   $invoice['mollie_payment_method']  = (string) get_post_meta( $post->ID, '_mollie_payment_method', true ) ?: null;
   $invoice['mollie_paid_at']         = (string) get_post_meta( $post->ID, '_mollie_paid_at', true ) ?: null;
   $invoice['mollie_dashboard_url']   = (string) get_post_meta( $post->ID, '_mollie_dashboard_url', true ) ?: null;
   $invoice['mollie_consumer_name']   = (string) get_post_meta( $post->ID, '_mollie_consumer_name', true ) ?: null;
   $invoice['mollie_consumer_account'] = (string) get_post_meta( $post->ID, '_mollie_consumer_account', true ) ?: null;
   ```

2. In the installment loop inside `format_invoice_detail()`, extend each installment array with:
   ```php
   'mollie_method'        => (string) get_post_meta( $post->ID, '_installment_' . $n . '_mollie_method', true ) ?: null,
   'mollie_paid_at'       => (string) get_post_meta( $post->ID, '_installment_' . $n . '_mollie_paid_at', true ) ?: null,
   'mollie_dashboard_url' => (string) get_post_meta( $post->ID, '_installment_' . $n . '_mollie_dashboard_url', true ) ?: null,
   ```

3. In `reset_payment_state()`, after the existing "Clear Mollie payment data" section, add deletion of the 6 new invoice-level meta keys:
   ```php
   delete_post_meta( $invoice_id, '_mollie_payment_method' );
   delete_post_meta( $invoice_id, '_mollie_paid_at' );
   delete_post_meta( $invoice_id, '_mollie_dashboard_url' );
   delete_post_meta( $invoice_id, '_mollie_consumer_name' );
   delete_post_meta( $invoice_id, '_mollie_consumer_account' );
   delete_post_meta( $invoice_id, '_mollie_payment_details' );
   ```
   Also loop through installments (read `_installment_count`) and clear per-installment Mollie meta:
   ```php
   $count = (int) get_post_meta( $invoice_id, '_installment_count', true );
   for ( $n = 1; $n <= $count; $n++ ) {
       delete_post_meta( $invoice_id, '_installment_' . $n . '_mollie_method' );
       delete_post_meta( $invoice_id, '_installment_' . $n . '_mollie_paid_at' );
       delete_post_meta( $invoice_id, '_installment_' . $n . '_mollie_dashboard_url' );
   }
   ```

4. Verify: `php -l includes/class-rest-invoices.php` passes; review confirms all fields are read-only additions.

## Must-Haves

- [ ] 5 invoice-level fields added to `format_invoice_detail()` response
- [ ] 3 per-installment fields added to each installment object in the loop
- [ ] All new fields return `null` when meta is empty (not empty string)
- [ ] `reset_payment_state()` deletes all 6 invoice-level Mollie meta keys
- [ ] `reset_payment_state()` loops through installments and deletes 3 per-installment Mollie meta keys
- [ ] No changes to existing response fields or behavior

## Verification

- `php -l includes/class-rest-invoices.php` exits 0
- Code review: `format_invoice_detail()` returns 5 new invoice-level keys
- Code review: each installment object includes 3 new keys
- Code review: `reset_payment_state()` clears 6 invoice-level + 3×N installment-level Mollie meta keys

## Observability Impact

- Signals added/changed: None — pure read additions to existing endpoint
- How a future agent inspects this: `curl` the invoice detail endpoint and check for `mollie_payment_method` in response; absence means no Mollie payment data stored
- Failure state exposed: None new — fields return null when meta is absent

## Inputs

- `includes/class-rest-invoices.php` — `format_invoice_detail()` at line 2375, installment loop at lines 2426-2438, `reset_payment_state()` at line 1924
- T01 output: meta keys `_mollie_payment_method`, `_mollie_paid_at`, `_mollie_dashboard_url`, `_mollie_consumer_name`, `_mollie_consumer_account`, `_mollie_payment_details` at invoice level; `_installment_N_mollie_method`, `_installment_N_mollie_paid_at`, `_installment_N_mollie_dashboard_url` per installment

## Expected Output

- `includes/class-rest-invoices.php` — `format_invoice_detail()` returns enriched response with payment details; `reset_payment_state()` clears all new meta keys
