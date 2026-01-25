# Phase 81: Export to Google - Context

**Gathered:** 2026-01-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Push Stadion contacts to Google Contacts with reverse field mapping. Includes automatic export on save (queued), updating existing linked contacts, and bulk export for unlinked contacts. Delta sync and conflict detection are separate phases (82-83).

</domain>

<decisions>
## Implementation Decisions

### Export Trigger Behavior
- **Auto on save + manual bulk**: New/edited contacts queue for export automatically, plus bulk export for existing unlinked contacts
- **Async processing**: Exports queued for background processing (contact saves immediately, Google push happens in background)
- **Bulk export location**: Button in Settings > Google Contacts for "Export all unlinked contacts to Google"
- **Imported contacts**: Update existing Google contacts when Stadion contact with google_contact_id is saved (push changes back)

### Photo Upload Handling
- **Source**: Featured image only (contact's main photo)
- **No photo**: Export without photo — create/update Google contact without a photo field

### Claude's Discretion
- Field mapping implementation (reverse of import mapping)
- Queue mechanism (WP-Cron, Action Scheduler, or similar)
- Error handling and retry logic for failed exports
- Google API rate limiting approach

</decisions>

<specifics>
## Specific Ideas

- Export queue should be similar to existing cron patterns in the codebase
- Reverse field mapping should mirror the import mapping from Phase 80 (GoogleContactsAPIImport class)
- Photos need base64 encoding for Google People API

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 81-export-to-google*
*Context gathered: 2026-01-17*
