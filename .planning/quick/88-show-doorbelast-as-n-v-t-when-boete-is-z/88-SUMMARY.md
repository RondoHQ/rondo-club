---
phase: quick-88
plan: 01
subsystem: ui
tags: [react, discipline-cases, filter, tuchtzaken]

requires: []
provides:
  - "isDoorbelastNVT utility helper in src/utils/disciplineCases.js"
  - "n.v.t. display in DisciplineCaseTable Doorbelast column for zero-fee uncharged cases"
  - "n.v.t. filter option in DisciplineCasesList dropdown"
  - "Nee filter excludes n.v.t. cases"
affects: []

tech-stack:
  added: []
  patterns:
    - "isDoorbelastNVT utility in src/utils/disciplineCases.js — shared between table and list page"
    - "getDoorbelastLabel helper in DisciplineCaseTable — single display logic source of truth"

key-files:
  created:
    - src/utils/disciplineCases.js
  modified:
    - src/components/DisciplineCaseTable.jsx
    - src/pages/DisciplineCases/DisciplineCasesList.jsx

key-decisions:
  - "isDoorbelastNVT extracted to src/utils/disciplineCases.js (not exported from component) to avoid react-refresh/only-export-components ESLint warning"
  - "n.v.t. option placed above Nee in dropdown — logical ordering: n.v.t. → Nee → Ja, Sportlink → Ja, Rondo"
  - "Sort: n.v.t. cases get value -1, Nee 0, charged 1 — puts n.v.t. first when sorting ascending"

duration: 8min
completed: 2026-02-19
---

# Quick Task 88: Show Doorbelast as n.v.t. when boete is €0

**Doorbelast column now shows "n.v.t." for zero-fee uncharged discipline cases, with matching filter option and exclusion from the "Nee" filter**

## Performance

- **Duration:** 8 min
- **Started:** 2026-02-19T~
- **Completed:** 2026-02-19T~
- **Tasks:** 2
- **Files modified:** 3 (including 1 created)

## Accomplishments

- Zero-fee uncharged discipline cases display "n.v.t." in the Doorbelast column (table row and expanded row detail)
- Sort by Doorbelast column puts n.v.t. cases first (value -1), then Nee (0), then charged (1)
- New "n.v.t." filter option in dropdown shows only zero-fee uncharged cases
- "Nee" filter now correctly excludes n.v.t. cases (was previously showing them)
- Shared `isDoorbelastNVT` helper in `src/utils/disciplineCases.js` keeps logic DRY

## Task Commits

1. **Task 1: Update DisciplineCaseTable to show n.v.t. for zero-fee uncharged cases** - `8ac6a84e` (feat)
2. **Task 2: Update DisciplineCasesList filter dropdown and filter logic** - `c9945a37` (feat)

## Files Created/Modified

- `src/utils/disciplineCases.js` - New utility with `isDoorbelastNVT(acf)` shared helper
- `src/components/DisciplineCaseTable.jsx` - Uses `isDoorbelastNVT` + `getDoorbelastLabel` for display and sort
- `src/pages/DisciplineCases/DisciplineCasesList.jsx` - Imports helper, updated filter logic, new dropdown option

## Decisions Made

- Extracted `isDoorbelastNVT` to `src/utils/disciplineCases.js` instead of exporting from the component file. ESLint's `react-refresh/only-export-components` rule fires when a component file has both a default component export and named non-component exports — creating a utils file avoids this.
- "n.v.t." option placed above "Nee" in the dropdown (n.v.t. → Nee → Ja, Sportlink → Ja, Rondo) for logical progression.
- Sort value -1 for n.v.t. cases so they sort before "Nee" cases when sorting ascending by Doorbelast.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Moved isDoorbelastNVT to separate utils file to fix ESLint warning**
- **Found during:** Task 1 (DisciplineCaseTable update)
- **Issue:** Plan specified `export function isDoorbelastNVT` directly in the component file, which triggered `react-refresh/only-export-components` ESLint warning (max-warnings 0 policy)
- **Fix:** Created `src/utils/disciplineCases.js` with the helper; both components import from there
- **Files modified:** src/utils/disciplineCases.js (created), src/components/DisciplineCaseTable.jsx (import added)
- **Verification:** `npm run lint` passes 0 warnings
- **Committed in:** 8ac6a84e (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Required for ESLint compliance (max-warnings 0 policy). No scope creep.

## Issues Encountered

None beyond the ESLint deviation above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Feature complete and deployed to production
- No follow-up work needed

## Self-Check: PASSED

- FOUND: src/utils/disciplineCases.js
- FOUND: src/components/DisciplineCaseTable.jsx
- FOUND: src/pages/DisciplineCases/DisciplineCasesList.jsx
- FOUND: commit 8ac6a84e (Task 1)
- FOUND: commit c9945a37 (Task 2)

---
*Phase: quick-88*
*Completed: 2026-02-19*
