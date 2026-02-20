---
phase: 207-in-app-profile-page
plan: 01
subsystem: user-profile
tags: [profile, password, rest-api, react, frontend, backend]
dependency_graph:
  requires: [phase-206-capability-sync, phase-205-user-provisioning]
  provides: [in-app-profile-page, password-change-endpoint, linked-person-display]
  affects: [Layout.jsx, useCurrentUser.js, class-rest-api.php]
tech_stack:
  added: []
  patterns: [useMutation-for-password-change, hard-redirect-after-session-destroy, demo-guard-pattern]
key_files:
  created:
    - src/pages/Profile/Profile.jsx
  modified:
    - includes/class-rest-api.php
    - src/api/client.js
    - src/hooks/useCurrentUser.js
    - src/lazyPages.js
    - src/router.jsx
    - src/components/layout/Layout.jsx
    - CHANGELOG.md
    - style.css
    - package.json
    - ../developer/src/content/docs/api/rest-api.md
decisions:
  - "Hard-redirect to login immediately on password change success — session is dead, no intermediate state possible"
  - "Demo guard in backend (not just frontend) — returns 403 for demo login regardless of UI state"
  - "Non-admin UserMenu links to /profile; admin users also get in-app /profile link (wp-admin link remains for admins)"
  - "active_functies extracted from work_history where is_current is truthy — same ACF field pattern used elsewhere"
metrics:
  duration: 4 minutes
  completed: 2026-02-20
  tasks_completed: 2
  files_changed: 10
---

# Phase 207 Plan 01: In-App Profile Page Summary

In-app profile page replacing wp-admin profile link for non-admin users, with Sportlink identity display and password change that destroys all sessions and hard-redirects to login.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Backend password endpoint + expanded /user/me | 2239e9de | includes/class-rest-api.php, developer/rest-api.md |
| 2 | Frontend Profile page + sidebar integration + version bump | bd086840 | 9 files |

## What Was Built

### Backend (Task 1)

**POST /rondo/v1/user/password** — New endpoint registered in `register_routes()`:
- Demo guard: returns 403 if `user_login === 'demo'`
- Verifies current password with `wp_check_password()` — returns 400 with Dutch error on failure
- Changes password via `wp_set_password()`
- Destroys all sessions via `WP_Session_Tokens::destroy_all()`
- Returns `{ success: true, message: "Wachtwoord succesvol gewijzigd. Log opnieuw in." }`

**GET /rondo/v1/user/me** expanded with:
- `linked_person_name` — from linked person's `first_name` + `last_name` ACF fields (null if no link)
- `active_functies` — array of `job_title` values from `work_history` where `is_current` is truthy

### Frontend (Task 2)

**Profile.jsx** (`src/pages/Profile/Profile.jsx`, 155 lines):
- Account card: user name and email
- Sportlink koppeling card: linked person name + active functies as pills (hidden if no linked person)
- Wachtwoord wijzigen card: three password fields with client-side validation (8+ chars, confirm match), error display, spinner during submit, hard-redirect to login on success
- Demo user guard hides password card via `window.rondoConfig?.isDemoUser`

**Wiring:**
- `useChangePassword()` mutation hook in `useCurrentUser.js`
- `changePassword()` API method in `client.js`
- Lazy import in `lazyPages.js`
- Route at `/profile` in `router.jsx`
- UserMenu updated: non-admin "Profiel" link uses React Router `<Link to="/profile">` instead of `<a href={profile_url}>`
- Admin users keep in-app profile link AND existing WordPress admin link
- `getPageTitle` returns 'Profiel' for `/profile` paths

**Version 29.4.0** in package.json, style.css, CHANGELOG.md.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

Files verified:
- `src/pages/Profile/Profile.jsx` — EXISTS (155 lines)
- `includes/class-rest-api.php` contains `change_password` — FOUND (line 2765)
- `includes/class-rest-api.php` contains `linked_person_name` — FOUND (line 2753)
- `src/hooks/useCurrentUser.js` contains `useChangePassword` — FOUND (line 30)
- `src/api/client.js` contains `changePassword` — FOUND (line 115)
- `src/lazyPages.js` contains `Profile` lazy import — FOUND (line 24)
- `src/router.jsx` contains `profile` route — FOUND (line 255)
- `src/components/layout/Layout.jsx` contains `/profile` link — FOUND (line 247, 535)

Commits verified:
- `2239e9de` — feat(207-01): add POST /user/password endpoint + expand GET /user/me — FOUND
- `bd086840` — feat(207-01): Profile page with identity display, password change, sidebar integration — FOUND

Build: `npm run build` — PASSED (0 errors)
Lint: `npm run lint` — PASSED (0 warnings)
Deploy: Production deployed to https://rondo.svawc.nl/
