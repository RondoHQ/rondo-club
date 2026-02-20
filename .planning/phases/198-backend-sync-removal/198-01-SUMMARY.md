---
phase: 198-backend-sync-removal
plan: 01
subsystem: api
tags: [google-contacts, sync, cron, wp-cli, cleanup]

# Dependency graph
requires: []
provides:
  - Google Contacts sync PHP classes deleted (5 files removed)
  - functions.php cleaned of all GoogleContacts imports, aliases, init calls, and cron scheduling
  - class-wp-cli.php cleaned of google-contacts WP-CLI command
  - rondo_google_contacts_sync cron hook deregistered
affects: [199-frontend-cleanup, 202-google-oauth-scope]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Orphaned cron hook cleanup: use wp_clear_scheduled_hook() in functions.php after removing the class that registered it"

key-files:
  created: []
  modified:
    - functions.php
    - includes/class-wp-cli.php
  deleted:
    - includes/class-google-contacts-sync.php
    - includes/class-google-contacts-export.php
    - includes/class-google-contacts-api-import.php
    - includes/class-google-contacts-connection.php
    - includes/class-rest-google-contacts.php

key-decisions:
  - "Added wp_clear_scheduled_hook() call in functions.php (not deactivation hook) so the orphaned cron event is cleaned up on next page load for existing installs"

patterns-established: []

# Metrics
duration: 3min
completed: 2026-02-20
---

# Phase 198 Plan 01: Backend Sync Removal Summary

**Deleted 5 Google Contacts sync PHP class files and scrubbed all references from functions.php and class-wp-cli.php as the first step of v29.0 Made in Europe**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-20T08:22:14Z
- **Completed:** 2026-02-20T08:26:05Z
- **Tasks:** 2
- **Files modified:** 2 modified, 5 deleted

## Accomplishments
- Deleted all 5 Google Contacts sync classes (GoogleContactsSync, GoogleContactsExport, GoogleContactsAPI, GoogleContactsConnection, RESTGoogleContacts)
- Cleaned functions.php: removed 4 use statements, 2 class aliases, 3 init calls (GoogleContactsExport::init, new RESTGoogleContacts, new GoogleContactsSync), and 2 theme lifecycle hooks (activation/deactivation)
- Added one-time cron hook deregistration for `rondo_google_contacts_sync` to clean up orphaned scheduled events on existing installs
- Removed entire RONDO_Google_Contacts_CLI_Command class (sync/status/conflicts/unlink-all subcommands) and WP_CLI::add_command registration from class-wp-cli.php
- Frontend build verified clean after all PHP changes

## Task Commits

Each task was committed atomically:

1. **Task 1: Delete Google Contacts sync class files and remove from functions.php** - `2bbac4e2` (feat)
2. **Task 2: Remove google-contacts WP-CLI command** - `5a0f1c8e` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `functions.php` - Removed all Google Contacts imports, aliases, init calls, activation/deactivation hooks; added cron hook deregistration
- `includes/class-wp-cli.php` - Removed google-contacts use statements, RONDO_Google_Contacts_CLI_Command class, and WP_CLI::add_command registration
- `includes/class-google-contacts-sync.php` - DELETED (Rondo\Contacts\GoogleContactsSync)
- `includes/class-google-contacts-export.php` - DELETED (Rondo\Export\GoogleContactsExport)
- `includes/class-google-contacts-api-import.php` - DELETED (Rondo\Import\GoogleContactsAPI)
- `includes/class-google-contacts-connection.php` - DELETED (Rondo\Contacts\GoogleContactsConnection)
- `includes/class-rest-google-contacts.php` - DELETED (Rondo\REST\GoogleContacts)

## Decisions Made
- Placed cron hook deregistration (`wp_clear_scheduled_hook('rondo_google_contacts_sync')`) at the top level of functions.php (not inside rondo_init or a hook) so it runs on every page load until the event is cleared. This is safe — the check is conditional and WordPress deduplicates cron events.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Google Contacts sync is completely removed from the PHP backend
- Phase 199 (frontend cleanup) can now safely remove any Google Contacts UI components without PHP dependency concerns
- Phase 202 (Google OAuth scope reduction) can proceed knowing Contacts scope is no longer used backend-side

## Self-Check: PASSED

All files verified:
- functions.php: FOUND, PHP syntax clean
- includes/class-wp-cli.php: FOUND, PHP syntax clean
- 5 deleted files: confirmed absent
- 198-01-SUMMARY.md: FOUND
- Commit 2bbac4e2: FOUND
- Commit 5a0f1c8e: FOUND

---
*Phase: 198-backend-sync-removal*
*Completed: 2026-02-20*
