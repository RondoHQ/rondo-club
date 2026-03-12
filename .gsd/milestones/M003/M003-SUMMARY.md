---
id: M003
provides:
  - credit_invoice_email_template
  - credit_invoice_status_fix
  - credit_template_finance_settings_ui
key_decisions:
  - "[M003-S01] Credit email template follows exact same OPTION/DEFAULTS/getter/heading/get_all_settings/update_settings pattern as existing 8 templates — no new patterns introduced"
  - "[M003-S01] Credit template variables exclude {betaallink}, {qr_code}, {betaalknop} — credit invoices clear these in send_invoice() so they would be empty"
  - "[M003-S01] invoice_kind checked BEFORE invoice_type in template routing — credit status (stored in _invoice_kind meta) overrides the invoice type (discipline/membership/manual)"
  - "[M003-S01] Auto-paid transition fully removed for credit invoices — they stay in rondo_sent like all other invoices; manual mark-as-paid is required"
  - "[M003-S01] InvoiceEmailSender reads _invoice_kind from post meta inside send() — avoids requiring callers to pass invoice_kind, same pattern as invoice_type which is read from ACF"
patterns_established:
  - Credit template uses identical constant/default/getter/settings/update pattern as all other email templates
  - Invoice kind (_invoice_kind) checked before invoice type for template selection — kind overrides type
  - Credit template routing applied to both send_invoice() and resend_invoice() for consistency
observability_surfaces:
  - Finance Settings > E-mail > Creditfacturen sub-tab shows current template on production
  - Credit invoices stay in rondo_sent post_status after sending (inspectable via WP admin or REST API)
  - TestEmailBlock with templateType="credit" sends preview emails from Finance Settings
requirement_outcomes: []
duration: ~30 minutes across 3 tasks
verification_result: passed
completed_at: 2026-03-12T13:52:48.604Z
---

# M003: Credit Invoice Improvements

**Credit invoices get their own email template (no payment link/QR code references) and no longer auto-mark as paid on send — they stay in "Verstuurd" until manually marked paid.**

## What Happened

This milestone made two targeted fixes to the credit invoice flow across a single slice (S01) with three tasks:

**T01 — Backend Config:** Added the 9th email template type to `FinanceConfig` following the exact established pattern. Two new constants (`OPTION_CREDIT_EMAIL_TEMPLATE`, `OPTION_CREDIT_EMAIL_HEADING`), Dutch default template text that intentionally omits `{betaallink}`, `{qr_code}`, and `{betaalknop}` placeholders, a `get_credit_email_template()` getter, heading support via `get_email_heading('credit')`, and full wiring into `get_all_settings()`/`get_setting()`/`update_settings()`. REST API args registered with proper sanitization. Test email support added with `'credit'` in the validator whitelist and switch statement.

**T02 — Email Routing & Status Fix:** Two changes to `class-rest-invoices.php`: (1) Credit template selection added to both `send_invoice()` and `resend_invoice()`, checking `_invoice_kind === 'credit'` before the existing `invoice_type`-based routing, with an `empty()` guard so custom `_email_body_override` still takes precedence. (2) The auto-paid block that was transitioning credit invoices to `rondo_paid` and writing `_credit_payment_adjustment_recorded_at` was removed entirely — credit invoices now stay in `rondo_sent` like all other invoice types. In `InvoiceEmailSender::send()`, added `_invoice_kind` meta read to route credit invoices to the `'credit'` heading type.

**T03 — Frontend & Deploy:** Added `Creditfacturen` as the 7th sub-tab in Finance Settings E-mail section. The sub-tab includes heading input, RichTextEditor for the template body, variable documentation (explicitly excluding payment-related variables), and a TestEmailBlock. Version bumped to 31.10.0, deployed to production via `bin/deploy.sh`, verified on production.

## Cross-Slice Verification

Only one slice (S01), so no cross-slice integration needed. All success criteria verified:

