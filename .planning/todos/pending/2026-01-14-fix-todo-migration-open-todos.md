---
created: 2026-01-14T16:30
title: Fix todo migration and open todos display
area: api
files:
  - includes/class-todo-migration.php
  - includes/class-rest-todos.php
  - includes/class-rest-api.php:709-736
---

## Problem

After deploying v3.1 with the new todo post status system:

1. The `wp prm migrate-todos` migration command doesn't work as expected
2. Open todos don't show any todos on the dashboard or TodosList

The migration was designed to convert existing todos from the old meta-based system (`is_completed`, `awaiting_response` fields with `post_status='publish'`) to the new WordPress post status system (`prm_open`, `prm_awaiting`, `prm_completed`).

Likely causes:
- Migration script may not be finding todos with `post_status='publish'`
- REST API queries may have issues with the new status filtering
- Dashboard `count_open_todos()` queries for `prm_open` status

## Solution

1. Debug migration script - check if it finds existing todos
2. Verify REST API status parameter handling
3. Check dashboard count query
4. May need to run migration with `--dry-run` first to see what it would do
