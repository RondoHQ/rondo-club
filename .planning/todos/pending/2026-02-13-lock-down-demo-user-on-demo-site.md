---
created: 2026-02-13T08:58:11.069Z
title: Lock down demo user on demo site
area: auth
files: []
---

## Problem

On the demo site (demo.rondo.club), people can log in as the `demo` user. Currently, that user can navigate to their WordPress profile and change their password. This means anyone could lock out future demo visitors by changing the demo account password.

## Solution

When a user is logged in as `demo` on the demo site (`rondo_is_demo_site` option is true):
1. Block access to the WordPress profile page (wp-admin/profile.php)
2. Prevent password changes via the REST API or any other method
3. Consider hiding/disabling the profile link in the Rondo UI if visible
4. Could be implemented as a PHP filter/hook that checks username === 'demo' && is_demo_site, then redirects or blocks profile edits
