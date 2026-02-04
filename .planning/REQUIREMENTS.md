# Requirements: Stadion v15.0 Personal Tasks

**Defined:** 2026-02-04
**Core Value:** Tasks are personal - each user manages their own task list without seeing others' tasks

## v15.0 Requirements

### Task Isolation

- [ ] **TASK-01**: User only sees tasks they created in the Tasks list page
- [ ] **TASK-02**: User only sees their own tasks in the PersonDetail sidebar
- [ ] **TASK-03**: User only sees their own tasks in the GlobalTodoModal
- [ ] **TASK-04**: Dashboard open todos count reflects only user's own tasks
- [ ] **TASK-05**: Dashboard awaiting todos count reflects only user's own tasks

### User Experience

- [ ] **UX-01**: Tasks section visible to all users in navigation (not capability-gated)
- [ ] **UX-02**: Persistent note in Tasks UI indicating tasks are personal
- [ ] **UX-03**: Note displayed when creating a new task that it will only be visible to the creator

### Migration

- [ ] **MIG-01**: Existing tasks assigned to their original post_author
- [ ] **MIG-02**: WP-CLI migration command to verify/fix task ownership

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
| TASK-01 | TBD | Pending |
| TASK-02 | TBD | Pending |
| TASK-03 | TBD | Pending |
| TASK-04 | TBD | Pending |
| TASK-05 | TBD | Pending |
| UX-01 | TBD | Pending |
| UX-02 | TBD | Pending |
| UX-03 | TBD | Pending |
| MIG-01 | TBD | Pending |
| MIG-02 | TBD | Pending |

**Coverage:**
- v15.0 requirements: 10 total
- Mapped to phases: 0
- Unmapped: 10 ⚠️

---
*Requirements defined: 2026-02-04*
*Last updated: 2026-02-04 after initial definition*
