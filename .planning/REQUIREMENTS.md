# Requirements: Rondo Club v23.0 Former Members

**Defined:** 2026-02-09
**Core Value:** Club administrators can manage their members, teams, and important dates through a single integrated system

## v23.0 Requirements

Requirements for the former members milestone. Each maps to roadmap phases.

### Data Model

- [ ] **DATA-01**: Person records have a `former_member` status field (boolean, default false)
- [ ] **DATA-02**: Former member status is settable via REST API (`PATCH /wp/v2/person/{id}`)

### Sync Integration

- [ ] **SYNC-01**: REST API accepts `former_member` field updates from rondo-sync
- [ ] **SYNC-02**: API documentation updated for the former member marking endpoint

### Leden List

- [ ] **LIST-01**: Leden list excludes former members by default
- [ ] **LIST-02**: Filter toggle "Toon oud-leden" shows former members (with visual distinction)

### Dashboard

- [ ] **DASH-01**: Dashboard member count excludes former members

### Search

- [ ] **SRCH-01**: Global search includes former members with visual "oud-lid" indicator

### Teams

- [ ] **TEAM-01**: Team rosters exclude former members from player/staff lists

### Contributie

- [ ] **FEE-01**: Former members included in fee list if they have a `lid-sinds` in the current season
- [ ] **FEE-02**: Pro-rata does NOT apply for former members who left during the season (full season fee)

## Future Requirements

None identified.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Manual marking in UI | Former member status is set by rondo-sync, not by admins in the UI |
| Separate "Oud-leden" navigation page | Filter toggle on Leden list is sufficient |
| Automatic data cleanup/purging | All data is preserved indefinitely |
| Former member notifications | No need to notify about archival |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DATA-01 | TBD | Pending |
| DATA-02 | TBD | Pending |
| SYNC-01 | TBD | Pending |
| SYNC-02 | TBD | Pending |
| LIST-01 | TBD | Pending |
| LIST-02 | TBD | Pending |
| DASH-01 | TBD | Pending |
| SRCH-01 | TBD | Pending |
| TEAM-01 | TBD | Pending |
| FEE-01 | TBD | Pending |
| FEE-02 | TBD | Pending |

**Coverage:**
- v23.0 requirements: 11 total
- Mapped to phases: 0
- Unmapped: 11

---
*Requirements defined: 2026-02-09*
*Last updated: 2026-02-09 after initial definition*
