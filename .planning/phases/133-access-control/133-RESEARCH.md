# Phase 133: Access Control - Research

**Researched:** 2026-02-03
**Domain:** WordPress capability-based access control with React SPA frontend
**Confidence:** HIGH

## Summary

Capability-based access control in WordPress + React requires three implementation layers: (1) WordPress capability registration during theme activation, (2) REST API enforcement via permission callbacks, and (3) React UI conditional rendering based on capabilities passed via initial page load config.

The existing codebase already implements a similar pattern for the user approval system (`is_approved`) and admin-only features (`is_admin`), providing proven infrastructure to extend for the `fairplay` capability. WordPress capabilities are persistent role-level permissions that automatically inherit to new administrators when using `get_role()->add_cap()`. Capabilities can be exposed to React via the existing `/stadion/v1/user/me` endpoint and `window.stadionConfig` global, then consumed via custom hooks for consistent access control.

**Primary recommendation:** Follow existing patterns - register capability on theme activation, add to `/user/me` response, extend `useAuth` hook with capability check, use conditional rendering for UI elements.

## Standard Stack

The established libraries/tools for WordPress capability-based access control:

### Core WordPress Functions
| Function | Purpose | Why Standard |
|----------|---------|--------------|
| `add_role()` / `get_role()` | Role and capability management | WordPress native role system |
| `add_cap()` | Add capability to role | Persistent database storage in `wp_options` |
| `current_user_can()` | Check user capability | Used in REST API permission callbacks |
| `user_can()` | Check user capability by ID | Static capability checking |
| `register_rest_route()` | REST endpoint registration | WordPress REST API standard |

### React Patterns (Already in Use)
| Pattern | Purpose | Current Usage |
|---------|---------|---------------|
| `window.stadionConfig` | Pass WordPress config to React | Used for `isAdmin`, `isLoggedIn`, user data |
| Custom hook (`useAuth`) | Access authentication state | Returns `isLoggedIn`, `userId`, etc. |
| `useQuery` (TanStack Query) | Fetch user data from API | Used in `ApprovalCheck` component |
| Conditional rendering (`{condition && <Component />}`) | Hide UI elements | Used for admin-only links in Layout.jsx |

### Installation

No installation required - uses WordPress core and existing React patterns.

## Architecture Patterns

### Recommended Implementation Structure

```
Backend (PHP):
includes/
├── class-user-roles.php       # Add fairplay capability registration
└── class-rest-api.php          # Add capability to /user/me endpoint

Frontend (React):
src/
├── hooks/
│   └── useAuth.js              # Extend with capability check helper
├── App.jsx                     # Protected routes enforcement
└── pages/People/
    └── PersonDetail.jsx        # Conditional tab rendering
```

### Pattern 1: Capability Registration (WordPress)

**What:** Register custom capability during theme activation, auto-assign to admins
**When to use:** Theme activation hook, runs once per activation
**Example:**
```php
// In class-user-roles.php, add to register_role() method
public function register_role() {
    // Existing code...

    // Add fairplay capability to administrator role
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('fairplay');
    }
}
```

**Why this pattern:**
- Capabilities are persistent (stored in database)
- `get_role()->add_cap()` automatically applies to ALL users with that role
- No need to loop through existing administrators
- New administrators automatically inherit all role capabilities
- Survives theme deactivation if role exists

### Pattern 2: REST API Capability Exposure

**What:** Add capability check to current user endpoint response
**When to use:** Every API request to `/stadion/v1/user/me`
**Example:**
```php
// In class-rest-api.php, get_current_user() method
public function get_current_user( $request ) {
    $user_id = get_current_user_id();
    // ... existing code ...

    return rest_ensure_response([
        'id' => $user_id,
        'is_admin' => current_user_can('manage_options'),
        'is_approved' => \STADION_User_Roles::is_user_approved($user_id),
        'can_access_fairplay' => current_user_can('fairplay'), // NEW
        // ... existing fields ...
    ]);
}
```

**Why this pattern:**
- `current_user_can()` is the WordPress standard for capability checks
- Returns boolean, safe for JSON response
- Works in REST API context (user already authenticated via nonce)
- Matches existing pattern for `is_admin` field

### Pattern 3: React Route Protection

