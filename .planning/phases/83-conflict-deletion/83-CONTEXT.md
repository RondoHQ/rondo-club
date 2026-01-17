# Phase 83: Conflict & Deletion - Context

**Gathered:** 2026-01-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Detect conflicts when contacts are modified in both Caelis and Google since the last sync, resolve them with Caelis as source of truth, and handle deletions correctly in both directions. Conflicts are field-level (only same field modified in both systems). Deleting in Caelis propagates to Google; deleting in Google only unlinks in Caelis.

</domain>

<decisions>
## Implementation Decisions

### Conflict detection
- Conflict occurs only when the SAME field is modified in both systems since last sync
- Compare actual field values directly (not hashes)
- Store "last synced" snapshot of field values to detect what changed
- Track all synced fields: name, email, phone, address, birthday, organization

### Resolution strategy
- Fixed strategy: Caelis always wins (no user configuration)
- Before overwriting Google value, log the conflict with both values
- Conflict history stored as activity entries on the person (not post meta)

### Deletion behavior
- Caelis → Google: Delete in Caelis triggers delete in Google via before_delete_post hook
- Google → Caelis: Unlink only, preserve Caelis contact (per 82-02 decision)
- No confirmation period - deletions propagate immediately on next sync
- Use WordPress before_delete_post hook to trigger Google API delete

### User notification
- Conflicts: Activity log only (silent resolution, viewable in history)
- Google deletions: Activity log only ("Google contact deleted, link removed")
- Sync errors: Settings status panel shows error count and last error
- Activity log entries include full detail: field name and both values (e.g., "Email conflict resolved: Google had john@old.com, kept john@new.com")

### Claude's Discretion
- Data structure for storing last synced field values
- How to batch multiple field conflicts into single activity entry
- Error retry logic for Google API delete calls

</decisions>

<specifics>
## Specific Ideas

- Asymmetric deletion model: Caelis is authoritative, so deletions flow Caelis→Google but not Google→Caelis
- Activity log serves as audit trail without being noisy - users can dig in if curious

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 83-conflict-deletion*
*Context gathered: 2026-01-17*
