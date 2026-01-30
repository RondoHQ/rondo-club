---
quick_task: 016
subsystem: ui
tags: [react, people-list, contact-info, columns]

# Tech tracking
tech-stack:
  added: []
  patterns: [contact info extraction from ACF repeater fields]

key-files:
  created: []
  modified:
    - includes/class-rest-api.php
    - src/pages/People/PeopleList.jsx

key-decisions: []

# Metrics
duration: 98s
completed: 2026-01-30
---

# Quick Task 016: Email and Phone Columns in People List

**Added email and phone columns to People list with clickable mailto/tel links showing first contact from ACF contact_info repeater**

## Performance

- **Duration:** 98s (1m 38s)
- **Started:** 2026-01-30T13:30:13Z
- **Completed:** 2026-01-30T13:31:51Z
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments
- Email and phone columns available in People list column settings
- Smart extraction of first email/phone from contact_info repeater field
- Clickable mailto: and tel: links for easy contact
- Consistent Dutch labels (E-mail, Telefoon, Laatst gewijzigd)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add email and phone to available columns in backend** - `2af01a3` (feat)
2. **Task 2: Add email and phone column rendering in frontend** - `8a794e6` (feat)
3. **Task 3: Build, deploy, and commit** - Deployed to production

## Files Created/Modified
- `includes/class-rest-api.php` - Added email and phone to CORE_LIST_COLUMNS and get_valid_column_ids(); updated "Last Modified" to "Laatst gewijzigd" for consistency
- `src/pages/People/PeopleList.jsx` - Added helper functions getFirstContactByType() and getFirstPhone() to extract contact values; added email and phone column rendering with clickable links

## Decisions Made
None - followed plan as specified. Updated "Last Modified" label to Dutch "Laatst gewijzigd" for consistency with other column labels.

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
Feature complete and deployed. Email and phone columns functional in production at https://stadion.svawc.nl/

---
*Quick Task: 016*
*Completed: 2026-01-30*
