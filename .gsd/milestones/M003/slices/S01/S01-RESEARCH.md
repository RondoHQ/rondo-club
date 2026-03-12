# S01: Credit Invoice Email Template & Status Fix — Research

**Date:** 2026-03-12

## Summary

This slice adds a dedicated credit invoice email template and removes the auto-paid transition for credit invoices. Both changes are small, well-understood modifications that follow established patterns in the codebase.

The codebase already has 8+ email template types (discipline, membership, installment, reminders, etc.), each following the same pattern: OPTION constant + DEFAULTS entry + getter method in `FinanceConfig`, exposed via `get_all_settings()`, saved via `update_settings()`, registered as REST arg, and editable in FinanceSettings.jsx with a sub-tab. Adding a 9th for credit invoices is mechanical.

The credit auto-paid transition is a single `if` block at line ~1440 in `class-rest-invoices.php` that overwrites the just-set `rondo_sent` status with `rondo_paid`. Removing this block (and the associated `_credit_payment_adjustment_recorded_at` meta write) is the entire fix for the status issue.

## Recommendation

Follow the exact established pattern for all 8 existing email template types. The changes touch 5 files across 4 concerns:

1. **FinanceConfig** (`includes/class-finance-config.php`): Add `OPTION_CREDIT_EMAIL_TEMPLATE`, `OPTION_CREDIT_EMAIL_HEADING`, default values in `DEFAULTS`, getter methods, expose in `get_all_settings()`, handle in `update_settings()`
2. **REST API** (`includes/class-rest-api.php`): Register `credit_email_template` and `credit_email_heading` as args on the finance/settings POST route; add `'credit'` to the test-email `template_type` validator; add `case 'credit'` in `send_finance_test_email()` switch
3. **Invoice send flow** (`includes/class-rest-invoices.php`): In `send_invoice()`, route credit invoices to the new template (check `invoice_kind === 'credit'` before template selection around line ~1385). Remove the auto-paid `if ('credit' === $invoice_kind)` block at line ~1440
4. **InvoiceEmailSender** (`includes/class-invoice-email-sender.php`): Add credit invoice_kind awareness for heading type selection (line ~356)
5. **Frontend** (`src/pages/Finance/FinanceSettings.jsx`): Add 'Creditfacturen' sub-tab in the email section with template editor, heading field, variable docs, and TestEmailBlock

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Email template storage | `FinanceConfig` OPTION/DEFAULTS/getter pattern | 8 templates already follow this exact pattern — copy it verbatim |
| Email template UI | `FinanceSettings.jsx` email sub-tabs with `RichTextEditor` + variable docs box | Each sub-tab is ~50 lines of consistent JSX — reuse the exact structure |
| Test email sending | `send_finance_test_email()` switch in `class-rest-api.php` | Add `case 'credit'` — one-line addition to existing switch |
| Email heading config | `get_email_heading()` method with match expression | Add `'credit'` case to existing match |

## Existing Code and Patterns

- `includes/class-finance-config.php` — Template pattern: `OPTION_*` constant → `DEFAULTS[key]` → `get_*_template()` getter → exposed in `get_all_settings()` → handled in `update_settings()` with `wp_kses_post()`. Heading pattern: `OPTION_*_HEADING` constant → `DEFAULTS[key]` → `get_email_heading()` match expression
- `includes/class-rest-api.php` lines 1100-1175 — REST arg registration for email templates. Each template has `'required' => false, 'sanitize_callback' => 'wp_kses_post'`. Headings have `sanitize_text_field`. Test email validator whitelist at line ~1189
- `includes/class-rest-invoices.php` lines 1297-1300 — `$invoice_kind` is read from `_invoice_kind` post meta (defaults to 'normal'). Template selection at lines ~1381-1387 checks `invoice_type` but NOT `invoice_kind` — this is the gap. Auto-paid block at lines 1440-1448 must be removed
- `includes/class-invoice-email-sender.php` lines 214-222 — Template selection in `send()` checks `invoice_type` (membership, manual, default=discipline). The `heading_type` match at line 356 maps invoice_type to heading key. Both need credit invoice_kind awareness
- `src/pages/Finance/FinanceSettings.jsx` — `EMAIL_SUB_TABS` array at line ~25 defines the sub-tab list. Each sub-tab renders a card with heading input, `RichTextEditor`, variables box, and `TestEmailBlock`. The formData state, useEffect loader, and handleSubmit payload all need the new credit keys

## Constraints

- **Template variables for credit invoices**: `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{organisatie_naam}`, `{tuchtzaken_lijst}` are available. **No** `{betaallink}`, `{qr_code}`, `{betaalknop}` — credit invoices clear these in `send_invoice()` at line 1300-1304
- **invoice_kind vs invoice_type**: Credit status is stored in `_invoice_kind` (post meta: 'credit' or 'normal'), separate from `invoice_type` (ACF field: 'discipline', 'membership', 'manual'). The template routing must check `invoice_kind` first, then fall back to `invoice_type`-based template selection
- **Default template must not reference payment**: The default credit email template must explain the credit (money owed TO the person) and must not contain `{betaallink}`, `{qr_code}`, or `{betaalknop}` since those are cleared for credit invoices
- **InvoiceEmailSender receives template via $options**: The `send_invoice()` method in `class-rest-invoices.php` passes the template to `InvoiceEmailSender::send()` via `$email_options['template']`. For credit invoices, this should be set to the credit template from FinanceConfig. The heading should also be passed or derived

## Common Pitfalls

- **Forgetting to update the InvoiceEmailSender heading_type match** — The `heading_type` match expression at line 356 only handles `membership`, `manual`, and default (discipline). If the send flow passes the credit template but doesn't also set a credit heading, it would fall through to the discipline heading. The `send_invoice()` caller must pass the heading or InvoiceEmailSender must detect credit invoices
- **Template override precedence** — `send_invoice()` checks `_email_body_override` meta before template selection. The credit template check must be placed AFTER the custom override check but BEFORE the existing `invoice_type`-based selection (lines ~1385-1387), so custom body overrides still work for credit invoices
- **Missing REST arg registration** — Note: `invoice_reminder_1_email_template` and `invoice_reminder_2_email_template` are NOT registered as REST args despite being saved. This works because WordPress accepts all params on POST. For consistency, register the new credit args properly
- **Frontend formData state initialization** — The `useEffect` that loads settings into formData must include the new credit template keys, and the `handleSubmit` payload must include them. Missing either causes data loss

## Open Risks

- None — all patterns are well-established, all integration points identified, and changes are small and localized

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress/PHP | N/A | Not applicable — standard WordPress theme development |
| React | vercel-react-best-practices | installed (but not needed — trivial UI addition) |

## Sources

- All research based on direct codebase inspection (no external sources needed)
