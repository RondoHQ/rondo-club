---
phase: quick-118
plan: 01
subsystem: finance
tags: [cron, email, reminders, membership-invoices, finance-settings]
dependency_graph:
  requires: [class-installment-scheduler.php, class-installment-email-sender.php, class-finance-config.php]
  provides: [invoice-reminder-emails, invoice-reminder-cron-sweeper, invoice-reminder-templates-ui]
  affects: [Finance Settings E-mail tab, daily cron jobs, membership invoice workflow]
tech_stack:
  added: []
  patterns: [cron-sweeper-pattern, email-sender-pattern, finance-config-options]
key_files:
  created:
    - includes/class-invoice-reminder-scheduler.php
    - includes/class-invoice-reminder-sender.php
  modified:
    - includes/class-finance-config.php
    - functions.php
    - src/pages/Finance/FinanceSettings.jsx
    - style.css
    - package.json
    - CHANGELOG.md
decisions:
  - "Used payment_link ACF field (already on invoice) rather than creating new Mollie links — the /betaling/{token} URL is valid indefinitely"
  - "Check reminder 2 (28 days) BEFORE reminder 1 (14 days) — same pattern as InstallmentScheduler to avoid false reminder 1 on day 28+"
  - "Idempotency via _invoice_reminder_1_sent_at and _invoice_reminder_2_sent_at written BEFORE wp_mail to prevent duplicates on re-run"
  - "Meta query uses NOT EXISTS for _installment_plan to find members who never visited the payment page"
  - "Renamed Herinneringen sub-tab to Termijnherinneringen to distinguish from new Factuurherinneringen tab"
metrics:
  duration: "~15 minutes"
  completed: "2026-02-22"
  tasks_completed: 3
  files_created: 2
  files_modified: 6
---

# Quick Task 118: Invoice Reminder Emails for Contributie Invoices Summary

**One-liner:** Daily cron sweeper sends 14-day and 28-day reminder emails to members who received a membership invoice but haven't selected a payment plan, with configurable HTML templates in Finance Settings.

## Tasks Completed

| # | Task | Commit | Key Files |
|---|------|--------|-----------|
| 1 | Create InvoiceReminderScheduler and InvoiceReminderSender backend classes | cea0642b | includes/class-invoice-reminder-scheduler.php, includes/class-invoice-reminder-sender.php, includes/class-finance-config.php, functions.php |
| 2 | Add Factuurherinneringen sub-tab to Finance Settings UI | 881b2c59 | src/pages/Finance/FinanceSettings.jsx |
| 3 | Version bump, changelog, deploy, and cron scheduling | a577c542 | style.css, package.json, CHANGELOG.md |

## What Was Built

### Backend

**`InvoiceReminderScheduler`** (`includes/class-invoice-reminder-scheduler.php`)
- Daily WP-Cron event: `rondo_invoice_reminder_sweeper`
- Transient lock: `rondo_invoice_reminder_sweeper_lock` (5-minute TTL)
- Queries `rondo_invoice` posts with `post_status = rondo_sent`, `invoice_type = membership`, and `_installment_plan NOT EXISTS`
- For each invoice: reads `sent_date` ACF field (Ymd format), calculates days since sent
- Sends reminder 2 (28+ days) before checking reminder 1 (14+ days) — same order as InstallmentScheduler
- Registered in `functions.php` alongside `InstallmentScheduler`

**`InvoiceReminderSender`** (`includes/class-invoice-reminder-sender.php`)
- `send_reminder_1($invoice_id)`: subject prefix "Herinnering", no BCC, uses `invoice_reminder_1_email_template`
- `send_reminder_2($invoice_id)`: subject prefix "Tweede herinnering", BCC to treasurer, uses `invoice_reminder_2_email_template`
- Reads `payment_link` ACF field from invoice — the `/betaling/{token}` URL is already valid and doesn't expire
- Template variables: `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{betaallink}`, `{factuurdatum}`, `{dagen_sinds_factuur}`, `{organisatie_naam}`
- Idempotency timestamps written BEFORE `wp_mail()`: `_invoice_reminder_1_sent_at`, `_invoice_reminder_2_sent_at`

**`FinanceConfig` additions** (`includes/class-finance-config.php`)
- Two new option constants: `OPTION_INVOICE_REMINDER_1_EMAIL_TEMPLATE`, `OPTION_INVOICE_REMINDER_2_EMAIL_TEMPLATE`
- Two Dutch-language default templates with all supported variables
- Getter methods: `get_invoice_reminder_1_email_template()`, `get_invoice_reminder_2_email_template()`
- Updated `get_all_settings()`, `get_setting()`, `update_settings()` to expose and persist new fields

### Frontend

**Finance Settings** (`src/pages/Finance/FinanceSettings.jsx`)
- Added `factuur_herinneringen` to `EMAIL_SUB_TABS` array
- Renamed `'Herinneringen'` tab to `'Termijnherinneringen'` for clarity
- New `Factuurherinneringen` tab with two `RichTextEditor` fields (first/second reminder)
- Variables reference box listing all 8 supported template variables
- Settings wired through `formData` initial state, `useEffect` load, and `handleSubmit` payload

### Production

- Version bumped: 30.0.0 → 30.1.0 (MINOR — new feature)
- Changelog entry added for 30.1.0
- Deployed to production: https://rondo.svawc.nl/
- Cron event manually scheduled: `rondo_invoice_reminder_sweeper` next run 2026-02-23 16:19:11 GMT, recurs daily

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

Files verified:
- FOUND: includes/class-invoice-reminder-scheduler.php
- FOUND: includes/class-invoice-reminder-sender.php
- FOUND: includes/class-finance-config.php (updated)
- FOUND: functions.php (updated)
- FOUND: src/pages/Finance/FinanceSettings.jsx (updated)

Commits verified:
- FOUND: cea0642b — feat(quick-118): add InvoiceReminderScheduler and InvoiceReminderSender backend classes
- FOUND: 881b2c59 — feat(quick-118): add Factuurherinneringen sub-tab to Finance Settings email templates
- FOUND: a577c542 — chore(quick-118): bump version to 30.1.0 and update changelog

Cron verified on production: rondo_invoice_reminder_sweeper scheduled daily
