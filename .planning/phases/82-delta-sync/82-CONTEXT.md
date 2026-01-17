# Phase 82: Delta Sync - Context

**Gathered:** 2026-01-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Background sync detects and propagates changes in both directions. Changes in Google Contacts appear in Caelis without manual action, and changes in Caelis appear in Google Contacts. Only changed contacts are synced (not full re-import every time).

</domain>

<decisions>
## Implementation Decisions

### Sync frequency & triggers
- Default sync frequency: hourly via WP-Cron
- User-configurable frequency in Settings (options: 15min, hourly, daily)
- Event-triggered sync: changes push to Google immediately on contact save in Caelis
- Manual "Sync Now" button available in Settings > Connections for immediate full sync

### Claude's Discretion
- Change detection approach (syncToken, timestamps, field diffing)
- Sync direction order when both sides have changes
- Error handling and retry logic
- Partial sync continuation on failures
- API rate limiting strategy

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches using Google's syncToken mechanism for efficient delta detection.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 82-delta-sync*
*Context gathered: 2026-01-17*
