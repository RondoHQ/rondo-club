# Requirements: Caelis v6.0 Custom Fields

**Defined:** 2026-01-18
**Core Value:** Admin-defined custom fields for People and Organizations using ACF-native storage

## v1 Requirements

Requirements for v6.0 release. Each maps to roadmap phases.

### Field Management

- [ ] **MGMT-01**: Admin can create custom field definition for People
- [ ] **MGMT-02**: Admin can create custom field definition for Organizations
- [ ] **MGMT-03**: Admin can edit existing field definition (label, description, options)
- [ ] **MGMT-04**: Admin can delete/deactivate field definition (data preserved)
- [ ] **MGMT-05**: Admin can reorder fields via drag-and-drop
- [ ] **MGMT-06**: Field key auto-generates from label (editable)
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

- [ ] **SETT-01**: Custom Fields subtab in Settings
- [ ] **SETT-02**: Toggle between People/Organizations field lists
- [ ] **SETT-03**: Add Field form/modal with type selector
- [ ] **SETT-04**: List view of existing fields with edit/delete actions
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
| MGMT-01 | TBD | Pending |
| MGMT-02 | TBD | Pending |
| MGMT-03 | TBD | Pending |
| MGMT-04 | TBD | Pending |
| MGMT-05 | TBD | Pending |
| MGMT-06 | TBD | Pending |
| MGMT-07 | TBD | Pending |
| MGMT-08 | TBD | Pending |
| TYPE-01 | TBD | Pending |
| TYPE-02 | TBD | Pending |
| TYPE-03 | TBD | Pending |
| TYPE-04 | TBD | Pending |
| TYPE-05 | TBD | Pending |
| TYPE-06 | TBD | Pending |
| TYPE-07 | TBD | Pending |
| TYPE-08 | TBD | Pending |
| TYPE-09 | TBD | Pending |
| TYPE-10 | TBD | Pending |
| TYPE-11 | TBD | Pending |
| TYPE-12 | TBD | Pending |
| TYPE-13 | TBD | Pending |
| TYPE-14 | TBD | Pending |
| DISP-01 | TBD | Pending |
| DISP-02 | TBD | Pending |
| DISP-03 | TBD | Pending |
| DISP-04 | TBD | Pending |
| DISP-05 | TBD | Pending |
| DISP-06 | TBD | Pending |
| DISP-07 | TBD | Pending |
| SRCH-01 | TBD | Pending |
| SRCH-02 | TBD | Pending |
| SRCH-03 | TBD | Pending |
| SETT-01 | TBD | Pending |
| SETT-02 | TBD | Pending |
| SETT-03 | TBD | Pending |
| SETT-04 | TBD | Pending |
| SETT-05 | TBD | Pending |
| SETT-06 | TBD | Pending |
| SETT-07 | TBD | Pending |

**Coverage:**
- v1 requirements: 29 total
- Mapped to phases: 0
- Unmapped: 29

---
*Requirements defined: 2026-01-18*
*Last updated: 2026-01-18 after initial definition*
