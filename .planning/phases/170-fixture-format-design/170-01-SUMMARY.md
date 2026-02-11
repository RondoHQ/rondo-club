---
phase: 170-fixture-format-design
plan: 01
subsystem: demo-data
tags:
  - fixture-format
  - schema
  - json
  - demo-data
  - export-import

dependency_graph:
  requires: []
  provides:
    - demo-fixture-schema
    - fixture-reference-system
  affects:
    - phase-171-export
    - phase-173-import

tech_stack:
  added: []
  patterns:
    - reference-id-system
    - portable-fixture-format
    - self-contained-json

key_files:
  created:
    - docs/demo-fixture-schema.md
    - fixtures/demo-fixture.example.json
  modified: []

decisions:
  - "Reference ID system using {entity_type}:{sequential_number} format for portability"
  - "Single JSON file with 8 top-level sections for all entity types"
  - "Explicit exclusion of photos, OAuth data, and deprecated taxonomies"
  - "Import order dependencies documented to handle forward references"

metrics:
  duration_minutes: 4
  tasks_completed: 2
  files_created: 2
  lines_added: 918
  completed_at: "2026-02-11"
---

# Phase 170 Plan 01: Fixture Format Design Summary

**One-liner:** JSON fixture schema with reference ID system for portable demo data covering all entity types (people, teams, commissies, discipline cases, todos, notes, activities, settings)

## What Was Built

Created the complete JSON fixture schema that serves as the contract between Phase 171 (export) and Phase 173 (import). The schema is self-contained, portable, and covers all entity types needed for a working Rondo Club demo.

### Deliverables

1. **Schema Documentation** (`docs/demo-fixture-schema.md`, 547 lines)
   - Complete specification of fixture format with 8 top-level sections
   - Detailed field definitions for all entity types
   - Reference ID system documentation
   - Import order dependencies
   - Explicit list of excluded data types
   - Implementation notes for export and import phases

2. **Example Fixture** (`fixtures/demo-fixture.example.json`, 371 lines)
   - Minimal but valid example demonstrating every entity type
   - Shows cross-entity references (person→team, person→person, discipline_case→person, etc)
   - Realistic Dutch data (fake but format-correct)
   - Demonstrates reference ID portability

## Technical Decisions

### Reference ID System

All cross-entity references use temporary IDs instead of WordPress post IDs:
- Format: `"{entity_type}:{sequential_number}"` (e.g., `"person:1"`, `"team:5"`)
- For taxonomies: `"{entity_type}:{slug}"` (e.g., `"relationship_type:parent"`)
- Makes fixtures portable across WordPress installations
- Importer maintains mapping from fixture refs to new WordPress post IDs

### Fixture Structure

Single JSON file with 8 top-level sections:
1. `meta`: Fixture metadata (version, export timestamp, record counts)
2. `people`: Person posts with ACF fields and Sportlink-synced meta
3. `teams`: Team posts (hierarchical, synced from Sportlink)
4. `commissies`: Commissie posts (hierarchical, synced from Sportlink)
5. `discipline_cases`: Discipline case posts with seizoen taxonomy
6. `todos`: Todo posts with custom statuses
7. `comments`: Notes, activities, and email logs (custom comment types)
8. `taxonomies`: Relationship types and seizoenen terms
9. `settings`: WordPress options needed for demo (fee categories, VOG config, etc)

### Excluded Data

Explicitly documented exclusions:
- Photos and media attachments (per EXPORT-04)
- Deprecated post types: `important_dates`
- Deprecated taxonomies: `person_label`, `team_label`, `date_types`
- OAuth tokens and calendar connections
- User accounts and passwords
- Internal feedback posts

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification criteria passed:
- ✅ Schema documentation covers all 8 entity types
- ✅ Reference ID system documented
- ✅ Excluded data types explicitly listed
- ✅ Example fixture is valid JSON
- ✅ Example contains instances of all entity types
- ✅ Example demonstrates cross-entity references
- ✅ Schema and example are consistent

## Task Breakdown

| Task | Name | Commit | Files | Status |
|------|------|--------|-------|--------|
| 1 | Create fixture schema documentation | ee2b5068 | docs/demo-fixture-schema.md | ✅ Complete |
| 2 | Create minimal example fixture | e0ae7cda | fixtures/demo-fixture.example.json | ✅ Complete |

## Impact on Codebase

**New Files:**
- `docs/demo-fixture-schema.md`: Complete schema specification
- `fixtures/demo-fixture.example.json`: Minimal valid example

**No Code Changes:** This phase is pure documentation/schema design. Implementation happens in Phase 171 (export) and Phase 173 (import).

## Dependencies for Next Phases

**Phase 171 (Export) must:**
- Read production data from all entity types
- Convert WordPress post IDs to fixture refs using sequential numbering
- Serialize ACF repeater fields correctly
- Handle anonymization if needed
- Write JSON in the documented format

**Phase 173 (Import) must:**
- Parse and validate fixture format
- Import in documented order (taxonomies → teams → commissies → people → discipline_cases → todos → comments → settings)
- Build ref → post ID mapping
- Resolve references in second pass after entities created
- Use ACF `update_field()` for complex fields

## Self-Check: PASSED

✅ **Created files exist:**
- docs/demo-fixture-schema.md (547 lines)
- fixtures/demo-fixture.example.json (371 lines)

✅ **Commits exist:**
- ee2b5068: feat(170-01): document demo fixture JSON schema
- e0ae7cda: feat(170-01): create minimal example fixture

✅ **Format validation:**
- Example fixture parses as valid JSON
- Contains all 9 required top-level keys
- Reference IDs follow documented format
- All field types match schema specification

## Notes for Future Work

**For Phase 171 (Export):**
- Consider subset selection strategy for ~200-300 people from ~3000 production records
- Decide on anonymization approach (if needed)
- Handle edge cases: missing ACF fields, null values, empty repeaters

**For Phase 173 (Import):**
- Consider batch processing for large fixtures
- Add progress reporting for long imports
- Validate fixture schema version before import
- Handle conflicts (duplicate dossier_ids, etc)

**Potential Future Enhancements:**
- JSON Schema file for automated validation
- Compression for large fixtures (gzip)
- Incremental import/export (deltas)
- Dry-run mode for import testing
