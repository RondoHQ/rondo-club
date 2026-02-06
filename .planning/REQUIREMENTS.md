# Requirements: Rondo Club v20.0 Configurable Roles

**Defined:** 2026-02-06
**Core Value:** Replace hardcoded club-specific arrays with settings and dynamic queries so any club can use Rondo Club without code changes.

## v20.0 Requirements

### Dynamic Filters

- [ ] **FILT-01**: Age group filter on People list is populated dynamically from distinct `leeftijdsgroep` values in the database
- [ ] **FILT-02**: Member type filter on People list is populated dynamically from distinct `type-lid` values in the database
- [ ] **FILT-03**: REST API endpoint returns available filter options for age group and member type
- [ ] **FILT-04**: Filter options update automatically when new values appear via sync (no code changes needed)

### Role Settings

- [ ] **ROLE-01**: Admin can configure which job titles count as "player roles" via Settings UI
- [ ] **ROLE-02**: Player role options are populated from actual job titles in work_history data
- [ ] **ROLE-03**: Admin can configure which roles are "excluded/honorary" via Settings UI
- [ ] **ROLE-04**: Excluded role options are populated from actual job titles in commissie work_history data
- [ ] **ROLE-05**: Volunteer status calculation uses configured player roles instead of hardcoded array
- [ ] **ROLE-06**: Team detail page uses configured player roles for player/staff split instead of hardcoded array
- [ ] **ROLE-07**: Volunteer status calculation uses configured excluded roles instead of hardcoded array
- [ ] **ROLE-08**: Settings stored as WordPress options (portable, no code changes per club)

### Sync Cleanup

- [ ] **SYNC-01**: Default role fallbacks (`Lid`, `Speler`, `Staflid`) removed from rondo-sync code

## Future Requirements

- Fee category definitions centralized and configurable — needs more design thought (parked)
- Sportlink free field mapping configurable per club — separate todo, depends on Sportlink API investigation

## Out of Scope

| Feature | Reason |
|---------|--------|
| Fee category configuration | Needs more design thought, parked for future milestone |
| Sportlink free field definitions | Separate todo, requires Sportlink API investigation |
| Server path renaming | Requires server-side migration, separate effort |
| SQL table/column renaming | Requires database migration, separate effort |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| FILT-01 | Phase 151 | Pending |
| FILT-02 | Phase 151 | Pending |
| FILT-03 | Phase 151 | Pending |
| FILT-04 | Phase 151 | Pending |
| ROLE-01 | Phase 152 | Pending |
| ROLE-02 | Phase 152 | Pending |
| ROLE-03 | Phase 152 | Pending |
| ROLE-04 | Phase 152 | Pending |
| ROLE-05 | Phase 153 | Pending |
| ROLE-06 | Phase 153 | Pending |
| ROLE-07 | Phase 153 | Pending |
| ROLE-08 | Phase 152 | Pending |
| SYNC-01 | Phase 154 | Pending |

**Coverage:**
- v20.0 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0

---
*Requirements defined: 2026-02-06*
*Last updated: 2026-02-06 after roadmap creation*
