---
id: T04
parent: S02
milestone: M010
provides:
  - "Version 32.1.0 deployed to production with age-group access filtering live"
  - "CHANGELOG.md updated with 32.1.0 entry"
key_files:
  - style.css
  - package.json
  - CHANGELOG.md
key_decisions:
  - "Used 32.1.0 (minor) since age-group access filtering is a new backward-compatible feature"
patterns_established: []
observability_surfaces:
  - "Production style.css Version header confirms deployed version"
  - "GET /rondo/v1/settings/age-group-access returns config with 21 available age groups"
  - "GET /rondo/v1/user/me returns permitted_age_groups field"
duration: 10m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T04: Version bump, changelog, deploy, and production verification

**Bumped to v32.1.0, updated changelog, deployed to production, and verified all S02 endpoints and UI on live site.**

## What Happened

1. Version bumped from 32.0.0 → 32.1.0 in both `style.css` and `package.json`
2. Added CHANGELOG.md entry for 32.1.0 with all S02 "Added" items (age-group filtering, Ledendata column, REST endpoints, /me extension, management cap bypass)
3. Build (`npm run build`) and lint (`npm run lint`) both passed with zero errors/warnings
4. PHP syntax checks passed on all 3 modified PHP files
5. Deployed to production via `bin/deploy.sh` — successful
6. Verified all REST endpoints on production via curl with Basic Auth
7. Logged into production SPA and visually confirmed the "Ledendata" column in Settings → Beheer → Capabilities

## Verification

**Slice-level checks — all pass:**
- ✅ `php -l includes/class-access-control.php` — no syntax errors
- ✅ `php -l includes/class-rest-api.php` — no syntax errors
- ✅ `php -l includes/class-rest-people.php` — no syntax errors
- ✅ `npm run build` — zero errors
- ✅ `npm run lint` — zero warnings
- ✅ Production: `GET /rondo/v1/settings/age-group-access` returns `{ roles: {}, available_age_groups: [...21 items] }`
- ✅ Production: `POST /rondo/v1/settings/age-group-access` saves config and returns updated state (tested with rondo_user → ["Onder 13", "Senioren"], then reset to empty)
- ✅ Production: `GET /rondo/v1/user/me` returns `permitted_age_groups: null` (admin user has manage_options, bypasses)
- ✅ Production: CapabilitiesTab shows "Ledendata" column with multi-select dropdowns per role; management-cap roles show greyed-out "Alle leden"
- ✅ Production style.css contains `Version: 32.1.0`
- ⏭️ WPUnit test `tests/Wpunit/AgeGroupAccessTest.php` — exists from T01, not runnable in this deployment context (requires WP test harness)

## Diagnostics

- Production version: `grep 'Version:' style.css` on server → `Version: 32.1.0`
- Age-group config: `GET /rondo/v1/settings/age-group-access` or `wp option get rondo_age_group_access --format=json` via WP-CLI
- User restriction state: `GET /rondo/v1/user/me` → `permitted_age_groups` field (null = unrestricted, string[] = restricted)

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `style.css` — Version bumped to 32.1.0
- `package.json` — Version bumped to 32.1.0
- `CHANGELOG.md` — Added 32.1.0 entry with S02 changes
