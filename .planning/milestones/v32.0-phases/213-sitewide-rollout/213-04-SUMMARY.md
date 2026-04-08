---
phase: 213-sitewide-rollout
plan: 04
subsystem: ui
tags: [react, tailwind, button-css, design-system]

# Dependency graph
requires:
  - phase: 212-button-css-system
    provides: btn-primary, btn-secondary, btn-tertiary, btn-danger CSS classes
provides:
  - People, Teams, Commissies pages with correct button tier assignments
  - Settings sub-pages with back navigation corrected from btn-primary to btn-tertiary
  - Profile, MembershipPassScanner, router with double-prefix bugs fixed
  - Redundant inline-flex/items-center classes cleaned up across 14 files
affects: [213-sitewide-rollout]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "btn-tertiary for back navigation, share, clear filters, and utility actions"
    - "btn-primary for save/create/submit, btn-secondary for cancel and important secondary actions"
    - "Keep only gap-2 and conditional classes alongside btn-* — base styles are now in CSS"

key-files:
  created: []
  modified:
    - src/pages/People/PeopleList.jsx
    - src/pages/People/PersonDetail.jsx
    - src/pages/Teams/TeamDetail.jsx
    - src/pages/Teams/TeamsList.jsx
    - src/pages/Teams/Kaderlijst.jsx
    - src/pages/Commissies/CommissieDetail.jsx
    - src/pages/Commissies/CommissiesList.jsx
    - src/pages/Settings/Settings.jsx
    - src/pages/Settings/CustomFields.jsx
    - src/pages/Settings/RelationshipTypes.jsx
    - src/pages/Settings/FeedbackManagement.jsx
    - src/pages/Profile/Profile.jsx
    - src/pages/MembershipPassScanner.jsx
    - src/router.jsx

key-decisions:
  - "Sync button in PersonDetail is utility (btn-tertiary) since it's a background data refresh, not a primary action"
  - "Webhook aanmaken button in Settings stays btn-secondary — creates something important and sits next to Save"
  - "Test email send button is utility/helper — btn-tertiary"
  - "Klaar button after password reveal is utility dismiss — btn-tertiary"
  - "FeedbackManagement has two back links (access-denied + header) — both changed to btn-tertiary"

patterns-established:
  - "Back navigation links always use btn-tertiary regardless of which Settings sub-page they appear on"
  - "Clear filters buttons always use btn-tertiary (utility, not a primary action)"
  - "Share/export/copy buttons always use btn-tertiary (utility weight)"
  - "Inline edit triggers (Bewerken/Toevoegen in detail panels) use btn-tertiary"

requirements-completed: [ROLL-04, ROLL-05]

# Metrics
duration: 5min
completed: 2026-03-11
---

# Phase 213 Plan 04: People/Teams/Commissies/Settings Button Tier Rollout Summary

**Back navigation, share, filter-clear, and utility buttons across 14 page files migrated from btn-secondary to btn-tertiary; double btn-prefix bugs fixed in Profile and router**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-11T13:17:36Z
- **Completed:** 2026-03-11T13:22:00Z
- **Tasks:** 2
- **Files modified:** 14

## Accomplishments
- People/Teams/Commissies: all back links, share buttons, and clear-filter buttons now use btn-tertiary; inline edit/add buttons in PersonDetail use btn-tertiary
- Settings sub-pages: "Terug naar Instellingen" links corrected from btn-primary to btn-tertiary across CustomFields, RelationshipTypes, and FeedbackManagement (both access-denied and header variants)
- Settings.jsx: copy buttons, test email, password-close, and milestone-add utility actions changed to btn-tertiary; redundant flex/items-center removed from btn-primary buttons
- Fixed double-prefix `btn btn-primary` in Profile.jsx and `btn btn-secondary` in router.jsx
- Cleaned redundant `inline-flex items-center` from Kaderlijst, MembershipPassScanner, and other buttons since btn-* base already includes these styles

## Task Commits

Each task was committed atomically:

1. **Task 1: Apply tier hierarchy to People, Teams, and Commissies pages** - `94682e0a` (feat)
2. **Task 2: Apply tier hierarchy to Settings, Profile, Scanner, and router pages** - `64186db6` (feat)

## Files Created/Modified
- `src/pages/People/PeopleList.jsx` - Export=btn-tertiary, clear filters=btn-tertiary
- `src/pages/People/PersonDetail.jsx` - Error back link, sync, export vCard, all edit/add inline buttons=btn-tertiary
- `src/pages/Teams/TeamDetail.jsx` - Error back link=btn-tertiary, share=btn-tertiary
- `src/pages/Teams/TeamsList.jsx` - Clear filters=btn-tertiary
- `src/pages/Teams/Kaderlijst.jsx` - Refresh button=btn-tertiary, removed redundant inline-flex items-center
- `src/pages/Commissies/CommissieDetail.jsx` - Error back link=btn-tertiary, share=btn-tertiary
- `src/pages/Commissies/CommissiesList.jsx` - Clear filters=btn-tertiary
- `src/pages/Settings/Settings.jsx` - Copy/test/close/milestone utility buttons=btn-tertiary; redundant flex removed
- `src/pages/Settings/CustomFields.jsx` - Back link btn-primary→btn-tertiary; redundant flex removed from "Veld toevoegen"
- `src/pages/Settings/RelationshipTypes.jsx` - Back link btn-primary→btn-tertiary; redundant flex removed from all btns
- `src/pages/Settings/FeedbackManagement.jsx` - Both Terug links btn-secondary/btn-primary→btn-tertiary
- `src/pages/Profile/Profile.jsx` - Fixed `btn btn-primary` double prefix, removed redundant flex
- `src/pages/MembershipPassScanner.jsx` - Person link btn-secondary→btn-tertiary, cleaned inline-flex items-center
- `src/router.jsx` - "Ga terug" `btn btn-secondary`→btn-tertiary

## Decisions Made
- Sync button in PersonDetail is utility (btn-tertiary): background data refresh, not primary action
- Webhook aanmaken stays btn-secondary: creates something important, secondary action next to Save primary
- FeedbackManagement has two separate "Terug naar Instellingen" links — both changed to btn-tertiary
- RelationshipTypes "Standaardwaarden herstellen" utility button: left as btn-secondary per plan (meaningful add-level action)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All 14 files from plan 04 now use correct btn-* tiers
- Build and lint pass clean
- Ready for phase 213 plan 05 (Finance/Contributie pages rollout)

---
*Phase: 213-sitewide-rollout*
*Completed: 2026-03-11*