**What:** Block route access at router level, show access denied page
**When to use:** Direct URL navigation to protected routes
**Example:**
```jsx
// In App.jsx
function FairplayRoute({ children }) {
  const { data: user } = useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
  });

  if (!user?.can_access_fairplay) {
    return <AccessDeniedPage />;
  }

  return children;
}

// Usage in routes:
<Route path="/discipline-cases" element={
  <FairplayRoute>
    <DisciplineCasesList />
  </FairplayRoute>
} />
```

**Why this pattern:**
- Follows existing `ApprovalCheck` pattern in App.jsx (lines 45-122)
- Query caching prevents redundant API calls
- Returns fallback UI (not redirect) per CONTEXT.md requirements
- Works for direct URL access and navigation

### Pattern 4: Element-Level Conditional Rendering

**What:** Hide tabs/nav items based on capability, element doesn't render at all
**When to use:** Component-level UI elements (tabs, navigation, buttons)
**Example:**
```jsx
// In PersonDetail.jsx (tabs)
const { data: user } = useQuery({
  queryKey: ['current-user'],
  queryFn: async () => {
    const response = await prmApi.getCurrentUser();
    return response.data;
  },
});

const canAccessFairplay = user?.can_access_fairplay ?? false;

// In tab rendering:
{canAccessFairplay && (
  <TabButton
    active={activeTab === 'tuchtzaken'}
    onClick={() => setActiveTab('tuchtzaken')}
  >
    Tuchtzaken
  </TabButton>
)}
```

**Why this pattern:**
- Matches existing admin-only pattern in Layout.jsx (line 210)
- Element doesn't render at all (not disabled)
- Uses nullish coalescing for safe default
- Query caching prevents performance issues

### Pattern 5: Hook-Based Capability Checking (Optional Enhancement)

**What:** Custom hook for capability checks, optional for cleaner code
**When to use:** If multiple components need capability checks
**Example:**
```jsx
// In useAuth.js
export function useAuth() {
  const config = window.stadionConfig || {};

  // Fetch current user for capabilities (optional, if not in config)
  const { data: user } = useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
    enabled: config.isLoggedIn,
  });

  return useMemo(() => ({
    isLoggedIn: config.isLoggedIn || false,
    userId: config.userId || null,
    isAdmin: user?.is_admin ?? config.isAdmin ?? false,
    canAccessFairplay: user?.can_access_fairplay ?? false,
    // ... existing fields ...
  }), [config, user]);
}
```

**Why this pattern:**
- Centralizes capability logic
- Reduces code duplication
- Provides consistent API across components
- Optional - can add later if needed

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Pass capability to initial page load | New global variable | Extend `window.stadionConfig` | Already established in functions.php line 542-576 |
| Fetch user capabilities on every component | New API endpoint | Use `/stadion/v1/user/me` | Already returns user data, cached by TanStack Query |
| Create new permission callback | Custom check function | Use `current_user_can('fairplay')` | WordPress standard, works in all contexts |
| Route protection wrapper | New component pattern | Follow `ApprovalCheck` pattern | Already proven in App.jsx |
| Capability persistence | Session storage / state | WordPress database via `add_cap()` | Survives page refresh, theme deactivation |

**Key insight:** The codebase already has ALL infrastructure patterns needed. This is not greenfield - it's extending existing, proven patterns. Don't reinvent; replicate existing `is_admin` and `is_approved` flows.

## Common Pitfalls

### Pitfall 1: Forgetting Capability Removal on Deactivation

**What goes wrong:** Custom capabilities persist in database after theme deactivation, orphaned capabilities can cause confusion or conflicts

**Why it happens:** WordPress stores role capabilities in `wp_options` table permanently. Unlike post types which get flushed, capabilities remain until explicitly removed.

**How to avoid:**
```php
// In class-user-roles.php, add to remove_role() method
public function remove_role() {
    // Remove fairplay capability from all roles before removing role
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('fairplay');
    }

    // Existing role removal code...
}
```

**Warning signs:** Capability checks returning true after theme switch, WordPress admin showing unexpected permissions

**Impact:** LOW - capabilities without associated code are harmless, but proper cleanup is best practice

---

### Pitfall 2: Assuming Initial Config is Sufficient

**What goes wrong:** Using only `window.stadionConfig` for capability checks, missing updates when capabilities change

