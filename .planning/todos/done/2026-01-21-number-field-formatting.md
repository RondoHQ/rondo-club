---
created: 2026-01-21T12:45
title: Add number format option for custom fields
area: ui
files:
  - src/pages/Settings/CustomFields.jsx
  - src/components/CustomFieldColumn.jsx
  - src/components/CustomFieldsSection.jsx
  - includes/class-custom-fields.php
---

## Problem

Custom fields of type "number" display raw numeric values without formatting. Large numbers like 1000000 are hard to read compared to "1,000,000" or "1M".

Users want a number format option so thousands and millions get displayed nicely in list views and detail views.

## Solution

Add a "number_format" option to number-type custom fields with options like:
- Plain (no formatting)
- Thousands separator (1,000,000)
- Abbreviated (1M, 1K)
- Currency prefix/suffix options

Update CustomFieldColumn and CustomFieldsSection to apply formatting when rendering.
