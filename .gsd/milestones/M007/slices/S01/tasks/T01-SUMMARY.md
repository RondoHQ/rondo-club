---
id: T01
parent: S01
milestone: M007
provides:
  - iCal class file deleted
  - All PHP and JS references to iCal removed from source code
key_files:
  - functions.php
  - src/api/client.js
key_decisions: []
patterns_established: []
observability_surfaces:
  - none — pure deletion task
duration: 5 minutes
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T01: Delete iCal class and remove all PHP/JS references

**Deleted `includes/class-ical-feed.php` (531 lines) and surgically removed all 5 iCal reference blocks from `functions.php` plus the unused `getIcalUrl` method from `src/api/client.js`.**

## What Happened

1. Deleted `includes/class-ical-feed.php`
2. Removed 5 blocks from `functions.php`:
   - `use Rondo\Export\ICalFeed;` import
   - `class_alias(ICalFeed::class, 'RONDO_ICal_Feed')` backward-compat block
   - `rondo_is_ical_request()` helper function
   - iCal early-return block inside `rondo_init()` (the one that short-circuited for iCal requests)
   - Second `ICalFeed` instantiation block (for non-iCal hook registration)
3. Removed `getIcalUrl` method and its comment from `src/api/client.js`
4. Verified surrounding code in `functions.php` is intact — `$is_admin`/`$is_rest`/`$is_cron` detection block flows cleanly after DemoProtection instantiation.

## Verification

- `test ! -f includes/class-ical-feed.php` — **PASS** (file deleted)
- `grep -rn 'ICalFeed|ical_feed|getIcalUrl|rondo_is_ical_request|RONDO_ICal_Feed|prm-ical' includes/ functions.php src/` — **PASS** (zero results)
- `npm run build` — **PASS** (exit code 0, 15.9s)
- `npm run lint` — **PASS** (exit code 0, zero warnings)

### Slice-level verification (partial — intermediate task):
- ✅ Source grep (`includes/ functions.php src/`) — zero iCal references
- ⬜ Developer docs grep — still has references (T02 scope)
- ✅ `npm run build` — passes
- ✅ `npm run lint` — passes
- ✅ `class-ical-feed.php` deleted
- ⬜ Developer docs `ical-feed.md` — T02 scope
- ⬜ Production deploy — T03 scope

## Diagnostics

None — pure deletion task. Future agents can verify with `grep -rn 'ICalFeed\|ical_feed\|getIcalUrl\|rondo_is_ical_request\|RONDO_ICal_Feed\|prm-ical' includes/ functions.php src/`.

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `includes/class-ical-feed.php` — **deleted** (531 lines of dead iCal feed code)
- `functions.php` — removed 5 iCal reference blocks (~20 lines total)
- `src/api/client.js` — removed unused `getIcalUrl` method (2 lines)
