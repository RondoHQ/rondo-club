---
id: T03
parent: S01
milestone: M010
provides:
  - Version 32.0.0 deployed to production with role-capability matrix feature live
  - CHANGELOG.md updated with 32.0.0 release notes
key_files:
  - style.css
  - package.json
  - CHANGELOG.md
key_decisions:
  - MAJOR version bump (32.0.0) because role system architecture changed from hardcoded to configurable
patterns_established:
  - none
observability_surfaces:
  - "Production: GET /rondo/v1/settings/capability-matrix returns current role×capability state"
  - "Production: style.css Version field confirms deployed version"
duration: 10m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T03: Version bump, changelog, deploy, and production verification

**Deployed version 32.0.0 to production with role-capability matrix fully functional end-to-end.**

## What Happened

Version was already bumped to 32.0.0 in style.css and package.json, and CHANGELOG.md already updated from the previous attempt. Confirmed lint and build pass cleanly. Deployed to production via `bin/deploy.sh`. Verified the capability-matrix REST endpoint works with authentication, tested the full save→reload persistence cycle (toggled fairplay on rondo_user, confirmed persistence via fresh GET, then reverted), and confirmed zero hardcoded `current_user_can('administrator')` checks remain on production.

## Verification

All slice-level checks pass:

- `npm run build` — ✅ zero errors, built in 35.38s
- `npm run lint` — ✅ zero warnings
- `php -l` on all 5 PHP files — ✅ no syntax errors
- `grep -c "current_user_can( 'administrator' )"` on 3 REST controller files — ✅ all return 0
- Production `GET /rondo/v1/settings/capability-matrix` — ✅ returns correct matrix with all roles and 5 capabilities
- Production save→reload cycle — ✅ toggled fairplay for rondo_user to true, confirmed via fresh GET, reverted back
- Production style.css — ✅ Version: 32.0.0

## Diagnostics

- `GET /rondo/v1/settings/capability-matrix` (with admin auth) — inspect current role×capability state on production
- `POST /rondo/v1/settings/capability-matrix` (with admin auth) — update capabilities, returns fresh matrix
- Production style.css Version field confirms deployed version

## Deviations

None — version bump and changelog were already completed in the previous attempt, so this execution focused on build verification, deployment, and production verification.

## Known Issues

None

## Files Created/Modified

- `style.css` — Version bumped to 32.0.0 (done in previous attempt, confirmed)
- `package.json` — Version bumped to 32.0.0 (done in previous attempt, confirmed)
- `CHANGELOG.md` — Added 32.0.0 release entry with Added/Changed/Fixed sections (done in previous attempt, confirmed)
