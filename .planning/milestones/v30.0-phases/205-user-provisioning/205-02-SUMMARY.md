---
phase: 205-user-provisioning
plan: 02
subsystem: ui
tags: [react, provisioning, settings, account-card, welcome-email, tanstack-query]

# Dependency graph
requires:
  - phase: 205-01
    provides: POST /rondo/v1/people/{id}/provision, GET/POST /rondo/v1/provisioning/settings, linked_user_id and welcome_email_sent_at on person REST response

provides:
  - AccountCard component: admin-only provisioning status card on PersonDetail second column
  - WelkomstmailTab: settings subtab for configuring from_email, from_name, subject, body
  - prmApi.provisionUser(), getProvisioningSettings(), updateProvisioningSettings() API client methods

affects:
  - 205-03 and beyond (AccountCard is the primary admin UI for triggering provisioning)
  - 206-capability-sync (admin verifies provisioned users via AccountCard)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "AccountCard follows VOGCard pattern: admin-gated, reads from personData prop, no separate data fetch"
    - "WelkomstmailTab follows FunctiesTab pattern: lazy-load on tab activation via useEffect guard (!welcomeSettings)"
    - "Query invalidation after provision: invalidate ['people', 'detail', personId] to refresh linked_user_id"

key-files:
  created:
    - src/components/AccountCard.jsx
  modified:
    - src/api/client.js
    - src/pages/People/PersonDetail.jsx
    - src/pages/Settings/Settings.jsx
    - CHANGELOG.md
    - style.css
    - package.json

key-decisions:
  - "AccountCard disabled state uses opacity-50 cursor-not-allowed (consistent with other disabled buttons)"
  - "WelkomstmailTab lazy-loads settings on first activation (not on component mount) to avoid unnecessary API calls"
  - "Success result state replaces button temporarily (without full page reload) — linked_user_id refetch handles permanent state update"

patterns-established:
  - "Admin-gated card pattern: check config.isAdmin and return null early if not admin"
  - "Provisioning API methods: prmApi.provisionUser(personId) convention for person-scoped actions"

# Metrics
duration: 6min
completed: 2026-02-20
---

# Phase 205 Plan 02: Frontend User Provisioning UI Summary

**AccountCard on PersonDetail with provisioning button + WelkomstmailTab in Settings, API client methods, and version bump to 29.2.0**

## Performance

- **Duration:** 6 min
- **Started:** 2026-02-20T18:55:27Z
- **Completed:** 2026-02-20T19:05:55Z
- **Tasks:** 2
- **Files created/modified:** 7

## Accomplishments

- AccountCard component with full provisioning state machine: not-provisioned (button), provisioning (spinner), provisioned (green check + email date), no-email (disabled button + help text), error state
- WelkomstmailTab in Settings > Beheer with from_email, from_name, subject, body fields, variable help text, and save with 2-second confirmation flash
- Three new prmApi methods: provisionUser, getProvisioningSettings, updateProvisioningSettings
- Version bumped to 29.2.0 with comprehensive changelog entry covering all Phase 205 additions
- Deployed to production (rondo.svawc.nl)

## Task Commits

1. **Task 1: Add AccountCard component and API client methods** - `4923dba2` (feat)
2. **Task 2: Add WelkomstmailTab to Settings and bump version to 29.2.0** - `a966db42` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `src/components/AccountCard.jsx` — Admin-only card: shows provisioning status, "Maak account aan" button, disabled state for persons without email, query invalidation on success
- `src/api/client.js` — Added provisionUser, getProvisioningSettings, updateProvisioningSettings to prmApi
- `src/pages/People/PersonDetail.jsx` — Import and render AccountCard in second column (admin-only, after SportlinkCard)
- `src/pages/Settings/Settings.jsx` — WelkomstmailTab component, ADMIN_SUBTABS entry, state/effects/handlers, prop threading
- `CHANGELOG.md` — 29.2.0 entry with all Phase 205 additions
- `style.css` — Version 29.2.0
- `package.json` — Version 29.2.0

## Decisions Made

- AccountCard reads personData from parent (no separate fetch) — same pattern as VOGCard, avoids redundant API calls
- WelkomstmailTab lazy-fetches settings only when first opened (useEffect with !welcomeSettings guard)
- Success message is transient state; permanent state comes from query invalidation refreshing linked_user_id
- No re-provision button when account already exists — per PROV-05 idempotency UX (backend is idempotent but UI prevents confusion)

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

Note: Before using in production with real members, verify Lettermint from-address is verified in the Lettermint dashboard (existing blocker from STATE.md). The AccountCard and WelkomstmailTab are live and functional on production.

## Next Phase Readiness

- Phase 205 is complete: both backend (Plan 01) and frontend (Plan 02) are on production
- Admin can navigate to any person detail page, see the Account card, and trigger provisioning
- Admin can configure the welcome email template in Settings > Beheer > Welkomstmail
- Phase 206 (capability sync) can proceed — the bidirectional link is established by provisioning
- Phase 208 (avatar) can proceed — rondo_linked_person_id user meta is set during provisioning

## Self-Check: PASSED

- AccountCard.jsx: FOUND
- Settings.jsx: FOUND
- SUMMARY.md: FOUND
- Commit 4923dba2 (Task 1): FOUND
- Commit a966db42 (Task 2): FOUND

---
*Phase: 205-user-provisioning*
*Completed: 2026-02-20*
