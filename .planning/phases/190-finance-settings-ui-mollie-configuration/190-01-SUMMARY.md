---
phase: 190-finance-settings-ui-mollie-configuration
plan: 01
subsystem: ui
tags: [react, mollie, finance, settings, rest-api, payments]

# Dependency graph
requires:
  - phase: 189-rest-invoices-provider-routing
    provides: Provider routing infrastructure that routes invoices to Mollie or Rabobank
  - phase: 186-mollie-sdk-finance-config
    provides: mollie_has_api_key and mollie_environment in get_all_settings() response
provides:
  - Mollie tab in Finance Settings with masked API key input and test/live badge
  - Payment provider selector (Rabobank/Mollie) in Betaling tab
  - REST args for mollie_api_key and active_payment_provider registered in update_finance_settings
  - Version 27.0.0 shipped with complete Mollie milestone changelog
affects: [finance-settings, mollie-payments, future-provider-config]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Masked API key: type=password input, never populated from API response, conditionally included in payload"
    - "Environment badge: derived from mollie_environment string, shown only when mollie_has_api_key is true"
    - "Credential clear: credential fields reset to empty string after successful save"

key-files:
  created: []
  modified:
    - src/pages/Finance/FinanceSettings.jsx
    - includes/class-rest-api.php
    - style.css
    - package.json
    - CHANGELOG.md

key-decisions:
  - "Mollie API key never populated from API response — only active_payment_provider is loaded from settings (security)"
  - "mollie_api_key conditionally included in payload only when non-empty (preserves existing encrypted key)"
  - "Environment badge derived from settings.mollie_environment string (live/test), rendered only when mollie_has_api_key"
  - "club_logo_id, accent_color, bcc_email REST args registered (they were used but unregistered — Rule 2 fix)"
  - "Version 27.0.0 closes out the full v27.0 Mollie milestone (Phases 186-190)"

patterns-established:
  - "Credential handling pattern: init to empty, load only non-sensitive from API, conditional payload inclusion, clear after save"

# Metrics
duration: 3min
completed: 2026-02-18
---

# Phase 190 Plan 01: Finance Settings UI — Mollie Configuration Summary

**Mollie tab with masked API key input and test/live environment badge, payment provider radio selector in Betaling tab, v27.0.0 shipped**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-02-18T07:05:38Z
- **Completed:** 2026-02-18T07:09:05Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Finance Settings now has 5 tabs: Organisatie, Betaling, E-mail, Rabobank, Mollie
- Mollie tab shows masked API key input (type=password), environment badge (Test/Live derived from `mollie_environment`), and existing key notice (shown only when `mollie_has_api_key` is true)
- Betaling tab has payment provider radio selector (Rabobank / Mollie) with `active_payment_provider` field
- REST route `update_finance_settings` now registers `mollie_api_key` and `active_payment_provider` args (plus `club_logo_id`, `accent_color`, `bcc_email` which were missing)
- Version bumped to 27.0.0 with complete CHANGELOG entry covering the full Mollie milestone (Phases 186–190)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Mollie tab UI, provider selector, and REST args** - `51f35498` (feat)
2. **Task 2: Version bump and changelog** - `91ccb0d2` (chore)

**Plan metadata:** TBD (docs: complete plan)

## Files Created/Modified
- `src/pages/Finance/FinanceSettings.jsx` - Added Mollie tab, provider selector in Betaling tab, all formData/payload/cleanup wiring
- `includes/class-rest-api.php` - Registered mollie_api_key, active_payment_provider, club_logo_id, accent_color, bcc_email REST args
- `style.css` - Version 27.0.0
- `package.json` - Version 27.0.0
- `CHANGELOG.md` - v27.0.0 entry covering full Mollie milestone

## Decisions Made
- Mollie API key never populated from API response — security boundary: key goes in, bool comes out
- `club_logo_id`, `accent_color`, and `bcc_email` REST args added (Rule 2: they were being used without registration)
- Environment badge only shown when `mollie_has_api_key` is true — avoids confusing "Test" badge when no key stored

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Registered missing REST args: club_logo_id, accent_color, bcc_email**
- **Found during:** Task 1 (reading the plan's explicit instruction to add them)
- **Issue:** These fields were sent in the payload but not registered in the REST args array
- **Fix:** Added `club_logo_id`, `accent_color`, `bcc_email` to the `update_finance_settings` args
- **Files modified:** `includes/class-rest-api.php`
- **Verification:** Grep confirms all args present; build passes
- **Committed in:** `51f35498` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 missing critical — the plan explicitly specified these args)
**Impact on plan:** The plan already specified adding these args as part of Task 1. No scope creep.

## Issues Encountered
None — plan executed cleanly. Both tasks compiled without errors on first attempt.

## User Setup Required
None — no external service configuration required. Administrators can now navigate to Finance Settings > Mollie tab to enter their API key.

## Next Phase Readiness
- v27.0 Mollie milestone is complete — all phases (186–190) shipped
- Administrators can now configure Mollie API key and select payment provider
- Full end-to-end flow: configure key → switch provider → send invoice → Mollie payment link generated → webhook updates status on payment

## Self-Check: PASSED

All files verified present. Both task commits (51f35498, 91ccb0d2) confirmed in git history.

---
*Phase: 190-finance-settings-ui-mollie-configuration*
*Completed: 2026-02-18*
