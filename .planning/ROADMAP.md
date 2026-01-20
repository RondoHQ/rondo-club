# Roadmap: Caelis v6.0 Custom Fields

## Overview

Transform Caelis into a flexible CRM with admin-defined custom fields for People and Organizations. Using ACF-native storage (field groups and fields as CPTs), this milestone delivers 14 field types with full CRUD management, detail view integration, list view columns, and search capabilities.

## Phases

**Phase Numbering:**
- Integer phases (87, 88, 89...): Planned milestone work
- Decimal phases (87.1, 87.2): Urgent insertions (marked with INSERTED)

- [x] **Phase 87: ACF Foundation** - PHP infrastructure for programmatic field group management
- [x] **Phase 88: Settings UI** - Custom Fields subtab with add/edit/delete interface
- [x] **Phase 89: Basic Field Types** - Text, Textarea, Number, Email, URL, Date, Select, Checkbox, True/False
- [x] **Phase 90: Extended Field Types** - Image, File, Link, Color, Relationship
- [x] **Phase 91: Detail View Integration** - Custom Fields section on Person/Organization detail pages
- [x] **Phase 92: List View Integration** - Custom field columns with column configuration
- [x] **Phase 93: Search Integration** - Include custom fields in global search
- [x] **Phase 94: Polish** - Drag-and-drop reorder, validation, placeholders

## Phase Details

### Phase 87: ACF Foundation
**Goal**: Establish PHP infrastructure for managing custom field definitions programmatically via ACF
**Depends on**: Nothing (first phase)
**Requirements**: MGMT-01, MGMT-02, MGMT-03, MGMT-04, MGMT-06
**Success Criteria** (what must be TRUE):
  1. PHP class can create a custom field definition for People that persists in database
  2. PHP class can create a custom field definition for Organizations that persists in database
  3. PHP class can update an existing field definition (label, description, options)
  4. PHP class can deactivate a field definition without losing stored data
  5. Field key auto-generates from label and is stored correctly
**Plans**: 2 plans

Plans:
- [x] 87-01-PLAN.md - Create CustomFields Manager class with CRUD operations
- [x] 87-02-PLAN.md - Create REST API endpoints for custom field management

### Phase 88: Settings UI
**Goal**: Admin can manage custom field definitions through a Settings subtab
**Depends on**: Phase 87
**Requirements**: SETT-01, SETT-02, SETT-03, SETT-04
**Success Criteria** (what must be TRUE):
  1. Custom Fields subtab appears in Settings
  2. Admin can toggle between People and Organizations field lists
  3. Admin can add a new field via form/modal with type selector
  4. Admin can see list of existing fields with edit/delete actions
**Plans**: 2 plans

Plans:
- [x] 88-01-PLAN.md - Custom fields page with API client, tab navigation, and field list
- [x] 88-02-PLAN.md - Add/edit slide-out panel and delete confirmation dialog

### Phase 89: Basic Field Types
**Goal**: Support 9 fundamental field types for text, numbers, choices, and dates
**Depends on**: Phase 88
**Requirements**: TYPE-01, TYPE-02, TYPE-03, TYPE-04, TYPE-05, TYPE-06, TYPE-07, TYPE-08, TYPE-09
**Success Criteria** (what must be TRUE):
  1. Text field captures single-line input
  2. Textarea field captures multi-line input
  3. Number field accepts integers/decimals with min/max validation
  4. Email and URL fields validate their formats
  5. Date field provides date picker interface
  6. Select field offers single-choice dropdown
  7. Checkbox field allows multiple selections
  8. True/False field renders as boolean toggle
**Plans**: 2 plans

Plans:
- [x] 89-01-PLAN.md - Extend backend for type-specific options (min/max, choices, formats)
- [x] 89-02-PLAN.md - Add type-specific configuration UI to FieldFormPanel

### Phase 90: Extended Field Types
**Goal**: Support 5 advanced field types for media, links, colors, and relationships
**Depends on**: Phase 89
**Requirements**: TYPE-10, TYPE-11, TYPE-12, TYPE-13, TYPE-14
**Success Criteria** (what must be TRUE):
  1. Image field allows upload with preview display
  2. File field allows upload with download link display
  3. Link field captures URL and display text pair
  4. Color field provides color picker interface
  5. Relationship field links to People or Organizations with search
