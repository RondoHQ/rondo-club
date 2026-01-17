---
created: 2026-01-14T18:34
title: Todo detail modal with notes and multi-person support
area: ui
files:
  - src/components/Timeline/TodoModal.jsx
  - includes/class-rest-todos.php
  - acf-json/group_todo_fields.json
---

## Problem

1. **Click behavior**: Clicking on a todo currently navigates to the related person's profile. Users want to click on a todo and see/edit the todo itself in a modal.

2. **No todo notes**: Todos don't have a way to add notes or comments. Users want to track progress, add context, or leave notes on a todo over time.

3. **Single person limitation**: A todo can currently only be connected to one person. Some todos involve multiple people (e.g., "Schedule meeting with Alice and Bob").

## Solution

**UI Changes:**
- Clicking a todo opens a detail modal instead of navigating to person
- Modal shows todo content, status, due date, related people
- Add notes/comments section in modal (stored as WP comments on the todo post?)

**Data Model Changes:**
- Change `person_id` from single value to array of person IDs
- Update REST API to handle multiple people
- Update UI components to display multiple related people

TBD: Notes storage mechanism (WP comments vs custom meta)
TBD: UI for selecting multiple people
