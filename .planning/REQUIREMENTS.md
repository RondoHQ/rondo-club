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
| LABEL-01 | — | Pending |
| LABEL-02 | — | Pending |
| LABEL-03 | — | Pending |
| LABEL-04 | — | Pending |
| LABEL-05 | — | Pending |
| LABEL-06 | — | Pending |
| LABEL-07 | — | Pending |
| LABEL-08 | — | Pending |
| LABEL-09 | — | Pending |
| LABEL-10 | — | Pending |
| CLEAN-01 | — | Pending |
| CLEAN-02 | — | Pending |
| CLEAN-03 | — | Pending |
| CLEAN-04 | — | Pending |
| DOCS-01 | — | Pending |
| DOCS-02 | — | Pending |

**Coverage:**
- v24.1 requirements: 16 total
- Mapped to phases: 0
- Unmapped: 16

---
*Requirements defined: 2026-02-12*
*Last updated: 2026-02-12 after initial definition*
