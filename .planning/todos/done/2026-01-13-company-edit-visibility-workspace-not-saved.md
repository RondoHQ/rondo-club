---
created: 2026-01-13T21:23
title: Fix company visibility and workspace not saving on edit
area: api
files:
  - src/pages/Organizations/OrganizationDetail.jsx
  - src/hooks/useCompanies.js
  - includes/class-rest-api.php
---

## Problem

When editing an existing company/organization, the visibility and workspace fields are not being saved correctly. This is similar to the issue with important dates where the `_visibility` field is required but not included in the update payload.

The organization edit form likely:
1. Does not send the `_visibility` field in the update payload
2. Does not send the `_workspace` field in the update payload
3. Or the backend is not processing these fields correctly during update

## Solution

TBD - Investigate and fix:
1. Check if OrganizationDetail.jsx includes visibility and workspace in the update mutation
2. Verify the useCompanies hook's update method passes all required fields
3. Ensure the REST API handler properly processes these ACF fields on update
