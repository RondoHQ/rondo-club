---
phase: 147-birthdate-field-widget
plan: 01
subsystem: data-model
tags: [acf, date-fns, person-meta, dashboard-widget, birthdate]

# Dependency graph
requires:
  - phase: 16-infix-tussenvoegsel
    provides: ACF person field structure and name formatting utilities
provides:
  - Birthdate field on person records (ACF date picker, readonly)
  - Person header displays age with birthdate in Dutch format
  - Dashboard birthday widget queries person meta instead of Important Dates CPT
  - Birthday calculation using month/day recurring logic
affects: [148-remove-important-dates, sportlink-sync, person-import-export]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Direct meta queries for birthdate widget (bypasses CPT joins)
    - Dutch date formatting with date-fns (d MMM yyyy format)
    - Recurring date calculation for birthdays (month/day matching)

key-files:
  created: []
  modified:
    - acf-json/group_person_fields.json
    - src/pages/People/PersonDetail.jsx
    - includes/class-reminders.php
    - docs/api-leden-crud.md

key-decisions:
  - "Store birthdate directly on person records (not as Important Date CPT)"
  - "Use Y-m-d format for birthdate storage (ACF standard)"
  - "Display format: age + birthdate (43 jaar (6 feb 1982))"
  - "Dashboard widget now only shows birthdays (other Important Dates removed)"

patterns-established:
  - "Person meta queries for dashboard widgets (direct wpdb queries)"
  - "Dutch month abbreviations in lowercase (jan, feb, mrt, etc.)"
  - "Graceful hiding when data missing (no placeholder text)"

# Metrics
duration: 5min
completed: 2026-02-06
---

# Phase 147 Plan 01: Birthdate Field & Widget Summary

**ACF birthdate field on person records with Dutch-formatted display and dashboard widget querying person meta**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-06T09:15:50Z
- **Completed:** 2026-02-06T09:20:16Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- Birthdate field added to person ACF group (readonly, Sportlink-synced)
- Person header shows age and birthdate: "43 jaar (6 feb 1982)"
- Dashboard upcoming birthdays widget queries person birthdate meta directly
- Birthday calculation uses recurring logic (month/day matching for next occurrence)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add birthdate ACF field and update person header display** - `c0e00116` (feat)
2. **Task 2: Update dashboard widget to query person birthdate meta** - `92e9e575` (feat)
3. **Task 3: Update documentation** - `63775f11` (docs)

## Files Created/Modified
- `acf-json/group_person_fields.json` - Added birthdate date picker field (readonly, 50% width)
- `src/pages/People/PersonDetail.jsx` - Updated age calculation to use acf.birthdate, display with formatted date
- `includes/class-reminders.php` - Replaced Important Dates CPT queries with person birthdate meta queries
- `docs/api-leden-crud.md` - Added birthdate field documentation, removed api-important-dates.md

## Decisions Made

**Display format:** "43 jaar (6 feb 1982)" - includes full birth year for clarity
- Alternative considered: "43 jaar (6 feb)" without year - rejected, less informative
- Dutch month abbreviations in lowercase (jan, feb, mrt) per existing conventions

**Dashboard widget scope:** Now only returns birthdays from person meta
- Alternative: Keep Important Dates CPT queries alongside person meta - rejected, adds complexity
- Phase 148 will remove Important Dates infrastructure entirely

**Graceful hiding:** When no birthdate exists, hide entire age/date line
- Alternative: Show placeholder like "Leeftijd onbekend" - rejected, cleaner without placeholder
- Consistent with existing pattern for optional fields

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - implementation straightforward, all existing patterns worked as expected.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Ready for Phase 148 (Remove Important Dates Infrastructure):
- Dashboard widget no longer queries Important Dates CPT
- Birthdate data model established on person records
- Important Dates API documentation already removed

**No blockers** - Phase 148 can proceed with removing:
- Important Dates CPT registration
- Important Dates ACF fields
- Important Dates REST endpoints
- PersonDates hook and UI components

---
*Phase: 147-birthdate-field-widget*
*Completed: 2026-02-06*
