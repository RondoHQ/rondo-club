# Requirements: Caelis v4.9 Dashboard & Calendar Polish

**Defined:** 2026-01-17
**Core Value:** Personal CRM with multi-user collaboration capabilities

## v1 Requirements

Requirements for v4.9 release. Each maps to roadmap phases.

### Dashboard

- [ ] **DASH-01**: All dashboard widgets have consistent fixed heights
- [ ] **DASH-02**: Widgets display internal scrollbar when content overflows
- [ ] **DASH-03**: Dashboard layout remains stable during data loading and refresh
- [ ] **DASH-04**: Stats row widgets have uniform height
- [ ] **DASH-05**: Activity widget has fixed height with scroll
- [ ] **DASH-06**: Meetings widget has fixed height with scroll
- [ ] **DASH-07**: Todos widget has fixed height with scroll
- [ ] **DASH-08**: Favorites widget has fixed height with scroll

### Calendar

- [ ] **CAL-01**: User can select multiple calendars per Google Calendar connection
- [ ] **CAL-02**: Selected calendars stored as array in connection settings
- [ ] **CAL-03**: Sync logic iterates through all selected calendars
- [ ] **CAL-04**: Connection card displays count of selected calendars
- [ ] **CAL-05**: Existing single-calendar connections auto-migrate to array format

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Calendar

- **CAL-V2-01**: Multi-calendar selection for CalDAV providers

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| User-configurable widget heights | Complexity not warranted for v4.9 |
| CalDAV multi-calendar selection | CalDAV typically one calendar per connection |

## Traceability

Which phases cover which requirements.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DASH-01 | Phase 77 | Pending |
| DASH-02 | Phase 77 | Pending |
| DASH-03 | Phase 77 | Pending |
| DASH-04 | Phase 77 | Pending |
| DASH-05 | Phase 77 | Pending |
| DASH-06 | Phase 77 | Pending |
| DASH-07 | Phase 77 | Pending |
| DASH-08 | Phase 77 | Pending |
| CAL-01 | Phase 78 | Pending |
| CAL-02 | Phase 78 | Pending |
| CAL-03 | Phase 78 | Pending |
| CAL-04 | Phase 78 | Pending |
| CAL-05 | Phase 78 | Pending |

**Coverage:**
- v1 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0 ✓

---
*Requirements defined: 2026-01-17*
*Last updated: 2026-01-17 after roadmap creation*
