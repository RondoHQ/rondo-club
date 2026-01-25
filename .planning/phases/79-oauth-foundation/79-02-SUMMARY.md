---
phase: 79-oauth-foundation
plan: 02
subsystem: ui
tags: [react, settings, google-contacts, oauth, tanstack-query]

# Dependency graph
requires:
  - phase: 79-01-oauth-foundation
    provides: REST API endpoints for Google Contacts OAuth (status, auth, callback, disconnect)
provides:
  - Google Contacts connection card in Settings > Connections > Contacts
  - Frontend OAuth flow integration (redirect to Google, handle callback)
  - Connection status display (email, access mode, last sync)
  - Connect/Disconnect functionality
affects: [80-initial-import, 81-ongoing-sync, frontend-contacts-sync]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Subtab-based connection team in Settings (Calendars, CardDAV, Slack, Contacts)"
    - "OAuth callback param handling with URL cleanup"
    - "Connection status polling with useEffect"

key-files:
  created: []
  modified:
    - src/pages/Settings/Settings.jsx
    - src/api/client.js
    - includes/class-google-oauth.php
    - includes/class-rest-google-contacts.php

key-decisions:
  - "Default to readwrite access mode for bidirectional sync capability"
  - "Show access mode in connected state (Read Only vs Read & Write)"
  - "Add email scope to OAuth request for reliable user identification"

patterns-established:
  - "ConnectionsContactsSubtab component follows existing subtab pattern"
  - "prmApi methods follow existing naming convention (getX, initiateX, disconnectX)"

# Metrics
duration: ~20min
completed: 2026-01-17
---

# Phase 79 Plan 02: Frontend Settings UI Summary

**Google Contacts connection card in Settings with OAuth flow integration, status display, and connect/disconnect controls**

## Performance

- **Duration:** ~20 min (including verification fixes)
- **Started:** 2026-01-17
- **Completed:** 2026-01-17
- **Tasks:** 3 (2 auto + 1 human verification)
- **Files modified:** 4

## Accomplishments
- Added Google Contacts API methods to client.js (getGoogleContactsStatus, initiateGoogleContactsAuth, disconnectGoogleContacts)
- Created Contacts subtab in Settings > Connections with full connection card UI
- Implemented OAuth flow: Connect button -> Google consent -> callback redirect -> connected state
- Display connection details: email, access mode, last sync time, contact count
- Fixed OAuth callback issues discovered during verification (email scope, redirect encoding)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Google Contacts API methods to client.js** - `37ea015` (feat)
2. **Task 2: Add Contacts subtab and Google Contacts card to Settings** - `7a47bfe` (feat)
3. **Task 3: Human verification** - `98648e9` (fix) - OAuth callback fixes

## Files Created/Modified
- `src/api/client.js` - Added getGoogleContactsStatus, initiateGoogleContactsAuth, disconnectGoogleContacts methods
- `src/pages/Settings/Settings.jsx` - Added Contacts subtab with ConnectionsContactsSubtab component
- `includes/class-google-oauth.php` - Added 'email' scope to OAuth request
- `includes/class-rest-google-contacts.php` - Fixed userinfo API call credentials and redirect encoding

## Decisions Made
- **Readwrite by default:** Connect button requests readwrite access for bidirectional sync (not readonly)
- **Show access mode:** Connected state displays "Read & Write" or "Read Only" so user knows their permissions
- **Email scope added:** OAuth request now includes 'email' scope for reliable user identification

## Deviations from Plan

### Auto-fixed Issues (during human verification)

**1. [Rule 1 - Bug] OAuth request missing email scope**
- **Found during:** Task 3 (Human verification)
- **Issue:** User email not being captured after OAuth completion - userinfo API returning empty
- **Fix:** Added 'email' scope to Google OAuth scopes array
- **Files modified:** includes/class-google-oauth.php
- **Verification:** OAuth flow now captures and displays connected user's email
- **Committed in:** 98648e9

**2. [Rule 1 - Bug] Userinfo API call missing credentials**
- **Found during:** Task 3 (Human verification)
- **Issue:** Google Client throwing "missing credentials" error when fetching userinfo
- **Fix:** Added setClientId and setClientSecret before setAccessToken
- **Files modified:** includes/class-rest-google-contacts.php
- **Verification:** Userinfo API call now succeeds
- **Committed in:** 98648e9

**3. [Rule 1 - Bug] OAuth callback redirect URL encoding issues**
- **Found during:** Task 3 (Human verification)
- **Issue:** Redirect URL had encoding issues preventing proper navigation
- **Fix:** Use HTTP 302 redirect header with wp_json_encode for JavaScript fallback
- **Files modified:** includes/class-rest-google-contacts.php
- **Verification:** OAuth callback now redirects properly to Settings > Connections > Contacts
- **Committed in:** 98648e9

---

**Total deviations:** 3 auto-fixed (3 bugs)
**Impact on plan:** All fixes necessary for OAuth flow to function correctly. No scope creep.

## Issues Encountered
- **redirect_uri_mismatch:** User needed to add callback URL to Google Cloud Console (user action, not code fix)
- **Autoloader issue:** Composer autoload needed regeneration for new classes (user ran `composer dump-autoload`)

## Authentication Gates

During execution, these authentication requirements were handled:

1. Task 3: Google OAuth required valid callback URL in Google Cloud Console
   - User added `https://cael.is/wp-json/stadion/v1/google-contacts/callback` to authorized redirect URIs
   - OAuth flow completed successfully after configuration

## User Setup Required

None - Google OAuth credentials already configured from Calendar integration (GOOGLE_CALENDAR_CLIENT_ID and GOOGLE_CALENDAR_CLIENT_SECRET).

## Next Phase Readiness
- OAuth Foundation complete - users can connect Google Contacts from Settings
- Backend stores: encrypted tokens, user email, access mode, pending_import flag
- Ready for Phase 80: Initial Import
  - `has_pending_import` flag is set after OAuth completion
  - Phase 80 can detect this and trigger automatic import
- Production verified: https://cael.is/settings?tab=connections&subtab=contacts

---
*Phase: 79-oauth-foundation*
*Completed: 2026-01-17*
