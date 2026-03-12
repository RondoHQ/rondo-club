# Queued Milestones

## ~~Q001: Remove CardDAV Backend Code~~ ✅

Completed 2026-03-12. Committed as `fdb6b18c`, deployed to production as v31.8.0.

Removed 6 PHP files, 1 Composer dependency (sabre/dav), CardDAV subtab from Settings UI, and all related references. Net -73K lines.

## Q002: Credit Invoice Improvements

**Priority:** Next
**Scope:** Two fixes for credit invoices (creditfacturen)

### 1. Separate email template for credit invoices

**Problem:** Credit invoices currently use the "Standaard e-mail voor gewone facturen" template, which mentions a payment link (`{betaallink}`) and QR code (`{qr_code}`) — neither of which apply to a credit invoice (the club owes the person money, not vice versa).

**Solution:**
- Add a dedicated credit invoice email template to `FinanceConfig` (new option `OPTION_CREDIT_EMAIL_TEMPLATE` + default template)
- Add a credit invoice email heading option
- Template should explain the credit amount and that the club will transfer the funds
- Available variables: `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{organisatie_naam}`, `{tuchtzaken_lijst}`
- In `class-rest-invoices.php` send_invoice: select credit template when `invoice_kind === 'credit'`
- In `InvoiceEmailSender::send()`: respect this via the existing `$options['template']` mechanism
- Add credit email template editor in Finance Settings UI (alongside existing template editors)
- Expose in `get_all_settings()` and handle in the update endpoint

### 2. Credit invoices should NOT auto-transition to paid

**Problem:** In `send_invoice()`, credit invoices are immediately set to `rondo_paid` after sending. But the actual bank transfer hasn't happened yet — someone with bank account access needs to manually transfer the funds.

**Solution:**
- Remove the block in `send_invoice()` that auto-transitions credit invoices to `rondo_paid`
- Credit invoices should stay in `rondo_sent` status after sending, like normal invoices
- The admin manually marks them as paid (via existing "Markeer als betaald" button) after completing the bank transfer
- No payment link/QR code is created (this already works correctly)

### Files affected

- `includes/class-finance-config.php` — new template option + default + getter
- `includes/class-rest-invoices.php` — credit template selection + remove auto-paid transition
- `includes/class-invoice-email-sender.php` — credit template selection in `send()` (or passed via options)
- `src/pages/Finance/FinanceSettings.jsx` (or equivalent) — credit email template editor
- Frontend: may need to handle credit invoices in `rondo_sent` status (verify "Markeer als betaald" works)
