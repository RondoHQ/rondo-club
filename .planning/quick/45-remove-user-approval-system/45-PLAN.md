---
phase: 45-remove-user-approval-system
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-user-roles.php
  - includes/class-access-control.php
  - includes/class-rest-base.php
  - includes/class-rest-api.php
  - src/pages/Settings/UserApproval.jsx
  - src/pages/Settings/Settings.jsx
  - src/router.jsx
autonomous: true

must_haves:
  truths:
    - "All logged-in users can access data without approval workflow"
    - "No approval-related code remains in access control logic"
    - "No approval UI appears in Settings"
    - "REST API permission checks only verify authentication, not approval"
  artifacts:
    - path: "includes/class-user-roles.php"
      provides: "Removed APPROVAL_META_KEY constant and approval logic"
      min_lines: 140
    - path: "includes/class-access-control.php"
      provides: "Simplified is_user_approved to check only login status"
      min_lines: 200
    - path: "includes/class-rest-base.php"
      provides: "Simplified check_user_approved to check only login status"
      min_lines: 200
    - path: "src/pages/Settings/Settings.jsx"
      provides: "Removed user approval settings link"
      min_lines: 3000
  key_links:
    - from: "includes/class-rest-base.php"
      to: "check_user_approved"
      via: "permission_callback"
      pattern: "check_user_approved"
    - from: "includes/class-access-control.php"
      to: "is_user_approved"
      via: "access control checks"
      pattern: "is_user_approved"
---

<objective>
Remove the user approval system that required admins to approve new users before they could access data.

Purpose: The approval workflow is no longer used (registration is disabled, users are manually created by admins). Removing it simplifies the codebase and eliminates unnecessary permission checks.

Output: Cleaner auth/access control code with only authentication checks remaining.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@AGENTS.md

# Relevant source files
@includes/class-user-roles.php
@includes/class-access-control.php
@includes/class-rest-base.php
@includes/class-rest-api.php
@src/pages/Settings/UserApproval.jsx
@src/pages/Settings/Settings.jsx
@src/router.jsx
</context>

<tasks>

<task type="auto">
  <name>Remove approval system from backend classes</name>
  <files>
    includes/class-user-roles.php
    includes/class-access-control.php
    includes/class-rest-base.php
    includes/class-rest-api.php
  </files>
  <action>
**1. In `includes/class-user-roles.php`:**
- Remove the `APPROVAL_META_KEY` constant (line 18)
- The `is_user_approved()` method already just checks user_id exists (returns true for all logged-in users) — keep it as-is for backward compatibility, but simplify the docblock to remove mention of "approval workflow removed"

**2. In `includes/class-access-control.php`:**
- Simplify `is_user_approved()` method (lines 44-59):
  - Remove the call to `\RONDO_User_Roles::is_user_approved()`
  - Just check `is_user_logged_in()` and admin status
  - Return true for all logged-in users (admins already return early)
- Update docblock to remove mention of "approved/unapproved users"
- Update method comment: "All logged-in users can access data"
- Keep all filter methods unchanged (they call is_user_approved internally)

**3. In `includes/class-rest-base.php`:**
- Simplify `check_user_approved()` method (lines 35-49):
  - Remove the call to `\RONDO_User_Roles::is_user_approved()`
  - Just check `is_user_logged_in()`
  - Return true for all logged-in users (admins already return early)
