# Roadmap: v10.0 Read-Only UI for Sportlink Data

## Overview

This milestone restricts UI editing capabilities for Sportlink-managed data while preserving full REST API functionality for automation and sync. The work covers person edit restrictions, organization creation restrictions, and custom field edit control across three focused phases.

## Milestones

- v1.0 through v9.0 - shipped (see PROJECT.md for history)
- **v10.0 Read-Only UI for Sportlink Data** - Phases 116-118 (in progress)

## Phases

- [ ] **Phase 116: Person Edit Restrictions** - Remove delete, address, and work history editing from PersonDetail
- [ ] **Phase 117: Organization Creation Restrictions** - Disable team and commissie creation in UI
- [ ] **Phase 118: Custom Field Edit Control** - Add editable_in_ui setting to custom fields

## Phase Details

### Phase 116: Person Edit Restrictions
**Goal**: Users cannot delete, add addresses, or edit work history for persons in the UI
**Depends on**: Nothing (first phase of v10.0)
**Requirements**: PERSON-01, PERSON-02, PERSON-03, PERSON-04
**Constraint**: API-01 (all REST API functionality remains unchanged)
**Success Criteria** (what must be TRUE):
  1. PersonDetail page has no "Verwijderen" button visible anywhere
  2. PersonDetail page has no "Voeg adres toe" button visible in address section
  3. Work history section has no "Functie toevoegen" button
  4. Work history items have no edit button and clicking them does not open edit mode
  5. REST API DELETE /wp/v2/people/{id} still works for automation
**Plans**: TBD

Plans:
- [ ] 116-01: TBD

### Phase 117: Organization Creation Restrictions
**Goal**: Users cannot create new teams or commissies in the UI
**Depends on**: Phase 116
**Requirements**: ORG-01, ORG-02
**Constraint**: API-01 (all REST API functionality remains unchanged)
**Success Criteria** (what must be TRUE):
  1. Teams list page has no "Nieuw team" button or creation action
  2. Commissies list page has no "Nieuwe commissie" button or creation action
  3. No other UI path allows creating teams or commissies
  4. REST API POST /wp/v2/teams still works for automation
  5. REST API POST /wp/v2/commissies still works for automation
**Plans**: TBD

Plans:
- [ ] 117-01: TBD

### Phase 118: Custom Field Edit Control
**Goal**: Custom fields can be marked as non-editable in UI while remaining API-accessible
**Depends on**: Phase 117
**Requirements**: FIELD-01, FIELD-02
**Constraint**: API-01 (all REST API functionality remains unchanged)
**Success Criteria** (what must be TRUE):
  1. Custom field settings UI shows "Bewerkbaar in UI" checkbox for each field
  2. Fields with editable_in_ui=true show normal edit button on person/team detail
  3. Fields with editable_in_ui=false display their value but show no edit button
  4. Fields with editable_in_ui=false can still be updated via REST API
  5. Default value for new fields is editable_in_ui=true (backward compatible)
**Plans**: TBD

Plans:
- [ ] 118-01: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 116. Person Edit Restrictions | v10.0 | 0/TBD | Not started | - |
| 117. Organization Creation Restrictions | v10.0 | 0/TBD | Not started | - |
| 118. Custom Field Edit Control | v10.0 | 0/TBD | Not started | - |
