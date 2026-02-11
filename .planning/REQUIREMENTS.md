# Requirements: Rondo Club

**Defined:** 2026-02-11
**Core Value:** Club administrators can manage their members, teams, and important dates through a single integrated system

## v24.0 Requirements

Requirements for Demo Data milestone. Each maps to roadmap phases.

### Export

- [ ] **EXPORT-01**: User can run `wp rondo demo export` to create an anonymized fixture file from current data
- [ ] **EXPORT-02**: Exported persons have Dutch fake names (first, infix, last), fake emails, fake Dutch phones, and fake Dutch addresses instead of real PII
- [ ] **EXPORT-03**: Exported data includes all entity types: people, teams/commissies, discipline cases, tasks, activities/notes
- [ ] **EXPORT-04**: All photos and avatars are excluded from the export
- [ ] **EXPORT-05**: Financial amounts (Nikki data) are replaced with plausible fake values
- [ ] **EXPORT-06**: Team and commissie names are preserved unchanged
- [ ] **EXPORT-07**: All relationships between entities are preserved (person-team links, discipline case-person links, task-person links)
- [ ] **EXPORT-08**: Settings data (fee categories, role config, family discount config) is included in the export

### Import

- [ ] **IMP-01**: User can run `wp rondo demo import` to load fixture into a target instance
- [ ] **IMP-02**: All dates are shifted relative to today so the demo always looks current (recent activities, upcoming birthdays, etc.)
- [ ] **IMP-03**: User can run `wp rondo demo import --clean` to wipe existing demo data before importing
- [ ] **IMP-04**: After import, all Rondo Club pages render correctly with demo data (leden, teams, contributie, tuchtzaken, tasks, dashboard)

### Fixture

- [ ] **FIX-01**: The fixture is a JSON file committed to the repository
- [ ] **FIX-02**: The fixture is self-contained — importing requires no external connections or services

## Future Requirements

None — this is a self-contained tooling milestone.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Calendar events in demo | OAuth-bound, per-user — too complex for fixture |
| Google Contacts sync data | OAuth-bound, per-user |
| User account creation | Users are created separately via WordPress |
| CardDAV sync data | Per-user connection |
| Automated refresh of fixture | Manual re-export when production data changes significantly |
| Multi-locale support | Dutch only, matching the club context |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| EXPORT-01 | Phase 171 | Pending |
| EXPORT-02 | Phase 172 | Pending |
| EXPORT-03 | Phase 171 | Pending |
| EXPORT-04 | Phase 172 | Pending |
| EXPORT-05 | Phase 172 | Pending |
| EXPORT-06 | Phase 171 | Pending |
| EXPORT-07 | Phase 171 | Pending |
| EXPORT-08 | Phase 171 | Pending |
| IMP-01 | Phase 173 | Pending |
| IMP-02 | Phase 173 | Pending |
| IMP-03 | Phase 173 | Pending |
| IMP-04 | Phase 174 | Pending |
| FIX-01 | Phase 170 | Pending |
| FIX-02 | Phase 170 | Pending |

**Coverage:**
- v24.0 requirements: 14 total
- Mapped to phases: 14
- Unmapped: 0

**Coverage validation:** ✓ 100% requirement coverage

---
*Requirements defined: 2026-02-11*
*Last updated: 2026-02-11 after roadmap creation*
