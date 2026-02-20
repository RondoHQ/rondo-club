# Architecture Research

**Domain:** User Accounts & Profiles — WordPress theme + React SPA integration
**Researched:** 2026-02-20
**Confidence:** HIGH (sourced from reading existing codebase)

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                      rondo-sync (Node.js CLI)                        │
│  Sportlink → download-functions-from-sportlink.js (SQLite staging)  │
│           → [NEW] submit-capabilities-to-rondo-club.js              │
│           → submit-rondo-club-sync.js (person upsert, sets email)   │
└──────────────────────────────┬──────────────────────────────────────┘
                               │ REST API (Basic Auth, Application Password)
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   Rondo Club (WordPress + PHP)                        │
│                                                                       │
│  functions.php → rondo_init() conditional class loading              │
│                                                                       │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────────┐  │
│  │  class-rest-api  │  │ class-user-roles │  │class-access-ctrl │  │
│  │  /rondo/v1/      │  │ role + caps mgmt │  │ per-request check│  │
│  │  [NEW] /users/   │  │ [MOD] cap sync   │  │                  │  │
│  │   provision      │  │                  │  │                  │  │
│  │  [MOD] /user/me  │  │                  │  │                  │  │
│  │  [NEW] /user/    │  │                  │  │                  │  │
│  │   password       │  │                  │  │                  │  │
│  └────────┬─────────┘  └──────────────────┘  └───────────────────┘  │
│           │                                                           │
│  ┌────────▼──────────────────────────────────────────────────────┐   │
│  │            WordPress User Meta (user_meta table)               │   │
│  │  rondo_linked_person_id (int)  — EXISTING: user ↔ person link │   │
│  │  rondo_knvb_id (string)        — NEW: KNVB ID for sync lookup │   │
│  │  rondo_functies (JSON string)  — NEW: last-known Functies      │   │
│  │  rondo_notification_channels   — existing                     │   │
│  │  rondo_people_list_preferences — existing                     │   │
│  └───────────────────────────────────────────────────────────────┘   │
│                                                                       │
│  WordPress Options API (options table)                                │
│  rondo_functie_capability_map (JSON) — NEW: Functie→role config      │
│  rondo_functie_auto_provision (bool) — NEW: auto-create users        │
└──────────────────────────────┬──────────────────────────────────────┘
                               │ REST API (nonce auth)
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     React SPA (Vite + React 18)                      │
│                                                                       │
│  useCurrentUser() → /rondo/v1/user/me                               │
│  [MOD] returns: linked person thumbnail, functies[]                  │
│                                                                       │
│  [NEW] Profile page → /profile                                       │
│    - Display name, email from WP user record                         │
│    - Password change form → POST /rondo/v1/user/password             │
│    - Avatar: linked person thumbnail or Gravatar fallback            │
│                                                                       │
│  Layout.jsx Sidebar → profile link in bottom user area               │
│  router.jsx → [NEW] /profile route (all authenticated users)         │
└─────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | New or Modified |
|-----------|----------------|-----------------|
| `class-user-roles.php` | Role registration, capability definitions | MODIFIED: add capability sync helper |
| `class-rest-api.php` | REST endpoints for user/me, users CRUD, user settings | MODIFIED: provision endpoint, expanded /user/me, password endpoint, capability map endpoints |
| `class-user-provisioning.php` | Create WP user from person record, link to person, send welcome email | NEW |
| `class-functie-capability-map.php` | Config storage and apply/sync Functie→role mappings | NEW |
| `functions.php` | WP admin redirect blocking, class loading | MODIFIED: add admin_init redirect hook, load new classes |
| `rondo-sync` submit step | Push Functies to Rondo Club provisioning endpoint | NEW STEP |
| `src/pages/Profile/index.jsx` | In-app profile page, password change form | NEW |
| `src/hooks/useProfile.js` | TanStack Query hooks for profile reads + password mutation | NEW |
| `src/hooks/useCurrentUser.js` | Fetch current user (already exists) | MODIFIED: include linked person thumbnail |
| `src/api/client.js` | API helper methods | MODIFIED: add profile/password/functie-map endpoints |
| `src/router.jsx` | Route definitions | MODIFIED: add /profile route |
| `src/components/layout/Layout.jsx` | Sidebar navigation | MODIFIED: add profile link to bottom user area |