| Success Criterion | Evidence | Result |
|---|---|---|
| Sending a credit invoice uses a dedicated email template without payment link/QR code references | `get_credit_email_template()` called in `send_invoice()` and `resend_invoice()` when `_invoice_kind === 'credit'`; default template confirmed to NOT contain `{betaallink}`, `{qr_code}`, or `{betaalknop}` via grep | ✅ PASS |
| Credit email template is configurable in Finance Settings | Creditfacturen sub-tab visible in Finance Settings > E-mail on production; `credit_email_template` and `credit_email_heading` wired into formData, useEffect, handleSubmit, and REST API | ✅ PASS |
| Sent credit invoices remain in "Verstuurd" status until manually marked paid | Auto-paid block (`wp_update_post` to `rondo_paid` + `_credit_payment_adjustment_recorded_at` write) completely removed from `send_invoice()`; only 1 read-only reference remains in `format_invoice_detail()` for backward compat | ✅ PASS |
| Existing invoice flows (discipline, membership, manual) are unaffected | Credit template routing uses `if ('credit' === $invoice_kind && empty($email_options['template']))` guard — only activates for credit invoices; existing `invoice_type` routing preserved below; `npm run build` + `npm run lint` pass | ✅ PASS |

**Definition of Done verification:**

| Criterion | Evidence | Result |
|---|---|---|
| Credit email template added to FinanceConfig with Dutch default | `grep -c 'OPTION_CREDIT_EMAIL_TEMPLATE' includes/class-finance-config.php` → 3 | ✅ |
| Template selection in send_invoice() routes credit invoices to new template | `grep 'get_credit_email_template' includes/class-rest-invoices.php` → 2 lines (send + resend) | ✅ |
| Auto-paid transition removed for credit invoices | Auto-paid block removed; `grep -c 'credit_payment_adjustment_recorded_at' includes/class-rest-invoices.php` → 1 (read-only in format_invoice_detail) | ✅ |
| Finance Settings UI has credit email template editor | `grep -c 'creditfacturen\|Creditfacturen' src/pages/Finance/FinanceSettings.jsx` → 6 | ✅ |
| Deployed to production and verified | Production browser verification confirmed Creditfacturen tab visible with correct content | ✅ |

**Build verification:** `npm run build` succeeds, `npm run lint` returns 0 warnings.

## Requirement Changes

No requirements changed status during this milestone. The 12 validated requirements (BTN-01 through ROLL-07) are unrelated button tier requirements from M001 and remain validated.

## Forward Intelligence

### What the next milestone should know
- The email template pattern is now at 9 templates — any further templates should follow the same constant/default/getter/settings/update pattern in FinanceConfig
- `_invoice_kind` is now checked before `invoice_type` in both `send_invoice()` and `resend_invoice()` — any future invoice kind additions should follow this precedence

### What's fragile
- The `_credit_payment_adjustment_recorded_at` read-only reference in `format_invoice_detail()` exists for backward compatibility with historical credit invoices that were auto-paid before v31.10.0 — can be removed once those invoices are no longer relevant

### Authoritative diagnostics
- Finance Settings > E-mail > Creditfacturen on production — shows current template configuration
- Credit invoice post_status after sending — should always be `rondo_sent`, never `rondo_paid`

### What assumptions changed
- No assumptions changed — both changes were small, well-understood modifications to existing patterns as predicted in the risk assessment

## Files Created/Modified

- `includes/class-finance-config.php` — Added 2 constants, 2 defaults, 1 getter, 1 heading match case, wired into settings read/write
- `includes/class-rest-invoices.php` — Added credit template routing in send_invoice() and resend_invoice(); removed auto-paid block
- `includes/class-invoice-email-sender.php` — Added _invoice_kind meta read and credit heading_type routing
- `includes/class-rest-api.php` — Added 2 REST args, 1 validator entry, 1 switch case for test email
- `src/pages/Finance/FinanceSettings.jsx` — Added Creditfacturen sub-tab with heading, template editor, variable docs, test email
- `style.css` — Version bumped to 31.10.0
- `package.json` — Version bumped to 31.10.0
- `CHANGELOG.md` — Added 31.10.0 entry
