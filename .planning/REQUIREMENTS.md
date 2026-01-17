# Requirements: Caelis v5.0 Google Contacts Sync

**Defined:** 2026-01-17
**Core Value:** Seamless bidirectional sync between Caelis and Google Contacts with Caelis as source of truth

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### OAuth Connection

- [x] **OAUTH-01**: Extend Google OAuth to request contacts scope
- [x] **OAUTH-02**: Support incremental scope addition (users with Calendar-only can add Contacts)
- [x] **OAUTH-03**: Store Google Contacts connection status in user meta
- [x] **OAUTH-04**: Display Google Contacts connection status in Settings > Connections

### Import from Google

- [x] **IMPORT-01**: Pull all contacts from user's Google Contacts via People API
- [x] **IMPORT-02**: Map Google Contact fields to Caelis person fields (names, emails, phones, addresses, URLs, biography)
- [x] **IMPORT-03**: Detect and handle duplicates (match by email first, then name)
- [x] **IMPORT-04**: Store google_contact_id (resourceName) on each imported person
- [x] **IMPORT-05**: Store google_etag for change detection
- [x] **IMPORT-06**: Track sync timestamp per contact
- [x] **IMPORT-07**: Sideload photos from Google to WordPress media library
- [x] **IMPORT-08**: Create birthday as important_date when present
- [x] **IMPORT-09**: Create company from organization data and link to work_history

### Export to Google

- [x] **EXPORT-01**: Push new Caelis contacts to Google Contacts
- [x] **EXPORT-02**: Reverse field mapping (Caelis person fields -> Google Contact format)
- [x] **EXPORT-03**: Upload photos to Google (base64 encoding)
- [x] **EXPORT-04**: Store returned resourceName as google_contact_id
- [x] **EXPORT-05**: Bulk export existing unlinked contacts to Google

### Ongoing Sync

- [x] **SYNC-01**: Background sync via WP-Cron with configurable frequency (15min to daily)
- [x] **SYNC-02**: Delta sync using People API syncToken (only changed contacts)
- [x] **SYNC-03**: Detect changes in Google (new, modified, deleted contacts)
- [x] **SYNC-04**: Detect changes in Caelis (compare post_modified vs last_synced)
- [x] **SYNC-05**: Propagate changes bidirectionally

### Conflict Resolution

- [x] **CONFLICT-01**: Detect conflicts when contact modified in both systems since last sync
- [x] **CONFLICT-02**: Default resolution strategy: Caelis wins
- [ ] **CONFLICT-03**: Configurable resolution strategies (caelis/google/newest/manual)
- [x] **CONFLICT-04**: Log all conflict resolutions for audit

### Deletion Handling

- [x] **DELETE-01**: When contact deleted in Caelis, delete corresponding contact in Google
- [x] **DELETE-02**: When contact deleted in Google, unlink in Caelis only (preserve Caelis data)

### Settings UI

- [x] **SETTINGS-01**: Google Contacts connection card in Settings > Connections
- [x] **SETTINGS-02**: Connect and Disconnect buttons with OAuth flow
- [x] **SETTINGS-03**: Sync status display (last sync time, contacts synced count, error count)
- [x] **SETTINGS-04**: Manual "Sync Now" button to trigger immediate sync
- [x] **SETTINGS-05**: Sync frequency preference dropdown (15min, 1hr, 6hr, daily)
- [ ] **SETTINGS-06**: Conflict resolution strategy preference dropdown
- [x] **SETTINGS-07**: Sync history log viewer showing recent sync operations

### Person Detail Integration

- [x] **PERSON-01**: "View in Google Contacts" link opening Google Contacts

### WP-CLI Commands

- [x] **CLI-01**: `wp caelis google-contacts sync --user-id=ID` trigger sync for user
- [x] **CLI-02**: `wp caelis google-contacts sync --user-id=ID --full` force full resync
- [x] **CLI-03**: `wp caelis google-contacts status --user-id=ID` check sync status
- [x] **CLI-04**: `wp caelis google-contacts conflicts --user-id=ID` list unresolved conflicts
- [x] **CLI-05**: `wp caelis google-contacts unlink-all --user-id=ID` unlink all contacts

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Contact Groups

- **GROUPS-01**: Sync Caelis labels to Google Contact groups
- **GROUPS-02**: Sync Google Contact groups to Caelis labels

### Multi-Account

- **MULTI-01**: Support connecting multiple Google accounts
- **MULTI-02**: Per-contact account assignment
- **MULTI-03**: Cross-account conflict handling

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Real-time sync (webhooks) | Google Contacts has no webhook support |
| Company sync to Google groups | Google doesn't have separate organization entities |
| Relationship sync | Google has limited relationship support, mapping is lossy |
| Custom field sync | No clear definition of what constitutes custom fields |
| "Other Contacts" sync | Auto-created contacts from email interactions, too noisy |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| OAUTH-01 | 79 | Complete |
| OAUTH-02 | 79 | Complete |
| OAUTH-03 | 79 | Complete |
| OAUTH-04 | 79 | Complete |
| IMPORT-01 | 80 | Complete |
| IMPORT-02 | 80 | Complete |
| IMPORT-03 | 80 | Complete |
| IMPORT-04 | 80 | Complete |
| IMPORT-05 | 80 | Complete |
| IMPORT-06 | 80 | Complete |
| IMPORT-07 | 80 | Complete |
| IMPORT-08 | 80 | Complete |
| IMPORT-09 | 80 | Complete |
| EXPORT-01 | 81 | Complete |
| EXPORT-02 | 81 | Complete |
| EXPORT-03 | 81 | Complete |
| EXPORT-04 | 81 | Complete |
| EXPORT-05 | 81 | Complete |
| SYNC-01 | 82 | Complete |
| SYNC-02 | 82 | Complete |
| SYNC-03 | 82 | Complete |
| SYNC-04 | 82 | Complete |
| SYNC-05 | 82 | Complete |
| CONFLICT-01 | 83 | Complete |
| CONFLICT-02 | 83 | Complete |
| CONFLICT-03 | 83 | Pending |
| CONFLICT-04 | 83 | Complete |
| DELETE-01 | 83 | Complete |
| DELETE-02 | 83 | Complete |
| SETTINGS-01 | 84 | Complete |
| SETTINGS-02 | 84 | Complete |
| SETTINGS-03 | 84 | Complete |
| SETTINGS-04 | 84 | Complete |
| SETTINGS-05 | 84 | Complete |
| SETTINGS-06 | 84 | Pending |
| SETTINGS-07 | 84 | Complete |
| PERSON-01 | 84 | Complete |
| CLI-01 | 85 | Complete |
| CLI-02 | 85 | Complete |
| CLI-03 | 85 | Complete |
| CLI-04 | 85 | Complete |
| CLI-05 | 85 | Complete |

**Coverage:**
- v1 requirements: 38 total
- Mapped to phases: 38
- Unmapped: 0

---
*Requirements defined: 2026-01-17*
*Last updated: 2026-01-18 after Phase 85 completion*
