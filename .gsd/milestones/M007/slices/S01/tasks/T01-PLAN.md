---
estimated_steps: 5
estimated_files: 3
---

# T01: Delete iCal class and remove all PHP/JS references

**Slice:** S01 — Remove iCal feed
**Milestone:** M007

## Description

Delete the self-contained iCal feed class file and surgically remove all references from `functions.php` and `src/api/client.js`. The iCal class is 531 lines of dead code — no other PHP class references it, and `getIcalUrl` in client.js is never called anywhere in the React app.

The `functions.php` changes require care: the iCal early-return block (lines 365–368) exits `rondo_init()` entirely for iCal requests, and the second instantiation (lines 416–420) loads it for non-iCal requests for hook registration. Both blocks, the helper function `rondo_is_ical_request()`, the `use` statement, and the `class_alias` must be removed cleanly without disturbing surrounding code.

## Steps

1. Delete `includes/class-ical-feed.php`
2. Remove from `functions.php`:
   - The `use Rondo\Export\ICalFeed;` import (line 126)
   - The `class_alias(ICalFeed::class, 'RONDO_ICal_Feed')` block with wrapping `if` (lines 284–286)
   - The `rondo_is_ical_request()` helper function (lines 335–340)
   - The iCal early-return block inside `rondo_init()` (lines 365–368: `if (rondo_is_ical_request()) { new ICalFeed(); $initialized = true; return; }`)
   - The second iCal instantiation block (lines 416–420: comment + `if (!rondo_is_ical_request()) { new ICalFeed(); }`)
3. Remove from `src/api/client.js`: the comment and `getIcalUrl` method (lines 229–230)
4. Run `npm run build` and `npm run lint` to verify no breakage
5. Run `grep -rn 'ICalFeed\|ical_feed\|getIcalUrl\|rondo_is_ical_request\|RONDO_ICal_Feed\|prm-ical' includes/ functions.php src/` to verify zero references remain

## Must-Haves

- [ ] `includes/class-ical-feed.php` is deleted
- [ ] All 5 iCal reference blocks removed from `functions.php`
- [ ] `getIcalUrl` removed from `src/api/client.js`
- [ ] Surrounding code in `functions.php` is intact (especially the `$is_admin`/`$is_rest`/`$is_cron` detection block after the removed early-return)
- [ ] `npm run build` succeeds
- [ ] `npm run lint` succeeds

## Verification

- `test ! -f includes/class-ical-feed.php` — file deleted
- `grep -rn 'ICalFeed\|ical_feed\|getIcalUrl\|rondo_is_ical_request\|RONDO_ICal_Feed\|prm-ical' includes/ functions.php src/` — zero results
- `npm run build` — exit code 0
- `npm run lint` — exit code 0

## Observability Impact

- Signals added/changed: None — pure deletion
- How a future agent inspects this: `grep` for orphaned references
- Failure state exposed: Build/lint failures if removal is incomplete

## Inputs

- `includes/class-ical-feed.php` — the file to delete (531 lines, self-contained)
- `functions.php` — contains 5 separate iCal reference blocks to remove
- `src/api/client.js` — contains unused `getIcalUrl` method

## Expected Output

- `includes/class-ical-feed.php` — deleted
- `functions.php` — clean, with all 5 iCal blocks removed, surrounding code intact
- `src/api/client.js` — `getIcalUrl` method removed
