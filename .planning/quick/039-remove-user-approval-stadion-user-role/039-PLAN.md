# Quick Task 039: Remove User Approval and Default Stadion User Role

**Description:** Remove the need for user approval and default creation of users as "Stadion User". Registration is disabled, so users are created manually.

**Directory:** .planning/quick/039-remove-user-approval-stadion-user-role

## Tasks

### Task 1: Simplify UserRoles class (backend)
**File:** includes/class-user-roles.php
**Changes:**
- Remove `set_default_role` filter hook from constructor
- Remove `handle_new_user_registration` action hook from constructor
- Remove approval-related hooks (admin columns, bulk actions, row actions, approval action handler)
- Delete methods: `set_default_role()`, `handle_new_user_registration()`, `notify_admins_of_pending_user()`, `approve_user()`, `deny_user()`, `add_approval_column()`, `show_approval_column()`, `add_bulk_approval_actions()`, `handle_bulk_approval()`, `add_user_row_actions()`, `handle_approval_action()`
- Keep: `APPROVAL_META_KEY` const (for backward compat), `is_user_approved()` (returns true always for logged-in users), role registration, `delete_user_posts()`

### Task 2: Simplify is_user_approved in UserRoles (backend)
**File:** includes/class-user-roles.php
**Changes:**
- Modify `is_user_approved()` to always return `true` for any logged-in user (no meta check needed)

### Task 3: Remove ApprovalCheck from router (frontend)
**File:** src/router.jsx
**Changes:**
- Remove `ApprovalCheck` component entirely
- In route config, replace `<ApprovalCheck><Layout /></ApprovalCheck>` with just `<Layout />`

### Task 4: Update UserApproval page to show message
**File:** src/pages/Settings/UserApproval.jsx
**Changes:**
- Replace with simple message explaining user approval is no longer used
- Keep route available but show informational message

### Task 5: Build and verify
- Run `npm run build`
- Run `npm run lint`

## Commit
```
chore(users): remove user approval workflow

Registration is disabled on the site, so users are created manually.
Remove:
- Default Stadion User role assignment on registration
- User approval requirement and admin notification
- Approval column/actions in WP admin
- ApprovalCheck wrapper in frontend router

All logged-in users now have full access.
```
