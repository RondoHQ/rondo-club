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
| FILT-01 | TBD | Pending |
| FILT-02 | TBD | Pending |
| FILT-03 | TBD | Pending |
| FILT-04 | TBD | Pending |
| ROLE-01 | TBD | Pending |
| ROLE-02 | TBD | Pending |
| ROLE-03 | TBD | Pending |
| ROLE-04 | TBD | Pending |
| ROLE-05 | TBD | Pending |
| ROLE-06 | TBD | Pending |
| ROLE-07 | TBD | Pending |
| ROLE-08 | TBD | Pending |
| SYNC-01 | TBD | Pending |

**Coverage:**
- v20.0 requirements: 13 total
- Mapped to phases: 0
- Unmapped: 13

---
*Requirements defined: 2026-02-06*
*Last updated: 2026-02-06 after initial definition*
