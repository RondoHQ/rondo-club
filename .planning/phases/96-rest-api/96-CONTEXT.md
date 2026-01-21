# Phase 96: REST API - Context

**Gathered:** 2026-01-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Build REST API endpoints for feedback CRUD operations under `prm/v1/feedback`. External tools can create and manage feedback programmatically with application password authentication. Admins can access all feedback; users access only their own.

</domain>

<decisions>
## Implementation Decisions

### Response format
- Follow WordPress REST API conventions (wp/v2 style)
- Top-level id, title, content, with ACF fields in meta object
- Embed author name/email in each feedback item (avoid extra requests)
- Pagination via X-WP-Total and X-WP-TotalPages headers, per_page/page params

### Filtering
- Support core filters: type (bug/feature_request), status, priority
- Match admin UI filtering needs

### Error handling
- Use WordPress REST standard error format: `{ code, message, data: { status, params } }`
- Standard HTTP status codes: 400 validation, 401 no auth, 403 forbidden, 404 not found, 500 server
- Distinguish 403 (forbidden) from 404 (not found) — don't hide existence
- Include debug info only when WP_DEBUG is true

### Authentication
- Application passwords only (no session/nonce auth for this API)
- All endpoints require valid authentication
- Return standard 401 with `{ code: 'rest_not_logged_in' }` on auth failure
- No rate limiting

### Permissions
- Users see only their own feedback (private feedback model)
- Users can always edit their own feedback (title, description, type-specific fields)
- Users can always delete their own feedback
- Users cannot change status — admin only
- Admins can list, read, update, and delete any feedback
- Admins can change status and priority

### Claude's Discretion
- Exact endpoint path structure (e.g., `/feedback` vs `/feedbacks`)
- Schema endpoint implementation details
- Internal helper methods and code organization

</decisions>

<specifics>
## Specific Ideas

No specific requirements — follow existing prm/v1 patterns and WordPress REST conventions.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 96-rest-api*
*Context gathered: 2026-01-21*
