# Summary: 15-01 Backend REST Endpoint Extension

## Overview
Extended the bulk-update REST endpoint (`/prm/v1/people/bulk-update`) to support organization assignment and label management operations, enabling backend support for Phase 15's extended bulk actions.

## Tasks Completed

### Task 1: Extend bulk-update endpoint validation
- Updated `register_routes()` validation callback to accept new parameters:
  - `organization_id`: Integer (company post ID) or null (to clear current organization)
  - `labels_add`: Array of person_label term IDs to add
  - `labels_remove`: Array of person_label term IDs to remove
- Added validation logic:
  - Organization ID validated as existing published 'company' post when not null
  - Label arrays validated as arrays of numeric IDs
  - Extended "at least one update required" check to include new fields
- Commit: `f13653d`

### Task 2: Implement organization and label updates
- Added organization assignment handling in `bulk_update_people()`:
  - Gets current `work_history` ACF field
  - When `organization_id` is null: Sets `is_current=false` on all work history entries
  - When `organization_id` provided: Sets `is_current=true` only on matching company entry
  - Creates new work history entry if company not already in history
- Added label management:
  - `labels_add`: Uses `wp_set_object_terms()` with append=true to add terms without replacing existing
  - `labels_remove`: Uses `wp_remove_object_terms()` to remove specified terms
- Updated method docblock to document all supported update types
- Commit: `752461f`

## Files Modified
- `includes/class-rest-people.php` - Extended validation and implementation

## Verification
- [x] PHP syntax check passes for class-rest-people.php
- [x] All includes/*.php files pass syntax check
- [x] Existing bulk visibility and workspace functionality preserved (no code changes to those sections)

## Deviations
None. Implementation followed plan exactly.

## API Usage Examples

### Assign organization to people
```json
POST /wp-json/prm/v1/people/bulk-update
{
  "ids": [123, 456],
  "updates": {
    "organization_id": 789
  }
}
```

### Clear organization from people
```json
POST /wp-json/prm/v1/people/bulk-update
{
  "ids": [123, 456],
  "updates": {
    "organization_id": null
  }
}
```

### Add labels to people
```json
POST /wp-json/prm/v1/people/bulk-update
{
  "ids": [123, 456],
  "updates": {
    "labels_add": [1, 2, 3]
  }
}
```

### Remove labels from people
```json
POST /wp-json/prm/v1/people/bulk-update
{
  "ids": [123, 456],
  "updates": {
    "labels_remove": [1]
  }
}
```

### Combined update
```json
POST /wp-json/prm/v1/people/bulk-update
{
  "ids": [123, 456],
  "updates": {
    "organization_id": 789,
    "labels_add": [1, 2],
    "labels_remove": [3],
    "visibility": "workspace"
  }
}
```
