---
created: 2026-01-14T13:54
title: Add label management interface
area: ui
files: []
---

## Problem

Currently there's no way for users to create, edit, or delete labels (taxonomies) in the Caelis frontend. Labels for people (`person_label`) and organizations (`company_label`) can only be managed through the WordPress admin.

This creates friction for users who want to organize their contacts with custom labels.

**Multi-user considerations:**
- Labels are WordPress taxonomies — they're global by default
- In a multi-user environment, should labels be:
  - Global (shared across all users)?
  - Per-user (each user has their own labels)?
  - Workspace-scoped (shared within a workspace)?
- Need to consider access control implications

## Solution

Add a label management UI that allows users to:
- Create new labels
- Rename existing labels
- Delete unused labels
- View label usage counts

TBD: Multi-user scope decision (global vs per-user vs workspace)
TBD: UI placement (Settings page section, dedicated Labels page, or inline creation)
