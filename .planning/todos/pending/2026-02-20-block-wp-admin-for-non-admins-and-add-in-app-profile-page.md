---
created: 2026-02-20T14:38:47.951Z
title: Block WP admin for non-admins and add in-app profile page
area: auth
files:
  - includes/class-access-control.php
  - includes/class-user-roles.php
  - src/components/layout/Layout.jsx
  - src/router.jsx
---

## Problem

Non-admin WordPress users (Rondo Users) can currently access the WordPress admin dashboard, including their profile page at `/wp-admin/profile.php`. They should be completely blocked from the WP admin area.

This creates three requirements:

1. **Block WP admin access** — Non-admin users should be redirected away from `/wp-admin/` entirely, including the profile page.

2. **In-app profile page** — Since users lose access to their WP profile, they need a profile page within the React SPA where they can at minimum update their password. This page should be accessible from the user avatar/menu in the top-right corner.

3. **User-to-member linking** — An admin should be able to connect a WordPress user account to the correct person (member) record in the system. This is the Sportlink-synced member data. Once linked, the user's avatar in the top-right of the app should show their Sportlink profile photo (if one exists) instead of a generic icon.

## Solution

1. Add a `admin_init` or `init` hook in `class-access-control.php` that redirects non-admin users away from `/wp-admin/` to the app's home page. Allow AJAX requests through (`admin-ajax.php`).
2. Create a new React page (`src/pages/Profile/`) with password change form using the WP REST API for user updates.
3. Add a user meta field (or ACF field) linking `user_id` → `person_id` (the Sportlink-synced member post). Admin UI to set this link in Settings or user management.
4. Update the Layout.jsx top-right avatar to fetch the linked person's photo when available.
5. Related to existing todo "Add club-admin role for settings access" — the admin blocking should respect whatever role hierarchy is established there.
