---
phase: 153-wire-up-role-settings
plan: 01
subsystem: ui
tags: [react, tanstack-query, wordpress-rest-api, volunteer-roles]

# Dependency graph
requires:
  - phase: 152-role-settings
    provides: Volunteer role classification backend with WordPress options storage and REST API endpoint
provides:
  - Frontend hook useVolunteerRoleSettings for fetching role settings via TanStack Query
  - TeamDetail page player/staff split driven by configured settings instead of hardcoded array
  - GET endpoint /rondo/v1/volunteer-roles/settings accessible to all authenticated users
affects: [154-configurable-roles-ui, future-volunteer-features]

# Tech tracking
tech-stack:
  added: []
  patterns: [query-key-factory-for-settings, 5-minute-staleTime-for-settings]

key-files:
  created: [src/hooks/useVolunteerRoleSettings.js]
  modified: [includes/class-rest-api.php, src/pages/Teams/TeamDetail.jsx, docs/rest-api.md]

key-decisions:
  - "GET endpoint permission changed from admin-only to all authenticated users for team detail page access"
  - "Empty array fallback for player roles ensures graceful degradation if settings unavailable"
  - "5-minute staleTime for role settings (rarely-changing data)"

patterns-established:
  - "Settings hooks use 5-minute staleTime, matching established pattern in usePeople.js filterOptions"
  - "Permission split: GET check_user_approved, POST check_admin_permission"

# Metrics
duration: 3min
completed: 2026-02-08
---

# Phase 153 Plan 01: Wire Up Role Settings Summary

**Team detail page player/staff split now driven by configured role settings from WordPress options via TanStack Query hook, eliminating last hardcoded role array in frontend**

## Performance

- **Duration:** 3 min 28 sec
- **Started:** 2026-02-08T10:14:23Z
- **Completed:** 2026-02-08T10:17:51Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments
- Created useVolunteerRoleSettings hook following established TanStack Query pattern
- Removed hardcoded 9-element playerRoles array from TeamDetail.jsx
- Fixed API permission to allow all authenticated users to read role settings
- Team detail page now respects configured player roles for member split

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix API permission and create useVolunteerRoleSettings hook** - `5e8a0874` (feat)
2. **Task 2: Wire TeamDetail to use settings, version bump, changelog, docs, build, deploy** - `5ac3081c` (feat)

## Files Created/Modified
- `src/hooks/useVolunteerRoleSettings.js` - TanStack Query hook for fetching role settings with queryKey factory and 5-minute staleTime
- `includes/class-rest-api.php` - Updated GET /rondo/v1/volunteer-roles/settings to use check_user_approved instead of check_admin_permission
- `src/pages/Teams/TeamDetail.jsx` - Replaced hardcoded playerRoles array with roleSettings?.player_roles || [] from hook
- `docs/rest-api.md` - Updated permission documentation for GET endpoint
- `style.css` - Version bump from 19.1.0 to 19.2.0
- `package.json` - Version bump from 19.1.0 to 19.2.0
- `CHANGELOG.md` - Added entries under Unreleased for Changed and Fixed sections
- `dist/` - Production build deployed to https://stadion.svawc.nl/

## Decisions Made
- **Permission model:** GET endpoint accessible to all authenticated users (not just admins) because non-admin users need to see correct player/staff split on team detail page. POST endpoint remains admin-only.
- **Fallback strategy:** Empty array `[]` as fallback if roleSettings unavailable - results in "all members shown as staff" which is safer than hardcoded list that might diverge from backend.
- **Caching:** 5-minute staleTime matches established pattern for rarely-changing settings data (see usePeople.js filterOptions).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - implementation proceeded smoothly following established patterns from useFees.js and usePeople.js.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- TeamDetail now fully settings-driven for player/staff classification
- v19.2.0 deployed to production with updated role settings integration
- Ready for Phase 154 (configurable roles UI enhancements) if needed
- Frontend and backend now use single source of truth (WordPress options) for role classification

---
*Phase: 153-wire-up-role-settings*
*Completed: 2026-02-08*
