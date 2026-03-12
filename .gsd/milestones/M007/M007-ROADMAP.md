# M007: Remove iCal Feed

**Vision:** Remove the unused iCal feed to reduce dead code and simplify request routing.

## Success Criteria

- `includes/class-ical-feed.php` is deleted
- All iCal references removed from `functions.php` and `src/api/client.js`
- Developer docs iCal page deleted, all iCal references removed from docs site
- Build passes, no runtime errors
- Deployed to production

## Key Risks / Unknowns

None — the frontend never calls `getIcalUrl` and no UI references the iCal feed.

## Verification Classes

- Contract verification: `npm run build` succeeds, no references to ICalFeed remain
- Integration verification: deployed to production, site loads without errors
- Operational verification: none
- UAT / human verification: none needed

## Milestone Definition of Done

This milestone is complete only when all are true:

- Class file deleted
- All references in functions.php and client.js removed
- Developer docs page deleted, all iCal mentions removed from docs site
- Build passes
- Deployed to production and rewrite rules flushed

## Slices

- [ ] **S01: Remove iCal feed** `risk:low` `depends:[]`
  > After this: iCal feed code is fully removed — deployed to production

## Boundary Map

### S01

Produces:
- Deletion of `includes/class-ical-feed.php`
- Clean `functions.php` without iCal references
- Clean `src/api/client.js` without `getIcalUrl`
- Deletion of `../developer/src/content/docs/integrations/ical-feed.md`
- Clean developer docs without iCal references (index, architecture, php-autoloading, sidebar)

Consumes:
- nothing (single slice)
