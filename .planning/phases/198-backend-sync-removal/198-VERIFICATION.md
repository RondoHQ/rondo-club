---
phase: 198-backend-sync-removal
verified: 2026-02-20T08:36:08Z
status: passed
score: 9/9 must-haves verified
re_verification: true
gaps: []
gap_fix: "Removed \\RONDO_Calendar_Connections references from class-google-oauth.php get_access_token() in commit aabfb5d9. Calendar sync REST endpoint partial note deferred to Phase 199 (OAuth cleanup)."
---

# Phase 198: Backend Sync Removal Verification Report

**Phase Goal:** Google Contacts and Calendar sync code no longer exists in the backend
**Verified:** 2026-02-20T08:36:08Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | No Google Contacts sync classes exist in the codebase | VERIFIED | All 5 files confirmed absent: class-google-contacts-sync.php, class-google-contacts-export.php, class-google-contacts-api-import.php, class-google-contacts-connection.php, class-rest-google-contacts.php |
| 2 | Google Contacts REST endpoints return 404 (routes no longer registered) | VERIFIED | new RESTGoogleContacts() removed from rondo_init(); no Google Contacts routes registered |
| 3 | functions.php no longer loads any Google Contacts sync classes | VERIFIED | Grep for all Google Contacts identifiers in functions.php returns zero matches |
| 4 | Google Contacts cron hook (rondo_google_contacts_sync) is deregistered | VERIFIED | wp_clear_scheduled_hook('rondo_google_contacts_sync') present at top level of functions.php (lines 392-394) |
| 5 | No Calendar sync classes exist in the codebase | VERIFIED | All 4 files confirmed absent: class-calendar-sync.php, class-google-calendar-provider.php, class-calendar-connections.php, class-rest-calendar.php |
| 6 | Calendar sync REST endpoints return 404 (routes no longer registered) | VERIFIED | new RESTCalendar() removed from rondo_init(); no Calendar sync routes registered |
| 7 | functions.php no longer loads any Calendar sync classes | VERIFIED | Grep for RESTCalendar, Calendar\\Sync, Calendar\\Connections, Calendar\\GoogleProvider, and all RONDO_Calendar_* aliases returns zero matches |
| 8 | Calendar sync cron hook (rondo_calendar_sync) is deregistered | VERIFIED | wp_clear_scheduled_hook('rondo_calendar_sync') present at top level of functions.php (lines 397-399) |
| 9 | PHP produces no errors after removal | FAILED | class-google-oauth.php lines 177 and 191 still reference \\RONDO_Calendar_Connections (alias deleted). get_access_token() is called by class-rest-google-sheets.php — token refresh will fatal |

**Score:** 7/9 truths verified (with one partial on REST endpoints noted)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `functions.php` | Theme init without Google Contacts sync references | VERIFIED | No GoogleContacts use statements, aliases, or init calls remain; cron deregistration present |
| `functions.php` | Theme init without Calendar sync references | VERIFIED | No RESTCalendar, Connections, Sync, or GoogleProvider references remain |
| `includes/class-wp-cli.php` | WP-CLI without google-contacts command | VERIFIED | RONDO_Google_Contacts_CLI_Command class and WP_CLI::add_command registration removed; no Google Contacts use statements |
| `includes/class-wp-cli.php` | WP-CLI without calendar sync commands | VERIFIED | sync(), status(), auto_log() methods removed from RONDO_Calendar_CLI_Command; only rematch() (uses Matcher — preserved) remains |
| `includes/class-calendar-matcher.php` | Calendar Matcher still intact (shared utility) | VERIFIED | File exists; referenced in class-auto-title.php line 242 and class-wp-cli.php |
| `includes/class-caldav-provider.php` | CalDAV Provider still intact | VERIFIED | File exists; referenced in class-wp-cli.php use statements |
| `includes/class-google-oauth.php` | No references to deleted Calendar\Connections class | FAILED | Lines 177 and 191 call \\RONDO_Calendar_Connections::update_credentials() and ::update_connection() — class alias was removed but these calls were not cleaned up |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `functions.php` | `includes/` | use statements + class_alias (Google Contacts) | VERIFIED | No GoogleContactsSync, GoogleContactsExport, GoogleContactsAPI, GoogleContactsConnection, or RESTGoogleContacts in functions.php |
| `functions.php` | `includes/` | use statements + class_alias (Calendar Sync) | VERIFIED | No RESTCalendar, Calendar\\Sync, Calendar\\Connections, Calendar\\GoogleProvider, or RONDO_Calendar_* aliases in functions.php |
| `includes/class-google-oauth.php` | `includes/class-calendar-connections.php` (deleted) | \\RONDO_Calendar_Connections:: calls | BROKEN | Lines 177 and 191 reference deleted class; will fatal at runtime on token refresh |
| `includes/class-auto-title.php` | `includes/class-calendar-matcher.php` | Matcher::on_person_saved call | VERIFIED | Line 242 in auto-title calls \\Rondo\\Calendar\\Matcher::on_person_saved() — Matcher is preserved |

### Requirements Coverage

Phase 198-01 and 198-02 must-haves from frontmatter:

| Requirement | Status | Blocking Issue |
|-------------|--------|----------------|
| 5 Google Contacts class files deleted | SATISFIED | — |
| All Google Contacts references removed from functions.php | SATISFIED | — |
| Google Contacts WP-CLI command removed | SATISFIED | — |
| rondo_google_contacts_sync cron hook deregistered | SATISFIED | — |
| 4 Calendar sync class files deleted | SATISFIED | — |
| All Calendar sync references removed from functions.php | SATISFIED | — |
| Calendar sync WP-CLI commands removed | SATISFIED | — |
| rondo_calendar_sync cron hook deregistered | SATISFIED | — |
| Calendar Matcher and CalDAV Provider preserved | SATISFIED | — |
| PHP produces no errors after removal | BLOCKED | class-google-oauth.php has dead reference to \\RONDO_Calendar_Connections |
| ESLint and build pass cleanly | NOT VERIFIED | Build not re-run during verification — SUMMARY claims pass |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `includes/class-google-oauth.php` | 177, 191 | Call to deleted class alias `\\RONDO_Calendar_Connections` in `get_access_token()` | Blocker | Fatal PHP error on Google Sheets token refresh at runtime |

### Human Verification Required

None — all critical checks are programmatic.

### Gaps Summary

**1 blocker gap found.**

The core file deletions and code cleanups are complete and verified: all 9 class files are deleted, functions.php is clean of all sync references, class-wp-cli.php has all sync commands removed, both cron hooks are deregistered, and the Matcher/CalDAV Provider are preserved.

The gap is in `class-google-oauth.php`: when the Calendar sync code was deleted, the `get_access_token()` method in `GoogleOAuth` was not updated. This method still calls `\RONDO_Calendar_Connections::update_credentials()` (line 177) and `\RONDO_Calendar_Connections::update_connection()` (line 191) — both of which reference an alias that no longer exists. Because `get_access_token()` is actively called by `class-rest-google-sheets.php` (lines 347 and 528), any Google Sheets export that requires a token refresh will trigger a PHP fatal error.

**Fix needed:** Remove or replace the `\RONDO_Calendar_Connections::` calls in `GoogleOAuth::get_access_token()`. Since calendar connections no longer exist, these post-refresh update calls should be removed (or the method refactored to accept a callback). The fresh credentials are returned to the caller, so removing the persistence calls is safe — the next request will simply refresh again.

---

_Verified: 2026-02-20T08:36:08Z_
_Verifier: Claude (gsd-verifier)_
