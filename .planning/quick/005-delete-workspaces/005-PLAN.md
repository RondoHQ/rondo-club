---
quick: 005
type: execute
description: Delete the entire Workspaces functionality from the app
files_modified:
  # Frontend - Delete entire files
  - src/pages/Workspaces/WorkspacesList.jsx (DELETE)
  - src/pages/Workspaces/WorkspaceDetail.jsx (DELETE)
  - src/pages/Workspaces/WorkspaceSettings.jsx (DELETE)
  - src/pages/Workspaces/WorkspaceInviteAccept.jsx (DELETE)
  - src/components/WorkspaceCreateModal.jsx (DELETE)
  - src/components/WorkspaceInviteModal.jsx (DELETE)
  - src/components/VisibilitySelector.jsx (DELETE)
  - src/hooks/useWorkspaces.js (DELETE)
  # Frontend - Modify to remove workspace references
  - src/App.jsx
  - src/components/layout/Layout.jsx
  - src/api/client.js
  - src/components/PersonEditModal.jsx
  - src/components/TeamEditModal.jsx
  - src/components/CommissieEditModal.jsx
  # Backend - Delete entire files
  - includes/class-rest-workspaces.php (DELETE)
  - includes/class-workspace-members.php (DELETE)
  - includes/class-visibility.php (DELETE)
  - acf-json/group_workspace_invite_fields.json (DELETE)
  - acf-json/group_visibility_settings.json (DELETE)
  - tests/Wpunit/WorkspacePermissionsTest.php (DELETE)
  # Backend - Modify to remove workspace references
  - functions.php
  - includes/class-post-types.php
  - includes/class-taxonomies.php
  - includes/class-access-control.php
  - includes/class-rest-people.php
  - includes/class-rest-teams.php
  - includes/class-rest-commissies.php
  - includes/class-reminders.php
  - includes/class-ical-feed.php
autonomous: true
---

<objective>
Remove the entire Workspaces functionality from Stadion.

Purpose: Workspaces are not needed for this application. Removing them simplifies the codebase and UI.

Output: Clean codebase with no workspace-related code, all tests passing, app functioning normally.
</objective>

<context>
@.planning/PROJECT.md - Project overview
@AGENTS.md - Development guidelines

The Workspaces feature was part of the v2.0 Multi-User milestone but is no longer needed. This removal task deletes:
- 4 frontend pages (WorkspacesList, WorkspaceDetail, WorkspaceSettings, WorkspaceInviteAccept)
- 3 frontend components (WorkspaceCreateModal, WorkspaceInviteModal, VisibilitySelector)
- 1 hook file (useWorkspaces.js)
- 2 backend PHP classes (class-rest-workspaces.php, class-workspace-members.php, class-visibility.php)
- 2 ACF field groups (workspace invites, visibility settings)
- 1 test file (WorkspacePermissionsTest.php)
- References in ~15 other files
</context>

<tasks>

<task type="auto">
  <name>Task 1: Delete workspace frontend files</name>
  <files>
    src/pages/Workspaces/ (entire directory)
    src/components/WorkspaceCreateModal.jsx
    src/components/WorkspaceInviteModal.jsx
    src/components/VisibilitySelector.jsx
    src/hooks/useWorkspaces.js
  </files>
  <action>
    Delete the following files and directories:

    1. Delete entire Workspaces pages directory:
       - src/pages/Workspaces/WorkspacesList.jsx
       - src/pages/Workspaces/WorkspaceDetail.jsx
       - src/pages/Workspaces/WorkspaceSettings.jsx
       - src/pages/Workspaces/WorkspaceInviteAccept.jsx
       - rm -rf src/pages/Workspaces/

    2. Delete workspace-related components:
       - src/components/WorkspaceCreateModal.jsx
       - src/components/WorkspaceInviteModal.jsx
       - src/components/VisibilitySelector.jsx

    3. Delete workspace hook:
       - src/hooks/useWorkspaces.js
  </action>
  <verify>ls src/pages/ | grep -i workspace should return nothing; ls src/components/ | grep -i Workspace should return nothing; ls src/hooks/ | grep -i workspace should return nothing</verify>
  <done>All workspace-related frontend files deleted</done>
</task>

<task type="auto">
  <name>Task 2: Remove workspace references from frontend</name>
  <files>
    src/App.jsx
    src/components/layout/Layout.jsx
    src/api/client.js
    src/components/PersonEditModal.jsx
    src/components/TeamEditModal.jsx
    src/components/CommissieEditModal.jsx
  </files>
  <action>
    1. src/App.jsx:
       - Remove lazy imports for WorkspacesList, WorkspaceDetail, WorkspaceSettings, WorkspaceInviteAccept
       - Remove routes: /workspaces, /workspaces/:id, /workspaces/:id/settings, /workspace-invite/:token

    2. src/components/layout/Layout.jsx:
       - Remove "Workspaces" entry from navigation array (the one with href: '/workspaces')
       - Remove the duplicate UsersRound icon import if no longer needed (keep it for Commissies)

    3. src/api/client.js:
       - Remove all workspace-related API methods from prmApi:
         - getWorkspaces, getWorkspace, createWorkspace, updateWorkspace, deleteWorkspace
         - addWorkspaceMember, removeWorkspaceMember, updateWorkspaceMember, searchWorkspaceMembers
         - getWorkspaceInvites, createWorkspaceInvite, revokeWorkspaceInvite
         - validateInvite, acceptInvite
         - getPostShares, sharePost, unsharePost (sharing functionality)

    4. src/components/PersonEditModal.jsx:
       - Remove VisibilitySelector import and usage
       - Remove visibility and workspaces state/props
       - Remove handleVisibilityChange function
       - Keep the modal functional for editing persons without visibility options

    5. src/components/TeamEditModal.jsx:
       - Remove VisibilitySelector import and usage
       - Remove visibility and workspaces state/props
       - Remove handleVisibilityChange function

    6. src/components/CommissieEditModal.jsx:
       - Remove VisibilitySelector import and usage
       - Remove visibility and workspaces state/props
       - Remove handleVisibilityChange function
  </action>
  <verify>npm run lint passes; grep -r "useWorkspaces\|VisibilitySelector\|WorkspacesList" src/ returns nothing</verify>
  <done>All workspace references removed from frontend code</done>
