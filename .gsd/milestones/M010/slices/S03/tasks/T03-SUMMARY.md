---
id: T03
parent: S03
milestone: M010
provides:
  - Age-group info banner on People list for restricted users
  - Version 32.2.0 with changelog for all S03 changes
  - Production deployment of complete S03 slice
key_files:
  - src/pages/People/PeopleList.jsx
  - style.css
  - package.json
  - CHANGELOG.md
key_decisions:
  - Banner placed after toolbar and before loading/content area so it's always visible when data loads
  - Used Array.isArray() guard on permitted_age_groups for robustness (null = unrestricted, string[] = restricted)
patterns_established:
  - useCurrentUser() in list views for conditional UX based on user permissions
observability_surfaces:
  - Banner visibility is driven by `/rondo/v1/user/me` permitted_age_groups field — inspect that endpoint to check user state
duration: ~8 minutes
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T03: Add People list info banner, version bump, changelog, and deploy

**Added blue info banner on People list showing permitted leeftijdsgroepen for restricted users, bumped version to 32.2.0, and deployed to production.**

## What Happened

Imported `useCurrentUser` and `Info` icon into PeopleList. Added a conditional banner that renders when `currentUser?.permitted_age_groups` is a non-null array, showing "Je ziet alleen leden uit de leeftijdsgroepen: {groups}" in blue info styling with dark mode support. Bumped version to 32.2.0 in both `package.json` and `style.css`. Added changelog entry documenting all three S03 changes (info banner, access-denied message, Kaderlijst bypass). Build and lint passed with zero errors. Deployed to production successfully with cache clearing.

## Verification

- `npm run build` — ✅ zero errors, 5960 modules transformed
- `npm run lint` — ✅ zero warnings/errors
- `grep -n "permitted_age_groups" src/pages/People/PeopleList.jsx` — ✅ lines 1101, 1104 show banner logic
- `grep "32.2.0" package.json style.css` — ✅ version bumped in both files
- `grep "32.2.0" CHANGELOG.md` — ✅ changelog entry present
- `bin/deploy.sh` — ✅ deployment complete, caches cleared

**Slice-level verification (all pass):**
- `grep -n "suppress_age_group" includes/class-access-control.php` — ✅ bypass handling (lines 27, 345, 381-382, 386)
- `grep -n "suppress_age_group" src/pages/Teams/Kaderlijst.jsx` — ✅ param at line 243
- `grep -n "rest_forbidden_age_group" src/pages/People/PersonDetail.jsx` — ✅ error differentiation at line 983
- `grep -n "permitted_age_groups" src/pages/People/PeopleList.jsx` — ✅ info banner logic
- Production deploy succeeds and site loads — ✅

## Diagnostics

- Check People list page for presence/absence of info banner based on user's age-group restrictions
- Inspect `/rondo/v1/user/me` response for `permitted_age_groups` value (null = unrestricted, string[] = restricted)
- Banner is purely informational — no failure states

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/pages/People/PeopleList.jsx` — Added `useCurrentUser` import, `Info` icon import, conditional age-group info banner
- `package.json` — Version bumped to 32.2.0
- `style.css` — Version bumped to 32.2.0
- `CHANGELOG.md` — Added [32.2.0] section with all S03 changes
