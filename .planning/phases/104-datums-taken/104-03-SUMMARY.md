---
phase: 104
plan: 03
type: summary
subsystem: ui-localization
tags: [dutch-translation, todo-modals, i18n]

requires: [104-02]
provides:
  - Dutch Todo modal (view/edit)
  - Dutch Global Todo modal (create)
  - Consistent terminology (Leden, Deadline, Gerelateerde personen)
affects: []

decisions:
  - Use "Leden" instead of "People" in all modal contexts
  - Use "Deadline" instead of "Due date" (per CONTEXT.md)
  - Use "Gerelateerde personen" for related people
  - Status terminology: Te doen, Openstaand, Afgerond

tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - src/components/Timeline/TodoModal.jsx
    - src/components/Timeline/GlobalTodoModal.jsx

metrics:
  duration: 4min
  completed: 2026-01-25
---

# Phase 104 Plan 03: Todo Modals Translation Summary

Translate Todo modals (view/edit and global create) to Dutch with consistent terminology.

## What Was Built

### TodoModal.jsx Translation
- **Modal headers**: "Taak toevoegen", "Taak bekijken", "Taak bewerken"
- **View mode labels**: Beschrijving, Deadline, Notities, Gerelateerde personen
- **Edit mode**: Same labels with Dutch placeholders
- **People selector**: "Lid toevoegen", "Leden zoeken..."
- **Loading states**: "Laden...", "Geen leden gevonden"
- **Buttons**: Sluiten, Bewerken, Annuleren, Opslaan, Toevoegen...
- **Status hint**: Dutch terminology (Te doen, Openstaand, Afgerond)

### GlobalTodoModal.jsx Translation
- **Modal header**: "Taak toevoegen"
- **People label**: "Leden *" (required field indicator)
- **Form labels**: Beschrijving *, Deadline (optioneel), Notities (optioneel)
- **Placeholders**: "Wat moet er gedaan worden?", "Voeg gedetailleerde notities toe..."
- **People selector**: "Lid toevoegen", "Leden zoeken..."
- **Loading states**: "Laden...", "Geen leden gevonden"
- **Buttons**: Annuleren, Toevoegen..., Taak toevoegen

## Decisions Made

### Terminology Choices

1. **"Leden" vs "Personen"**
   - Context: People selector in todo modals
   - Decision: Use "Leden" consistently
   - Rationale: Follows established pattern from Phases 101-103
   - Impact: Consistent with navigation ("Leden" menu item)

2. **"Deadline" vs "Verloopdatum"**
   - Context: Due date field label
   - Decision: Use "Deadline"
   - Rationale: Per CONTEXT.md decision; more natural Dutch term
   - Impact: Clear and familiar to Dutch users

3. **"Gerelateerde personen" vs "Gekoppelde leden"**
   - Context: Related people section header
   - Decision: Use "Gerelateerde personen"
   - Rationale: Broader, more formal term
   - Impact: Matches similar sections elsewhere in the app

## Implementation Notes

### Translation Pattern
- Direct string replacement in JSX
- Maintained all functionality (no logic changes)
- Preserved placeholders, labels, and button states
- Updated loading/empty states

### Status Terminology Consistency
Both modals reference the todo status system. The status hint in TodoModal.jsx uses:
- Te doen (Open/active)
- Openstaand (Pending/awaiting)
- Afgerond (Completed)

These match the terminology established in plan 104-02 (TodosList translation).

### People Selector Labels
Both modals share the same people selector pattern:
- "Lid toevoegen" for add button
- "Leden zoeken..." for search placeholder
- "Laden..." for loading state
- "Geen leden gevonden" for empty state

This creates a consistent experience across all todo creation/editing workflows.

## Deviations from Plan

None - plan executed exactly as written.

## Testing Performed

1. **Lint check**: Passed (pre-existing errors unrelated to changes)
2. **Build**: Successful (npm run build completed)
3. **String verification**: Grepped for remaining English - only comments remain
4. **Commit verification**: Both tasks committed atomically

## Next Phase Readiness

### Blockers
None.

### Integration Points
- TodoModal.jsx is used from TodosList.jsx (person detail pages)
- GlobalTodoModal.jsx is used from TodosList.jsx (global add button)
- Both modals integrate with existing Dutch todo status system

### Outstanding Items
None - all todo modal strings translated.

## Metrics

- **Files modified**: 2
- **Lines changed**: ~36 (23 in TodoModal, 13 in GlobalTodoModal)
- **Commits**: 2 (one per task)
- **Duration**: 4 minutes
- **Build time**: 2.59s

## Related Documentation

- Phase 104 CONTEXT.md: Terminology decisions
- Phase 104 RESEARCH.md: Translation mappings
- Phase 103: Reference for translation patterns

---

*Completed: 2026-01-25*
*Commits: 7e3a68f, 05a2678*
