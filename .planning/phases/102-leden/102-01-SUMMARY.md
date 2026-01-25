---
phase: 102-leden
plan: 01
subsystem: ui
tags: [react, dutch-localization, i18n, people-list]

# Dependency graph
requires:
  - phase: 101-dashboard
    provides: Dutch terminology patterns and informal "je/jij" tone
provides:
  - Fully translated People list page with Dutch column headers and filters
  - Translated bulk action modals for visibility, workspace, organization, and labels
  - Consistent use of "lid/leden" singular/plural forms throughout
affects: [102-02, person-detail, person-forms]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Singular/plural logic for lid/leden maintained throughout UI"
    - "Keep 'Workspace' and 'Labels' in English as understood tech terms"

key-files:
  created: []
  modified:
    - src/pages/People/PeopleList.jsx

key-decisions:
  - "Use 'Leden' consistently for people (implies club/association membership)"
  - "Keep 'Workspace' and 'Labels' in English as they're commonly understood tech terms"
  - "Organization column renamed to 'Team' in list view (simpler, matches context)"
  - "Maintain informal 'je/jij' tone consistent with Dashboard translation"

patterns-established:
  - "Singular/plural: lid/leden throughout interface"
  - "Empty states use encouraging informal tone: 'Voeg je eerste lid toe om te beginnen'"

# Metrics
duration: 5min
completed: 2026-01-25
---

# Phase 102 Plan 01: Leden List Translation Summary

**Complete Dutch translation of People list page with column headers (Voornaam, Achternaam, Team), filters (Alleen favorieten, Geboortejaar, Laatst gewijzigd, Eigenaar), and bulk action modals (Zichtbaarheid, Workspace, Team, Labels)**

## Performance

- **Duration:** 5 min
- **Started:** 2026-01-25T15:43:33Z
- **Completed:** 2026-01-25T15:48:20Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- Translated all list page headers, column labels, and sort options to Dutch
- Translated all filter labels, options, and active filter chips
- Translated all four bulk action modals with proper singular/plural forms
- Maintained consistent "lid/leden" terminology throughout
- Kept "Workspace" and "Labels" in English as commonly understood tech terms

## Task Commits

Each task was committed atomically:

1. **Task 1: Translate PeopleList.jsx headers and columns** - `be2236d` (feat)
   - Column headers, sort options, filter labels, empty states

Note: Task 2 bulk modal translations were included in Task 1 commit as they were all part of the same file translation pass.

## Files Created/Modified
- `src/pages/People/PeopleList.jsx` - Complete Dutch translation of people list page including table headers, filters, bulk modals, and empty states

## Decisions Made

**1. Column terminology**
- "Organization" → "Team" in column header (simpler, matches user mental model per CONTEXT.md)
- "First Name" → "Voornaam", "Last Name" → "Achternaam" (standard Dutch)
- Kept "Workspace" and "Labels" in English (commonly understood tech terms)

**2. Singular/plural forms**
- Consistent use of "lid" (singular) / "leden" (plural) throughout
- Applied to selection toolbar, bulk modals, empty states
- Examples: "3 leden geselecteerd", "Toepassen op 1 lid", "Geen leden gevonden"

**3. Informal tone**
- Used "je/jij" consistently: "Voeg je eerste lid toe om te beginnen"
- "Pas je filters aan om meer resultaten te zien"
- Matches Dashboard translation from Phase 101

**4. Filter translations**
- "Favorites only" → "Alleen favorieten"
- "Birth year" → "Geboortejaar" / "All years" → "Alle jaren"
- "Last modified" → "Laatst gewijzigd" with "Laatste X dagen/jaar" options
- "Ownership" → "Eigenaar" with "Alle/Mijn/Gedeeld" options
- Active chips show: "Favorieten", "Geboren 1990", "Gewijzigd: laatste 7 dagen"

**5. Bulk modal terminology**
- "Change Visibility" → "Zichtbaarheid wijzigen"
- "Assign to Workspace" → "Toewijzen aan workspace"
- "Set Organization" → "Team instellen" (consistent with column rename)
- "Manage Labels" → "Labels beheren"
- All use consistent button labels: "Annuleren", "Toepassen...", "Toepassen op X lid/leden"

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - straightforward string replacement translation.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- People list page fully translated and deployed to production
- Ready for Phase 102-02: Person detail page and form translation
- Established patterns:
  - "Leden" terminology throughout
  - Informal "je/jij" tone
  - "Workspace" and "Labels" remain in English
  - Singular/plural logic for lid/leden

No blockers. Phase 102-02 can proceed immediately.

---
*Phase: 102-leden*
*Completed: 2026-01-25*
