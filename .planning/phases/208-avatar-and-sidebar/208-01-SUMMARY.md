---
phase: 208-avatar-and-sidebar
plan: 01
subsystem: ui
tags: [react, sidebar, avatar, profile-photo, rest-api, wordpress]

# Dependency graph
requires:
  - phase: 205-user-provisioning
    provides: rondo_linked_person_id user meta linking WP user to Sportlink person record
provides:
  - linked_person_photo field in GET /rondo/v1/user/me REST response (URL string or null)
  - Sidebar footer redesigned with circular avatar, user name, and logout link
  - Demo-safe identity row (plain div for demo users, Link to /profile for regular users)
affects: [future phases using /user/me data, any sidebar layout changes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - get_the_post_thumbnail_url() with ?: null fallback (returns false, not null, when no thumbnail)
    - Demo user guard pattern in sidebar: isDemoUser ? plain div : Link (matches UserMenu pattern)

key-files:
  created: []
  modified:
    - includes/class-rest-api.php
    - src/components/layout/Layout.jsx
    - style.css
    - package.json
    - CHANGELOG.md

key-decisions:
  - "Use ?: null (not ?? null) for get_the_post_thumbnail_url() because it returns false (not null) when no thumbnail"
  - "Demo users get plain div identity row (not clickable); regular users get Link to /profile - matches UserMenu pattern"
  - "Default avatar (User icon in cyan circle) doubles as loading state - prevents layout shift"
  - "thumbnail image size used for avatar (96x96 WP default) - sufficient for 32x32 sidebar display"

patterns-established:
  - "Sidebar identity row: photo when available, User icon fallback, always same 32x32 dimensions"

# Metrics
duration: 15min
completed: 2026-02-20
---

# Phase 208 Plan 01: Avatar and Sidebar Summary

**Sidebar footer redesigned with circular Sportlink person photo avatar (or User icon fallback), user name, and logout; linked_person_photo added to /rondo/v1/user/me**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-02-20T00:00:00Z
- **Completed:** 2026-02-20
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Added `linked_person_photo` field to `get_current_user()` using `get_the_post_thumbnail_url()` with `?: null` fallback
- Redesigned sidebar footer with user identity row (circular avatar + display name) above logout link
- Photo avatar shown when linked person has a featured image; User icon in cyan circle as fallback
- Identity row links to `/profile` for regular users, renders as non-clickable div for demo users
- Bumped version to 30.0.0 (MAJOR - completes v30.0 User Accounts & Profiles milestone)
- Deployed to production at https://rondo.svawc.nl/

## Task Commits

Each task was committed atomically:

1. **Task 1: Add linked_person_photo to /user/me and redesign sidebar footer** - `172dc15f` (feat)
2. **Task 2: Version bump, changelog, deploy, and developer docs** - `ba069b41` (chore)

## Files Created/Modified
- `includes/class-rest-api.php` - Added `linked_person_photo` field to `get_current_user()` response
- `src/components/layout/Layout.jsx` - Sidebar footer redesigned with avatar + name + logout
- `style.css` - Version bumped to 30.0.0
- `package.json` - Version bumped to 30.0.0
- `CHANGELOG.md` - Added [30.0.0] entry

## Decisions Made
- Used `?: null` (not `?? null`) for `get_the_post_thumbnail_url()` because the function returns `false` (not `null`) when no thumbnail is set
- Demo users get a plain `div` identity row (not clickable); regular users get `Link to="/profile"` - matches the existing `UserMenu` behavior pattern
- No new imports needed - `User` icon was already imported from lucide-react, `currentUser` already available from existing `useCurrentUser()` call
- No developer docs created - no specific `/user/me` API doc exists; field is additive and self-documenting

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- v30.0 User Accounts & Profiles milestone complete
- Version 30.0.0 deployed to production
- Sidebar avatar displays linked Sportlink person photo when available

---
*Phase: 208-avatar-and-sidebar*
*Completed: 2026-02-20*
