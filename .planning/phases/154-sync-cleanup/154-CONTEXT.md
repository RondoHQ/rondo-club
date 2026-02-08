# Phase 154: Sync Cleanup - Context

**Gathered:** 2026-02-08
**Status:** Ready for planning

<domain>
## Phase Boundary

Remove all hardcoded role fallback values ("Speler", "Staflid", "Lid") from rondo-sync. The sync should pass through whatever role descriptions Sportlink provides — Rondo Club handles role classification on its end via configured settings (Phase 152/153). No new API integration between rondo-sync and Rondo Club's role settings.

</domain>

<decisions>
## Implementation Decisions

### Fallback strategy
- Sportlink always provides role descriptions for both team members and commissie members — the hardcoded fallbacks are dead code
- Remove all fallback strings: "Speler", "Staflid", "Lid" defaults
- Remove the `determineJobTitleFallback()` function entirely (KernelGameActivities-based classification is unnecessary)
- If a role description is unexpectedly missing: skip the entry and log a warning, continue syncing other members

### Settings fetch behavior
- rondo-sync does NOT need to fetch role settings from Rondo Club's API
- The sync just passes through what Sportlink provides — Rondo Club classifies roles using its own settings
- No new API integration needed for this phase

### Member type classification
- Remove the `member_type` (player/staff) classification from rondo-sync entirely
- Drop the `member_type` column from the `sportlink_team_members` database table
- The `getTeamMemberRole()` function should be simplified to return `role_description` only (or null if missing)
- Rondo Club determines player vs staff classification from its configured role settings, not from sync-provided member_type

### Claude's Discretion
- Order of code changes across the 4 affected files
- Whether to consolidate the skip+warn logic into a shared helper or handle inline
- How to handle the database migration (ALTER TABLE timing)

</decisions>

<specifics>
## Specific Ideas

- The user is confident Sportlink always provides role data — this is a clean removal, not a replacement with alternative logic
- Error handling should be lightweight: warn and skip, don't stop the sync

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 154-sync-cleanup*
*Context gathered: 2026-02-08*
