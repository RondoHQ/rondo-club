---
phase: quick-56
plan: 01
subsystem: ui/person-detail
tags: [sportlink, person-detail, sidebar, read-only]
dependency_graph:
  requires: []
  provides: [sportlink-visibility]
  affects: [person-detail-sidebar]
tech_stack:
  added: []
  patterns: [sidebar-card, conditional-rendering, dutch-locale-dates]
key_files:
  created:
    - src/components/SportlinkCard.jsx
  modified:
    - src/pages/People/PersonDetail.jsx
decisions: []
metrics:
  duration_seconds: 79
  completed_date: 2026-02-12
---

# Quick Task 56: Add Sportlink Info Card to PersonDetail

**One-liner:** Read-only Sportlink card in PersonDetail sidebar showing synced membership fields with Dutch-formatted dates.

## What Was Built

Created a new `SportlinkCard` component that displays Sportlink sync data in the PersonDetail sidebar. The card shows membership information synchronized from Sportlink Club including membership dates, age group, member type, photo date, and parent status.

## Tasks Completed

### Task 1: Create SportlinkCard component and integrate in sidebar
**Files:** `src/components/SportlinkCard.jsx`, `src/pages/People/PersonDetail.jsx`
**Commit:** `0f5f1e48`

Created the SportlinkCard component following the established VOGCard pattern:
- **Visibility logic:** Card only appears when at least one Sportlink field has data (lid-sinds, lid-tot, leeftijdsgroep, type-lid, datum-foto, or isparent)
- **Field rendering:** Empty fields are hidden; only populated fields are shown
- **Date formatting:** All date fields (lid-sinds, lid-tot, datum-foto) use Dutch locale formatting via `format(new Date(value), 'd MMM yyyy')`
- **Boolean display:** isparent field displays as "Ja" or "Nee"
- **Styling:** Uses card pattern with Database icon and text-brand-gradient header, consistent with VOGCard

Integrated the component in PersonDetail sidebar between VOGCard and Todos card.

## Deviations from Plan

None - plan executed exactly as written.

## Technical Details

**Component structure:**
- Props: Receives `acfData` (person.acf object)
- Conditional rendering: Returns null when no Sportlink data exists
- Field list: Uses `dl` element for semantic label/value pairs
- Icon: Database from lucide-react

**ACF field keys:**
- `lid-sinds` (date_picker) → "Lid sinds"
- `lid-tot` (date_picker) → "Lid tot"
- `leeftijdsgroep` (text) → "Leeftijdsgroep"
- `type-lid` (text) → "Type lid"
- `datum-foto` (date_picker) → "Datum foto"
- `isparent` (true_false) → "Ouder van lid"

## Verification Results

- [x] `npm run build` succeeded with no errors
- [x] `npm run lint` passed (no new lint errors introduced)
- [x] SportlinkCard.jsx exists and exports default function component
- [x] PersonDetail.jsx imports and renders SportlinkCard in sidebar
- [x] Component follows VOGCard pattern (card p-6 mb-4, text-brand-gradient header)
- [x] Only populated fields shown, card hidden when no data

## Impact

**User-facing:**
- Club administrators can now see Sportlink sync data directly on person detail pages
- Improved data visibility without requiring navigation to separate sync logs
- Clear indication of which Sportlink fields have been populated during sync

**Developer-facing:**
- Reusable pattern for sidebar info cards established
- Consistent with existing VOGCard component pattern
- No breaking changes to existing functionality

## Self-Check: PASSED

**Created files exist:**
```
FOUND: src/components/SportlinkCard.jsx
```

**Modified files exist:**
```
FOUND: src/pages/People/PersonDetail.jsx
```

**Commits exist:**
```
FOUND: 0f5f1e48
```

## Next Steps

- Consider adding similar info cards for other synced data sources
- User testing to verify field labels are clear and useful
