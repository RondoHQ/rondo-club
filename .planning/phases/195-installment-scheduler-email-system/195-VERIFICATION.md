---
phase: 195-installment-scheduler-email-system
verified: 2026-02-19T08:52:00Z
status: passed
score: 8/8 must-haves verified
re_verification: false
---

# Phase 195: Installment Scheduler & Email System Verification Report

**Phase Goal:** Members receive their installment payment emails automatically on the 25th of each scheduled month, with escalating overdue reminders if they don't pay, without any manual treasurer action.
**Verified:** 2026-02-19T08:52:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | On the 25th of a scheduled installment month, members with an unpaid installment due that day receive an email with a fresh Mollie payment link | VERIFIED | `InstallmentScheduler.process_invoice()` checks `$status === 'pending' && $today >= $due_date`; calls `InstallmentEmailSender::send_installment_email()` which calls `InstallmentPaymentService::create_payment()` first |
| 2 | A member who has not paid 14 days after an installment due date receives a first reminder email automatically | VERIFIED | Sweeper checks `$days_overdue >= 14 && empty($reminder_1_sent_at)` and calls `send_reminder_1()` with fresh Mollie link |
| 3 | A member who has still not paid 21 days after the due date receives a second reminder email, and the treasurer receives a BCC | VERIFIED | Sweeper checks reminder 2 before reminder 1 (`$days_overdue >= 21`); `send_reminder_2()` passes `$add_bcc = true`; `resolve_and_send()` adds `Bcc: $config->get_bcc_email()` header |
| 4 | Admin can configure separate email templates for the initial invoice, installment follow-up emails, and overdue reminders via Finance Settings | VERIFIED | Three `RichTextEditor` sections in Finance Settings E-mail tab: "Template termijnbetaling" (installment), "Template eerste herinnering" (reminder 1), "Template tweede herinnering" (reminder 2) with Dutch labels and variable docs |
| 5 | The scheduler uses a single daily WP-Cron sweeper (not per-invoice scheduled events) | VERIFIED | `InstallmentScheduler` registers ONE `rondo_installment_sweeper` hook with `wp_schedule_event(..., 'daily', ...)`. No per-invoice events created anywhere |
| 6 | Installment due dates are written at plan selection time so the sweeper can read them | VERIFIED | `write_installment_meta()` in `class-public-payment-page.php` calls `calculate_installment_due_dates()` and writes `_installment_N_due_date` (Y-m-d) for each installment |
| 7 | Email templates persist across page reloads via the REST API and WordPress options | VERIFIED | Three template keys in `FinanceConfig::get_all_settings()`, `get_setting()`, and `update_settings()` with `wp_kses_post` sanitization; REST POST `/rondo/v1/finance/settings` accepts all three fields |
| 8 | Duplicate emails are prevented by idempotency guards | VERIFIED | Transient lock (`rondo_installment_sweeper_lock`, 5-min TTL) prevents concurrent sweeps; `_installment_N_status` written to 'sent' before `wp_mail()`; reminder timestamps written before `wp_mail()` |

