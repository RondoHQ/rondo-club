---
phase: quick-019
plan: 01
subsystem: frontend-ui
tags: [teams, commissies, ui-cleanup, buttons]

dependency-graph:
  requires: []
  provides:
    - "Simplified Teams detail header (Share button only)"
    - "Simplified Commissies detail header (Share button only)"
  affects: []

tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - src/pages/Teams/TeamDetail.jsx
    - src/pages/Commissies/CommissieDetail.jsx

decisions:
  - id: "quick-019-d1"
    choice: "Keep updateTeam/updateCommissie mutations"
    why: "Still needed for logo upload and CustomFieldsSection functionality"

metrics:
  duration: "5 minutes"
  completed: "2025-01-30"
---

# Quick Task 019: Remove Edit/Delete Buttons from Teams and Commissies

Removed Edit and Delete buttons from Teams and Commissies single pages while preserving Share, logo upload, and Custom Fields functionality.

## What Changed

### TeamDetail.jsx
- Removed `Edit` and `Trash2` icon imports from lucide-react
- Removed `TeamEditModal` import and component
- Removed `showEditModal` and `isSaving` state variables
- Removed `deleteTeam` mutation
- Removed `handleDelete` and `handleSaveTeam` functions
- Removed Edit ("Bewerken") and Delete ("Verwijderen") buttons from header
- Kept Share ("Delen") button intact

### CommissieDetail.jsx
- Removed `Edit` and `Trash2` icon imports from lucide-react
- Removed `CommissieEditModal` import and component
- Removed `showEditModal` and `isSaving` state variables
- Removed `deleteCommissie` mutation
- Removed `handleDelete` and `handleSaveCommissie` functions
- Removed Edit ("Bewerken") and Delete ("Verwijderen") buttons from header
- Kept Share ("Delen") button intact

## Preserved Functionality

- Share button and modal
- Logo upload (hover over logo, click camera icon)
- Custom Fields section editing
- All navigation and display features

## Commits

| Hash | Description |
|------|-------------|
| b2c9987 | feat(quick-019): remove Edit and Delete buttons from TeamDetail |
| 990a289 | feat(quick-019): remove Edit and Delete buttons from CommissieDetail |

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- [x] Edit and Delete buttons removed from Teams detail page
- [x] Edit and Delete buttons removed from Commissies detail page
- [x] Share button remains functional on both pages
- [x] Logo upload still works on both pages
- [x] Custom Fields editing still works on both pages
- [x] Build completes successfully
- [x] Deployed to production and verified
