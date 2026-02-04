# Requirements: Stadion v15.0 Personal Tasks

**Defined:** 2026-02-04
**Core Value:** Tasks are personal - each user manages their own task list without seeing others' tasks

## v15.0 Requirements

### Task Isolation

- [x] **TASK-01**: User only sees tasks they created in the Tasks list page
- [x] **TASK-02**: User only sees their own tasks in the PersonDetail sidebar
- [x] **TASK-03**: User only sees their own tasks in the GlobalTodoModal
- [x] **TASK-04**: Dashboard open todos count reflects only user's own tasks
- [x] **TASK-05**: Dashboard awaiting todos count reflects only user's own tasks

### User Experience

- [x] **UX-01**: Tasks section visible to all users in navigation (not capability-gated)
- [x] **UX-02**: Persistent note in Tasks UI indicating tasks are personal
- [x] **UX-03**: Note displayed when creating a new task that it will only be visible to the creator

### Migration

- [x] **MIG-01**: Existing tasks assigned to their original post_author
- [x] **MIG-02**: WP-CLI migration command to verify/fix task ownership

## Future Requirements

(None identified)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Shared tasks | Tasks are intentionally personal; sharing adds complexity |
| Task delegation | Would require visibility to other users' tasks |
| Team task lists | Out of scope for personal CRM model |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| TASK-01 | Phase 139 | Complete |
| TASK-02 | Phase 139 | Complete |
| TASK-03 | Phase 139 | Complete |
| TASK-04 | Phase 139 | Complete |
| TASK-05 | Phase 139 | Complete |
| UX-01 | Phase 140 | Complete |
| UX-02 | Phase 140 | Complete |
| UX-03 | Phase 140 | Complete |
| MIG-01 | Phase 139 | Complete |
| MIG-02 | Phase 139 | Complete |

**Coverage:**
- v15.0 requirements: 10 total
- Mapped to phases: 10
- Unmapped: 0

---
*Requirements defined: 2026-02-04*
*Last updated: 2026-02-04 after phase 140 completion*
