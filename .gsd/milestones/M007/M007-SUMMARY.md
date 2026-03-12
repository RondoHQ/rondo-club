---
id: M007
provides:
  - Removal of unused iCal feed class and all references across PHP, JS, and developer docs
key_decisions:
  - "[M007-S01] Replace empty Integrations sidebar with Demo Data entry — section should not be empty after iCal removal"
patterns_established:
  - Dead feature removal pattern: delete class → remove references in functions.php/client.js → clean developer docs → bump version → deploy → flush rewrite rules
observability_surfaces:
  - none — pure code removal
requirement_outcomes: []
duration: ~15 minutes
verification_result: passed
completed_at: 2026-03-12
---

# M007: Remove iCal Feed

**Deleted the unused iCal feed class (531 lines), removed all references from PHP, JS, and developer documentation, and deployed to production.**

## What Happened

The iCal feed (`includes/class-ical-feed.php`) was dead code — the frontend `getIcalUrl` method was defined but never called, and no UI referenced the feed. This single-slice milestone removed it entirely.

**T01** deleted `class-ical-feed.php` and surgically removed 5 reference blocks from `functions.php` (use statement, class_alias, helper function, two instantiation blocks) plus the `getIcalUrl` method from `src/api/client.js`.

**T02** cleaned 7 developer doc files: deleted the dedicated `integrations/ical-feed.md` page, removed iCal rows/references from `index.mdx`, `architecture.md`, `php-autoloading.md`, `relationship-system.md`, and `reminders.md`, and updated the sidebar in `astro.config.mjs` to replace the iCal entry with Demo Data. Version bumped to 31.13.1, deployed to production, and flushed WordPress rewrite rules.

## Cross-Slice Verification

Single-slice milestone. All success criteria verified:

| Criterion | Evidence |
|-----------|----------|
| `class-ical-feed.php` deleted | `test ! -f includes/class-ical-feed.php` — PASS |
| All iCal refs removed from `functions.php` | `grep -n 'ical\|ICalFeed\|ICal_Feed\|getIcalUrl\|rondo_is_ical_request' functions.php` — zero matches (only "dynamically" false positive) |
| `getIcalUrl` removed from `client.js` | `grep -n 'ical\|ICalFeed\|getIcalUrl' src/api/client.js` — zero matches |
| Developer docs iCal page deleted | `test ! -f ../developer/src/content/docs/integrations/ical-feed.md` — PASS |
| All iCal refs removed from docs site | Recursive grep across docs and astro.config — zero matches |
| Build passes | `npm run build` — succeeds (16s, 109 precache entries) |
| No runtime errors | `npm run lint` — zero warnings/errors |
| Deployed to production | HTTP 200 confirmed, `wp rewrite flush` — "Success: Rewrite rules flushed" |

## Requirement Changes

No requirements changed status during this milestone. The iCal feed was listed in PROJECT.md as "existing" validated functionality but was dead code with no active consumers.

## Forward Intelligence

### What the next milestone should know
- The Integrations section in developer docs sidebar now contains only "Demo Data" — future integrations should add their sidebar entry there.
- `functions.php` request-type detection block (`$is_admin`/`$is_rest`/`$is_cron`) flows directly after DemoProtection instantiation now, with no iCal early-return interrupting the flow.

### What's fragile
- Nothing — pure removal with zero behavioral changes to any remaining code path.

### Authoritative diagnostics
- `grep -rn 'ICalFeed\|ical_feed\|getIcalUrl\|rondo_is_ical_request\|RONDO_ICal_Feed\|prm-ical' includes/ functions.php src/` — should always return zero results.

### What assumptions changed
- None — the iCal feed was confirmed unused before starting, and removal was clean.

## Files Created/Modified

- `includes/class-ical-feed.php` — **deleted** (531 lines)
- `functions.php` — removed 5 iCal reference blocks (~20 lines)
- `src/api/client.js` — removed `getIcalUrl` method (2 lines)
- `../developer/src/content/docs/integrations/ical-feed.md` — **deleted** (348 lines)
- `../developer/src/content/docs/index.mdx` — Integrations card updated to Demo Data
- `../developer/src/content/docs/architecture.md` — IcalFeed row removed
- `../developer/src/content/docs/architecture/php-autoloading.md` — 4 iCal references removed
- `../developer/src/content/docs/architecture/relationship-system.md` — iCal row removed
- `../developer/src/content/docs/features/reminders.md` — iCal link removed
- `../developer/astro.config.mjs` — sidebar entry changed from iCal Feed to Demo Data
- `style.css` — version bumped to 31.13.1
- `package.json` — version bumped to 31.13.1
- `CHANGELOG.md` — added 31.13.1 entry
