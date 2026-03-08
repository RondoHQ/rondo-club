# Requirements: Rondo Club

**Defined:** 2026-03-08
**Core Value:** Club administrators can manage their members, teams, and club operations through a single integrated system

## v31.0 Requirements

Requirements for editable contact fields milestone. Each maps to roadmap phases.

### Data Model

- [x] **DATA-01**: Person records store contact info in 6 fixed ACF fields: email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2
- [x] **DATA-02**: Phone numbers (mobile + telephone) are normalized to E.164 format on save
- [x] **DATA-03**: Existing contact_info repeater data is migrated to fixed fields
- [x] **DATA-04**: Legacy contact_info repeater field and social link fields are removed after migration

### UI

- [x] **UI-01**: Person detail page displays 6 fixed contact fields with tel:/mailto: links
- [x] **UI-02**: User can edit all 6 contact fields on person detail page
- [x] **UI-03**: Email fields show a warning that changes affect the member's voetbal.nl login
- [x] **UI-04**: Phone numbers display in readable format but store in E.164

### Sync

- [x] **SYNC-01**: rondo-sync forward sync maps Sportlink fields 1:1 to new fixed Rondo Club fields
- [x] **SYNC-02**: Reverse sync change detection reads from fixed fields instead of contact_info repeater
- [ ] **SYNC-03**: Reverse sync cron is re-enabled on the rondo-sync server

## Future Requirements

None — this is a focused milestone.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Social media links (LinkedIn, Bluesky, etc.) | Dropped entirely — not synced, rarely used |
| Website/calendar contact fields | Dropped with social links — not in Sportlink |
| Real-time reverse sync trigger | Cron-based detection is more stable and already built |
| Phone number format auto-detection (non-NL) | Club is Dutch, +31 normalization sufficient |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DATA-01 | Phase 209 | Complete |
| DATA-02 | Phase 210 | Complete |
| DATA-03 | Phase 209 | Complete |
| DATA-04 | Phase 209 | Complete |
| UI-01 | Phase 210 | Complete |
| UI-02 | Phase 210 | Complete |
| UI-03 | Phase 210 | Complete |
| UI-04 | Phase 210 | Complete |
| SYNC-01 | Phase 211 | Complete |
| SYNC-02 | Phase 211 | Complete |
| SYNC-03 | Phase 211 | Pending |

**Coverage:**
- v31.0 requirements: 11 total
- Mapped to phases: 11
- Unmapped: 0

---
*Requirements defined: 2026-03-08*
*Last updated: 2026-03-08 after roadmap creation*
