---
created: 2026-02-04T15:51
title: Personal Tasks with User Isolation
area: api
files:
  - src/pages/Todos/TodosList.jsx
  - includes/class-rest-api.php
---

## Problem

Tasks (todos) are currently visible to all users. They should be entirely personal - each user should only see tasks they've created themselves. The Tasks section should remain visible to everyone in navigation, but the content should be user-specific.

Additionally, when creating a task, there should be a clear note making it obvious that tasks are personal and won't be seen by other users.

## Solution

1. Add `post_author` filtering to todo queries in REST API (similar to how access control works for other CPTs)
2. Ensure new todos are created with current user as author
3. Add a note/disclaimer in the task creation UI explaining tasks are personal
4. Update any list views to filter by current user