## Recommended Project Structure

New files to create:

```
includes/
├── class-user-provisioning.php      # NEW: WP user creation from person record + welcome email
├── class-functie-capability-map.php # NEW: Functie→role config (Options API) + sync logic

src/pages/
└── Profile/
    └── index.jsx                    # NEW: Profile page (display info + password change)

src/hooks/
└── useProfile.js                    # NEW: profile data fetch + password change mutation
```

Modified files:

```
includes/
├── class-user-roles.php             # MOD: add sync_user_capabilities() helper
├── class-rest-api.php               # MOD: provision, /user/me expansion, /user/password,
│                                    #      functie-capability-map endpoints

functions.php                        # MOD: admin_init redirect for non-admins, load new classes

src/
├── router.jsx                       # MOD: /profile route under ProtectedLayout
├── lazyPages.js                     # MOD: export Profile lazy component
├── api/client.js                    # MOD: prmApi.updatePassword(), capability map methods
├── components/layout/Layout.jsx     # MOD: profile link in sidebar bottom section
```

### Structure Rationale

- **`class-user-provisioning.php` separate from `class-user-roles.php`:** Provisioning is a one-time event (create user, send email). Role management is ongoing infrastructure. Keeps SRP. Load conditionally on REST requests only.
- **`class-functie-capability-map.php` separate from `class-user-roles.php`:** Config concerns (what Functie maps to what role) are separate from infrastructure concerns (what roles exist). Admin can tune the mapping without touching role code.
- **Profile as standalone route `/profile`, not inside `/settings`:** Settings is admin-heavy with multiple tabs. Profile is personal and should be discoverable without navigating a settings tree. All authenticated users need it.

## Architectural Patterns

### Pattern 1: Extend class-rest-api.php for new user endpoints

**What:** Add new REST routes to the existing `Api` class (which already extends `Base`). `Base` provides `check_user_approved()`, `check_admin_permission()`, and common response helpers. All new user endpoints follow this pattern.

**When to use:** For all new `/rondo/v1/` endpoints. This is the established convention for every domain REST class in the codebase.

**Trade-offs:** `class-rest-api.php` is already large (~4440 lines). However, user endpoints are closely related to the existing `/rondo/v1/user/me`, `/rondo/v1/user/linked-person`, and `/rondo/v1/users` endpoints already in that file. The actual provisioning logic lives in `class-user-provisioning.php`, so the REST callbacks are thin wrappers.

**Example:**
```php
// In class-rest-api.php register_routes():
register_rest_route('rondo/v1', '/users/provision', [
    'methods'             => WP_REST_Server::CREATABLE,
    'callback'            => [ $this, 'provision_user' ],
    'permission_callback' => [ $this, 'check_admin_permission' ],
    'args'                => [
        'person_id' => [ 'required' => true, 'type' => 'integer' ],
        'email'     => [ 'required' => true, 'type' => 'string'  ],
        'knvb_id'   => [ 'required' => false, 'type' => 'string' ],
        'functies'  => [ 'required' => false, 'type' => 'array'  ],
    ],
]);

public function provision_user( $request ) {
    $provisioner = new \Rondo\Core\UserProvisioning();
    return rest_ensure_response(
        $provisioner->provision(
            $request->get_param('person_id'),
            $request->get_param('email'),
            $request->get_param('knvb_id') ?? '',
            $request->get_param('functies') ?? []
        )
    );
}
```

### Pattern 2: WordPress Options API for Functie→capability mapping

**What:** Store the admin-configurable Functie→role mapping as a single WordPress option (`rondo_functie_capability_map`) containing a JSON object. Follow the pattern established by `ClubConfig` and `FinanceConfig`.

