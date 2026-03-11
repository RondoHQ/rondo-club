---
phase: quick-127
plan: "01"
subsystem: people/person-detail
tags: [address, crud, modal, ui]
dependency_graph:
  requires: [AddressEditModal component]
  provides: [Address CRUD on PersonDetail page]
  affects: [src/pages/People/PersonDetail.jsx]
tech_stack:
  added: []
  patterns: [relationship CRUD pattern replicated for addresses]
key_files:
  created: []
  modified:
    - src/pages/People/PersonDetail.jsx
decisions:
  - Followed exact relationship CRUD pattern (state vars, handlers, hover buttons) for consistency
  - Used window.confirm for delete confirmation (matching relationship delete pattern)
  - handleSaveAddress does not call queryClient.invalidateQueries (updatePerson mutation already handles cache invalidation via usePeople hook)
metrics:
  duration: ~10min
  completed: 2026-03-11
---

# Quick Task 127: Make Person Address Editable in the Frontend — Summary

**One-liner:** Wired existing AddressEditModal into PersonDetail.jsx enabling full address add/edit/delete, following the established relationship CRUD pattern.

## What Was Done

Connected the pre-existing `AddressEditModal` component to `PersonDetail.jsx`, which previously displayed addresses as read-only. The implementation exactly mirrors the relationship CRUD pattern already in the file.

## Changes Made

### src/pages/People/PersonDetail.jsx

1. **Import added:** `AddressEditModal` imported alongside other modal imports
2. **State variables added** (4 vars): `showAddressModal`, `isSavingAddress`, `editingAddress`, `editingAddressIndex`
3. **`handleSaveAddress`** handler: builds updated addresses array, calls `sanitizePersonAcf` + `updatePerson.mutateAsync`
4. **`handleDeleteAddress`** handler: confirms deletion, splices array, saves via `updatePerson.mutateAsync`
5. **Adressen card header** updated to flex layout with Plus button (canEditPeople-gated)
6. **Address rows** updated with `group` class + Pencil/Trash2 buttons on hover (canEditPeople-gated)
7. **Empty state** updated with "Toevoegen" link for editors
8. **AddressEditModal rendered** at bottom of component (canEditPeople-gated)

## Verification

- `npm run build` — passed (147.37 kB PersonDetail chunk)
- `npm run lint` — passed (0 warnings)
- Deployed to production: https://rondo.svawc.nl/

## Deviations from Plan

None — plan executed exactly as written.
