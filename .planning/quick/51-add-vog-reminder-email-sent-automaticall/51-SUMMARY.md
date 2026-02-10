---
phase: quick-51
plan: 51
subsystem: vog-reminders
tags: [vog, email, cron, automation]
dependency-graph:
  requires: [vog-email-system]
  provides: [vog-reminder-automation]
  affects: [vog-workflow]
tech-stack:
  added: [wp-cron-daily]
  patterns: [template-substitution, meta-query-date-comparison]
key-files:
  created: []
  modified:
    - includes/class-vog-email.php
    - includes/class-rest-api.php
    - src/pages/VOG/VOGSettings.jsx
    - functions.php
decisions:
  - Daily cron sweep pattern (vs per-person scheduling)
  - LIKE comparison for date matching (handles datetime format)
  - Separate send_reminder() method (vs extending send())
  - Same from_email/from_name for reminders (vs separate settings)
metrics:
  duration: 226s
  tasks-completed: 2
  files-modified: 4
  commits: 2
completed: 2026-02-10T21:52:57Z
---

# Quick Task 51: Add VOG Reminder Email Summary

**One-liner:** Automated VOG reminder emails sent 7 days after Justis submission using daily cron with template variables for email_sent_date, justis_date, and previous_vog_date

## Tasks Completed

### Task 1: Extend VOGEmail class with reminder templates
- Added `OPTION_REMINDER_TEMPLATE_NEW` and `OPTION_REMINDER_TEMPLATE_RENEWAL` constants
- Added getter methods `get_reminder_template_new()` and `get_reminder_template_renewal()` with fallback to defaults
- Added update methods `update_reminder_template_new()` and `update_reminder_template_renewal()`
- Created default Dutch templates with variables: `{first_name}`, `{email_sent_date}`, `{justis_date}`, `{previous_vog_date}` (renewal only)
- Added `send_reminder()` method that:
  - Validates template type ('reminder_new' or 'reminder_renewal')
  - Retrieves person email from ACF contact_info
  - Builds substitution vars including formatted dates
  - Sends email using same from_email/from_name as initial VOG emails
  - Records `vog_reminder_sent_date` in post meta
  - Logs to timeline via `CommentTypes::create_email_log()`
- Added `schedule_reminder_cron()` static method to schedule daily cron at 08:00
- Added `unschedule_reminder_cron()` static method for theme deactivation
- Added `process_pending_reminders()` method that:
  - Queries people with `vog_justis_submitted_date` exactly 7 days ago (LIKE comparison handles datetime format)
  - Filters for `vog_reminder_sent_date` NOT EXISTS
  - Determines template type based on presence of `datum-vog` field
  - Sends reminder and returns count
- Added `__construct()` to register `rondo_vog_reminder_check` action
- Initialized VOGEmail in `rondo_init()` for cron hook registration
- Called `schedule_reminder_cron()` in theme activation
- Called `unschedule_reminder_cron()` in theme deactivation
- **Commit:** f3e69c26

### Task 2: Extend settings REST API & React UI
- Extended REST API `/rondo/v1/vog/settings` endpoint:
  - Added `reminder_template_new` and `reminder_template_renewal` params to args schema
  - Updated `update_vog_settings()` to handle reminder template params
  - Templates are included in `get_all_settings()` response
- Updated React `VOGSettings.jsx`:
  - Added `reminder_template_new` and `reminder_template_renewal` to state
  - Added "Herinnering templates" section with description of automatic 7-day send
  - Added textarea for "Herinnering template nieuwe vrijwilliger" with vars: `{first_name}`, `{email_sent_date}`, `{justis_date}`
  - Added textarea for "Herinnering template verlenging" with vars: `{first_name}`, `{email_sent_date}`, `{justis_date}`, `{previous_vog_date}`
- Built frontend assets
- **Commit:** 0de14976

## Architecture Decisions

