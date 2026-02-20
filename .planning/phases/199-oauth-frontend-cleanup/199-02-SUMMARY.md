---
phase: 199-oauth-frontend-cleanup
plan: 02
subsystem: ui
tags: [react, settings, cleanup, dead-code-removal]

# Dependency graph
requires:
  - phase: 199-oauth-frontend-cleanup/199-01
    provides: "Backend OAuth cleanup (GoogleOAuth scoped to Sheets, Gravatar endpoint removed)"
  - phase: 198-backend-sync-removal
    provides: "Backend sync classes (CalendarSync, GoogleContactsSync) deleted"
provides:
  - "Settings Connections tab with only CardDAV and API-toegang subtabs"
  - "API client without dead Calendar/Contacts/Gravatar methods"
  - "Person creation hook without Gravatar sideload"
  - "PersonEditModal without Gravatar helper text"
affects:
  - "Any future Settings work"
  - "Any future frontend work touching prmApi"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Remove entire dead component trees when backend is deleted, not just individual references"
    - "After removing component groups, always scan for unused imports to keep lint clean"

key-files:
  created: []
  modified:
    - src/api/client.js
    - src/hooks/usePeople.js
    - src/components/PersonEditModal.jsx
    - src/pages/Settings/Settings.jsx

key-decisions:
  - "Removed testCalDAVConnection from client.js even though research said keep - the backend endpoint was in class-rest-calendar.php which was deleted in Phase 198, making it dead code"
  - "Removed ConnectionsCalendarsSubtab wrapper and CalendarsTab + CalDAVModal + EditConnectionModal (all calendar-related UI components) since all their backend endpoints are gone"
  - "Removed useQueryClient import from Settings.jsx - it was only used in the Google Contacts handlers that were deleted"

patterns-established: []

# Metrics
duration: 7min
completed: 2026-02-20
---

# Phase 199 Plan 02: Frontend Cleanup Summary

**Stripped Settings.jsx of ~1700 lines of Calendar/Contacts UI and removed 17 dead API methods from client.js, leaving Connections tab with only CardDAV and API-toegang subtabs**

## Performance

- **Duration:** 7 min
- **Started:** 2026-02-20T10:01:03Z
- **Completed:** 2026-02-20T10:07:34Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Removed 8 Google Contacts OAuth methods + 8 Calendar connections methods + Gravatar method from `src/api/client.js`
- Removed Gravatar sideload block from `useCreatePerson` in `usePeople.js` and updated JSDoc
- Removed Gravatar helper text from `PersonEditModal.jsx` email field
- Removed ~1700 lines from Settings.jsx: CalendarsTab, CalDAVModal, EditConnectionModal, ConnectionsCalendarsSubtab, ConnectionsContactsSubtab, SYNC_FREQUENCY_OPTIONS, 13 Google Contacts state vars, 4 useEffects, 6 handler functions
- CONNECTION_SUBTABS now has exactly 2 entries: carddav and api-access
- Default activeSubtab changed from 'calendars' to 'carddav'
- Frontend builds (36.88 kB Settings bundle) and lints cleanly with zero warnings

## Task Commits

Each task was committed atomically:

1. **Task 1: Remove dead API client methods and Gravatar frontend code** - `2d9f63a3` (feat)
2. **Task 2: Remove Calendar/Contacts UI from Settings.jsx** - `8d13299b` (feat)

**Plan metadata:** (docs commit below)

## Files Created/Modified

- `src/api/client.js` - Removed sideloadGravatar, 8 Google Contacts OAuth methods, 8 Calendar connections methods
- `src/hooks/usePeople.js` - Removed Gravatar sideload block, updated JSDoc
- `src/components/PersonEditModal.jsx` - Removed Gravatar helper text
- `src/pages/Settings/Settings.jsx` - Removed Calendar/Contacts tabs, components, state, and handlers; cleaned up unused imports

## Decisions Made

- Removed `testCalDAVConnection` from client.js: research said "keep it" but the backend endpoint lived in `class-rest-calendar.php` which was deleted in Phase 198, making it dead code
- Removed `useQueryClient` import entirely - it was only used in the Google Contacts handlers that were deleted
- Also removed `apiClient` (direct axios instance) import from Settings.jsx - it was used only inside CalendarsTab (iCal URL fetch/regenerate), which was deleted

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Removed testCalDAVConnection from client.js despite research note**
- **Found during:** Task 1 (Remove dead API client methods)
- **Issue:** Plan research incorrectly said to keep `testCalDAVConnection`. The endpoint was registered in `class-rest-calendar.php` which was deleted in Phase 198.
- **Fix:** Removed the method along with other Calendar connections methods
- **Files modified:** src/api/client.js
- **Verification:** grep confirms no reference in codebase
- **Committed in:** 2d9f63a3 (Task 1 commit)

**2. [Rule 2 - Missing Critical] Removed unused imports left by component deletion**
- **Found during:** Task 2 (lint step after removing CalendarsTab etc.)
- **Issue:** ESLint reported 9 errors for unused symbols after removing Calendar/Contacts components: RefreshCw, Trash2, Edit2, ExternalLink, AlertCircle, X, CheckCircle (lucide), formatDistanceToNow, apiClient
- **Fix:** Removed the unused imports from the import lines
- **Files modified:** src/pages/Settings/Settings.jsx
- **Verification:** npm run lint passes with zero warnings
- **Committed in:** 8d13299b (Task 2 commit)

**3. [Rule 2 - Missing Critical] Removed useQueryClient import and usage**
- **Found during:** Task 2 (removing Google Contacts handlers)
- **Issue:** queryClient was only used in Google Contacts handler functions. After removing those, the import and const declaration became unused dead code.
- **Fix:** Removed @tanstack/react-query useQueryClient import and `const queryClient = useQueryClient()` declaration
- **Files modified:** src/pages/Settings/Settings.jsx
- **Verification:** npm run lint passes with zero warnings
- **Committed in:** 8d13299b (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (1 Rule 1 bug, 2 Rule 2 missing critical)
**Impact on plan:** All auto-fixes necessary for correctness. No scope creep.

## Issues Encountered

None - plan executed cleanly.

## Next Phase Readiness

- Frontend cleanup complete for Phase 199
- Google OAuth in codebase is now exclusively Sheets-scoped (backend + frontend aligned)
- Ready for Phase 200 (CSV Export) and Phase 201 (Lettermint Setup) to proceed

---
*Phase: 199-oauth-frontend-cleanup*
*Completed: 2026-02-20*
