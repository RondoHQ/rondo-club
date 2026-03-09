---
phase: 119-restrict-people-editing
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-access-control.php
  - includes/class-rest-base.php
  - includes/class-rest-api.php
  - src/pages/People/PersonDetail.jsx
autonomous: true
requirements: [EDIT-RESTRICT-01]
must_haves:
  truths:
    - "Users with FairPlay, Bestuur, VOG, or Financieel roles can edit people"
    - "Users without any of these roles cannot see edit buttons on people"
    - "Users without any of these roles get 403 when attempting to edit people via API"
  artifacts:
    - path: "includes/class-access-control.php"
      provides: "can_edit_people() capability check method"
      contains: "can_edit_people"
    - path: "includes/class-rest-base.php"
      provides: "Updated check_person_edit_permission using can_edit_people"
    - path: "includes/class-rest-api.php"
      provides: "can_edit_people flag in current-user response"
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "Conditional rendering of edit UI based on canEditPeople"
  key_links:
    - from: "src/pages/People/PersonDetail.jsx"
      to: "includes/class-rest-api.php"
      via: "useCurrentUser() -> can_edit_people flag"
      pattern: "canEditPeople"
    - from: "includes/class-rest-base.php"
      to: "includes/class-access-control.php"
      via: "can_edit_people() method call"
      pattern: "can_edit_people"
---

<objective>
Restrict people editing to users with FairPlay, Bestuur, VOG, or Financieel roles.

Purpose: Currently all logged-in users can edit any person record. This should be limited to users with specific capabilities (fairplay, vog, or financieel - Bestuur already has all three).
Output: Backend enforcement + frontend UI gating so only authorized users see and can use edit functionality on people.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@CLAUDE.md
@includes/class-access-control.php
@includes/class-rest-base.php
@includes/class-rest-api.php (current-user endpoint around line 3929)
@includes/class-user-roles.php (role/capability definitions)
@includes/class-post-types.php (person CPT uses capability_type => post)
@src/pages/People/PersonDetail.jsx
@src/hooks/useCurrentUser.js

<interfaces>
<!-- From includes/class-rest-base.php -->
class Base {
    public function check_user_approved(): bool;
    public function check_person_edit_permission($request): bool;
    public function check_person_access($request): bool;
}

<!-- From includes/class-access-control.php -->
class AccessControl {
    public function user_can_access_post($post_id, $user_id = null): bool;
    private function is_vog_only_user($user_id = null): bool;
}

<!-- From includes/class-user-roles.php - capability constants -->
const FAIRPLAY_CAPABILITY   = 'fairplay';
const VOG_CAPABILITY        = 'vog';
const FINANCIEEL_CAPABILITY = 'financieel';

<!-- Current user API response (from class-rest-api.php line ~3929) includes: -->
can_access_fairplay, can_access_vog, can_access_financieel, can_access_toegangscontrole, can_access_clothing

<!-- PersonDetail.jsx currently uses: -->
const canAccessFairplay = currentUser?.can_access_fairplay ?? false;
const canAccessFinancieel = currentUser?.can_access_financieel ?? false;
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add can_edit_people backend enforcement</name>
  <files>includes/class-access-control.php, includes/class-rest-base.php, includes/class-rest-api.php</files>
  <action>
1. In `includes/class-access-control.php`, add a static method `can_edit_people($user_id = null)` that returns true if the user has ANY of: `fairplay`, `vog`, `financieel`, or `manage_options` capabilities. Use `user_can()` with the provided/current user ID.

2. In `includes/class-rest-base.php`, update `check_person_edit_permission()` to also call `AccessControl::can_edit_people()`. After the existing logged-in and person-exists checks, add: `if (!AccessControl::can_edit_people()) { return false; }`. This gates the photo upload and any other custom endpoints using this permission callback.

