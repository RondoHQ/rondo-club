---
created: 2026-02-10T12:28:16.963Z
title: Add club-admin role for settings access
area: auth
files:
  - src/pages/VOG/VOG.jsx
  - src/pages/Contributie/Contributie.jsx
  - src/pages/Settings/Settings.jsx
  - src/router.jsx
  - includes/class-rest-api.php
  - includes/class-user-roles.php
---

## Problem

Currently, settings tabs on `/vog/instellingen` and `/contributie/instellingen` are gated by `isAdmin` (WordPress administrator). The `/settings/admin/rollen` page is also admin-only. This means only full WordPress administrators can configure VOG email templates, fee categories, and role assignments.

There should be a "club-admin" role that can manage these operational settings without being a full WordPress administrator. This role would have access to:
- VOG settings (email templates, exempt commissies)
- Contributie settings (fee categories, matching rules, family discount)
- Role settings (currently at `/settings/admin/rollen`)

The role settings page at `/settings/admin/rollen` should also move to a more logical location — it's buried inside the admin subtab of general settings, but it's really its own management area.

## Solution

1. Create a `club-admin` role (or capability) in `class-user-roles.php`
2. Update frontend `isAdmin` checks in VOG.jsx, Contributie.jsx, and Settings.jsx to also allow `club-admin`
3. Update backend `check_admin_permission` in REST API to allow `club-admin` for the relevant endpoints (VOG settings, fee settings, role settings) while keeping other admin endpoints restricted
4. Move the "Rollen" settings page out of `/settings/admin/rollen` to its own section or top-level settings tab
5. Consider whether `club-admin` should be a new WordPress role or a capability added to existing roles