- Update docblock to say "Check if user is logged in" instead of "logged in and approved"
- Keep all other permission methods that call check_user_approved (they'll inherit the simpler logic)

**4. In `includes/class-rest-api.php`:**
- Line 384 comment: Change "Allow logged-in users (not just approved) so we can check approval status" to "Allow all logged-in users"
- Lines 2333, 2342, 2364: Remove the `is_approved` field from the current user and user list responses (these lines check and return is_approved status)
- Line 2188: The access control check will still work (is_user_approved now just checks login)
- Line 236 error message: Change "Your account is pending approval. Please contact an administrator." to "You do not have permission to access this resource." (generic permission error since approval no longer exists)

**Why this approach:**
- Keeps methods for backward compatibility (external plugins/sync scripts may call them)
- Simplifies to just authentication checks (no approval meta lookup)
- Removes approval status from API responses
- Updates user-facing error messages to be generic
  </action>
  <verify>
```bash
# Verify APPROVAL_META_KEY removed
! grep -q "APPROVAL_META_KEY" includes/class-user-roles.php

# Verify is_user_approved simplified
grep -A 10 "function is_user_approved" includes/class-access-control.php | grep -q "is_user_logged_in"

# Verify check_user_approved simplified
grep -A 10 "function check_user_approved" includes/class-rest-base.php | grep -q "is_user_logged_in"

# Verify is_approved removed from API responses
! grep -q "is_approved" includes/class-rest-api.php

# Verify error message updated
grep -q "You do not have permission" includes/class-access-control.php
```
  </verify>
  <done>
- APPROVAL_META_KEY constant removed from UserRoles class
- is_user_approved() methods simplified to check only login status (no meta lookup)
- check_user_approved() method simplified to check only login status
- is_approved field removed from REST API user responses
- Generic permission error message replaces approval-specific message
  </done>
</task>

<task type="auto">
  <name>Remove approval UI from frontend</name>
  <files>
    src/pages/Settings/Settings.jsx
    src/pages/Settings/UserApproval.jsx
    src/router.jsx
  </files>
  <action>
**1. In `src/pages/Settings/Settings.jsx`:**
- Remove the entire "Gebruikersbeheer" card section (lines 3145-3157)
- This is the card containing the link to `/settings/user-approval`

**2. Delete `src/pages/Settings/UserApproval.jsx`:**
- This entire file can be deleted (it just shows a message that approval is disabled)

**3. In `src/router.jsx`:**
- Remove the UserApproval import (line 29): `const UserApproval = lazy(() => import('@/pages/Settings/UserApproval'));`
- Remove the route (line 246): `{ path: 'settings/user-approval', element: <UserApproval /> },`

**Why:** The approval UI is just an info page saying "approval is disabled, use WordPress admin". Since we're removing the whole approval concept, remove the UI entirely.
  </action>
  <verify>
```bash
# Verify UserApproval component deleted
[ ! -f src/pages/Settings/UserApproval.jsx ]

# Verify UserApproval import removed from router
! grep -q "UserApproval" src/router.jsx

# Verify user-approval link removed from Settings
! grep -q "user-approval" src/pages/Settings/Settings.jsx
```
  </verify>
  <done>
- UserApproval.jsx component deleted
- User approval link removed from Settings page
- UserApproval route removed from router
- No approval-related UI remains in frontend
  </done>
</task>

<task type="auto">
  <name>Build and deploy changes</name>
  <files>
    dist/
  </files>
  <action>
Build the frontend and deploy to production:

```bash
cd /Users/joostdevalk/Code/rondo/rondo-club
npm run build
bin/deploy.sh
```

The build ensures the UserApproval component removal is reflected in production assets. Deployment syncs all changes to the production server.
  </action>
  <verify>
```bash
# Verify build succeeded
[ -f dist/manifest.json ]

# Verify deployment script completed
echo "Check deploy.sh output for 'Cache cleared successfully'"
```
  </verify>
  <done>
- Frontend built with updated code (no UserApproval component)
- All changes deployed to production server
- WordPress and SiteGround caches cleared
  </done>
</task>

</tasks>

<verification>
**Backend verification:**
1. Check that logged-in users can access REST endpoints without approval checks
2. Verify user meta table has no new approval meta entries being created
3. Check REST API `/rondo/v1/user` endpoint returns user data without `is_approved` field

**Frontend verification:**
1. Visit `/settings` - confirm no "Gebruikersbeheer" section appears
2. Try navigating to `/settings/user-approval` - should show 404 or redirect
3. Verify no console errors related to UserApproval component

**Access control verification:**
1. Log in as non-admin Rondo User
2. Access people list - should work (no approval block)
3. Create/edit a person - should work (no approval block)
</verification>

<success_criteria>
- All approval-related constants, methods, and checks removed or simplified to auth-only
- No approval meta key lookups occur in code
- REST API responses don't include `is_approved` field
- Frontend has no approval UI or routes
- Logged-in users can access all data without approval checks
- Production deployment complete with cache cleared
</success_criteria>

<output>
After completion, create `.planning/quick/45-remove-user-approval-system/45-SUMMARY.md`
</output>
