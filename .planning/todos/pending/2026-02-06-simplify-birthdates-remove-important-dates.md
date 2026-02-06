---
created: 2026-02-06T09:12
title: Simplify birthdates - move to person field, remove Datums
area: data-model
files:
  - includes/class-post-types.php
  - acf-json/group_person_fields.json
  - src/pages/DatesList.jsx
  - src/components/Dashboard/UpcomingDates.jsx
  - src/App.jsx
  - includes/class-rest-api.php
---

## Problem

The "Important Dates" system (Datums) was designed to track many types of dates linked to people, but in practice only birthdays are used. This creates unnecessary complexity:

- Separate `important_date` CPT with relationship to person
- Dedicated "Datums" menu item and page
- Complex queries to find upcoming birthdays
- Extra maintenance burden

Birthdates are a fundamental person attribute, not a separate entity.

## Solution

1. **Add birthdate ACF field to person**
   - Date field on person post type
   - Migrate existing important_date birthday records to this field

2. **Update upcoming dates widget**
   - Query person post meta for birthdates instead of important_date CPT
   - Calculate upcoming birthdays from birthdate field (month/day comparison)

3. **Remove Important Dates infrastructure**
   - Remove "Datums" menu item from navigation
   - Remove DatesList page
   - Optionally deprecate `important_date` CPT (or keep for backwards compatibility)
   - Remove ImportantDateModal component

4. **Migration path**
   - One-time script to copy birthdates from important_date posts to person meta
   - Verify data before removing old structure
