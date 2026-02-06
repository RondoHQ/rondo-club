---
created: 2026-02-06T09:14
title: Store Sportlink sync field mappings in ACF JSON
area: data-model
files:
  - acf-json/
  - includes/class-sportlink-sync.php
  - src/pages/Settings.jsx
---

## Problem

Important field definitions and mappings for Sportlink sync (and other external tools) are currently configured through the WordPress admin UI and stored in the database. This means:

- Fresh installations require manual reconfiguration
- Settings don't travel with the theme in git
- Risk of losing configuration if database is reset
- Hard to track changes to field mappings over time

The theme should be portable - installing it on a new WordPress instance should bring all necessary configuration.

## Solution

1. **Audit current Sportlink field mappings**
   - Identify which fields are synced from Sportlink
   - Document the mapping between Sportlink API fields and ACF fields

2. **Store mappings in version-controlled files**
   - Option A: Extend ACF JSON with custom metadata
   - Option B: Create dedicated config file (e.g., `config/sportlink-mappings.json`)
   - Option C: Define mappings in PHP constants/arrays in class file

3. **Make UI read-only for synced fields**
   - Fields that come from external sources should show source indicator
   - Prevent manual editing of auto-synced values

4. **Apply same pattern to other integrations**
   - CardDAV field mappings
   - Google Contacts mappings
   - Any future external data sources
