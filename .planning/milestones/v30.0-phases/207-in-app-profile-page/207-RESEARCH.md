# Phase 207: In-App Profile Page — Research

**Researched:** 2026-02-20
**Domain:** WordPress REST API password management + React SPA profile page
**Confidence:** HIGH

---

## Summary

Phase 207 adds a Profile page to the React SPA so users can view their linked Sportlink identity (PROF-04) and change their password (PROF-01 through PROF-03) without ever visiting wp-login.php. This is straightforward given the existing infrastructure: the `/rondo/v1/user/me` endpoint already exists in `class-rest-api.php`, the `rondo_linked_person_id` user meta already links WP users to person posts, and the `work_history` repeater already stores Functies with `is_current` flags.

The backend work is a single new POST endpoint `POST /rondo/v1/user/password` added to the existing `class-rest-api.php`. It verifies the current password with `wp_check_password()`, changes it with `wp_set_password()`, destroys all sessions with `WP_Session_Tokens::get_instance()->destroy_all()`, and returns a 200 success — the nonce is then dead and any subsequent REST call by the React app will 401, which the existing 401 interceptor in `api/client.js` handles by redirecting to the WP login page.

The frontend work is a new `Profile.jsx` page, a `useChangePassword` mutation hook, an expansion of `GET /rondo/v1/user/me` to include `linked_person_name` and `active_functies`, and a sidebar link plus router entry.

**Primary recommendation:** Add the password endpoint to existing `class-rest-api.php`, expand `get_current_user()` in place, create `src/pages/Profile/Profile.jsx` as a standalone page (no tabs needed), and hook it into the sidebar alongside the existing logout link.

---

## Standard Stack

### Core
| Library/API | Version | Purpose | Why Standard |
|---|---|---|---|
| WordPress `wp_check_password()` | Core | Verifies current password against stored hash | Native, handles bcrypt/phpass transparently |
| WordPress `wp_set_password()` | Core | Updates password hash in DB | Native; handles all hashing |
| WordPress `WP_Session_Tokens` | Core | Destroys all user sessions | Native session invalidation class |
| TanStack Query `useMutation` | v5 (already installed) | Password change mutation + loading/error state | Already the project standard for mutations |
| `useCurrentUser` hook | existing | Reads linked person for PROF-04 display | Already deduplicated across components |

### Supporting
| Library | Version | Purpose | When to Use |
|---|---|---|---|
| `wp_update_user()` | Core | Alternative to `wp_set_password` (wraps it) | `wp_set_password()` is simpler for password-only changes |
| Lucide React (`Lock`, `User`) | already installed | Icons for profile form | Already the icon library used everywhere |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|---|---|---|
| Adding to `class-rest-api.php` | New `class-rest-user-password.php` | Only one endpoint — not enough to justify a new class file. Consistent with the existing user endpoints in `class-rest-api.php`. |
| Expanding `GET /user/me` response | Separate `/user/me/profile` endpoint | `GET /user/me` is already the canonical user data source; adding two fields is zero-cost and reuses existing caching. |
| `window.location.href = loginUrl` for redirect | React Router redirect | After password change, session is dead; hard redirect to WP login is the right behavior (not an SPA route transition). |

---

## Architecture Patterns

### Backend — Existing Pattern for User Endpoints

All user-related endpoints live in `class-rest-api.php` under the `Rondo\REST\Api` class, registered in `register_routes()`. The file is large (~4300 lines) but well-organized. The existing `/user/me` endpoint at line 351 and `get_current_user()` handler at line 2668 are the direct neighbours.

**Pattern for new endpoint registration (from existing code):**
```php
// Source: includes/class-rest-api.php lines 347-359
register_rest_route(
    'rondo/v1',
    '/user/me',
    [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_current_user' ],
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]
);
```

The new endpoint follows this exact pattern with `CREATABLE` (POST):

```php
register_rest_route(
    'rondo/v1',
    '/user/password',
    [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'change_password' ],
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => [
            'current_password' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => function( $v ) { return $v; }, // Never sanitize passwords
            ],
            'new_password' => [
                'required'          => true,
                'type'              => 'string',
            ],
        ],
    ]
);
```

**IMPORTANT — never sanitize passwords.** Sanitization strips characters that are valid in passwords. Use the raw value directly.

### Backend — Password Change Handler

WordPress native flow for in-app password change:

