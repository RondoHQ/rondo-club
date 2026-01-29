# Requirements: v10.0 Read-Only UI for Sportlink Data

**Defined:** 2026-01-29
**Core Value:** Restrict UI editing for Sportlink-managed data while preserving REST API functionality for automation and sync.

## v10.0 Requirements

### Person Edit Restrictions — 4 requirements

- [x] **PERSON-01**: "Verwijderen" (delete) button is removed from PersonDetail
- [x] **PERSON-02**: "Voeg adres toe" (add address) button is removed from PersonDetail
- [x] **PERSON-03**: "Functie toevoegen" (add function) button is removed from work history section
- [x] **PERSON-04**: Work history items cannot be edited in the UI (no edit button, no click-to-edit)

### Custom Field Edit Control — 2 requirements

- [x] **FIELD-01**: Custom fields have an `editable_in_ui` setting (checkbox in custom field configuration)
- [x] **FIELD-02**: Custom fields with `editable_in_ui=false` display their value but show no edit button

### Organization Edit Restrictions — 2 requirements

- [x] **ORG-01**: Team creation is disabled in UI (no "Nieuw team" button or equivalent)
- [x] **ORG-02**: Commissie creation is disabled in UI (no "Nieuwe commissie" button or equivalent)

### API Preservation — 1 requirement

- [x] **API-01**: All current REST API edit/create/delete functionality remains fully available and unchanged

## Future Requirements (Deferred)

None identified for this milestone.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Team/Commissie deletion restriction | Only blocking creation; existing delete via API is fine |
| Team/Commissie edit restrictions in UI | Focus on create; existing teams can still be edited |
| Person creation restriction | Users need to add new people manually |
| Audit logging for API changes | Can be added later if needed |
| Role-based edit permissions | Current shared access model works; complexity not needed |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| PERSON-01 | Phase 116 | Complete |
| PERSON-02 | Phase 116 | Complete |
| PERSON-03 | Phase 116 | Complete |
| PERSON-04 | Phase 116 | Complete |
| FIELD-01 | Phase 118 | Complete |
| FIELD-02 | Phase 118 | Complete |
| ORG-01 | Phase 117 | Complete |
| ORG-02 | Phase 117 | Complete |
| API-01 | All Phases | Complete |
