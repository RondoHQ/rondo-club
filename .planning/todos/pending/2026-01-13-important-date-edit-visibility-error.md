---
created: 2026-01-13T20:58
title: Fix visibility required error when editing important date
area: api
files:
  - src/pages/People/PersonDetail.jsx
  - includes/class-post-types.php
---

## Problem

When editing an existing Important Date, the REST API returns a 400 error:

```json
{
    "code": "rest_invalid_param",
    "message": "Invalid parameter(s): acf",
    "data": {
        "status": 400,
        "params": {
            "acf": "_visibility is a required property of acf."
        },
        "details": {
            "acf": {
                "code": "rest_property_required",
                "message": "_visibility is a required property of acf.",
                "data": null
            }
        }
    }
}
```

The `_visibility` field is marked as required in the ACF field group for important_date posts, but the frontend edit form is not including it in the update payload.

## Solution

TBD - Either:
1. Include `_visibility` in the important date update payload (copy from existing value or default to 'private')
2. Make `_visibility` field not required in ACF schema for important_date CPT
3. Set a default value for `_visibility` in the REST API handler
