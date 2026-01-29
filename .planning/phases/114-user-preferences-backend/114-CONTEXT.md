# Phase 114: User Preferences Backend - Context

**Gathered:** 2026-01-29
**Status:** Ready for planning

<domain>
## Phase Boundary

REST API to store and retrieve per-user column preferences for the People list. Provides GET endpoint to retrieve preferences, PATCH endpoint to save them. Storage in wp_usermeta. The UI for column customization is Phase 115 — this phase is backend only.

</domain>

<decisions>
## Implementation Decisions

### Preference data structure
- Store visible columns as simple array of column IDs in display order
- e.g., `['team', 'labels', 'telephone', 'modified']`
- Array order determines column order (no separate order field)
- Name column is always visible and first — not included in preferences
- Configurable columns: team, labels, modified, plus all active custom fields
- Use ACF field name (e.g., 'telephone') as column ID for custom fields — stable across environments

### Default behavior
- Default visible columns: `['team', 'labels', 'modified']` (hardcoded)
- New users see only core columns; they add custom fields as needed
- Empty array on save = reset to defaults (prevents useless empty state)
- PATCH with `{ reset: true }` clears preferences and returns defaults
- No DELETE endpoint needed — reset action handled via PATCH

### Claude's Discretion
- Validation handling for unknown column IDs (log warning vs reject)
- Response format (include defaults flag, column metadata)
- Cache invalidation strategy for preferences

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 114-user-preferences-backend*
*Context gathered: 2026-01-29*
