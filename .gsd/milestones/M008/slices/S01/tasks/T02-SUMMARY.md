---
id: T02
parent: S01
milestone: M008
provides:
  - Production deployment of Credit badge and filter on Facturen list (v31.14.0)
key_files:
  - style.css
  - package.json
  - CHANGELOG.md
key_decisions:
  - Bumped minor version (31.13.1 → 31.14.0) since this is a new feature, not a patch
patterns_established:
  - none
observability_surfaces:
  - none — pure presentational change
duration: ~5m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: Deploy to production and verify

**Bumped version to 31.14.0, added changelog entry, committed, deployed to production with cache clear.**

## What Happened

1. Bumped version from 31.13.1 to 31.14.0 in both `package.json` and `style.css` (minor bump for new feature)
2. Added changelog entry under `[31.14.0] - 2026-03-12` with "Added" section describing the Credit badge and filter
3. Committed with `feat: add Credit badge and filter to Facturen list` and pushed to `gsd/M008/S01` branch
4. Ran `bin/deploy.sh` — build succeeded (5960 modules, 16s), rsync completed, caches cleared

## Verification

- ✅ `npm run build` exits 0 (ran as part of deploy)
- ✅ `npm run lint` exits 0 with zero warnings
- ✅ `bin/deploy.sh` completed successfully (dist sync + theme sync + cache clear)
- ✅ `git log --oneline -1` shows `6dfcb392 feat: add Credit badge and filter to Facturen list`
- ✅ Production `style.css` shows `Version: 31.14.0` (verified via SSH)
- ✅ Production site accessible at https://rondo.svawc.nl/financien/facturen (for human visual verification)

### Slice-level verification status (final task):
- ✅ `npm run build` exits 0
- ✅ `npm run lint` exits 0
- ✅ `bin/deploy.sh` completes successfully
- 🔲 Visual verification on production (requires human UAT — credit invoices should show rose "Credit" badge, Type filter should include "Credit" option)

## Diagnostics

None — pure presentational change. Verify on production by visiting https://rondo.svawc.nl/financien/facturen and checking:
- Credit invoices display rose "Credit" badge in Type column
- Type filter dropdown includes "Credit" option
- Filtering by "Credit" shows only credit invoices
- Filtering by "Handmatig" excludes credit invoices

## Deviations

Bumped minor version (31.14.0) instead of patch as suggested in the task plan, since this is a new feature (credit badge + filter), not a bug fix.

## Known Issues

None

## Files Created/Modified

- `package.json` — version bumped to 31.14.0
- `style.css` — version bumped to 31.14.0
- `CHANGELOG.md` — added [31.14.0] entry with Credit badge and filter description
