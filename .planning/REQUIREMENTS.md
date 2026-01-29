# Requirements: Stadion v9.0 People List Performance & Customization

**Defined:** 2026-01-29
**Core Value:** Fast, customizable People list that scales to 1400+ contacts with server-side filtering and per-user column preferences.

## v9.0 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### Data Layer (Backend)

- [x] **DATA-01**: Server returns paginated people (100 per page by default)
- [x] **DATA-02**: Server filters people by label taxonomy (multiple labels, OR logic)
- [x] **DATA-03**: Server filters people by ownership (mine/shared/all)
- [x] **DATA-04**: Server filters people by modified date (within last N days)
- [x] **DATA-05**: Server filters people by birth year (range: from-to)
- [x] **DATA-06**: Server sorts people by first_name (asc/desc)
- [x] **DATA-07**: Server sorts people by last_name (asc/desc)
- [x] **DATA-08**: Server sorts people by modified date (asc/desc)
- [x] **DATA-09**: Server sorts people by custom ACF fields (text, number, date types)
- [x] **DATA-10**: Custom endpoint uses $wpdb JOIN to fetch posts + meta in single query
- [x] **DATA-11**: Birthdate is denormalized to person post_meta (_birthdate) for fast filtering
- [x] **DATA-12**: Birthdate syncs when birthday important_date is created/updated/deleted
- [x] **DATA-13**: Access control is preserved in custom $wpdb queries (unapproved users see nothing)
- [x] **DATA-14**: All filter parameters are validated/escaped to prevent SQL injection

### Pagination (Frontend)

- [x] **PAGE-01**: PeopleList displays paginated results (100 per page)
- [x] **PAGE-02**: User can navigate between pages (prev/next/page numbers)
- [x] **PAGE-03**: Current page and total pages are displayed
- [x] **PAGE-04**: Filter changes reset to page 1
- [x] **PAGE-05**: Loading indicator shows while fetching page
- [x] **PAGE-06**: Empty state when no results match filters

### Column Customization

- [ ] **COL-01**: User can show/hide columns on PeopleList
- [ ] **COL-02**: User can reorder columns via drag-and-drop
- [x] **COL-03**: Column preferences persist per user (stored in user_meta)
- [ ] **COL-04**: Available columns include: name, team, labels, modified, all active custom fields
- [ ] **COL-05**: Column width preferences persist per user
- [ ] **COL-06**: Settings modal provides column customization UI
- [ ] **COL-07**: "Tonen als kolom in lijstweergave" removed from custom field settings (replaced by per-user selection)

## Future Requirements

Deferred to future release. Tracked but not in current roadmap.

### Search & Filters

- **SRCH-01**: Full-text search across name, email, phone, notes
- **SRCH-02**: Quick filter presets (frequently used filter combinations)
- **SRCH-03**: Saved filters that persist across sessions

### Advanced UI

- **ADV-01**: Virtual scrolling for 5000+ record lists
- **ADV-02**: Infinite scroll option (toggle between pagination and infinite)
- **ADV-03**: URL-based filter state (shareable links to filtered views)
- **ADV-04**: Multi-column sorting (sort by A, then by B)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Infinite scroll | Chose traditional pagination for 100/page (research: better for goal-oriented tasks) |
| Real-time updates | Complex, not needed for current use case |
| Export filtered results | Separate feature, can add later |
| Bulk edit from filtered view | Existing bulk edit works, no change needed |
| Teams/Dates list optimization | Focus on People first, extend pattern later if needed |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DATA-01 | Phase 111 | ✓ Complete |
| DATA-02 | Phase 111 | ✓ Complete |
| DATA-03 | Phase 111 | ✓ Complete |
| DATA-04 | Phase 111 | ✓ Complete |
| DATA-05 | Phase 112 | ✓ Complete |
| DATA-06 | Phase 111 | ✓ Complete |
| DATA-07 | Phase 111 | ✓ Complete |
| DATA-08 | Phase 111 | ✓ Complete |
| DATA-09 | Phase 113 | ✓ Complete |
| DATA-10 | Phase 111 | ✓ Complete |
| DATA-11 | Phase 112 | ✓ Complete |
| DATA-12 | Phase 112 | ✓ Complete |
| DATA-13 | Phase 111 | ✓ Complete |
| DATA-14 | Phase 111 | ✓ Complete |
| PAGE-01 | Phase 113 | ✓ Complete |
| PAGE-02 | Phase 113 | ✓ Complete |
| PAGE-03 | Phase 113 | ✓ Complete |
| PAGE-04 | Phase 113 | ✓ Complete |
| PAGE-05 | Phase 113 | ✓ Complete |
| PAGE-06 | Phase 113 | ✓ Complete |
| COL-01 | Phase 115 | Pending |
| COL-02 | Phase 115 | Pending |
| COL-03 | Phase 114 | ✓ Complete |
| COL-04 | Phase 115 | Pending |
| COL-05 | Phase 115 | Pending |
| COL-06 | Phase 115 | Pending |
| COL-07 | Phase 115 | Pending |

**Coverage:**
- v9.0 requirements: 27 total
- Mapped to phases: 27
- Unmapped: 0

---
*Requirements defined: 2026-01-29*
*Last updated: 2026-01-29 after Phase 114 completion*
