---
created: 2026-02-08T15:26
title: Remove how_we_met and met_date fields
area: data-model
files:
  - src/pages/People/PersonDetail.jsx
  - src/components/PersonEditModal.jsx
  - src/hooks/usePeople.js
  - docs/data-model.md
  - docs/api-leden-crud.md
---

## Problem

The `how_we_met` and `met_date` ACF fields on person (leden) posts are unused and add unnecessary clutter to the data model, edit forms, and detail views. These fields should be removed entirely.

## Solution

Remove the ACF field definitions from the field group JSON, remove from PersonDetail display, PersonEditModal form, usePeople hooks, API documentation, and any REST API response shaping that includes these fields. Clean up related ACF JSON in `acf-json/`.
