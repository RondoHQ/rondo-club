# Requirements: Rondo Club

**Defined:** 2026-02-20
**Core Value:** Club administrators can manage their members, teams, and club operations through a single integrated system

## v29.0 Requirements

Requirements for v29.0 Made in Europe. Each maps to roadmap phases.

### Google Sync Removal

- [ ] **GSYNC-01**: Google Contacts sync classes removed (sync, export, import, connection, REST endpoints)
- [ ] **GSYNC-02**: Google Calendar sync classes removed (sync, connections, google-calendar-provider, REST endpoints)
- [ ] **GSYNC-03**: Google OAuth simplified to serve only Google Sheets (remove Contacts/Calendar scopes and callbacks)
- [ ] **GSYNC-04**: Settings UI cleaned up (Contacts and Calendar connection UI sections removed)
- [ ] **GSYNC-05**: Frontend hooks, API client methods, and pages for Contacts/Calendar removed
- [ ] **GSYNC-06**: CSV export available on People, VOG, and Contributie list pages as download alternative

### Gravatar Removal

- [ ] **GRAV-01**: Gravatar REST endpoint removed from backend
- [ ] **GRAV-02**: Gravatar API calls and hooks removed from frontend

### Lettermint Email

- [ ] **EMAIL-01**: Lettermint WordPress plugin installed and configured on production
- [ ] **EMAIL-02**: DNS records (DKIM, bounce CNAME, DMARC) configured for sending domain
- [ ] **EMAIL-03**: Invoice email with PDF attachment verified working through Lettermint
- [ ] **EMAIL-04**: Installment and reminder emails verified working through Lettermint
- [ ] **EMAIL-05**: VOG and notification emails verified working through Lettermint

## Future Requirements

### Club Admin Role

- **AUTH-01**: Club-admin role for settings access (deferred from pending todo — not part of "Made in Europe" theme)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Google Sheets export removal | Staying — useful export, keeping OAuth for Sheets only |
| Lettermint PHP SDK direct integration | WordPress plugin covers all needs; SDK requires PHP 8.2+ |
| Custom email templates in Lettermint | Theme does PHP-side template rendering, no Lettermint templates needed |
| Lettermint Broadcast (marketing) | Transactional only; no newsletter/marketing use case |
| Email log viewer in admin | Lettermint dashboard provides delivery monitoring |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| GSYNC-01 | Phase 198 | Pending |
| GSYNC-02 | Phase 198 | Pending |
| GSYNC-03 | Phase 199 | Pending |
| GSYNC-04 | Phase 199 | Pending |
| GSYNC-05 | Phase 199 | Pending |
| GSYNC-06 | Phase 200 | Pending |
| GRAV-01 | Phase 199 | Pending |
| GRAV-02 | Phase 199 | Pending |
| EMAIL-01 | Phase 201 | Pending |
| EMAIL-02 | Phase 201 | Pending |
| EMAIL-03 | Phase 202 | Pending |
| EMAIL-04 | Phase 202 | Pending |
| EMAIL-05 | Phase 202 | Pending |

**Coverage:**
- v29.0 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0

---
*Requirements defined: 2026-02-20*
*Last updated: 2026-02-20 — traceability mapped to phases 198-202*
