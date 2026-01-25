# Phase 93: Search Integration - Context

**Gathered:** 2026-01-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Include custom field values in global search results. Users searching for text that exists in custom fields should find matching People/Teams. Creating new search UI or filtering options is out of scope.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

User delegated all decisions to Claude. The following approach aligns with existing search patterns and practical considerations:

**Searchable field types:**
- Text, Textarea, Email, URL — these contain user-searchable text content
- Number — searchable (users might search for phone extensions, ID numbers)
- Select, Checkbox — search the stored values (not just the labels)
- NOT searchable: Image, File (binary data), Color (hex codes not useful), Relationship (IDs not text), Link (complex array), Date (would need special handling), True/False (boolean not text)

**Search behavior:**
- Follow existing pattern: LIKE queries with partial matching (case-insensitive via MySQL default)
- Custom fields searched with lower priority than core fields (first_name score: 60-100, custom fields: 30)
- No highlight marking — matches current implementation

**Performance strategy:**
- Add custom field meta queries to existing search function
- Limit to active custom fields only (inactive fields not searched)
- No additional indexing — WordPress meta queries are sufficient for CRM scale

**Field-level control:**
- All text-based custom fields searchable by default
- No per-field "searchable" toggle — keep it simple, search all applicable types

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches following existing search patterns in `class-rest-api.php`.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 93-search-integration*
*Context gathered: 2026-01-20*