**When to use:** Site-wide configuration that the admin sets once and the system reads on every provisioning call. Options API has built-in object caching via `get_option()` autoload, so no custom caching is needed.

**Trade-offs:** JSON in one option is simpler than one option per Functie. Easy to export, reset to defaults, and version. Downside: no per-entry validation — validate at REST API write time.

**Example:**
```php
// class-functie-capability-map.php
class FunctieCapabilityMap {
    const OPTION_MAP           = 'rondo_functie_capability_map';
    const OPTION_AUTO_PROVISION = 'rondo_functie_auto_provision';

    // Stored as: { "Bestuurslid": "rondo_bestuur", "Leider": "rondo_fairplay" }
    public function get_map(): array {
        return get_option( self::OPTION_MAP, [] );
    }

    // Returns WP role slug for an array of Functies, or null if no mapping found.
    // Priority: highest-privilege role wins when multiple Functies map to different roles.
    public function get_role_for_functies( array $functies ): ?string {
        $map = $this->get_map();
        $role_priority = [
            'rondo_bestuur'  => 4,
            'rondo_fairplay' => 3,
            'rondo_vog'      => 2,
            'rondo_user'     => 1,
        ];
        $best_role     = null;
        $best_priority = 0;
        foreach ( $functies as $functie ) {
            $role = $map[ $functie ] ?? null;
            if ( $role && ( $role_priority[ $role ] ?? 0 ) > $best_priority ) {
                $best_role     = $role;
                $best_priority = $role_priority[ $role ];
            }
        }
        return $best_role;
    }
}
```

### Pattern 3: User meta for user-to-person linkage (already established)

**What:** `rondo_linked_person_id` user meta already links a WP user to a person post. Auto-set this during provisioning from the `person_id` passed in the request. The REST endpoint `GET /rondo/v1/user/linked-person` already reads and returns this. The existing `GET /rondo/v1/user/me` response can then read the linked person's thumbnail.

**When to use:** Provisioning must call `update_user_meta( $user_id, 'rondo_linked_person_id', $person_id )` immediately after creating the user. This makes avatar resolution, profile display, and meeting attendee filtering work with zero additional code.

**Example:**
```php
// In UserProvisioning::provision():
$user_id = wp_insert_user( $userdata );
if ( ! is_wp_error( $user_id ) ) {
    update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
    update_user_meta( $user_id, 'rondo_knvb_id', $knvb_id );
    update_user_meta( $user_id, 'rondo_functies', wp_json_encode( $functies ) );
}
```

### Pattern 4: WP admin blocking via admin_init hook

**What:** Hook into `admin_init` to redirect non-admin users who try to reach `/wp-admin/`. WordPress fires `admin_init` on every admin page load. If the user lacks `manage_options`, redirect to `home_url()` and exit.

**Critical:** Only block the HTTP request to `/wp-admin/`. REST API requests have `is_admin()` = false during execution — the `admin_init` hook never fires during REST calls. AJAX requests must also be exempted (`DOING_AJAX`).

**Example:**
```php
// In functions.php (or called from rondo_init):
add_action( 'admin_init', function() {
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_redirect( home_url() );
        exit;
    }
} );
```

### Pattern 5: Password change via custom REST endpoint

**What:** New `POST /rondo/v1/user/password` endpoint. Accepts `current_password` and `new_password`. Verifies current password with `wp_check_password()`, then calls `wp_set_password()`. Returns 200 on success, 400 on wrong current password.

**Why not `POST /wp/v2/users/me`:** WordPress's native user REST endpoint requires `edit_users` capability to change passwords. `rondo_user` role does not have `edit_users`. A custom endpoint with `check_user_approved()` permission is required.