**Why it happens:** `window.stadionConfig` is set on initial page load (functions.php line 584). If capabilities change during session (admin grants permission), React doesn't know until page refresh.

**How to avoid:** Use TanStack Query to fetch `/user/me` endpoint, which provides current capability state:
```jsx
// BAD - stale on capability changes
const canAccessFairplay = window.stadionConfig?.canAccessFairplay;

// GOOD - always current via query
const { data: user } = useQuery({
  queryKey: ['current-user'],
  queryFn: async () => {
    const response = await prmApi.getCurrentUser();
    return response.data;
  },
});
const canAccessFairplay = user?.can_access_fairplay ?? false;
```

**Warning signs:** User doesn't see new features until page refresh, permissions appear inconsistent

**Impact:** MEDIUM - capability changes are rare but user experience is poor when it happens

---

### Pitfall 3: Checking Capability in REST API Response Instead of Permission Callback

**What goes wrong:** Returning 403 error in endpoint body instead of using `permission_callback`, bypassing WordPress security

**Why it happens:** Developers familiar with manual permission checks don't realize WordPress REST API has built-in authorization flow

**How to avoid:** Always use `permission_callback` in `register_rest_route()`:
```php
// BAD - manual check in callback
register_rest_route('stadion/v1', '/discipline-cases', [
    'callback' => function($request) {
        if (!current_user_can('fairplay')) {
            return new \WP_Error('forbidden', 'No access', ['status' => 403]);
        }
        // ... endpoint logic
    },
    'permission_callback' => '__return_true', // WRONG - bypasses security
]);

// GOOD - let WordPress handle authorization
register_rest_route('stadion/v1', '/discipline-cases', [
    'callback' => function($request) {
        // ... endpoint logic, capability already verified
    },
    'permission_callback' => function() {
        return current_user_can('fairplay');
    },
]);
```

**Warning signs:** Seeing authorization logic inside endpoint callbacks, endpoints always executing before failing

**Impact:** HIGH - security bypass, performance penalty (endpoint runs before checking permission), non-standard error responses

---

### Pitfall 4: Not Handling Loading States

**What goes wrong:** UI flickers or shows protected content briefly before hiding it

**Why it happens:** TanStack Query starts with no data, `user?.can_access_fairplay` is undefined initially

**How to avoid:**
```jsx
// BAD - shows content while loading
const { data: user } = useQuery({ /* ... */ });
const canAccessFairplay = user?.can_access_fairplay;

{canAccessFairplay && <ProtectedTab />} // Renders nothing initially (falsy undefined)

// GOOD - explicit loading state
const { data: user, isLoading } = useQuery({ /* ... */ });
const canAccessFairplay = user?.can_access_fairplay ?? false;

if (isLoading) {
  return <Spinner />;
}

{canAccessFairplay && <ProtectedTab />}
```

**Warning signs:** Protected elements briefly flash on screen, tabs jump around during load

**Impact:** MEDIUM - poor UX, potential information disclosure in edge cases

---

### Pitfall 5: Hardcoding Capability Name

**What goes wrong:** Capability name typo (`'fair_play'` vs `'fairplay'`) causes silent failures

**Why it happens:** PHP string literals aren't type-checked, typos in React don't fail until runtime

**How to avoid:** Define capability as constant in one place:
```php
// In class-user-roles.php
class UserRoles {
    const FAIRPLAY_CAPABILITY = 'fairplay';

    public function register_role() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap(self::FAIRPLAY_CAPABILITY);
        }
    }
}

// In REST API
'permission_callback' => function() {
    return current_user_can(\STADION_User_Roles::FAIRPLAY_CAPABILITY);
}
```

**Warning signs:** Permission checks always failing, capability appearing in database with different name

**Impact:** HIGH - complete feature failure, difficult to debug

## Code Examples

Verified patterns from existing codebase and WordPress standards:

