# S01: Remove iCal Feed — Research

**Date:** 2026-03-12

## Summary

This is a straightforward dead-code removal. The iCal feed class (`includes/class-ical-feed.php`, 531 lines) is fully self-contained — no other PHP class references it, and the frontend API method `getIcalUrl` is defined in `src/api/client.js` but never called anywhere in the React app.

The removal touches 6 source files plus 5 developer doc files. The `functions.php` changes require care: the iCal early-return block (lines 365–368) exits `rondo_init()` entirely for iCal requests, and the second instantiation (line 419) loads it for non-iCal requests for hook registration. Both blocks plus the helper function `rondo_is_ical_request()` and the `use` statement and `class_alias` must be removed cleanly without disturbing surrounding code.

Developer docs have iCal references in 5 files: the dedicated page (delete), `index.mdx` (update Integrations card), `architecture.md` (remove table row), `architecture/php-autoloading.md` (remove from 4 locations), and `astro.config.mjs` (remove sidebar entry). Two cross-reference docs (`features/reminders.md` and `architecture/relationship-system.md`) link to `ical-feed.md` and must also be cleaned.

## Recommendation

Delete the class file, surgically remove all references from `functions.php` and `client.js`, delete the doc page, and clean all doc references. After deploy, flush rewrite rules via WP-CLI. This is a single-pass operation with no dependencies or phasing needed.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Rewrite rule cleanup | WordPress `wp rewrite flush` | WordPress automatically rebuilds rewrite rules from registered sources — no manual DB cleanup needed |

## Existing Code and Patterns

- `functions.php` lines 364–368 — iCal early-return block: `if (rondo_is_ical_request()) { new ICalFeed(); return; }` — remove entire block
- `functions.php` lines 416–420 — second iCal instantiation for non-iCal requests — remove entire block (including the comment)
- `functions.php` lines 335–340 — `rondo_is_ical_request()` helper function — remove entirely
- `functions.php` line 126 — `use Rondo\Export\ICalFeed;` — remove the use statement
- `functions.php` lines 284–286 — `class_alias(ICalFeed::class, 'RONDO_ICal_Feed');` with wrapping `if` — remove entire block
- `src/api/client.js` lines 228–229 — `getIcalUrl` method — remove both lines (comment + method)
- M001/Q001 (CardDAV removal) — prior art for this exact removal pattern, successfully deployed

## Constraints

- Must flush rewrite rules after deploy (`wp rewrite flush` via SSH) — iCal registered custom rewrite rules that will be orphaned
- `rondo_ical_token` user meta will remain in the database — harmless orphaned data, explicitly out of scope per M007 context
- The Integrations sidebar section in developer docs will still have `demo-data.md` — section should remain, only iCal entry removed

## Common Pitfalls

- **Breaking the `rondo_init()` early-return flow** — The iCal early-return block (lines 365–368) exits the function. Removing it must not accidentally remove the surrounding logic or leave dangling comments. The block after it (`$is_admin`, `$is_rest`, `$is_cron` detection) must remain intact.
- **Forgetting cross-reference doc links** — Two additional docs (`features/reminders.md` line 502 and `architecture/relationship-system.md` line 328) link to `ical-feed.md` and will become broken links if not cleaned.
- **Incomplete index.mdx update** — The Integrations card currently links to `/integrations/ical-feed/` and describes "iCal feeds, contact import." — must update both description and link to point to `demo-data` or another valid integration page.

## Open Risks

- None. The code is unused, self-contained, and the removal pattern has prior art (M001 CardDAV removal).

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress | — | Not needed for simple deletion |
| PHP | — | Not needed for simple deletion |

No specialized skills needed — this is a pure deletion task.

## Sources

- `includes/class-ical-feed.php` — verified 531-line self-contained class, no external consumers
- `src/api/client.js` — `getIcalUrl` confirmed never imported/called anywhere in `src/`
- `grep -rn ICalFeed includes/` — confirmed zero references outside `class-ical-feed.php` itself
- Developer docs grep — identified all 7 files with iCal references (5 in M007 context + 2 additional cross-refs)
