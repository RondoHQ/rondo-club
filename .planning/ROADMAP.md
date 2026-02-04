# Roadmap: Stadion v15.0 Personal Tasks

## Overview

Transform the task system from shared visibility to personal isolation. Each user will only see tasks they created, while maintaining the multi-person linking capability for context. Backend filtering ensures data isolation, migration preserves existing ownership, and frontend messaging makes the personal nature clear.

## Phases

**Phase Numbering:**
- Continues from v14.0 (last phase: 138)
- Integer phases (139, 140): Planned milestone work

- [x] **Phase 139: Backend & Migration** - Filter tasks by post_author, migrate existing tasks
- [ ] **Phase 140: Frontend Messaging** - Add personal task indicators to UI

## Phase Details

### Phase 139: Backend & Migration
**Goal**: Tasks are filtered by creator across all API endpoints; existing tasks have correct ownership
**Depends on**: v14.0 complete (Phase 138)
**Requirements**: TASK-01, TASK-02, TASK-03, TASK-04, TASK-05, MIG-01, MIG-02
**Success Criteria** (what must be TRUE):
  1. User A cannot see tasks created by User B in the Tasks list page
  2. User A cannot see User B's tasks in any PersonDetail sidebar
  3. User A cannot see User B's tasks in the GlobalTodoModal
  4. Dashboard open/awaiting counts reflect only the current user's tasks
  5. WP-CLI command can verify and fix task ownership for existing data
**Plans:** 1 plan

Plans:
- [x] 139-01-PLAN.md — Backend task filtering and WP-CLI migration command

### Phase 140: Frontend Messaging
**Goal**: Users understand tasks are personal through clear UI messaging
**Depends on**: Phase 139
**Requirements**: UX-01, UX-02, UX-03
**Success Criteria** (what must be TRUE):
  1. Tasks navigation item is visible to all users (no capability gating)
  2. Tasks list page shows persistent note indicating tasks are personal
  3. Task creation modal shows note that task will only be visible to creator
**Plans:** 1 plan

Plans:
- [ ] 140-01-PLAN.md — Frontend personal task indicators (navigation + info messages)

## Progress

**Execution Order:**
Phases execute in numeric order: 139 -> 140

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 139. Backend & Migration | 1/1 | ✓ Complete | 2026-02-04 |
| 140. Frontend Messaging | 0/1 | Not started | - |

---
*Roadmap created: 2026-02-04*
*Milestone: v15.0 Personal Tasks*
