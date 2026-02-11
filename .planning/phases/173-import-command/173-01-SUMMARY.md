---
phase: 173-import-command
plan: 01
subsystem: Demo Fixture System
tags:
  - import
  - wp-cli
  - fixture
  - data-portability
dependency_graph:
  requires:
    - 171-01 (DemoExport class and reference system)
    - 172-01 (DemoAnonymizer for fixture format)
  provides:
    - DemoImport class with 8 entity import methods
    - wp rondo demo import WP-CLI command
    - Reference resolution system for fixture refs → WordPress IDs
  affects:
    - None (pure import, no side effects on export or other systems)
tech_stack:
  added:
    - Rondo\Demo\DemoImport class
  patterns:
    - Two-pass import for hierarchical and cross-entity references
    - Fixture reference resolution mapping
    - Dependency-ordered import (taxonomies → teams → commissies → people → etc.)
key_files:
  created:
    - includes/class-demo-import.php (DemoImport class - 801 lines)
  modified:
    - functions.php (added DemoImport use statement and class alias)
    - includes/class-wp-cli.php (added import subcommand)
decisions: []
metrics:
  duration: 173
  completed: 2026-02-11
---

# Phase 173 Plan 01: Demo Import Pipeline Summary

**One-liner:** Full-featured demo data importer with dependency-ordered entity creation, two-pass reference resolution, and progress logging for large datasets

## What Was Built

Created the complete import counterpart to Phase 171's export system. The DemoImport class reads a fixture JSON file and imports all 8 entity types into WordPress in the correct dependency order, resolving all fixture references to WordPress post/term IDs.

### Core Components

**1. DemoImport Class (`includes/class-demo-import.php`)**
- Main `import()` orchestration method that imports in dependency order
- `read_fixture()` validates fixture format and version
- `resolve_ref()` maps fixture refs to WordPress IDs
- 8 entity-specific import methods

**2. Import Methods (in execution order)**
1. `import_taxonomies()` - relationship_types and seizoenen with inverse mappings
2. `import_teams()` - two-pass: create posts, then resolve parent hierarchy
3. `import_commissies()` - two-pass: create posts, then resolve parent hierarchy
4. `import_people()` - two-pass: create posts with simple fields, then resolve all refs
5. `import_discipline_cases()` - create posts, resolve person refs, set seizoen terms
6. `import_todos()` - create posts with custom statuses, resolve related_persons refs
7. `import_comments()` - create comments on person posts with type-specific meta
8. `import_settings()` - update WordPress options, resolve commissie refs in VOG exempt list

**3. WP-CLI Command**
- `wp rondo demo import` command with `--input` parameter
- Default fixture path: `fixtures/demo-fixture.json`
- File validation before import

### Key Technical Patterns

**Reference Resolution System:**
- Fixture refs (`"person:1"`, `"team:5"`) are portable across WordPress installations
- As each entity is created, `$this->ref_map["person:1"] = $new_post_id` stores mapping
- Later entities resolve refs: `$person_id = $this->resolve_ref("person:1")`
- Handles post refs (person, team, commissie, discipline_case, todo) and term refs (relationship_type)

**Two-Pass Import:**
- **Teams/Commissies:** Pass 1 creates all posts, Pass 2 resolves parent hierarchy
- **People:** Pass 1 creates posts with simple ACF fields, Pass 2 resolves refs in work_history, werkfuncties, and relationships repeaters

**Progress Logging:**
- Log counts after each entity type
- For people: progress update every 100 records (matches export pattern)

**ACF Field Handling:**
- Simple fields: `update_field()` directly
- Repeaters with refs: build resolved array, then `update_field()`
- Post meta: `update_post_meta()` for non-ACF fields (VOG dates, nikki/fee data)

**Comment Import:**
- Resolve person ref to get `comment_post_ID`
- Type-specific meta handling (note visibility, activity participants, email snapshots)
- Activity participants array resolved from fixture refs to WordPress IDs

**Settings Import:**
- Special handling for `rondo_vog_exempt_commissies`: resolve commissie refs to post IDs
- All other settings: direct `update_option()`

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

1. ✅ `php -l includes/class-demo-import.php` passes (no syntax errors)
2. ✅ All 8 import methods present in class: import_taxonomies, import_teams, import_commissies, import_people, import_discipline_cases, import_todos, import_comments, import_settings
3. ✅ `includes/class-wp-cli.php` has `import` method in `RONDO_Demo_CLI_Command`
4. ✅ `functions.php` has `use Rondo\Demo\DemoImport` statement
5. ✅ Ref resolution pattern (`ref_map`, `resolve_ref()`) present throughout class
6. ✅ Import order matches schema: taxonomies → teams → commissies → people → discipline_cases → todos → comments → settings

## Files Changed

### Created
- `includes/class-demo-import.php` (801 lines)
  - DemoImport class with full import pipeline
  - 8 entity-specific import methods
  - Reference resolution system

### Modified
- `functions.php`
  - Added `use Rondo\Demo\DemoImport;`
  - Added `RONDO_Demo_Import` class alias
- `includes/class-wp-cli.php`
  - Added `use Rondo\Demo\DemoImport;`
  - Added `import()` method to `RONDO_Demo_CLI_Command`

## Testing Notes

Ready for testing with Phase 174 (Import Testing & Example Fixtures):
- Test with minimal fixture (few entities)
- Test with large fixture (hundreds of people)
- Verify all reference types resolve correctly
- Test hierarchical parents (teams, commissies)
- Test cross-entity relationships (people → people, people → teams/commissies)
- Verify imported data displays in Rondo Club UI

## Next Steps

Phase 173-02: Create example demo fixture file with realistic test data
Phase 173-03: Import testing and validation
Phase 174: Documentation and fixture workflow

## Self-Check: PASSED

✅ All created files exist:
- includes/class-demo-import.php

✅ All commits exist:
- 691849c1 (feat(173-01): add DemoImport class with full import pipeline)
- 9131a261 (feat(173-01): register WP-CLI import subcommand)

✅ All modified files contain expected changes:
- functions.php has DemoImport use statement and class alias
- class-wp-cli.php has import method and DemoImport use statement
