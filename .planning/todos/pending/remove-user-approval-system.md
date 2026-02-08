---
created: 2026-02-08T15:25
title: Remove user approval system
area: auth
files:
  - includes/class-access-control.php
  - includes/class-rest-api.php
  - functions.php
  - src/hooks/useAuth.js
---

## Problem

The user approval system (where admins must approve new users before they can see data) is no longer used. This dead feature adds complexity to access control, REST API permission checks, and the frontend auth flow. Should be removed entirely.

## Solution

Remove all approval-related logic from AccessControl, REST API permission callbacks, frontend auth hooks, and any admin UI related to approving/rejecting users. Simplify the access model.
