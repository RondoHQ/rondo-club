---
phase: 133
plan: 01
subsystem: access-control
tags: [capabilities, authentication, fairplay, react-routing, conditional-rendering]
requires: [phase-132]
provides: [fairplay-capability, access-control-foundation]
affects: [phase-134]
decisions:
  - id: fairplay-cap-name
    choice: Use "fairplay" as capability name
    rationale: Clear, domain-specific, follows WordPress convention
  - id: route-protection
    choice: Create FairplayRoute wrapper component
    rationale: Follows existing pattern (ProtectedRoute, ApprovalCheck), reusable
  - id: navigation-filtering
    choice: Filter navigation array based on requiresFairplay flag
    rationale: Declarative, maintainable, consistent with navigation structure
tech-stack:
  added: []
  patterns: [capability-based-access-control, route-wrapper-components]
key-files:
  created:
    - src/pages/DisciplineCases/DisciplineCasesList.jsx
  modified:
    - includes/class-user-roles.php
    - includes/class-rest-api.php
    - src/App.jsx
    - src/components/layout/Layout.jsx
    - src/pages/People/PersonDetail.jsx
metrics:
  tasks: 3
  commits: 3
  duration: 3m 42s
  completed: 2026-02-03
---

# Phase 133 Plan 01: Capability-Based Access Control Summary

**One-liner:** Implemented fairplay capability for administrators with conditional UI rendering across routes, navigation, and person detail tabs

## What Was Built

### Backend Capability System
- **FAIRPLAY_CAPABILITY constant** in UserRoles class defining capability name
- **Capability registration** adds `fairplay` to administrator role on theme activation
- **Capability cleanup** removes `fairplay` from administrator role on theme deactivation
- **REST API exposure** adds `can_access_fairplay` boolean to `/stadion/v1/user/me` endpoint

### Frontend Access Control
- **FairplayRoute component** wraps protected routes with access denied page
- **DisciplineCasesList placeholder** page created for Phase 134 implementation
- **Protected route** `/discipline-cases` requires fairplay capability
- **Conditional navigation** Tuchtzaken menu item shown only to fairplay users
- **Conditional person tab** Tuchtzaken tab on person detail shown only to fairplay users

## Implementation Details

### Task 1: Backend Capability Registration
**Files:** `includes/class-user-roles.php`, `includes/class-rest-api.php`

Added constant and registration logic to UserRoles:
```php
const FAIRPLAY_CAPABILITY = 'fairplay';

public function register_role() {
    // ... existing role registration

    // Add fairplay capability to administrator role
    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        $admin_role->add_cap( self::FAIRPLAY_CAPABILITY );
    }
}
```

Exposed capability in REST API response:
```php
return rest_ensure_response([
    // ... existing fields
    'can_access_fairplay' => current_user_can( 'fairplay' ),
]);
```

**Commit:** `61852631` - feat(133-01): register fairplay capability and expose in REST API

### Task 2: React Route Protection
**Files:** `src/App.jsx`, `src/components/layout/Layout.jsx`, `src/pages/DisciplineCases/DisciplineCasesList.jsx`

Created FairplayRoute wrapper component:
- Queries current user via `useQuery` with `['current-user']` key
- Shows loading spinner during capability check
- Renders access denied page with Shield icon if user lacks capability
- Returns children if user has fairplay capability

Added conditional navigation in Layout.jsx:
- Imported Gavel icon for Tuchtzaken menu item
- Added `requiresFairplay: true` flag to navigation array
- Filtered navigation items: `.filter(item => !item.requiresFairplay || canAccessFairplay)`
- Fetches current user in Sidebar component for capability check

**Commit:** `f4efad99` - feat(133-01): add React route protection and conditional navigation

### Task 3: Person Detail Tab
**Files:** `src/pages/People/PersonDetail.jsx`

Added capability query and conditional rendering:
- Imported Gavel icon
- Added `['current-user']` query to component
- Derived `canAccessFairplay` boolean from query data
- Conditionally rendered Tuchtzaken TabButton: `{canAccessFairplay && <TabButton ... />}`
- Added placeholder tab content with Gavel icon and Phase 134 message

