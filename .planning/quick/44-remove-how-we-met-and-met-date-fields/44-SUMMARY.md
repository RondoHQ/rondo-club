---
phase: quick-44
plan: 01
subsystem: data-model
tags: [cleanup, acf, frontend]
dependency_graph:
  requires: []
  provides: [person-simplified-fields]
  affects: [person-acf-schema, person-detail-ui, person-create-flow]
tech_stack:
  added: []
  patterns: [acf-field-removal, ui-section-cleanup]
key_files:
  created: []
  modified:
    - path: acf-json/group_person_fields.json
      description: Removed Story tab and how_we_met/met_date field definitions
    - path: src/pages/People/PersonDetail.jsx
      description: Removed Story section conditional display
    - path: src/components/PersonEditModal.jsx
      description: Removed how_we_met field from form state and UI
    - path: src/hooks/usePeople.js
      description: Removed how_we_met from createPerson payload
decisions: []
metrics:
  duration_seconds: 102
  files_changed: 4
  completed_date: 2026-02-09
---

# Quick Task 44: Remove how_we_met and met_date fields

**One-liner:** Removed deprecated Story tab fields (how_we_met, met_date) from person records that were never adopted in production

## Summary

Cleaned up unused "Story" fields from person records. The how_we_met and met_date fields were part of the initial ACF field design but were never actually used in production. This task removes them from:

- ACF field group definition (Story tab + 2 fields)
- PersonDetail display (conditional Story section)
- PersonEditModal form (field, state initialization, vCard import)
- usePeople hook (createPerson mutation payload)

The removal simplifies the person data model and reduces UI clutter.

## Changes Made

### 1. ACF Field Group (acf-json/group_person_fields.json)

**Removed:**
- Story tab field definition (key: field_person_story)
- How We Met field definition (key: field_how_we_met)
- When We Met field definition (key: field_met_date)

### 2. PersonDetail Display (src/pages/People/PersonDetail.jsx)

**Removed:**
- Conditional Story section rendering (lines 1342-1354)
- Section displayed "Our Story" card with met_date and how_we_met content

### 3. PersonEditModal Form (src/components/PersonEditModal.jsx)

**Removed from:**
- Default form values (line 61)
- Person edit state initialization (line 91)
- Prefill mode reset (line 106)
- Create mode reset (line 121)
- vCard import data mapping (line 171)
- Form textarea field (lines 421-430)

### 4. usePeople Hook (src/hooks/usePeople.js)

**Removed:**
- how_we_met from createPersonMutation payload (line 255)

## Verification

All verification checks passed:

```bash
✓ ACF fields removed from JSON
✓ PersonDetail.jsx clean (no how_we_met or met_date references)
✓ PersonEditModal.jsx clean (no how_we_met references)
✓ usePeople.js clean (no how_we_met references)
✓ npm run build succeeded
```

## Deviations from Plan

None - plan executed exactly as written.

## Impact

**User-facing:**
- Story tab no longer appears in WordPress admin person editor
- "Our Story" card no longer displays on person detail page
- Person create modal no longer shows "Hoe we elkaar kennen" field

**Technical:**
- ACF field group simplified (3 fewer fields)
- Person API responses no longer include how_we_met or met_date
- Form state management simplified (1 fewer field to track)

**Data preservation:**
- Existing how_we_met and met_date data in database is preserved
- Fields are simply hidden from UI and excluded from new records
- Can be restored by re-adding ACF field definitions if needed

## Next Steps

None required. Task complete. Existing data remains in database but is no longer exposed in the UI.

---

## Self-Check: PASSED

**Files verified:**
- [x] acf-json/group_person_fields.json exists and is modified
- [x] src/pages/People/PersonDetail.jsx exists and is modified
- [x] src/components/PersonEditModal.jsx exists and is modified
- [x] src/hooks/usePeople.js exists and is modified

**Commits verified:**
- [x] Commit 018b294c exists and contains all changes

**Build verification:**
- [x] npm run build passed without errors
- [x] No grep matches for how_we_met or met_date in src/ files
