# Phase 170: Fixture Format Design - Context

**Gathered:** 2026-02-11
**Status:** Ready for planning

<domain>
## Phase Boundary

Define the JSON fixture structure that will contain all demo data. This is the "contract" between the export command (Phase 171) and the import command (Phase 173). The fixture is a single JSON file committed to the repository, self-contained with no external dependencies.

</domain>

<decisions>
## Implementation Decisions

### Entity scope
- Include: people, teams, commissies, discipline cases, tasks, notes, activities, relationship types
- Include: settings data (fee categories, role config, family discount, plus whatever else is needed for the demo to function)
- Exclude: important_dates (being removed from codebase)
- Exclude: person_labels, team_labels, date_types (being removed from codebase)
- Exclude: photos and avatars (per EXPORT-04)
- Exclude: OAuth/calendar/CardDAV data (per Out of Scope in requirements)

### Notes and activities
- Both user-written notes AND sync-generated activities must be in the fixture
- These are WordPress comments on person posts, distinguished by comment type

### Demo scale
- Production has ~3000 people, but the fixture should contain a representative subset (~200-300 people)
- The subset should cover all scenarios: active members, former members, different teams, different age classes, discipline cases, etc.
- This keeps the fixture manageable for git and import speed

### Claude's Discretion
- JSON structure organization (flat vs nested, entity grouping)
- Reference strategy between entities (slugs, temporary IDs, etc.)
- Human readability vs compactness tradeoffs
- Schema documentation approach (formal JSON Schema vs documented convention)
- Which specific WordPress options count as "settings" for the demo
- File size optimization strategies

</decisions>

<specifics>
## Specific Ideas

- The fixture is committed to the repository (FIX-01), so it should be reasonably readable in diffs
- The subset selection logic (which ~200-300 people to include) will be implemented in Phase 171 (export), but the schema should be designed to work with any subset size

</specifics>

<deferred>
## Deferred Ideas

- **Remove person_labels, team_labels, date_types**: These taxonomy types should be removed from the entire codebase (code, docs, ACF). Captured as a todo.
- **Remove important_dates**: The entire important_dates CPT, its UI, and all related code should be removed. Captured as a todo.

</deferred>

---

*Phase: 170-fixture-format-design*
*Context gathered: 2026-02-11*
