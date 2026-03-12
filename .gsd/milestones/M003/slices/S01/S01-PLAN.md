# S01: Credit Invoice Email Template & Status Fix

**Goal:** Credit invoices use a dedicated email template (no payment link/QR references), stay in "Verstuurd" status after sending (not auto-marked as paid), and the template is configurable in Finance Settings.
**Demo:** Send a credit invoice → email uses credit-specific template without `{betaallink}`/`{qr_code}` → invoice status is "Verstuurd" (not "Betaald") → template is editable in Finance Settings > E-mail > Creditfacturen.

## Must-Haves

- Credit email template with Dutch default (no payment link/QR/button references)
- Credit email heading configurable via FinanceConfig
- `send_invoice()` routes credit invoices to credit template (after custom override, before invoice_type fallback)
- Auto-paid transition removed for credit invoices (the `if ('credit' === $invoice_kind)` block at ~line 1440)
- InvoiceEmailSender `heading_type` match handles credit invoices
- REST API registers `credit_email_template` and `credit_email_heading` args
- Test email system supports `credit` template type
- Finance Settings UI has 'Creditfacturen' sub-tab with heading, template editor, variable docs, and TestEmailBlock
- Frontend formData, useEffect loader, and handleSubmit payload include credit keys
- Build + lint pass

## Proof Level

- This slice proves: integration
- Real runtime required: yes (production deploy + send test)
- Human/UAT required: yes (verify email content on production)

## Verification

- `npm run build` — frontend compiles without errors
- `npm run lint` — no lint warnings
- `grep -c 'OPTION_CREDIT_EMAIL_TEMPLATE' includes/class-finance-config.php` — returns ≥ 3 (constant, default, getter, get_all_settings, update_settings)
- `grep -c "'credit'" includes/class-rest-api.php` — present in test-email validator and switch
- `grep 'credit_email_template' includes/class-rest-api.php` — registered as REST arg
- `grep "credit_email_template" src/pages/Finance/FinanceSettings.jsx` — present in formData, useEffect, handleSubmit, and sub-tab render
- `grep -c "creditfacturen\|Creditfacturen" src/pages/Finance/FinanceSettings.jsx` — sub-tab exists
- Verify the auto-paid block is removed: `grep -c "credit_payment_adjustment_recorded_at" includes/class-rest-invoices.php` returns 0

## Observability / Diagnostics

- Runtime signals: Credit invoices log the same `error_log` entries as other types on email send failure; no new signals needed
- Inspection surfaces: Finance Settings > E-mail > Creditfacturen sub-tab shows current template; test email button sends a preview
- Failure visibility: `InvoiceEmailSender::send()` returns `WP_Error` on failure, surfaced to the REST response
- Redaction constraints: None — no secrets involved

## Integration Closure

- Upstream surfaces consumed: `FinanceConfig` (option/default/getter pattern), `class-rest-api.php` (REST arg + test email pattern), `class-rest-invoices.php` (`send_invoice()` template routing + auto-paid block), `InvoiceEmailSender` (heading_type match), `FinanceSettings.jsx` (sub-tab + formData pattern)
- New wiring introduced in this slice: Credit template option → FinanceConfig getter → send_invoice() routing → InvoiceEmailSender heading → REST API registration → Frontend sub-tab
- What remains before the milestone is truly usable end-to-end: nothing — this is the only slice

## Tasks

- [x] **T01: Add credit email template to FinanceConfig and wire REST API** `est:30m`
  - Why: Backend storage, retrieval, and REST API registration are the foundation — everything else depends on these being in place
  - Files: `includes/class-finance-config.php`, `includes/class-rest-api.php`
  - Do: Add `OPTION_CREDIT_EMAIL_TEMPLATE`, `OPTION_CREDIT_EMAIL_HEADING` constants; add defaults (credit template without `{betaallink}`/`{qr_code}`/`{betaalknop}`, heading 'Creditfactuur'); add `get_credit_email_template()` getter; add `'credit'` case to `get_email_heading()` match; expose in `get_all_settings()` and `get_setting()`; handle in `update_settings()` with `wp_kses_post`; register `credit_email_template` and `credit_email_heading` as REST args on finance/settings POST; add `'credit'` to test-email validator whitelist; add `case 'credit'` to `send_finance_test_email()` switch
  - Verify: `grep -c 'OPTION_CREDIT_EMAIL_TEMPLATE' includes/class-finance-config.php` returns ≥ 3 AND `grep "'credit'" includes/class-rest-api.php` shows presence in validator and switch
  - Done when: Credit template is stored/retrieved/exposed via REST, test email switch handles credit type

- [x] **T02: Route credit invoices to credit template and remove auto-paid transition** `est:25m`
  - Why: This is the core behavioral fix — credit invoices must use the credit template and stay in "Verstuurd" status
  - Files: `includes/class-rest-invoices.php`, `includes/class-invoice-email-sender.php`
  - Do: In `send_invoice()`, after the custom `_email_body_override` check but before the `invoice_type`-based template selection (~line 1376), add: if `invoice_kind === 'credit'` and `email_options['template']` is still empty, set it to `$finance_config->get_credit_email_template()`. Remove the auto-paid `if ('credit' === $invoice_kind)` block at ~line 1440 (the `wp_update_post` to `rondo_paid`, `update_field` to 'paid', and `_credit_payment_adjustment_recorded_at` meta write). In `InvoiceEmailSender::send()`, update the `heading_type` match to check `invoice_kind` first: if the invoice has `_invoice_kind` = 'credit', use 'credit' as the heading_type regardless of `invoice_type`. Pass `invoice_kind` to InvoiceEmailSender via `$options` or read it from post meta inside `send()`.
  - Verify: `grep -c "credit_payment_adjustment_recorded_at" includes/class-rest-invoices.php` returns 0 AND `grep "get_credit_email_template" includes/class-rest-invoices.php` shows credit template selection
  - Done when: Credit invoices use credit template, stay in "Verstuurd" status after send, and get the credit email heading

- [ ] **T03: Add Creditfacturen sub-tab to Finance Settings UI, build, deploy, and verify** `est:30m`
  - Why: The credit template must be configurable in the UI; build/lint must pass; deploy to production for UAT
  - Files: `src/pages/Finance/FinanceSettings.jsx`, `style.css`, `package.json`, `CHANGELOG.md`
  - Do: Add `{ id: 'creditfacturen', label: 'Creditfacturen' }` to `EMAIL_SUB_TABS`. Add `credit_email_template` and `credit_email_heading` to formData initial state, useEffect settings loader, and handleSubmit payload. Add `{emailSubTab === 'creditfacturen' && (...)}` block with heading input, RichTextEditor, variable docs (only `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{tuchtzaken_lijst}`, `{organisatie_naam}` — NO `{betaallink}`/`{qr_code}`/`{betaalknop}`), and `<TestEmailBlock templateType="credit" />`. Run `npm run lint` and `npm run build`. Bump version. Update CHANGELOG. Git commit + push. Deploy via `bin/deploy.sh`.
  - Verify: `npm run build` succeeds AND `npm run lint` succeeds AND `grep "creditfacturen" src/pages/Finance/FinanceSettings.jsx` shows sub-tab AND deployed to production
  - Done when: Creditfacturen sub-tab visible in Finance Settings on production, test email works, credit invoices stay in "Verstuurd" after sending

## Files Likely Touched

- `includes/class-finance-config.php`
- `includes/class-rest-api.php`
- `includes/class-rest-invoices.php`
- `includes/class-invoice-email-sender.php`
- `src/pages/Finance/FinanceSettings.jsx`
- `style.css`
- `package.json`
- `CHANGELOG.md`
