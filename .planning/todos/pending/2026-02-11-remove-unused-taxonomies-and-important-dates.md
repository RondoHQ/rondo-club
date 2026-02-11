# Remove unused taxonomies and important_dates

**Created:** 2026-02-11
**Source:** Phase 170 discussion
**Priority:** Medium

## Description

Remove the following features from the entire codebase (PHP, React, REST API, docs):

1. **Taxonomies to remove:**
   - `person_label` — person tags
   - `team_label` — team tags
   - `date_type` — date categorization

2. **CPT to remove:**
   - `important_date` — recurring dates linked to people, including its reminders system

## Scope

- PHP: taxonomy registration, post type registration, query filters, REST exposure
- React: any UI referencing labels, date types, important dates pages/components
- ACF: field groups referencing these
- Docs: developer documentation referencing these features
- Related: reminder/digest system tied to important_dates

## Notes

- This simplifies the data model significantly
- Should be done as its own milestone or quick task batch, not mixed into v24.0 Demo Data
