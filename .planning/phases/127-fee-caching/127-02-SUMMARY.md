---
phase: 127-fee-caching
plan: 02
subsystem: fees
tags: [caching, invalidation, acf-hooks, membership-fees]

# Dependency graph
requires:
  - phase: 127-01
    provides: Fee cache storage methods in MembershipFees class
provides:
  - Automatic cache invalidation via ACF hooks
  - Family-wide invalidation on address changes
  - REST API cache invalidation
affects: [127-03-rest-integration, 128-payment-reminders]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - ACF filter pattern for cache invalidation
    - Family grouping for cascading invalidation

key-files:
  created:
    - includes/class-fee-cache-invalidator.php
  modified:
    - functions.php

key-decisions:
  - "Address changes invalidate entire family (old family group before update)"
  - "FeeCacheInvalidator loaded in admin/REST/cron context only"

patterns-established:
  - "ACF update_value filter for per-field cache invalidation"
  - "Family-wide invalidation using build_family_groups()"

# Metrics
duration: 3min
completed: 2026-02-01
---

# Phase 127 Plan 02: Cache Invalidation Hooks Summary

**Added FeeCacheInvalidator class with ACF hooks for automatic fee cache invalidation**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-01T09:10:00Z
- **Completed:** 2026-02-01T09:13:XX
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Created FeeCacheInvalidator class with 5 ACF/REST hooks
- Address changes trigger family-wide cache invalidation
- All fee-affecting fields now trigger automatic cache clearing

## Task Commits

Each task was committed atomically:

1. **Task 1: Create FeeCacheInvalidator class** - `743163d4` (feat)
2. **Task 2: Load FeeCacheInvalidator in functions.php** - `de2a9545` (feat)

## Files Created/Modified
- `includes/class-fee-cache-invalidator.php` - New class (161 lines)
- `functions.php` - Added import and initialization

## Hooks Registered

| Hook | Field | Handler | Purpose |
|------|-------|---------|---------|
| `acf/update_value/name=leeftijdsgroep` | leeftijdsgroep | invalidate_person_cache | Fee category changes |
| `acf/update_value/name=addresses` | addresses | invalidate_family_cache | Family grouping changes |
| `acf/update_value/name=work_history` | work_history | invalidate_person_cache | Team membership changes |
| `acf/update_value/name=lid-sinds` | lid-sinds | invalidate_person_cache | Pro-rata calculation |
| `rest_after_insert_person` | - | invalidate_person_cache_rest | REST API updates |

## Key Methods

| Method | Purpose |
|--------|---------|
| `invalidate_person_cache()` | Clear cache for single person |
| `invalidate_family_cache()` | Clear cache for person + all family members |
| `invalidate_family_by_key()` | Helper for family-wide invalidation |
| `invalidate_person_cache_rest()` | REST API hook handler |
| `invalidate_all_caches()` | Bulk invalidation (for settings changes) |

## Decisions Made
- Address invalidation uses OLD family key (before the update) to ensure siblings get invalidated correctly
- FeeCacheInvalidator only loaded in admin/REST/cron contexts (same as VolunteerStatus, InverseRelationships)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - both tasks completed without issues.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Cache invalidation infrastructure complete
- Ready for REST API integration (127-03) to use cached fees
- invalidate_all_caches() available for admin "recalculate all" feature

---
*Phase: 127-fee-caching*
*Completed: 2026-02-01*
