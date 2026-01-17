# Roadmap: Caelis v5.0 Google Contacts Sync

## Overview

Transform Caelis from manual CSV import/export to seamless bidirectional Google Contacts synchronization. Building on existing Google OAuth infrastructure from Calendar integration, this milestone adds Contacts API scope, comprehensive field mapping, delta sync via syncToken, conflict resolution with Caelis as source of truth, and a Settings UI for connection management. Seven phases progress from OAuth foundation through import, export, ongoing sync, conflict handling, UI, and finally CLI commands for administrative operations.

## Milestones

- **v5.0 Google Contacts Sync** - Phases 79-85 (in progress)

## Phases

**Phase Numbering:**
- Integer phases (79, 80, 81): Planned milestone work
- Decimal phases (79.1, 79.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 79: OAuth Foundation** - Extend Google OAuth for Contacts scope with incremental permission addition
- [x] **Phase 80: Import from Google** - Pull all contacts with field mapping, duplicate detection, and photo sideloading
- [x] **Phase 81: Export to Google** - Push Caelis contacts to Google with reverse field mapping
- [x] **Phase 82: Delta Sync** - Background sync with syncToken-based change detection
- [x] **Phase 83: Conflict & Deletion** - Conflict resolution strategies and deletion handling
- [ ] **Phase 84: Settings & Person UI** - Connection management, sync preferences, and person detail integration
- [ ] **Phase 85: Polish & CLI** - WP-CLI commands and final hardening

## Phase Details

### Phase 79: OAuth Foundation
**Goal**: Users can connect Google Contacts via OAuth with existing Calendar infrastructure
**Depends on**: Nothing (first phase of milestone)
**Requirements**: OAUTH-01, OAUTH-02, OAUTH-03, OAUTH-04
**Success Criteria** (what must be TRUE):
  1. User with existing Google Calendar connection can add Contacts scope without re-authenticating
  2. New users can connect Google Contacts in a single OAuth flow
  3. Google Contacts connection status displays in Settings > Connections
  4. Tokens are stored securely using existing Sodium encryption
**Plans**: 2 plans

Plans:
- [x] 79-01-PLAN.md — Backend OAuth infrastructure (GoogleOAuth extension, connection storage, REST endpoints)
- [x] 79-02-PLAN.md — Frontend Settings UI (Contacts subtab, connect/disconnect flow)

### Phase 80: Import from Google
**Goal**: Users can import all their Google Contacts into Caelis with proper field mapping
**Depends on**: Phase 79
**Requirements**: IMPORT-01, IMPORT-02, IMPORT-03, IMPORT-04, IMPORT-05, IMPORT-06, IMPORT-07, IMPORT-08, IMPORT-09
**Success Criteria** (what must be TRUE):
  1. User sees all Google Contacts appear in Caelis after connecting
  2. Contact details (name, email, phone, address, birthday) are correctly mapped
  3. Duplicate contacts are detected by email and merged rather than duplicated
  4. Photos from Google appear on Caelis person profiles
  5. Work history is created from Google organization data
**Plans**: 3 plans

Plans:
- [x] 80-01-PLAN.md — Backend API Import class with Google People API integration and field mapping
- [x] 80-02-PLAN.md — REST endpoint to trigger import and update API client
- [x] 80-03-PLAN.md — Frontend import UI with auto-trigger, progress, and results display

### Phase 81: Export to Google
**Goal**: Users can push Caelis contacts to Google Contacts
**Depends on**: Phase 80
**Requirements**: EXPORT-01, EXPORT-02, EXPORT-03, EXPORT-04, EXPORT-05
**Success Criteria** (what must be TRUE):
  1. New Caelis contact appears in user's Google Contacts after sync
  2. Caelis contact fields (name, email, phone, etc.) are correctly mapped to Google format
  3. Photos uploaded from Caelis appear in Google Contacts
  4. User can bulk export existing unlinked contacts to Google
**Plans**: 3 plans

Plans:
- [x] 81-01-PLAN.md — Core export class with field mapping and Google API integration
- [x] 81-02-PLAN.md — Save hooks, async queue, and REST endpoint for single export
- [x] 81-03-PLAN.md — Bulk export for unlinked contacts with Settings UI

### Phase 82: Delta Sync
**Goal**: Background sync detects and propagates changes in both directions
**Depends on**: Phase 81
**Requirements**: SYNC-01, SYNC-02, SYNC-03, SYNC-04, SYNC-05
**Success Criteria** (what must be TRUE):
  1. Changes in Google Contacts appear in Caelis without manual action
  2. Changes in Caelis contacts appear in Google Contacts without manual action
  3. Sync runs automatically in background at configurable frequency
  4. Only changed contacts are synced (not full re-import every time)
**Plans**: 3 plans

Plans:
- [x] 82-01-PLAN.md — Core sync class with WP-Cron scheduling and round-robin user processing
- [x] 82-02-PLAN.md — Delta sync logic using syncToken for pull and post_modified for push
- [x] 82-03-PLAN.md — REST endpoint for manual sync and Settings UI for frequency control

### Phase 83: Conflict & Deletion
**Goal**: Conflicts are detected and resolved, deletions are handled correctly
**Depends on**: Phase 82
**Requirements**: CONFLICT-01, CONFLICT-02, CONFLICT-04, DELETE-01, DELETE-02
**Success Criteria** (what must be TRUE):
  1. When contact modified in both systems, conflict is detected
  2. Default resolution strategy (Caelis wins) is applied automatically
  3. Conflict resolution is logged as activity entry for audit
  4. Deleting a contact in Caelis removes it from Google Contacts
  5. Deleting a contact in Google only unlinks it in Caelis (preserves Caelis data)
**Plans**: 2 plans

Plans:
- [x] 83-01-PLAN.md — Conflict detection and resolution with field snapshots and activity logging
- [x] 83-02-PLAN.md — Deletion propagation via WordPress hook to Google API

### Phase 84: Settings & Person UI
**Goal**: Users can manage sync connection and view sync status per contact
**Depends on**: Phase 83
**Requirements**: SETTINGS-01, SETTINGS-02, SETTINGS-03, SETTINGS-04, SETTINGS-05, SETTINGS-06, SETTINGS-07, PERSON-01
**Success Criteria** (what must be TRUE):
  1. Google Contacts connection card appears in Settings > Connections
  2. User can connect and disconnect Google Contacts
  3. Sync status shows last sync time, contact count, and error count
  4. Manual "Sync Now" button triggers immediate sync
  5. User can configure sync frequency and conflict resolution strategy
  6. Person detail page shows "View in Google Contacts" link for synced contacts
**Plans**: TBD

Plans:
- [ ] 84-01: TBD

### Phase 85: Polish & CLI
**Goal**: Administrative CLI commands for sync management and final hardening
**Depends on**: Phase 84
**Requirements**: CLI-01, CLI-02, CLI-03, CLI-04, CLI-05
**Success Criteria** (what must be TRUE):
  1. Admin can trigger sync via WP-CLI with `wp caelis google-contacts sync --user-id=ID`
  2. Admin can force full resync with `--full` flag
  3. Admin can check sync status via WP-CLI
  4. Admin can list unresolved conflicts via WP-CLI
  5. Admin can unlink all contacts to reset sync state
**Plans**: TBD

Plans:
- [ ] 85-01: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 79 -> 80 -> 81 -> 82 -> 83 -> 84 -> 85

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 79. OAuth Foundation | 2/2 | Complete | 2026-01-17 |
| 80. Import from Google | 3/3 | Complete | 2026-01-17 |
| 81. Export to Google | 3/3 | Complete | 2026-01-17 |
| 82. Delta Sync | 3/3 | Complete | 2026-01-17 |
| 83. Conflict & Deletion | 2/2 | Complete | 2026-01-17 |
| 84. Settings & Person UI | 0/TBD | Not started | - |
| 85. Polish & CLI | 0/TBD | Not started | - |

---
*Roadmap created: 2026-01-17*
*Total phases: 7 (Phase 79-85)*
*Total v1 requirements: 38*