3. In `includes/class-access-control.php`, add a `map_meta_cap` filter (in the constructor) that intercepts `edit_post` for person post types. When a user without can_edit_people() tries to edit a person post, map the capability to `do_not_allow`. This blocks the standard WP REST API `PUT /wp/v2/people/{id}` route. The filter should check: if the requested capability is `edit_post` and the post type is `person`, and `can_edit_people()` returns false for that user, return `['do_not_allow']`. Otherwise return the original caps unchanged. Be careful: the `map_meta_cap` filter receives `($caps, $cap, $user_id, $args)` where `$args[0]` is the post ID (for `edit_post`). Only apply when `$cap === 'edit_post'` and the post exists and is type `person`.

4. In `includes/class-rest-api.php`, in the current-user endpoint response (around line 3936), add `'can_edit_people' => \Rondo\Core\AccessControl::can_edit_people()` to expose this flag to the frontend.
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build</automated>
  </verify>
  <done>Backend enforces that only users with fairplay, vog, financieel, or manage_options can edit person posts (both standard WP REST and custom endpoints). Current-user API exposes can_edit_people flag.</done>
</task>

<task type="auto">
  <name>Task 2: Gate frontend edit UI behind canEditPeople</name>
  <files>src/pages/People/PersonDetail.jsx</files>
  <action>
1. In PersonDetail.jsx, after the existing capability variables (line ~78), add:
   `const canEditPeople = currentUser?.can_edit_people ?? false;`

2. Conditionally render ALL edit-related UI elements based on `canEditPeople`:

   a. **Contact edit button** (around line 1287-1293): Wrap the "Bewerken" button in `{canEditPeople && (...)}`. Also wrap the "Toevoegen" link in the empty contacts state (line ~1324).

   b. **Relationship edit/delete buttons** (around line 1463-1477): Wrap the Pencil edit button and Trash2 delete button per-relationship in `{canEditPeople && (...)}`. Also wrap the "Toevoegen" link in empty relationships state (line ~1486). Wrap the "Relatie toevoegen" button at the top of relationships section if one exists.

   c. **Photo upload** (around line 1090-1102): Wrap the camera overlay click handler and file input in `{canEditPeople && (...)}`. The photo should still display, just not be uploadable.

   d. **Custom fields section**: If `CustomFieldsSection` has edit capabilities, pass `readOnly={!canEditPeople}` or similar. Check the component to determine the right prop.

   e. **Timeline action buttons** (Notes, Activities around line 1582-1590): Wrap the "Notitie" and "Activiteit" add buttons in `{canEditPeople && (...)}`. Also gate the `onEdit` and `onDelete` callbacks in TimelineView — pass them as `undefined` or `null` when `!canEditPeople` so the timeline items don't show edit/delete buttons.

   f. **Todo add button**: Wrap todo creation UI in `{canEditPeople && (...)}`.

   g. **Contact modals**: The ContactEditModal and RelationshipEditModal are already behind showContactModal/showRelationshipModal state, which can only be set to true via the buttons we're gating. But for safety, also add `canEditPeople &&` before rendering these modals.

3. Do NOT gate the FinancesCard separately - it already has its own `canAccessFinancieel` gating. Similarly, VOGCard has its own capability check.

4. Do NOT gate viewing/reading of any data - all users should still see all person information, just not be able to edit.
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build && npm run lint</automated>
  </verify>
  <done>Users without FairPlay/Bestuur/VOG/Financieel roles see person detail pages in read-only mode: no edit buttons for contacts, relationships, photos, timeline items, or custom fields. Users with those roles see the full edit UI as before.</done>
</task>

</tasks>

<verification>
1. Build succeeds: `npm run build`
2. Lint passes: `npm run lint`
3. Deploy to production and verify:
   - As an admin/bestuur user: all edit buttons visible on person detail, editing works
   - As a regular rondo_user: person detail shows all data but no edit buttons, API returns 403 on edit attempts
</verification>

<success_criteria>
- Backend: `map_meta_cap` filter blocks edit_post for person posts for unauthorized users
- Backend: `check_person_edit_permission` additionally checks can_edit_people
- Backend: Current user API returns `can_edit_people` flag
- Frontend: All edit UI on PersonDetail conditionally rendered based on `canEditPeople`
- No change to data visibility - all users still see all person data
- Build and lint pass
</success_criteria>

<output>
After completion, create `.planning/quick/119-the-editing-functionality-on-people-shou/119-SUMMARY.md`
</output>
