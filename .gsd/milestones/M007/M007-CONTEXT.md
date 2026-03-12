# M007: Remove iCal Feed — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

Remove the iCal feed functionality from Rondo Club. It's no longer needed.

## Why This Milestone

The iCal feed is unused and adds dead code. The frontend API method `getIcalUrl` is defined but never called anywhere. Removing it simplifies the codebase and the request routing in `functions.php`.

## User-Visible Outcome

### When this milestone is complete, the user can:

- Confirm the iCal feed URLs (`/prm-ical/...`) no longer serve calendar data (returns 404)
- See no functional change anywhere else in the application

### Entry point / environment

- Entry point: N/A (removal)
- Environment: production WordPress site
- Live dependencies involved: none

## Completion Class

- Contract complete means: class deleted, all references removed, build passes, no errors
- Integration complete means: deployed to production
- Operational complete means: none

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- `includes/class-ical-feed.php` is deleted
- All references in `functions.php`, `src/api/client.js` are removed
- `npm run build` succeeds
- Deployed to production without errors

## Risks and Unknowns

- None — `getIcalUrl` is never called in the frontend, and the feed is not referenced in any UI

## Existing Codebase / Prior Art

- `includes/class-ical-feed.php` (531 lines) — the entire iCal feed class: rewrite rules, token auth, VCALENDAR output, REST endpoint for URL retrieval
- `functions.php` line 126 — `use Rondo\Export\ICalFeed;`
- `functions.php` line 285 — `class_alias( ICalFeed::class, 'RONDO_ICal_Feed' );`
- `functions.php` lines 337-340 — `rondo_is_ical_request()` helper
- `functions.php` line 366 — early-return iCal instantiation
- `functions.php` line 419 — second iCal instantiation
- `src/api/client.js` line 228-229 — `getIcalUrl` API method (defined but never used)
- Similar prior art: M001/Q001 removed CardDAV backend code following the same pattern

### Developer docs (`../developer/src/content/docs/`)

- `integrations/ical-feed.md` (348 lines) — dedicated iCal feed documentation page → **delete**
- `index.mdx` lines 108-110 — mentions iCal feeds and links to the integration doc → **remove**
- `architecture.md` line 73 — `IcalFeed` in class table → **remove row**
- `architecture/php-autoloading.md` lines 46-50, 104-105, 140 — iCal in loading strategy, helper function, optimization note → **remove**
- `../developer/astro.config.mjs` line 59 — sidebar entry for iCal Feed → **remove**

## Scope

### In Scope

- Delete `includes/class-ical-feed.php`
- Remove all iCal references from `functions.php` (use statement, class_alias, helper function, both instantiations)
- Remove `getIcalUrl` from `src/api/client.js`
- Delete `../developer/src/content/docs/integrations/ical-feed.md`
- Remove iCal references from developer docs: `index.mdx`, `architecture.md`, `architecture/php-autoloading.md`, `astro.config.mjs`
- Deploy to production

### Out of Scope / Non-Goals

- Cleaning up any iCal tokens stored in user meta (`rondo_ical_token`) — harmless orphaned data
- Removing rewrite rules from the database (WordPress will rebuild on next flush)

## Technical Constraints

- Must flush rewrite rules after deploy (visit Settings > Permalinks or run `wp rewrite flush`)

## Integration Points

- None — this is a pure removal
