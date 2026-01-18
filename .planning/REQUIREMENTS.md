# Requirements: Caelis v6.0 Custom Fields

**Defined:** 2026-01-18
**Core Value:** Admin-defined custom fields for People and Organizations using ACF-native storage

## v1 Requirements

Requirements for v6.0 release. Each maps to roadmap phases.

### Field Management

- [x] **MGMT-01**: Admin can create custom field definition for People
- [x] **MGMT-02**: Admin can create custom field definition for Organizations
- [x] **MGMT-03**: Admin can edit existing field definition (label, description, options)
- [x] **MGMT-04**: Admin can delete/deactivate field definition (data preserved)
- [ ] **MGMT-05**: Admin can reorder fields via drag-and-drop
- [x] **MGMT-06**: Field key auto-generates from label (editable)
- [ ] **MGMT-07**: Admin can set validation options (required, unique)
- [ ] **MGMT-08**: Admin can set placeholder text for fields

### Field Types

- [ ] **TYPE-01**: Text field type (single line)
- [ ] **TYPE-02**: Textarea field type (multi-line)
- [ ] **TYPE-03**: Number field type (integer/decimal with min/max)
- [ ] **TYPE-04**: Email field type (with validation)
- [ ] **TYPE-05**: URL field type (with validation)
- [ ] **TYPE-06**: Date field type (date picker)
- [ ] **TYPE-07**: Select field type (single choice dropdown)
- [ ] **TYPE-08**: Checkbox field type (multiple choices)
- [ ] **TYPE-09**: True/False field type (boolean toggle)
- [ ] **TYPE-10**: Image field type (upload with preview)
- [ ] **TYPE-11**: File field type (upload with download link)
- [ ] **TYPE-12**: Link field type (URL + display text)
- [ ] **TYPE-13**: Color picker field type
- [ ] **TYPE-14**: Relationship field type (link to People/Organizations)

### Display

- [ ] **DISP-01**: Custom Fields section on Person detail view
- [ ] **DISP-02**: Custom Fields section on Organization detail view
- [ ] **DISP-03**: Inline editing of custom field values
- [ ] **DISP-04**: Type-appropriate rendering (date pickers, file previews, color swatches, etc.)
- [ ] **DISP-05**: Custom field columns in People list view
- [ ] **DISP-06**: Custom field columns in Organizations list view
- [ ] **DISP-07**: Type-appropriate column rendering (truncated text, icons, badges, etc.)

### Search

- [ ] **SRCH-01**: Text custom field values included in global search
- [ ] **SRCH-02**: Email custom field values included in global search
- [ ] **SRCH-03**: URL custom field values included in global search

### Settings UI

- [x] **SETT-01**: Custom Fields subtab in Settings
- [x] **SETT-02**: Toggle between People/Organizations field lists
- [x] **SETT-03**: Add Field form/modal with type selector
- [x] **SETT-04**: List view of existing fields with edit/delete actions
- [ ] **SETT-05**: Drag-and-drop reordering of fields
- [ ] **SETT-06**: "Show in list view" toggle per field
- [ ] **SETT-07**: Column order configuration for list view fields

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced Field Types

- **TYPE-15**: Repeater field type (nested multi-row data)
- **TYPE-16**: Gallery field type (multiple images)
- **TYPE-17**: WYSIWYG field type (rich text editor)

### Extended Relationships

- **REL-01**: Relationship to any post type (not just People/Organizations)
- **REL-02**: Bidirectional relationship tracking

### Import/Export

- **IMPEXP-01**: Export custom field definitions to JSON
- **IMPEXP-02**: Import custom field definitions from JSON

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Per-user custom fields | Complexity; global fields cover primary use case |
| Per-workspace custom fields | Complexity; global fields sufficient for v1 |
| Custom tables for field storage | Rule 0: Use WordPress/ACF native storage only |
| Conditional logic between fields | ACF supports this but adds significant UI complexity |
| Calculated/formula fields | High complexity, unclear requirements |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| MGMT-01 | Phase 87 | Complete |
| MGMT-02 | Phase 87 | Complete |
| MGMT-03 | Phase 87 | Complete |
| MGMT-04 | Phase 87 | Complete |
| MGMT-05 | Phase 94 | Pending |
| MGMT-06 | Phase 87 | Complete |
| MGMT-07 | Phase 94 | Pending |
| MGMT-08 | Phase 94 | Pending |
| TYPE-01 | Phase 89 | Pending |
| TYPE-02 | Phase 89 | Pending |
| TYPE-03 | Phase 89 | Pending |
| TYPE-04 | Phase 89 | Pending |
| TYPE-05 | Phase 89 | Pending |
| TYPE-06 | Phase 89 | Pending |
| TYPE-07 | Phase 89 | Pending |
| TYPE-08 | Phase 89 | Pending |
| TYPE-09 | Phase 89 | Pending |
| TYPE-10 | Phase 90 | Pending |
| TYPE-11 | Phase 90 | Pending |
| TYPE-12 | Phase 90 | Pending |
| TYPE-13 | Phase 90 | Pending |
| TYPE-14 | Phase 90 | Pending |
| DISP-01 | Phase 91 | Pending |
| DISP-02 | Phase 91 | Pending |
| DISP-03 | Phase 91 | Pending |
| DISP-04 | Phase 91 | Pending |
| DISP-05 | Phase 92 | Pending |
| DISP-06 | Phase 92 | Pending |
| DISP-07 | Phase 92 | Pending |
| SRCH-01 | Phase 93 | Pending |
| SRCH-02 | Phase 93 | Pending |
| SRCH-03 | Phase 93 | Pending |
| SETT-01 | Phase 88 | Complete |
| SETT-02 | Phase 88 | Complete |
| SETT-03 | Phase 88 | Complete |
| SETT-04 | Phase 88 | Complete |
| SETT-05 | Phase 94 | Pending |
| SETT-06 | Phase 92 | Pending |
| SETT-07 | Phase 92 | Pending |

**Coverage:**
- v1 requirements: 29 total
- Mapped to phases: 29
- Unmapped: 0

---
*Requirements defined: 2026-01-18*
*Last updated: 2026-01-18 after roadmap creation*