**Example:**
```php
register_rest_route('rondo/v1', '/user/password', [
    'methods'             => WP_REST_Server::CREATABLE,
    'callback'            => [ $this, 'update_password' ],
    'permission_callback' => 'is_user_logged_in',
    'args'                => [
        'current_password' => [ 'required' => true, 'type' => 'string' ],
        'new_password'     => [ 'required' => true, 'type' => 'string', 'minLength' => 8 ],
    ],
]);

public function update_password( $request ) {
    $user = get_userdata( get_current_user_id() );
    if ( ! wp_check_password( $request['current_password'], $user->user_pass, $user->ID ) ) {
        return new \WP_Error( 'wrong_password', 'Huidig wachtwoord klopt niet.', [ 'status' => 400 ] );
    }
    wp_set_password( $request['new_password'], $user->ID );
    return rest_ensure_response( [ 'success' => true ] );
}
```

## Data Flow

### Provisioning Flow (rondo-sync triggers)

```
Sportlink Functies sync (download-functions-from-sportlink.js)
    ↓ stores in SQLite: sportlink_member_functions table
    ↓ (knvb_id, function_description, is_active)
    ↓
[NEW] submit-capabilities-to-rondo-club.js
    ↓ For each member with email in rondo-club-db:
    ↓ POST /rondo/v1/users/provision
    ↓ { person_id, email, knvb_id, functies: ["Bestuurslid", ...] }
    ↓
class-rest-api.php → provision_user()
    ↓
class-user-provisioning.php → provision()
    ├── email_exists( $email )?
    │   YES → get user, skip creation, go to capability sync
    │   NO  → wp_insert_user({ user_login: email, user_email: email,
    │            display_name: first_name + last_name, role: 'rondo_user' })
    ├── update_user_meta(user_id, 'rondo_linked_person_id', person_id)
    ├── update_user_meta(user_id, 'rondo_knvb_id', knvb_id)
    ├── update_user_meta(user_id, 'rondo_functies', json_encode(functies))
    ├── class-functie-capability-map → get_role_for_functies(functies)
    │   → returns: 'rondo_bestuur' | 'rondo_fairplay' | 'rondo_vog' | 'rondo_user' | null
    ├── wp_update_user([ 'ID' => user_id, 'role' => $role ])
    └── If new user: wp_mail() welcome email with login link
        ↓
REST response: { user_id, created: true|false, role: "rondo_bestuur" }
```

### Capability Sync Flow (on-demand or triggered by admin)

```
functies[] (e.g. ["Bestuurslid", "Penningmeester"])
    ↓
FunctieCapabilityMap::get_role_for_functies(functies)
    ↓ reads rondo_functie_capability_map option
    ↓ highest-privilege role wins
    ↓
wp_update_user([ 'ID' => user_id, 'role' => $role_slug ])
    ↓
update_user_meta(user_id, 'rondo_functies', json_encode($functies))
    ↓ (stored for profile display + next sync comparison)

Admin can also trigger "sync all" via:
POST /rondo/v1/users/sync-capabilities (admin only)
→ loops all WP users with rondo_knvb_id meta
→ re-reads stored rondo_functies and re-applies role from config
```

### Profile Page Data Flow (React)

```
User navigates to /profile
    ↓
Profile page mounts → useCurrentUser() (already cached from layout)
    ↓ { id, name, email, avatar_url, is_admin, can_access_*,
    ↓   linked_person_thumbnail, functies[] }     ← MOD: new fields
    ↓
Avatar resolution:
    linked_person_thumbnail ?? avatar_url (Gravatar fallback)
    ↓
Profile renders: name, email, avatar, functies list
    ↓
Password change form submits:
    { current_password, new_password }
    ↓ POST /rondo/v1/user/password
    ↓ success → toast "Wachtwoord gewijzigd", clear form
    ↓ 400 → inline error "Huidig wachtwoord klopt niet"
```

### Admin Capability Map Config Flow

