---
estimated_steps: 5
estimated_files: 3
---

# T04: Version bump, changelog, deploy, and production verification

**Slice:** S02 — Age-group access filtering
**Milestone:** M010

## Description

Bump the theme version to 32.1.0 (minor — new backward-compatible feature), update the changelog with S02 changes, run build and lint to confirm everything compiles, deploy to production, and verify all S02 deliverables on the live site.

## Steps

1. **Version bump:**
   - Update `style.css` Version to `32.1.0`
   - Update `package.json` version to `32.1.0`

2. **Update CHANGELOG.md:**
   - Add `## [32.1.0] - 2026-03-13` entry (or current date)
   - Added: Age-group access filtering — per-role leeftijdsgroep restrictions for member data visibility
   - Added: "Ledendata" column in Settings → Beheer → Capabilities with multi-select per role
   - Added: REST endpoints `GET/POST /rondo/v1/settings/age-group-access` for age-group access configuration
   - Added: `permitted_age_groups` field in `/rondo/v1/user/me` response
   - Added: Users with management capabilities bypass age-group filtering (see all members)

3. **Build and lint verification:**
   - `npm run build` — zero errors
   - `npm run lint` — zero warnings
   - `php -l` on all modified PHP files

4. **Deploy to production:**
   - Run `bin/deploy.sh`
   - Wait for completion

5. **Production verification:**
   - `GET /rondo/v1/settings/age-group-access` with admin auth — returns `available_age_groups` array and `roles` config
   - `POST /rondo/v1/settings/age-group-access` with admin auth — save a test config and verify it persists
   - `GET /rondo/v1/user/me` with admin auth — response includes `permitted_age_groups: null` (admin bypasses)
   - Open Settings → Beheer → Capabilities — "Ledendata" column visible
   - Verify style.css on production shows Version: 32.1.0

## Must-Haves

- [ ] Version 32.1.0 in style.css and package.json
- [ ] CHANGELOG.md has 32.1.0 entry with Added section
- [ ] `npm run build` passes
- [ ] `npm run lint` passes
- [ ] Deployed to production
- [ ] Production endpoints verified working

## Verification

- Production `GET /rondo/v1/settings/age-group-access` returns 200 with valid JSON
- Production `GET /rondo/v1/user/me` includes `permitted_age_groups` key
- Production style.css contains `Version: 32.1.0`
- Git commit and push completed

## Observability Impact

- Signals added/changed: None (deployment task)
- How a future agent inspects this: Check production style.css for version; check `/rondo/v1/settings/age-group-access` for feature availability
- Failure state exposed: None

## Inputs

- All files modified in T01, T02, T03 — complete and passing build/lint
- `bin/deploy.sh` — deployment script
- `.env` — production credentials

## Expected Output

- `style.css` — Version 32.1.0
- `package.json` — Version 32.1.0
- `CHANGELOG.md` — 32.1.0 entry
- Production site running v32.1.0 with age-group access filtering live
