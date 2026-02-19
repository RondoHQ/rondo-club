---
phase: 195-installment-scheduler-email-system
plan: 01
subsystem: payments
tags: [php, react, wordpress-options, FinanceConfig, installment, email-templates, rest-api]

# Dependency graph
requires:
  - phase: 193-public-payment-landing-page
    provides: PublicPaymentPage with write_installment_meta() and plan selection POST handler
  - phase: 194-payment-plan-manager-webhook-extension
    provides: InstallmentPaymentService, MollieWebhook dual-path; established installment meta schema

provides:
  - "_installment_N_due_date" Y-m-d meta written at plan selection time for each installment
  - calculate_installment_due_dates() — season-aware due date computation for quarterly_3 and monthly_8 plans
  - Three FinanceConfig option constants: OPTION_INSTALLMENT_EMAIL_TEMPLATE, OPTION_REMINDER_1_EMAIL_TEMPLATE, OPTION_REMINDER_2_EMAIL_TEMPLATE
  - Three getter methods and Dutch HTML defaults for installment, reminder_1, reminder_2 templates
  - REST POST /rondo/v1/finance/settings accepts installment_email_template, reminder_1_email_template, reminder_2_email_template, and installment_admin_fee
  - Finance Settings E-mail tab: four RichTextEditor sections (discipline + installment + reminder_1 + reminder_2)

affects:
  - 195-02 (daily cron sweeper) — reads _installment_N_due_date and all three email templates via FinanceConfig

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Season derivation from post_date uses MembershipFees::get_season_key() — same pattern as render_page()"
    - "calculate_installment_due_dates() is private static — pure function, no instance state"
    - "wp_kses_post() sanitization for all HTML email templates — consistent with existing email_template"

key-files:
  created: []
  modified:
    - includes/class-public-payment-page.php
    - includes/class-finance-config.php
    - includes/class-rest-api.php
    - src/pages/Finance/FinanceSettings.jsx

key-decisions:
  - "Due dates are hardcoded as the 25th of each month — no admin configuration, matches fixed Dutch football season structure"
  - "quarterly_3 due dates: Sep 25, Nov 25, Feb 25 of start/end year; monthly_8: Sep 25 through Apr 25"
  - "Season extracted from invoice post_date (not ACF field) via MembershipFees::get_season_key() — same approach as render_page()"
  - "installment_admin_fee REST arg was missing (auto-fix Rule 1 bug) — added alongside the three new template args"
  - "Reminder cards share a single variable docs box — reduces duplication since reminder_1 and reminder_2 use the same variable set"

patterns-established:
  - "calculate_installment_due_dates(): private static method, returns int=>string array, returns [] for unknown plans"

# Metrics
duration: 5min
completed: 2026-02-19
---

# Phase 195 Plan 01: Installment Due Dates and Email Templates Summary

**Installment due dates written at plan selection (Y-m-d per installment) and three configurable Dutch HTML email templates added to FinanceConfig, REST API, and Finance Settings E-mail tab**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-19T08:26:00Z
- **Completed:** 2026-02-19T08:31:12Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- `write_installment_meta()` now writes `_installment_N_due_date` in Y-m-d format for every installment using season-derived dates (25th of each month, Sep-Apr)
- Three new email template options (installment, reminder_1, reminder_2) added to `FinanceConfig` with Dutch HTML defaults, getters, `get_all_settings()`, `get_setting()`, and `update_settings()` support
- REST API POST `/rondo/v1/finance/settings` accepts all three new template fields (plus missing `installment_admin_fee` auto-fix)
- Finance Settings E-mail tab now shows four RichTextEditor sections with Dutch labels and variable documentation boxes
- Deployed to production at https://rondo.svawc.nl/

## Task Commits

1. **Task 1: Extend write_installment_meta with due date calculation** - `9f02c9fb` (feat)
2. **Task 2: Add three email templates to FinanceConfig, REST API, and React UI** - `02ba0406` (feat)

## Files Created/Modified

- `includes/class-public-payment-page.php` — Added `calculate_installment_due_dates()` private static method and due date writes inside `write_installment_meta()`
- `includes/class-finance-config.php` — Three new OPTION_ constants, three Dutch HTML defaults, three getter methods, extended `get_all_settings()`, `get_setting()` switch, and `update_settings()`
- `includes/class-rest-api.php` — Added `installment_email_template`, `reminder_1_email_template`, `reminder_2_email_template`, and `installment_admin_fee` to POST `/finance/settings` args
- `src/pages/Finance/FinanceSettings.jsx` — Added three fields to `formData` initial state, `useEffect` loader, `handleSubmit` payload, and three new RichTextEditor card sections in the E-mail tab

## Decisions Made

- Due dates are the 25th of each relevant month — aligns with Dutch football season cadence, no admin config needed
- Season derived from invoice `post_date` via `MembershipFees::get_season_key()` — same approach already used in `render_page()` and `render_success_page()`
- Reminder templates share one variable docs box in the UI (avoids duplication since they share the same variable set)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Added missing `installment_admin_fee` REST arg**
- **Found during:** Task 2 (REST API update)
- **Issue:** The `installment_admin_fee` field was handled by `FinanceConfig::update_settings()` but was never registered as a REST arg in the `/finance/settings` POST route, meaning it could never be saved from the UI
- **Fix:** Added `'installment_admin_fee' => [ 'required' => false, 'type' => 'number' ]` alongside the three new template args
- **Files modified:** `includes/class-rest-api.php`
- **Verification:** Arg present in route definition, `FinanceConfig::update_settings()` already handles it
- **Committed in:** `02ba0406` (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug: missing REST arg)
**Impact on plan:** Necessary correctness fix. The installment admin fee was silently not saveable from the React UI. No scope creep.

## Issues Encountered

None — plan executed cleanly with one auto-fixed bug.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Phase 195-02 (daily cron sweeper) is ready to proceed: `_installment_N_due_date` is now written at plan selection time, and all three email templates are retrievable via `FinanceConfig::get_installment_email_template()`, `get_reminder_1_email_template()`, `get_reminder_2_email_template()`
- Production deployed and verified

---
*Phase: 195-installment-scheduler-email-system*
*Completed: 2026-02-19*