```
Admin opens Settings → Gebruikers tab (or dedicated sub-section)
    ↓
GET /rondo/v1/functie-capability-map (admin only)
    → { map: { "Bestuurslid": "rondo_bestuur" }, available_functies: [...] }
    ↓
Admin sets: Functie X → role Y via dropdown
    ↓ POST /rondo/v1/functie-capability-map
    ↓ saves to WordPress Options
    ↓
Admin clicks "Synchroniseer rollen"
    ↓ POST /rondo/v1/users/sync-capabilities
    → loops all rondo users, re-applies roles
```

### Key Data Flows Summary

1. **Provisioning:** rondo-sync posts to REST → UserProvisioning creates/updates WP user, links to person, applies role from Functie mapping, sends welcome email
2. **Capability sync:** REST endpoint reads stored functies from user meta, applies FunctieCapabilityMap config to determine WP role
3. **Avatar in profile:** `GET /rondo/v1/user/me` returns linked person's thumbnail; React falls back to Gravatar if no linked person
4. **Admin blocking:** `admin_init` hook checks `manage_options`; non-admins redirected to `home_url()`
5. **Password change:** `POST /rondo/v1/user/password` verifies current password before setting new one

## New vs Modified — Explicit Breakdown

### NEW PHP classes (create in `includes/`)

| File | Class | Namespace | Loaded When |
|------|-------|-----------|-------------|
| `class-user-provisioning.php` | `UserProvisioning` | `Rondo\Core` | REST requests only (`$is_rest`) |
| `class-functie-capability-map.php` | `FunctieCapabilityMap` | `Rondo\Config` | REST + admin (`$is_rest || $is_admin`) |

### MODIFIED PHP

| File | What Changes |
|------|-------------|
| `functions.php` | (1) Add `admin_init` hook to redirect non-admins from wp-admin. (2) Load `UserProvisioning` and `FunctieCapabilityMap` in `rondo_init()`. (3) Add class aliases for backward compat. |
| `class-rest-api.php` | (1) `POST /rondo/v1/users/provision`. (2) `POST /rondo/v1/user/password`. (3) `GET/POST /rondo/v1/functie-capability-map` (admin). (4) `POST /rondo/v1/users/sync-capabilities` (admin). (5) Expand `get_current_user()` to include `linked_person_thumbnail` and `functies`. |
| `class-user-roles.php` | Add optional `sync_user_capabilities( int $user_id, array $functies )` static helper (delegates to FunctieCapabilityMap). |

### NEW React files

| File | Purpose |
|------|---------|
| `src/pages/Profile/index.jsx` | Profile page: avatar, display info, password change form |
| `src/hooks/useProfile.js` | TanStack Query hooks: profile data read, password mutation |

### MODIFIED React files

| File | What Changes |
|------|-------------|
| `src/router.jsx` | Add `{ path: 'profile', element: <Profile /> }` under ProtectedLayout |
| `src/lazyPages.js` | Export `Profile` as lazy component |
| `src/api/client.js` | Add `prmApi.updatePassword()`, `prmApi.getFunctieCapabilityMap()`, `prmApi.updateFunctieCapabilityMap()`, `prmApi.syncUserCapabilities()` |
| `src/components/layout/Layout.jsx` | Add profile link to bottom user area in sidebar (next to logout) |

### NEW rondo-sync step

| File | Purpose |
|------|---------|
| `steps/submit-capabilities-to-rondo-club.js` | Reads SQLite `sportlink_member_functions`, POSTs to `/rondo/v1/users/provision` for each member with email |

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Sportlink (via rondo-sync) | Existing Basic Auth REST + new provision step | rondo-sync adds new step after the existing sync, reading already-downloaded Functies from SQLite |
| Lettermint (via WordPress `wp_mail()`) | Existing email delivery plugin | Welcome email uses same pattern as VOG emails and installment emails — `wp_mail()` with HTML content |
| Gravatar | `get_avatar_url()` WordPress function | Already used in existing `get_current_user()` endpoint. Fallback when no linked person photo exists |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| `class-rest-api.php` ↔ `class-user-provisioning.php` | Direct instantiation inside callback | `Api::provision_user()` instantiates `UserProvisioning` and calls `provision()` |
| `class-user-provisioning.php` ↔ `class-functie-capability-map.php` | Direct instantiation | Provisioner creates map instance to resolve the correct role |
| `class-user-provisioning.php` ↔ `class-user-roles.php` | `UserRoles::ROLES` constant | Read to validate that the resolved role slug is one of the 4 Rondo roles |
| `class-user-roles.php` ↔ WordPress DB | `add_role()`, `wp_update_user()` | No change to role infrastructure — purely additive |
| React Profile ↔ PHP REST | `POST /rondo/v1/user/password` via axios with nonce | Follows existing auth pattern |
| rondo-sync ↔ Rondo Club API | Basic Auth via Application Password | New step uses existing `rondoClubRequest()` from `lib/rondo-club-client.js` |