</task>

<task type="auto">
  <name>Task 3: Delete workspace backend files</name>
  <files>
    includes/class-rest-workspaces.php
    includes/class-workspace-members.php
    includes/class-visibility.php
    acf-json/group_workspace_invite_fields.json
    acf-json/group_visibility_settings.json
    tests/Wpunit/WorkspacePermissionsTest.php
  </files>
  <action>
    Delete the following backend files:

    1. PHP classes:
       - includes/class-rest-workspaces.php
       - includes/class-workspace-members.php
       - includes/class-visibility.php

    2. ACF field groups:
       - acf-json/group_workspace_invite_fields.json
       - acf-json/group_visibility_settings.json

    3. Tests:
       - tests/Wpunit/WorkspacePermissionsTest.php
  </action>
  <verify>ls includes/ | grep -i workspace returns nothing; ls acf-json/ | grep -i workspace returns nothing</verify>
  <done>All workspace-related backend files deleted</done>
</task>

<task type="auto">
  <name>Task 4: Remove workspace references from backend</name>
  <files>
    functions.php
    includes/class-post-types.php
    includes/class-taxonomies.php
    includes/class-access-control.php
    includes/class-rest-people.php
    includes/class-rest-teams.php
    includes/class-rest-commissies.php
    includes/class-reminders.php
    includes/class-ical-feed.php
  </files>
  <action>
    1. functions.php:
       - Remove use statements for Workspaces, WorkspaceMembers, Visibility classes
       - Remove class_alias entries for RONDO_REST_Workspaces, RONDO_Workspace_Members, RONDO_Visibility
       - Remove "new Workspaces();" instantiation in rondo_init()
       - Remove "new WorkspaceMembers();" instantiation

    2. includes/class-post-types.php:
       - Remove 'workspace' and 'workspace_invite' CPT registrations if present

    3. includes/class-taxonomies.php:
       - Remove 'workspace_access' taxonomy registration if present

    4. includes/class-access-control.php:
       - Remove workspace-related access control logic
       - Simplify to just author-based access (users see only their own posts)
       - Remove _visibility, _workspace_ids, _shared_with meta handling

    5. includes/class-rest-people.php:
       - Remove visibility and workspace_ids fields from REST schema
       - Remove workspace-related filtering from collection queries

    6. includes/class-rest-teams.php:
       - Remove visibility and workspace_ids fields from REST schema
       - Remove workspace-related filtering from collection queries

    7. includes/class-rest-commissies.php:
       - Remove visibility and workspace_ids fields from REST schema
       - Remove workspace-related filtering from collection queries

    8. includes/class-reminders.php:
       - Remove workspace activity digest functionality
       - Keep personal reminder functionality intact

    9. includes/class-ical-feed.php:
       - Remove workspace calendar feed functionality
       - Keep personal iCal feed functionality intact
  </action>
  <verify>grep -r "workspace" includes/*.php should only show legitimate uses (like "Workspace" in comments or unrelated context); composer lint passes</verify>
  <done>All workspace references removed from backend PHP code</done>
</task>

<task type="auto">
  <name>Task 5: Build, test, and deploy</name>
  <files>
    dist/
    style.css
    package.json
    CHANGELOG.md
  </files>
  <action>
    1. Build frontend:
       - npm run build
       - Verify build completes without errors

    2. Run tests:
       - Run any remaining tests: vendor/bin/codecept run wpunit
       - Fix any test failures related to workspace removal

    3. Update version:
       - Bump patch version in style.css and package.json (e.g., 7.0.x -> 7.0.x+1)

    4. Update CHANGELOG.md:
       - Add entry under [Unreleased] or new version:
         - Removed: Workspaces functionality (pages, components, API endpoints, backend classes)

    5. Deploy to production:
       - bin/deploy.sh
  </action>
  <verify>npm run build completes successfully; production site loads without errors</verify>
  <done>Application built, tested, and deployed with workspaces removed</done>
</task>

</tasks>

<verification>
1. Frontend verification:
   - npm run lint passes
   - npm run build succeeds
   - No workspace-related imports or routes in compiled code

2. Backend verification:
   - composer lint passes
   - No PHP errors on page load
   - REST API responds correctly for people, teams, commissies

3. Functional verification:
   - Navigation no longer shows "Workspaces" link
   - /workspaces route redirects to home or 404
   - Person/Team/Commissie edit modals work without visibility selector
   - All core functionality (people, teams, dates, todos) works normally
</verification>

<success_criteria>
- All workspace-related files deleted (8 frontend files, 5 backend files, 1 test file)
- All workspace references removed from remaining files
- npm run lint passes
- npm run build succeeds
- Application deployed and functional
- Navigation shows no Workspaces link
- Edit modals work without visibility options
</success_criteria>