```php
// Source: WordPress core — verified pattern
public function change_password( $request ) {
    $user_id          = get_current_user_id();
    $current_password = $request->get_param( 'current_password' );
    $new_password     = $request->get_param( 'new_password' );

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return new \WP_Error( 'not_logged_in', 'Niet ingelogd.', [ 'status' => 401 ] );
    }

    // 1. Verify current password (PROF-02)
    if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
        return new \WP_Error(
            'wrong_password',
            'Huidig wachtwoord is onjuist.',
            [ 'status' => 400 ]
        );
    }

    // 2. Change password
    wp_set_password( $new_password, $user_id );

    // 3. Destroy all sessions (PROF-03) — this invalidates the current nonce too
    $sessions = \WP_Session_Tokens::get_instance( $user_id );
    $sessions->destroy_all();

    return rest_ensure_response( [
        'success' => true,
        'message' => 'Wachtwoord succesvol gewijzigd. Log opnieuw in.',
    ] );
}
```

After `destroy_all()`, any subsequent REST request with the old nonce will return 403 (nonce invalid) or behave as unauthenticated. The React `api/client.js` interceptor already handles 401 → redirect to login. The front-end should explicitly redirect to `loginUrl` after success rather than waiting for the 401.

### Backend — Expanding `GET /user/me`

The existing `get_current_user()` handler (line 2668) returns `id`, `name`, `email`, `avatar_url`, `is_admin`, `can_access_*`, `profile_url`, `admin_url`. For PROF-04 we need to add `linked_person_name` and `active_functies`.

**Data already available via user meta:** `rondo_linked_person_id` (integer). From the person post, `get_field('first_name')`, `get_field('last_name')`, and `get_field('work_history')` (repeater with `job_title` and `is_current` fields) are already used in other endpoints.

```php
// Add to get_current_user() return array:
$person_id       = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
$linked_person_name = null;
$active_functies    = [];

if ( $person_id ) {
    $person = get_post( $person_id );
    if ( $person && 'person' === $person->post_type ) {
        $first = get_field( 'first_name', $person_id ) ?: '';
        $last  = get_field( 'last_name', $person_id ) ?: '';
        $linked_person_name = trim( "$first $last" ) ?: null;

        $work_history = get_field( 'work_history', $person_id ) ?: [];
        foreach ( $work_history as $job ) {
            if ( ! empty( $job['is_current'] ) && ! empty( $job['job_title'] ) ) {
                $active_functies[] = $job['job_title'];
            }
        }
    }
}
// Then add to the response array:
// 'linked_person_name' => $linked_person_name,
// 'active_functies'    => $active_functies,
```

**Note:** `useCurrentUser` has `staleTime: 5 * 60 * 1000` — the new fields will be available on next cache refresh or after `queryClient.invalidateQueries(['current-user'])`.

### Frontend — Page Structure

New page: `src/pages/Profile/Profile.jsx`. Single-file page, no tabs (the profile is simple enough). Pattern matches Login.jsx (simple standalone page) rather than Settings.jsx (tabbed).

```
src/pages/Profile/
└── Profile.jsx      # Standalone profile + password change page
```

**Hook:** New `useChangePassword` mutation in `src/hooks/useCurrentUser.js` (extend existing file):

```js
// Source: established project pattern from useFeedback.js
export function useChangePassword() {
  return useMutation({
    mutationFn: ({ currentPassword, newPassword }) =>
      prmApi.changePassword({ current_password: currentPassword, new_password: newPassword }),
    // No cache invalidation needed — session destroyed on success
  });
}
```

**API client entry (add to `prmApi` in `src/api/client.js`):**
```js
changePassword: (data) => api.post('/rondo/v1/user/password', data),
```

**Sidebar link:** Add a `{ name: 'Profiel', href: '/profile', icon: User }` entry to the `navigation` array in `Layout.jsx`. The `User` icon is already imported. It should appear between `Instellingen` and the logout button (or at the bottom of the nav, above logout).

**Router entry (add to `router.jsx`):**
```js
{ path: 'profile', element: <Profile /> },
```

**UserMenu update:** The existing `UserMenu` component in `Layout.jsx` currently links to `user.profile_url` (wp-admin profile). For non-admin, non-demo users, replace the external link with a `<Link to="/profile">` internal navigation link. For admins, keep both (in-app profile + wp-admin link).

### Anti-Patterns to Avoid
- **Sanitizing password inputs on the server:** `sanitize_text_field()` strips special characters from passwords. Use the raw request value.
- **Using `wp_update_user()` without session destruction:** `wp_set_password()` alone does not kill existing sessions. `WP_Session_Tokens::get_instance()->destroy_all()` is mandatory for PROF-03.
- **Redirecting with React Router after password change:** The session is dead; use `window.location.href = config.loginUrl` (hard redirect), not `navigate()`.
- **Fetching linked person data separately in the Profile component:** The `useCurrentUser` hook already has the data after the `GET /user/me` expansion — no separate person fetch needed. Same pattern as AccountCard in Phase 205.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---|---|---|---|
| Password verification | Custom hash comparison | `wp_check_password()` | WordPress handles bcrypt/phpass upgrade, pepper, etc. |
| Session destruction | Clearing cookies manually | `WP_Session_Tokens::get_instance($uid)->destroy_all()` | Only this method clears all active sessions, including those from other browsers |
| Password hashing | Custom hashing | `wp_set_password()` | Handles WordPress password hash format + triggers `password_change` action |