## Data Storage

### New user meta keys

| Key | Type | Set By | Read By |
|-----|------|--------|---------|
| `rondo_knvb_id` | string | `UserProvisioning::provision()` | FunctieCapabilityMap sync endpoint; profile display |
| `rondo_functies` | JSON string | `UserProvisioning::provision()` on create + sync | Profile display; `sync-capabilities` endpoint |

### New WordPress options

| Key | Type | Default | Set By |
|-----|------|---------|--------|
| `rondo_functie_capability_map` | JSON object | `{}` | Admin via `POST /rondo/v1/functie-capability-map` |
| `rondo_functie_auto_provision` | bool | `false` | Admin config — whether to auto-create users during sync |

### Existing data re-used (no schema changes needed)

- `rondo_linked_person_id` user meta — already exists; provisioning sets it automatically during user creation
- Person post thumbnail — `get_the_post_thumbnail_url()` already called in linked-person endpoint; re-used in profile/me response
- `UserRoles::ROLES` constant — defines the 4 valid roles; no new roles needed

## Build Order (Phase Dependencies)

The features have clear dependency chains:

```
Phase 1: WP Admin Blocking
    → No dependencies
    → Simple: add_action('admin_init', ...) in functions.php
    → Must ship first to prevent non-admins accessing wp-admin
      once we start creating WP user accounts at scale

Phase 2: Functie-to-capability config
    → Depends on Phase 1 (admin must be able to reach wp-admin to test)
    → New FunctieCapabilityMap class + REST endpoints
    → Admin configures mapping before provisioning uses it
    → Can include Settings UI tab or standalone admin-only page

Phase 3: User Provisioning REST endpoint + rondo-sync step
    → Depends on Phase 2 (provisioner reads capability map)
    → New UserProvisioning class + POST /rondo/v1/users/provision
    → New submit-capabilities-to-rondo-club.js rondo-sync step
    → Welcome email

Phase 4: In-app Profile page + password change
    → Technically independent of Phase 3 (profile reads /user/me which exists today)
    → But expand /user/me to include linked_person_thumbnail and functies in Phase 3
    → Password change endpoint is fully independent of provisioning
    → Can be built in parallel with Phase 3 or after

Phase 5: Avatar + sidebar profile link
    → Depends on Phase 4 UI + Phase 3 data (/user/me expansion)
    → Reads linked_person_thumbnail from expanded /user/me
    → Adds profile link to Layout.jsx sidebar bottom area
```

## Anti-Patterns

### Anti-Pattern 1: Using wp/v2/users for password changes

**What people do:** Call `PUT /wp/v2/users/{id}` with `{ password: "..." }` from the React profile page.
**Why it's wrong:** Requires `edit_users` capability. The `rondo_user` role does not have `edit_users`. Returns 403 for all non-admin users.
**Do this instead:** Custom `POST /rondo/v1/user/password` endpoint with `is_user_logged_in` permission, verifies current password via `wp_check_password()`, then calls `wp_set_password()`.

### Anti-Pattern 2: Storing Functie→capability mapping in ACF fields