### Capability Registration on Theme Activation
```php
// Source: WordPress Codex + existing class-user-roles.php pattern
// Location: includes/class-user-roles.php

public function register_role() {
    // Existing Stadion User role registration...
    $capabilities = $this->get_role_capabilities();
    add_role(
        self::ROLE_NAME,
        self::ROLE_DISPLAY_NAME,
        $capabilities
    );

    // Add fairplay capability to administrator role
    // This automatically applies to ALL administrators, including future ones
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('fairplay');
    }
}

public function remove_role() {
    // Remove fairplay capability before role removal
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('fairplay');
    }

    // Existing role removal code...
    $users = get_users(['role' => self::ROLE_NAME]);
    foreach ($users as $user) {
        $user->set_role('subscriber');
    }
    remove_role(self::ROLE_NAME);
}
```

---

### REST API Capability Exposure
```php
// Source: Existing includes/class-rest-api.php line 2271-2311
// Location: includes/class-rest-api.php

public function get_current_user($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new \WP_Error('not_logged_in', __('User is not logged in.', 'stadion'), ['status' => 401]);
    }

    $user = get_userdata($user_id);

    if (!$user) {
        return new \WP_Error('user_not_found', __('User not found.', 'stadion'), ['status' => 404]);
    }

    return rest_ensure_response([
        'id' => $user_id,
        'name' => $user->display_name,
        'email' => $user->user_email,
        'avatar_url' => get_avatar_url($user_id, ['size' => 96]),
        'is_admin' => current_user_can('manage_options'),
        'is_approved' => \STADION_User_Roles::is_user_approved($user_id),
        'can_access_fairplay' => current_user_can('fairplay'), // ADD THIS LINE
        'profile_url' => admin_url('profile.php'),
        'admin_url' => admin_url(),
    ]);
}
```

---

### REST API Permission Callback
```php
// Source: WordPress REST API Handbook + existing patterns in class-rest-api.php
// Location: includes/class-rest-api.php (or new class-rest-discipline-cases.php)

// NEW endpoints for discipline cases
public function register_routes() {
    register_rest_route('stadion/v1', '/discipline-cases', [
        'methods' => \WP_REST_Server::READABLE,
        'callback' => [$this, 'get_discipline_cases'],
        'permission_callback' => function() {
            return current_user_can('fairplay');
        },
    ]);

    register_rest_route('stadion/v1', '/discipline-cases/(?P<id>\d+)', [
        'methods' => \WP_REST_Server::READABLE,
        'callback' => [$this, 'get_discipline_case'],
        'permission_callback' => function() {
            return current_user_can('fairplay');
        },
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
            ],
        ],
    ]);
}
```

---

### React Route Protection
```jsx
// Source: Existing App.jsx ApprovalCheck pattern (lines 45-122)
// Location: src/App.jsx

function FairplayRoute({ children }) {
  const { data: user, isLoading } = useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
    retry: false,
  });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600"></div>
      </div>
    );
  }

  // User doesn't have fairplay capability
  if (!user?.can_access_fairplay) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="max-w-md w-full mx-4">
          <div className="card p-8 text-center">
            <div className="flex justify-center mb-4">
              <div className="p-3 bg-red-100 rounded-full">
                <Shield className="w-8 h-8 text-red-600" />
              </div>
            </div>
            <h2 className="text-2xl font-semibold text-gray-900 mb-2">
              Geen toegang
            </h2>
            <p className="text-gray-600 mb-6">
              Je hebt geen toestemming om deze pagina te bekijken.
            </p>
            <button
              onClick={() => navigate(-1)}
              className="btn btn-secondary"
            >
              Ga terug
            </button>
          </div>
        </div>
      </div>
    );
  }

  return children;
}

// Usage in route definition:
<Route path="/discipline-cases" element={
  <Suspense fallback={<PageLoader />}>
    <FairplayRoute>
      <DisciplineCasesList />
    </FairplayRoute>
  </Suspense>
} />
```

---

### Conditional Element Rendering
```jsx
// Source: Existing Layout.jsx admin check (line 210)
// Location: src/pages/People/PersonDetail.jsx

export default function PersonDetail() {
  const { id } = useParams();
  const [activeTab, setActiveTab] = useState('profile');

  // Fetch current user for capability check
  const { data: user } = useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
  });

  const canAccessFairplay = user?.can_access_fairplay ?? false;

  // ... existing code ...

  return (
    <div className="tabs">
      <TabButton
        active={activeTab === 'profile'}
        onClick={() => setActiveTab('profile')}
      >
        Profiel
      </TabButton>

      {/* Only render tab if user has fairplay capability */}
      {canAccessFairplay && (
        <TabButton
          active={activeTab === 'tuchtzaken'}
          onClick={() => setActiveTab('tuchtzaken')}
        >
          Tuchtzaken
        </TabButton>
      )}

      {/* Tab content */}
      {activeTab === 'tuchtzaken' && canAccessFairplay && (
        <DisciplineCasesTab personId={id} />
      )}
    </div>
  );
}
```

