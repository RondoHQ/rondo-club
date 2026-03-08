---
phase: 209-data-model-migration
plan: 03
subsystem: frontend
tags: [react, acf, contact-fields, vcard, rest-api]

# Dependency graph
requires:
  - 209-02
provides:
  - Frontend reads directly from fixed ACF fields (email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2)
  - Legacy contact_info repeater removed from ACF field group
  - Backward-compatible contact_info array removed from REST API
  - Simple fixed-field ContactEditModal replaces dynamic repeater form
affects: [rondo-sync]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Fixed contact field access pattern: person.acf.email_1 instead of contact_info array iteration"
    - "ContactEditModal with 6 named fields instead of useFieldArray repeater"

key-files:
  created: []
  modified:
    - src/utils/formatters.js
    - src/components/ContactEditModal.jsx
    - src/utils/socialIcons.js
    - src/pages/People/PersonDetail.jsx
    - src/pages/People/PeopleList.jsx
    - src/components/AccountCard.jsx
    - src/utils/vcard.js
    - src/pages/VOG/VOGList.jsx
    - src/pages/VOG/VOGUpcoming.jsx
    - src/hooks/usePeople.js
    - src/components/PersonEditModal.jsx
    - src/pages/Teams/Kaderlijst.jsx
    - acf-json/group_person_fields.json
    - includes/class-rest-api.php
    - includes/class-rest-people.php
    - style.css
    - package.json
    - CHANGELOG.md

key-decisions:
  - "Social link types (LinkedIn, Twitter, Bluesky, etc.) dropped from person contacts per requirements"
  - "WhatsApp link built from mobile_1 field directly"
  - "Version bumped to 31.7.0 (minor: data model migration, not patch)"

patterns-established:
  - "getFirstEmail(person) reads email_1 then email_2"
  - "getFirstPhone(person) reads mobile_1, telephone_1, mobile_2, telephone_2 in priority order"

requirements-completed: [DATA-04]

# Metrics
duration: 10min
completed: 2026-03-08
---

# Phase 209 Plan 03: Frontend Migration to Fixed Contact Fields Summary

**All frontend code reads from fixed ACF contact fields, legacy contact_info repeater and REST backward-compat layer removed, deployed as v31.7.0**

## Performance

- **Duration:** 10 min
- **Started:** 2026-03-08T11:04:37Z
- **Completed:** 2026-03-08T11:14:37Z
- **Tasks:** 2
- **Files modified:** 18

## Accomplishments
- Replaced all person-related contact_info array reads with direct fixed field access across 12 frontend files
- Rewrote ContactEditModal from dynamic repeater (useFieldArray) to simple 6-field form
- Removed social link types from socialIcons.js, keeping only WhatsApp/Sportlink/FreeScout/MembershipPass
- Removed legacy contact_info repeater from ACF JSON and backward-compat layer from REST API
- Updated vCard export, PeopleList, VOGList, VOGUpcoming, Kaderlijst, AccountCard, PersonEditModal, usePeople hooks
- Bumped version to 31.7.0, deployed to production

## Task Commits

Each task was committed atomically:

1. **Task 1: Update frontend to use fixed contact fields** - `0f59dc70` (feat)
2. **Task 2: Remove legacy ACF fields and backward-compatibility layer** - `9b33eae7` (feat)

## Files Created/Modified
- `src/utils/formatters.js` - Removed normalizeContactInfo, removed contact_info from repeaterFields
- `src/components/ContactEditModal.jsx` - Rewritten to 6 fixed-field form
- `src/utils/socialIcons.js` - Stripped to WhatsApp only, removed unused social icon imports
- `src/pages/People/PersonDetail.jsx` - Direct ACF field reads, simplified contact display
- `src/pages/People/PeopleList.jsx` - getFirstEmail/getFirstPhone helpers from fixed fields
- `src/components/AccountCard.jsx` - hasEmail from email_1 field
- `src/utils/vcard.js` - Export from fixed fields
- `src/pages/VOG/VOGList.jsx` - Fixed field helpers
- `src/pages/VOG/VOGUpcoming.jsx` - Fixed field helpers
- `src/hooks/usePeople.js` - useCreatePerson and useAddEmailToPerson use fixed fields
- `src/components/PersonEditModal.jsx` - Prefill from fixed fields
- `src/pages/Teams/Kaderlijst.jsx` - Person contact helpers from fixed fields
- `acf-json/group_person_fields.json` - contact_info repeater removed
- `includes/class-rest-api.php` - Backward-compat filter and builder removed
- `includes/class-rest-people.php` - contact_info builder call removed
- `style.css` / `package.json` - Version 31.7.0
- `CHANGELOG.md` - v31.7.0 entry

## Decisions Made
- Social link types (LinkedIn, Twitter, etc.) dropped from person contacts per requirements
- WhatsApp link built directly from mobile_1 (no longer from contact_info array search)
- Version bumped to 31.7.0 as minor (data model migration, not patch)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing] Kaderlijst.jsx also had person contact_info reads**
- **Found during:** Task 1
- **Issue:** Kaderlijst.jsx had getPrimaryContactByType and getPrimaryPhone functions reading from person.acf.contact_info
- **Fix:** Updated both functions to read from fixed fields
- **Files modified:** src/pages/Teams/Kaderlijst.jsx
- **Commit:** 0f59dc70

---

**Total deviations:** 1 auto-fixed (1 missing critical)
**Impact on plan:** Essential for correctness - Kaderlijst would have broken without this fix.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All contact data now uses fixed fields end-to-end (migration command, backend PHP, frontend React, REST API)
- Milestone v31.0 Editable Contact Fields complete
- Orphaned contact_info repeater meta rows remain in database (cosmetic, not functional)
- Reverse sync on rondo-sync server should be re-enabled in Phase 211

## Self-Check: PASSED
