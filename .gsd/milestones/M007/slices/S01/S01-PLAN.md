# S01: Remove iCal feed

**Goal:** Fully remove the unused iCal feed — class file deleted, all references cleaned from source and developer docs, build passes, deployed to production.
**Demo:** `grep -rn ICalFeed includes/ functions.php src/` returns zero results; `npm run build` succeeds; production site loads without errors.

## Must-Haves

- `includes/class-ical-feed.php` deleted
- All iCal references removed from `functions.php` (use statement, class_alias, helper function, two instantiation blocks)
- `getIcalUrl` method removed from `src/api/client.js`
- Developer docs: `integrations/ical-feed.md` deleted
- Developer docs: all iCal references cleaned from `index.mdx`, `architecture.md`, `architecture/php-autoloading.md`, `architecture/relationship-system.md`, `features/reminders.md`, `astro.config.mjs`
- Build passes (`npm run build`)
- Version bumped (patch), changelog updated
- Deployed to production, rewrite rules flushed

## Proof Level

- This slice proves: integration
- Real runtime required: yes (deploy + WP-CLI rewrite flush)
- Human/UAT required: no

## Verification

- `grep -rn 'ICalFeed\|IcalFeed\|ical-feed\|ical_feed\|getIcalUrl\|rondo_is_ical_request\|RONDO_ICal_Feed\|prm-ical' includes/ functions.php src/ ../developer/src/content/docs/ ../developer/astro.config.mjs` returns zero results
- `npm run build` succeeds with exit code 0
- `npm run lint` succeeds with exit code 0
- `test ! -f includes/class-ical-feed.php` passes
- `test ! -f ../developer/src/content/docs/integrations/ical-feed.md` passes
- Production site loads without errors after deploy

## Observability / Diagnostics

- Runtime signals: none — this is a deletion, no new runtime behavior
- Inspection surfaces: `grep` for orphaned references post-removal
- Failure visibility: build failure (npm run build), lint failure (npm run lint)
- Redaction constraints: none

## Integration Closure

- Upstream surfaces consumed: nothing (single standalone slice)
- New wiring introduced in this slice: none (pure deletion)
- What remains before the milestone is truly usable end-to-end: nothing — this is the only slice in M007

## Tasks

- [ ] **T01: Delete iCal class and remove all PHP/JS references** `est:20m`
  - Why: Remove the dead iCal feed code from the application — the core of this slice
  - Files: `includes/class-ical-feed.php`, `functions.php`, `src/api/client.js`
  - Do: Delete the class file. Remove the `use` statement (line 126), `class_alias` block (lines 284–286), helper function `rondo_is_ical_request()` (lines 335–340), early-return block (lines 365–368), and second instantiation block (lines 416–420) from functions.php. Remove `getIcalUrl` lines from client.js. Verify surrounding code remains intact.
  - Verify: `grep -rn 'ICalFeed\|ical_feed\|getIcalUrl\|rondo_is_ical_request\|RONDO_ICal_Feed\|prm-ical' includes/ functions.php src/` returns zero; `npm run build` succeeds; `npm run lint` succeeds
  - Done when: All iCal references gone from source, build and lint pass

- [ ] **T02: Clean developer docs, bump version, deploy** `est:20m`
  - Why: Remove all iCal references from developer docs, update changelog/version, deploy to production and flush rewrite rules
  - Files: `../developer/src/content/docs/integrations/ical-feed.md`, `../developer/src/content/docs/index.mdx`, `../developer/src/content/docs/architecture.md`, `../developer/src/content/docs/architecture/php-autoloading.md`, `../developer/src/content/docs/architecture/relationship-system.md`, `../developer/src/content/docs/features/reminders.md`, `../developer/astro.config.mjs`, `style.css`, `package.json`, `CHANGELOG.md`
  - Do: Delete ical-feed.md. Update index.mdx Integrations card (description + link to demo-data). Remove IcalFeed row from architecture.md. Remove all 4 iCal references from php-autoloading.md. Remove iCal link from relationship-system.md and reminders.md. Replace iCal sidebar entry with demo-data in astro.config.mjs. Bump version to 31.13.1. Update changelog. Commit, push, deploy, flush rewrite rules.
  - Verify: `grep -rn 'ical-feed\|IcalFeed\|iCal Feed\|iCal feed' ../developer/src/content/docs/ ../developer/astro.config.mjs` returns zero; production site loads; `wp rewrite flush` succeeds
  - Done when: Docs clean, version bumped, deployed to production, rewrite rules flushed

## Files Likely Touched

- `includes/class-ical-feed.php` (delete)
- `functions.php`
- `src/api/client.js`
- `../developer/src/content/docs/integrations/ical-feed.md` (delete)
- `../developer/src/content/docs/index.mdx`
- `../developer/src/content/docs/architecture.md`
- `../developer/src/content/docs/architecture/php-autoloading.md`
- `../developer/src/content/docs/architecture/relationship-system.md`
- `../developer/src/content/docs/features/reminders.md`
- `../developer/astro.config.mjs`
- `style.css`
- `package.json`
- `CHANGELOG.md`
