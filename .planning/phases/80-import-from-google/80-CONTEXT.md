# Phase 80: Import from Google - Context

**Gathered:** 2026-01-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Pull all contacts from user's Google Contacts into Stadion with proper field mapping, duplicate detection, and photo sideloading. This is a one-directional initial import. Export to Google and ongoing sync are separate phases.

</domain>

<decisions>
## Implementation Decisions

### Duplicate handling
- Match by email only — no name-based matching
- Contacts without email addresses are skipped entirely (not imported)
- When duplicate found (matching email): link and fill gaps only
  - Store google_contact_id on existing person
  - Add Google data only for empty fields in Stadion
  - Existing Stadion data is never overwritten

### Field mapping
- Multiple emails/phones: Import all into ACF repeater fields
- Team data: Create/find team post, add work_history entry with title
- Google notes field: Skip (not imported)
- Google contact groups/labels: Skip (not imported)
- Birthday: Create as important_date (from requirements)
- Addresses, URLs, biography: Map to corresponding Stadion fields

### Photo handling
- Sideload photos only for contacts that don't already have a photo in Stadion
- If photo download fails: skip silently, continue with import
- Photos uploaded to WordPress media library and set as person featured image

### Claude's Discretion
- Import trigger mechanism (auto after connect vs manual button)
- Progress feedback UI during import
- Batch size and rate limiting for API calls
- Summary/feedback after import completion

</decisions>

<specifics>
## Specific Ideas

- Phase 79 set a `has_pending_import` flag after OAuth — can be used to trigger import
- Stadion is source of truth — Google data fills gaps but never overwrites

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 80-import-from-google*
*Context gathered: 2026-01-17*
