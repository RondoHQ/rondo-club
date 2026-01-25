---
phase: 82-delta-sync
plan: 01
subsystem: contacts-sync
tags: [google-contacts, wp-cron, background-sync, delta-sync]
dependency-graph:
  requires:
    - 79-01: Google Contacts OAuth connection
    - 80-01: Google Contacts import infrastructure
    - 81-01: Google Contacts export infrastructure
  provides:
    - GoogleContactsSync class with cron scheduling
    - Round-robin user processing for API load distribution
    - Frequency-based sync gating
  affects:
    - 82-02: Delta sync logic will plug into sync_user method
    - 82-03: Sync monitoring will use get_sync_status
tech-stack:
  added: []
  patterns:
    - WP-Cron background processing
    - Round-robin user scheduling via transients
    - Frequency-based rate limiting
key-files:
  created:
    - includes/class-google-contacts-sync.php
  modified:
    - functions.php
decisions:
  - id: cron-schedule-sharing
    decision: Check if every_15_minutes schedule already exists before adding
    rationale: Calendar sync may have already registered this schedule
  - id: default-frequency
    decision: Default sync frequency of 60 minutes (hourly)
    rationale: Balance between freshness and API quota usage
  - id: placeholder-sync
    decision: sync_user logs placeholder message, updates last_sync timestamp
    rationale: Establishes infrastructure, Plan 02 adds actual delta logic
metrics:
  duration: ~5 minutes
  completed: 2026-01-17
---

# Phase 82 Plan 01: Cron Scheduling Infrastructure Summary

WP-Cron scheduling for Google Contacts delta sync with round-robin user processing and frequency-based gating following proven calendar sync patterns.

## What Was Built

### GoogleContactsSync Class

Created `includes/class-google-contacts-sync.php` implementing:

1. **Cron Constants**:
   - `CRON_HOOK = 'stadion_google_contacts_sync'`
   - `CRON_SCHEDULE = 'every_15_minutes'`
   - `USER_INDEX_TRANSIENT = 'stadion_contacts_sync_last_user_index'`

2. **Scheduling Methods**:
   - `add_cron_schedules()` - Adds 15-minute interval (checks if already exists)
   - `schedule_sync()` - Schedules cron event if not already scheduled
   - `unschedule_sync()` - Removes cron event on theme deactivation

3. **Round-Robin Processing**:
   - `run_background_sync()` - Cron callback, processes one user per run
   - `get_users_with_connections()` - Queries users with Google Contacts connections
   - Uses transient to track last processed user index

4. **Frequency Gating**:
   - `is_sync_due()` - Checks if sync interval has elapsed for user
   - Default frequency: 60 minutes
   - Respects user's `sync_frequency` setting in connection data

5. **Placeholder Sync**:
   - `sync_user()` - Logs placeholder message, updates `last_sync` timestamp
   - Will be replaced with actual delta logic in Plan 02

6. **Status & Testing**:
   - `get_sync_status()` - Returns scheduling status for monitoring
   - `force_sync_all()` - Bypasses rate limiting for CLI/testing

### Theme Integration

Updated `functions.php`:
- Added `use Stadion\Contacts\GoogleContactsSync;`
- Instantiate `new GoogleContactsSync()` in `stadion_init()`
- Schedule sync on theme activation
- Unschedule sync on theme deactivation

## Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Cron schedule sharing | Check if exists before adding | Calendar sync already registers 15-minute interval |
| Default sync frequency | 60 minutes | Hourly balances freshness with API quota |
| Placeholder implementation | Log + update timestamp | Establishes infrastructure for Plan 02 |

## Verification Results

- [x] Class file exists with correct namespace
- [x] All 9 required methods implemented
- [x] PHP syntax valid
- [x] functions.php updated and working
- [x] Deployed to production
- [x] Cron event `stadion_google_contacts_sync` scheduled and running

## Commits

| Hash | Description |
|------|-------------|
| 1124d45 | feat(82-01): create GoogleContactsSync class with cron infrastructure |
| d8ed8dd | feat(82-01): register GoogleContactsSync in theme initialization |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Composer autoloader not aware of new class**

- **Found during:** Task 3 deployment
- **Issue:** Class not found error on production - autoloader hadn't been regenerated
- **Fix:** Ran `composer dump-autoload -o` and synced vendor/composer autoload files
- **Files modified:** vendor/composer/autoload_classmap.php, vendor/composer/autoload_static.php

## Next Plan Readiness

Plan 02 (Delta Sync Logic) can proceed:
- [x] GoogleContactsSync class in place with placeholder `sync_user()` method
- [x] Cron scheduling working
- [x] Round-robin user processing operational
- [x] Frequency gating functional
- [x] Connection storage already has `sync_token` field for delta sync
