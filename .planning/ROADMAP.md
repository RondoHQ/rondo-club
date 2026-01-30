# Roadmap: Stadion v11.0 VOG Management

## Overview

This milestone adds VOG (Verklaring Omtrent Gedrag) compliance management for volunteers. Users will be able to identify volunteers needing VOG certification, send templated emails in bulk, and track the status of VOG requests. The system handles both new volunteers and renewals (VOG expires after 3 years).

## Milestones

- v1.0 through v10.0: shipped (see milestones/ archive)
- v11.0 VOG Management: Phases 119-122 (in progress)

## Phases

- [x] **Phase 119: Backend Foundation** - Email infrastructure, settings, and tracking data model
- [x] **Phase 120: VOG List Page** - Navigation and filtered volunteer list
- [ ] **Phase 121: Bulk Actions** - Multi-select, send emails, mark requested
- [ ] **Phase 122: Tracking & Polish** - Email status filtering and history display

## Phase Details

### Phase 119: Backend Foundation
**Goal**: Email sending infrastructure and configuration are ready for VOG workflows
**Depends on**: Nothing (first phase of milestone)
**Requirements**: SET-01, SET-02, SET-03, EMAIL-01, EMAIL-02, EMAIL-03, TRACK-01
**Success Criteria** (what must be TRUE):
  1. User can configure VOG email settings in Settings page (from address, templates)
  2. System can send emails via wp_mail() with configured from address
  3. New volunteer template supports {first_name} variable substitution
  4. Renewal template supports {first_name} and {previous_vog_date} variables
  5. VOG email sent date is stored per person (ACF field)
**Plans:** 2 plans

Plans:
- [x] 119-01-PLAN.md — PHP backend: VOG email service class and REST endpoints
- [x] 119-02-PLAN.md — Frontend: VOG settings tab in Settings page

### Phase 120: VOG List Page
**Goal**: Users can see which volunteers need VOG action
**Depends on**: Phase 119 (needs tracking field to exist)
**Requirements**: VOG-01, VOG-02, VOG-03
**Success Criteria** (what must be TRUE):
  1. User sees VOG section in sidebar navigation
  2. VOG list shows only volunteers with huidig-vrijwilliger=true AND (no datum-vog OR datum-vog 3+ years ago)
  3. List displays Name, KNVB ID, Email, Phone, and Datum VOG columns
**Plans:** 1 plan

Plans:
- [x] 120-01-PLAN.md — VOG list page with navigation, filtering, and badges

### Phase 121: Bulk Actions
**Goal**: Users can send VOG emails to multiple volunteers at once
**Depends on**: Phase 120 (needs list to select from)
**Requirements**: BULK-01, BULK-02, BULK-03, EMAIL-04
**Success Criteria** (what must be TRUE):
  1. User can select multiple people in VOG list via checkboxes
  2. User can send VOG email to all selected people with one action
  3. System automatically selects new volunteer vs renewal template based on datum-vog
  4. User can mark selected people as "VOG requested" (records current date)
**Plans**: TBD

Plans:
- [ ] 121-01: TBD

### Phase 122: Tracking & Polish
**Goal**: Users can track VOG email status and view history
**Depends on**: Phase 121 (needs emails to have been sent)
**Requirements**: TRACK-02, TRACK-03
**Success Criteria** (what must be TRUE):
  1. User can filter VOG list by email status (sent/not sent)
  2. User can see email history on person profile page
**Plans**: TBD

Plans:
- [ ] 122-01: TBD

## Progress

**Execution Order:** 119 -> 120 -> 121 -> 122

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 119. Backend Foundation | 2/2 | Complete | 2026-01-30 |
| 120. VOG List Page | 1/1 | Complete | 2026-01-30 |
| 121. Bulk Actions | 0/TBD | Not started | - |
| 122. Tracking & Polish | 0/TBD | Not started | - |

---
*Roadmap created: 2026-01-30*
*Requirements: 16 mapped, 0 orphaned*
