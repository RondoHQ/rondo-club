---
phase: quick-95
plan: 01
subsystem: finance
tags: [email, invoices, membership, templates, settings]
dependency_graph:
  requires: []
  provides: [membership-email-template]
  affects: [InvoiceEmailSender, FinanceConfig, FinanceSettings, REST invoices]
tech_stack:
  added: []
  patterns: [optional-options-override, invoice-type-routing]
key_files:
  created: []
  modified:
    - includes/class-finance-config.php
    - includes/class-invoice-email-sender.php
    - includes/class-rest-invoices.php
    - includes/class-rest-api.php
    - src/pages/Finance/FinanceSettings.jsx
decisions:
  - Membership template stored as new WP option (rondo_finance_membership_email_template) — existing discipline option key unchanged to preserve user data
  - InvoiceEmailSender accepts optional 'template' key in $options — defaults to discipline template for backward compatibility
  - Template selection in REST layer (send_invoice/resend_invoice) — clean separation from email sender logic
  - Membership template variables omit tuchtzaken_lijst in UI docs — irrelevant for contribution invoices (variable replacement still works, produces empty output)
metrics:
  duration: 4min
  completed: 2026-02-19
  tasks: 2
  files: 5
---

# Quick Task 95: Add Separate Email Template for Contributie Summary

Separate email template for membership (contributie) invoices stored as new WP option, selected automatically based on invoice_type, independently editable in Finance Settings.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add membership email template to backend | c1877076 | class-finance-config.php, class-invoice-email-sender.php, class-rest-invoices.php, class-rest-api.php |
| 2 | Add membership template editor to FinanceSettings frontend | 9f98284e | src/pages/Finance/FinanceSettings.jsx |

## What Was Built

### Backend Changes

**FinanceConfig (`includes/class-finance-config.php`):**
- Added `OPTION_MEMBERSHIP_EMAIL_TEMPLATE = 'rondo_finance_membership_email_template'` constant
- Added default template in DEFAULTS array — clean contributie text without tuchtcommissie references
- Added `get_membership_email_template()` getter method
- Added `membership_email_template` to `get_all_settings()` and `get_setting()` switch
- Added `update_settings()` handler with `wp_kses_post()` sanitization

**InvoiceEmailSender (`includes/class-invoice-email-sender.php`):**
- Added optional `template` key to `$options` parameter (documented in docblock)
- Changed template resolution: `$options['template'] ?? $config->get_email_template()` — callers can pass a custom template, discipline template remains the default

**REST Invoices (`includes/class-rest-invoices.php`):**
- `send_invoice()`: reads `invoice_type` ACF field and adds membership template to `$email_options` when `'membership' === $invoice_type`. Reuses already-created `$finance_config` instance.
- `resend_invoice()`: same logic — creates a new FinanceConfig instance (isolated method, no shared instance available)

**REST API (`includes/class-rest-api.php`):**
- Added `membership_email_template` arg to finance settings endpoint with `wp_kses_post` sanitize callback

### Frontend Changes

**FinanceSettings (`src/pages/Finance/FinanceSettings.jsx`):**
- Added `membership_email_template: ''` to initial formData state
- Added `membership_email_template` to useEffect settings loader
- Added `membership_email_template` to handleSubmit payload
- Updated discipline card subtitle from "facturen" to "boete-facturen" for clarity
- Added new "Template e-mail voor contributie" card between discipline and installment sections
- Card uses RichTextEditor with contributie-specific variable list (no tuchtzaken_lijst)

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- `php -l` passed on all 4 modified PHP files
- `npm run build` succeeded
- `npm run lint` passed with 0 warnings
- Deployed to production: https://rondo.svawc.nl/

## Self-Check: PASSED

Files verified:
- `includes/class-finance-config.php` — FOUND, contains `OPTION_MEMBERSHIP_EMAIL_TEMPLATE`
- `includes/class-invoice-email-sender.php` — FOUND, contains `$options['template']`
- `includes/class-rest-invoices.php` — FOUND, contains membership template selection
- `includes/class-rest-api.php` — FOUND, contains `membership_email_template` arg
- `src/pages/Finance/FinanceSettings.jsx` — FOUND, contains membership template card

Commits verified:
- `c1877076` — feat(quick-95): add membership email template to backend
- `9f98284e` — feat(quick-95): add membership template editor to Finance Settings