**What people do:** Create an ACF field group for the mapping configuration.
**Why it's wrong:** ACF is for per-post data, not site-wide configuration. The existing pattern for site-wide settings is the WordPress Options API — see `ClubConfig` and `FinanceConfig`. Using ACF here breaks the established convention and adds unnecessary complexity.
**Do this instead:** WordPress Options API with a JSON value, accessed via a `FunctieCapabilityMap` class that mirrors the `ClubConfig` pattern.

### Anti-Pattern 3: Creating new WP roles for each possible Functie

**What people do:** Map "Bestuurslid" → new WP role "rondo_bestuurslid", "Penningmeester" → "rondo_penningmeester", etc.
**Why it's wrong:** The 4 existing Rondo roles (`rondo_user`, `rondo_fairplay`, `rondo_vog`, `rondo_bestuur`) map precisely to the 3 capability gates in the React app (`fairplay`, `vog`, `financieel`). Adding more roles breaks this clean mapping and creates role management chaos. WordPress stores roles globally — too many causes confusion.
**Do this instead:** Map many Functies to one of the 4 existing Rondo roles. "Bestuurslid", "Penningmeester", "Voorzitter" all map to `rondo_bestuur`. The mapping is N Functies → 1 of 4 Rondo roles.

### Anti-Pattern 4: Blocking wp-admin by removing capabilities from rondo_user

**What people do:** Remove `read` or other capabilities from `rondo_user` role to prevent admin access.
**Why it's wrong:** The Rondo React SPA depends on REST API access, which requires authenticated WordPress sessions. The `read` capability is required for WordPress REST auth to work. Removing capabilities breaks REST for non-admin users.
**Do this instead:** Keep all capabilities intact. Block only the HTTP request to `/wp-admin/` via `admin_init` hook with a redirect. REST API requests are unaffected because `is_admin()` is false during REST execution.

### Anti-Pattern 5: Auto-provisioning every synced member as a WP user

**What people do:** Create a WP user for every person record during Sportlink sync.
**Why it's wrong:** Most members don't need Rondo Club access — it's an internal management tool, not a member portal. Hundreds of unnecessary user records, password reset emails flooding inboxes, and confusion about who has access.
**Do this instead:** Only provision users who have a Sportlink Functie that the admin has explicitly mapped to a Rondo role. Gate auto-provisioning behind `rondo_functie_auto_provision` option (default: `false`). The admin decides which Functies imply app access.

### Anti-Pattern 6: Setting user password during provisioning

**What people do:** Generate a temporary password and set it on the WP user during provisioning, then email it.
**Why it's wrong:** Email is not a secure channel. Temporary passwords need to be force-changed. WordPress has a built-in password reset flow that is more secure.
**Do this instead:** Use WordPress's native `wp_new_user_notification()` or `wp_send_new_user_notifications()` which sends a set-password link (not the password itself). Or generate a password reset key via `get_password_reset_key()` and include the reset URL in the welcome email. The user sets their own password on first login.

## Sources

- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-user-roles.php` — roles, capabilities, ROLES constant (HIGH confidence, direct read)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` lines 351-410, 2570-2610, 1734-1820 — existing user endpoints, linked person pattern (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-access-control.php` — permission model (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php` — Options API pattern to follow (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-club-config.php` — simpler Options API pattern (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/functions.php` lines 286-395, 610-655 — class loading, rondoConfig globals (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/hooks/useCurrentUser.js` — hook structure (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/router.jsx` — ProtectedRoute, CapabilityRoute patterns (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/components/layout/Layout.jsx` — sidebar capability filtering (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-sync/steps/download-functions-from-sportlink.js` — Functies data structure and SQLite schema (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-sync/lib/rondo-club-client.js` — REST client pattern for new step (HIGH confidence)
- WordPress `admin_init` redirect pattern — standard WordPress approach for non-admin access control (MEDIUM confidence, training data + consistent with existing codebase usage)

---
*Architecture research for: User Accounts & Profiles — Rondo Club*
*Researched: 2026-02-20*
