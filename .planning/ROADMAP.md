# Roadmap: v31.0 Editable Contact Fields

## Overview

Replace the flexible-but-messy ACF contact_info repeater with 6 fixed contact fields (email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2), migrate existing data, build editable UI with phone normalization and email warnings, then update rondo-sync to read/write the new fields and re-enable reverse sync cron.

## Phases

- [ ] **Phase 209: Data Model Migration** - Register 6 fixed ACF fields, migrate repeater data, remove legacy fields
- [ ] **Phase 210: Backend Normalization & UI** - E.164 phone normalization on save, editable contact fields in person detail with email warning
- [ ] **Phase 211: Sync Update** - Update rondo-sync forward + reverse sync for new fields, re-enable reverse sync cron

## Phase Details

### Phase 209: Data Model Migration
**Goal**: Person records store contact info in 6 fixed ACF fields with all existing data migrated
**Depends on**: Nothing (first phase)
**Requirements**: DATA-01, DATA-03, DATA-04
**Success Criteria** (what must be TRUE):
  1. Every person record has 6 fixed contact fields (email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2) available in ACF
  2. All existing contact_info repeater data has been migrated to the correct fixed fields with no data loss
  3. The legacy contact_info repeater field group and social link fields no longer appear in the system
  4. REST API responses for person records return the new fixed fields instead of the old repeater structure
**Plans**: TBD

Plans:
- [ ] 209-01: TBD

### Phase 210: Backend Normalization & UI
**Goal**: Users can view and edit all 6 contact fields on person detail with phone normalization and email change warnings
**Depends on**: Phase 209
**Requirements**: DATA-02, UI-01, UI-02, UI-03, UI-04
**Success Criteria** (what must be TRUE):
  1. Person detail page displays all 6 contact fields with clickable tel: and mailto: links
  2. User can edit all 6 contact fields inline on the person detail page and save successfully
  3. When editing an email field, a warning is displayed that changes affect the member's voetbal.nl login
  4. Phone numbers entered in any Dutch format (06-12345678, 0612345678, etc.) are normalized to E.164 (+31612345678) on save but display in readable format
**Plans**: TBD

Plans:
- [ ] 210-01: TBD

### Phase 211: Sync Update
**Goal**: rondo-sync reads and writes the new fixed fields for both forward and reverse sync, with reverse sync cron active
**Depends on**: Phase 210
**Requirements**: SYNC-01, SYNC-02, SYNC-03
**Success Criteria** (what must be TRUE):
  1. Forward sync from Sportlink maps email, email2, mobile, phone fields 1:1 to the new Rondo Club fixed fields
  2. Reverse sync change detection compares current fixed field values against Sportlink data using hash comparison
  3. Reverse sync cron job is running on the rondo-sync server on its scheduled interval
**Plans**: TBD

Plans:
- [ ] 211-01: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 209 -> 210 -> 211

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 209. Data Model Migration | 0/? | Not started | - |
| 210. Backend Normalization & UI | 0/? | Not started | - |
| 211. Sync Update | 0/? | Not started | - |