---

## Common Pitfalls

### Pitfall 1: Sessions Not Fully Invalidated
**What goes wrong:** `wp_set_password()` is called but `destroy_all()` is not. The old session token (baked into the REST nonce) remains valid briefly. The user sees the success message but the app doesn't redirect to login.
**Why it happens:** Developers assume password change invalidates session automatically.
**How to avoid:** Always call `WP_Session_Tokens::get_instance($user_id)->destroy_all()` immediately after `wp_set_password()`.
**Warning signs:** After password change, user stays logged in; old nonce still works.

### Pitfall 2: Demo User Can Change Password
**What goes wrong:** The demo user's password change succeeds in the API but is silently reverted by `DemoProtection::protect_demo_user_data` (the `wp_pre_insert_user_data` filter). The API returns success but the password didn't actually change.
**Why it happens:** `DemoProtection` restores the original `user_pass` on every `wp_insert_user()` call, which is what `wp_set_password()` ultimately triggers.
**How to avoid:** Add an early check in `change_password()`: if `window.rondoConfig.isDemoUser` is true (frontend) or the user's login is `demo` (backend), return a WP_Error. The Profile page should also be hidden/disabled for demo users.
**Warning signs:** API returns success but user can still log in with old password.

### Pitfall 3: `useCurrentUser` Cache Staleness for PROF-04 Display
**What goes wrong:** `active_functies` and `linked_person_name` are added to `GET /user/me` but the cache (`staleTime: 5 min`) returns the old response without these fields until it expires.
**Why it happens:** TanStack Query caches are invalidated only when explicitly triggered or when staleTime expires.
**How to avoid:** This is acceptable behavior since the profile data doesn't change mid-session. The fields will be present on next app load. No action needed — just document it.

### Pitfall 4: Nonce Expiry Before Redirect
**What goes wrong:** After `destroy_all()`, the React app tries to `invalidateQueries()` or do any API call before redirecting. These calls fail with 403, triggering error UI instead of a clean redirect.
**How to avoid:** After `onSuccess` in the mutation, immediately call `window.location.href = window.rondoConfig.loginUrl` without any intermediate API calls. Do not call `queryClient.invalidateQueries()` in `onSuccess` — the session is dead.

### Pitfall 5: Password Form Validation on Frontend
**What goes wrong:** Showing "incorrect password" errors from the server while the new password field has focus leaks which field is wrong.
**How to avoid:** Show generic "Wachtwoord wijzigen mislukt" on 400. The specific server message (huidig wachtwoord onjuist) is in `error.response.data.message` — display it clearly, but on the current_password field specifically.

---

## Code Examples

### Session Token Destruction (HIGH confidence — WordPress core)
```php
// Destroy all sessions for a user after password change
$sessions = \WP_Session_Tokens::get_instance( $user_id );
$sessions->destroy_all();
```

### Password Verification (HIGH confidence — WordPress core)
```php
$user = get_userdata( $user_id );
$is_valid = wp_check_password( $current_password, $user->user_pass, $user_id );
```

### Frontend Redirect After Password Change (existing pattern from api/client.js)
```js
// Source: src/api/client.js response interceptor
if (error.response?.status === 401) {
    window.location.href = config.loginUrl || '/wp-login.php';
}

// Pattern for post-success redirect in onSuccess:
onSuccess: () => {
    window.location.href = window.rondoConfig?.loginUrl || '/wp-login.php';
}
```

### TanStack Query mutation pattern (from useFeedback.js)
```js
export function useChangePassword() {
    return useMutation({
        mutationFn: ({ currentPassword, newPassword }) =>
            prmApi.changePassword({
                current_password: currentPassword,
                new_password: newPassword,
            }),
    });
}
```

### Active Functies extraction from work_history
```php
// work_history is an ACF repeater with sub-fields:
// team (post_object), entity_type (text), job_title (text),
// start_date (date_picker), end_date (date_picker), is_current (true_false)
$work_history = get_field( 'work_history', $person_id ) ?: [];
$active_functies = [];
foreach ( $work_history as $job ) {
    if ( ! empty( $job['is_current'] ) && ! empty( $job['job_title'] ) ) {
        $active_functies[] = $job['job_title'];
    }
}
```

---

## Implementation Checklist for 207-01

All requirements fit into one plan. The single plan `207-01` should:

1. **Backend — `class-rest-api.php`:**
   - Register `POST /rondo/v1/user/password` in `register_routes()` (near line 359)
   - Add `change_password()` callback method
   - Expand `get_current_user()` to include `linked_person_name` and `active_functies`
   - Demo user guard in `change_password()` (check `user_login === 'demo'`)

