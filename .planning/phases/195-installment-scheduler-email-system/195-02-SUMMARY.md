---
phase: 195-installment-scheduler-email-system
plan: 02
subsystem: payments
tags: [php, wordpress-cron, wp-mail, installment, email, mollie, FinanceConfig, scheduler]

# Dependency graph
requires:
  - phase: 195-01
    provides: _installment_N_due_date Y-m-d meta on invoices; three email templates via FinanceConfig getters
  - phase: 194-payment-plan-manager-webhook-extension
    provides: InstallmentPaymentService::create_payment for fresh Mollie link creation
  - phase: 192-invoice-infrastructure
    provides: rondo_invoice CPT, _installment_N_* meta schema, InvoiceEmailSender pattern

provides:
  - InstallmentEmailSender — three public static methods for initial send, reminder 1, reminder 2 with fresh Mollie payment links per email
  - InstallmentScheduler — daily WP-Cron sweeper (rondo_installment_sweeper) querying rondo_sent invoices with payment plans
  - Automated installment due-date email delivery (pending + due today or past)
  - First reminder at 14 days overdue (status=sent, no reminder_1_sent_at)
  - Second reminder at 21 days overdue (status=sent, no reminder_2_sent_at) with BCC to treasurer
  - Idempotency: transient lock per sweep + sent timestamps per installment prevent duplicates
  - Version 27.4.0 deployed to production

affects:
  - 196-bulk-invoice-creation — can query installment status to understand full pipeline
  - 197-billing-method-toggle — plan-enable toggles control which plan options appear

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "InstallmentEmailSender: static methods pattern — no instance state, all dependencies resolved via new FinanceConfig() inline"
    - "Status written BEFORE wp_mail for idempotency — if cron re-runs, status already 'sent' so initial email won't re-trigger"
    - "Reminder 2 checked before reminder 1 in sweeper — >= 21 days also satisfies >= 14 days, check order prevents double-send on day 21+"
    - "try/catch \Throwable per invoice in sweeper — one invoice failure never stops the full sweep"
    - "Cron lifecycle: after_switch_theme schedules, switch_theme unschedules (same as Calendar\\Sync)"

key-files:
  created:
    - includes/class-installment-email-sender.php
    - includes/class-installment-scheduler.php
  modified:
    - functions.php

key-decisions:
  - "Status 'sent' and sent_at written BEFORE wp_mail (not after) — prevents duplicate emails if cron fires twice; acceptable tradeoff vs. failed send tracking"
  - "Fresh Mollie payment link created per email method — links expire, so initial send, reminder 1, and reminder 2 each create their own payment"
  - "Reminder 2 checked BEFORE reminder 1 in decision tree — avoids sending both when overdue >= 21 days (21 days also satisfies 14-day threshold)"
  - "Dutch month names array in InstallmentEmailSender — avoids intl extension dependency which isn't guaranteed on all WP hosts"
  - "Sweeper manually scheduled via wp eval-file after deploy — after_switch_theme doesn't fire on rsync deploys"

patterns-established:
  - "Installment email flow: create_payment() first, write timestamp, resolve_and_send() — payment failure aborts before any state is written"
  - "Dutch long date formatting: day + dutch_months[month] + year array lookup (no intl)"

# Metrics
duration: 10min
completed: 2026-02-19
---

# Phase 195 Plan 02: Installment Scheduler & Email System Summary

**Daily WP-Cron sweeper (rondo_installment_sweeper) with InstallmentEmailSender automating installment due-date emails and 14/21-day overdue reminders via fresh Mollie payment links**

## Performance

- **Duration:** 10 min
- **Started:** 2026-02-19T08:34:04Z
- **Completed:** 2026-02-19T08:44:00Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments

- `InstallmentEmailSender` created with three public static methods (`send_installment_email`, `send_reminder_1`, `send_reminder_2`) — each creates a fresh Mollie payment link before composing the email using FinanceConfig templates
- `InstallmentScheduler` daily cron sweeper queries all `rondo_sent` invoices with payment plans (not 'full'), iterates each installment's status and due date, and dispatches the correct email action
- Idempotency at two levels: transient lock prevents concurrent sweeper runs; sent timestamps written before `wp_mail()` prevent duplicate emails on re-runs
- Deployed to production, cron event manually scheduled (`rondo_installment_sweeper` daily, next run 2026-02-20 00:00:00)
- Version bumped to 27.4.0, CHANGELOG updated

## Task Commits

1. **Task 1: Create InstallmentEmailSender class** - `1042ead6` (feat)
2. **Task 2: Create InstallmentScheduler cron sweeper and wire everything** - `6ebd28c2` (feat)

## Files Created/Modified

- `includes/class-installment-email-sender.php` — Three public static methods for initial installment email, reminder 1, reminder 2; private `resolve_and_send()` helper with Dutch date/currency formatting, template variable substitution, wp_mail sending
- `includes/class-installment-scheduler.php` — Daily cron sweeper with transient lock, `process_invoices()` WP_Query for rondo_sent invoices with plans, `process_invoice()` decision tree with initial/reminder 1/reminder 2 logic
- `functions.php` — Added `use` statements for `InstallmentEmailSender` and `InstallmentScheduler`; instantiated `new InstallmentScheduler()` after `PublicPaymentPage`
- `style.css` — Version bumped to 27.4.0
- `package.json` — Version bumped to 27.4.0
- `CHANGELOG.md` — Added 27.4.0 entry with all new features

## Decisions Made

- **Status written before wp_mail:** Idempotency takes priority over retry tracking. A failed `wp_mail()` leaves the status as 'sent' — this is acceptable; a failed `create_payment()` returns early without writing status, so the sweeper will retry the next day.
- **Fresh payment links per email:** Mollie checkout links expire, so each of the three email types calls `InstallmentPaymentService::create_payment()` independently rather than reusing a stored link.
- **Reminder 2 checked before reminder 1:** The 21-day threshold also satisfies the 14-day check. Checking reminder 2 first ensures exactly one reminder is sent per overdue period, not two.
- **Dutch month names via static array:** Avoids `intl` extension dependency; simpler and more portable across WP hosting environments.
- **Manual cron scheduling after deploy:** `after_switch_theme` doesn't fire on rsync-based deploys. Used `wp eval-file` to register the event immediately after deploy.

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

**Cron registration via wp eval with escaped characters failed:** The plan suggested using `wp eval` with inline PHP containing backslashes (e.g., `\"rondo_installment_sweeper\"`), but the shell escaping caused a PHP parse error. Used `wp eval-file /tmp/schedule_cron.php` instead — wrote a temp file and executed it. Result: same outcome, cron registered successfully.

## User Setup Required

None — no external service configuration required. Cron event is registered on production.

## Self-Check: PASSED

- `includes/class-installment-email-sender.php` — FOUND
- `includes/class-installment-scheduler.php` — FOUND
- Commit `1042ead6` — FOUND
- Commit `6ebd28c2` — FOUND
- Version 27.4.0 in style.css — FOUND
- Version 27.4.0 in package.json — FOUND
- CHANGELOG 27.4.0 entry — FOUND
- Production cron event registered — VERIFIED

## Next Phase Readiness

- Phase 196 (Bulk Invoice Creation) is ready: the full installment pipeline is now operational end-to-end (plan selection → Mollie payment → webhook → next installment → due-date email → overdue reminders)
- Phase 197 (Billing Method Toggle) can proceed: plan-enable toggles control which payment options appear on the landing page; the scheduler handles whichever plans are active

---
*Phase: 195-installment-scheduler-email-system*
*Completed: 2026-02-19*
