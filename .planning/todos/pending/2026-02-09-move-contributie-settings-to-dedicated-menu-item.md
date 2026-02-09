---
created: 2026-02-09T14:26
title: Move contributie settings to dedicated menu item
area: ui
files:
  - src/router.jsx
  - src/pages/Settings/Settings.jsx
  - src/components/Layout.jsx
---

## Problem

The Contributie (membership fees) settings are currently nested inside the Settings page. When Contributie gets its own dedicated menu item in the sidebar navigation, the settings page for fee categories, family discounts, and matching rules should move there too — keeping everything contributie-related in one place.

## Solution

When creating a separate Contributie menu item, extract the contributie settings tab (fee categories, family discount, matching rules) from the Settings page and place it under the Contributie section (e.g., as a settings/config sub-page or tab within the Contributie area). Update router and navigation accordingly.