**Score:** 8/8 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-installment-email-sender.php` | Three public static send methods with fresh Mollie links | VERIFIED | 309 lines. `send_installment_email()`, `send_reminder_1()`, `send_reminder_2()` all call `InstallmentPaymentService::create_payment()`. `resolve_and_send()` helper handles template resolution, Dutch date/currency formatting, `wp_mail()` |
| `includes/class-installment-scheduler.php` | Daily cron sweeper querying rondo_sent invoices | VERIFIED | 278 lines. Constants `CRON_HOOK = 'rondo_installment_sweeper'`, `LOCK_TRANSIENT`. `run_sweep()` with transient lock, `process_invoices()` WP_Query, `process_invoice()` decision tree with correct reminder 2-before-1 ordering |
| `includes/class-finance-config.php` | Three new OPTION_ constants, getters, update_settings support | VERIFIED | `OPTION_INSTALLMENT_EMAIL_TEMPLATE`, `OPTION_REMINDER_1_EMAIL_TEMPLATE`, `OPTION_REMINDER_2_EMAIL_TEMPLATE` at lines 58-60. Three getter methods (lines 155-179). `get_all_settings()` includes all three (lines 275-277). `update_settings()` handles all three with `wp_kses_post` (lines 377-387) |
| `includes/class-rest-api.php` | REST args for three new template fields | VERIFIED | `installment_email_template`, `reminder_1_email_template`, `reminder_2_email_template` registered at lines 749-751. Bonus: `installment_admin_fee` auto-fixed at line 768 |
| `includes/class-public-payment-page.php` | `write_installment_meta` extended with due date calculation | VERIFIED | `calculate_installment_due_dates()` private static method at line 512. Due dates written inside for loop at line 494. Season derived via `MembershipFees::get_season_key()` at line 476 |
| `src/pages/Finance/FinanceSettings.jsx` | Three RichTextEditor sections on the E-mail tab | VERIFIED | Three fields in `formData` initial state (lines 141-143), `useEffect` loader (lines 172-174), `handleSubmit` payload (lines 269-271), three `RichTextEditor` instances (lines 637-638, 681-682, 697-698) |
| `functions.php` | Use statements and instantiation of InstallmentScheduler | VERIFIED | `use Rondo\Finance\InstallmentEmailSender` at line 81, `use Rondo\Finance\InstallmentScheduler` at line 82, `new InstallmentScheduler()` at line 415 |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `class-installment-scheduler.php` | `class-installment-email-sender.php` | `InstallmentEmailSender::send_installment_email`, `send_reminder_1`, `send_reminder_2` | WIRED | All three calls present in `process_invoice()` decision tree |
| `class-installment-email-sender.php` | `class-installment-payment-service.php` | `InstallmentPaymentService::create_payment()` | WIRED | Called in all three public methods (lines 72, 103, 134). Same `Rondo\Finance` namespace — no import needed |
| `class-installment-email-sender.php` | `class-finance-config.php` | `get_installment_email_template()`, `get_reminder_1_email_template()`, `get_reminder_2_email_template()` | WIRED | Each send method instantiates `new FinanceConfig()` and calls the matching getter. `use Rondo\Config\FinanceConfig` declared at line 15 |
| `class-installment-scheduler.php` | WP-Cron | `wp_schedule_event('daily', 'rondo_installment_sweeper')` | WIRED | `schedule_sweeper()` calls `wp_schedule_event(strtotime('tomorrow midnight'), 'daily', self::CRON_HOOK)` at line 62 |
| `functions.php` | `class-installment-scheduler.php` | `new InstallmentScheduler()` at line 415 | WIRED | Instantiated unconditionally in `rondo_init()`, ensuring cron hook registration on all requests |
| `src/pages/Finance/FinanceSettings.jsx` | `/rondo/v1/finance/settings` (REST POST) | `installment_email_template`, `reminder_1_email_template`, `reminder_2_email_template` in payload | WIRED | All three fields included in `handleSubmit` POST payload (lines 269-271) |
| `class-public-payment-page.php` | `class-membership-fees.php` | `MembershipFees::get_season_key()` to derive season from invoice `post_date` | WIRED | `$fees = new MembershipFees()` and `$fees->get_season_key($invoice_date)` at lines 474-476 |

### Requirements Coverage

| Requirement (Success Criterion) | Status | Notes |
|----------------------------------|--------|-------|
| 1. On the 25th, members with unpaid installment due receive email with fresh Mollie payment link | SATISFIED | Sweeper evaluates `$today >= $due_date` (due dates set to 25th of each month); `InstallmentEmailSender` creates fresh Mollie link before composing email |
| 2. Member who hasn't paid 14 days after due date receives first reminder automatically | SATISFIED | `$days_overdue >= 14` check in `process_invoice()` triggers `send_reminder_1()` with fresh link |
| 3. Member who hasn't paid 21 days after due date receives second reminder; treasurer receives BCC | SATISFIED | `$days_overdue >= 21` checked first; `send_reminder_2()` passes `$add_bcc = true`; `Bcc:` header added if `get_bcc_email()` configured |
| 4. Admin can configure separate email templates (installment, reminder 1, reminder 2) via Finance Settings | SATISFIED | Three `RichTextEditor` sections with Dutch labels, variable documentation boxes, and correct form wiring |
| 5. Scheduler uses single daily WP-Cron sweeper (not per-invoice scheduled events) | SATISFIED | One `rondo_installment_sweeper` event registered with `'daily'` recurrence; no per-invoice scheduling anywhere |

### Anti-Patterns Found

No blockers or warnings found.

| File | Pattern | Severity | Impact |
|------|---------|----------|--------|
| `class-installment-scheduler.php` | `error_log()` for success/failure logging | Info | Expected operational logging; not a stub |

### Human Verification Required

#### 1. End-to-End Email Delivery

**Test:** On a development environment, use `wp eval` to set an installment's due date to today and its status to 'pending', then trigger the sweeper manually with `wp cron event run rondo_installment_sweeper`. Check that the member receives an email with a working Mollie payment link.
**Expected:** Email delivered with correct template variables resolved, Mollie link valid and navigable.
**Why human:** `wp_mail()` delivery and Mollie API responses can only be confirmed in a live environment.

#### 2. BCC to Treasurer on Reminder 2

**Test:** Set an installment to `status = 'sent'` with a due date 21+ days ago and no `_installment_N_reminder_2_sent_at`. Run the sweeper. Verify the treasurer's BCC email address receives a copy.
**Expected:** Treasurer receives BCC of the second reminder email.
**Why human:** BCC delivery requires a live mail server test.

#### 3. Finance Settings E-mail Tab Rendering

**Test:** Navigate to Finance Settings in the browser. Switch to the E-mail tab. Confirm four template editors are visible: discipline email, installment payment email, first reminder, second reminder.
**Expected:** All four `RichTextEditor` sections render with correct Dutch labels and variable documentation. Templates load from saved values and persist after Save.
**Why human:** Visual rendering and form persistence require browser interaction.

#### 4. Cron Registered on Production

**Test:** SSH to production and run `wp cron event list --fields=hook,next_run,recurrence | grep installment`.
**Expected:** `rondo_installment_sweeper` appears with daily recurrence.
**Why human:** SUMMARY confirms manual registration via `wp eval-file`; persistence across WP restarts is a runtime check.

### Gaps Summary

No gaps. All five success criteria from the ROADMAP are met by verified, wired, substantive code. Phase goal is achieved.

---

_Verified: 2026-02-19T08:52:00Z_
_Verifier: Claude (gsd-verifier)_
