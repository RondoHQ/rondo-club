# Plan 60-01 Summary

**Phase:** 60 - Calendar Email Matching
**Plan:** 01 - Trigger Re-matching on Person Email Changes
**Status:** COMPLETE
**Date:** 2026-01-15

## Objective

Trigger calendar event re-matching when a person's email addresses change, ensuring newly added emails immediately match against existing calendar events.

## Tasks Completed

| # | Task | Status | Commit |
|---|------|--------|--------|
| 1 | Add re-matching method to STADION_Calendar_Matcher | Done | a4ee051 |
| 2 | Hook person save to trigger re-matching | Done | 4c839eb |
| 3 | Add WP-CLI command for manual re-matching | Done | ba8ef87, 778d8ab, 1e0b7e6 |

## Changes Made

### includes/class-calendar-matcher.php
- Added `rematch_events_for_user(int $user_id)` method that queries all calendar events with attendees and re-runs matching
- Added `on_person_saved(int $post_id)` method that invalidates cache and triggers re-matching when a person is saved

### includes/class-auto-title.php
- Added `trigger_calendar_rematch($post_id)` method hooked to `acf/save_post` at priority 25
- When a person is saved, triggers `STADION_Calendar_Matcher::on_person_saved()` to re-match events

### includes/class-wp-cli.php
- Added `rematch` subcommand to `STADION_Calendar_CLI_Command` class
- Command: `wp prm calendar rematch --user-id=ID`
- Invalidates email cache and re-matches all calendar events for the specified user

## Verification

- [x] `rematch_events_for_user()` method exists in STADION_Calendar_Matcher
- [x] `on_person_saved()` method exists in STADION_Calendar_Matcher
- [x] `trigger_calendar_rematch()` method exists in STADION_Auto_Title
- [x] acf/save_post hook registered at priority 25 in STADION_Auto_Title constructor
- [x] WP-CLI command `wp prm calendar rematch` is available
- [x] No PHP syntax errors in class-calendar-matcher.php
- [x] No PHP syntax errors in class-auto-title.php
- [x] Production tested: Re-matched 574 calendar events for user 1

## Technical Notes

1. **Hook Priority:** The calendar re-match hook fires at priority 25 on `acf/save_post`, which is after the auto-title generation (priority 20), ensuring all ACF fields are saved first.

2. **Performance:** Re-matching happens on every person save. This is acceptable because:
   - Email lookup cache rebuild is O(n) where n = user's contacts
   - Event re-matching only updates post meta, no external API calls
   - Processing happens after the save completes, user won't notice delay

3. **WP-CLI Parameter:** Changed from `--user` to `--user-id` to avoid conflict with WP-CLI's global `--user` parameter that sets WordPress user context.

## Files Modified

- `includes/class-calendar-matcher.php`
- `includes/class-auto-title.php`
- `includes/class-wp-cli.php`