2. **Frontend — `src/api/client.js`:**
   - Add `changePassword: (data) => api.post('/rondo/v1/user/password', data)` to `prmApi`

3. **Frontend — `src/hooks/useCurrentUser.js`:**
   - Add `useChangePassword` mutation

4. **Frontend — `src/pages/Profile/Profile.jsx`:**
   - Create page component
   - Show `linked_person_name` + `active_functies` from `useCurrentUser()` (no separate fetch)
   - Password change form with current/new password fields
   - On success: `window.location.href = loginUrl`

5. **Frontend — `src/lazyPages.js`:**
   - Add `export const Profile = lazy(() => import('@/pages/Profile/Profile'))`

6. **Frontend — `src/router.jsx`:**
   - Import `Profile` from `./lazyPages`
   - Add `{ path: 'profile', element: <Profile /> }` inside `ProtectedLayout` children

7. **Frontend — `src/components/layout/Layout.jsx`:**
   - Add `{ name: 'Profiel', href: '/profile', icon: User }` to `navigation` array
   - Update `UserMenu` "Profiel bewerken" link to use `<Link to="/profile">` instead of external `href`
   - Add `'Profiel'` case to `getPageTitle()` in `Header`

8. **Version bump:** 29.3.0 → 29.4.0 (MINOR — new feature)

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|---|---|---|---|
| External wp-admin profile.php | In-app profile page | This phase | Users never leave the SPA |
| GET /user/me returns minimal data | GET /user/me returns linked person + functies | This phase | Enables PROF-04 without extra fetch |

**Existing behavior to preserve:**
- `UserMenu` currently links `profile_url` → `user.profile_url` (admin profile.php). After this phase, non-admin non-demo users should use the in-app link instead. Admin users can still have both the in-app profile and the WP admin link.

---

## Open Questions

1. **Password minimum length/strength validation**
   - What we know: The phase requirements don't mention a minimum length or strength rule.
   - What's unclear: Should we enforce a minimum (e.g., 8 characters) on the frontend, or leave it to WordPress defaults?
   - Recommendation: Add a client-side minimum of 8 characters (standard security practice) and show a helpful message. No backend validation needed beyond WordPress's own behavior.

2. **Sidebar placement of Profile link**
   - What we know: Current sidebar has: Dashboard, Leden, (VOG, Tuchtzaken), Teams, Commissies, (Financiën section), Taken, Feedback, Instellingen. Logout is at the bottom.
   - What's unclear: Should Profile go in the main nav or near the logout button?
   - Recommendation: Add it to the bottom of the main `navigation` array (just before Instellingen is reasonable), or as a separate item near the logout. Given it's personal/account-level, placing it just above the logout button in its own `<div>` makes semantic sense. Final choice for planner.

3. **UserMenu "Profiel bewerken" link for admins**
   - What we know: `UserMenu` currently shows "Profiel bewerken" → `user.profile_url` (external) for non-demo users.
   - What's unclear: Should admins get both the in-app profile link and the WP admin link, or just the in-app one?
   - Recommendation: Non-admin users → in-app `/profile` link. Admin users → keep both (in-app profile + WP admin profile). The existing `user.is_admin` check can guide this.

---

## Sources

### Primary (HIGH confidence)
- WordPress source code (read via codebase) — `wp_check_password()`, `wp_set_password()`, `WP_Session_Tokens` usage patterns
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` — existing `/user/me` endpoint and `get_current_user()` implementation (lines 347-359, 2668-2706)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-user-provisioning.php` — UserProvisioning service (pure service, no hooks pattern)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-demo-protection.php` — `wp_pre_insert_user_data` filter for demo protection
- `/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_person_fields.json` — `work_history` repeater schema (team, job_title, is_current, etc.)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/hooks/useCurrentUser.js` — existing hook pattern
- `/Users/joostdevalk/Code/rondo/rondo-club/src/api/client.js` — `prmApi` pattern + 401 redirect interceptor
- `/Users/joostdevalk/Code/rondo/rondo-club/src/components/layout/Layout.jsx` — navigation array, UserMenu, sidebar structure
- `/Users/joostdevalk/Code/rondo/rondo-club/src/router.jsx` — route registration pattern

### Secondary (MEDIUM confidence)
- WordPress Codex — `WP_Session_Tokens::destroy_all()` documented in core; confirmed by reading demo-protection.php and access-control.php patterns

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already installed, WordPress core functions documented
- Architecture: HIGH — read existing code; patterns are clear and consistent
- Pitfalls: HIGH — confirmed from reading DemoProtection, existing interceptor, and TanStack Query hook patterns

**Research date:** 2026-02-20
**Valid until:** 2026-03-20 (stable tech, 30-day window)