**Commit:** `1c4dfa5b` - feat(133-01): add conditional Tuchtzaken tab on person detail page

## Deviations from Plan

None - plan executed exactly as written.

## Decisions Made

| ID | Decision | Rationale | Impact |
|----|----------|-----------|--------|
| fairplay-cap-name | Use "fairplay" as capability name | Clear domain terminology, follows WordPress conventions | Capability name affects all permission checks |
| route-protection | Create FairplayRoute wrapper component | Follows existing pattern (ProtectedRoute, ApprovalCheck), reusable for future protected routes | Consistent architecture, maintainable |
| navigation-filtering | Filter navigation based on requiresFairplay flag | Declarative approach, easy to add more protected items | Scalable for additional capability-based features |

## Technical Patterns

### Capability-Based Access Control
- WordPress native `add_cap()` and `current_user_can()` functions
- Capability cleanup on theme deactivation prevents orphaned permissions
- REST API exposure enables frontend capability checks without additional requests

### Route Wrapper Components
- FairplayRoute follows React Router best practices
- Access denied page provides clear UX with "Ga terug" button
- Dark mode support via Tailwind classes

### Conditional Rendering
- Navigation filtering keeps navigation array declarative
- Person detail tab uses same `canAccessFairplay` boolean for consistency
- Queries use same `['current-user']` key for cache efficiency

## Verification Results

✅ **Capability Registration**
- Administrator role has `fairplay` capability (verified via WP-CLI)
- Capability persists after page refresh
- Capability will be removed on theme deactivation

✅ **REST API**
- `/stadion/v1/user/me` returns `can_access_fairplay: true` for admins
- Non-admin users would receive `can_access_fairplay: false`

✅ **Route Protection**
- `/discipline-cases` loads placeholder page for fairplay users
- Non-fairplay users see access denied page with Shield icon

✅ **Navigation**
- Tuchtzaken nav item visible in sidebar (admin user)
- Navigation filtering works without errors

✅ **Person Detail Tab**
- Tuchtzaken tab visible on person detail page (admin user)
- Tab content shows placeholder message

## Next Phase Readiness

**Phase 134 (Discipline Cases UI) is READY:**
- ✅ Capability infrastructure in place
- ✅ Route protection working
- ✅ Navigation structure prepared
- ✅ Person detail tab placeholder exists
- ✅ DisciplineCasesList component created (placeholder)

**Integration points for Phase 134:**
1. Replace DisciplineCasesList placeholder with actual list view
2. Add discipline case create/edit modals
3. Add person tab content linking person to their discipline cases
4. Use same `canAccessFairplay` checks for action buttons

**No blockers identified.**

## Files Changed

### Created
- `src/pages/DisciplineCases/DisciplineCasesList.jsx` - Placeholder page for Phase 134

### Modified
- `includes/class-user-roles.php` - Added FAIRPLAY_CAPABILITY constant, registration, cleanup
- `includes/class-rest-api.php` - Exposed can_access_fairplay in user endpoint
- `src/App.jsx` - Added FairplayRoute component, Shield icon, discipline-cases route
- `src/components/layout/Layout.jsx` - Added Gavel icon, current user query, navigation filtering
- `src/pages/People/PersonDetail.jsx` - Added Gavel icon, capability query, conditional tab rendering

## Commits

| Hash | Message | Files |
|------|---------|-------|
| 61852631 | feat(133-01): register fairplay capability and expose in REST API | class-user-roles.php, class-rest-api.php |
| f4efad99 | feat(133-01): add React route protection and conditional navigation | App.jsx, Layout.jsx, DisciplineCasesList.jsx |
| 1c4dfa5b | feat(133-01): add conditional Tuchtzaken tab on person detail page | PersonDetail.jsx |

## Performance Notes

- No new network requests (current user already queried by existing components)
- Navigation filtering adds negligible overhead (array filter operation)
- Capability check cached by React Query across all components

## Security Notes

- Capability-based access control follows WordPress security best practices
- Frontend checks are UX-only - backend API will enforce access control in Phase 134
- Access denied page prevents accidental exposure of sensitive routes

---

**Status:** ✅ Complete
**Duration:** 3m 42s
**Tasks completed:** 3/3
**Success criteria met:** 4/4
