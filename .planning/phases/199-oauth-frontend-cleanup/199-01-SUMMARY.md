---
phase: 199-oauth-frontend-cleanup
plan: 01
subsystem: auth
tags: [google-oauth, sheets, php, namespace, rest-api]

# Dependency graph
requires:
  - phase: 198-backend-sync-removal
    provides: Calendar/Contacts sync classes removed, leaving GoogleOAuth with dead methods
provides:
  - GoogleOAuth class scoped to Sheets only (namespace Rondo\Sheets)
  - Gravatar endpoint removed from People REST API
affects: [200-csv-export, 201-lettermint-setup]

# Tech tracking
tech-stack:
  added: []
  patterns: [Namespace reflects sole purpose of class (Rondo\Sheets for Sheets-only OAuth)]

key-files:
  created: []
  modified:
    - includes/class-google-oauth.php
    - includes/class-rest-google-sheets.php
    - includes/class-rest-people.php
    - functions.php

key-decisions:
  - "GoogleOAuth class namespace changed from Rondo\\Calendar to Rondo\\Sheets to reflect sole Sheets purpose"
  - "refresh_token() now creates client inline instead of calling deleted get_client() — scopes not needed for token refresh"
  - "Gravatar endpoint removed as part of Made in Europe cleanup — Gravatar is a US service"

patterns-established:
  - "Namespace should reflect single responsibility: Rondo\\Sheets for anything Sheets-related"

# Metrics
duration: 2min
completed: 2026-02-20
---

# Phase 199 Plan 01: OAuth + Gravatar Cleanup Summary

**GoogleOAuth class renamed to Rondo\Sheets namespace with 7 methods + 1 constant (Sheets only), Calendar/Contacts dead code removed, Gravatar REST endpoint deleted**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-20T09:00:20Z
- **Completed:** 2026-02-20T09:02:36Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Stripped 8 Calendar/Contacts methods and 3 scope constants from GoogleOAuth, leaving only 7 Sheets-specific methods
- Changed namespace from `Rondo\Calendar` to `Rondo\Sheets` in class-google-oauth.php and updated both consumers (functions.php, class-rest-google-sheets.php)
- Removed Gravatar REST endpoint (POST /rondo/v1/people/{id}/gravatar) and sideload_gravatar() method from class-rest-people.php

## Task Commits

Each task was committed atomically:

1. **Task 1: Strip Calendar/Contacts methods from GoogleOAuth and change namespace to Rondo\Sheets** - `f1d1068f` (refactor)
2. **Task 2: Remove Gravatar REST endpoint from class-rest-people.php** - `356272c7` (feat)

**Plan metadata:** _(docs commit follows)_

## Files Created/Modified
- `includes/class-google-oauth.php` - Namespace changed to Rondo\Sheets; Calendar/Contacts constants and methods removed; refresh_token() now inlines client creation instead of calling deleted get_client()
- `functions.php` - Updated use statement from Rondo\Calendar\GoogleOAuth to Rondo\Sheets\GoogleOAuth
- `includes/class-rest-google-sheets.php` - Updated use statement from Rondo\Calendar\GoogleOAuth to Rondo\Sheets\GoogleOAuth
- `includes/class-rest-people.php` - Removed Gravatar route registration and sideload_gravatar() method (70 lines removed)

## Decisions Made
- `refresh_token()` inlines Google Client creation instead of calling the deleted `get_client()` method — scopes are not needed for token refresh so the simpler inline approach is correct
- `'scope' => $token['scope'] ?? ''` rather than using the removed `SCOPES` constant fallback — for refresh tokens, if scope is not returned by Google the previous token's scope is preserved by the caller anyway

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- GoogleOAuth class is clean and Sheets-scoped, ready for Plan 02 (frontend cleanup)
- No blockers

---
*Phase: 199-oauth-frontend-cleanup*
*Completed: 2026-02-20*
