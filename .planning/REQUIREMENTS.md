# Requirements: Stadion v13.0 Discipline Cases

**Defined:** 2026-02-03
**Core Value:** Personal CRM with team collaboration while maintaining relationship-focused experience

## v13.0 Requirements

Requirements for discipline cases milestone. Each maps to roadmap phases.

### Data Foundation

- [ ] **DATA-01**: `discipline_case` CPT registered with proper labels (Tuchtzaak/Tuchtzaken)
- [ ] **DATA-02**: ACF field group with all case fields (dossier-id, person, match-date, match-description, team-name, charge-codes, charge-description, sanction-description, processing-date, administrative-fee, is-charged)
- [ ] **DATA-03**: Shared `seizoen` taxonomy (non-hierarchical, REST-enabled, usable across features)
- [ ] **DATA-04**: REST API endpoints for discipline case CRUD operations (Sportlink sync compatibility)

### Access Control

- [ ] **ACCESS-01**: `fairplay` capability defined and registered
- [ ] **ACCESS-02**: Admins auto-assigned fairplay capability on theme activation
- [ ] **ACCESS-03**: Discipline case list page restricted to users with fairplay capability
- [ ] **ACCESS-04**: Person Tuchtzaken tab restricted to users with fairplay capability

### List Page

- [ ] **LIST-01**: Discipline cases list page at `/discipline-cases` route
- [ ] **LIST-02**: Table with columns: Person, Match, Date, Sanction, Season
- [ ] **LIST-03**: Season filter dropdown (from seizoen taxonomy)
- [ ] **LIST-04**: Sortable by date column
- [ ] **LIST-05**: Navigation item visible only to users with fairplay capability

### Person Integration

- [ ] **PERSON-01**: "Tuchtzaken" tab on person detail page
- [ ] **PERSON-02**: Tab shows all discipline cases linked to that person
- [ ] **PERSON-03**: Read-only display with case details (match, charges, sanctions, fee)
- [ ] **PERSON-04**: Tab only visible to users with fairplay capability

## Future Requirements

Deferred to later milestones. Tracked but not in current roadmap.

### Capability Management

- **CAPMGMT-01**: Settings UI for assigning/revoking fairplay capability to users
- **CAPMGMT-02**: Bulk capability assignment

### List Enhancements

- **LISTEXT-01**: Team filter on discipline cases list
- **LISTEXT-02**: Export discipline cases to spreadsheet
- **LISTEXT-03**: Search by person name or dossier-id

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Discipline case creation in UI | Data comes from Sportlink sync only |
| Discipline case editing in UI | Read-only per Sportlink data model |
| Appeal/response workflow | Outside current use case |
| Financial integration | Fees are informational only |
| Notifications for new cases | Can be added in future milestone |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DATA-01 | 132 | Pending |
| DATA-02 | 132 | Pending |
| DATA-03 | 132 | Pending |
| DATA-04 | 132 | Pending |
| ACCESS-01 | 133 | Pending |
| ACCESS-02 | 133 | Pending |
| ACCESS-03 | 133 | Pending |
| ACCESS-04 | 133 | Pending |
| LIST-01 | 134 | Pending |
| LIST-02 | 134 | Pending |
| LIST-03 | 134 | Pending |
| LIST-04 | 134 | Pending |
| LIST-05 | 134 | Pending |
| PERSON-01 | 134 | Pending |
| PERSON-02 | 134 | Pending |
| PERSON-03 | 134 | Pending |
| PERSON-04 | 134 | Pending |

**Coverage:**
- v13.0 requirements: 17 total
- Mapped to phases: 17
- Unmapped: 0

---
*Requirements defined: 2026-02-03*
*Last updated: 2026-02-03 after roadmap creation*
