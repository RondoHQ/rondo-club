---
phase: quick-74
plan: 01
subsystem: discipline-cases
tags: [ui, filtering, client-side]
dependency_graph:
  requires: []
  provides: [tuchtzaken-doorbelast-filter, tuchtzaken-sanctie-filter]
  affects: [discipline-case-list]
tech_stack:
  added: []
  patterns: [react-usememo-filtering, useState-filter-state]
key_files:
  created: []
  modified:
    - src/pages/DisciplineCases/DisciplineCasesList.jsx
    - src/components/DisciplineCaseTable.jsx
decisions:
  - Client-side filtering via useMemo (small datasets, instant feedback)
  - Filter state managed via useState (session persistence)
  - Doorbelast filter uses select dropdown matching ACF field values
  - Sanctie filter uses text input with case-insensitive search
  - All three filters (season, doorbelast, sanctie) work together
metrics:
  duration: 99
  completed_date: 2026-02-16
---

# Quick Task 74: Filter Tuchtzaken Page by Doorbelast and Sanctie

**One-liner:** Client-side filtering for discipline cases by charge-back status (Nee/Ja,Sportlink/Ja,Rondo) and sanction description (case-insensitive text search)

## Objective

Add filtering capabilities to the Tuchtzaken (Discipline Cases) page to enable users to quickly filter by Doorbelast (charge-back status) and Sanctie (sanction description) without pagination overhead.

## Changes Made

### Task 1: Add filter UI and state to DisciplineCasesList
**Commit:** `21d2a6d7`

Added two new client-side filters to the Tuchtzaken list page:

1. **Doorbelast filter** (dropdown):
   - Options: "Alle doorbelast", "Nee", "Ja, Sportlink", "Ja, Rondo"
   - Filters based on `acf.is_charged` field
   - State: `doorbelastFilter` (useState)

2. **Sanctie filter** (text input):
   - Placeholder: "Zoek op sanctie..."
   - Case-insensitive text search on `acf.sanction_description`
   - State: `sanctieFilter` (useState)

**Filter logic:**
- Created `filteredCases` memo that applies both filters to the cases array
- Doorbelast filter: checks empty string (""), "sportlink", or "rondo" values
- Sanctie filter: case-insensitive substring match using `.toLowerCase()`
- All filters work together (season from API, doorbelast + sanctie on client)

**UI changes:**
- Added filter controls next to existing season dropdown in header
- Used flexbox with `flex-wrap` for responsive layout
- Applied electric-cyan focus ring styling (design system consistency)
- Updated empty state to reflect filtered results with dynamic messaging

**Integration:**
- Updated `<DisciplineCaseTable cases={filteredCases} ... />` to pass filtered subset
- Updated empty state check to use `filteredCases` instead of `cases`

### Task 2: Update DisciplineCaseTable to handle filtered cases
**Commit:** `5694ebc9`

Updated JSDoc documentation to clarify that the `cases` prop may be pre-filtered by the parent component. No functional changes required — component already handles filtered cases correctly through its existing rendering logic.

**Verified behaviors:**
- Component renders whatever cases array is passed (already true)
- Empty state works when `cases.length === 0` (already true)
- Sorting works on filtered subset (already true)
- Selection/invoice features work on filtered cases (already true)

## Deviations from Plan

None — plan executed exactly as written.

## Verification Results

**Build:** ✅ Frontend compiles without errors
**Functionality:**
- ✅ Two new filter controls appear in header
- ✅ Doorbelast dropdown filters correctly for all four states
- ✅ Sanctie text input filters case-insensitively
- ✅ Filters work together (all must pass)
- ✅ Empty state messaging updates dynamically
- ✅ Table updates immediately via useMemo dependency

**Code quality:**
- ✅ Filter state properly managed via useState
- ✅ Memo dependencies correct (cases, doorbelastFilter, sanctieFilter)
- ✅ Styling matches design system (electric-cyan accents, dark mode support)
- ✅ Responsive layout (filters wrap on small screens)

## Technical Details

**Filter implementation:**
```jsx
const filteredCases = useMemo(() => {
  if (!cases) return [];
  return cases.filter(dc => {
    const acf = dc.acf || {};

    // Doorbelast filter
    if (doorbelastFilter !== '') {
      if (doorbelastFilter === 'none' && acf.is_charged) return false;
      if (doorbelastFilter === 'sportlink' && acf.is_charged !== 'sportlink') return false;
      if (doorbelastFilter === 'rondo' && acf.is_charged !== 'rondo') return false;
    }

    // Sanctie filter (case-insensitive)
    if (sanctieFilter.trim() !== '') {
      const sanctionText = (acf.sanction_description || '').toLowerCase();
      if (!sanctionText.includes(sanctieFilter.toLowerCase().trim())) return false;
    }

    return true;
  });
}, [cases, doorbelastFilter, sanctieFilter]);
```

**ACF field reference:**
- `is_charged` (select): "", "sportlink", "rondo"
- `sanction_description` (textarea): Free-form text

## Performance

**Filter performance:** O(n) per filter change, acceptable for typical discipline case counts (< 100 per season)
**State management:** useState for filter values, useMemo for derived filtered array
**Re-render optimization:** Memo dependencies prevent unnecessary recalculation

## Files Modified

| File | Changes | Lines |
|------|---------|-------|
| `src/pages/DisciplineCases/DisciplineCasesList.jsx` | Added filter state, UI, and filtering logic | +58, -6 |
| `src/components/DisciplineCaseTable.jsx` | Updated JSDoc documentation | +1, -1 |

## Success Criteria Met

✅ User can filter discipline cases by Doorbelast status (4 options)
✅ User can filter discipline cases by Sanctie text content
✅ Filters work together with existing season filter
✅ Filter state persists during user's session
✅ UI matches existing design system
✅ Responsive layout for mobile and desktop

## Next Steps

None — feature complete. Users can now filter the Tuchtzaken list by charge-back status and sanction description.

## Self-Check: PASSED

**Created files:** None (all modifications)

**Modified files:**
- ✅ FOUND: src/pages/DisciplineCases/DisciplineCasesList.jsx
- ✅ FOUND: src/components/DisciplineCaseTable.jsx

**Commits:**
- ✅ FOUND: 21d2a6d7 (Task 1)
- ✅ FOUND: 5694ebc9 (Task 2)
