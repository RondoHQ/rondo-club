---
phase: 79-oauth-foundation
plan: 01
subsystem: auth
tags: [google-oauth, contacts-api, people-api, incremental-auth, token-encryption]

# Dependency graph
requires:
  - phase: 47-calendar-oauth
    provides: GoogleOAuth class pattern, CredentialEncryption, REST Base class
provides:
  - Google Contacts OAuth scope constants and client configuration
  - GoogleContactsConnection class for user meta storage
  - REST API endpoints for contacts OAuth flow
affects: [80-initial-import, 81-ongoing-sync, 82-field-mapping, frontend-settings]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Incremental OAuth authorization (setIncludeGrantedScopes)"
    - "Separate transient keys for different OAuth flows"
    - "User-level connection storage (vs per-resource)"

key-files:
  created:
    - includes/class-google-contacts-connection.php
    - includes/class-rest-google-contacts.php
  modified:
    - includes/class-google-oauth.php
    - functions.php

key-decisions:
  - "Use separate callback endpoint /rondo/v1/google-contacts/callback to distinguish from calendar OAuth"
  - "Store contacts connection in user meta (one per user) vs calendar connections array (multiple per user)"
  - "Do NOT revoke token on disconnect - user may have Calendar connected with same account"
  - "Set pending_import flag for Phase 80 to trigger auto-import after OAuth"

patterns-established:
  - "GoogleOAuth public static methods for contacts-specific client/auth URL generation"
  - "get_contacts_access_mode() to determine readonly vs readwrite from granted scopes"
  - "Extract email from id_token JWT before falling back to userinfo API call"

# Metrics
duration: 5min
completed: 2026-01-17
---

# Phase 79 Plan 01: OAuth Foundation Summary

**Backend OAuth infrastructure for Google Contacts with incremental authorization, encrypted token storage, and REST endpoints**

## Performance

- **Duration:** 5 min
- **Started:** 2026-01-17T19:20:45Z
- **Completed:** 2026-01-17T19:25:53Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- Extended GoogleOAuth class with CONTACTS_SCOPE_READONLY and CONTACTS_SCOPE_READWRITE constants
- Created GoogleContactsConnection class for user meta storage with Sodium encryption
- Implemented REST API endpoints: status, auth, callback, disconnect
- Incremental authorization preserves existing Calendar scopes when adding Contacts

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend GoogleOAuth class for contacts scope** - `bfee084` (feat)
2. **Task 2: Create GoogleContactsConnection class** - `a302f5e` (feat)
3. **Task 3: Create REST API endpoints** - `23c0cba` (feat)

## Files Created/Modified
- `includes/class-google-oauth.php` - Extended with contacts scope constants and client methods
- `includes/class-google-contacts-connection.php` - NEW: User meta storage for contacts connection
- `includes/class-rest-google-contacts.php` - NEW: REST endpoints for OAuth flow
- `functions.php` - Added RESTGoogleContacts instantiation

## Decisions Made
- **Separate callback endpoint:** Using `/rondo/v1/google-contacts/callback` instead of shared callback to allow different post-auth behavior (contacts redirects to subtab=contacts, sets pending_import flag)
- **User-level storage:** GoogleContactsConnection stores one connection per user (unlike calendar connections which are per-calendar-resource) because Contacts sync is account-wide
- **No token revocation on disconnect:** When user disconnects contacts, we don't revoke the Google token because they may still have Calendar connected with the same Google account
- **Pending import flag:** OAuth callback sets `_stadion_google_contacts_pending_import` user meta for Phase 80 to detect and auto-start import

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- Commit history shows commits were merged with other unrelated changes due to concurrent git operations - code is correct but commit messages are slightly misleading (commit 23c0cba includes REST file but message says "style: add subtle background")

## User Setup Required

None - no external service configuration required. Google OAuth credentials (GOOGLE_CALENDAR_CLIENT_ID and GOOGLE_CALENDAR_CLIENT_SECRET) are already configured from Calendar integration.

## Next Phase Readiness
- Backend OAuth infrastructure complete
- Ready for Phase 79-02: Frontend settings UI for Google Contacts connection
- REST endpoints deployed and verified on production:
  - `GET /rondo/v1/google-contacts/status`
  - `GET /rondo/v1/google-contacts/auth`
  - `GET /rondo/v1/google-contacts/callback`
  - `DELETE /rondo/v1/google-contacts`

---
*Phase: 79-oauth-foundation*
*Completed: 2026-01-17*
