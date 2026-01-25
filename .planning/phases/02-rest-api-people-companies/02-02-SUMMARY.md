---
phase: 02-rest-api-people-companies
plan: 02
subsystem: api
tags: [rest-api, php, wordpress, inheritance, refactoring]

# Dependency graph
requires: [01-rest-api-infrastructure, 02-01-people-endpoints]
provides:
  - STADION_REST_Companies class with company-specific REST endpoints
  - Extracted routes: /companies/{id}/people, /companies/{id}/logo, /companies/{id}/logo/upload
  - Complete separation of company domain from monolithic REST API class
affects: [03-rest-api-integrations]

# Tech tracking
tech-stack:
  added: []
  patterns: [domain-specific-rest-class]

key-files:
  created: [includes/class-rest-companies.php]
  modified: [includes/class-rest-api.php, functions.php]

key-decisions:
  - "STADION_REST_Companies extends STADION_REST_Base for shared permission and formatting methods"
  - "Registers routes in constructor via rest_api_init hook following established pattern"
  - "Instantiated after STADION_REST_People in stadion_init() to maintain route registration order"

patterns-established:
  - "Domain-specific REST classes: extend STADION_REST_Base, register routes via rest_api_init"

issues-created: []

# Metrics
duration: 6min
completed: 2026-01-13
---

# Phase 2: REST API People & Companies - Plan 02 Summary

**Extract company-related REST API endpoints into dedicated STADION_REST_Companies class**

## Performance

- **Duration:** 6 min
- **Started:** 2026-01-13
- **Completed:** 2026-01-13
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Created STADION_REST_Companies class extending STADION_REST_Base with 3 company-specific methods
- Registered 3 REST routes: /companies/{id}/people, /companies/{id}/logo, /companies/{id}/logo/upload
- Removed ~247 lines from class-rest-api.php, added 282-line dedicated class
- Phase 2 (People & Companies extraction) is now complete

## Task Commits

Each task was committed atomically:

1. **Task 1: Create STADION_REST_Companies class with routes and methods** - `bdb5061` (feat)
2. **Task 2: Remove company methods from STADION_REST_API and update autoloader** - `ddea1cc` (refactor)

## Files Created/Modified

- `includes/class-rest-companies.php` - New class with 3 methods and 3 routes (282 lines)
- `includes/class-rest-api.php` - Removed company routes and methods (-247 lines)
- `functions.php` - Autoloader mapping and instantiation (+2 lines)

## Decisions Made

- Used STADION_REST_Base permission method (check_company_edit_permission) as callback for logo endpoints
- Used STADION_REST_Base formatting method (format_person_summary) for employee list response
- Public endpoint for /companies/{id}/people maintained (per existing code) with internal access control

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Phase 2 Completion

Phase 2 (REST API People & Companies) is now complete:
- Plan 02-01: People endpoints extracted to STADION_REST_People
- Plan 02-02: Company endpoints extracted to STADION_REST_Companies

The monolithic class-rest-api.php has been reduced by approximately 600 lines across both plans. The domain-specific REST class pattern is fully established and ready for Phase 3 (Integrations extraction).

---
*Phase: 02-rest-api-people-companies*
*Completed: 2026-01-13*
