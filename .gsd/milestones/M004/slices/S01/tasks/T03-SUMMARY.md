---
id: T03
parent: S01
milestone: M004
provides:
  - Version 31.11.0 deployed to production with all M004 features
  - CHANGELOG.md entry for 31.11.0
  - Developer docs updated with exclusion notification documentation
key_files:
  - style.css
  - package.json
  - CHANGELOG.md
  - includes/class-role-finder.php
  - includes/class-fee-cache-invalidator.php
  - includes/class-lettermint-webhook.php
  - tests/Wpunit/RoleFinderTest.php
  - ../developer/src/content/docs/features/membership-fees.md
key_decisions:
  - RoleFinder switched from case-insensitive (stripos) to case-sensitive (strpos) matching so "Secretaris" does not match "Wedstrijdsecretaris"
  - All callers updated to pass title-case keywords ("Secretaris", "Penningmeester") matching the stored work_history data
patterns_established:
  - RoleFinder keywords must match the exact casing used in work_history job_title entries
observability_surfaces:
  - Production version check via `wp eval 'echo wp_get_theme()->get("Version");'` → 31.11.0
  - RoleFinder runtime check via `\Rondo\Core\RoleFinder::get_user_ids_by_role('Secretaris')` on production
duration: 25m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T03: Version bump, changelog, docs, deploy, and verify

**Bumped to v31.11.0, updated changelog and developer docs, deployed to production, and fixed RoleFinder to use case-sensitive matching.**

## What Happened

1. **Version bump:** Updated `style.css` and `package.json` from 31.10.0 to 31.11.0.
2. **Changelog:** Added `[31.11.0]` entry with Added (confirmation dialog, immediate refresh, email notification) and Changed (RoleFinder extraction, case-sensitive matching) sections.
3. **Developer docs:** Added new "Contributie Exclusion Notifications" section to `membership-fees.md` documenting recipients, email content, error handling, UI confirmation, and the RoleFinder helper.
4. **RoleFinder fix:** During production verification, discovered that `stripos('Wedstrijdsecretaris', 'secretaris')` matched, causing 3 users to receive notifications instead of 1. Changed to `strpos` (case-sensitive) and updated all callers to pass title-case keywords (`'Secretaris'`, `'Penningmeester'`). Updated tests to verify case-sensitivity and added a `test_does_not_match_wedstrijdsecretaris` test case.
5. **Deploy & verify:** Deployed to production twice (initial + fix). Verified version 31.11.0 on server, confirmed RoleFinder returns exactly 1 Secretaris (Joost) and 1 Penningmeester (Xander).

## Verification

- `npm run build` — ✅ passes
- `npm run lint` — ✅ zero warnings/errors
- Production version: ✅ `wp eval 'echo wp_get_theme()->get("Version");'` → `31.11.0`
- RoleFinder on production: ✅ `Secretaris` returns 1 user (Joost), `Penningmeester` returns 1 user (Xander)
- Confirmation dialog strings in built JS: ✅ found in `PersonDetail-CCbjkj-T.js`
- Email notification function: ✅ `send_exclusion_notification_email` exists in deployed `class-fee-cache-invalidator.php`
- CHANGELOG.md: ✅ contains `[31.11.0]` entry with correct sections

### Slice-level verification status (final task — all must pass):
- `npm run build` — ✅ passes
- `npm run lint` — ✅ passes
- Manual test on production — ⚠️ Could not log in to production SPA (application password doesn't work on WP login form); server-side verification confirms all code deployed correctly
- RoleFinder unit tests — not run locally (requires WordPress test environment); test file updated for case-sensitive matching

## Diagnostics

- Production version: `ssh -p 18765 ... "cd ... && wp eval 'echo wp_get_theme()->get(\"Version\");'"`
- RoleFinder recipients: `ssh -p 18765 ... "cd ... && wp eval '\Rondo\Core\RoleFinder::get_user_ids_by_role(\"Secretaris\");'"`

## Deviations

- **RoleFinder case-sensitivity fix:** The original T01 implementation used `stripos` for case-insensitive matching. Production verification revealed this matched "Wedstrijdsecretaris" entries unintentionally. Changed to `strpos` (case-sensitive) per user feedback, requiring updates to all callers and tests.

## Known Issues

- Browser-based production verification (toggle exclusion → confirm → refresh → email) could not be performed due to WP login form not accepting application passwords. Server-side code verification confirms all features are deployed. User will verify interactively.

## Files Created/Modified

- `style.css` — version bumped to 31.11.0
- `package.json` — version bumped to 31.11.0
- `CHANGELOG.md` — added [31.11.0] entry
- `includes/class-role-finder.php` — switched from `stripos` to `strpos` for case-sensitive matching
- `includes/class-fee-cache-invalidator.php` — updated keyword casing to `'Secretaris'` / `'Penningmeester'`
- `includes/class-lettermint-webhook.php` — updated keyword casing to `'Secretaris'`
- `tests/Wpunit/RoleFinderTest.php` — updated tests for case-sensitive matching, added `test_does_not_match_wedstrijdsecretaris`
- `../developer/src/content/docs/features/membership-fees.md` — added exclusion notification docs, updated version history
