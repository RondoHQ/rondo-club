---
phase: 119-restrict-people-editing
plan: 01
subsystem: access-control
tags: [permissions, people, frontend-gating, backend-enforcement]
dependency_graph:
  requires: []
  provides: [can_edit_people capability check, frontend read-only mode for people]
  affects: [PersonDetail page, REST API person editing, current-user endpoint]
tech_stack:
  added: []
  patterns: [map_meta_cap filter for capability gating, conditional UI rendering based on API flags]
key_files:
  created: []
  modified:
    - includes/class-access-control.php
    - includes/class-rest-base.php
    - includes/class-rest-api.php
    - src/pages/People/PersonDetail.jsx
decisions:
  - Static can_edit_people() method on AccessControl for reuse across backend and API
  - map_meta_cap filter intercepts edit_post for person CPT (blocks standard WP REST too)
  - Frontend passes undefined to onEdit/onDelete callbacks to suppress action buttons in child components
  - CustomFieldsSection gets onUpdate=undefined to disable inline editing when user lacks permission
metrics:
  duration: 233s
  completed: 2026-03-09
---

# Quick Task 119: Restrict People Editing Summary

Backend capability enforcement + frontend UI gating to limit people editing to users with FairPlay, Bestuur, VOG, or Financieel roles.

## What Was Done

### Task 1: Backend Enforcement (c7c66c15)

- Added `AccessControl::can_edit_people()` static method checking `fairplay`, `vog`, `financieel`, or `manage_options` capabilities
- Added `map_meta_cap` filter that maps `edit_post` to `do_not_allow` for person posts when user lacks required capabilities -- this blocks both custom endpoints AND standard `PUT /wp/v2/people/{id}`
- Updated `Base::check_person_edit_permission()` to additionally check `can_edit_people()` before the `edit_post` capability check
- Added `can_edit_people` flag to the current-user API response for frontend consumption

### Task 2: Frontend UI Gating (3bf4c458)

Gated all edit UI elements on PersonDetail behind `canEditPeople`:
- Photo upload overlay and file input
- Contact edit "Bewerken" button and empty state "Toevoegen" link
- Relationship "Relatie toevoegen" button, per-relationship edit/delete buttons, and empty state "Toevoegen" link
- Timeline "Notitie" and "Activiteit" add buttons, plus `onEdit`/`onDelete` callbacks on TimelineView
- Todo add button (desktop + mobile), `onEdit`/`onDelete` on TodoItem (desktop + mobile)
- CustomFieldsSection `onUpdate` callback (undefined = read-only)
- ContactEditModal and RelationshipEditModal rendering

Data visibility is unchanged -- all users still see all person information.

## Deviations from Plan

None -- plan executed exactly as written.

## Verification

- Build: PASSED
- Lint: PASSED (zero warnings)
- Deployed to production

## Self-Check: PASSED
