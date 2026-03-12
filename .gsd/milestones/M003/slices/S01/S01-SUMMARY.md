---
id: S01
parent: M003
milestone: M003
provides:
  - credit_email_template_config
  - credit_email_heading_config
  - credit_test_email_support
  - credit_invoice_template_routing
  - auto_paid_block_removed
  - creditfacturen_subtab_ui
requires: []
affects:
  - includes/class-finance-config.php
  - includes/class-rest-invoices.php
  - includes/class-invoice-email-sender.php
  - includes/class-rest-api.php
  - src/pages/Finance/FinanceSettings.jsx
key_files:
  - includes/class-finance-config.php
  - includes/class-rest-invoices.php
  - includes/class-invoice-email-sender.php
  - src/pages/Finance/FinanceSettings.jsx
key_decisions:
  - Credit template follows exact same pattern as existing 8 templates (OPTION/DEFAULTS/getter/heading/settings)
  - invoice_kind checked before invoice_type for template routing precedence
  - Auto-paid block fully removed; credit invoices stay in rondo_sent
  - Credit template routing added to both send_invoice() and resend_invoice()
  - Read-only _credit_payment_adjustment_recorded_at kept in format_invoice_detail() for backward compat
patterns_established:
  - Invoice kind overrides invoice type for template selection
  - Credit template sub-tab follows identical structure to boetes sub-tab
observability_surfaces:
  - Finance Settings > E-mail > Creditfacturen sub-tab on production
  - Credit invoice post_status stays rondo_sent after sending
  - TestEmailBlock with templateType="credit" for preview emails
drill_down_paths:
  - T01 — FinanceConfig + REST API additions
  - T02 — Email routing + auto-paid removal
  - T03 — Frontend sub-tab + deploy + verify
duration: ~30 minutes
verification_result: passed
completed_at: 2026-03-12T13:52:48.604Z
---

# S01: Credit Invoice Email Template & Status Fix

**Credit invoices use their own email template (no payment/QR references), stay in "Verstuurd" status, and the template is configurable in Settings.**

## What Happened

Three tasks implemented the complete credit invoice email improvement:

1. **T01** added the 9th email template to FinanceConfig: constants, Dutch default (without payment variables), getter, heading support, settings exposure, and REST API args with test email support.

2. **T02** wired the routing: `send_invoice()` and `resend_invoice()` check `_invoice_kind === 'credit'` before the existing `invoice_type` routing; `InvoiceEmailSender::send()` routes credit invoices to the credit heading. The auto-paid block was removed entirely — credit invoices now stay in `rondo_sent`.

3. **T03** added the Creditfacturen sub-tab to Finance Settings with heading input, template editor, variable docs (explicitly excluding payment variables), and test email block. Deployed to production as v31.10.0.

## Verification

- `npm run build` + `npm run lint` pass (0 warnings)
- Default credit template confirmed free of `{betaallink}`, `{qr_code}`, `{betaalknop}`
- Auto-paid block removed from send_invoice()
- Credit template routing present in both send and resend paths
- Creditfacturen sub-tab verified on production

## Deviations

- T02 added credit template routing to `resend_invoice()` (not in original plan but necessary for consistency)
- One read-only reference to `_credit_payment_adjustment_recorded_at` kept in `format_invoice_detail()` for backward compatibility

## Known Limitations

- Historical credit invoices that were auto-paid before v31.10.0 retain their `rondo_paid` status — no backfill to revert them

## Follow-ups

None required.

## Files Created/Modified

- `includes/class-finance-config.php` — 9th email template type added
- `includes/class-rest-invoices.php` — Credit template routing + auto-paid removal
- `includes/class-invoice-email-sender.php` — Credit heading routing
- `includes/class-rest-api.php` — REST args + test email support
- `src/pages/Finance/FinanceSettings.jsx` — Creditfacturen sub-tab
- `style.css` + `package.json` — Version 31.10.0
- `CHANGELOG.md` — 31.10.0 entry

## Forward Intelligence

### What the next slice should know
- Email template pattern is at 9 templates; follow same pattern for any additions
- `_invoice_kind` takes precedence over `invoice_type` in template routing

### What's fragile
- Read-only `_credit_payment_adjustment_recorded_at` in format_invoice_detail() — can be removed when historical data is no longer relevant

### Authoritative diagnostics
- Production Finance Settings > E-mail > Creditfacturen tab

### What assumptions changed
- None — both changes were small and well-understood as predicted
