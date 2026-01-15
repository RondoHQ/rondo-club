---
phase: 42
plan: 01
subsystem: ui
tags: [todo, modal, dashboard, date-fns, UX]
requires: []
provides: [todo-view-mode, dashboard-todo-modal, tomorrow-default]
affects: []
tech-stack:
  added: []
  patterns: [view-edit-modal-pattern]
key-files:
  created: []
  modified:
    - src/pages/Dashboard.jsx
    - src/components/Timeline/TodoModal.jsx
    - src/components/Timeline/GlobalTodoModal.jsx
key-decisions:
  - View mode shows HTML notes with dangerouslySetInnerHTML (safe since content is from RichTextEditor)
  - Cancel in edit mode returns to view mode for existing todos, closes modal for new todos
  - Tomorrow as default date balances task urgency with realistic planning
duration: ~10 min
completed: 2026-01-15
---

# Phase 42 Plan 01: Todo UX Polish Summary

**One-liner:** Dashboard todo click opens modal with view-first mode; new todos default to tomorrow

## Accomplishments

### Task 1: Add TodoModal to Dashboard for viewing/editing todos
- Imported TodoModal with lazy loading and Suspense
- Converted TodoCard from Link to button element with onView callback
- Converted AwaitingTodoCard from Link to button element with onView callback
- Added state variables (todoToView, showTodoModal) for modal control
- Added handleViewTodo and handleUpdateTodo handlers
- Added TodoModal wrapped in Suspense fallback to Dashboard JSX

### Task 2: Add view-first mode to TodoModal
- Added isViewMode state to track current display mode
- Existing todos open in view mode by default, new todos in edit mode
- View mode displays:
  - Description as plain text (whitespace preserved)
  - Due date formatted (e.g., "Jan 16, 2026" or "No due date")
  - Notes rendered as HTML with prose styling
  - Related people as read-only avatar chips
- Added Edit button with pencil icon to switch to edit mode
- Cancel button in edit mode returns to view mode for existing todos
- Modal title dynamically shows "View todo" / "Edit todo" / "Add todo"
- Added formatDateForDisplay helper using date-fns format

### Task 3: Change default due date to tomorrow
- Renamed getTodayDate to getTomorrowDate in both modal components
- Updated function to add 1 day to current date
- All references updated in TodoModal.jsx and GlobalTodoModal.jsx
- New todos now default to tomorrow's date

## Files Created/Modified

| File | Action | Description |
|------|--------|-------------|
| src/pages/Dashboard.jsx | Modified | Added TodoModal with lazy loading, converted todo cards to buttons |
| src/components/Timeline/TodoModal.jsx | Modified | Added view-first mode, tomorrow default date |
| src/components/Timeline/GlobalTodoModal.jsx | Modified | Tomorrow default date |

## Deviations from Plan

None - plan executed exactly as written.

## Decisions Made

1. **HTML notes rendering** - Used dangerouslySetInnerHTML for notes in view mode since content comes from sanitized RichTextEditor (TipTap)
2. **Cancel behavior** - Cancel in edit mode returns to view mode for existing todos, closes modal for new todos (better UX than always closing)
3. **Tomorrow default** - Balanced between task urgency (not too far) and realistic planning (not today which may already be overdue)

## Performance

- Duration: ~10 minutes
- Tasks completed: 3/3
- Files modified: 3

## Issues Encountered

None

## Next Phase Readiness

Phase 42 is complete. This was the only phase in milestone v3.7.

**Ready for:** `/gsd:complete-milestone` to archive v3.7 and prepare for next version