**Plans**: 2 plans

Plans:
- [x] 90-01-PLAN.md - Extend backend for extended type options (image, file, link, color, relationship)
- [x] 90-02-PLAN.md - Add extended type configuration UI to FieldFormPanel

### Phase 91: Detail View Integration
**Goal**: Custom fields appear in dedicated section on Person and Organization detail pages
**Depends on**: Phase 90
**Requirements**: DISP-01, DISP-02, DISP-03, DISP-04
**Success Criteria** (what must be TRUE):
  1. Person detail view shows "Custom Fields" section with all defined fields
  2. Organization detail view shows "Custom Fields" section with all defined fields
  3. Field values can be edited via modal (Caelis standard pattern)
  4. Each field type renders appropriately (date pickers, file previews, color swatches, etc.)
**Plans**: 2 plans

Plans:
- [x] 91-01-PLAN.md - Expose field metadata to non-admins via REST API
- [x] 91-02-PLAN.md - CustomFieldsSection component and detail page integration

### Phase 92: List View Integration
**Goal**: Custom fields can appear as columns in People and Organizations list views
**Depends on**: Phase 91
**Requirements**: DISP-05, DISP-06, DISP-07, SETT-06, SETT-07
**Success Criteria** (what must be TRUE):
  1. Custom field columns appear in People list view when enabled
  2. Custom field columns appear in Organizations list view when enabled
  3. Column values render appropriately (truncated text, icons, badges)
  4. Admin can toggle "Show in list view" per field in Settings
  5. Admin can configure column order for list view fields
**Plans**: 2 plans

Plans:
- [x] 92-01-PLAN.md - Backend support for show_in_list_view and list_view_order field properties
- [x] 92-02-PLAN.md - FieldFormPanel settings, CustomFieldColumn component, and list view integration

### Phase 93: Search Integration
**Goal**: Custom field values are included in global search results
**Depends on**: Phase 91
**Requirements**: SRCH-01, SRCH-02, SRCH-03
**Success Criteria** (what must be TRUE):
  1. Searching for text in a Text custom field returns matching People/Organizations
  2. Searching for an email in an Email custom field returns matching records
  3. Searching for a URL in a URL custom field returns matching records
**Plans**: 1 plan

Plans:
- [x] 93-01-PLAN.md - Add custom field search to global_search() with helper methods and scoring

### Phase 94: Polish
**Goal**: Complete field management UX with drag-and-drop ordering and validation options
**Depends on**: Phase 92
**Requirements**: MGMT-05, MGMT-07, MGMT-08, SETT-05
**Success Criteria** (what must be TRUE):
  1. Admin can reorder fields via drag-and-drop in Settings
  2. Admin can mark fields as required (validation enforced on save)
  3. Admin can mark fields as unique (prevents duplicate values)
  4. Admin can set placeholder text that appears in empty fields
**Plans**: 2 plans

Plans:
- [x] 94-01-PLAN.md - Backend: menu_order, reorder endpoint, unique validation
- [x] 94-02-PLAN.md - Frontend: drag-and-drop, required/unique toggles, placeholder expansion

## Progress

**Execution Order:**
Phases execute in numeric order: 87 -> 88 -> 89 -> 90 -> 91 -> 92 -> 93 -> 94

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 87. ACF Foundation | 2/2 | Complete | 2026-01-18 |
| 88. Settings UI | 2/2 | Complete | 2026-01-18 |
| 89. Basic Field Types | 2/2 | Complete | 2026-01-18 |
| 90. Extended Field Types | 2/2 | Complete | 2026-01-18 |
| 91. Detail View Integration | 2/2 | Complete | 2026-01-19 |
| 92. List View Integration | 2/2 | Complete | 2026-01-20 |
| 93. Search Integration | 1/1 | Complete | 2026-01-20 |
| 94. Polish | 2/2 | Complete | 2026-01-20 |