### Daily cron sweep vs per-person scheduling
**Chosen:** Daily cron sweep at 08:00 checks all people with Justis date 7 days ago

**Why:**
- Simpler implementation and maintenance
- No risk of orphaned scheduled events (WordPress cron can miss individual events)
- Automatic catch-up for any missed reminders
- Follows same pattern as existing `rondo_daily_reminder_check`

**Alternative considered:** Schedule individual event per person when Justis date is set
- More complex: requires save_post hook, event cleanup, handling date changes
- Risk of orphaned events if Justis date is updated
- No clear benefit over daily sweep

### Meta query date comparison strategy
**Chosen:** LIKE comparison with DATE type

**Rationale:**
- `vog_justis_submitted_date` stored as 'Y-m-d H:i:s' (datetime)
- LIKE with 'Y-m-d' pattern matches any time on that date
- DATE type ensures proper date arithmetic
- Handles both date and datetime formats gracefully

### Separate send_reminder() method
**Chosen:** New method `send_reminder()` instead of extending `send()`

**Why:**
- Different template types: 'reminder_new' vs 'new', 'reminder_renewal' vs 'renewal'
- Different variables: reminders include `{email_sent_date}` and `{justis_date}`
- Different subjects: "VOG herinnering" vs "VOG aanvraag"
- Clear separation of concerns: initial emails vs follow-up reminders
- Avoids validation complexity in single method

### From email/name settings
**Chosen:** Reuse existing `from_email` and `from_name` for reminders

**Why:**
- Constraint specified: "should use the SAME from_email and from_name"
- Single source of truth for VOG sender identity
- Simpler UI and configuration
- No user confusion about which address is used when

## Deviations from Plan

None - plan executed exactly as written.

## Verification Checklist

- [x] `npm run build` succeeds
- [x] Settings UI shows reminder template fields with proper section heading
- [x] Default templates are sensible Dutch text with correct variables
- [x] Cron hook registered on theme initialization via `__construct()`
- [x] Cron scheduled at 08:00 daily on theme activation
- [x] Cron unscheduled on theme deactivation
- [x] Meta query uses LIKE comparison with 7-days-ago date
- [x] `vog_reminder_sent_date` prevents duplicate sends (NOT EXISTS in query)
- [x] Template type determined by presence of `datum-vog` field
- [x] Reminder email uses correct subject based on template type
- [x] Timeline logging via `CommentTypes::create_email_log()`
- [x] Deployed to production

## Files Modified

| File | Changes |
|------|---------|
| `includes/class-vog-email.php` | Added 2 constants, 6 methods (getters/setters/send_reminder), 2 default templates, 3 cron methods, constructor |
| `includes/class-rest-api.php` | Added 2 REST params, handler logic for reminder templates |
| `src/pages/VOG/VOGSettings.jsx` | Added 2 state fields, section heading, 2 textarea fields with variable hints |
| `functions.php` | Initialize VOGEmail in rondo_init(), schedule/unschedule in activation/deactivation |

## Commits

| Hash | Message |
|------|---------|
| f3e69c26 | feat(quick-51): add VOG reminder email templates and cron processing |
| 0de14976 | feat(quick-51): add reminder template fields to VOG settings UI |

## Self-Check: PASSED

### Files Created
All modifications to existing files - no new files created.

### Files Modified
- [x] `includes/class-vog-email.php` exists and contains send_reminder(), process_pending_reminders()
- [x] `includes/class-rest-api.php` exists and handles reminder_template_new/renewal params
- [x] `src/pages/VOG/VOGSettings.jsx` exists and renders reminder template fields
- [x] `functions.php` exists and calls VOGEmail::schedule_reminder_cron()

### Commits Exist
- [x] f3e69c26: feat(quick-51): add VOG reminder email templates and cron processing
- [x] 0de14976: feat(quick-51): add reminder template fields to VOG settings UI

### Deployment
- [x] Deployed to production: https://stadion.svawc.nl/

All verifications passed.
