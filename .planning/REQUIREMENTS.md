# Requirements: Rondo Club v24.1

**Defined:** 2026-02-12
**Core Value:** Club administrators can manage their members, teams, and important dates through a single integrated system

## v24.1 Requirements

Requirements for dead feature removal. Each maps to roadmap phases.

### Labels Removal

- [ ] **LABEL-01**: person_label taxonomy registration is removed from PHP
- [ ] **LABEL-02**: team_label taxonomy registration is removed from PHP
- [ ] **LABEL-03**: Labels REST API endpoints and response fields are removed
- [ ] **LABEL-04**: Settings/Labels page is removed from the frontend
- [ ] **LABEL-05**: BulkLabelsModal and label bulk actions are removed from all list views
- [ ] **LABEL-06**: Label columns/badges are removed from PeopleList, TeamsList, CommissiesList
- [ ] **LABEL-07**: Label references are removed from PersonDetail
- [ ] **LABEL-08**: Label-related API client methods and hooks are removed
- [ ] **LABEL-09**: ACF JSON for label fields (if any) is removed
- [ ] **LABEL-10**: Label-related tests are removed or updated

### Residual Cleanup

- [ ] **CLEAN-01**: important_date references are removed from PHP backend files
- [ ] **CLEAN-02**: date_type references are removed from PHP backend files
- [ ] **CLEAN-03**: Reminders system references to important_dates are cleaned up
- [ ] **CLEAN-04**: iCal feed references to important_dates are cleaned up

### Documentation

- [ ] **DOCS-01**: AGENTS.md is updated to remove references to removed taxonomies, CPT, and labels
- [ ] **DOCS-02**: Developer docs are updated to reflect simplified data model

## Future Requirements

None — this is a cleanup milestone.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Remove relationship_type taxonomy | Actively used for person relationships |
| Remove workspace/visibility system | Actively used for access control |
| Remove custom fields system | Actively used |
| Refactor reminders system | Only cleaning up stale references, not rebuilding |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| LABEL-01 | Phase 175 | Pending |
| LABEL-02 | Phase 175 | Pending |
| LABEL-03 | Phase 175 | Pending |
| LABEL-04 | Phase 176 | Pending |
| LABEL-05 | Phase 176 | Pending |
| LABEL-06 | Phase 176 | Pending |
| LABEL-07 | Phase 176 | Pending |
| LABEL-08 | Phase 176 | Pending |
| LABEL-09 | Phase 175 | Pending |
| LABEL-10 | Phase 176 | Pending |
| CLEAN-01 | Phase 175 | Pending |
| CLEAN-02 | Phase 175 | Pending |
| CLEAN-03 | Phase 175 | Pending |
| CLEAN-04 | Phase 175 | Pending |
| DOCS-01 | Phase 177 | Pending |
| DOCS-02 | Phase 177 | Pending |

**Coverage:**
- v24.1 requirements: 16 total
- Mapped to phases: 16
- Unmapped: 0

**Phase mapping:**
- Phase 175 (Backend Cleanup): 8 requirements (LABEL-01, LABEL-02, LABEL-03, LABEL-09, CLEAN-01, CLEAN-02, CLEAN-03, CLEAN-04)
- Phase 176 (Frontend Cleanup): 6 requirements (LABEL-04, LABEL-05, LABEL-06, LABEL-07, LABEL-08, LABEL-10)
- Phase 177 (Documentation Updates): 2 requirements (DOCS-01, DOCS-02)

---
*Requirements defined: 2026-02-12*
*Last updated: 2026-02-13 after roadmap creation — 100% coverage achieved*
