# Phase 211: Sync Update - Context

**Gathered:** 2026-03-08
**Status:** Ready for planning

<domain>
## Phase Boundary

Update rondo-sync's forward sync (Sportlink → Rondo Club) and reverse sync (Rondo Club → Sportlink) to use the 6 fixed ACF contact fields instead of the contact_info repeater. Re-enable the reverse sync cron on the rondo-sync server. This is a cross-repo phase: code changes in rondo-sync, planning artifacts in rondo-club.

</domain>

<decisions>
## Implementation Decisions

### Forward sync field mapping
- Direct 1:1 mapping: Email→email_1, Email2→email_2, Mobile→mobile_1, Mobile2→mobile_2, Telephone→telephone_1, Telephone2→telephone_2
- Add Mobile2 and Telephone2 from Sportlink if they exist in the Sportlink data (check sportlink-fields.json)
- Apply E.164 phone normalization in rondo-sync before writing to WordPress (don't rely on WordPress PhoneNormalizer ACF hook)
- Bidirectional sync: forward sync writes Sportlink values, reverse sync pushes Rondo Club changes back

### Reverse sync field scope
- Track all 6 contact fields for reverse sync: email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2
- Read from fixed ACF fields only (acf.email_1, etc.) — no repeater fallback needed, all records migrated in Phase 209
- Convert E.164 phone numbers back to local Dutch format (0612345678) before writing to Sportlink
- Rename database tracking columns from email/email2/mobile/phone to email_1/email_2/mobile_1/mobile_2/telephone_1/telephone_2 for consistency across the stack

### Cron re-enablement
- Hourly schedule (same as before)
- Dry-run reverse sync first to validate change detection with new field names, then enable cron
- Manual test run of forward sync for a small batch before letting scheduled cron take over

### Claude's Discretion
- SQLite migration approach for renaming tracking columns (ALTER TABLE vs recreate)
- How to handle Mobile2/Telephone2 if Sportlink doesn't actually provide those fields
- E.164 normalization implementation in Node.js (port PhoneNormalizer logic or use simpler approach)
- Dry-run flag implementation details
- Non-contact tracked fields (datum-vog, freescout-id, financiele-blokkade) — keep existing behavior unchanged

</decisions>

<specifics>
## Specific Ideas

- Bidirectional sync is the goal: Sportlink is source of truth for forward sync, but Rondo Club edits should push back to Sportlink via reverse sync
- Phone numbers should be stored as E.164 in Rondo Club but written in local Dutch format to Sportlink
- The existing non-contact reverse sync fields (datum-vog, freescout-id, financiele-blokkade) should continue working unchanged

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `buildContactInfo()` in `rondo-sync/steps/prepare-rondo-club-members.js` (lines 154-171): Currently builds repeater array, needs to write fixed fields instead
- `extractFieldValue()` in `rondo-sync/lib/detect-rondo-club-changes.js` (lines 21-67): Reads from contact_info repeater, needs to read from fixed fields
- `SPORTLINK_FIELD_MAP` in `rondo-sync/lib/reverse-sync-sportlink.js` (lines 11-25): Maps field names to Sportlink form selectors, needs key renaming
- `TRACKED_FIELDS` in `rondo-sync/lib/sync-origin.js` (lines 24-32): Array of tracked field names, needs updating
- `PhoneNormalizer` class in `rondo-club/includes/class-phone-normalizer.php`: Reference implementation for E.164 normalization to port to Node.js

### Established Patterns
- Forward sync uses REST API to write ACF fields via `acf` object in POST body
- Reverse sync uses Playwright browser automation to fill Sportlink web forms
- Change detection uses SHA-256 hash comparison of tracked field values
- Sync origin tracking prevents infinite loops (forward vs reverse origin flags)

### Integration Points
- Forward sync: `prepare-rondo-club-members.js` builds the WordPress REST API payload
- Reverse sync detection: `detect-rondo-club-changes.js` fetches modified members from `/wp/v2/people?modified_after=`
- Reverse sync execution: `reverse-sync-sportlink.js` writes to Sportlink via browser automation
- Database: `rondo-club-db.js` has tracking tables and functions for change detection
- Cron: `scripts/install-cron.sh` configures hourly reverse sync schedule

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 211-sync-update*
*Context gathered: 2026-03-08*
