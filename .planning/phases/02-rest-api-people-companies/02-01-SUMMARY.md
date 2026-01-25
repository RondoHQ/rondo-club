---
phase: 02-rest-api-people-teams
plan: 01
subsystem: api
tags: [rest-api, php, wordpress, inheritance, refactoring]

# Dependency graph
requires: [01-rest-api-infrastructure]
provides:
  - STADION_REST_People class with people-specific REST endpoints
  - Extracted routes: /dates, /gravatar, /photo
  - Extracted filters: expand_person_relationships, add_person_computed_fields
affects: [02-02-team-endpoints, 03-rest-api-integrations]

# Tech tracking
tech-stack:
  added: []
  patterns: [domain-specific-rest-class]

key-files:
  created: [includes/class-rest-people.php]
  modified: [includes/class-rest-api.php, functions.php]

key-decisions:
  - "STADION_REST_People extends STADION_REST_Base for shared permission and formatting methods"
  - "Registers both routes and filters in constructor for complete domain encapsulation"
  - "Instantiated after STADION_REST_API in stadion_init() to maintain route registration order"

patterns-established:
  - "Domain-specific REST classes: extend STADION_REST_Base, register routes via rest_api_init"

issues-created: []

# Metrics
duration: 8min
completed: 2026-01-13
---

# Phase 2: REST API People & Teams - Plan 01 Summary

**Extract people-related REST API endpoints into dedicated STADION_REST_People class**

## Performance

- **Duration:** 8 min
- **Started:** 2026-01-13
- **Completed:** 2026-01-13
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- Created STADION_REST_People class extending STADION_REST_Base with 5 people-specific methods
- Registered 3 REST routes: /people/{id}/dates, /people/{id}/gravatar, /people/{id}/photo
- Moved rest_prepare_person filters to STADION_REST_People for domain encapsulation
- Removed ~346 lines from class-rest-api.php, added 387-line dedicated class

## Task Commits

Each task was committed atomically:

1. **Task 1: Create STADION_REST_People class with routes and methods** - `410b9b3` (feat)
2. **Task 2: Remove people methods from STADION_REST_API** - `71979cc` (refactor)
3. **Task 3: Update autoloader and instantiate new class** - `9c615db` (chore)

## Files Created/Modified

- `includes/class-rest-people.php` - New class with 5 methods and 3 routes (387 lines)
- `includes/class-rest-api.php` - Removed people routes, filters, and methods (-346 lines)
- `functions.php` - Autoloader mapping and instantiation (+2 lines)

## Decisions Made

- Registered both routes and filters in STADION_REST_People constructor for complete domain ownership
- Used STADION_REST_Base permission methods (check_person_access, check_person_edit_permission) as callbacks
- Used STADION_REST_Base formatting method (format_date) for response formatting

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

- Pattern established for Phase 02-02 (team endpoints extraction)
- STADION_REST_Base inheritance proven to work for domain-specific classes
- Ready to extract team-related endpoints following same approach

---
*Phase: 02-rest-api-people-teams*
*Completed: 2026-01-13*
