---
estimated_steps: 5
estimated_files: 3
---

# T03: Version bump, changelog, deploy, and production verification

**Slice:** S01 — Role-capability matrix backend & UI
**Milestone:** M010

## Description

Ship the complete S01 feature to production. Bump version to 32.0.0 (MAJOR — role system architecture change), update changelog, run final build check, deploy via `bin/deploy.sh`, and verify the feature works end-to-end on production with real data.

## Steps

1. **Bump version** — Update `style.css` Version field and `package.json` version to `32.0.0`. This is a MAJOR bump because:
   - The role capability system fundamentally changes from hardcoded ROLES constant to admin-configurable matrix
   - All 6 administrator role checks replaced with capability checks (changes authorization behavior)
   - `register_role()` behavior changed (no longer re-adds caps to existing roles)

2. **Update CHANGELOG.md** — Add `## [32.0.0] - 2026-03-12` entry with:
   - **Added**: Role-capability matrix UI in Settings → Beheer → Capabilities; REST endpoints `GET/POST /rondo/v1/settings/capability-matrix`
   - **Changed**: All 6 `current_user_can('administrator')` checks replaced with `current_user_can('manage_options')` for proper capability-based authorization
   - **Fixed**: `register_role()` no longer re-adds capabilities to existing roles, allowing matrix changes to persist

3. **Final build check** — Run `npm run build` and `npm run lint` to confirm everything compiles cleanly before deploy.

4. **Deploy to production** — Run `bin/deploy.sh` to sync files to production server. The script handles building and cache clearing.

5. **Production verification** — SSH to production and verify:
   - Navigate to Settings → Beheer → Capabilities (confirm subtab renders)
   - Matrix shows roles with current capability state
   - Toggle a checkbox, save, reload — state persists
   - Grep production files to confirm no `current_user_can( 'administrator' )` remains

## Must-Haves

- [ ] Version 32.0.0 in both `style.css` and `package.json`
- [ ] CHANGELOG.md has complete entry for 32.0.0
- [ ] `npm run build` passes
- [ ] `npm run lint` passes
- [ ] Successfully deployed to production
- [ ] Capabilities tab renders and functions on production

## Verification

- `npm run build` — zero errors
- `npm run lint` — zero warnings
- Production: `GET /rondo/v1/settings/capability-matrix` returns matrix data (verify via curl or browser)
- Production: Settings → Beheer → Capabilities tab visible and interactive
- Production: Save→reload cycle preserves matrix state

## Observability Impact

- Signals added/changed: None
- How a future agent inspects this: Version 32.0.0 in style.css confirms deployment; Capabilities tab in Settings confirms feature is live
- Failure state exposed: None

## Inputs

- T01 output — all PHP files with backend changes
- T02 output — all frontend files with UI changes
- `bin/deploy.sh` — deployment script
- `.env` — server credentials for deployment

## Expected Output

- `style.css` — Version: 32.0.0
- `package.json` — "version": "32.0.0"
- `CHANGELOG.md` — 32.0.0 release entry
- Production deployment — feature live and verified
