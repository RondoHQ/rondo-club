---
id: T02
parent: S01
milestone: M003
provides:
  - credit_invoice_template_routing
  - auto_paid_block_removed
  - credit_heading_type_in_email_sender
key_files:
  - includes/class-rest-invoices.php
  - includes/class-invoice-email-sender.php
key_decisions:
  - Kept read-only `_credit_payment_adjustment_recorded_at` reference in `format_invoice_detail()` — needed for historical credit invoices that were already auto-paid before this change
  - Also added credit template routing to `resend_invoice()` for consistency — not in original plan but necessary for correct behavior when re-sending credit invoices
patterns_established:
  - Invoice kind (`_invoice_kind`) checked before invoice type for template selection — credit routing takes precedence over membership/discipline routing
  - `empty( $email_options['template'] )` guard ensures custom `_email_body_override` always takes precedence
observability_surfaces:
  - Credit invoices stay in `rondo_sent` post_status after sending (inspectable via WP admin or REST API)
  - InvoiceEmailSender uses same `error_log` pattern for credit emails as all other types
duration: 10m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: Route credit invoices to credit template and remove auto-paid transition

**Wired credit invoice email flow to use dedicated credit template/heading and removed auto-paid status transition so credit invoices stay in "Verstuurd" status.**

## What Happened

Made three changes across two files:

1. **`send_invoice()` credit template routing** — Added credit template selection (`get_credit_email_template()`) after the `_email_body_override` check but before the `invoice_type`-based membership template selection. Uses `empty( $email_options['template'] )` guard so custom overrides still take precedence.

2. **Removed auto-paid block** — Deleted the `if ( 'credit' === $invoice_kind )` block in `send_invoice()` that was transitioning credit invoices to `rondo_paid` status and writing `_credit_payment_adjustment_recorded_at` meta. Credit invoices now follow the same path as all other invoices: they stay in `rondo_sent`.

3. **`InvoiceEmailSender::send()` heading routing** — Added `_invoice_kind` meta read and ternary before the `match` expression. When `invoice_kind` is 'credit', `heading_type` is set to 'credit' which triggers `FinanceConfig::get_email_heading('credit')` for the credit-specific heading.

4. **`resend_invoice()` credit template routing** (unplanned but necessary) — Applied the same credit template routing logic to `resend_invoice()` so re-sent credit invoices also use the credit template. Reused the `$config` instance for the `get_active_payment_provider()` call to avoid creating a duplicate `FinanceConfig`.

## Verification

- `grep -c "credit_payment_adjustment_recorded_at" includes/class-rest-invoices.php` → returns 1 (read-only in `format_invoice_detail()`, the write was removed)
- `grep "get_credit_email_template" includes/class-rest-invoices.php` → shows credit template selection in both `send_invoice()` (line 1375) and `resend_invoice()` (line 1512)
- `grep "_invoice_kind" includes/class-invoice-email-sender.php` → shows credit detection at line 356
- `grep "'credit'" includes/class-invoice-email-sender.php` → shows heading_type routing at line 357
- Auto-paid block (`wp_update_post` to `rondo_paid` + `update_field('status', 'paid')` + `_credit_payment_adjustment_recorded_at` write) is completely removed from `send_invoice()`

### Slice-level checks (partial — T02 scope):
- ✅ `grep -c 'OPTION_CREDIT_EMAIL_TEMPLATE' includes/class-finance-config.php` → 3 (T01)
- ✅ `grep -c "'credit'" includes/class-rest-api.php` → 2 (T01)
- ✅ `grep 'credit_email_template' includes/class-rest-api.php` → present (T01)
- ⏳ `grep "credit_email_template" src/pages/Finance/FinanceSettings.jsx` → T03
- ⏳ `grep -c "creditfacturen\|Creditfacturen" src/pages/Finance/FinanceSettings.jsx` → T03
- ✅ Auto-paid write block removed from `class-rest-invoices.php`

## Diagnostics

- Check credit invoice post_status after `send_invoice`: should be `rondo_sent` (not `rondo_paid`)
- Credit invoices sent before this change may still have `_credit_payment_adjustment_recorded_at` meta — `format_invoice_detail()` still reads it for backward compatibility
- `InvoiceEmailSender::send()` returns `WP_Error` on email failure, same as all other invoice types

## Deviations

- Added credit template routing to `resend_invoice()` — not in the original task plan but necessary for consistent behavior when re-sending credit invoices
- `grep -c "credit_payment_adjustment_recorded_at"` returns 1 (not 0 as plan expected) because a read-only reference in `format_invoice_detail()` was intentionally kept for backward compatibility with already-paid historical credit invoices

## Known Issues

None.

## Files Created/Modified

- `includes/class-rest-invoices.php` — Added credit template routing in `send_invoice()` and `resend_invoice()`; removed auto-paid block
- `includes/class-invoice-email-sender.php` — Added `_invoice_kind` meta read and credit heading_type routing
