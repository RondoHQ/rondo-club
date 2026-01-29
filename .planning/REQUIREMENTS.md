# Requirements: Stadion v9.0 People List Performance & Customization

**Defined:** 2026-01-29
**Core Value:** Fast, customizable People list that scales to 1400+ contacts with server-side filtering and per-user column preferences.

## v9.0 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### Data Layer (Backend)

- [ ] **DATA-01**: Server returns paginated people (100 per page by default)
- [ ] **DATA-02**: Server filters people by label taxonomy (multiple labels, OR logic)
- [ ] **DATA-03**: Server filters people by ownership (mine/shared/all)
- [ ] **DATA-04**: Server filters people by modified date (within last N days)
- [ ] **DATA-05**: Server filters people by birth year (exact match)
- [ ] **DATA-06**: Server sorts people by first_name (asc/desc)
- [ ] **DATA-07**: Server sorts people by last_name (asc/desc)
- [ ] **DATA-08**: Server sorts people by modified date (asc/desc)
- [ ] **DATA-09**: Server sorts people by custom ACF fields (text, number, date types)
- [ ] **DATA-10**: Custom endpoint uses $wpdb JOIN to fetch posts + meta in single query
- [ ] **DATA-11**: Birthdate is denormalized to person post_meta (_birthdate) for fast filtering
- [ ] **DATA-12**: Birthdate syncs when birthday important_date is created/updated/deleted
- [ ] **DATA-13**: Access control is preserved in custom $wpdb queries (unapproved users see nothing)
- [ ] **DATA-14**: All filter parameters are validated/escaped to prevent SQL injection

### Pagination (Frontend)

- [ ] **PAGE-01**: PeopleList displays paginated results (100 per page)
- [ ] **PAGE-02**: User can navigate between pages (prev/next/page numbers)
- [ ] **PAGE-03**: Current page and total pages are displayed
- [ ] **PAGE-04**: Filter changes reset to page 1
- [ ] **PAGE-05**: Loading indicator shows while fetching page
- [ ] **PAGE-06**: Empty state when no results match filters

### Column Customization

- [ ] **COL-01**: User can show/hide columns on PeopleList
- [ ] **COL-02**: User can reorder columns via drag-and-drop
- [ ] **COL-03**: Column preferences persist per user (stored in user_meta)
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
| DATA-01 | Phase 111 | Pending |
| DATA-02 | Phase 111 | Pending |
| DATA-03 | Phase 111 | Pending |
| DATA-04 | Phase 111 | Pending |
| DATA-05 | Phase 112 | Pending |
| DATA-06 | Phase 111 | Pending |
| DATA-07 | Phase 111 | Pending |
| DATA-08 | Phase 111 | Pending |
| DATA-09 | Phase 113 | Pending |
| DATA-10 | Phase 111 | Pending |
| DATA-11 | Phase 112 | Pending |
| DATA-12 | Phase 112 | Pending |
| DATA-13 | Phase 111 | Pending |
| DATA-14 | Phase 111 | Pending |
| PAGE-01 | Phase 113 | Pending |
| PAGE-02 | Phase 113 | Pending |
| PAGE-03 | Phase 113 | Pending |
| PAGE-04 | Phase 113 | Pending |
| PAGE-05 | Phase 113 | Pending |
| PAGE-06 | Phase 113 | Pending |
| COL-01 | Phase 115 | Pending |
| COL-02 | Phase 115 | Pending |
| COL-03 | Phase 114 | Pending |
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
*Last updated: 2026-01-29 after roadmap creation*