---

### Navigation Item Conditional Rendering
```jsx
// Source: Existing Layout.jsx pattern
// Location: src/components/layout/Layout.jsx (or Navigation component)

function Navigation() {
  const { data: user } = useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
  });

  const canAccessFairplay = user?.can_access_fairplay ?? false;

  return (
    <nav>
      <NavLink to="/people">Mensen</NavLink>
      <NavLink to="/teams">Teams</NavLink>

      {/* Only show if user has fairplay capability */}
      {canAccessFairplay && (
        <NavLink to="/discipline-cases">Tuchtzaken</NavLink>
      )}

      <NavLink to="/settings">Instellingen</NavLink>
    </nav>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Check roles (`in_array('administrator', $user->roles)`) | Check capabilities (`current_user_can('capability')`) | WordPress 2.0 (2005) | Flexible permission system, role-independent |
| Pass all permissions via config | Fetch on-demand via API | TanStack Query era (2020+) | Fresh data, better caching, smaller initial payload |
| Disable UI elements | Hide UI elements completely | Modern UX pattern | Security through obscurity principle |
| Global `$current_user` | `get_current_user_id()` + `current_user_can()` | WordPress 3.0+ | Thread-safe, no global pollution |
| Custom session storage for permissions | WordPress database (`wp_options`) | Core WordPress | Survives sessions, consistent across requests |

**Deprecated/outdated:**
- **Storing capabilities in user meta**: Capabilities belong to roles, not individual users. User-specific permissions should use role assignment, not meta fields.
- **Using `$wp_roles` global directly**: Use `get_role()` function instead - proper abstraction, handles caching.
- **Checking `is_admin()` for permissions**: That checks if you're in admin dashboard, not user role. Use `current_user_can()` for permissions.

## Open Questions

None - research domain is well-documented and patterns are established in WordPress core and existing codebase.

## Sources

### Primary (HIGH confidence)
- [WordPress Roles and Capabilities – Plugin Handbook](https://developer.wordpress.org/plugins/users/roles-and-capabilities/)
- [WP_Role::add_cap() – WordPress Developer Reference](https://developer.wordpress.org/reference/classes/wp_role/add_cap/)
- [current_user_can() – WordPress Developer Reference](https://developer.wordpress.org/reference/functions/current_user_can/)
- [WordPress REST API Users Documentation](https://developer.wordpress.org/rest-api/reference/users/)
- Existing codebase:
  - `includes/class-user-roles.php` (lines 14-443) - Role registration patterns
  - `includes/class-rest-api.php` (line 2271-2311) - Current user endpoint pattern
  - `includes/class-rest-base.php` (lines 35-60) - Permission callback patterns
  - `src/App.jsx` (lines 45-122) - ApprovalCheck route protection pattern
  - `src/components/layout/Layout.jsx` (line 210) - Admin-only conditional rendering
  - `src/hooks/useAuth.js` - Authentication state management

### Secondary (MEDIUM confidence)
- [Implementing Role Based Access Control in React](https://www.permit.io/blog/implementing-react-rbac-authorization)
- [An elegant pattern to implement Authorization checks in React](https://guillermodlpa.com/blog/an-elegant-pattern-to-implement-authorization-checks-in-react)
- [Handling Permissions in WordPress REST routes](https://dev.to/david_woolf/handling-permissions-in-your-wordpress-rest-routes-c6j)

### Tertiary (LOW confidence)
- None - all findings verified with official docs or existing code

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress core functions, existing React patterns proven in codebase
- Architecture: HIGH - Follows established patterns from `is_admin` and `is_approved` implementations
- Pitfalls: HIGH - Identified from WordPress Codex warnings and common capability implementation mistakes

**Research date:** 2026-02-03
**Valid until:** 90 days (WordPress capability system is stable, React patterns are established)
