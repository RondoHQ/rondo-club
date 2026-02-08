---
created: 2026-02-08T15:27
title: Remove contact import feature
area: ui
files:
  - src/pages/Settings/Settings.jsx
  - src/pages/People/PersonDetail.jsx
  - src/api/client.js
  - includes/class-vcard-import.php
  - docs/rest-api.md
  - docs/architecture.md
---

## Problem

The contact import feature (vCard/Google import) will never be used. It adds dead code across the settings UI, API client, backend PHP import handler, and documentation. Should be removed entirely.

## Solution

Remove the import UI from Settings, the import API endpoint and client methods, the PHP vCard import class, and update documentation to remove references to contact importing.
