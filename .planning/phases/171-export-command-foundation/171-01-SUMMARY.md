---
phase: 171-export-command-foundation
plan: 01
subsystem: demo-data-export
tags: [wp-cli, export, fixtures, foundation]
requires: []
provides:
  - wp-cli-export-command
  - ref-mapping-system
  - export-orchestration
affects: [demo-data-export]
tech_stack:
  added: [wp-cli-commands]
  patterns: [reference-id-mapping, sequential-id-generation]
key_files:
  created:
    - includes/class-demo-export.php
  modified:
    - includes/class-wp-cli.php
    - functions.php
decisions: []
metrics:
  duration_minutes: 4
  tasks_completed: 2
  files_modified: 3
  completed_at: "2026-02-11T10:18:20Z"
---

# Phase 171 Plan 01: Export Command Foundation Summary

**One-liner:** WP-CLI command `wp rondo demo export` with reference ID mapping system for portable fixture generation

## What Was Built

Created the foundational infrastructure for exporting Rondo Club production data to portable JSON fixtures:

1. **DemoExport Class** (`includes/class-demo-export.php`):
   - Export orchestration with `export()` method that builds complete fixture array
   - Reference ID mapping system via `build_ref_maps()` - converts WordPress post IDs to sequential fixture refs (`person:1`, `team:5`, etc.)
   - `get_ref()` helper method for ref lookups during export
   - Meta section builder with version, timestamp, source, and accurate record counts
   - Stub methods for all 8 entity sections (to be implemented in plans 02-04):
     - `export_people()`
     - `export_teams()`
     - `export_commissies()`
     - `export_discipline_cases()`
     - `export_todos()`
     - `export_comments()`
     - `export_taxonomies()`
     - `export_settings()`
   - JSON file writer with pretty-print formatting

2. **WP-CLI Command** (`includes/class-wp-cli.php`):
   - `RONDO_Demo_CLI_Command` class with `export()` method
   - Command registered as `wp rondo demo`
   - `--output` parameter with default to `fixtures/demo-fixture.json`
   - Progress logging throughout export process
   - Success summary with record counts

3. **Integration** (`functions.php`):
   - Added `use Rondo\Demo\DemoExport` import
   - Created `RONDO_Demo_Export` class alias for backward compatibility

## How It Works

### Reference ID System

The core innovation is the ref-mapping system that makes fixtures portable:

1. **Build Phase:** `build_ref_maps()` queries all WordPress post IDs for each entity type and assigns sequential reference IDs:
   ```
   WordPress ID 123 → "person:1"
   WordPress ID 456 → "person:2"
   WordPress ID 789 → "team:1"
   ```

2. **Export Phase:** When exporting entities (in future plans), use `get_ref()` to convert WP IDs to refs:
   ```php
   $person_ref = $this->get_ref( $post_id, 'person' );
   // Returns "person:42" instead of WordPress ID 123
   ```

3. **Import Phase (Phase 173):** Importer will reverse the mapping, creating new WordPress posts and building its own ref → new ID mapping.

### Export Flow

```
1. build_ref_maps() → Build ID mappings for all entity types
2. build_meta() → Generate meta section with counts
3. export_people() → Export people (stub for now)
4. export_teams() → Export teams (stub)
5. export_commissies() → Export commissies (stub)
6. export_discipline_cases() → Export discipline cases (stub)
7. export_todos() → Export todos (stub)
8. export_comments() → Export comments (stub)
9. export_taxonomies() → Export taxonomies (stub)
10. export_settings() → Export settings (stub)
11. Write JSON with wp_json_encode()
```

## Production Testing

Tested on production with real data:

```bash
$ wp rondo demo export --output=/tmp/test-fixture.json
Exporting demo data to: /tmp/test-fixture.json
Starting demo data export...
Building reference ID mappings...
Reference maps built: 3948 people, 61 teams, 30 commissies, 112 discipline cases, 0 todos
Building meta section...
[...entity export steps...]
Writing fixture to: /tmp/test-fixture.json
Export complete!
Success: Export complete! 3948 people, 61 teams, 30 commissies, 112 discipline cases, 0 todos, 32 comments exported to /tmp/test-fixture.json
```

**Exported JSON Structure:**

```json
{
  "meta": {
    "version": "1.0",
    "exported_at": "2026-02-11T10:18:00+00:00",
    "source": "Production export",
    "record_counts": {
      "people": 3948,
      "teams": 61,
      "commissies": 30,
      "discipline_cases": 112,
      "todos": 0,
      "comments": 32
    }
  },
  "people": [],
  "teams": [],
  "commissies": [],
  "discipline_cases": [],
  "todos": [],
  "comments": [],
  "taxonomies": [],
  "settings": []
}
```

All 9 top-level keys present, meta section accurate, entity arrays empty (as expected - stubs).

## Deviations from Plan

None - plan executed exactly as written.

## Next Steps

The following plans will implement the entity export methods:

- **Plan 02:** Implement `export_people()`, `export_discipline_cases()`, `export_todos()`, `export_comments()`
- **Plan 03:** Implement `export_teams()`, `export_commissies()` with parent hierarchy handling
- **Plan 04:** Implement `export_taxonomies()`, `export_settings()` with commissie ref resolution

Each plan will build on the ref-mapping system established here.

## Technical Decisions

1. **Sequential numbering starting at 1:** Makes fixture refs human-readable and predictable
2. **Separate ref_maps per entity type:** Allows independent numbering (person:1, team:1, etc.)
3. **Protected get_ref() method:** Accessible to subclasses if needed for extension
4. **Stub methods return empty arrays:** Clean separation - each future plan fills in specific methods
5. **JSON formatting flags:** `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` for human-readable, version-controllable fixtures

## Commits

| Commit | Message | Files |
|--------|---------|-------|
| d8f3650d | feat(171-01): create DemoExport class with ref-mapping and export orchestration | includes/class-demo-export.php |
| e23e0bfb | feat(171-01): register wp rondo demo export command | includes/class-wp-cli.php, functions.php |

## Self-Check: PASSED

**Files created:**
- ✓ `includes/class-demo-export.php` exists
- ✓ Contains `namespace Rondo\Demo`
- ✓ Contains `class DemoExport`
- ✓ Contains all required methods

**Files modified:**
- ✓ `includes/class-wp-cli.php` has `RONDO_Demo_CLI_Command`
- ✓ Command registered: `WP_CLI::add_command( 'rondo demo', 'RONDO_Demo_CLI_Command' )`
- ✓ `functions.php` has `use Rondo\Demo\DemoExport`

**Commits exist:**
- ✓ d8f3650d: DemoExport class
- ✓ e23e0bfb: WP-CLI command registration

**Production validation:**
- ✓ Command runs without errors
- ✓ JSON file created at specified path
- ✓ All 9 top-level keys present
- ✓ Meta section has correct structure
- ✓ Record counts accurate (3948 people, 61 teams, 30 commissies, 112 discipline cases, 0 todos, 32 comments)
- ✓ Ref maps built successfully
